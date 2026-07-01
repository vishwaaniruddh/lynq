<?php
/**
 * Inventory Access Service
 * Handles role-based access control for inventory operations
 * 
 * Requirements: 8.1, 8.2, 8.3, 8.4, 11.2
 * - 8.1: ADV users can access all warehouses, all contractor allocations, and full repair/scrap history
 * - 8.2: Contractor users can only access inventory delegated to their company and their engineers
 * - 8.3: Engineers can only access items assigned to them with limited status update capabilities
 * - 8.4: Deny actions beyond permission level and log the attempt
 * - 11.2: Limit engineer status updates to: In Use, Returned, Working, Not Working
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/StockRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../services/CompanyIsolationService.php';

class InventoryAccessService {
    private $db;
    private $warehouseRepository;
    private $assetRepository;
    private $stockRepository;
    private $companyRepository;
    private $companyIsolationService;
    private $userModel;
    private $roleModel;
    
    // Role types for inventory access
    const ROLE_ADV = 'ADV';
    const ROLE_CONTRACTOR = 'CONTRACTOR';
    const ROLE_ENGINEER = 'ENGINEER';
    
    // Engineer-allowed status updates (Requirement 11.2)
    private static $engineerAllowedStatuses = [
        AssetRepository::STATUS_IN_USE,
        AssetRepository::STATUS_RETURNED
    ];
    
    // Engineer-allowed working condition updates
    private static $engineerAllowedConditions = [
        AssetRepository::CONDITION_WORKING,
        AssetRepository::CONDITION_NOT_WORKING
    ];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->assetRepository = new AssetRepository();
        $this->stockRepository = new StockRepository();
        $this->companyRepository = new CompanyRepository();
        $this->companyRepository->disableCompanyFilter();
        $this->companyIsolationService = new CompanyIsolationService();
        $this->userModel = new User();
        $this->roleModel = new Role();
    }
    
    /**
     * Get user's role type for inventory access
     * Returns: ADV, CONTRACTOR, or ENGINEER
     */
    public function getUserRoleType(int $userId): ?string {
        $user = $this->userModel->findWithRelations($userId);
        if (!$user) {
            return null;
        }
        
        // Check company type first
        $companyType = strtoupper($user['company_type'] ?? '');
        
        if ($companyType === 'ADV') {
            return self::ROLE_ADV;
        }
        
        // For contractor company users, check if they are an engineer
        if ($companyType === 'CONTRACTOR') {
            // Check role level - engineers typically have lower level
            $roleLevel = (int)($user['role_level'] ?? 0);
            $roleName = strtolower($user['role_name'] ?? '');
            
            // Engineer detection: role name contains 'engineer' or level is low (field level)
            if (strpos($roleName, 'engineer') !== false || $roleLevel <= 2) {
                return self::ROLE_ENGINEER;
            }
            
            return self::ROLE_CONTRACTOR;
        }
        
        return null;
    }
    
    /**
     * Get warehouses accessible to a user based on their role
     * Requirement 8.1, 8.2, 8.3
     * 
     * @param int $userId User ID
     * @return array List of accessible warehouses
     */
    public function getAccessibleWarehouses(int $userId): array {
        $roleType = $this->getUserRoleType($userId);
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user || !$roleType) {
            $this->logAccessAttempt($userId, 'warehouse', 'list', 'DENIED', 'Invalid user or role');
            return [];
        }
        
        switch ($roleType) {
            case self::ROLE_ADV:
                // ADV users can access all warehouses
                return $this->warehouseRepository->findAllWithCompany();
                
            case self::ROLE_CONTRACTOR:
                // Contractor users can only access warehouses belonging to their company
                return $this->warehouseRepository->findByCompanyId($user['company_id']);
                
            case self::ROLE_ENGINEER:
                // Engineers can only see warehouses where they have assigned items
                return $this->getWarehousesWithAssignedItems($userId);
                
            default:
                return [];
        }
    }
    
    /**
     * Get inventory accessible to a user based on their role
     * Requirement 8.1, 8.2, 8.3
     * 
     * @param int $userId User ID
     * @param array $filters Optional filters (product_id, warehouse_id, status, etc.)
     * @return array List of accessible inventory items
     */
    public function getAccessibleInventory(int $userId, array $filters = []): array {
        $roleType = $this->getUserRoleType($userId);
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user || !$roleType) {
            $this->logAccessAttempt($userId, 'inventory', 'list', 'DENIED', 'Invalid user or role');
            return [];
        }
        
        switch ($roleType) {
            case self::ROLE_ADV:
                // ADV users can access all inventory
                return $this->getAllInventory($filters);
                
            case self::ROLE_CONTRACTOR:
                // Contractor users can only access inventory delegated to their company
                return $this->getContractorInventory($user['company_id'], $filters);
                
            case self::ROLE_ENGINEER:
                // Engineers can only access items assigned to them
                return $this->getEngineerInventory($userId, $filters);
                
            default:
                return [];
        }
    }
    
    /**
     * Check if user can dispatch from a warehouse
     * Requirement 8.4
     * 
     * @param int $userId User ID
     * @param int $warehouseId Warehouse ID
     * @return bool True if user can dispatch from warehouse
     */
    public function canDispatchFrom(int $userId, int $warehouseId): bool {
        $roleType = $this->getUserRoleType($userId);
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user || !$roleType) {
            $this->logAccessAttempt($userId, 'warehouse', 'dispatch_from', 'DENIED', 'Invalid user or role');
            return false;
        }
        
        // Get warehouse details
        $warehouse = $this->warehouseRepository->find($warehouseId);
        if (!$warehouse) {
            return false;
        }
        
        // Check if warehouse is active
        if ($warehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            $this->logAccessAttempt($userId, 'warehouse', 'dispatch_from', 'DENIED', 'Warehouse inactive');
            return false;
        }
        
        switch ($roleType) {
            case self::ROLE_ADV:
                // ADV users can dispatch from any active warehouse
                return true;
                
            case self::ROLE_CONTRACTOR:
                // Contractor users can only dispatch from their company's warehouses
                $canDispatch = (int)$warehouse['company_id'] === (int)$user['company_id'];
                if (!$canDispatch) {
                    $this->logAccessAttempt($userId, 'warehouse', 'dispatch_from', 'DENIED', 'Cross-company dispatch attempt');
                }
                return $canDispatch;
                
            case self::ROLE_ENGINEER:
                // Engineers cannot dispatch
                $this->logAccessAttempt($userId, 'warehouse', 'dispatch_from', 'DENIED', 'Engineers cannot dispatch');
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Check if user can dispatch to a destination
     * Requirement 8.4
     * 
     * @param int $userId User ID
     * @param mixed $destination Destination (company_id, user_id, or warehouse_id)
     * @param string $destinationType Type of destination ('company', 'user', 'warehouse')
     * @return bool True if user can dispatch to destination
     */
    public function canDispatchTo(int $userId, $destination, string $destinationType): bool {
        $roleType = $this->getUserRoleType($userId);
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user || !$roleType) {
            $this->logAccessAttempt($userId, 'dispatch', 'dispatch_to', 'DENIED', 'Invalid user or role');
            return false;
        }
        
        switch ($roleType) {
            case self::ROLE_ADV:
                // ADV users can dispatch to any valid destination
                return $this->isValidDestination($destination, $destinationType);
                
            case self::ROLE_CONTRACTOR:
                // Contractor users can dispatch to their own engineers or warehouses
                return $this->isContractorValidDestination($user['company_id'], $destination, $destinationType);
                
            case self::ROLE_ENGINEER:
                // Engineers cannot dispatch
                $this->logAccessAttempt($userId, 'dispatch', 'dispatch_to', 'DENIED', 'Engineers cannot dispatch');
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Check if user can update asset status
     * Requirement 8.4, 11.2
     * 
     * @param int $userId User ID
     * @param int $assetId Asset ID
     * @param string $newStatus New status to set
     * @return array Result with success status and message
     */
    public function canUpdateAssetStatus(int $userId, int $assetId, string $newStatus): array {
        $roleType = $this->getUserRoleType($userId);
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user || !$roleType) {
            $this->logAccessAttempt($userId, 'asset', 'update_status', 'DENIED', 'Invalid user or role');
            return [
                'success' => false,
                'message' => 'Invalid user or role',
                'code' => 'INVALID_USER'
            ];
        }
        
        // Get asset details
        $asset = $this->assetRepository->findWithDetails($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        switch ($roleType) {
            case self::ROLE_ADV:
                // ADV users can update any status
                return ['success' => true];
                
            case self::ROLE_CONTRACTOR:
                // Contractor users can update status for assets in their company
                if (!$this->isAssetInCompanyScope($asset, $user['company_id'])) {
                    $this->logAccessAttempt($userId, 'asset', 'update_status', 'DENIED', 'Asset not in company scope');
                    return [
                        'success' => false,
                        'message' => 'You do not have access to this asset',
                        'code' => 'ACCESS_DENIED'
                    ];
                }
                return ['success' => true];
                
            case self::ROLE_ENGINEER:
                // Engineers can only update status for assets assigned to them
                // and only to specific statuses (Requirement 11.2)
                return $this->validateEngineerStatusUpdate($userId, $asset, $newStatus);
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown role type',
                    'code' => 'UNKNOWN_ROLE'
                ];
        }
    }
    
    /**
     * Check if user can update asset working condition
     * Requirement 11.2
     * 
     * @param int $userId User ID
     * @param int $assetId Asset ID
     * @param string $newCondition New working condition
     * @return array Result with success status and message
     */
    public function canUpdateWorkingCondition(int $userId, int $assetId, string $newCondition): array {
        $roleType = $this->getUserRoleType($userId);
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user || !$roleType) {
            return [
                'success' => false,
                'message' => 'Invalid user or role',
                'code' => 'INVALID_USER'
            ];
        }
        
        // Get asset details
        $asset = $this->assetRepository->findWithDetails($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        switch ($roleType) {
            case self::ROLE_ADV:
            case self::ROLE_CONTRACTOR:
                // ADV and Contractor users can update any working condition
                return ['success' => true];
                
            case self::ROLE_ENGINEER:
                // Engineers can only update condition for assets assigned to them
                // and only to Working or Not Working
                return $this->validateEngineerConditionUpdate($userId, $asset, $newCondition);
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown role type',
                    'code' => 'UNKNOWN_ROLE'
                ];
        }
    }
    
    /**
     * Get statuses allowed for engineer updates
     * Requirement 11.2
     * 
     * @return array Allowed statuses for engineers
     */
    public function getEngineerAllowedStatuses(): array {
        return self::$engineerAllowedStatuses;
    }
    
    /**
     * Get working conditions allowed for engineer updates
     * 
     * @return array Allowed conditions for engineers
     */
    public function getEngineerAllowedConditions(): array {
        return self::$engineerAllowedConditions;
    }
    
    /**
     * Check if a status is allowed for engineer updates
     * Requirement 11.2
     * 
     * @param string $status Status to check
     * @return bool True if engineer can update to this status
     */
    public function isEngineerAllowedStatus(string $status): bool {
        return in_array($status, self::$engineerAllowedStatuses);
    }
    
    /**
     * Check if a working condition is allowed for engineer updates
     * 
     * @param string $condition Condition to check
     * @return bool True if engineer can update to this condition
     */
    public function isEngineerAllowedCondition(string $condition): bool {
        return in_array($condition, self::$engineerAllowedConditions);
    }
    
    // ==================== Private Helper Methods ====================
    
    /**
     * Get all inventory (for ADV users)
     */
    private function getAllInventory(array $filters = []): array {
        $assets = $this->assetRepository->search($filters);
        $stock = $this->stockRepository->findAllWithDetails($filters);
        
        return [
            'assets' => $assets,
            'stock' => $stock
        ];
    }
    
    /**
     * Get inventory for contractor company
     */
    private function getContractorInventory(int $companyId, array $filters = []): array {
        // Get assets delegated to this company (current holder is company or company's users)
        $assets = $this->getAssetsByCompanyScope($companyId, $filters);
        
        // Get stock in company's warehouses
        $stock = $this->getStockByCompany($companyId, $filters);
        
        return [
            'assets' => $assets,
            'stock' => $stock
        ];
    }
    
    /**
     * Get inventory for engineer
     */
    private function getEngineerInventory(int $userId, array $filters = []): array {
        // Get assets assigned to this engineer
        $sql = "SELECT a.*, p.name as product_name, w.name as warehouse_name
                FROM assets a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                WHERE a.current_holder_type = 'user' AND a.current_holder_id = ?";
        $params = [$userId];
        $types = 'i';
        
        // Apply additional filters
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['working_condition'])) {
            $sql .= " AND a.working_condition = ?";
            $params[] = $filters['working_condition'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY a.serial_number";
        
        $assets = $this->db->getResults($sql, $params, $types);
        
        return [
            'assets' => $assets,
            'stock' => [] // Engineers don't have access to stock
        ];
    }
    
    /**
     * Get warehouses where user has assigned items
     */
    private function getWarehousesWithAssignedItems(int $userId): array {
        $sql = "SELECT DISTINCT w.*, c.name as company_name, c.type as company_type
                FROM warehouses w
                LEFT JOIN companies c ON w.company_id = c.id
                INNER JOIN assets a ON a.source_warehouse_id = w.id
                WHERE a.current_holder_type = 'user' AND a.current_holder_id = ?";
        
        return $this->db->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Get assets by company scope
     */
    private function getAssetsByCompanyScope(int $companyId, array $filters = []): array {
        $sql = "SELECT a.*, p.name as product_name, w.name as warehouse_name
                FROM assets a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN users u ON a.current_holder_type = 'user' AND a.current_holder_id = u.id
                WHERE (
                    (a.current_holder_type = 'company' AND a.current_holder_id = ?)
                    OR (a.current_holder_type = 'user' AND u.company_id = ?)
                    OR (a.current_holder_type = 'warehouse' AND w.company_id = ?)
                )";
        $params = [$companyId, $companyId, $companyId];
        $types = 'iii';
        
        // Apply additional filters
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY a.serial_number";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get stock by company
     */
    private function getStockByCompany(int $companyId, array $filters = []): array {
        $sql = "SELECT s.*, p.name as product_name, w.name as warehouse_name
                FROM stock s
                LEFT JOIN products p ON s.product_id = p.id
                LEFT JOIN warehouses w ON s.warehouse_id = w.id
                WHERE w.company_id = ?";
        $params = [$companyId];
        $types = 'i';
        
        if (!empty($filters['product_id'])) {
            $sql .= " AND s.product_id = ?";
            $params[] = $filters['product_id'];
            $types .= 'i';
        }
        
        $sql .= " ORDER BY p.name";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Check if asset is in company scope
     */
    private function isAssetInCompanyScope(array $asset, int $companyId): bool {
        // Check if asset is held by company
        if ($asset['current_holder_type'] === 'company' && (int)$asset['current_holder_id'] === $companyId) {
            return true;
        }
        
        // Check if asset is held by a user in the company
        if ($asset['current_holder_type'] === 'user') {
            $holder = $this->userModel->find($asset['current_holder_id']);
            if ($holder && (int)$holder['company_id'] === $companyId) {
                return true;
            }
        }
        
        // Check if asset is in a warehouse belonging to the company
        if ($asset['current_holder_type'] === 'warehouse') {
            $warehouse = $this->warehouseRepository->find($asset['current_holder_id']);
            if ($warehouse && (int)$warehouse['company_id'] === $companyId) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate engineer status update
     * Requirement 11.2
     */
    private function validateEngineerStatusUpdate(int $userId, array $asset, string $newStatus): array {
        // Check if asset is assigned to this engineer
        if ($asset['current_holder_type'] !== 'user' || (int)$asset['current_holder_id'] !== $userId) {
            $this->logAccessAttempt($userId, 'asset', 'update_status', 'DENIED', 'Asset not assigned to engineer');
            return [
                'success' => false,
                'message' => 'You can only update status for assets assigned to you',
                'code' => 'NOT_ASSIGNED'
            ];
        }
        
        // Check if status is allowed for engineers
        if (!$this->isEngineerAllowedStatus($newStatus)) {
            $this->logAccessAttempt($userId, 'asset', 'update_status', 'DENIED', "Engineer attempted status: $newStatus");
            return [
                'success' => false,
                'message' => "Engineers can only update status to: " . implode(', ', self::$engineerAllowedStatuses),
                'code' => 'STATUS_NOT_ALLOWED',
                'data' => [
                    'requested_status' => $newStatus,
                    'allowed_statuses' => self::$engineerAllowedStatuses
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Validate engineer working condition update
     */
    private function validateEngineerConditionUpdate(int $userId, array $asset, string $newCondition): array {
        // Check if asset is assigned to this engineer
        if ($asset['current_holder_type'] !== 'user' || (int)$asset['current_holder_id'] !== $userId) {
            return [
                'success' => false,
                'message' => 'You can only update condition for assets assigned to you',
                'code' => 'NOT_ASSIGNED'
            ];
        }
        
        // Check if condition is allowed for engineers
        if (!$this->isEngineerAllowedCondition($newCondition)) {
            return [
                'success' => false,
                'message' => "Engineers can only update condition to: " . implode(', ', self::$engineerAllowedConditions),
                'code' => 'CONDITION_NOT_ALLOWED',
                'data' => [
                    'requested_condition' => $newCondition,
                    'allowed_conditions' => self::$engineerAllowedConditions
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Check if destination is valid
     */
    private function isValidDestination($destination, string $destinationType): bool {
        switch ($destinationType) {
            case 'company':
                $company = $this->companyRepository->find($destination);
                return $company !== null;
                
            case 'user':
                $user = $this->userModel->find($destination);
                return $user !== null;
                
            case 'warehouse':
                $warehouse = $this->warehouseRepository->find($destination);
                return $warehouse !== null && $warehouse['status'] === WarehouseRepository::STATUS_ACTIVE;
                
            default:
                return false;
        }
    }
    
    /**
     * Check if destination is valid for contractor
     */
    private function isContractorValidDestination(int $companyId, $destination, string $destinationType): bool {
        switch ($destinationType) {
            case 'user':
                // Can only dispatch to users in their own company
                $user = $this->userModel->find($destination);
                return $user !== null && (int)$user['company_id'] === $companyId;
                
            case 'warehouse':
                // Can only dispatch to warehouses in their own company
                $warehouse = $this->warehouseRepository->find($destination);
                return $warehouse !== null && 
                       (int)$warehouse['company_id'] === $companyId && 
                       $warehouse['status'] === WarehouseRepository::STATUS_ACTIVE;
                
            case 'company':
                // Contractors cannot dispatch to other companies
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Log access attempt for security auditing
     * Requirement 8.4
     */
    private function logAccessAttempt(int $userId, string $resourceType, string $action, string $result, string $reason = null): void {
        try {
            $sql = "INSERT INTO inventory_audit_log 
                    (action_type, entity_type, entity_id, user_id, new_values, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details = json_encode([
                'result' => $result,
                'reason' => $reason,
                'resource_type' => $resourceType
            ]);
            
            $stmt = $this->db->executeQuery($sql, [
                "access_$action",
                $resourceType,
                0,
                $userId,
                $details,
                $ipAddress
            ], 'ssiiss');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log access attempt: " . $e->getMessage());
        }
    }
}
