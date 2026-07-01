<?php
/**
 * Discrepancy Repository
 * Provides data access for tracking quantity differences between dispatched and received items
 * 
 * Requirements: 10.3
 * - Create discrepancy record for the difference when partial acceptance occurs
 */

require_once __DIR__ . '/BaseRepository.php';

class DiscrepancyRepository extends BaseRepository {
    protected $table = 'discrepancies';
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    // Discrepancy type constants
    const TYPE_SHORTAGE = 'shortage';
    const TYPE_DAMAGE = 'damage';
    const TYPE_WRONG_ITEM = 'wrong_item';
    const TYPE_EXCESS = 'excess';
    
    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_WRITTEN_OFF = 'written_off';
    
    /**
     * Get all valid discrepancy types
     */
    public static function getDiscrepancyTypes() {
        return [
            self::TYPE_SHORTAGE,
            self::TYPE_DAMAGE,
            self::TYPE_WRONG_ITEM,
            self::TYPE_EXCESS
        ];
    }
    
    /**
     * Check if discrepancy type is valid
     */
    public static function isValidDiscrepancyType($type) {
        return in_array($type, self::getDiscrepancyTypes());
    }
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_OPEN,
            self::STATUS_RESOLVED,
            self::STATUS_WRITTEN_OFF
        ];
    }
    
    /**
     * Check if status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Create a new discrepancy record
     * Requirement: 10.3
     * 
     * @param array $data Discrepancy data
     * @return array Created discrepancy record
     */
    public function create($data) {
        // Validate required fields
        $requiredFields = ['dispatch_id', 'pending_receive_id', 'product_id', 'expected_quantity', 'received_quantity', 'discrepancy_type'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Validate discrepancy type
        if (!self::isValidDiscrepancyType($data['discrepancy_type'])) {
            throw new Exception("Invalid discrepancy type: {$data['discrepancy_type']}");
        }
        
        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_OPEN;
        }
        
        return parent::create($data);
    }
    
    /**
     * Find discrepancies by dispatch ID
     * 
     * @param int $dispatchId Dispatch ID
     * @return array List of discrepancies with product details
     */
    public function findByDispatch($dispatchId) {
        $sql = "SELECT disc.*, 
                       p.name as product_name,
                       a.serial_number,
                       CONCAT(rb.first_name, ' ', rb.last_name) as resolved_by_name
                FROM `{$this->table}` disc
                LEFT JOIN products p ON disc.product_id = p.id
                LEFT JOIN assets a ON disc.asset_id = a.id
                LEFT JOIN users rb ON disc.resolved_by = rb.id
                WHERE disc.dispatch_id = ?
                ORDER BY disc.created_at DESC";
        
        return $this->db->getResults($sql, [$dispatchId], 'i');
    }
    
    /**
     * Find discrepancies by pending receive ID
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return array List of discrepancies
     */
    public function findByPendingReceive($pendingReceiveId) {
        $sql = "SELECT disc.*, 
                       p.name as product_name,
                       a.serial_number,
                       CONCAT(rb.first_name, ' ', rb.last_name) as resolved_by_name
                FROM `{$this->table}` disc
                LEFT JOIN products p ON disc.product_id = p.id
                LEFT JOIN assets a ON disc.asset_id = a.id
                LEFT JOIN users rb ON disc.resolved_by = rb.id
                WHERE disc.pending_receive_id = ?
                ORDER BY disc.created_at DESC";
        
        return $this->db->getResults($sql, [$pendingReceiveId], 'i');
    }
    
    /**
     * Find discrepancies by status
     * 
     * @param string $status Status to filter by
     * @return array List of discrepancies
     */
    public function findByStatus($status) {
        if (!self::isValidStatus($status)) {
            throw new Exception("Invalid status: $status");
        }
        
        $sql = "SELECT disc.*, 
                       p.name as product_name,
                       d.dispatch_number,
                       CONCAT(rb.first_name, ' ', rb.last_name) as resolved_by_name
                FROM `{$this->table}` disc
                LEFT JOIN products p ON disc.product_id = p.id
                LEFT JOIN dispatches d ON disc.dispatch_id = d.id
                LEFT JOIN users rb ON disc.resolved_by = rb.id
                WHERE disc.status = ?
                ORDER BY disc.created_at DESC";
        
        return $this->db->getResults($sql, [$status], 's');
    }
    
    /**
     * Find open discrepancies
     * 
     * @return array List of open discrepancies
     */
    public function findOpen() {
        return $this->findByStatus(self::STATUS_OPEN);
    }
    
    /**
     * Resolve a discrepancy
     * 
     * @param int $id Discrepancy ID
     * @param int $resolvedBy User ID who resolved
     * @param string|null $resolutionNotes Optional resolution notes
     * @return array Updated discrepancy record
     */
    public function resolve($id, $resolvedBy, $resolutionNotes = null) {
        $updateData = [
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $resolvedBy
        ];
        
        if ($resolutionNotes !== null) {
            $updateData['resolution_notes'] = $resolutionNotes;
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Write off a discrepancy
     * 
     * @param int $id Discrepancy ID
     * @param int $writtenOffBy User ID who wrote off
     * @param string|null $notes Optional notes
     * @return array Updated discrepancy record
     */
    public function writeOff($id, $writtenOffBy, $notes = null) {
        $updateData = [
            'status' => self::STATUS_WRITTEN_OFF,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $writtenOffBy
        ];
        
        if ($notes !== null) {
            $updateData['resolution_notes'] = $notes;
        }
        
        return $this->update($id, $updateData);
    }
    
    /**
     * Find discrepancy with full details
     * 
     * @param int $id Discrepancy ID
     * @return array|null Discrepancy with details or null
     */
    public function findWithDetails($id) {
        $sql = "SELECT disc.*, 
                       p.name as product_name, p.is_serializable,
                       a.serial_number,
                       d.dispatch_number, d.dispatch_date,
                       pr.recipient_type, pr.recipient_id,
                       CONCAT(rb.first_name, ' ', rb.last_name) as resolved_by_name,
                       CASE 
                           WHEN pr.recipient_type = 'warehouse' THEN rw.name
                           WHEN pr.recipient_type = 'company' THEN rc.name
                           WHEN pr.recipient_type = 'user' THEN CONCAT(ru.first_name, ' ', ru.last_name)
                       END as recipient_name
                FROM `{$this->table}` disc
                LEFT JOIN products p ON disc.product_id = p.id
                LEFT JOIN assets a ON disc.asset_id = a.id
                LEFT JOIN dispatches d ON disc.dispatch_id = d.id
                LEFT JOIN pending_receives pr ON disc.pending_receive_id = pr.id
                LEFT JOIN users rb ON disc.resolved_by = rb.id
                LEFT JOIN warehouses rw ON pr.recipient_type = 'warehouse' AND pr.recipient_id = rw.id
                LEFT JOIN companies rc ON pr.recipient_type = 'company' AND pr.recipient_id = rc.id
                LEFT JOIN users ru ON pr.recipient_type = 'user' AND pr.recipient_id = ru.id
                WHERE disc.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find discrepancies by product
     * 
     * @param int $productId Product ID
     * @param string|null $status Optional status filter
     * @return array List of discrepancies
     */
    public function findByProduct($productId, $status = null) {
        $sql = "SELECT disc.*, 
                       d.dispatch_number, d.dispatch_date,
                       CONCAT(rb.first_name, ' ', rb.last_name) as resolved_by_name
                FROM `{$this->table}` disc
                LEFT JOIN dispatches d ON disc.dispatch_id = d.id
                LEFT JOIN users rb ON disc.resolved_by = rb.id
                WHERE disc.product_id = ?";
        
        $params = [$productId];
        $types = 'i';
        
        if ($status !== null) {
            $sql .= " AND disc.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $sql .= " ORDER BY disc.created_at DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Count discrepancies by status
     * 
     * @param string|null $status Optional status filter
     * @return int Count
     */
    public function countByStatus($status = null) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}`";
        $params = [];
        $types = '';
        
        if ($status !== null) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
            $types = 's';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Count open discrepancies
     * 
     * @return int Count
     */
    public function countOpen() {
        return $this->countByStatus(self::STATUS_OPEN);
    }
    
    /**
     * Get discrepancy summary by type
     * 
     * @param string|null $status Optional status filter
     * @return array Summary grouped by type
     */
    public function getSummaryByType($status = null) {
        $sql = "SELECT discrepancy_type, 
                       COUNT(*) as count,
                       SUM(expected_quantity - received_quantity) as total_discrepancy
                FROM `{$this->table}`";
        
        $params = [];
        $types = '';
        
        if ($status !== null) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
            $types = 's';
        }
        
        $sql .= " GROUP BY discrepancy_type";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Create discrepancy from partial acceptance
     * Helper method to create discrepancy when received quantity differs from expected
     * 
     * @param int $dispatchId Dispatch ID
     * @param int $pendingReceiveId Pending receive ID
     * @param int $productId Product ID
     * @param int $expectedQuantity Expected quantity
     * @param int $receivedQuantity Received quantity
     * @param int|null $assetId Optional asset ID for serializable items
     * @param string|null $notes Optional notes
     * @return array Created discrepancy record
     */
    public function createFromPartialAcceptance($dispatchId, $pendingReceiveId, $productId, $expectedQuantity, $receivedQuantity, $assetId = null, $notes = null) {
        // Determine discrepancy type based on quantities
        $discrepancyType = $receivedQuantity < $expectedQuantity ? self::TYPE_SHORTAGE : self::TYPE_EXCESS;
        
        $data = [
            'dispatch_id' => $dispatchId,
            'pending_receive_id' => $pendingReceiveId,
            'product_id' => $productId,
            'expected_quantity' => $expectedQuantity,
            'received_quantity' => $receivedQuantity,
            'discrepancy_type' => $discrepancyType,
            'status' => self::STATUS_OPEN
        ];
        
        if ($assetId !== null) {
            $data['asset_id'] = $assetId;
        }
        
        if ($notes !== null) {
            $data['notes'] = $notes;
        }
        
        return $this->create($data);
    }
}
