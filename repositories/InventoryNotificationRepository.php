<?php
/**
 * Inventory Notification Repository
 * Provides data access for inventory transfer notifications
 * 
 * Requirements: 11.1, 11.2, 11.3
 * - Notify recipient of pending materials when dispatch is created
 * - Notify sender of successful delivery when dispatch is accepted
 * - Notify sender with rejection reason when dispatch is rejected
 */

require_once __DIR__ . '/BaseRepository.php';

class InventoryNotificationRepository extends BaseRepository {
    protected $table = 'inventory_notifications';
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    // Notification type constants
    const TYPE_PENDING_RECEIVE = 'pending_receive';
    const TYPE_ACCEPTED = 'accepted';
    const TYPE_REJECTED = 'rejected';
    const TYPE_OVERDUE = 'overdue';
    const TYPE_DISCREPANCY = 'discrepancy';
    
    /**
     * Get all valid notification types
     */
    public static function getNotificationTypes() {
        return [
            self::TYPE_PENDING_RECEIVE,
            self::TYPE_ACCEPTED,
            self::TYPE_REJECTED,
            self::TYPE_OVERDUE,
            self::TYPE_DISCREPANCY
        ];
    }
    
    /**
     * Check if notification type is valid
     */
    public static function isValidNotificationType($type) {
        return in_array($type, self::getNotificationTypes());
    }
    
