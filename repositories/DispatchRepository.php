<?php
/**
 * Dispatch Repository
 * Provides data access for dispatches with history queries
 * 
 * Requirements: 5.1, 5.3
 * - Dispatch with source warehouse, destination (company/user/warehouse), items, quantities
 * - Update item status from "In Stock" to "Dispatched" and record from/to details
 */

require_once __DIR__ . '/BaseRepository.php';

class DispatchRepository extends BaseRepository {
    protected $table = 'dispatches';
    protected $companyIdColumn = 'from_company_id';
    
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
        $result = $this->db->getResults($sql, [$dispatchNumber], 's');
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
        $sql = "SELECT d.*, fw.name as from_warehouse_name, fc.name as from_company_name
                FROM `{$this->table}` d
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                WHERE d.to_company_id = ?
                ORDER BY d.dispatch_date DESC";
        return $this->db->getResults($sql, [$companyId], 'i');
    }
    
    /**
     * Find dispatches to user
     */
    public function findToUser($userId) {
        $sql = "SELECT d.*, fw.name as from_warehouse_name, fc.name as from_company_name
                FROM `{$this->table}` d
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                WHERE d.to_user_id = ?
                ORDER BY d.dispatch_date DESC";
        return $this->db->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Find dispatches to warehouse
     */
    public function findToWarehouse($warehouseId) {
        $sql = "SELECT d.*, fw.name as from_warehouse_name, fc.name as from_company_name
                FROM `{$this->table}` d
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                WHERE d.to_warehouse_id = ?
                ORDER BY d.dispatch_date DESC";
        return $this->db->getResults($sql, [$warehouseId], 'i');
    }
    
    /**
     * Find pending acknowledgment dispatches
     */
    public function findPendingAcknowledgment() {
        $sql = "SELECT d.*, fw.name as from_warehouse_name, fc.name as from_company_name,
                       tc.name as to_company_name, CONCAT(tu.first_name, ' ', tu.last_name) as to_user_name
                FROM `{$this->table}` d
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN companies tc ON d.to_company_id = tc.id
                LEFT JOIN users tu ON d.to_user_id = tu.id
                WHERE d.acknowledgment_status = ? AND d.status = ?
                ORDER BY d.dispatch_date DESC";
        return $this->db->getResults($sql, [self::ACK_PENDING, self::STATUS_DELIVERED], 'ss');
    }
    
    /**
     * Get dispatch with full details
     */
    public function findWithDetails($id) {
        // Use a simpler query that doesn't depend on new columns
        $sql = "SELECT d.*, 
                       fc.name as from_company_name,
                       fw.name as from_warehouse_name,
                       tc.name as to_company_name,
                       CONCAT(tu.first_name, ' ', tu.last_name) as to_user_name,
                       tw.name as to_warehouse_name,
                       CONCAT(ab.first_name, ' ', ab.last_name) as acknowledged_by_name,
                       CONCAT(cb.first_name, ' ', cb.last_name) as created_by_name
                FROM `{$this->table}` d
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies tc ON d.to_company_id = tc.id
                LEFT JOIN users tu ON d.to_user_id = tu.id
                LEFT JOIN warehouses tw ON d.to_warehouse_id = tw.id
                LEFT JOIN users ab ON d.acknowledged_by = ab.id
                LEFT JOIN users cb ON d.created_by = cb.id
                WHERE d.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        $dispatch = !empty($result) ? $result[0] : null;
        
        // Try to get courier name if courier_id column exists
        if ($dispatch && isset($dispatch['courier_id']) && $dispatch['courier_id']) {
            try {
                $courierSql = "SELECT name FROM couriers WHERE id = ?";
                $courierResult = $this->db->getResults($courierSql, [$dispatch['courier_id']], 'i');
                $dispatch['courier_name'] = !empty($courierResult) ? $courierResult[0]['name'] : null;
            } catch (Exception $e) {
                $dispatch['courier_name'] = null;
            }
        } else {
            $dispatch['courier_name'] = null;
        }
        
        return $dispatch;
    }
    
    /**
     * Get all dispatches with full details
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'd.dispatch_date DESC') {
        $sql = "SELECT d.*, 
                       fc.name as from_company_name,
                       fw.name as from_warehouse_name,
                       tc.name as to_company_name,
                       CONCAT(tu.first_name, ' ', tu.last_name) as to_user_name,
                       tw.name as to_warehouse_name
                FROM `{$this->table}` d
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies tc ON d.to_company_id = tc.id
                LEFT JOIN users tu ON d.to_user_id = tu.id
                LEFT JOIN warehouses tw ON d.to_warehouse_id = tw.id";
        $params = [];
        $types = '';
        $whereClause = [];
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'd.' . $this->companyIdColumn
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        // Add custom conditions
        foreach ($conditions as $field => $value) {
            $whereClause[] = "d.`$field` = ?";
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
     * Update dispatch status
     */
    public function updateStatus($id, $status) {
        if (!self::isValidStatus($status)) {
            throw new Exception("Invalid status: $status");
        }
        
        return $this->update($id, ['status' => $status]);
    }
    
    /**
     * Acknowledge dispatch with optional additional data
     * @param int $id Dispatch ID
     * @param int $acknowledgedBy User ID
     * @param array $additionalData Optional data including notes, condition, proof
     */
    public function acknowledge($id, $acknowledgedBy, array $additionalData = []) {
        // Core fields that always exist
        $updateData = [
            'acknowledgment_status' => self::ACK_ACKNOWLEDGED,
            'acknowledged_at' => $additionalData['acknowledged_at'] ?? date('Y-m-d H:i:s'),
            'acknowledged_by' => $acknowledgedBy
        ];
        
        // Try to add optional fields - these may not exist in older database schemas
        // We'll attempt the update and if it fails due to missing columns, 
        // we'll retry with just the core fields
        $optionalFields = [];
        if (isset($additionalData['acknowledgment_notes'])) {
            $optionalFields['acknowledgment_notes'] = $additionalData['acknowledgment_notes'];
        }
        if (isset($additionalData['acknowledgment_condition'])) {
            $optionalFields['acknowledgment_condition'] = $additionalData['acknowledgment_condition'];
        }
        if (isset($additionalData['acknowledgment_proof'])) {
            $optionalFields['acknowledgment_proof'] = $additionalData['acknowledgment_proof'];
        }
        
        // First try with all fields
        if (!empty($optionalFields)) {
            try {
                return $this->update($id, array_merge($updateData, $optionalFields));
            } catch (Exception $e) {
                // If it fails (likely due to missing columns), try with just core fields
                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                    return $this->update($id, $updateData);
                }
                throw $e;
            }
        }
        
        return $this->update($id, $updateData);
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
        $sql = "SELECT di.*, p.name as product_name, p.is_serializable, a.serial_number
                FROM dispatch_items di
                LEFT JOIN products p ON di.product_id = p.id
                LEFT JOIN assets a ON di.asset_id = a.id
                WHERE di.dispatch_id = ?";
        
        return $this->db->getResults($sql, [$id], 'i');
    }
    
    /**
     * Get dispatch history with filters
     */
    public function getHistory($filters = []) {
        $sql = "SELECT d.*, 
                       fc.name as from_company_name,
                       fw.name as from_warehouse_name,
                       tc.name as to_company_name,
                       CONCAT(tu.first_name, ' ', tu.last_name) as to_user_name,
                       tw.name as to_warehouse_name,
                       c.name as courier_name,
                       s.site_name as site_name,
                       mr.id as mr_id
                FROM `{$this->table}` d
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies tc ON d.to_company_id = tc.id
                LEFT JOIN users tu ON d.to_user_id = tu.id
                LEFT JOIN warehouses tw ON d.to_warehouse_id = tw.id
                LEFT JOIN couriers c ON d.courier_id = c.id
                LEFT JOIN sites s ON d.site_id = s.id
                LEFT JOIN material_requests mr ON d.material_request_id = mr.id
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($filters['from_warehouse_id'])) {
            $sql .= " AND d.from_warehouse_id = ?";
            $params[] = $filters['from_warehouse_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['to_company_id'])) {
            $sql .= " AND d.to_company_id = ?";
            $params[] = $filters['to_company_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['to_user_id'])) {
            $sql .= " AND d.to_user_id = ?";
            $params[] = $filters['to_user_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['acknowledgment_status'])) {
            $sql .= " AND d.acknowledgment_status = ?";
            $params[] = $filters['acknowledgment_status'];
            $types .= 's';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND d.dispatch_date >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND d.dispatch_date <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Company scope filter for contractors
        if (!empty($filters['company_scope'])) {
            $companyId = $filters['company_scope']['company_id'];
            $warehouseIds = $filters['company_scope']['warehouse_ids'] ?? [];
            
            if (!empty($warehouseIds)) {
                $placeholders = implode(',', array_fill(0, count($warehouseIds), '?'));
                $sql .= " AND (d.to_company_id = ? OR d.from_warehouse_id IN ($placeholders))";
                $params[] = $companyId;
                $types .= 'i';
                foreach ($warehouseIds as $wid) {
                    $params[] = $wid;
                    $types .= 'i';
                }
            } else {
                $sql .= " AND d.to_company_id = ?";
                $params[] = $companyId;
                $types .= 'i';
            }
        }
        
        $sql .= " ORDER BY d.dispatch_date DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
}
