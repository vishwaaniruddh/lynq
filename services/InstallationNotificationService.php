<?php
/**
 * Installation Notification Service
 * Handles notification operations for installation workflow
 * 
 * Requirements: 1.4, 12.4, 13.5
 * - 1.4: Notify contractor when installation is initiated
 * - 12.4: Notify engineer when section is rejected
 * - 13.5: Notify contractor and engineer when ADV rejects
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InstallationNotificationRepository.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';
require_once __DIR__ . '/../config/InstallationSections.php';

class InstallationNotificationService {
    private $db;
    private $notificationRepository;
    private $installationRepository;
    private $siteRepository;
    private $userRepository;
    private $engineerAssignmentRepository;
    
    /**
     * Constructor - inject dependencies
     */
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->notificationRepository = new InstallationNotificationRepository();
        $this->installationRepository = new InstallationRepository();
        $this->siteRepository = new SiteRepository();
        $this->siteRepository->disableCompanyFilter();
        $this->userRepository = new UserRepository();
        $this->userRepository->disableCompanyFilter();
        $this->engineerAssignmentRepository = new EngineerAssignmentRepository();
        $this->engineerAssignmentRepository->disableCompanyFilter();
    }
    
    /**
     * Notify contractor when installation is initiated
     * Requirement: 1.4
     * 
     * @param array $installation Installation data
     * @return array Result with success status
     */
    public function notifyInstallationInitiated(array $installation): array {
        try {
            $installationId = $installation['id'];
            $siteId = $installation['site_id'];
            
            // Get site information
            $site = $this->siteRepository->findById($siteId);
            if (!$site) {
                return [
                    'success' => false,
                    'message' => 'Site not found',
                    'code' => 'SITE_NOT_FOUND'
                ];
            }
            
            $siteName = $site['site_name'] ?? $installation['atm_id'] ?? 'Unknown Site';
            
            // Get contractor users to notify (contractor_admin and contractor_manager)
            $contractorUsers = $this->getContractorUsersForSite($siteId);
            
            if (empty($contractorUsers)) {
                return [
                    'success' => true,
                    'message' => 'No contractor users to notify',
                    'data' => ['notifications_sent' => 0]
                ];
            }
            
            $notificationsSent = 0;
            $errors = [];
            
            foreach ($contractorUsers as $user) {
                try {
                    // Check if notification already exists to prevent duplicates
                    if ($this->notificationRepository->existsForInstallation(
                        $user['id'],
                        $installationId,
                        InstallationNotificationRepository::TYPE_INSTALLATION_INITIATED
                    )) {
                        continue;
                    }
                    
                    $this->notificationRepository->createInstallationInitiatedNotification(
                        $user['id'],
                        $installationId,
                        $siteId,
                        $siteName
                    );
                    $notificationsSent++;
                } catch (Exception $e) {
                    $errors[] = [
                        'user_id' => $user['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Also notify the assigned engineer if any
            $engineer = $this->getAssignedEngineerForSite($siteId);
            if ($engineer) {
                try {
                    if (!$this->notificationRepository->existsForInstallation(
                        $engineer['id'],
                        $installationId,
                        InstallationNotificationRepository::TYPE_INSTALLATION_INITIATED
                    )) {
                        $this->notificationRepository->createInstallationInitiatedNotification(
                            $engineer['id'],
                            $installationId,
                            $siteId,
                            $siteName
                        );
                        $notificationsSent++;
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'user_id' => $engineer['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => "Sent $notificationsSent installation initiated notifications",
                'data' => [
                    'notifications_sent' => $notificationsSent,
                    'errors' => $errors
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send installation initiated notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }

    
    /**
     * Notify engineer when assigned to an installation
     * Requirement: 2.5
     * 
     * @param array $installation Installation data
     * @param int $engineerId Engineer user ID
     * @return array Result with success status
     */
    public function notifyEngineerAssigned(array $installation, int $engineerId): array {
        try {
            $installationId = $installation['id'];
            $siteId = $installation['site_id'];
            
            // Get site information
            $site = $this->siteRepository->findById($siteId);
            $siteName = $site['site_name'] ?? $installation['atm_id'] ?? 'Unknown Site';
            
            // Check if notification already exists to prevent duplicates
            if ($this->notificationRepository->existsForInstallation(
                $engineerId,
                $installationId,
                InstallationNotificationRepository::TYPE_INSTALLATION_INITIATED
            )) {
                return [
                    'success' => true,
                    'message' => 'Notification already exists',
                    'data' => ['notifications_sent' => 0]
                ];
            }
            
            // Create notification for engineer
            $this->notificationRepository->createInstallationInitiatedNotification(
                $engineerId,
                $installationId,
                $siteId,
                $siteName
            );
            
            return [
                'success' => true,
                'message' => 'Engineer assignment notification sent',
                'data' => [
                    'notifications_sent' => 1,
                    'engineer_id' => $engineerId
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send engineer assignment notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Notify contractor when installation is delegated
     * Requirement: 1.5
     * 
     * @param array $installation Installation data
     * @param int $contractorId Contractor company ID
     * @return array Result with success status
     */
    public function notifyInstallationDelegated(array $installation, int $contractorId): array {
        try {
            $installationId = $installation['id'];
            $siteId = $installation['site_id'];
            
            // Get site information
            $site = $this->siteRepository->findById($siteId);
            $siteName = $site['site_name'] ?? $installation['atm_id'] ?? 'Unknown Site';
            
            // Get contractor users to notify (contractor_admin and contractor_manager)
            $sql = "SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name
                    FROM users u
                    INNER JOIN roles r ON u.role_id = r.id
                    WHERE u.company_id = ? 
                    AND u.status = 1
                    AND r.name IN ('contractor_admin', 'contractor_manager')";
            
            $contractorUsers = $this->db->getResults($sql, [$contractorId], 'i');
            
            if (empty($contractorUsers)) {
                return [
                    'success' => true,
                    'message' => 'No contractor users to notify',
                    'data' => ['notifications_sent' => 0]
                ];
            }
            
            $notificationsSent = 0;
            $errors = [];
            
            foreach ($contractorUsers as $user) {
                try {
                    // Check if notification already exists to prevent duplicates
                    if ($this->notificationRepository->existsForInstallation(
                        $user['id'],
                        $installationId,
                        InstallationNotificationRepository::TYPE_INSTALLATION_INITIATED
                    )) {
                        continue;
                    }
                    
                    $this->notificationRepository->createInstallationInitiatedNotification(
                        $user['id'],
                        $installationId,
                        $siteId,
                        $siteName
                    );
                    $notificationsSent++;
                } catch (Exception $e) {
                    $errors[] = [
                        'user_id' => $user['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => "Sent $notificationsSent installation delegation notifications",
                'data' => [
                    'notifications_sent' => $notificationsSent,
                    'errors' => $errors
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send installation delegation notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Notify engineer when section is rejected by contractor
     * Requirement: 12.4
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param string $reason Rejection reason
     * @param string $reviewerLevel Reviewer level ('contractor' or 'adv')
     * @return array Result with success status
     */
    public function notifySectionRejected(
        int $installationId, 
        string $section, 
        string $reason,
        string $reviewerLevel = 'contractor'
    ): array {
        try {
            // Get installation
            $installation = $this->installationRepository->findById($installationId);
            if (!$installation) {
                return [
                    'success' => false,
                    'message' => 'Installation not found',
                    'code' => 'INSTALLATION_NOT_FOUND'
                ];
            }
            
            $siteId = $installation['site_id'];
            $sectionLabel = InstallationSections::getLabel($section);
            
            // Get engineer who submitted the installation
            $engineerId = $installation['submitted_by'] ?? $installation['created_by'];
            
            if (!$engineerId) {
                return [
                    'success' => false,
                    'message' => 'No engineer found for this installation',
                    'code' => 'NO_ENGINEER'
                ];
            }
            
            // Create notification for engineer
            $this->notificationRepository->createSectionRejectedNotification(
                $engineerId,
                $installationId,
                $siteId,
                $section,
                $sectionLabel,
                $reason,
                $reviewerLevel
            );
            
            return [
                'success' => true,
                'message' => 'Section rejection notification sent to engineer',
                'data' => [
                    'engineer_id' => $engineerId,
                    'section' => $section
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send section rejection notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Notify contractor and engineer when ADV rejects
     * Requirement: 13.5
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param string $reason Rejection reason
     * @return array Result with success status
     */
    public function notifyAdvRejection(
        int $installationId, 
        string $section, 
        string $reason
    ): array {
        try {
            // Get installation
            $installation = $this->installationRepository->findById($installationId);
            if (!$installation) {
                return [
                    'success' => false,
                    'message' => 'Installation not found',
                    'code' => 'INSTALLATION_NOT_FOUND'
                ];
            }
            
            $siteId = $installation['site_id'];
            $sectionLabel = InstallationSections::getLabel($section);
            
            $notificationsSent = 0;
            $errors = [];
            
            // Notify engineer
            $engineerId = $installation['submitted_by'] ?? $installation['created_by'];
            if ($engineerId) {
                try {
                    $this->notificationRepository->createAdvRejectionNotification(
                        $engineerId,
                        $installationId,
                        $siteId,
                        $section,
                        $sectionLabel,
                        $reason
                    );
                    $notificationsSent++;
                } catch (Exception $e) {
                    $errors[] = [
                        'user_id' => $engineerId,
                        'role' => 'engineer',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Notify contractor users
            $contractorUsers = $this->getContractorUsersForSite($siteId);
            foreach ($contractorUsers as $user) {
                try {
                    $this->notificationRepository->createAdvRejectionNotification(
                        $user['id'],
                        $installationId,
                        $siteId,
                        $section,
                        $sectionLabel,
                        $reason
                    );
                    $notificationsSent++;
                } catch (Exception $e) {
                    $errors[] = [
                        'user_id' => $user['id'],
                        'role' => 'contractor',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => "Sent $notificationsSent ADV rejection notifications",
                'data' => [
                    'notifications_sent' => $notificationsSent,
                    'errors' => $errors
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Failed to send ADV rejection notification: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
                'code' => 'NOTIFICATION_ERROR'
            ];
        }
    }
    
    /**
     * Get notifications for a user
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
     * Get contractor users (admin and manager) for a site
     * 
     * @param int $siteId Site ID
     * @return array Array of user records
     */
    private function getContractorUsersForSite(int $siteId): array {
        try {
            // Get site to find company
            $site = $this->siteRepository->findById($siteId);
            if (!$site) {
                return [];
            }
            
            // Get delegation to find contractor company
            $sql = "SELECT sd.contractor_id 
                    FROM site_delegations sd 
                    WHERE sd.site_id = ? AND sd.status = 'active'
                    ORDER BY sd.created_at DESC LIMIT 1";
            $result = $this->db->getResults($sql, [$siteId], 'i');
            
            if (empty($result)) {
                return [];
            }
            
            $contractorCompanyId = $result[0]['contractor_id'];
            
            // Get contractor_admin and contractor_manager users for this company
            $sql = "SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name
                    FROM users u
                    INNER JOIN roles r ON u.role_id = r.id
                    WHERE u.company_id = ? 
                    AND u.status = 1
                    AND r.name IN ('contractor_admin', 'contractor_manager')";
            
            return $this->db->getResults($sql, [$contractorCompanyId], 'i');
            
        } catch (Exception $e) {
            error_log("Failed to get contractor users for site: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get assigned engineer for a site
     * 
     * @param int $siteId Site ID
     * @return array|null Engineer user record or null
     */
    private function getAssignedEngineerForSite(int $siteId): ?array {
        try {
            // Get active engineer assignment for this site
            $sql = "SELECT ea.engineer_id, u.id, u.first_name, u.last_name, u.email
                    FROM engineer_assignments ea
                    INNER JOIN users u ON ea.engineer_id = u.id
                    WHERE ea.site_id = ? AND ea.status = 'active'
                    ORDER BY ea.created_at DESC LIMIT 1";
            
            $result = $this->db->getResults($sql, [$siteId], 'i');
            
            return !empty($result) ? $result[0] : null;
            
        } catch (Exception $e) {
            error_log("Failed to get assigned engineer for site: " . $e->getMessage());
            return null;
        }
    }
}
