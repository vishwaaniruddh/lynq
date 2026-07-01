<?php
/**
 * Pending Receive Repository
 * Provides data access for pending receives (dispatches awaiting acceptance)
 * 
 * Requirements: 2.1, 2.2, 2.5
 * - Display dispatch in contractor's pending receives list when items dispatched to contractor
 * - Display dispatch in engineer's pending receives list when items dispatched to engineer
 * - Highlight pending receives older than configurable threshold as overdue
 */

require_once __DIR__ . '/BaseRepository.php';

class PendingReceiveRepository extends BaseRepository {
    protected $table = 'pending_receives';
    protected $companyIdColumn = null; // Pending receives are entity-based
    protected $applyCompanyFilter = false;
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PARTIAL = 'partial';
    
    // Recipient type constants
    const RECIPIENT_WAREHOUSE = 'warehouse';
    const RECIPIENT_COMPANY = 'company';
    const RECIPIENT_USER = 'user';
    
    // Default overdue threshold in days
    const DEFAULT_OVERDUE_DAYS = 7;
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses() {
        return [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_PARTIAL
        ];
    }
    
    /**
     * Check if status is valid
     */
    public static function isValidStatus($status) {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Get all valid recipient types
     */
    public static function getRecipientTypes() {
        return [
            self::RECIPIENT_WAREHOUSE,
            self::RECIPIENT_COMPANY,
            self::RECIPIENT_USER
        ];
    }
    
    /**
     * Check if recipient type is valid
     */
    public static function isValidRecipientType($recipientType) {
        return in_array($recipientType, self::getRecipientTypes());
    }
    
    /**
     * Find pending receives by recipient
     * Requirements: 2.1, 2.2, 3.2, 3.3
     * 
     * @param string $recipientType Recipient type (warehouse, company, user)
     * @param int $recipientId Recipient ID
     * @param string|null $status Optional status filter
     * @return array List of pending receives with dispatch details
     */
    public function findByRecipient($recipientType, $recipientId, $status = null) {
        if (!self::isValidRecipientType($recipientType)) {
            throw new Exception("Invalid recipient type: $recipientType");
        }
        
        $sql = "SELECT pr.*, 
                       d.dispatch_number, d.dispatch_date, d.notes as dispatch_notes,
                       d.sender_type, d.sender_id, d.site_id,
                       d.from_warehouse_id, d.from_company_id,
                       fw.name as from_warehouse_name,
                       fc.name as from_company_name,
                       s.site_name, s.lho as site_lho, s.city as site_city,
                       CONCAT(su.first_name, ' ', su.last_name) as sender_user_name,
                       DATEDIFF(NOW(), pr.created_at) as days_pending,
                       CASE 
                           WHEN d.from_warehouse_id IS NOT NULL THEN fw.name
                           WHEN d.from_company_id IS NOT NULL THEN fc.name
                           WHEN d.sender_type = 'user' THEN CONCAT(su.first_name, ' ', su.last_name)
                           ELSE 'Unknown'
                       END as sender_name,
                       CASE 
                           WHEN d.from_warehouse_id IS NOT NULL THEN fc.name
                           ELSE NULL
                       END as sender_company_name,
                       (SELECT COUNT(*) FROM pending_receive_items pri WHERE pri.pending_receive_id = pr.id) as total_items,
                       (SELECT SUM(pri.expected_quantity) FROM pending_receive_items pri WHERE pri.pending_receive_id = pr.id) as total_expected_quantity
                FROM `{$this->table}` pr
                LEFT JOIN dispatches d ON pr.dispatch_id = d.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN sites s ON d.site_id = s.id
                LEFT JOIN users su ON d.sender_type = 'user' AND d.sender_id = su.id
                WHERE pr.recipient_type = ? AND pr.recipient_id = ?";
        
        $params = [$recipientType, $recipientId];
        $types = 'si';
        
        if ($status !== null) {
            if (!self::isValidStatus($status)) {
                throw new Exception("Invalid status: $status");
            }
            $sql .= " AND pr.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $sql .= " ORDER BY pr.created_at DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Find all pending (not yet processed) receives for a recipient
     * 
     * @param string $recipientType Recipient type
     * @param int $recipientId Recipient ID
     * @return array List of pending receives
     */
    public function findPending($recipientType, $recipientId) {
        return $this->findByRecipient($recipientType, $recipientId, self::STATUS_PENDING);
    }
    
    /**
     * Find overdue pending receives
     * Requirement: 2.5
     * 
     * @param int $thresholdDays Number of days after which a pending receive is considered overdue
     * @param string|null $recipientType Optional filter by recipient type
     * @param int|null $recipientId Optional filter by recipient ID
     * @return array List of overdue pending receives
     */
    public function findOverdue($thresholdDays = self::DEFAULT_OVERDUE_DAYS, $recipientType = null, $recipientId = null) {
        $sql = "SELECT pr.*, 
                       d.dispatch_number, d.dispatch_date, d.notes as dispatch_notes,
                       d.sender_type, d.sender_id,
                       fw.name as from_warehouse_name,
                       fc.name as from_company_name,
                       DATEDIFF(NOW(), pr.created_at) as days_pending,
                       CASE 
                           WHEN pr.recipient_type = 'warehouse' THEN rw.name
                           WHEN pr.recipient_type = 'company' THEN rc.name
                           WHEN pr.recipient_type = 'user' THEN CONCAT(ru.first_name, ' ', ru.last_name)
                       END as recipient_name
                FROM `{$this->table}` pr
                LEFT JOIN dispatches d ON pr.dispatch_id = d.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN warehouses rw ON pr.recipient_type = 'warehouse' AND pr.recipient_id = rw.id
                LEFT JOIN companies rc ON pr.recipient_type = 'company' AND pr.recipient_id = rc.id
                LEFT JOIN users ru ON pr.recipient_type = 'user' AND pr.recipient_id = ru.id
                WHERE pr.status = ? AND DATEDIFF(NOW(), pr.created_at) > ?";
        
        $params = [self::STATUS_PENDING, $thresholdDays];
        $types = 'si';
        
        if ($recipientType !== null) {
            $sql .= " AND pr.recipient_type = ?";
            $params[] = $recipientType;
            $types .= 's';
            
            if ($recipientId !== null) {
                $sql .= " AND pr.recipient_id = ?";
                $params[] = $recipientId;
                $types .= 'i';
            }
        }
        
        $sql .= " ORDER BY days_pending DESC, pr.created_at ASC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Find pending receive by dispatch ID
     * 
     * @param int $dispatchId Dispatch ID
     * @return array|null Pending receive record or null
     */
    public function findByDispatch($dispatchId) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `dispatch_id` = ?";
        $result = $this->db->getResults($sql, [$dispatchId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find pending receive with full details
     * 
     * @param int $id Pending receive ID
     * @return array|null Pending receive with details or null
     */
    public function findWithDetails($id) {
        $sql = "SELECT pr.*, 
                       d.dispatch_number, d.dispatch_date, d.notes as dispatch_notes,
                       d.sender_type, d.sender_id,
                       d.from_warehouse_id, d.from_company_id,
                       fw.name as from_warehouse_name,
                       fc.name as from_company_name,
                       CONCAT(ab.first_name, ' ', ab.last_name) as accepted_by_name,
                       DATEDIFF(NOW(), pr.created_at) as days_pending,
                       CASE 
                           WHEN pr.recipient_type = 'warehouse' THEN rw.name
                           WHEN pr.recipient_type = 'company' THEN rc.name
                           WHEN pr.recipient_type = 'user' THEN CONCAT(ru.first_name, ' ', ru.last_name)
                       END as recipient_name,
                       CASE 
                           WHEN d.sender_type = 'warehouse' THEN sw.name
                           WHEN d.sender_type = 'company' THEN sc.name
                           WHEN d.sender_type = 'user' THEN CONCAT(su.first_name, ' ', su.last_name)
                       END as sender_name
                FROM `{$this->table}` pr
                LEFT JOIN dispatches d ON pr.dispatch_id = d.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN companies fc ON d.from_company_id = fc.id
                LEFT JOIN users ab ON pr.accepted_by = ab.id
                LEFT JOIN warehouses rw ON pr.recipient_type = 'warehouse' AND pr.recipient_id = rw.id
                LEFT JOIN companies rc ON pr.recipient_type = 'company' AND pr.recipient_id = rc.id
                LEFT JOIN users ru ON pr.recipient_type = 'user' AND pr.recipient_id = ru.id
                LEFT JOIN warehouses sw ON d.sender_type = 'warehouse' AND d.sender_id = sw.id
                LEFT JOIN companies sc ON d.sender_type = 'company' AND d.sender_id = sc.id
                LEFT JOIN users su ON d.sender_type = 'user' AND d.sender_id = su.id
                WHERE pr.id = ?";
        
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Update status to accepted
     * 
     * @param int $id Pending receive ID
     * @param int $acceptedBy User ID who accepted
     * @return array Updated record
     */
    public function accept($id, $acceptedBy) {
        return $this->update($id, [
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => date('Y-m-d H:i:s'),
            'accepted_by' => $acceptedBy
        ]);
    }
    
    /**
     * Update status to rejected
     * 
     * @param int $id Pending receive ID
     * @param int $rejectedBy User ID who rejected
     * @param string $reason Rejection reason
     * @return array Updated record
     */
    public function reject($id, $rejectedBy, $reason) {
        if (empty(trim($reason))) {
            throw new Exception("Rejection reason is required");
        }
        
        return $this->update($id, [
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'accepted_at' => date('Y-m-d H:i:s'), // Using accepted_at for rejection timestamp too
            'accepted_by' => $rejectedBy
        ]);
    }
    
    /**
     * Update status to partial
     * 
     * @param int $id Pending receive ID
     * @param int $acceptedBy User ID who partially accepted
     * @return array Updated record
     */
    public function partialAccept($id, $acceptedBy) {
        return $this->update($id, [
            'status' => self::STATUS_PARTIAL,
            'accepted_at' => date('Y-m-d H:i:s'),
            'accepted_by' => $acceptedBy
        ]);
    }
    
    /**
     * Count pending receives by recipient
     * 
     * @param string $recipientType Recipient type
     * @param int $recipientId Recipient ID
     * @param string|null $status Optional status filter
     * @return int Count
     */
    public function countByRecipient($recipientType, $recipientId, $status = null) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `recipient_type` = ? AND `recipient_id` = ?";
        
        $params = [$recipientType, $recipientId];
        $types = 'si';
        
        if ($status !== null) {
            $sql .= " AND `status` = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Count pending (unprocessed) receives by recipient
     * 
     * @param string $recipientType Recipient type
     * @param int $recipientId Recipient ID
     * @return int Count
     */
    public function countPending($recipientType, $recipientId) {
        return $this->countByRecipient($recipientType, $recipientId, self::STATUS_PENDING);
    }
    
    /**
     * Count overdue pending receives by recipient
     * 
     * @param string $recipientType Recipient type
     * @param int $recipientId Recipient ID
     * @param int $thresholdDays Overdue threshold in days
     * @return int Count
     */
    public function countOverdue($recipientType, $recipientId, $thresholdDays = self::DEFAULT_OVERDUE_DAYS) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `recipient_type` = ? AND `recipient_id` = ? 
                AND `status` = ? AND DATEDIFF(NOW(), `created_at`) > ?";
        
        $result = $this->db->getResults($sql, [$recipientType, $recipientId, self::STATUS_PENDING, $thresholdDays], 'sisi');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Get pending receives with items
     * 
     * @param int $id Pending receive ID
     * @return array|null Pending receive with items or null if not found
     */
    public function findWithItems($id) {
        $pendingReceive = $this->findWithDetails($id);
        
        if (!$pendingReceive) {
            return null;
        }
        
        // Get items from dispatch_items through pending_receive_items
        $sql = "SELECT pri.*, di.product_id, di.asset_id, di.quantity as dispatch_quantity,
                       p.name as product_name, p.is_serializable,
                       a.serial_number
                FROM pending_receive_items pri
                LEFT JOIN dispatch_items di ON pri.dispatch_item_id = di.id
                LEFT JOIN products p ON di.product_id = p.id
                LEFT JOIN assets a ON di.asset_id = a.id
                WHERE pri.pending_receive_id = ?
                ORDER BY p.name";
        
        $pendingReceive['items'] = $this->db->getResults($sql, [$id], 'i');
        
        return $pendingReceive;
    }
    
    /**
     * Get all pending receives for multiple recipients (bulk query)
     * 
     * @param array $recipients Array of ['type' => string, 'id' => int]
     * @param string|null $status Optional status filter
     * @return array List of pending receives grouped by recipient
     */
    public function findByMultipleRecipients(array $recipients, $status = null) {
        if (empty($recipients)) {
            return [];
        }
        
        $conditions = [];
        $params = [];
        $types = '';
        
        foreach ($recipients as $recipient) {
            $conditions[] = "(pr.recipient_type = ? AND pr.recipient_id = ?)";
            $params[] = $recipient['type'];
            $params[] = $recipient['id'];
            $types .= 'si';
        }
        
        $sql = "SELECT pr.*, 
                       d.dispatch_number, d.dispatch_date,
                       DATEDIFF(NOW(), pr.created_at) as days_pending
                FROM `{$this->table}` pr
                LEFT JOIN dispatches d ON pr.dispatch_id = d.id
                WHERE (" . implode(' OR ', $conditions) . ")";
        
        if ($status !== null) {
            $sql .= " AND pr.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $sql .= " ORDER BY pr.created_at DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
}
