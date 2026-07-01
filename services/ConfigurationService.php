<?php
/**
 * Configuration Service
 * Handles the complete configuration workflow for binding IP_Master to routers
 * 
 * Requirements: 3.1, 4.4, 4.5, 5.1
 * - 3.1: Automatic IP assignment when selecting router
 * - 4.4: Complete configuration creates permanent binding
 * - 4.5: Cancel configuration releases lock immediately
 * - 5.1: Create permanent binding between router and IP_Master
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';
require_once __DIR__ . '/../models/RouterIPBinding.php';
require_once __DIR__ . '/../models/ConfigurationAuditLog.php';
require_once __DIR__ . '/LockService.php';

class ConfigurationService {
    private $db;
    private $lockService;
    private $lockRepository;
    private $ipMasterRepository;
    private $bindingModel;
    private $auditLog;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->lockService = new LockService();
        $this->lockRepository = new IPLockRepository();
        $this->ipMasterRepository = new IPMasterRepository();
        $this->bindingModel = new RouterIPBinding();
        $this->auditLog = new ConfigurationAuditLog();
    }
    
    /**
     * Start a configuration session for a router
     * Gets the next available IP, acquires a lock, and returns the IP details
     * 
     * @param string $routerSerialNumber Router serial number to configure
     * @param int $userId User ID starting the configuration
     * @param int|null $specificIPMasterId Optional specific IP_Master ID to use
     * @return array Result with success status, lock data, and IP details
     * 
     * Requirements: 3.1, 4.1
     */
    public function startConfiguration(string $routerSerialNumber, int $userId, ?int $specificIPMasterId = null): array {
        // Validate router serial number
        if (empty(trim($routerSerialNumber))) {
            return [
                'success' => false,
                'message' => 'Router serial number is required',
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        $routerSerialNumber = trim($routerSerialNumber);
        
        // Check if router already has an active configuration session
        $existingLock = $this->lockService->getActiveLockByRouter($routerSerialNumber);
        if ($existingLock) {
            return [
                'success' => false,
                'message' => 'Router already has an active configuration session',
                'code' => 'ROUTER_IN_SESSION',
                'data' => [
                    'lock_id' => $existingLock['id'],
                    'expires_at' => $existingLock['expires_at'],
                    'remaining_seconds' => IPLock::getRemainingSeconds($existingLock)
                ]
            ];
        }
        
        // Check if router is already configured with an IP
        $existingBinding = $this->bindingModel->findByRouter($routerSerialNumber);
        if ($existingBinding) {
            return [
                'success' => false,
                'message' => 'Router is already configured with an IP. Unbind first to reconfigure.',
                'code' => 'ALREADY_CONFIGURED',
                'data' => [
                    'binding_id' => $existingBinding['id'],
                    'ip_master_id' => $existingBinding['ip_master_id'],
                    'network_ip' => $existingBinding['network_ip'],
                    'router_ip' => $existingBinding['router_ip'],
                    'site_ip' => $existingBinding['site_ip'],
                    'subnet_mask' => $existingBinding['subnet_mask']
                ]
            ];
        }
        
        // Get the IP_Master to use
        $ipMaster = null;
        
        if ($specificIPMasterId !== null) {
            // User requested a specific IP
            $ipMaster = $this->ipMasterRepository->findById($specificIPMasterId);
            if (!$ipMaster) {
                return [
                    'success' => false,
                    'message' => 'Specified IP_Master not found',
                    'code' => 'IP_NOT_FOUND'
                ];
            }
            
            // Check if the specific IP is available
            if (!$this->lockService->isIPAvailable($specificIPMasterId)) {
                return [
                    'success' => false,
                    'message' => 'Specified IP_Master is not available (status: ' . $ipMaster['status'] . ')',
                    'code' => 'IP_NOT_AVAILABLE',
                    'data' => [
                        'ip_master_id' => $specificIPMasterId,
                        'status' => $ipMaster['status']
                    ]
                ];
            }
        } else {
            // Get the next available IP automatically (Requirement 3.1)
            $ipMaster = $this->lockService->getNextAvailableIP();
            if (!$ipMaster) {
                return [
                    'success' => false,
                    'message' => 'No IP addresses available for configuration',
                    'code' => 'NO_IP_AVAILABLE'
                ];
            }
        }
        
        // Acquire lock on the IP_Master
        $lockResult = $this->lockService->acquireLock(
            (int)$ipMaster['id'],
            $routerSerialNumber,
            $userId
        );
        
        if (!$lockResult['success']) {
            return $lockResult;
        }
        
        // Return success with lock and IP details
        return [
            'success' => true,
            'message' => 'Configuration session started successfully',
            'data' => [
                'lock_id' => $lockResult['data']['id'],
                'session_id' => $lockResult['data']['id'], // Alias for clarity
                'expires_at' => $lockResult['data']['expires_at'],
                'remaining_seconds' => IPLock::getRemainingSeconds($lockResult['data']),
                'router_serial_number' => $routerSerialNumber,
                'ip_master' => [
                    'id' => (int)$ipMaster['id'],
                    'network_ip' => $ipMaster['network_ip'],
                    'router_ip' => $ipMaster['router_ip'],
                    'site_ip' => $ipMaster['site_ip'],
                    'subnet_mask' => $ipMaster['subnet_mask']
                ]
            ]
        ];
    }

    
    /**
     * Complete a configuration session
     * Creates a permanent binding between the router and IP_Master, releases the lock
     * 
     * @param int $lockId Lock ID (session ID) to complete
     * @param int $userId User ID completing the configuration
     * @param string|null $notes Optional notes about the configuration
     * @return array Result with success status and binding data
     * 
     * Requirements: 4.4, 5.1, 5.2
     */
    public function completeConfiguration(int $lockId, int $userId, ?string $notes = null): array {
        // Get the lock
        $lock = $this->lockRepository->findById($lockId);
        if (!$lock) {
            return [
                'success' => false,
                'message' => 'Configuration session not found',
                'code' => 'SESSION_NOT_FOUND'
            ];
        }
        
        // Check if lock is still active
        if (!IPLock::isActive($lock)) {
            $status = $lock['status'];
            if (IPLock::isExpired($lock)) {
                $status = 'expired';
            }
            return [
                'success' => false,
                'message' => 'Configuration session is no longer active (status: ' . $status . ')',
                'code' => 'SESSION_INACTIVE',
                'data' => [
                    'lock_status' => $lock['status'],
                    'expired' => IPLock::isExpired($lock)
                ]
            ];
        }
        
        // Validate ownership - only the user who started can complete
        if ((int)$lock['locked_by'] !== $userId) {
            return [
                'success' => false,
                'message' => 'Only the user who started the configuration can complete it',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        $conn = $this->db->getConnection();
        
        try {
            $conn->begin_transaction();
            
            // Create permanent binding (Requirement 5.1)
            $bindingData = [
                'router_serial_number' => $lock['router_serial_number'],
                'ip_master_id' => $lock['ip_master_id'],
                'configured_by' => $userId,
                'configured_at' => date('Y-m-d H:i:s'),
                'notes' => $notes,
                'status' => RouterIPBinding::STATUS_ACTIVE
            ];
            
            $binding = $this->bindingModel->create($bindingData);
            
            if (!$binding) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Failed to create binding',
                    'code' => 'BINDING_ERROR'
                ];
            }
            
            // Update IP_Master status to configured
            $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $configuredStatus = IPMaster::STATUS_CONFIGURED;
            $stmt->bind_param('si', $configuredStatus, $lock['ip_master_id']);
            $stmt->execute();
            $stmt->close();
            
            // Release the lock (mark as released, don't reset IP status since it's now configured)
            $sql = "UPDATE `ip_locks` SET `status` = ?, `released_at` = NOW() WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $releasedStatus = IPLock::STATUS_RELEASED;
            $stmt->bind_param('si', $releasedStatus, $lockId);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            // Log the configuration completion (Requirement 5.2)
            $this->auditLog->logConfigured(
                $userId,
                $lock['router_serial_number'],
                $lock['ip_master_id'],
                [
                    'binding_id' => $binding['id'],
                    'notes' => $notes,
                    'lock_id' => $lockId
                ]
            );
            
            // Get IP_Master details for response
            $ipMaster = $this->ipMasterRepository->findById($lock['ip_master_id']);
            
            return [
                'success' => true,
                'message' => 'Configuration completed successfully',
                'data' => [
                    'binding_id' => $binding['id'],
                    'router_serial_number' => $lock['router_serial_number'],
                    'ip_master' => [
                        'id' => (int)$ipMaster['id'],
                        'network_ip' => $ipMaster['network_ip'],
                        'router_ip' => $ipMaster['router_ip'],
                        'site_ip' => $ipMaster['site_ip'],
                        'subnet_mask' => $ipMaster['subnet_mask'],
                        'status' => $ipMaster['status']
                    ],
                    'configured_by' => $userId,
                    'configured_at' => $binding['configured_at'],
                    'notes' => $notes
                ]
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Configuration completion failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to complete configuration: ' . $e->getMessage(),
                'code' => 'COMPLETION_ERROR'
            ];
        }
    }
    
    /**
     * Cancel a configuration session
     * Releases the lock immediately and restores IP status to available
     * 
     * @param int $lockId Lock ID (session ID) to cancel
     * @param int $userId User ID cancelling the configuration
     * @return array Result with success status
     * 
     * Requirements: 4.5
     */
    public function cancelConfiguration(int $lockId, int $userId): array {
        // Get the lock
        $lock = $this->lockRepository->findById($lockId);
        if (!$lock) {
            return [
                'success' => false,
                'message' => 'Configuration session not found',
                'code' => 'SESSION_NOT_FOUND'
            ];
        }
        
        // Check if lock is still active
        if ($lock['status'] !== IPLock::STATUS_ACTIVE) {
            return [
                'success' => false,
                'message' => 'Configuration session is not active (status: ' . $lock['status'] . ')',
                'code' => 'SESSION_INACTIVE',
                'data' => [
                    'lock_status' => $lock['status']
                ]
            ];
        }
        
        // Validate ownership - only the user who started can cancel
        // (or allow admins to cancel any session - could be added later)
        if ((int)$lock['locked_by'] !== $userId) {
            return [
                'success' => false,
                'message' => 'Only the user who started the configuration can cancel it',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        // Release the lock (this will also restore IP status to available)
        $releaseResult = $this->lockService->releaseLock($lockId, $userId);
        
        if (!$releaseResult['success']) {
            return $releaseResult;
        }
        
        // Log the cancellation
        $this->auditLog->logLockReleased(
            $userId,
            $lock['router_serial_number'],
            $lock['ip_master_id'],
            [
                'action' => 'cancelled',
                'lock_id' => $lockId
            ]
        );
        
        return [
            'success' => true,
            'message' => 'Configuration session cancelled successfully',
            'data' => [
                'lock_id' => $lockId,
                'router_serial_number' => $lock['router_serial_number'],
                'ip_master_id' => $lock['ip_master_id']
            ]
        ];
    }
    
    /**
     * Force cancel a configuration session (admin only)
     * Releases the lock immediately regardless of who started it
     * 
     * @param int $lockId Lock ID (session ID) to cancel
     * @param int $adminUserId Admin user ID performing the force cancel
     * @return array Result with success status
     */
    public function forceCancelConfiguration(int $lockId, int $adminUserId): array {
        // Get the lock
        $lock = $this->lockRepository->findById($lockId);
        if (!$lock) {
            return [
                'success' => false,
                'message' => 'Configuration session not found',
                'code' => 'SESSION_NOT_FOUND'
            ];
        }
        
        // Check if lock is still active
        if ($lock['status'] !== IPLock::STATUS_ACTIVE) {
            return [
                'success' => false,
                'message' => 'Configuration session is not active (status: ' . $lock['status'] . ')',
                'code' => 'SESSION_INACTIVE',
                'data' => [
                    'lock_status' => $lock['status']
                ]
            ];
        }
        
        // Release the lock (this will also restore IP status to available)
        $releaseResult = $this->lockService->releaseLock($lockId, $adminUserId);
        
        if (!$releaseResult['success']) {
            return $releaseResult;
        }
        
        // Log the force cancellation
        $this->auditLog->logLockReleased(
            $adminUserId,
            $lock['router_serial_number'],
            $lock['ip_master_id'],
            [
                'action' => 'force_cancelled',
                'lock_id' => $lockId,
                'original_user_id' => $lock['locked_by']
            ]
        );
        
        return [
            'success' => true,
            'message' => 'Configuration session force cancelled successfully',
            'data' => [
                'lock_id' => $lockId,
                'router_serial_number' => $lock['router_serial_number'],
                'ip_master_id' => $lock['ip_master_id']
            ]
        ];
    }

    
    /**
     * Get available routers for configuration
     * Returns routers that are not in an active session and not already configured
     * 
     * @return array Array of available routers
     * 
     * Requirements: 2.1, 2.2
     */
    public function getAvailableRouters(): array {
        // Get routers that are in active configuration sessions
        $activeLocks = $this->lockRepository->getActiveLocks();
        $lockedRouterSerials = array_column($activeLocks, 'router_serial_number');
        
        // Get routers that are already configured
        $activeBindings = $this->bindingModel->getActiveBindingsWithDetails();
        $configuredRouterSerials = array_column($activeBindings, 'router_serial_number');
        
        // Combine excluded serials
        $excludedSerials = array_unique(array_merge($lockedRouterSerials, $configuredRouterSerials));
        
        // Query inventory for available routers
        // This assumes routers are stored in the inventory/assets table
        // Adjust the query based on actual inventory structure
        $sql = "SELECT DISTINCT serial_number, model, status 
                FROM assets 
                WHERE serial_number IS NOT NULL 
                AND serial_number != ''
                AND status = 'available'";
        
        $params = [];
        $types = '';
        
        if (!empty($excludedSerials)) {
            $placeholders = implode(',', array_fill(0, count($excludedSerials), '?'));
            $sql .= " AND serial_number NOT IN ($placeholders)";
            $params = $excludedSerials;
            $types = str_repeat('s', count($excludedSerials));
        }
        
        $sql .= " ORDER BY serial_number ASC";
        
        try {
            $results = $this->db->getResults($sql, $params, $types);
            return $results ?: [];
        } catch (Exception $e) {
            // If assets table doesn't exist or has different structure,
            // return empty array and log the error
            error_log("Failed to get available routers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get router configuration details
     * Returns the IP configuration for a specific router if configured
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array|null Router configuration or null if not configured
     * 
     * Requirements: 2.4, 5.3
     */
    public function getRouterConfiguration(string $routerSerialNumber): ?array {
        // Check for active binding
        $binding = $this->bindingModel->findByRouter($routerSerialNumber);
        
        if ($binding) {
            return [
                'router_serial_number' => $routerSerialNumber,
                'status' => 'configured',
                'binding' => [
                    'id' => $binding['id'],
                    'configured_by' => $binding['configured_by'],
                    'configured_by_username' => $binding['configured_by_username'] ?? null,
                    'configured_at' => $binding['configured_at'],
                    'notes' => $binding['notes']
                ],
                'ip_master' => [
                    'id' => $binding['ip_master_id'],
                    'network_ip' => $binding['network_ip'],
                    'router_ip' => $binding['router_ip'],
                    'site_ip' => $binding['site_ip'],
                    'subnet_mask' => $binding['subnet_mask']
                ]
            ];
        }
        
        // Check for active lock (in-progress configuration)
        $lock = $this->lockService->getActiveLockByRouter($routerSerialNumber);
        
        if ($lock) {
            $ipMaster = $this->ipMasterRepository->findById($lock['ip_master_id']);
            return [
                'router_serial_number' => $routerSerialNumber,
                'status' => 'in_progress',
                'lock' => [
                    'id' => $lock['id'],
                    'locked_by' => $lock['locked_by'],
                    'locked_at' => $lock['locked_at'],
                    'expires_at' => $lock['expires_at'],
                    'remaining_seconds' => IPLock::getRemainingSeconds($lock)
                ],
                'ip_master' => $ipMaster ? [
                    'id' => (int)$ipMaster['id'],
                    'network_ip' => $ipMaster['network_ip'],
                    'router_ip' => $ipMaster['router_ip'],
                    'site_ip' => $ipMaster['site_ip'],
                    'subnet_mask' => $ipMaster['subnet_mask']
                ] : null
            ];
        }
        
        // Router is not configured
        return [
            'router_serial_number' => $routerSerialNumber,
            'status' => 'unconfigured',
            'binding' => null,
            'ip_master' => null
        ];
    }
    
    /**
     * Get active configuration session for a user
     * 
     * @param int $userId User ID
     * @return array|null Active session or null
     */
    public function getActiveSessionForUser(int $userId): ?array {
        $sql = "SELECT l.*, 
                       im.network_ip, im.router_ip, im.site_ip, im.subnet_mask
                FROM `ip_locks` l
                LEFT JOIN `ip_master` im ON l.ip_master_id = im.id
                WHERE l.`locked_by` = ? 
                AND l.`status` = ? 
                AND l.`expires_at` > NOW()
                ORDER BY l.`locked_at` DESC
                LIMIT 1";
        
        $result = $this->db->getResults($sql, [$userId, IPLock::STATUS_ACTIVE], 'is');
        
        if (empty($result)) {
            return null;
        }
        
        $lock = $result[0];
        return [
            'lock_id' => $lock['id'],
            'session_id' => $lock['id'],
            'router_serial_number' => $lock['router_serial_number'],
            'expires_at' => $lock['expires_at'],
            'remaining_seconds' => IPLock::getRemainingSeconds($lock),
            'ip_master' => [
                'id' => (int)$lock['ip_master_id'],
                'network_ip' => $lock['network_ip'],
                'router_ip' => $lock['router_ip'],
                'site_ip' => $lock['site_ip'],
                'subnet_mask' => $lock['subnet_mask']
            ]
        ];
    }
    
    /**
     * Get session details by lock ID
     * 
     * @param int $lockId Lock ID
     * @return array|null Session details or null
     */
    public function getSessionDetails(int $lockId): ?array {
        $lock = $this->lockRepository->findById($lockId);
        
        if (!$lock) {
            return null;
        }
        
        $ipMaster = $this->ipMasterRepository->findById($lock['ip_master_id']);
        
        return [
            'lock_id' => $lock['id'],
            'session_id' => $lock['id'],
            'router_serial_number' => $lock['router_serial_number'],
            'locked_by' => $lock['locked_by'],
            'locked_at' => $lock['locked_at'],
            'expires_at' => $lock['expires_at'],
            'status' => $lock['status'],
            'is_active' => IPLock::isActive($lock),
            'is_expired' => IPLock::isExpired($lock),
            'remaining_seconds' => IPLock::getRemainingSeconds($lock),
            'ip_master' => $ipMaster ? [
                'id' => (int)$ipMaster['id'],
                'network_ip' => $ipMaster['network_ip'],
                'router_ip' => $ipMaster['router_ip'],
                'site_ip' => $ipMaster['site_ip'],
                'subnet_mask' => $ipMaster['subnet_mask'],
                'status' => $ipMaster['status']
            ] : null
        ];
    }
    
    /**
     * Skip to next available IP during configuration
     * Releases current lock and acquires a new one on a different IP
     * 
     * @param int $lockId Current lock ID
     * @param int $userId User ID
     * @return array Result with new lock data
     * 
     * Requirements: 3.4
     */
    public function skipToNextIP(int $lockId, int $userId): array {
        // Get current lock
        $currentLock = $this->lockRepository->findById($lockId);
        if (!$currentLock) {
            return [
                'success' => false,
                'message' => 'Configuration session not found',
                'code' => 'SESSION_NOT_FOUND'
            ];
        }
        
        // Validate ownership
        if ((int)$currentLock['locked_by'] !== $userId) {
            return [
                'success' => false,
                'message' => 'Only the user who started the configuration can skip IPs',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        // Check if lock is still active
        if (!IPLock::isActive($currentLock)) {
            return [
                'success' => false,
                'message' => 'Configuration session is no longer active',
                'code' => 'SESSION_INACTIVE'
            ];
        }
        
        $routerSerialNumber = $currentLock['router_serial_number'];
        $currentIPMasterId = $currentLock['ip_master_id'];
        
        // Release current lock
        $releaseResult = $this->lockService->releaseLock($lockId, $userId);
        if (!$releaseResult['success']) {
            return $releaseResult;
        }
        
        // Get next available IP (excluding the one we just released)
        $availableIPs = $this->lockService->getAvailableIPs();
        $nextIP = null;
        
        foreach ($availableIPs as $ip) {
            if ((int)$ip['id'] !== $currentIPMasterId) {
                $nextIP = $ip;
                break;
            }
        }
        
        if (!$nextIP) {
            return [
                'success' => false,
                'message' => 'No other IP addresses available',
                'code' => 'NO_IP_AVAILABLE'
            ];
        }
        
        // Acquire lock on new IP
        $lockResult = $this->lockService->acquireLock(
            (int)$nextIP['id'],
            $routerSerialNumber,
            $userId
        );
        
        if (!$lockResult['success']) {
            return $lockResult;
        }
        
        return [
            'success' => true,
            'message' => 'Skipped to next available IP',
            'data' => [
                'lock_id' => $lockResult['data']['id'],
                'session_id' => $lockResult['data']['id'],
                'expires_at' => $lockResult['data']['expires_at'],
                'remaining_seconds' => IPLock::getRemainingSeconds($lockResult['data']),
                'router_serial_number' => $routerSerialNumber,
                'ip_master' => [
                    'id' => (int)$nextIP['id'],
                    'network_ip' => $nextIP['network_ip'],
                    'router_ip' => $nextIP['router_ip'],
                    'site_ip' => $nextIP['site_ip'],
                    'subnet_mask' => $nextIP['subnet_mask']
                ],
                'previous_ip_master_id' => $currentIPMasterId
            ]
        ];
    }
}
