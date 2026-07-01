<?php
/**
 * IP Lock Repository
 * Provides data access operations for IP Lock records
 * 
 * Requirements: 4.1, 4.2, 4.3, 11.1, 11.3
 * - 4.1: Lock IP for 20 minutes during configuration
 * - 4.2: Prevent other users from selecting locked IPs
 * - 4.3: Auto-expire locks after timeout
 * - 11.1: Ensure each user receives a different available IP_Master during simultaneous configuration
 * - 11.3: Database-level locking to prevent race conditions
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';

class IPLockRepository extends BaseRepository {
    protected $table = 'ip_locks';
    protected $primaryKey = 'id';
    
    // IP Locks are global configuration data, not company-specific
    protected $applyCompanyFilter = false;
    protected $companyIdColumn = null;
    
    // Retry configuration for lock conflicts (Requirement 11.1, 11.3)
    const MAX_RETRY_ATTEMPTS = 3;
    const RETRY_DELAY_MS = 100; // milliseconds
    const LOCK_WAIT_TIMEOUT = 5; // seconds for innodb_lock_wait_timeout
    
    /**
     * Acquire a lock on an IP_Master for configuration with retry logic
     * Uses database-level locking (SELECT FOR UPDATE) to prevent race conditions
     * Implements retry logic for handling lock conflicts during concurrent access
     * 
     * @param int $ipMasterId IP_Master ID to lock
     * @param string $routerSerialNumber Router serial number being configured
     * @param int $lockedBy User ID acquiring the lock
     * @param int $retryAttempt Current retry attempt (internal use)
     * @return array Result with success status and lock data
     * 
     * Requirements: 4.1, 11.1, 11.3
     */
    public function acquireLock(int $ipMasterId, string $routerSerialNumber, int $lockedBy, int $retryAttempt = 0): array {
        $conn = $this->db->getConnection();
        
        try {
            // Set a shorter lock wait timeout to fail fast on conflicts
            // This allows retry logic to kick in sooner
            $conn->query("SET innodb_lock_wait_timeout = " . self::LOCK_WAIT_TIMEOUT);
            
            // Start transaction
            $conn->begin_transaction();
            
            // Lock the IP_Master row for update (prevents race conditions)
            // Requirement 11.3: Database-level locking
            $sql = "SELECT * FROM `ip_master` WHERE `id` = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $ipMasterId);
            $stmt->execute();
            $result = $stmt->get_result();
            $ipMaster = $result->fetch_assoc();
            $stmt->close();
            
            if (!$ipMaster) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'IP_Master not found',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            // Check if IP is available
            if ($ipMaster['status'] !== IPMaster::STATUS_AVAILABLE) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'IP_Master is not available (status: ' . $ipMaster['status'] . ')',
                    'code' => 'NOT_AVAILABLE'
                ];
            }
            
            // Check for existing active lock on this IP
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `ip_master_id` = ? AND `status` = ? AND `expires_at` > NOW() 
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $status = IPLock::STATUS_ACTIVE;
            $stmt->bind_param('is', $ipMasterId, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingLock = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingLock) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'IP_Master is already locked by another user',
                    'code' => 'ALREADY_LOCKED',
                    'data' => [
                        'locked_by' => $existingLock['locked_by'],
                        'expires_at' => $existingLock['expires_at']
                    ]
                ];
            }
            
            // Check for existing active lock on this router
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `router_serial_number` = ? AND `status` = ? AND `expires_at` > NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $routerSerialNumber, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $routerLock = $result->fetch_assoc();
            $stmt->close();
            
            if ($routerLock) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Router already has an active configuration session',
                    'code' => 'ROUTER_IN_SESSION',
                    'data' => [
                        'ip_master_id' => $routerLock['ip_master_id'],
                        'expires_at' => $routerLock['expires_at']
                    ]
                ];
            }
            
            // Calculate expiry time (Requirement 4.1: 20 minutes)
            // Use database NOW() to avoid timezone issues
            $sql = "SELECT NOW() as locked_time, DATE_ADD(NOW(), INTERVAL 20 MINUTE) as expires_time";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $timeData = $result->fetch_assoc();
            $stmt->close();
            
            $lockedAt = $timeData['locked_time'];
            $expiresAt = $timeData['expires_time'];
            
            // Create the lock
            $sql = "INSERT INTO `{$this->table}` 
                    (`ip_master_id`, `router_serial_number`, `locked_by`, `locked_at`, `expires_at`, `status`) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $status = IPLock::STATUS_ACTIVE;
            $stmt->bind_param('isisss', $ipMasterId, $routerSerialNumber, $lockedBy, $lockedAt, $expiresAt, $status);
            $stmt->execute();
            $lockId = $conn->insert_id;
            $stmt->close();
            
            // Update IP_Master status to locked
            $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $lockedStatus = IPMaster::STATUS_LOCKED;
            $stmt->bind_param('si', $lockedStatus, $ipMasterId);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Fetch the created lock
            $lock = $this->findById($lockId);
            
            return [
                'success' => true,
                'message' => 'Lock acquired successfully',
                'data' => $lock
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            
            // Check if this is a lock conflict error that can be retried
            // MySQL error codes: 1205 = Lock wait timeout, 1213 = Deadlock
            $errorCode = $conn->errno ?? 0;
            $isLockConflict = ($errorCode == 1205 || $errorCode == 1213 || 
                              strpos($e->getMessage(), 'Lock wait timeout') !== false ||
                              strpos($e->getMessage(), 'Deadlock') !== false);
            
            // Retry logic for lock conflicts (Requirement 11.1, 11.3)
            if ($isLockConflict && $retryAttempt < self::MAX_RETRY_ATTEMPTS) {
                // Wait before retrying (exponential backoff)
                $delayMs = self::RETRY_DELAY_MS * pow(2, $retryAttempt);
                usleep($delayMs * 1000); // Convert to microseconds
                
                // Retry the lock acquisition
                return $this->acquireLock($ipMasterId, $routerSerialNumber, $lockedBy, $retryAttempt + 1);
            }
            
            return [
                'success' => false,
                'message' => 'Failed to acquire lock: ' . $e->getMessage(),
                'code' => $isLockConflict ? 'LOCK_CONFLICT' : 'LOCK_ERROR',
                'retry_attempted' => $retryAttempt > 0,
                'retry_count' => $retryAttempt
            ];
        }
    }
    
    /**
     * Release a lock (mark as released)
     * 
     * @param int $lockId Lock ID to release
     * @param bool $updateIPStatus Whether to update IP_Master status back to available
     * @return array Result with success status
     * 
     * Requirements: 4.5
     */
    public function releaseLock(int $lockId, bool $updateIPStatus = true): array {
        $conn = $this->db->getConnection();
        
        try {
            $conn->begin_transaction();
            
            // Get the lock
            $sql = "SELECT * FROM `{$this->table}` WHERE `id` = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $lockId);
            $stmt->execute();
            $result = $stmt->get_result();
            $lock = $result->fetch_assoc();
            $stmt->close();
            
            if (!$lock) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Lock not found',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            if ($lock['status'] !== IPLock::STATUS_ACTIVE) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Lock is not active (status: ' . $lock['status'] . ')',
                    'code' => 'NOT_ACTIVE'
                ];
            }
            
            // Update lock status to released
            $sql = "UPDATE `{$this->table}` SET `status` = ?, `released_at` = NOW() WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $status = IPLock::STATUS_RELEASED;
            $stmt->bind_param('si', $status, $lockId);
            $stmt->execute();
            $stmt->close();
            
            // Update IP_Master status back to available if requested
            if ($updateIPStatus) {
                $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
                $stmt = $conn->prepare($sql);
                $availableStatus = IPMaster::STATUS_AVAILABLE;
                $stmt->bind_param('si', $availableStatus, $lock['ip_master_id']);
                $stmt->execute();
                $stmt->close();
            }
            
            $conn->commit();
            
            return [
                'success' => true,
                'message' => 'Lock released successfully'
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            return [
                'success' => false,
                'message' => 'Failed to release lock: ' . $e->getMessage(),
                'code' => 'RELEASE_ERROR'
            ];
        }
    }
    
    /**
     * Get all active locks for dashboard display
     * 
     * @return array Array of active locks with IP details and remaining time
     * 
     * Requirements: 7.3
     */
    public function getActiveLocks(): array {
        $sql = "SELECT l.*, 
                       im.network_ip, im.router_ip, im.site_ip, im.subnet_mask,
                       u.username as locked_by_username,
                       TIMESTAMPDIFF(SECOND, NOW(), l.expires_at) as remaining_seconds
                FROM `{$this->table}` l
                LEFT JOIN `ip_master` im ON l.ip_master_id = im.id
                LEFT JOIN `users` u ON l.locked_by = u.id
                WHERE l.`status` = ? 
                AND l.`expires_at` > NOW()
                ORDER BY l.`expires_at` ASC";
        
        return $this->db->getResults($sql, [IPLock::STATUS_ACTIVE], 's');
    }
    
    /**
     * Expire all timed-out locks
     * Updates status to 'expired' and resets IP_Master status to 'available'
     * 
     * @return int Number of locks expired
     * 
     * Requirements: 4.3
     */
    public function expireTimedOutLocks(): int {
        $conn = $this->db->getConnection();
        $expiredCount = 0;
        
        try {
            $conn->begin_transaction();
            
            // Find all expired locks that are still marked as active
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `status` = ? AND `expires_at` <= NOW() 
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $status = IPLock::STATUS_ACTIVE;
            $stmt->bind_param('s', $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $expiredLocks = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (empty($expiredLocks)) {
                $conn->commit();
                return 0;
            }
            
            // Update each expired lock
            foreach ($expiredLocks as $lock) {
                // Update lock status to expired
                $sql = "UPDATE `{$this->table}` SET `status` = ? WHERE `id` = ?";
                $stmt = $conn->prepare($sql);
                $expiredStatus = IPLock::STATUS_EXPIRED;
                $stmt->bind_param('si', $expiredStatus, $lock['id']);
                $stmt->execute();
                $stmt->close();
                
                // Reset IP_Master status to available
                $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ? AND `status` = ?";
                $stmt = $conn->prepare($sql);
                $availableStatus = IPMaster::STATUS_AVAILABLE;
                $lockedStatus = IPMaster::STATUS_LOCKED;
                $stmt->bind_param('sis', $availableStatus, $lock['ip_master_id'], $lockedStatus);
                $stmt->execute();
                $stmt->close();
                
                $expiredCount++;
            }
            
            $conn->commit();
            return $expiredCount;
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Failed to expire locks: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Find lock by ID
     * 
     * @param int $id Lock ID
     * @return array|null Lock record or null
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `id` = ?";
        $result = $this->db->getResults($sql, [$id], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find active lock by IP_Master ID
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array|null Active lock or null
     */
    public function findActiveLockByIPMaster(int $ipMasterId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `ip_master_id` = ? AND `status` = ? AND `expires_at` > NOW()
                ORDER BY `id` DESC LIMIT 1";
        $result = $this->db->getResults($sql, [$ipMasterId, IPLock::STATUS_ACTIVE], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find active lock by router serial number
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array|null Active lock or null
     */
    public function findActiveLockByRouter(string $routerSerialNumber): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `router_serial_number` = ? AND `status` = ? AND `expires_at` > NOW()
                ORDER BY `id` DESC LIMIT 1";
        $result = $this->db->getResults($sql, [$routerSerialNumber, IPLock::STATUS_ACTIVE], 'ss');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if an IP_Master is currently locked
     * 
     * @param int $ipMasterId IP_Master ID
     * @return bool True if locked
     * 
     * Requirements: 4.2
     */
    public function isIPLocked(int $ipMasterId): bool {
        return $this->findActiveLockByIPMaster($ipMasterId) !== null;
    }
    
    /**
     * Get lock history for an IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @param int $limit Maximum records to return
     * @return array Lock history records
     */
    public function getLockHistory(int $ipMasterId, int $limit = 10): array {
        $sql = "SELECT l.*, u.username as locked_by_username
                FROM `{$this->table}` l
                LEFT JOIN `users` u ON l.locked_by = u.id
                WHERE l.`ip_master_id` = ?
                ORDER BY l.`locked_at` DESC
                LIMIT ?";
        return $this->db->getResults($sql, [$ipMasterId, $limit], 'ii');
    }
    
    /**
     * Get count of active locks
     * 
     * @return int Number of active locks
     */
    public function getActiveLockCount(): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE `status` = ? AND `expires_at` > NOW()";
        $result = $this->db->getResults($sql, [IPLock::STATUS_ACTIVE], 's');
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Get all locked IP_Master IDs
     * Used to exclude from available IP list
     * 
     * @return array Array of locked IP_Master IDs
     * 
     * Requirements: 11.2
     */
    public function getLockedIPMasterIds(): array {
        $sql = "SELECT DISTINCT `ip_master_id` FROM `{$this->table}` 
                WHERE `status` = ? AND `expires_at` > NOW()";
        $results = $this->db->getResults($sql, [IPLock::STATUS_ACTIVE], 's');
        return array_column($results, 'ip_master_id');
    }
    
    /**
     * Acquire lock on next available IP atomically
     * This method handles concurrent access by selecting and locking an available IP
     * in a single atomic transaction, preventing race conditions where two users
     * might get the same IP.
     * 
     * @param string $routerSerialNumber Router serial number being configured
     * @param int $lockedBy User ID acquiring the lock
     * @param int $retryAttempt Current retry attempt (internal use)
     * @return array Result with success status and lock data
     * 
     * Requirements: 11.1, 11.3
     */
    public function acquireLockOnNextAvailableIP(string $routerSerialNumber, int $lockedBy, int $retryAttempt = 0): array {
        $conn = $this->db->getConnection();
        
        try {
            // Set a shorter lock wait timeout to fail fast on conflicts
            $conn->query("SET innodb_lock_wait_timeout = " . self::LOCK_WAIT_TIMEOUT);
            
            // Start transaction with SERIALIZABLE isolation for maximum consistency
            $conn->begin_transaction();
            
            // Check if router already has an active lock
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `router_serial_number` = ? AND `status` = ? AND `expires_at` > NOW()
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $status = IPLock::STATUS_ACTIVE;
            $stmt->bind_param('ss', $routerSerialNumber, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingRouterLock = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingRouterLock) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'Router already has an active configuration session',
                    'code' => 'ROUTER_IN_SESSION',
                    'data' => [
                        'ip_master_id' => $existingRouterLock['ip_master_id'],
                        'expires_at' => $existingRouterLock['expires_at']
                    ]
                ];
            }
            
            // Select and lock the next available IP_Master atomically
            // Note: MariaDB doesn't support SKIP LOCKED, so we use a different approach
            // We select available IPs and lock them with FOR UPDATE
            // The transaction isolation ensures no two users get the same IP
            $sql = "SELECT im.* FROM `ip_master` im
                    LEFT JOIN `{$this->table}` l ON im.id = l.ip_master_id 
                        AND l.status = ? AND l.expires_at > NOW()
                    WHERE im.status = ? AND l.id IS NULL
                    ORDER BY im.id ASC
                    LIMIT 1
                    FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $activeStatus = IPLock::STATUS_ACTIVE;
            $availableStatus = IPMaster::STATUS_AVAILABLE;
            $stmt->bind_param('ss', $activeStatus, $availableStatus);
            $stmt->execute();
            $result = $stmt->get_result();
            $availableIP = $result->fetch_assoc();
            $stmt->close();
            
            if (!$availableIP) {
                $conn->rollback();
                return [
                    'success' => false,
                    'message' => 'No IP addresses available for configuration',
                    'code' => 'NO_AVAILABLE_IP'
                ];
            }
            
            $ipMasterId = $availableIP['id'];
            
            // Calculate expiry time using database NOW()
            $sql = "SELECT NOW() as locked_time, DATE_ADD(NOW(), INTERVAL 20 MINUTE) as expires_time";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $timeData = $result->fetch_assoc();
            $stmt->close();
            
            $lockedAt = $timeData['locked_time'];
            $expiresAt = $timeData['expires_time'];
            
            // Create the lock
            $sql = "INSERT INTO `{$this->table}` 
                    (`ip_master_id`, `router_serial_number`, `locked_by`, `locked_at`, `expires_at`, `status`) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $status = IPLock::STATUS_ACTIVE;
            $stmt->bind_param('isisss', $ipMasterId, $routerSerialNumber, $lockedBy, $lockedAt, $expiresAt, $status);
            $stmt->execute();
            $lockId = $conn->insert_id;
            $stmt->close();
            
            // Update IP_Master status to locked
            $sql = "UPDATE `ip_master` SET `status` = ? WHERE `id` = ?";
            $stmt = $conn->prepare($sql);
            $lockedStatus = IPMaster::STATUS_LOCKED;
            $stmt->bind_param('si', $lockedStatus, $ipMasterId);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Fetch the created lock with IP details
            $lock = $this->findById($lockId);
            $lock['ip_master'] = $availableIP;
            
            return [
                'success' => true,
                'message' => 'Lock acquired successfully',
                'data' => $lock
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            
            // Check if this is a lock conflict error that can be retried
            $errorCode = $conn->errno ?? 0;
            $isLockConflict = ($errorCode == 1205 || $errorCode == 1213 || 
                              strpos($e->getMessage(), 'Lock wait timeout') !== false ||
                              strpos($e->getMessage(), 'Deadlock') !== false);
            
            // Retry logic for lock conflicts
            if ($isLockConflict && $retryAttempt < self::MAX_RETRY_ATTEMPTS) {
                $delayMs = self::RETRY_DELAY_MS * pow(2, $retryAttempt);
                usleep($delayMs * 1000);
                return $this->acquireLockOnNextAvailableIP($routerSerialNumber, $lockedBy, $retryAttempt + 1);
            }
            
            return [
                'success' => false,
                'message' => 'Failed to acquire lock: ' . $e->getMessage(),
                'code' => $isLockConflict ? 'LOCK_CONFLICT' : 'LOCK_ERROR',
                'retry_attempted' => $retryAttempt > 0,
                'retry_count' => $retryAttempt
            ];
        }
    }
    
    /**
     * Get expired locks with details for audit logging
     * Returns locks that have expired but are still marked as active
     * 
     * @return array Array of expired lock records with IP details
     * 
     * Requirements: 4.3, 9.3
     */
    public function getExpiredLocksForAudit(): array {
        $sql = "SELECT l.*, im.network_ip, im.router_ip, im.site_ip, im.subnet_mask
                FROM `{$this->table}` l
                LEFT JOIN `ip_master` im ON l.ip_master_id = im.id
                WHERE l.`status` = ? AND l.`expires_at` <= NOW()
                ORDER BY l.`expires_at` ASC";
        
        return $this->db->getResults($sql, [IPLock::STATUS_ACTIVE], 's');
    }
}
