<?php
/**
 * Dispatch Model
 * Represents a dispatch of inventory from one entity to another
 * 
 * Requirements: 5.1, 5.3
 * - Dispatch with source warehouse, destination (company/user/warehouse), items, quantities
 * - Update item status from "In Stock" to "Dispatched" and record from/to details
 */

require_once __DIR__ . '/BaseModel.php';

class Dispatch extends BaseModel {
    protected $table = 'dispatches';
    protected $fillable = [
        'dispatch_number', 'from_company_id', 'from_warehouse_id',
        'to_company_id', 'to_user_id', 'to_warehouse_id',
        'dispatch_date', 'status', 'acknowledgment_status',
        'acknowledged_at', 'acknowledged_by', 'notes',
        'courier_id', 'pod_number', 'contact_person_name', 
        'contact_person_phone', 'lr_copy_path', 'pod_receipt_path',
        'created_by'
    ];
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    
    // Acknowledgment status constants
    const ACK_PENDING = 'pending';
    const ACK_ACKNOWLEDGED = 'acknowledged';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_TRANSIT,
            self::STATUS_DELIVERED,
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
     * Get all valid acknowledgment statuses
     */
    public static function getAcknowledgmentStatuses() {
        return [
            self::ACK_PENDING,
            self::ACK_ACKNOWLEDGED
        ];
    }
    
    /**
     * Generate unique dispatch number
     */
    public static function generateDispatchNumber() {
        return 'DSP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
    
    /**
     * Find dispatch by dispatch number
     */
    public function findByDispatchNumber($dispatchNumber) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `dispatch_number` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$dispatchNumber], 's');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find dispatches by status
     */
    public function findByStatus($status) {
        return $this->findAll(['status' => $status], 'dispatch_date DESC');
    }
    
    /**
     * Find dispatches from warehouse
     */
    public function findFromWarehouse($warehouseId) {
        return $this->findAll(['from_warehouse_id' => $warehouseId], 'dispatch_date DESC');
    }
    
    /**
     * Find dispatches to company
     */
    public function findToCompany($companyId) {
        return $this->findAll(['to_company_id' => $companyId], 'dispatch_date DESC');
    }
    
    /**
     * Find dispatches to user
     */
    public function findToUser($userId) {
        return $this->findAll(['to_user_id' => $userId], 'dispatch_date DESC');
    }
    
    /**
     * Find dispatches to warehouse
     */
    public function findToWarehouse($warehouseId) {
        return $this->findAll(['to_warehouse_id' => $warehouseId], 'dispatch_date DESC');
    }
    
    /**
     * Find pending acknowledgment dispatches
     */
    public function findPendingAcknowledgment() {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `acknowledgment_status` = ? AND `status` = ?
                ORDER BY `dispatch_date` DESC";
        return DatabaseConfig::getInstance()->getResults($sql, [self::ACK_PENDING, self::STATUS_DELIVERED], 'ss');
    }
    
    /**
     * Get dispatch with full details
     */
    public function findWithDetails($id) {
        $sql = "SELECT d.*, 
                       fc.name as from_company_name,
                       fw.name as from_warehouse_name,
                       tc.name as to_company_name,
                       tu.name as to_user_name,
                       tw.name as to_warehouse_name,
                       ab.name as acknowledged_by_name,
                       cb.name as created_by_name
                FROM `{$this->table}` d
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies tc ON d.to_company_id = tc.id
                LEFT JOIN users tu ON d.to_user_id = tu.id
                LEFT JOIN warehouses tw ON d.to_warehouse_id = tw.id
                LEFT JOIN users ab ON d.acknowledged_by = ab.id
                LEFT JOIN users cb ON d.created_by = cb.id
                WHERE d.id = ?";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all dispatches with full details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'd.dispatch_date DESC') {
        $sql = "SELECT d.*, 
                       fc.name as from_company_name,
                       fw.name as from_warehouse_name,
                       tc.name as to_company_name,
                       tu.name as to_user_name,
                       tw.name as to_warehouse_name
                FROM `{$this->table}` d
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies tc ON d.to_company_id = tc.id
                LEFT JOIN users tu ON d.to_user_id = tu.id
                LEFT JOIN warehouses tw ON d.to_warehouse_id = tw.id";
        $params = [];
        $types = '';
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "d.`$field` = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY $orderBy";
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Update dispatch status
     */
    public function updateStatus($id, $status) {
        if (!self::isValidStatus($status)) {
            throw new Exception("Invalid status: $status");
        }
        
        return $this->update($id, ['status' => $status]);
    }
    
    /**
     * Acknowledge dispatch
     */
    public function acknowledge($id, $acknowledgedBy) {
        return $this->update($id, [
            'acknowledgment_status' => self::ACK_ACKNOWLEDGED,
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'acknowledged_by' => $acknowledgedBy
        ]);
    }
    
    /**
     * Check if dispatch can be cancelled
     */
    public function canCancel($id) {
        $dispatch = $this->find($id);
        return $dispatch && $dispatch['status'] === self::STATUS_PENDING;
    }
    
    /**
     * Get dispatch items
     */
    public function getItems($id) {
        $sql = "SELECT di.*, p.name as product_name, a.serial_number
                FROM dispatch_items di
                LEFT JOIN products p ON di.product_id = p.id
                LEFT JOIN assets a ON di.asset_id = a.id
                WHERE di.dispatch_id = ?";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
    }
}
