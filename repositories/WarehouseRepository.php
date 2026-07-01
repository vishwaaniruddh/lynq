<?php
/**
 * Warehouse Repository with Access Control
 * Provides company-aware warehouse data access
 * 
 * Requirements: 1.1, 1.2, 1.3
 * - Store warehouse with name, location, company assignment, and active status
 * - Display only warehouses belonging to companies the user has access to
 * - Prevent new dispatches from inactive warehouses while preserving existing stock records
 */

require_once __DIR__ . '/BaseRepository.php';

class WarehouseRepository extends BaseRepository {
    protected $table = 'warehouses';
    protected $companyIdColumn = 'company_id';
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE
        ];
    }
    
    /**
     * Check if a status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Find warehouses by company (with access control)
     */
    public function findByCompanyId($companyId) {
        // Validate access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->findAll(['company_id' => $companyId], 'name');
    }
    
    /**
     * Find active warehouses (with access control)
     */
    public function findActive() {
        return $this->findAll(['status' => self::STATUS_ACTIVE], 'name');
    }
    
    /**
     * Find active warehouses by company (with access control)
     */
    public function findActiveByCompany($companyId) {
        // Validate access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_id` = ? AND `status` = ? 
                ORDER BY `name`";
        return $this->db->getResults($sql, [$companyId, self::STATUS_ACTIVE], 'is');
    }
    
    /**
     * Check if warehouse is active
     */
    public function isActive($id) {
        $warehouse = $this->find($id);
        return $warehouse && $warehouse['status'] === self::STATUS_ACTIVE;
    }
    
    /**
     * Check if warehouse name is unique within company
     * Requirement 1.4: Validate that warehouse name is unique within the same company
     */
    public function isNameUniqueInCompany($name, $companyId, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `name` = ? AND `company_id` = ?";
        $params = [$name, $companyId];
        $types = 'si';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return $result[0]['count'] == 0;
    }
    
    /**
     * Create warehouse with uniqueness validation
     */
    public function create($data) {
        // Validate name uniqueness within company
        if (isset($data['name']) && isset($data['company_id'])) {
            if (!$this->isNameUniqueInCompany($data['name'], $data['company_id'])) {
                throw new Exception("Warehouse name '{$data['name']}' already exists in this company");
            }
        }
        
        return parent::create($data);
    }
    
    /**
     * Update warehouse with uniqueness validation
     */
    public function update($id, $data) {
        // Get existing warehouse to check company_id
        $existing = $this->find($id);
        if (!$existing) {
            throw new Exception("Warehouse not found");
        }
        
        // Validate name uniqueness if name is being changed
        if (isset($data['name'])) {
            $companyId = $data['company_id'] ?? $existing['company_id'];
            if (!$this->isNameUniqueInCompany($data['name'], $companyId, $id)) {
                throw new Exception("Warehouse name '{$data['name']}' already exists in this company");
            }
        }
        
        return parent::update($id, $data);
    }
    
    /**
     * Get warehouse with company details (with access control)
     */
    public function findWithCompany($id) {
        $sql = "SELECT w.*, c.name as company_name, c.type as company_type
                FROM `{$this->table}` w
                LEFT JOIN companies c ON w.company_id = c.id
                WHERE w.id = ?";
        $params = [$id];
        $types = 'i';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'w.' . $this->companyIdColumn
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all warehouses with company details (with access control)
     */
    public function findAllWithCompany($conditions = [], $orderBy = 'w.name') {
        $sql = "SELECT w.*, c.name as company_name, c.type as company_type
                FROM `{$this->table}` w
                LEFT JOIN companies c ON w.company_id = c.id";
        $params = [];
        $types = '';
        $whereClause = [];
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'w.' . $this->companyIdColumn
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        // Add custom conditions
        foreach ($conditions as $field => $value) {
            $whereClause[] = "w.`$field` = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }
        
        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Deactivate warehouse (soft disable)
     * Requirement 1.3: Prevent new dispatches while preserving existing stock records
     */
    public function deactivate($id, $updatedBy = null) {
        $data = ['status' => self::STATUS_INACTIVE];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        return $this->update($id, $data);
    }
    
    /**
     * Activate warehouse
     */
    public function activate($id, $updatedBy = null) {
        $data = ['status' => self::STATUS_ACTIVE];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        return $this->update($id, $data);
    }
    
    /**
     * Get stock count for warehouse
     */
    public function getStockCount($id) {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total_quantity
                FROM stock WHERE warehouse_id = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return $result[0]['total_quantity'] ?? 0;
    }
    
    /**
     * Get asset count for warehouse
     */
    public function getAssetCount($id) {
        $sql = "SELECT COUNT(*) as count FROM assets 
                WHERE warehouse_id = ? AND status NOT IN ('scrapped', 'lost')";
        $result = $this->db->getResults($sql, [$id], 'i');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get warehouses accessible to a user
     */
    public function getAccessibleWarehouses($userId) {
        $this->setCurrentUser($userId);
        return $this->findAllWithCompany();
    }
    
    /**
     * Check if user can dispatch from warehouse
     * Requirement 1.3: Prevent new dispatches from inactive warehouses
     */
    public function canDispatchFrom($warehouseId, $userId = null) {
        $warehouse = $this->find($warehouseId);
        
        if (!$warehouse) {
            return false;
        }
        
        // Check if warehouse is active
        if ($warehouse['status'] !== self::STATUS_ACTIVE) {
            return false;
        }
        
        // Check user access if user is specified
        if ($userId !== null) {
            try {
                $this->companyIsolationService->validateCompanyAccess($userId, $warehouse['company_id']);
            } catch (Exception $e) {
                return false;
            }
        }
        
        return true;
    }
}
