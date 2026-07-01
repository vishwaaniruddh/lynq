<?php
/**
 * Installation Notification Repository
 * Provides data access for installation workflow notifications
 * 
 * Requirements: 1.4, 12.4, 13.5
 * - 1.4: Notify contractor when installation is initiated
 * - 12.4: Notify engineer when section is rejected
 * - 13.5: Notify contractor and engineer when ADV rejects
 */

require_once __DIR__ . '/BaseRepository.php';

class InstallationNotificationRepository extends BaseRepository {
    protected $table = 'installation_notifications';
    protected $companyIdColumn = null;
    protected $applyCompanyFilter = false;
    
    // Notification type constants
    const TYPE_INSTALLATION_INITIATED = 'installation_initiated';
    const TYPE_SECTION_REJECTED = 'section_rejected';
    const TYPE_ADV_REJECTED = 'adv_rejected';
    const TYPE_CONTRACTOR_REJECTED = 'contractor_rejected';
    const TYPE_ADV_APPROVED = 'adv_approved';
    const TYPE_CONTRACTOR_APPROVED = 'contractor_approved';
    
    /**
     * Get all valid notification types
     */
    public static function getNotificationTypes(): array {
        return [
            self::TYPE_INSTALLATION_INITIATED,
            self::TYPE_SECTION_REJECTED,
            self::TYPE_ADV_REJECTED,
            self::TYPE_CONTRACTOR_REJECTED,
            self::TYPE_ADV_APPROVED,
            self::TYPE_CONTRACTOR_APPROVED
        ];
    }
    
    /**
     * Check if notification type is valid
     */
    public static function isValidNotificationType(string $type): bool {
        return in_array($type, self::getNotificationTypes());
    }
    
    /**
     * Create a new notification
     * 
     * @param array $data Notification data
     * @return array Created notification record
     */
    public function create($data): array {
        // Validate required fields
        $requiredFields = ['user_id', 'notification_type', 'installation_id', 'title'];
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
    public function findByUser(int $userId, ?bool $isRead = null, ?int $limit = null): array {
        $sql = "SELECT n.*, 
                       i.atm_id,
                       i.status as installation_status,
                       s.site_name
                FROM `{$this->table}` n
                LEFT JOIN installations i ON n.installation_id = i.id
                LEFT JOIN sites s ON n.site_id = s.id
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
    public function findUnreadByUser(int $userId, ?int $limit = null): array {
        return $this->findByUser($userId, false, $limit);
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $id Notification ID
     * @return array Updated notification record
     */
    public function markAsRead(int $id): array {
        return $this->update($id, ['is_read' => true]);
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return int Number of updated records
     */
    public function markAllAsReadByUser(int $userId): int {
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
    public function countUnreadByUser(int $userId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `user_id` = ? AND `is_read` = 0";
        
        $result = $this->db->getResults($sql, [$userId], 'i');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Find notifications by installation
     * 
     * @param int $installationId Installation ID
     * @return array List of notifications
     */
    public function findByInstallation(int $installationId): array {
        $sql = "SELECT n.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM `{$this->table}` n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.installation_id = ?
                ORDER BY n.created_at DESC";
        
        return $this->db->getResults($sql, [$installationId], 'i');
    }
    
    /**
     * Create installation initiated notification
     * Requirement: 1.4
     * 
     * @param int $userId Contractor user ID to notify
     * @param int $installationId Installation ID
     * @param int $siteId Site ID
     * @param string $siteName Site name/ATM ID
     * @return array Created notification
     */
    public function createInstallationInitiatedNotification(
        int $userId, 
        int $installationId, 
        int $siteId, 
        string $siteName
    ): array {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_INSTALLATION_INITIATED,
            'installation_id' => $installationId,
            'site_id' => $siteId,
            'title' => 'New Installation Task',
            'message' => "A new installation has been initiated for site: $siteName. Please ensure materials are received and proceed with installation."
        ]);
    }
    
    /**
     * Create section rejected notification
     * Requirement: 12.4
     * 
     * @param int $userId Engineer user ID to notify
     * @param int $installationId Installation ID
     * @param int $siteId Site ID
     * @param string $section Section identifier
     * @param string $sectionLabel Section display label
     * @param string $reason Rejection reason
     * @param string $reviewerLevel Reviewer level (contractor/adv)
     * @return array Created notification
     */
    public function createSectionRejectedNotification(
        int $userId, 
        int $installationId, 
        int $siteId, 
        string $section,
        string $sectionLabel,
        string $reason,
        string $reviewerLevel
    ): array {
        $levelLabel = $reviewerLevel === 'adv' ? 'ADV' : 'Contractor';
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_SECTION_REJECTED,
            'installation_id' => $installationId,
            'site_id' => $siteId,
            'section' => $section,
            'title' => "Section Rejected by $levelLabel",
            'message' => "The '$sectionLabel' section has been rejected. Reason: $reason. Please review and resubmit."
        ]);
    }
    
    /**
     * Create ADV rejection notification
     * Requirement: 13.5
     * 
     * @param int $userId User ID to notify (contractor or engineer)
     * @param int $installationId Installation ID
     * @param int $siteId Site ID
     * @param string $section Section identifier
     * @param string $sectionLabel Section display label
     * @param string $reason Rejection reason
     * @return array Created notification
     */
    public function createAdvRejectionNotification(
        int $userId, 
        int $installationId, 
        int $siteId, 
        string $section,
        string $sectionLabel,
        string $reason
    ): array {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_ADV_REJECTED,
            'installation_id' => $installationId,
            'site_id' => $siteId,
            'section' => $section,
            'title' => 'Installation Rejected by ADV',
            'message' => "The '$sectionLabel' section has been rejected by ADV. Reason: $reason. Please review and resubmit."
        ]);
    }
    
