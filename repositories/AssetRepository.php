<?php
/**
 * Asset Repository
 * Provides data access for serializable inventory items with serial number lookup
 * 
 * Requirements: 3.1, 6.1, 6.2
 * - Create individual asset records with unique serial numbers and set status to "In Stock"
 * - Update status to one of: In Stock, Dispatched, Assigned, In Use, Returned, Under Repair, Scrapped, or Lost
 * - Return current status, current holder, source warehouse, and working condition when querying
 */

require_once __DIR__ . '/BaseRepository.php';

class AssetRepository extends BaseRepository {
    protected $table = 'assets';
    protected $companyIdColumn = null; // Assets are linked to warehouse which has company
    protected $applyCompanyFilter = false;
    
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
        $result = $this->db->getResults($sql, [$serialNumber], 's');
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
        
        $result = $this->db->getResults($sql, $params, $types);
        return $result[0]['count'] > 0;
    }
    
    /**
     * Create asset with serial number uniqueness validation
     */
    public function create($data) {
        // Validate serial number uniqueness
        if (isset($data['serial_number'])) {
            if ($this->serialNumberExists($data['serial_number'])) {
                throw new Exception("Serial number '{$data['serial_number']}' already exists");
            }
        }
        
        return parent::create($data);
    }
    
    /**
     * Update asset with serial number uniqueness validation
     */
    public function update($id, $data) {
        // Validate serial number uniqueness if being changed
        if (isset($data['serial_number'])) {
            if ($this->serialNumberExists($data['serial_number'], $id)) {
                throw new Exception("Serial number '{$data['serial_number']}' already exists");
            }
        }
        
        return parent::update($id, $data);
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
        return $this->db->getResults($sql, [$warehouseId, self::STATUS_IN_STOCK], 'is');
    }
    
    /**
     * Find assets by current holder
     */
    public function findByHolder($holderType, $holderId) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `current_holder_type` = ? AND `current_holder_id` = ?
                ORDER BY `serial_number`";
        return $this->db->getResults($sql, [$holderType, $holderId], 'si');
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
                           WHEN a.current_holder_type = 'user' THEN CONCAT(hu.first_name, ' ', hu.last_name)
                       END as current_holder_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id
                LEFT JOIN warehouses hw ON a.current_holder_type = 'warehouse' AND a.current_holder_id = hw.id
                LEFT JOIN companies hc ON a.current_holder_type = 'company' AND a.current_holder_id = hc.id
                LEFT JOIN users hu ON a.current_holder_type = 'user' AND a.current_holder_id = hu.id
                WHERE a.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get asset with dispatch destination details
     * Requirements 2.2, 2.4: Include current_holder_name, courier_name, pod_number for dispatched assets
     * 
     * @param int $id Asset ID
     * @return array|null Asset with dispatch details or null if not found
     */
    public function findWithDispatchDetails($id) {
        $sql = "SELECT a.*, 
                       p.name as product_name, p.is_repairable,
                       w.name as warehouse_name,
                       sw.name as source_warehouse_name,
                       CASE 
                           WHEN a.current_holder_type = 'warehouse' THEN hw.name
                           WHEN a.current_holder_type = 'company' THEN hc.name
                           WHEN a.current_holder_type = 'user' THEN CONCAT(hu.first_name, ' ', hu.last_name)
                       END as current_holder_name,
                       d.id as dispatch_id,
                       d.dispatch_number,
                       d.dispatch_date,
                       d.status as dispatch_status,
                       d.to_company_id,
                       d.to_user_id,
                       d.to_warehouse_id,
                       d.courier_id,
                       d.pod_number,
                       d.acknowledgment_status,
                       tc.name as dispatched_to_company_name,
                       CONCAT(tu.first_name, ' ', tu.last_name) as dispatched_to_user_name,
                       tw.name as dispatched_to_warehouse_name,
                       cr.name as courier_name,
                       fw.name as from_warehouse_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id
                LEFT JOIN warehouses hw ON a.current_holder_type = 'warehouse' AND a.current_holder_id = hw.id
                LEFT JOIN companies hc ON a.current_holder_type = 'company' AND a.current_holder_id = hc.id
                LEFT JOIN users hu ON a.current_holder_type = 'user' AND a.current_holder_id = hu.id
                LEFT JOIN dispatch_items di ON di.asset_id = a.id
                LEFT JOIN dispatches d ON d.id = di.dispatch_id AND d.status != 'cancelled'
                LEFT JOIN companies tc ON d.to_company_id = tc.id
                LEFT JOIN users tu ON d.to_user_id = tu.id
                LEFT JOIN warehouses tw ON d.to_warehouse_id = tw.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN couriers cr ON d.courier_id = cr.id
                WHERE a.id = ?
                ORDER BY d.created_at DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        
        if (empty($result)) {
            return null;
        }
        
        $asset = $result[0];
        
        // Build dispatched_to_name based on destination type
        if ($asset['status'] === self::STATUS_DISPATCHED) {
            if (!empty($asset['dispatched_to_company_name'])) {
                $asset['dispatched_to_name'] = $asset['dispatched_to_company_name'];
                $asset['dispatched_to_type'] = 'company';
            } elseif (!empty($asset['dispatched_to_user_name'])) {
                $asset['dispatched_to_name'] = $asset['dispatched_to_user_name'];
                $asset['dispatched_to_type'] = 'user';
            } elseif (!empty($asset['dispatched_to_warehouse_name'])) {
                $asset['dispatched_to_name'] = $asset['dispatched_to_warehouse_name'];
                $asset['dispatched_to_type'] = 'warehouse';
            }
        }
        
        return $asset;
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
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get assets by company (through warehouse)
     */
    public function findByCompany($companyId) {
        $sql = "SELECT a.*, p.name as product_name, w.name as warehouse_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                WHERE w.company_id = ?
                ORDER BY a.serial_number";
        
        return $this->db->getResults($sql, [$companyId], 'i');
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
        $validConditions = [self::CONDITION_WORKING, self::CONDITION_NOT_WORKING];
        if (!in_array($condition, $validConditions)) {
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
        $validTypes = [self::HOLDER_WAREHOUSE, self::HOLDER_COMPANY, self::HOLDER_USER];
        if (!in_array($holderType, $validTypes)) {
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
        $result = $this->db->getResults($sql, [$status], 's');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Count assets by product and status
     */
    public function countByProductAndStatus($productId, $status) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `product_id` = ? AND `status` = ?";
        $result = $this->db->getResults($sql, [$productId, $status], 'is');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Search assets with filters
     */
    public function search($filters = []) {
        $sql = "SELECT a.*, p.name as product_name, w.name as warehouse_name, sw.name as source_warehouse_name
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['product_id'])) {
            $sql .= " AND a.product_id = ?";
            $params[] = $filters['product_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['warehouse_id'])) {
            // Include assets in this warehouse OR dispatched from this warehouse
            $sql .= " AND (a.warehouse_id = ? OR a.source_warehouse_id = ?)";
            $params[] = $filters['warehouse_id'];
            $params[] = $filters['warehouse_id'];
            $types .= 'ii';
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
            
            // For in_stock status, only include assets that are actually in a warehouse
            if ($filters['status'] === self::STATUS_IN_STOCK) {
                $sql .= " AND a.warehouse_id IS NOT NULL";
            }
        }
        
        if (!empty($filters['working_condition'])) {
            $sql .= " AND a.working_condition = ?";
            $params[] = $filters['working_condition'];
            $types .= 's';
        }
        
        if (!empty($filters['serial_number'])) {
            $sql .= " AND a.serial_number LIKE ?";
            $params[] = '%' . $filters['serial_number'] . '%';
            $types .= 's';
        }
        
        $sql .= " ORDER BY a.serial_number";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get asset movement history
     */
    public function getMovementHistory($id) {
        $sql = "SELECT * FROM inventory_audit_log 
                WHERE entity_type = 'asset' AND entity_id = ?
                ORDER BY created_at DESC";
        
        return $this->db->getResults($sql, [$id], 'i');
    }
    
    /**
     * Get asset counts grouped by product and warehouse
     * 
     * @param array $warehouseIds List of warehouse IDs to filter
     * @param int|null $productId Optional product ID filter
     * @return array Asset counts with product and warehouse details
     */
    public function getCountsByProductAndWarehouse(array $warehouseIds, ?int $productId = null) {
        if (empty($warehouseIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($warehouseIds), '?'));
        
        // Get in-stock counts per warehouse (only assets currently in these warehouses)
        $sql = "SELECT 
                    a.product_id,
                    p.name as product_name,
                    a.warehouse_id,
                    w.name as warehouse_name,
                    COUNT(*) as in_stock_count
                FROM `{$this->table}` a
                LEFT JOIN products p ON a.product_id = p.id
                LEFT JOIN warehouses w ON a.warehouse_id = w.id
                WHERE a.warehouse_id IN ($placeholders) AND a.status = 'in_stock'";
        
        $params = $warehouseIds;
        $types = str_repeat('i', count($warehouseIds));
        
        if ($productId !== null) {
            $sql .= " AND a.product_id = ?";
            $params[] = $productId;
            $types .= 'i';
        }
        
        $sql .= " GROUP BY a.product_id, a.warehouse_id, p.name, w.name
                  HAVING in_stock_count > 0
                  ORDER BY p.name, w.name";
        
        $results = $this->db->getResults($sql, $params, $types);
        
        // For each product found, get the total count from source_warehouse_id OR warehouse_id
        // This handles both dispatched assets (source_warehouse_id) and in-stock assets (warehouse_id)
        foreach ($results as &$result) {
            $productId = $result['product_id'];
            $warehouseId = $result['warehouse_id'];
            
            // Count total assets that originated from or are currently in this warehouse
            $totalSql = "SELECT 
                            COUNT(*) as total_count,
                            SUM(CASE WHEN status = 'dispatched' THEN 1 ELSE 0 END) as dispatched_count,
                            SUM(CASE WHEN status = 'under_repair' THEN 1 ELSE 0 END) as under_repair_count
                         FROM `{$this->table}`
                         WHERE product_id = ? 
                         AND (source_warehouse_id = ? OR (warehouse_id = ? AND source_warehouse_id IS NULL))";
            
            $totalResult = $this->db->getResults($totalSql, [$productId, $warehouseId, $warehouseId], 'iii');
            
            if (!empty($totalResult)) {
                $result['total_count'] = (int)$totalResult[0]['total_count'];
                $result['dispatched_count'] = (int)$totalResult[0]['dispatched_count'];
                $result['under_repair_count'] = (int)$totalResult[0]['under_repair_count'];
            } else {
                $result['total_count'] = (int)$result['in_stock_count'];
                $result['dispatched_count'] = 0;
                $result['under_repair_count'] = 0;
            }
        }
        
        return $results;
    }
}
