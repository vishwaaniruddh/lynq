<?php
/**
 * Asset Model
 * Represents a serializable inventory item tracked by unique serial number
 * 
 * Requirements: 3.1, 6.1, 6.2
 * - Create individual asset records with unique serial numbers and set status to "In Stock"
 * - Update status to one of: In Stock, Dispatched, Assigned, In Use, Returned, Under Repair, Scrapped, or Lost
 * - Return current status, current holder, source warehouse, and working condition when querying
 */

require_once __DIR__ . '/BaseModel.php';

class Asset extends BaseModel {
    protected $table = 'assets';
    protected $fillable = [
        'product_id', 'serial_number', 'warehouse_id',
        'status', 'working_condition', 'current_holder_type', 
        'current_holder_id', 'source_warehouse_id', 'warranty_expiry', 
        'notes', 'created_by', 'updated_by'
    ];
    
    // Status constants
    const STATUS_IN_STOCK = 'in_stock';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_IN_USE = 'in_use';
    const STATUS_RETURNED = 'returned';
    const STATUS_UNDER_REPAIR = 'under_repair';
    const STATUS_SCRAPPED = 'scrapped';
    const STATUS_LOST = 'lost';
    
    // Working condition constants
    const CONDITION_WORKING = 'working';
    const CONDITION_NOT_WORKING = 'not_working';
    
    // Holder type constants
    const HOLDER_WAREHOUSE = 'warehouse';
    const HOLDER_COMPANY = 'company';
    const HOLDER_USER = 'user';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_IN_STOCK,
            self::STATUS_DISPATCHED,
            self::STATUS_ASSIGNED,
            self::STATUS_IN_USE,
            self::STATUS_RETURNED,
            self::STATUS_UNDER_REPAIR,
            self::STATUS_SCRAPPED,
            self::STATUS_LOST
        ];
    }
    
    /**
     * Check if a status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Get all valid working conditions
     */
    public static function getWorkingConditions() {
        return [
            self::CONDITION_WORKING,
            self::CONDITION_NOT_WORKING
        ];
    }
    
    /**
     * Check if a working condition is valid
     */
    public static function isValidWorkingCondition($condition) {
        return in_array($condition, self::getWorkingConditions());
    }
    
    /**
     * Get all valid holder types
     */
    public static function getHolderTypes() {
        return [
            self::HOLDER_WAREHOUSE,
            self::HOLDER_COMPANY,
            self::HOLDER_USER
        ];
    }
    
    /**
     * Check if a holder type is valid
     */
    public static function isValidHolderType($type) {
        return in_array($type, self::getHolderTypes());
    }
    
    /**
     * Get statuses that indicate the asset is locked (cannot be dispatched)
     */
    public static function getLockedStatuses() {
        return [
            self::STATUS_SCRAPPED,
            self::STATUS_LOST
        ];
    }
    
    /**
     * Check if asset is in a locked status
     */
    public function isLocked($id) {
        $asset = $this->find($id);
        return $asset && in_array($asset['status'], self::getLockedStatuses());
    }
    
    /**
     * Find asset by serial number
     */
    public function findBySerialNumber($serialNumber) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `serial_number` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$serialNumber], 's');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if serial number exists
     */
    public function serialNumberExists($serialNumber, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `serial_number` = ?";
        $params = [$serialNumber];
        $types = 's';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        return $result[0]['count'] > 0;
    }
    
    /**
     * Find assets by product
     */
    public function findByProduct($productId) {
        return $this->findAll(['product_id' => $productId]);
    }
    
    /**
     * Find assets by warehouse
     */
    public function findByWarehouse($warehouseId) {
        return $this->findAll(['warehouse_id' => $warehouseId]);
    }
    
    /**
     * Find assets by status
     */
    public function findByStatus($status) {
        return $this->findAll(['status' => $status]);
    }
    
    /**
     * Find assets in stock at warehouse
     */
    public function findInStockAtWarehouse($warehouseId) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `warehouse_id` = ? AND `status` = ?
                ORDER BY `serial_number`";
        return DatabaseConfig::getInstance()->getResults($sql, [$warehouseId, self::STATUS_IN_STOCK], 'is');
    }
    
    /**
     * Find assets by current holder
     */
    public function findByHolder($holderType, $holderId) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `current_holder_type` = ? AND `current_holder_id` = ?
                ORDER BY `serial_number`";
        return DatabaseConfig::getInstance()->getResults($sql, [$holderType, $holderId], 'si');
    }
    
    /**
     * Get asset with full details
     * Requirement 6.2: Return current status, current holder, source warehouse, and working condition
     */
    public function findWithDetails($id) {
        $sql = "SELECT a.*, 
                       p.name as product_name, p.is_repairable,
                       w.name as warehouse_name,
                       sw.name as source_warehouse_name,
                       CASE 
                           WHEN a.current_holder_type = 'warehouse' THEN hw.name
                           WHEN a.current_holder_type = 'company' THEN hc.name
                           WHEN a.current_holder_type = 'user' THEN hu.name
                       END as current_holder_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id
                LEFT JOIN warehouses hw ON a.current_holder_type = 'warehouse' AND a.current_holder_id = hw.id
                LEFT JOIN companies hc ON a.current_holder_type = 'company' AND a.current_holder_id = hc.id
                LEFT JOIN users hu ON a.current_holder_type = 'user' AND a.current_holder_id = hu.id
                WHERE a.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all assets with full details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'a.serial_number') {
        $sql = "SELECT a.*, 
                       p.name as product_name, p.is_repairable,
                       w.name as warehouse_name,
                       sw.name as source_warehouse_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "a.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Update asset status
     */
    public function updateStatus($id, $status, $updatedBy = null) {
        if (!self::isValidStatus($status)) {
            throw new Exception("Invalid status: $status");
        }
        
        $data = ['status' => $status];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Update working condition
     */
    public function updateWorkingCondition($id, $condition, $updatedBy = null) {
        if (!self::isValidWorkingCondition($condition)) {
            throw new Exception("Invalid working condition: $condition");
        }
        
        $data = ['working_condition' => $condition];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Update current holder
     */
    public function updateHolder($id, $holderType, $holderId, $updatedBy = null) {
        if (!self::isValidHolderType($holderType)) {
            throw new Exception("Invalid holder type: $holderType");
        }
        
        $data = [
            'current_holder_type' => $holderType,
            'current_holder_id' => $holderId
        ];
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Count assets by status
     */
    public function countByStatus($status) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `status` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$status], 's');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Count assets by product and status
     */
    public function countByProductAndStatus($productId, $status) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `product_id` = ? AND `status` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$productId, $status], 'is');
        return $result[0]['count'] ?? 0;
    }
}