    /**
     * Create a new notification
     * Requirements: 11.1, 11.2, 11.3
     * 
     * @param array $data Notification data
     * @return array Created notification record
     */
    public function create($data) {
        // Validate required fields
        $requiredFields = ['user_id', 'notification_type', 'title'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Validate notification type
        if (!self::isValidNotificationType($data['notification_type'])) {
            throw new Exception("Invalid notification type: {$data['notification_type']}");
        }
        
        // Set default is_read if not provided
        if (!isset($data['is_read'])) {
            $data['is_read'] = false;
        }
        
        return parent::create($data);
    }
    
    /**
     * Find notifications by user
     * 
     * @param int $userId User ID
     * @param bool|null $isRead Filter by read status (null for all)
     * @param int|null $limit Optional limit
     * @return array List of notifications
     */
    public function findByUser($userId, $isRead = null, $limit = null) {
        $sql = "SELECT n.*, 
                       d.dispatch_number,
                       pr.status as pending_receive_status
                FROM `{$this->table}` n
                LEFT JOIN dispatches d ON n.dispatch_id = d.id
                LEFT JOIN pending_receives pr ON n.pending_receive_id = pr.id
                WHERE n.user_id = ?";
        
        $params = [$userId];
        $types = 'i';
        
        if ($isRead !== null) {
            $sql .= " AND n.is_read = ?";
            $params[] = $isRead ? 1 : 0;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY n.created_at DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Find unread notifications by user
     * 
     * @param int $userId User ID
     * @param int|null $limit Optional limit
     * @return array List of unread notifications
     */
    public function findUnreadByUser($userId, $limit = null) {
        return $this->findByUser($userId, false, $limit);
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $id Notification ID
     * @return array Updated notification record
     */
    public function markAsRead($id) {
        return $this->update($id, ['is_read' => true]);
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return int Number of updated records
     */
    public function markAllAsReadByUser($userId) {
        $sql = "UPDATE `{$this->table}` SET `is_read` = 1 WHERE `user_id` = ? AND `is_read` = 0";
        
        $stmt = $this->db->executeQuery($sql, [$userId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Count unread notifications for a user
     * 
     * @param int $userId User ID
     * @return int Count of unread notifications
     */
    public function countUnreadByUser($userId) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `user_id` = ? AND `is_read` = 0";
        
        $result = $this->db->getResults($sql, [$userId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Find notifications by dispatch
     * 
     * @param int $dispatchId Dispatch ID
     * @return array List of notifications
     */
    public function findByDispatch($dispatchId) {
        $sql = "SELECT n.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.dispatch_id = ?
                ORDER BY n.created_at DESC";
        
        return $this->db->getResults($sql, [$dispatchId], 'i');
    }
    
    /**
     * Find notifications by pending receive
     * 
     * @param int $pendingReceiveId Pending receive ID
     * @return array List of notifications
     */
    public function findByPendingReceive($pendingReceiveId) {
        $sql = "SELECT n.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.pending_receive_id = ?
                ORDER BY n.created_at DESC";
        
        return $this->db->getResults($sql, [$pendingReceiveId], 'i');
    }
    
    /**
     * Find notifications by type
     * 
     * @param string $notificationType Notification type
     * @param int|null $userId Optional user filter
     * @return array List of notifications
     */
    public function findByType($notificationType, $userId = null) {
        if (!self::isValidNotificationType($notificationType)) {
            throw new Exception("Invalid notification type: $notificationType");
        }
        
        $sql = "SELECT n.*, 
                       d.dispatch_number,
                       CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` n
                LEFT JOIN dispatches d ON n.dispatch_id = d.id
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.notification_type = ?";
        
        $params = [$notificationType];
        $types = 's';
        
        if ($userId !== null) {
            $sql .= " AND n.user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY n.created_at DESC";
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Create pending receive notification
     * Requirement: 11.1
     * 
     * @param int $userId Recipient user ID
     * @param int $dispatchId Dispatch ID
     * @param int $pendingReceiveId Pending receive ID
     * @param string $senderName Name of sender
     * @return array Created notification
     */
    public function createPendingReceiveNotification($userId, $dispatchId, $pendingReceiveId, $senderName) {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_PENDING_RECEIVE,
            'dispatch_id' => $dispatchId,
            'pending_receive_id' => $pendingReceiveId,
            'title' => 'New Materials Pending',
            'message' => "You have new materials pending from $senderName. Please review and accept or reject."
        ]);
    }
    
    /**
     * Create acceptance notification
     * Requirement: 11.2
     * 
     * @param int $userId Sender user ID
     * @param int $dispatchId Dispatch ID
     * @param int $pendingReceiveId Pending receive ID
     * @param string $recipientName Name of recipient who accepted
     * @return array Created notification
     */
    public function createAcceptanceNotification($userId, $dispatchId, $pendingReceiveId, $recipientName) {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_ACCEPTED,
            'dispatch_id' => $dispatchId,
            'pending_receive_id' => $pendingReceiveId,
            'title' => 'Dispatch Accepted',
            'message' => "Your dispatch has been accepted by $recipientName."
        ]);
    }
    
    /**
     * Create rejection notification
     * Requirement: 11.3
     * 
     * @param int $userId Sender user ID
     * @param int $dispatchId Dispatch ID
     * @param int $pendingReceiveId Pending receive ID
     * @param string $recipientName Name of recipient who rejected
     * @param string $reason Rejection reason
     * @return array Created notification
     */
    public function createRejectionNotification($userId, $dispatchId, $pendingReceiveId, $recipientName, $reason) {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_REJECTED,
            'dispatch_id' => $dispatchId,
            'pending_receive_id' => $pendingReceiveId,
            'title' => 'Dispatch Rejected',
            'message' => "Your dispatch has been rejected by $recipientName. Reason: $reason"
        ]);
    }
    
    /**
     * Create overdue notification
     * 
     * @param int $userId Recipient user ID
     * @param int $dispatchId Dispatch ID
     * @param int $pendingReceiveId Pending receive ID
     * @param int $daysPending Number of days pending
     * @return array Created notification
     */
    public function createOverdueNotification($userId, $dispatchId, $pendingReceiveId, $daysPending) {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_OVERDUE,
            'dispatch_id' => $dispatchId,
            'pending_receive_id' => $pendingReceiveId,
            'title' => 'Overdue Pending Receive',
            'message' => "You have a pending receive that has been waiting for $daysPending days. Please review and process."
        ]);
    }
    
    /**
     * Create discrepancy notification
     * 
     * @param int $userId Sender user ID
     * @param int $dispatchId Dispatch ID
     * @param int $pendingReceiveId Pending receive ID
     * @param string $recipientName Name of recipient
     * @param string $discrepancyDetails Details of the discrepancy
     * @return array Created notification
     */
    public function createDiscrepancyNotification($userId, $dispatchId, $pendingReceiveId, $recipientName, $discrepancyDetails) {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_DISCREPANCY,
            'dispatch_id' => $dispatchId,
            'pending_receive_id' => $pendingReceiveId,
            'title' => 'Dispatch Discrepancy Reported',
            'message' => "$recipientName reported a discrepancy: $discrepancyDetails"
        ]);
    }
    
    /**
     * Delete old read notifications
     * 
     * @param int $daysOld Delete notifications older than this many days
     * @return int Number of deleted records
     */
    public function deleteOldReadNotifications($daysOld = 30) {
        $sql = "DELETE FROM `{$this->table}` 
                WHERE `is_read` = 1 AND `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->executeQuery($sql, [$daysOld], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Delete notifications by user
     * 
     * @param int $userId User ID
     * @return int Number of deleted records
     */
    public function deleteByUser($userId) {
        $sql = "DELETE FROM `{$this->table}` WHERE `user_id` = ?";
        
        $stmt = $this->db->executeQuery($sql, [$userId], 'i');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Get notification summary by type for a user
     * 
     * @param int $userId User ID
     * @return array Summary grouped by type
     */
    public function getSummaryByUser($userId) {
        $sql = "SELECT notification_type, 
                       COUNT(*) as total,
                       SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
                FROM `{$this->table}`
                WHERE user_id = ?
                GROUP BY notification_type";
        
        return $this->db->getResults($sql, [$userId], 'i');
    }
    
    /**
     * Check if notification exists for dispatch and type
     * Useful to prevent duplicate notifications
     * 
     * @param int $userId User ID
     * @param int $dispatchId Dispatch ID
     * @param string $notificationType Notification type
     * @return bool True if exists
     */
    public function existsForDispatch($userId, $dispatchId, $notificationType) {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `user_id` = ? AND `dispatch_id` = ? AND `notification_type` = ?";
        
        $result = $this->db->getResults($sql, [$userId, $dispatchId, $notificationType], 'iis');
        return ($result[0]['count'] ?? 0) > 0;
    }
}
