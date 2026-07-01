<?php
/**
 * Dispatch Chain Repository
 * Provides data access for tracking complete item journey (dispatch chain traceability)
 * 
 * Requirements: 9.1, 9.2
 * - Display complete chain of dispatches and receives from origin to current holder when querying item history
 * - Show each transfer with sender, receiver, timestamps, and acceptance status when viewing dispatch chain
 */

require_once __DIR__ . '/BaseRepository.php';

class DispatchChainRepository extends BaseRepository {
    protected $table = 'dispatch_chain';
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    // Status constants
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    
    // Entity type constants
    const ENTITY_WAREHOUSE = 'warehouse';
    const ENTITY_COMPANY = 'company';
    const ENTITY_USER = 'user';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_DISPATCHED,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED
        ];
    }
    
    /**
     * Check if status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Get all valid entity types
     */
    public static function getEntityTypes() {
        return [
            self::ENTITY_WAREHOUSE,
            self::ENTITY_COMPANY,
            self::ENTITY_USER
        ];
    }
    
    /**
     * Add entry to dispatch chain
     * 
     * @param array $data Chain entry data
     * @return array Created chain entry
     */
    public function addToChain(array $data) {
        // Validate required fields
        $requiredFields = ['product_id', 'dispatch_id', 'from_entity_type', 'from_entity_id', 'to_entity_type', 'to_entity_id', 'dispatch_date'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Calculate sequence number for this item
        $sequenceNumber = $this->getNextSequenceNumber($data['asset_id'] ?? null, $data['product_id']);
        $data['sequence_number'] = $sequenceNumber;
        
        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_DISPATCHED;
        }
        
        // Set default quantity if not provided
        if (!isset($data['quantity'])) {
            $data['quantity'] = 1;
        }
        
        return $this->create($data);
    }
    
    /**
     * Get next sequence number for an item
     * 
     * @param int|null $assetId Asset ID (for serializable items)
     * @param int $productId Product ID
     * @return int Next sequence number
     */
    private function getNextSequenceNumber($assetId, $productId) {
        if ($assetId !== null) {
            // For serializable items, sequence by asset
            $sql = "SELECT COALESCE(MAX(sequence_number), 0) + 1 as next_seq 
                    FROM `{$this->table}` WHERE asset_id = ?";
            $result = $this->db->getResults($sql, [$assetId], 'i');
        } else {
            // For non-serializable items, sequence by product
            $sql = "SELECT COALESCE(MAX(sequence_number), 0) + 1 as next_seq 
                    FROM `{$this->table}` WHERE product_id = ? AND asset_id IS NULL";
            $result = $this->db->getResults($sql, [$productId], 'i');
        }
        
        return (int)($result[0]['next_seq'] ?? 1);
    }
    
    /**
     * Get item history for a serializable item (by asset ID)
     * Requirement: 9.1, 9.2
     * 
     * @param int $assetId Asset ID
     * @return array Complete dispatch chain for the asset
     */
    public function getItemHistory($assetId) {
        $sql = "SELECT dc.*, 
                       p.name as product_name,
                       d.dispatch_number,
                       CASE 
                           WHEN dc.from_entity_type = 'warehouse' THEN fw.name
                           WHEN dc.from_entity_type = 'company' THEN fc.name
                           WHEN dc.from_entity_type = 'user' THEN CONCAT(fu.first_name, ' ', fu.last_name)
                       END as from_entity_name,
                       CASE 
                           WHEN dc.to_entity_type = 'warehouse' THEN tw.name
                           WHEN dc.to_entity_type = 'company' THEN tc.name
                           WHEN dc.to_entity_type = 'user' THEN CONCAT(tu.first_name, ' ', tu.last_name)
                       END as to_entity_name
                FROM `{$this->table}` dc
                LEFT JOIN products p ON dc.product_id = p.id
                LEFT JOIN dispatches d ON dc.dispatch_id = d.id
                LEFT JOIN warehouses fw ON dc.from_entity_type = 'warehouse' AND dc.from_entity_id = fw.id
                LEFT JOIN companies fc ON dc.from_entity_type = 'company' AND dc.from_entity_id = fc.id
                LEFT JOIN users fu ON dc.from_entity_type = 'user' AND dc.from_entity_id = fu.id
                LEFT JOIN warehouses tw ON dc.to_entity_type = 'warehouse' AND dc.to_entity_id = tw.id
                LEFT JOIN companies tc ON dc.to_entity_type = 'company' AND dc.to_entity_id = tc.id
                LEFT JOIN users tu ON dc.to_entity_type = 'user' AND dc.to_entity_id = tu.id
                WHERE dc.asset_id = ?
                ORDER BY dc.sequence_number ASC, dc.dispatch_date ASC";
        
        return $this->db->getResults($sql, [$assetId], 'i');
    }
    
    /**
     * Get product history at a specific entity
     * For non-serializable items, tracks dispatch history for product at entity
     * 
     * @param int $productId Product ID
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Dispatch history for product at entity
     */
    public function getProductHistory($productId, $entityType, $entityId) {
        $sql = "SELECT dc.*, 
                       d.dispatch_number,
                       CASE 
                           WHEN dc.from_entity_type = 'warehouse' THEN fw.name
                           WHEN dc.from_entity_type = 'company' THEN fc.name
                           WHEN dc.from_entity_type = 'user' THEN CONCAT(fu.first_name, ' ', fu.last_name)
                       END as from_entity_name,
                       CASE 
                           WHEN dc.to_entity_type = 'warehouse' THEN tw.name
                           WHEN dc.to_entity_type = 'company' THEN tc.name
                           WHEN dc.to_entity_type = 'user' THEN CONCAT(tu.first_name, ' ', tu.last_name)
                       END as to_entity_name
                FROM `{$this->table}` dc
                LEFT JOIN dispatches d ON dc.dispatch_id = d.id
                LEFT JOIN warehouses fw ON dc.from_entity_type = 'warehouse' AND dc.from_entity_id = fw.id
                LEFT JOIN companies fc ON dc.from_entity_type = 'company' AND dc.from_entity_id = fc.id
                LEFT JOIN users fu ON dc.from_entity_type = 'user' AND dc.from_entity_id = fu.id
                LEFT JOIN warehouses tw ON dc.to_entity_type = 'warehouse' AND dc.to_entity_id = tw.id
                LEFT JOIN companies tc ON dc.to_entity_type = 'company' AND dc.to_entity_id = tc.id
                LEFT JOIN users tu ON dc.to_entity_type = 'user' AND dc.to_entity_id = tu.id
                WHERE dc.product_id = ? 
                AND dc.asset_id IS NULL
                AND ((dc.from_entity_type = ? AND dc.from_entity_id = ?) 
                     OR (dc.to_entity_type = ? AND dc.to_entity_id = ?))
                ORDER BY dc.dispatch_date DESC";
        
        return $this->db->getResults($sql, [$productId, $entityType, $entityId, $entityType, $entityId], 'isisi');
    }
    
    /**
     * Find chain entries by dispatch ID
     * 
     * @param int $dispatchId Dispatch ID
     * @return array Chain entries for the dispatch
     */
    public function findByDispatch($dispatchId) {
        $sql = "SELECT dc.*, 
                       p.name as product_name,
                       a.serial_number,
                       CASE 
                           WHEN dc.from_entity_type = 'warehouse' THEN fw.name
                           WHEN dc.from_entity_type = 'company' THEN fc.name
                           WHEN dc.from_entity_type = 'user' THEN CONCAT(fu.first_name, ' ', fu.last_name)
                       END as from_entity_name,
                       CASE 
                           WHEN dc.to_entity_type = 'warehouse' THEN tw.name
                           WHEN dc.to_entity_type = 'company' THEN tc.name
                           WHEN dc.to_entity_type = 'user' THEN CONCAT(tu.first_name, ' ', tu.last_name)
                       END as to_entity_name
                FROM `{$this->table}` dc
                LEFT JOIN products p ON dc.product_id = p.id
                LEFT JOIN assets a ON dc.asset_id = a.id
                LEFT JOIN warehouses fw ON dc.from_entity_type = 'warehouse' AND dc.from_entity_id = fw.id
                LEFT JOIN companies fc ON dc.from_entity_type = 'company' AND dc.from_entity_id = fc.id
                LEFT JOIN users fu ON dc.from_entity_type = 'user' AND dc.from_entity_id = fu.id
                LEFT JOIN warehouses tw ON dc.to_entity_type = 'warehouse' AND dc.to_entity_id = tw.id
                LEFT JOIN companies tc ON dc.to_entity_type = 'company' AND dc.to_entity_id = tc.id
                LEFT JOIN users tu ON dc.to_entity_type = 'user' AND dc.to_entity_id = tu.id
                WHERE dc.dispatch_id = ?
                ORDER BY dc.sequence_number ASC";
        
        return $this->db->getResults($sql, [$dispatchId], 'i');
    }
    
    /**
     * Update chain entry status (when dispatch is accepted/rejected)
     * 
     * @param int $dispatchId Dispatch ID
     * @param string $status New status
     * @param string|null $acceptanceDate Acceptance date (for accepted status)
     * @return int Number of updated records
     */
    public function updateStatusByDispatch($dispatchId, $status, $acceptanceDate = null) {
        if (!self::isValidStatus($status)) {
            throw new Exception("Invalid status: $status");
        }
        
        $sql = "UPDATE `{$this->table}` SET `status` = ?";
        $params = [$status];
        $types = 's';
        
        if ($acceptanceDate !== null) {
            $sql .= ", `acceptance_date` = ?";
            $params[] = $acceptanceDate;
            $types .= 's';
        }
        
        $sql .= " WHERE `dispatch_id` = ?";
        $params[] = $dispatchId;
        $types .= 'i';
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Get current holder of an asset from chain
     * 
     * @param int $assetId Asset ID
     * @return array|null Current holder info or null
     */
    public function getCurrentHolder($assetId) {
        $sql = "SELECT dc.to_entity_type, dc.to_entity_id,
                       CASE 
                           WHEN dc.to_entity_type = 'warehouse' THEN tw.name
                           WHEN dc.to_entity_type = 'company' THEN tc.name
                           WHEN dc.to_entity_type = 'user' THEN CONCAT(tu.first_name, ' ', tu.last_name)
                       END as holder_name
                FROM `{$this->table}` dc
                LEFT JOIN warehouses tw ON dc.to_entity_type = 'warehouse' AND dc.to_entity_id = tw.id
                LEFT JOIN companies tc ON dc.to_entity_type = 'company' AND dc.to_entity_id = tc.id
                LEFT JOIN users tu ON dc.to_entity_type = 'user' AND dc.to_entity_id = tu.id
                WHERE dc.asset_id = ? AND dc.status = ?
                ORDER BY dc.sequence_number DESC, dc.acceptance_date DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$assetId, self::STATUS_ACCEPTED], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get chain depth (number of transfers) for an asset
     * 
     * @param int $assetId Asset ID
     * @return int Number of transfers
     */
    public function getChainDepth($assetId) {
        $sql = "SELECT COUNT(*) as depth FROM `{$this->table}` 
                WHERE asset_id = ? AND status = ?";
        
        $result = $this->db->getResults($sql, [$assetId, self::STATUS_ACCEPTED], 'is');
        return (int)($result[0]['depth'] ?? 0);
    }
    
    /**
     * Find chain entries by entity (as sender or receiver)
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param string|null $role 'sender', 'receiver', or null for both
     * @return array Chain entries
     */
    public function findByEntity($entityType, $entityId, $role = null) {
        $sql = "SELECT dc.*, 
                       p.name as product_name,
                       a.serial_number,
                       d.dispatch_number
                FROM `{$this->table}` dc
                LEFT JOIN products p ON dc.product_id = p.id
                LEFT JOIN assets a ON dc.asset_id = a.id
                LEFT JOIN dispatches d ON dc.dispatch_id = d.id
                WHERE ";
        
        $params = [];
        $types = '';
        
        if ($role === 'sender') {
            $sql .= "dc.from_entity_type = ? AND dc.from_entity_id = ?";
            $params = [$entityType, $entityId];
            $types = 'si';
        } elseif ($role === 'receiver') {
            $sql .= "dc.to_entity_type = ? AND dc.to_entity_id = ?";
            $params = [$entityType, $entityId];
            $types = 'si';
        } else {
            $sql .= "(dc.from_entity_type = ? AND dc.from_entity_id = ?) OR (dc.to_entity_type = ? AND dc.to_entity_id = ?)";
            $params = [$entityType, $entityId, $entityType, $entityId];
            $types = 'sisi';
        }
        
        $sql .= " ORDER BY dc.dispatch_date DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get chain entries within date range
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param int|null $productId Optional product filter
     * @return array Chain entries
     */
    public function findByDateRange($startDate, $endDate, $productId = null) {
        $sql = "SELECT dc.*, 
                       p.name as product_name,
                       a.serial_number,
                       d.dispatch_number
                FROM `{$this->table}` dc
                LEFT JOIN products p ON dc.product_id = p.id
                LEFT JOIN assets a ON dc.asset_id = a.id
                LEFT JOIN dispatches d ON dc.dispatch_id = d.id
                WHERE dc.dispatch_date BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        $types = 'ss';
        
        if ($productId !== null) {
            $sql .= " AND dc.product_id = ?";
            $params[] = $productId;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY dc.dispatch_date DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Delete chain entries by dispatch ID
     * 
     * @param int $dispatchId Dispatch ID
     * @return int Number of deleted records
     */
    public function deleteByDispatch($dispatchId) {
        $sql = "DELETE FROM `{$this->table}` WHERE dispatch_id = ?";
        
        $stmt = $this->db->executeQuery($sql, [$dispatchId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Get summary statistics for dispatch chain
     * 
     * @param int|null $productId Optional product filter
     * @return array Summary statistics
     */
    public function getSummary($productId = null) {
        $sql = "SELECT 
                    COUNT(*) as total_transfers,
                    COUNT(DISTINCT asset_id) as unique_assets,
                    COUNT(DISTINCT product_id) as unique_products,
                    SUM(CASE WHEN status = 'dispatched' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM `{$this->table}`";
        
        $params = [];
        $types = '';
        
        if ($productId !== null) {
            $sql .= " WHERE product_id = ?";
            $params[] = $productId;
            $types = 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return $result[0] ?? [
            'total_transfers' => 0,
            'unique_assets' => 0,
            'unique_products' => 0,
            'pending_count' => 0,
            'accepted_count' => 0,
            'rejected_count' => 0
        ];
    }
}
