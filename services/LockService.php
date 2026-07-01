<?php
/**
 * Lock Service
 * Handles business logic for IP lock management during configuration
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.5
 * - 4.1: Lock IP for 20 minutes during configuration
 * - 4.2: Prevent other users from selecting locked IPs
 * - 4.3: Auto-expire locks after timeout
 * - 4.5: Release lock on cancel
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/IPLockRepository.php';
require_once __DIR__ . '/../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../models/IPLock.php';
require_once __DIR__ . '/../models/IPMaster.php';

class LockService {
    private $db;
    private $lockRepository;
    private $ipMasterRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->lockRepository = new IPLockRepository();
        $this->ipMasterRepository = new IPMasterRepository();
    }
    
    /**
     * Acquire a lock on an IP_Master for configuration
     * 
     * @param int $ipMasterId IP_Master ID to lock
     * @param string $routerSerialNumber Router serial number being configured
     * @param int $userId User ID acquiring the lock
     * @return array Result with success status and lock data
     * 
     * Requirements: 4.1, 4.2
     */
    public function acquireLock(int $ipMasterId, string $routerSerialNumber, int $userId): array {
        // First, expire any timed-out locks to ensure accurate availability
        $this->expireTimedOutLocks();
        
        // Check if IP_Master exists
        $ipMaster = $this->ipMasterRepository->findById($ipMasterId);
        if (!$ipMaster) {
            return [
                'success' => false,
                'message' => 'IP_Master not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if IP is available (Requirement 4.2)
        if (!$this->isIPAvailable($ipMasterId)) {
            $existingLock = $this->lockRepository->findActiveLockByIPMaster($ipMasterId);
            return [
                'success' => false,
                'message' => 'IP_Master is not available for locking',
                'code' => 'NOT_AVAILABLE',
                'data' => [
                    'current_status' => $ipMaster['status'],
                    'existing_lock' => $existingLock
                ]
            ];
        }
        
        // Attempt to acquire the lock (Requirement 4.1)
        $result = $this->lockRepository->acquireLock($ipMasterId, $routerSerialNumber, $userId);
        
        if ($result['success']) {
            // Log the lock acquisition
            $this->logAction($userId, $ipMasterId, 'lock_acquired', [
                'router_serial_number' => $routerSerialNumber,
                'lock_id' => $result['data']['id'],
                'expires_at' => $result['data']['expires_at']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Release a lock (mark as released and restore IP status)
     * 
     * @param int $lockId Lock ID to release
     * @param int|null $userId User ID releasing the lock (for audit)
     * @return array Result with success status
     * 
     * Requirements: 4.5
     */
    public function releaseLock(int $lockId, ?int $userId = null): array {
        // Get the lock first for audit logging
        $lock = $this->lockRepository->findById($lockId);
        if (!$lock) {
            return [
                'success' => false,
                'message' => 'Lock not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Release the lock
        $result = $this->lockRepository->releaseLock($lockId, true);
        
        if ($result['success']) {
            // Log the lock release
            $this->logAction($userId ?? $lock['locked_by'], $lock['ip_master_id'], 'lock_released', [
                'lock_id' => $lockId,
                'router_serial_number' => $lock['router_serial_number'],
                'released_by' => $userId ?? $lock['locked_by']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Check if an IP_Master is available for locking
     * An IP is available if:
     * - Its status is 'available'
     * - It has no active (non-expired) locks
     * 
     * @param int $ipMasterId IP_Master ID
     * @return bool True if available
     * 
     * Requirements: 4.2
     */
    public function isIPAvailable(int $ipMasterId): bool {
        // Check IP_Master status
        $ipMaster = $this->ipMasterRepository->findById($ipMasterId);
        if (!$ipMaster || $ipMaster['status'] !== IPMaster::STATUS_AVAILABLE) {
            return false;
        }
        
        // Check for active locks
        return !$this->lockRepository->isIPLocked($ipMasterId);
    }
    
    /**
     * Get the next available IP_Master for configuration
     * Excludes all locked IPs
     * 
     * @return array|null Next available IP_Master or null if none available
     * 
     * Requirements: 3.1, 4.2
     */
    public function getNextAvailableIP(): ?array {
        // First, expire any timed-out locks
        $this->expireTimedOutLocks();
        
        // Get all locked IP_Master IDs
        $lockedIds = $this->lockRepository->getLockedIPMasterIds();
        
        // Get the next available IP that is not locked
        $sql = "SELECT * FROM `ip_master` 
                WHERE `status` = ?";
        $params = [IPMaster::STATUS_AVAILABLE];
        $types = 's';
        
        if (!empty($lockedIds)) {
            $placeholders = implode(',', array_fill(0, count($lockedIds), '?'));
            $sql .= " AND `id` NOT IN ($placeholders)";
            $params = array_merge($params, $lockedIds);
            $types .= str_repeat('i', count($lockedIds));
        }
        
        $sql .= " ORDER BY `id` ASC LIMIT 1";
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all available IPs (excluding locked ones)
     * 
     * @return array Array of available IP_Master records
     * 
     * Requirements: 4.2, 11.2
     */
    public function getAvailableIPs(): array {
        // First, expire any timed-out locks
        $this->expireTimedOutLocks();
        
        // Get all locked IP_Master IDs
        $lockedIds = $this->lockRepository->getLockedIPMasterIds();
        
        // Get all available IPs that are not locked
        $sql = "SELECT * FROM `ip_master` 
                WHERE `status` = ?";
        $params = [IPMaster::STATUS_AVAILABLE];
        $types = 's';
        
        if (!empty($lockedIds)) {
            $placeholders = implode(',', array_fill(0, count($lockedIds), '?'));
            $sql .= " AND `id` NOT IN ($placeholders)";
            $params = array_merge($params, $lockedIds);
            $types .= str_repeat('i', count($lockedIds));
        }
        
        $sql .= " ORDER BY `id` ASC";
        
        return $this->db->getResults($sql, $params, $types);
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
        $expiredCount = $this->lockRepository->expireTimedOutLocks();
        
        if ($expiredCount > 0) {
            // Log the expiry events
            error_log("Expired $expiredCount timed-out IP locks");
        }
        
        return $expiredCount;
    }
    
    /**
     * Get all active locks for dashboard
     * 
     * @return array Array of active locks with details
     * 
     * Requirements: 7.3
     */
    public function getActiveLocks(): array {
        // First, expire any timed-out locks
        $this->expireTimedOutLocks();
        
        return $this->lockRepository->getActiveLocks();
    }
    
    /**
     * Get lock by ID
     * 
     * @param int $lockId Lock ID
     * @return array|null Lock record or null
     */
    public function getLockById(int $lockId): ?array {
        return $this->lockRepository->findById($lockId);
    }
    
    /**
     * Get active lock for an IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array|null Active lock or null
     */
    public function getActiveLockByIPMaster(int $ipMasterId): ?array {
        return $this->lockRepository->findActiveLockByIPMaster($ipMasterId);
    }
    
    /**
     * Get active lock for a router
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array|null Active lock or null
     */
    public function getActiveLockByRouter(string $routerSerialNumber): ?array {
        return $this->lockRepository->findActiveLockByRouter($routerSerialNumber);
    }
    
    /**
     * Check if a router has an active configuration session
     * 
     * @param string $routerSerialNumber Router serial number
     * @return bool True if router has active session
     */
    public function routerHasActiveSession(string $routerSerialNumber): bool {
        return $this->lockRepository->findActiveLockByRouter($routerSerialNumber) !== null;
    }
    
    /**
     * Get lock statistics for dashboard
     * 
     * @return array Lock statistics
     */
    public function getLockStats(): array {
        // First, expire any timed-out locks
        $this->expireTimedOutLocks();
        
        return [
            'active_count' => $this->lockRepository->getActiveLockCount(),
            'active_locks' => $this->lockRepository->getActiveLocks()
        ];
    }
    
    /**
     * Get lock history for an IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @param int $limit Maximum records to return
     * @return array Lock history records
     */
    public function getLockHistory(int $ipMasterId, int $limit = 10): array {
        return $this->lockRepository->getLockHistory($ipMasterId, $limit);
    }
    
    /**
     * Validate that a lock belongs to a specific user
     * 
     * @param int $lockId Lock ID
     * @param int $userId User ID
     * @return bool True if lock belongs to user
     */
    public function validateLockOwnership(int $lockId, int $userId): bool {
        $lock = $this->lockRepository->findById($lockId);
        if (!$lock) {
            return false;
        }
        return (int)$lock['locked_by'] === $userId;
    }
    
    /**
     * Check if a lock is still active (not expired and status is active)
     * 
     * @param int $lockId Lock ID
     * @return bool True if lock is active
     */
    public function isLockActive(int $lockId): bool {
        $lock = $this->lockRepository->findById($lockId);
        if (!$lock) {
            return false;
        }
        return IPLock::isActive($lock);
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int|null $userId User performing the action
     * @param int $ipMasterId IP_Master ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(?int $userId, int $ipMasterId, string $action, array $details): void {
        try {
            $routerSerial = $details['router_serial_number'] ?? null;
            
            $sql = "INSERT INTO configuration_audit_log 
                    (action_type, user_id, ip_master_id, router_serial_number, details, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->executeQuery($sql, [
                $action,
                $userId ?? 0,
                $ipMasterId,
                $routerSerial,
                json_encode($details)
            ], 'siiss');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log lock action: " . $e->getMessage());
        }
    }
}
