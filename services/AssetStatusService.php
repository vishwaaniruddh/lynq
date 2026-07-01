<?php
/**
 * Asset Status Service
 * Manages asset status transitions with validation and business rules
 * 
 * Requirements: 6.1, 6.3
 * - 6.1: Update status to one of: In Stock, Dispatched, Assigned, In Use, Returned, Under Repair, Scrapped, or Lost
 * - 6.3: Lock items marked as "Lost" from further transactions and flag for audit review
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/AssetRepository.php';
require_once __DIR__ . '/../repositories/ProductRepository.php';
require_once __DIR__ . '/../repositories/InventoryAuditLogRepository.php';
require_once __DIR__ . '/InventoryAccessService.php';

class AssetStatusService {
    private $db;
    private $assetRepository;
    private $productRepository;
    private $auditLogRepository;
    private $inventoryAccessService;
    
    // Valid status transitions map
    // Key: current status, Value: array of allowed next statuses
    private static $validTransitions = [
        AssetRepository::STATUS_IN_STOCK => [
            AssetRepository::STATUS_DISPATCHED,
            AssetRepository::STATUS_ASSIGNED,
            AssetRepository::STATUS_IN_USE,
            AssetRepository::STATUS_UNDER_REPAIR,
            AssetRepository::STATUS_SCRAPPED,
            AssetRepository::STATUS_LOST
        ],
        AssetRepository::STATUS_DISPATCHED => [
            AssetRepository::STATUS_IN_STOCK,
            AssetRepository::STATUS_ASSIGNED,
            AssetRepository::STATUS_IN_USE,
            AssetRepository::STATUS_RETURNED,
            AssetRepository::STATUS_LOST
        ],
        AssetRepository::STATUS_ASSIGNED => [
            AssetRepository::STATUS_IN_STOCK,
            AssetRepository::STATUS_IN_USE,
            AssetRepository::STATUS_RETURNED,
            AssetRepository::STATUS_UNDER_REPAIR,
            AssetRepository::STATUS_LOST
        ],
        AssetRepository::STATUS_IN_USE => [
            AssetRepository::STATUS_RETURNED,
            AssetRepository::STATUS_UNDER_REPAIR,
            AssetRepository::STATUS_LOST
        ],
        AssetRepository::STATUS_RETURNED => [
            AssetRepository::STATUS_IN_STOCK,
            AssetRepository::STATUS_ASSIGNED,
            AssetRepository::STATUS_UNDER_REPAIR,
            AssetRepository::STATUS_SCRAPPED,
            AssetRepository::STATUS_LOST
        ],
        AssetRepository::STATUS_UNDER_REPAIR => [
            AssetRepository::STATUS_IN_STOCK,
            AssetRepository::STATUS_SCRAPPED,
            AssetRepository::STATUS_LOST
        ],
        // Terminal states - no transitions allowed
        AssetRepository::STATUS_SCRAPPED => [],
        AssetRepository::STATUS_LOST => []
    ];
    
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
        $this->assetRepository = new AssetRepository();
        $this->productRepository = new ProductRepository();
        $this->auditLogRepository = new InventoryAuditLogRepository();
        $this->inventoryAccessService = new InventoryAccessService();
    }
    
    /**
     * Update asset status with transition validation
     * Requirement 6.1: Update status to valid statuses only
     * 
     * @param int $assetId Asset ID
     * @param string $newStatus New status to set
     * @param int|null $userId User performing the action
     * @param array $options Additional options (e.g., 'force' => true to bypass transition validation)
     * @return array Result with success status
     */
    public function updateStatus(int $assetId, string $newStatus, ?int $userId = null, array $options = []): array {
        // Validate new status is valid
        if (!AssetRepository::isValidStatus($newStatus)) {
            return [
                'success' => false,
                'message' => "Invalid status: $newStatus",
                'code' => 'INVALID_STATUS'
            ];
        }
        
        // Get current asset
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        $currentStatus = $asset['status'];
        
        // Check if asset is locked (Lost or Scrapped)
        // Requirement 6.3: Lock items marked as "Lost" from further transactions
        if ($this->isAssetLocked($asset)) {
            return [
                'success' => false,
                'message' => "Asset is locked in status '{$currentStatus}' and cannot be modified",
                'code' => 'ASSET_LOCKED'
            ];
        }
        
        // Validate status transition (unless force option is set)
        if (empty($options['force'])) {
            $transitionValidation = $this->validateStatusTransition($currentStatus, $newStatus);
            if (!$transitionValidation['success']) {
                return $transitionValidation;
            }
        }
        
        // Apply status-specific business rules
        $businessRuleResult = $this->applyStatusBusinessRules($asset, $newStatus, $userId);
        if (!$businessRuleResult['success']) {
            return $businessRuleResult;
        }
        
        try {
            // Update the status
            $this->assetRepository->updateStatus($assetId, $newStatus, $userId);
            
            // Log audit entry
            $this->logAuditEntry(
                'status_changed',
                'asset',
                $assetId,
                $userId,
                null,
                null,
                null,
                null,
                [
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'serial_number' => $asset['serial_number']
                ]
            );
            
            return [
                'success' => true,
                'message' => "Asset status updated from '$currentStatus' to '$newStatus'",
                'data' => [
                    'asset_id' => $assetId,
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
                'code' => 'UPDATE_STATUS_ERROR'
            ];
        }
    }
    
    /**
     * Update asset working condition
     * 
     * @param int $assetId Asset ID
     * @param string $condition New working condition
     * @param int|null $userId User performing the action
     * @return array Result with success status
     */
    public function updateWorkingCondition(int $assetId, string $condition, ?int $userId = null): array {
        // Validate condition
        if (!AssetRepository::isValidWorkingCondition($condition)) {
            return [
                'success' => false,
                'message' => "Invalid working condition: $condition",
                'code' => 'INVALID_CONDITION'
            ];
        }
        
        // Get current asset
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        // Check if asset is locked
        if ($this->isAssetLocked($asset)) {
            return [
                'success' => false,
                'message' => "Asset is locked and cannot be modified",
                'code' => 'ASSET_LOCKED'
            ];
        }
        
        $oldCondition = $asset['working_condition'];
        
        try {
            $this->assetRepository->updateWorkingCondition($assetId, $condition, $userId);
            
            // Log audit entry
            $this->logAuditEntry(
                'condition_changed',
                'asset',
                $assetId,
                $userId,
                null,
                null,
                null,
                null,
                [
                    'old_condition' => $oldCondition,
                    'new_condition' => $condition,
                    'serial_number' => $asset['serial_number']
                ]
            );
            
            return [
                'success' => true,
                'message' => "Working condition updated from '$oldCondition' to '$condition'",
                'data' => [
                    'asset_id' => $assetId,
                    'old_condition' => $oldCondition,
                    'new_condition' => $condition
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update working condition: ' . $e->getMessage(),
                'code' => 'UPDATE_CONDITION_ERROR'
            ];
        }
    }

    
    /**
     * Mark asset as lost
     * Requirement 6.3: Lock the item from further transactions and flag for audit review
     * 
     * @param int $assetId Asset ID
     * @param int|null $userId User performing the action
     * @param string|null $notes Notes about the loss
     * @return array Result with success status
     */
    public function markAsLost(int $assetId, ?int $userId = null, ?string $notes = null): array {
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        // Check if already lost
        if ($asset['status'] === AssetRepository::STATUS_LOST) {
            return [
                'success' => false,
                'message' => 'Asset is already marked as lost',
                'code' => 'ALREADY_LOST'
            ];
        }
        
        // Check if scrapped (cannot mark scrapped items as lost)
        if ($asset['status'] === AssetRepository::STATUS_SCRAPPED) {
            return [
                'success' => false,
                'message' => 'Cannot mark scrapped asset as lost',
                'code' => 'ASSET_SCRAPPED'
            ];
        }
        
        try {
            $updateData = [
                'status' => AssetRepository::STATUS_LOST,
                'updated_by' => $userId
            ];
            
            if ($notes !== null) {
                $updateData['notes'] = $notes;
            }
            
            $this->assetRepository->update($assetId, $updateData);
            
            // Log audit entry with flag for review
            $this->logAuditEntry(
                'asset_lost',
                'asset',
                $assetId,
                $userId,
                null,
                null,
                null,
                null,
                [
                    'old_status' => $asset['status'],
                    'serial_number' => $asset['serial_number'],
                    'notes' => $notes,
                    'requires_audit_review' => true
                ]
            );
            
            return [
                'success' => true,
                'message' => 'Asset marked as lost and flagged for audit review',
                'data' => [
                    'asset_id' => $assetId,
                    'status' => AssetRepository::STATUS_LOST
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to mark asset as lost: ' . $e->getMessage(),
                'code' => 'MARK_LOST_ERROR'
            ];
        }
    }
    
    /**
     * Mark asset as scrapped
     * 
     * @param int $assetId Asset ID
     * @param int|null $userId User performing the action
     * @param string|null $reason Reason for scrapping
     * @return array Result with success status
     */
    public function markAsScrapped(int $assetId, ?int $userId = null, ?string $reason = null): array {
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        // Check if already scrapped
        if ($asset['status'] === AssetRepository::STATUS_SCRAPPED) {
            return [
                'success' => false,
                'message' => 'Asset is already scrapped',
                'code' => 'ALREADY_SCRAPPED'
            ];
        }
        
        // Check if lost (cannot scrap lost items)
        if ($asset['status'] === AssetRepository::STATUS_LOST) {
            return [
                'success' => false,
                'message' => 'Cannot scrap a lost asset',
                'code' => 'ASSET_LOST'
            ];
        }
        
        try {
            $updateData = [
                'status' => AssetRepository::STATUS_SCRAPPED,
                'updated_by' => $userId
            ];
            
            if ($reason !== null) {
                $updateData['notes'] = $reason;
            }
            
            $this->assetRepository->update($assetId, $updateData);
            
            // Log audit entry
            $this->logAuditEntry(
                'asset_scrapped',
                'asset',
                $assetId,
                $userId,
                null,
                null,
                null,
                null,
                [
                    'old_status' => $asset['status'],
                    'serial_number' => $asset['serial_number'],
                    'reason' => $reason
                ]
            );
            
            return [
                'success' => true,
                'message' => 'Asset marked as scrapped',
                'data' => [
                    'asset_id' => $assetId,
                    'status' => AssetRepository::STATUS_SCRAPPED
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to scrap asset: ' . $e->getMessage(),
                'code' => 'SCRAP_ERROR'
            ];
        }
    }
    
    /**
     * Validate status transition
     * 
     * @param string $currentStatus Current status
     * @param string $newStatus New status
     * @return array Validation result
     */
    public function validateStatusTransition(string $currentStatus, string $newStatus): array {
        // Same status is always valid (no-op)
        if ($currentStatus === $newStatus) {
            return ['success' => true];
        }
        
        // Check if current status has defined transitions
        if (!isset(self::$validTransitions[$currentStatus])) {
            return [
                'success' => false,
                'message' => "Unknown current status: $currentStatus",
                'code' => 'UNKNOWN_STATUS'
            ];
        }
        
        // Check if transition is allowed
        $allowedTransitions = self::$validTransitions[$currentStatus];
        if (!in_array($newStatus, $allowedTransitions)) {
            return [
                'success' => false,
                'message' => "Invalid status transition from '$currentStatus' to '$newStatus'",
                'code' => 'INVALID_TRANSITION',
                'data' => [
                    'current_status' => $currentStatus,
                    'requested_status' => $newStatus,
                    'allowed_transitions' => $allowedTransitions
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Check if asset is locked (cannot be modified)
     * Requirement 6.3: Lost items are locked from further transactions
     * 
     * @param array $asset Asset data
     * @return bool True if asset is locked
     */
    public function isAssetLocked(array $asset): bool {
        return in_array($asset['status'], AssetRepository::getLockedStatuses());
    }
    
    /**
     * Check if asset can be dispatched
     * 
     * @param int $assetId Asset ID
     * @return array Result with success status
     */
    public function canDispatch(int $assetId): array {
        $asset = $this->assetRepository->find($assetId);
        if (!$asset) {
            return [
                'success' => false,
                'message' => 'Asset not found',
                'code' => 'ASSET_NOT_FOUND'
            ];
        }
        
        // Check if locked
        if ($this->isAssetLocked($asset)) {
            return [
                'success' => false,
                'message' => "Asset is locked in status '{$asset['status']}' and cannot be dispatched",
                'code' => 'ASSET_LOCKED'
            ];
        }
        
        // Check if in stock
        if ($asset['status'] !== AssetRepository::STATUS_IN_STOCK) {
            return [
                'success' => false,
                'message' => "Asset must be 'in_stock' to be dispatched (current: {$asset['status']})",
                'code' => 'NOT_IN_STOCK'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Asset can be dispatched'
        ];
    }
    
    /**
     * Get allowed transitions for a status
     * 
     * @param string $status Current status
     * @return array Allowed next statuses
     */
    public function getAllowedTransitions(string $status): array {
        return self::$validTransitions[$status] ?? [];
    }
    
    /**
     * Get all valid statuses
     * 
     * @return array All valid statuses
     */
    public function getValidStatuses(): array {
        return AssetRepository::getStatuses();
    }
    
    /**
     * Check if engineer can update to this status
     * Requirement 11.2: Limit engineer status updates
     * 
     * @param string $status Status to check
     * @return bool True if engineer can update to this status
     */
    public function isEngineerAllowedStatus(string $status): bool {
        return in_array($status, self::$engineerAllowedStatuses);
    }
    
    /**
     * Get statuses allowed for engineer updates
     * 
     * @return array Allowed statuses for engineers
     */
    public function getEngineerAllowedStatuses(): array {
        return self::$engineerAllowedStatuses;
    }
    
    /**
     * Check if engineer can update to this working condition
     * 
     * @param string $condition Condition to check
     * @return bool True if engineer can update to this condition
     */
    public function isEngineerAllowedCondition(string $condition): bool {
        return in_array($condition, self::$engineerAllowedConditions);
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
     * Update asset status with role-based access control
     * Requirement 11.2: Limit engineer status updates to: In Use, Returned, Working, Not Working
     * 
     * @param int $assetId Asset ID
     * @param string $newStatus New status to set
     * @param int $userId User performing the action
     * @param array $options Additional options
     * @return array Result with success status
     */
    public function updateStatusWithAccessControl(int $assetId, string $newStatus, int $userId, array $options = []): array {
        // Check access control first
        $accessCheck = $this->inventoryAccessService->canUpdateAssetStatus($userId, $assetId, $newStatus);
        if (!$accessCheck['success']) {
            return $accessCheck;
        }
        
        // Proceed with normal status update
        return $this->updateStatus($assetId, $newStatus, $userId, $options);
    }
    
    /**
     * Update asset working condition with role-based access control
     * Requirement 11.2: Engineers can only update to Working or Not Working
     * 
     * @param int $assetId Asset ID
     * @param string $condition New working condition
     * @param int $userId User performing the action
     * @return array Result with success status
     */
    public function updateWorkingConditionWithAccessControl(int $assetId, string $condition, int $userId): array {
        // Check access control first
        $accessCheck = $this->inventoryAccessService->canUpdateWorkingCondition($userId, $assetId, $condition);
        if (!$accessCheck['success']) {
            return $accessCheck;
        }
        
        // Proceed with normal condition update
        return $this->updateWorkingCondition($assetId, $condition, $userId);
    }
    
    /**
     * Validate if a user can update asset status based on their role
     * Requirement 11.2: Limit engineer status updates
     * 
     * @param int $userId User ID
     * @param int $assetId Asset ID
     * @param string $newStatus New status to set
     * @return array Validation result
     */
    public function validateUserStatusUpdate(int $userId, int $assetId, string $newStatus): array {
        return $this->inventoryAccessService->canUpdateAssetStatus($userId, $assetId, $newStatus);
    }
    
    /**
     * Apply status-specific business rules
     * 
     * @param array $asset Asset data
     * @param string $newStatus New status
     * @param int|null $userId User performing the action
     * @return array Result with success status
     */
    private function applyStatusBusinessRules(array $asset, string $newStatus, ?int $userId): array {
        // Get product to check if repairable
        $product = $this->productRepository->find($asset['product_id']);
        
        // Rule: Non-repairable items cannot go to Under Repair status
        if ($newStatus === AssetRepository::STATUS_UNDER_REPAIR) {
            if ($product && !$product['is_repairable']) {
                return [
                    'success' => false,
                    'message' => 'This product is not repairable. Mark as scrapped instead.',
                    'code' => 'NOT_REPAIRABLE'
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Log audit entry for status changes
     */
    private function logAuditEntry(
        string $actionType,
        string $entityType,
        int $entityId,
        ?int $userId,
        ?string $fromLocationType,
        ?int $fromLocationId,
        ?string $toLocationType,
        ?int $toLocationId,
        ?array $details = null
    ): void {
        try {
            $this->auditLogRepository->create([
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => $userId ?? 0,
                'from_location_type' => $fromLocationType,
                'from_location_id' => $fromLocationId,
                'to_location_type' => $toLocationType,
                'to_location_id' => $toLocationId,
                'new_values' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log audit entry: " . $e->getMessage());
        }
    }
}
