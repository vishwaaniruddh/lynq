<?php
/**
 * Transfer Model
 * Represents an inter-warehouse transfer of inventory
 * 
 * Requirements: 5.4
 * - Inter-warehouse transfer: decrement source warehouse stock and increment destination warehouse stock
 */

require_once __DIR__ . '/BaseModel.php';

class Transfer extends BaseModel {
    protected $table = 'transfers';
    protected $fillable = [
        'transfer_number', 'from_warehouse_id', 'to_warehouse_id',
        'transfer_date', 'status', 'notes',
        'created_by', 'updated_by'
    ];
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_TRANSIT,
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
     * Generate unique transfer number
     */
    public static function generateTransferNumber() {
        return 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
    
    /**
     * Find transfer by transfer number
     */
    public function findByTransferNumber($transferNumber) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `transfer_number` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$transferNumber], 's');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find transfers by status
     */
    public function findByStatus($status) {
        return $this->findAll(['status' => $status], 'transfer_date DESC');
    }
    
    /**
     * Find transfers from warehouse
     */
    public function findFromWarehouse($warehouseId) {
        return $this->findAll(['from_warehouse_id' => $warehouseId], 'transfer_date DESC');
    }
    
    /**
     * Find transfers to warehouse
     */
    public function findToWarehouse($warehouseId) {
        return $this->findAll(['to_warehouse_id' => $warehouseId], 'transfer_date DESC');
    }
    
    /**
     * Get transfer with full details
     */
    public function findWithDetails($id) {
        $sql = "SELECT t.*, 
                       fw.name as from_warehouse_name, fc.name as from_company_name,
                       tw.name as to_warehouse_name, tc.name as to_company_name,
                       cb.name as created_by_name, ub.name as updated_by_name
                FROM `{$this->table}` t
                LEFT JOIN warehouses fw ON t.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON fw.company_id = fc.id
                LEFT JOIN warehouses tw ON t.to_warehouse_id = tw.id
                LEFT JOIN companies tc ON tw.company_id = tc.id
                LEFT JOIN users cb ON t.created_by = cb.id
                LEFT JOIN users ub ON t.updated_by = ub.id
                WHERE t.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all transfers with full details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 't.transfer_date DESC') {
        $sql = "SELECT t.*, 
                       fw.name as from_warehouse_name, fc.name as from_company_name,
                       tw.name as to_warehouse_name, tc.name as to_company_name
                FROM `{$this->table}` t
                LEFT JOIN warehouses fw ON t.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON fw.company_id = fc.id
                LEFT JOIN warehouses tw ON t.to_warehouse_id = tw.id
                LEFT JOIN companies tc ON tw.company_id = tc.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "t.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Update transfer status
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
     * Check if transfer can be cancelled
     */
    public function canCancel($id) {
        $transfer = $this->find($id);
        return $transfer && $transfer['status'] === self::STATUS_PENDING;
    }
    
    /**
     * Get transfer items
     */
    public function getItems($id) {
        $sql = "SELECT ti.*, p.name as product_name, a.serial_number
                FROM transfer_items ti
                LEFT JOIN products p ON ti.product_id = p.id
                LEFT JOIN assets a ON ti.asset_id = a.id
                WHERE ti.transfer_id = ?";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
    }
}
