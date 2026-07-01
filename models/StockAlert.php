<?php
/**
 * StockAlert Model
 * Represents alerts for low stock and overdue repairs
 * 
 * Requirements: 12.1, 13.1
 * - Generate low stock alert when product stock falls below defined threshold
 * - Track alert status (active/cleared)
 */

require_once __DIR__ . '/BaseModel.php';

class StockAlert extends BaseModel {
    protected $table = 'stock_alerts';
    protected $fillable = [
        'product_id', 'warehouse_id', 'alert_type',
        'current_value', 'threshold_value', 'status',
        'cleared_at', 'cleared_by'
    ];
    
    // Alert type constants
    const TYPE_LOW_STOCK = 'low_stock';
    const TYPE_OVERDUE_REPAIR = 'overdue_repair';
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_CLEARED = 'cleared';
    
    /**
     * Get all valid alert types
     */
    public static function getAlertTypes() {
        return [
            self::TYPE_LOW_STOCK,
            self::TYPE_OVERDUE_REPAIR
        ];
    }
    
    /**
     * Check if an alert type is valid
     */
    public static function isValidAlertType($type) {
        return in_array($type, self::getAlertTypes());
    }
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_CLEARED
        ];
    }
    
    /**
     * Find active alerts
     */
    public function findActive() {
        return $this->findAll(['status' => self::STATUS_ACTIVE], 'created_at DESC');
    }
    
    /**
     * Find alerts by type
     */
    public function findByType($type) {
        return $this->findAll(['alert_type' => $type], 'created_at DESC');
    }
    
    /**
     * Find active alerts by type
     */
    public function findActiveByType($type) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `alert_type` = ? AND `status` = ?
                ORDER BY `created_at` DESC";
        return DatabaseConfig::getInstance()->getResults($sql, [$type, self::STATUS_ACTIVE], 'ss');
    }
    
    /**
     * Find alerts by product
     */
    public function findByProduct($productId) {
        return $this->findAll(['product_id' => $productId], 'created_at DESC');
    }
    
    /**
     * Find alerts by warehouse
     */
    public function findByWarehouse($warehouseId) {
        return $this->findAll(['warehouse_id' => $warehouseId], 'created_at DESC');
    }
    
    /**
     * Find active alert for product and warehouse
     */
    public function findActiveAlert($productId, $warehouseId, $alertType) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `product_id` = ? AND `warehouse_id` = ? 
                AND `alert_type` = ? AND `status` = ?
                LIMIT 1";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$productId, $warehouseId, $alertType, self::STATUS_ACTIVE], 'iiss');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get alert with full details
     */
    public function findWithDetails($id) {
        $sql = "SELECT a.*, 
                       p.name as product_name, p.unit_of_measure,
                       w.name as warehouse_name, c.name as company_name,
                       cb.name as cleared_by_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN companies c ON w.company_id = c.id
                LEFT JOIN users cb ON a.cleared_by = cb.id
                WHERE a.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all alerts with full details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'a.created_at DESC') {
        $sql = "SELECT a.*, 
                       p.name as product_name, p.unit_of_measure,
                       w.name as warehouse_name, c.name as company_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN companies c ON w.company_id = c.id";
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
     * Clear alert
     */
    public function clearAlert($id, $clearedBy = null) {
        $data = [
            'status' => self::STATUS_CLEARED,
            'cleared_at' => date('Y-m-d H:i:s')
        ];
        
        if ($clearedBy !== null) {
            $data['cleared_by'] = $clearedBy;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Create or update alert
     */
    public function createOrUpdateAlert($productId, $warehouseId, $alertType, $currentValue, $thresholdValue) {
        $existingAlert = $this->findActiveAlert($productId, $warehouseId, $alertType);
        
        if ($existingAlert) {
            // Update existing alert
            return $this->update($existingAlert['id'], [
                'current_value' => $currentValue,
                'threshold_value' => $thresholdValue
            ]);
        } else {
            // Create new alert
            return $this->create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'alert_type' => $alertType,
                'current_value' => $currentValue,
                'threshold_value' => $thresholdValue,
                'status' => self::STATUS_ACTIVE
            ]);
        }
    }
    
    /**
     * Count active alerts
     */
    public function countActive() {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `status` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE], 's');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Count active alerts by type
     */
    public function countActiveByType($type) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `alert_type` = ? AND `status` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$type, self::STATUS_ACTIVE], 'ss');
        return $result[0]['count'] ?? 0;
    }
}
