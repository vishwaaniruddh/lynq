<?php
/**
 * IP Lock Model
 * Represents a temporary lock on an IP_Master during configuration
 * 
 * Requirements: 4.1
 * - Temporary locking during 20-minute configuration process
 * - Tracks lock status (active, released, expired)
 * - Prevents other users from selecting locked IPs
 */

require_once __DIR__ . '/BaseModel.php';

class IPLock extends BaseModel {
    protected $table = 'ip_locks';
    protected $fillable = [
        'ip_master_id', 'router_serial_number', 'locked_by',
        'locked_at', 'expires_at', 'status', 'released_at'
    ];
    
    // Lock duration in minutes (Requirement 4.1)
    const LOCK_DURATION_MINUTES = 20;
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_RELEASED = 'released';
    const STATUS_EXPIRED = 'expired';
    
    /**
     * Get all valid statuses
     */
    public static function getStatuses(): array {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_RELEASED,
            self::STATUS_EXPIRED
        ];
    }
    
    /**
     * Check if a status is valid
     */
    public static function isValidStatus(string $status): bool {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Check if the lock is expired based on current time
     * Requirement 4.1: Lock expires after 20 minutes
     * 
     * @param array $lock Lock record with expires_at field
     * @return bool True if the lock has expired
     */
    public static function isExpired(array $lock): bool {
        if (!isset($lock['expires_at'])) {
            return true;
        }
        
        $expiresAt = strtotime($lock['expires_at']);
        $now = time();
        
        return $now >= $expiresAt;
    }
    
    /**
     * Check if the lock is active (not expired and status is active)
     * 
     * @param array $lock Lock record
     * @return bool True if the lock is currently active
     */
    public static function isActive(array $lock): bool {
        if (!isset($lock['status'])) {
            return false;
        }
        
        return $lock['status'] === self::STATUS_ACTIVE && !self::isExpired($lock);
    }
    
    /**
     * Calculate expiry time from a given timestamp
     * Requirement 4.1: Lock duration is exactly 20 minutes
     * 
     * @param string|null $fromTime Starting time (defaults to now)
     * @return string Expiry timestamp in MySQL format
     */
    public static function calculateExpiryTime(?string $fromTime = null): string {
        $startTime = $fromTime ? strtotime($fromTime) : time();
        $expiryTime = $startTime + (self::LOCK_DURATION_MINUTES * 60);
        return date('Y-m-d H:i:s', $expiryTime);
    }
    
    /**
     * Get remaining time in seconds for a lock
     * 
     * @param array $lock Lock record with expires_at field
     * @return int Remaining seconds (0 if expired)
     */
    public static function getRemainingSeconds(array $lock): int {
        if (!isset($lock['expires_at'])) {
            return 0;
        }
        
        $expiresAt = strtotime($lock['expires_at']);
        $now = time();
        $remaining = $expiresAt - $now;
        
        return max(0, $remaining);
    }
    
    /**
     * Get remaining time formatted as MM:SS
     * 
     * @param array $lock Lock record with expires_at field
     * @return string Formatted remaining time
     */
    public static function getRemainingTimeFormatted(array $lock): string {
        $seconds = self::getRemainingSeconds($lock);
        
        if ($seconds <= 0) {
            return '00:00';
        }
        
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $secs);
    }
    
    /**
     * Find active locks for a specific IP_Master
     * 
     * @param int $ipMasterId IP_Master ID
     * @return array|null Active lock record or null
     */
    public function findActiveLockByIPMaster(int $ipMasterId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `ip_master_id` = ? 
                AND `status` = ? 
                AND `expires_at` > NOW()
                ORDER BY `id` DESC 
                LIMIT 1";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$ipMasterId, self::STATUS_ACTIVE], 'is');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find active lock for a specific router
     * 
     * @param string $routerSerialNumber Router serial number
     * @return array|null Active lock record or null
     */
    public function findActiveLockByRouter(string $routerSerialNumber): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `router_serial_number` = ? 
                AND `status` = ? 
                AND `expires_at` > NOW()
                ORDER BY `id` DESC 
                LIMIT 1";
        
        $result = DatabaseConfig::getInstance()->getResults($sql, [$routerSerialNumber, self::STATUS_ACTIVE], 'ss');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all active locks
     * 
     * @return array Array of active lock records
     */
    public function findAllActive(): array {
        $sql = "SELECT l.*, im.network_ip, im.router_ip, im.site_ip, im.subnet_mask
                FROM `{$this->table}` l
                LEFT JOIN `ip_master` im ON l.ip_master_id = im.id
                WHERE l.`status` = ? 
                AND l.`expires_at` > NOW()
                ORDER BY l.`expires_at` ASC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE], 's');
    }
    
    /**
     * Find expired locks that need to be cleaned up
     * 
     * @return array Array of expired lock records
     */
    public function findExpiredLocks(): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `status` = ? 
                AND `expires_at` <= NOW()
                ORDER BY `expires_at` ASC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_ACTIVE], 's');
    }
    
    /**
     * Get lock count by status
     * 
     * @return array Counts by status
     */
    public function getCountByStatus(): array {
        $sql = "SELECT status, COUNT(*) as count FROM `{$this->table}` GROUP BY status";
        $results = DatabaseConfig::getInstance()->getResults($sql, [], '');
        
        $counts = [
            self::STATUS_ACTIVE => 0,
            self::STATUS_RELEASED => 0,
            self::STATUS_EXPIRED => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total'] += (int)$row['count'];
        }
        
        return $counts;
    }
}
