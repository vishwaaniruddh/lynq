<?php
/**
 * Inventory Notification Service
 * Handles notification operations for inventory transfers
 * 
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5
 * - 11.1: Notify recipient of pending materials when dispatch is created
 * - 11.2: Notify sender of successful delivery when dispatch is accepted
 * - 11.3: Notify sender with rejection reason when dispatch is rejected
 * - 11.4: Send reminder notifications for overdue pending receives
 * - 11.5: Display notification type, related dispatch, and timestamp
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InventoryNotificationRepository.php';
require_once __DIR__ . '/../repositories/PendingReceiveRepository.php';
require_once __DIR__ . '/../repositories/DispatchRepository.php';
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class InventoryNotificationService {
    private $db;
    private $notificationRepository;
    private $pendingReceiveRepository;
    private $dispatchRepository;
    private $warehouseRepository;
    private $userRepository;
    
    /**
     * Constructor - inject dependencies
     */
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->notificationRepository = new InventoryNotificationRepository();
        $this->pendingReceiveRepository = new PendingReceiveRepository();
        $this->dispatchRepository = new DispatchRepository();
        $this->dispatchRepository->disableCompanyFilter();
        $this->warehouseRepository = new WarehouseRepository();
        $this->warehouseRepository->disableCompanyFilter();
        $this->userRepository = new UserRepository();
        $this->userRepository->disableCompanyFilter();
    }
    
    /**
     * Notify recipient of pending materials when dispatch is created
     * Requirement: 11.1
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param string $senderType Sender entity type
     * @param int $senderId Sender entity ID
     * @param string $recipientType Recipient entity type
     * @param int $recipientId Recipient entity ID
     * @return array Result with success status
     */
    public function notifyPendingReceive(
        array $dispatch,
        array $pendingReceive,
        string $senderType,
        int $senderId,
        string $recipientType,
        int $recipientId
    ): array {
        try {
            // Get sender name
            $senderName = $this->getEntityName($senderType, $senderId);
            
            // Get recipient user ID for notification
            $recipientUserId = $this->getRecipientUserId($recipientType, $recipientId);
            
            if (!$recipientUserId) {
                return [
                    'success' => false,
                    'message' => 'Could not determine recipient user for notification',
                    'code' => 'NO_RECIPIENT_USER'
                ];
            }
            
            // Check if notification already exists to prevent duplicates
            if ($this->notificationRepository->existsForDispatch(
                $recipientUserId,
                $dispatch['id'],
                InventoryNotificationRepository::TYPE_PENDING_RECEIVE
            )) {
                return [
                    'success' => true,
                    'message' => 'Notification already exists',
                    'data' => ['duplicate' => true]
                ];
            }
            
            // Create the notification
            $notification = $this->notificationRepository->createPendingReceiveNotification(
                $recipientUserId,
                $dispatch['id'],
                $pendingReceive['id'],
                $senderName
            );
            
            return [
                'success' => true,
                'message' => 'Pending receive notification sent successfully',
                'data' => ['notification' => $notification]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send pending receive notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Notify sender of successful delivery when dispatch is accepted
     * Requirement: 11.2
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param int $acceptedByUserId User ID who accepted
     * @return array Result with success status
     */
    public function notifyAcceptance(
        array $dispatch,
        array $pendingReceive,
        int $acceptedByUserId
    ): array {
        try {
            // Get recipient name (the one who accepted)
            $recipientName = $this->getEntityName(
                $pendingReceive['recipient_type'],
                $pendingReceive['recipient_id']
            );
            
            // Get sender user ID to notify
            $senderUserId = $this->getSenderUserId($dispatch);
            
            if (!$senderUserId) {
                return [
                    'success' => false,
                    'message' => 'Could not determine sender user for notification',
                    'code' => 'NO_SENDER_USER'
                ];
            }
            
            // Create the notification
            $notification = $this->notificationRepository->createAcceptanceNotification(
                $senderUserId,
                $dispatch['id'],
                $pendingReceive['id'],
                $recipientName
            );
            
            return [
                'success' => true,
                'message' => 'Acceptance notification sent successfully',
                'data' => ['notification' => $notification]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send acceptance notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Notify sender with rejection reason when dispatch is rejected
     * Requirement: 11.3
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param int $rejectedByUserId User ID who rejected
     * @param string $reason Rejection reason
     * @return array Result with success status
     */
    public function notifyRejection(
        array $dispatch,
        array $pendingReceive,
        int $rejectedByUserId,
        string $reason
    ): array {
        try {
            // Get recipient name (the one who rejected)
            $recipientName = $this->getEntityName(
                $pendingReceive['recipient_type'],
                $pendingReceive['recipient_id']
            );
            
            // Get sender user ID to notify
            $senderUserId = $this->getSenderUserId($dispatch);
            
            if (!$senderUserId) {
                return [
                    'success' => false,
                    'message' => 'Could not determine sender user for notification',
                    'code' => 'NO_SENDER_USER'
                ];
            }
            
            // Create the notification
            $notification = $this->notificationRepository->createRejectionNotification(
                $senderUserId,
                $dispatch['id'],
                $pendingReceive['id'],
                $recipientName,
                $reason
            );
            
            return [
                'success' => true,
                'message' => 'Rejection notification sent successfully',
                'data' => ['notification' => $notification]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send rejection notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Notify sender of discrepancy when partial acceptance occurs
     * 
     * @param array $dispatch Dispatch data
     * @param array $pendingReceive Pending receive data
     * @param int $acceptedByUserId User ID who partially accepted
     * @param array $discrepancies Array of discrepancy records
     * @return array Result with success status
     */
    public function notifyDiscrepancy(
        array $dispatch,
        array $pendingReceive,
        int $acceptedByUserId,
        array $discrepancies
    ): array {
        try {
            // Get recipient name (the one who reported discrepancy)
            $recipientName = $this->getEntityName(
                $pendingReceive['recipient_type'],
                $pendingReceive['recipient_id']
            );
            
            // Get sender user ID to notify
            $senderUserId = $this->getSenderUserId($dispatch);
            
            if (!$senderUserId) {
                return [
                    'success' => false,
                    'message' => 'Could not determine sender user for notification',
                    'code' => 'NO_SENDER_USER'
                ];
            }
            
            // Build discrepancy details
            $discrepancyDetails = count($discrepancies) . " item(s) with quantity discrepancies";
            
            // Create the notification
            $notification = $this->notificationRepository->createDiscrepancyNotification(
                $senderUserId,
                $dispatch['id'],
                $pendingReceive['id'],
                $recipientName,
                $discrepancyDetails
            );
            
            return [
                'success' => true,
                'message' => 'Discrepancy notification sent successfully',
                'data' => ['notification' => $notification]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send discrepancy notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Send overdue reminder notifications
     * Requirement: 11.4
     * 
     * @param int $thresholdDays Number of days after which a pending receive is considered overdue
     * @return array Result with count of notifications sent
     */
    public function sendOverdueNotifications(int $thresholdDays = PendingReceiveRepository::DEFAULT_OVERDUE_DAYS): array {
        try {
            // Get all overdue pending receives
            $overdueReceives = $this->pendingReceiveRepository->findOverdue($thresholdDays);
            
            $notificationsSent = 0;
            $errors = [];
            
            foreach ($overdueReceives as $pendingReceive) {
                // Get recipient user ID
                $recipientUserId = $this->getRecipientUserId(
                    $pendingReceive['recipient_type'],
                    $pendingReceive['recipient_id']
                );
                
                if (!$recipientUserId) {
                    continue;
                }
                
                // Check if overdue notification already sent today
                if ($this->hasRecentOverdueNotification($recipientUserId, $pendingReceive['dispatch_id'])) {
                    continue;
                }
                
                try {
                    $this->notificationRepository->createOverdueNotification(
                        $recipientUserId,
                        $pendingReceive['dispatch_id'],
                        $pendingReceive['id'],
                        $pendingReceive['days_pending']
                    );
                    $notificationsSent++;
                } catch (Exception $e) {
                    $errors[] = [
                        'pending_receive_id' => $pendingReceive['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => "Sent $notificationsSent overdue notifications",
                'data' => [
                    'notifications_sent' => $notificationsSent,
                    'total_overdue' => count($overdueReceives),
                    'errors' => $errors
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send overdue notifications: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send overdue notifications: ' . $e->getMessage(),
                'code' => 'OVERDUE_NOTIFICATION_ERROR'
            ];
        }
    }

    
    /**
     * Get notifications for a user
     * Requirement: 11.5
     * 
     * @param int $userId User ID
     * @param bool|null $isRead Filter by read status (null for all)
     * @param int|null $limit Optional limit
     * @return array Result with notifications
     */
    public function getNotifications(int $userId, ?bool $isRead = null, ?int $limit = null): array {
        try {
            $notifications = $this->notificationRepository->findByUser($userId, $isRead, $limit);
            
            return [
                'success' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => [
                    'notifications' => $notifications,
                    'count' => count($notifications)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve notifications: ' . $e->getMessage(),
                'code' => 'GET_NOTIFICATIONS_ERROR'
            ];
        }
    }
    
    /**
     * Get unread notification count for a user
     * 
     * @param int $userId User ID
     * @return array Result with count
     */
    public function getUnreadCount(int $userId): array {
        try {
            $count = $this->notificationRepository->countUnreadByUser($userId);
            
            return [
                'success' => true,
                'data' => ['unread_count' => $count]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get unread count: ' . $e->getMessage(),
                'code' => 'COUNT_ERROR'
            ];
        }
    }
    
    /**
     * Mark notification as read
     * 
     * @param int $notificationId Notification ID
     * @return array Result with success status
     */
    public function markAsRead(int $notificationId): array {
        try {
            $notification = $this->notificationRepository->markAsRead($notificationId);
            
            return [
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => ['notification' => $notification]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage(),
                'code' => 'MARK_READ_ERROR'
            ];
        }
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return array Result with count of updated notifications
     */
    public function markAllAsRead(int $userId): array {
        try {
            $count = $this->notificationRepository->markAllAsReadByUser($userId);
            
            return [
                'success' => true,
                'message' => "Marked $count notifications as read",
                'data' => ['updated_count' => $count]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to mark notifications as read: ' . $e->getMessage(),
                'code' => 'MARK_ALL_READ_ERROR'
            ];
        }
    }
    
    /**
     * Get notification summary by type for a user
     * 
     * @param int $userId User ID
     * @return array Result with summary
     */
    public function getNotificationSummary(int $userId): array {
        try {
            $summary = $this->notificationRepository->getSummaryByUser($userId);
            
            return [
                'success' => true,
                'data' => ['summary' => $summary]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get notification summary: ' . $e->getMessage(),
                'code' => 'SUMMARY_ERROR'
            ];
        }
    }
    
    /**
     * Delete old read notifications
     * 
     * @param int $daysOld Delete notifications older than this many days
     * @return array Result with count of deleted notifications
     */
    public function cleanupOldNotifications(int $daysOld = 30): array {
        try {
            $count = $this->notificationRepository->deleteOldReadNotifications($daysOld);
            
            return [
                'success' => true,
                'message' => "Deleted $count old notifications",
                'data' => ['deleted_count' => $count]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to cleanup notifications: ' . $e->getMessage(),
                'code' => 'CLEANUP_ERROR'
            ];
        }
    }
    
    /**
     * Check if a recent overdue notification was sent (within last 24 hours)
     * 
     * @param int $userId User ID
     * @param int $dispatchId Dispatch ID
     * @return bool True if recent notification exists
     */
    private function hasRecentOverdueNotification(int $userId, int $dispatchId): bool {
        try {
            $sql = "SELECT COUNT(*) as count FROM inventory_notifications 
                    WHERE user_id = ? AND dispatch_id = ? 
                    AND notification_type = ? 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $result = $this->db->getResults($sql, [
                $userId,
                $dispatchId,
                InventoryNotificationRepository::TYPE_OVERDUE
            ], 'iis');
            
            return ($result[0]['count'] ?? 0) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get recipient user ID for notification
     * 
     * @param string $recipientType Recipient entity type
     * @param int $recipientId Recipient entity ID
     * @return int|null User ID or null
     */
    private function getRecipientUserId(string $recipientType, int $recipientId): ?int {
        try {
            if ($recipientType === 'user') {
                return $recipientId;
            }
            
            if ($recipientType === 'company') {
                // Get first admin user of the company for notification
                $sql = "SELECT u.id FROM users u 
                        INNER JOIN roles r ON u.role_id = r.id 
                        WHERE u.company_id = ? AND u.status = 1 
                        ORDER BY r.level DESC LIMIT 1";
                $result = $this->db->getResults($sql, [$recipientId], 'i');
                return !empty($result) ? (int)$result[0]['id'] : null;
            }
            
            if ($recipientType === 'warehouse') {
                // Get warehouse manager or first user of warehouse company
                $warehouse = $this->warehouseRepository->find($recipientId);
                if ($warehouse && $warehouse['company_id']) {
                    $sql = "SELECT u.id FROM users u 
                            INNER JOIN roles r ON u.role_id = r.id 
                            WHERE u.company_id = ? AND u.status = 1 
                            ORDER BY r.level DESC LIMIT 1";
                    $result = $this->db->getResults($sql, [$warehouse['company_id']], 'i');
                    return !empty($result) ? (int)$result[0]['id'] : null;
                }
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get sender user ID from dispatch for notifications
     * 
     * @param array $dispatch Dispatch data
     * @return int|null User ID to notify
     */
    private function getSenderUserId(array $dispatch): ?int {
        // If dispatch was created by a user, notify them
        if (!empty($dispatch['created_by'])) {
            return (int)$dispatch['created_by'];
        }
        
        // If sender is a user, notify them
        if (!empty($dispatch['sender_type']) && $dispatch['sender_type'] === 'user' && !empty($dispatch['sender_id'])) {
            return (int)$dispatch['sender_id'];
        }
        
        return null;
    }
    
    /**
     * Get entity name based on type and ID
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return string Entity name
     */
    private function getEntityName(string $entityType, int $entityId): string {
        try {
            switch ($entityType) {
                case 'user':
                    $user = $this->userRepository->find($entityId);
                    return $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'Unknown User';
                    
                case 'company':
                    $sql = "SELECT name FROM companies WHERE id = ?";
                    $result = $this->db->getResults($sql, [$entityId], 'i');
                    return !empty($result) ? $result[0]['name'] : 'Unknown Company';
                    
                case 'warehouse':
                    $warehouse = $this->warehouseRepository->find($entityId);
                    return $warehouse ? $warehouse['name'] : 'Unknown Warehouse';
                    
                default:
                    return 'Unknown';
            }
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
}
