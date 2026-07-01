<?php
/**
 * Repair Model
 * Represents a repair workflow for an asset
 * 
 * Requirements: 7.2, 7.3
 * - Record repair vendor, estimated cost, send date, and expected return date
 * - Update status to "In Stock" and record actual repair cost and completion date when repaired
 */

require_once __DIR__ . '/BaseModel.php';

class Repair extends BaseModel {
    protected $table = 'repairs';
    protected $fillable = [
        'asset_id', 'repair_vendor', 'estimated_cost',
        'actual_cost', 'send_date', 'expected_return_date',
        'actual_return_date', 'status', 'repair_notes',
        'diagnosis', 'resolution',
        'created_by', 'updated_by'
    ];
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED
        ];
    }
    
    /**
     * Check if a status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Find repairs by asset
     */
    public function findByAsset($assetId) {
        return $this->findAll(['asset_id' => $assetId], 'send_date DESC');
    }
    
    /**
     * Find repairs by status
     */
    public function findByStatus($status) {
        return $this->findAll(['status' => $status], 'send_date DESC');
    }
    
    /**
     * Find active repairs (pending or in progress)
     */
    public function findActive() {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `status` IN (?, ?)
                ORDER BY `send_date` DESC";
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], 'ss');
    }
    
    /**
     * Find overdue repairs
     */
    public function findOverdue() {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `status` IN (?, ?) 
                AND `expected_return_date` < CURDATE()
                ORDER BY `expected_return_date` ASC";
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], 'ss');
    }
    
    /**
     * Find repairs by vendor
     */
    public function findByVendor($vendor) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `repair_vendor` LIKE ?
                ORDER BY `send_date` DESC";
        return DatabaseConfig::getInstance()->getResults($sql, ['%' . $vendor . '%'], 's');
    }
    
    /**
     * Get repair with full details
     */
    public function findWithDetails($id) {
        $sql = "SELECT r.*, 
                       a.serial_number, a.status as asset_status, a.working_condition,
                       p.name as product_name,
                       w.name as warehouse_name,
                       cb.name as created_by_name, ub.name as updated_by_name
                FROM `{$this->table}` r
                LEFT JOIN assets a ON r.asset_id = a.id
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN users cb ON r.created_by = cb.id
                LEFT JOIN users ub ON r.updated_by = ub.id
                WHERE r.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all repairs with full details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'r.send_date DESC') {
        $sql = "SELECT r.*, 
                       a.serial_number, a.status as asset_status, a.working_condition,
                       p.name as product_name,
                       w.name as warehouse_name
                FROM `{$this->table}` r
                LEFT JOIN assets a ON r.asset_id = a.id
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "r.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Update repair status
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
     * Complete repair
     */
    public function complete($id, $actualCost, $resolution = null, $updatedBy = null) {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'actual_cost' => $actualCost,
            'actual_return_date' => date('Y-m-d')
        ];
        
        if ($resolution !== null) {
            $data['resolution'] = $resolution;
        }
        
        if ($updatedBy !== null) {
            $data['updated_by'] = $updatedBy;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Get active repair for asset
     */
    public function getActiveRepairForAsset($assetId) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `asset_id` = ? AND `status` IN (?, ?)
                ORDER BY `send_date` DESC
                LIMIT 1";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$assetId, self::STATUS_PENDING, self::STATUS_IN_PROGRESS], 'iss');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if asset has active repair
     */
    public function hasActiveRepair($assetId) {
        return $this->getActiveRepairForAsset($assetId) !== null;
    }
    
    /**
     * Get total repair cost for asset
     */
    public function getTotalRepairCost($assetId) {
        $sql = "SELECT COALESCE(SUM(actual_cost), 0) as total 
                FROM `{$this->table}` 
                WHERE `asset_id` = ? AND `status` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$assetId, self::STATUS_COMPLETED], 'is');
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * Get repair count for asset
     */
    public function getRepairCount($assetId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `asset_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$assetId], 'i');
        return $result[0]['count'] ?? 0;
    }
}