    /**
     * Create contractor rejection notification
     * 
     * @param int $userId User ID to notify (engineer)
     * @param int $installationId Installation ID
     * @param int $siteId Site ID
     * @param string $section Section identifier
     * @param string $sectionLabel Section display label
     * @param string $reason Rejection reason
     * @return array Created notification
     */
    public function createContractorRejectionNotification(
        int $userId, 
        int $installationId, 
        int $siteId, 
        string $section,
        string $sectionLabel,
        string $reason
    ): array {
        return $this->create([
            'user_id' => $userId,
            'notification_type' => self::TYPE_CONTRACTOR_REJECTED,
            'installation_id' => $installationId,
            'site_id' => $siteId,
            'section' => $section,
            'title' => 'Installation Section Rejected',
            'message' => "The '$sectionLabel' section has been rejected by contractor reviewer. Reason: $reason. Please review and resubmit."
        ]);
    }
    
    /**
     * Check if notification exists for installation and type
     * Useful to prevent duplicate notifications
     * 
     * @param int $userId User ID
     * @param int $installationId Installation ID
     * @param string $notificationType Notification type
     * @return bool True if exists
     */
    public function existsForInstallation(int $userId, int $installationId, string $notificationType): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `user_id` = ? AND `installation_id` = ? AND `notification_type` = ?";
        
        $result = $this->db->getResults($sql, [$userId, $installationId, $notificationType], 'iis');
        return ($result[0]['count'] ?? 0) > 0;
    }
    
    /**
     * Delete old read notifications
     * 
     * @param int $daysOld Delete notifications older than this many days
     * @return int Number of deleted records
     */
    public function deleteOldReadNotifications(int $daysOld = 30): int {
        $sql = "DELETE FROM `{$this->table}` 
                WHERE `is_read` = 1 AND `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->executeQuery($sql, [$daysOld], 'i');
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
    public function getSummaryByUser(int $userId): array {
        $sql = "SELECT notification_type, 
                       COUNT(*) as total,
                       SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
                FROM `{$this->table}`
                WHERE user_id = ?
                GROUP BY notification_type";
        
        return $this->db->getResults($sql, [$userId], 'i');
    }
}
