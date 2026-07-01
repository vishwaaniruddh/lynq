<?php
/**
 * Transfer Repository
 * Provides data access for inter-warehouse transfers with status tracking
 * 
 * Requirements: 5.4
 * - Inter-warehouse transfer: decrement source warehouse stock and increment destination warehouse stock
 */

require_once __DIR__ . '/BaseRepository.php';

class TransferRepository extends BaseRepository {
    protected $table = 'transfers';
    protected $companyIdColumn = null; // Transfers are between warehouses
    protected $applyCompanyFilter = false;
    
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
        $result = $this->db->getResults($sql, [$transferNumber], 's');
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
     * Find transfers involving warehouse (from or to)
     */
    public function findByWarehouse($warehouseId) {
        $sql = "SELECT t.*, 
                       fw.name as from_warehouse_name, tw.name as to_warehouse_name
                FROM `{$this->table}` t
                LEFT JOIN warehouses fw ON t.from_warehouse_id = fw.id
                LEFT JOIN warehouses tw ON t.to_warehouse_id = tw.id
                WHERE t.from_warehouse_id = ? OR t.to_warehouse_id = ?
                ORDER BY t.transfer_date DESC";
        return $this->db->getResults($sql, [$warehouseId, $warehouseId], 'ii');
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
        
        $result = $this->db->getResults($sql, [$id], 'i');
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
        
        return $this->db->getResults($sql, $params, $types);
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
        $sql = "SELECT ti.*, p.name as product_name, p.is_serializable, a.serial_number
                FROM transfer_items ti
                LEFT JOIN products p ON ti.product_id = p.id
                LEFT JOIN assets a ON ti.asset_id = a.id
                WHERE ti.transfer_id = ?";
        
        return $this->db->getResults($sql, [$id], 'i');
    }
    
    /**
     * Get transfer history with filters
     */
    public function getHistory($filters = []) {
        $sql = "SELECT t.*, 
                       fw.name as from_warehouse_name, fc.name as from_company_name,
                       tw.name as to_warehouse_name, tc.name as to_company_name
                FROM `{$this->table}` t
                LEFT JOIN warehouses fw ON t.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON fw.company_id = fc.id
                LEFT JOIN warehouses tw ON t.to_warehouse_id = tw.id
                LEFT JOIN companies tc ON tw.company_id = tc.id
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['from_warehouse_id'])) {
            $sql .= " AND t.from_warehouse_id = ?";
            $params[] = $filters['from_warehouse_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['to_warehouse_id'])) {
            $sql .= " AND t.to_warehouse_id = ?";
            $params[] = $filters['to_warehouse_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND t.transfer_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND t.transfer_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Warehouse scope filter for contractors
        if (!empty($filters['warehouse_scope']) && is_array($filters['warehouse_scope'])) {
            $warehouseIds = $filters['warehouse_scope'];
            if (!empty($warehouseIds)) {
                $placeholders = implode(',', array_fill(0, count($warehouseIds), '?'));
                $sql .= " AND (t.from_warehouse_id IN ($placeholders) OR t.to_warehouse_id IN ($placeholders))";
                foreach ($warehouseIds as $wid) {
                    $params[] = $wid;
                    $types .= 'i';
                }
                foreach ($warehouseIds as $wid) {
                    $params[] = $wid;
                    $types .= 'i';
                }
            }
        }
        
        $sql .= " ORDER BY t.transfer_date DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
}
