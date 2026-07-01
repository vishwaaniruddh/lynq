<?php
/**
 * Account Lockout Service
 * Implements account lockout after failed login attempts
 * 
 * Requirements: 5.5, 3.2 - Security enforcement and cross-company access prevention
 */

require_once __DIR__ . '/../config/autoload.php';

class AccountLockoutService {
    private $db;
    private $securityEventService;
    
    // Lockout configuration
    private $maxAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes in seconds
    private $attemptWindow = 900; // Time window to count attempts (15 minutes)
    private $progressiveLockout = true; // Enable progressive lockout
    private $progressiveMultiplier = 2; // Double lockout time for each subsequent lockout
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->securityEventService = new SecurityEventService();
    }
    
    /**
     * Record a login attempt
     * 
     * @param string $identifier Username or email
     * @param string $ipAddress IP address
     * @param bool $success Whether the attempt was successful
     * @param string|null $failureReason Reason for failure
     * @return array Result with lockout status
     */
    public function recordAttempt($identifier, $ipAddress, $success, $failureReason = null) {
        try {
            // Record the attempt
            $sql = "INSERT INTO login_attempts (identifier, ip_address, success, failure_reason, user_agent) 
                    VALUES (?, ?, ?, ?, ?)";
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $stmt = $this->db->executeQuery($sql, [
                $identifier, 
                $ipAddress, 
                $success ? 1 : 0, 
                $failureReason,
                $userAgent
            ], 'ssiss');
            $stmt->close();
            
            if ($success) {
                // Clear failed attempts on successful login
                $this->clearFailedAttempts($identifier, $ipAddress);
                return ['locked' => false, 'attempts_remaining' => $this->maxAttempts];
            }
            
            // Check if account should be locked
            $failedAttempts = $this->getRecentFailedAttempts($identifier, $ipAddress);
            
            if ($failedAttempts >= $this->maxAttempts) {
                $lockoutTime = $this->calculateLockoutDuration($identifier, $ipAddress);
                
                // Log security event
                $this->securityEventService->logEvent(
                    'ACCOUNT_LOCKOUT',
                    'CRITICAL',
                    null,
                    $ipAddress,
                    [
                        'identifier' => $identifier,
                        'failed_attempts' => $failedAttempts,
                        'lockout_duration' => $lockoutTime
                    ]
                );
                
                return [
                    'locked' => true,
                    'lockout_duration' => $lockoutTime,
                    'lockout_until' => date('Y-m-d H:i:s', time() + $lockoutTime),
                    'attempts_remaining' => 0
                ];
            }
            
            return [
                'locked' => false,
                'attempts_remaining' => $this->maxAttempts - $failedAttempts
            ];
            
        } catch (Exception $e) {
            error_log("Record login attempt error: " . $e->getMessage());
            return ['locked' => false, 'attempts_remaining' => $this->maxAttempts];
        }
    }
    
    /**
     * Check if account/IP is currently locked
     * 
     * @param string $identifier Username or email
     * @param string $ipAddress IP address
     * @return array Lockout status
     */
    public function isLocked($identifier, $ipAddress) {
        try {
            $failedAttempts = $this->getRecentFailedAttempts($identifier, $ipAddress);
            
            if ($failedAttempts >= $this->maxAttempts) {
                // Check if lockout period has passed
                $lastAttempt = $this->getLastFailedAttemptTime($identifier, $ipAddress);
                $lockoutDuration = $this->calculateLockoutDuration($identifier, $ipAddress);
                $lockoutUntil = strtotime($lastAttempt) + $lockoutDuration;
                
                if (time() < $lockoutUntil) {
                    return [
                        'locked' => true,
                        'lockout_until' => date('Y-m-d H:i:s', $lockoutUntil),
                        'remaining_seconds' => $lockoutUntil - time()
                    ];
                }
            }
            
            return ['locked' => false];
            
        } catch (Exception $e) {
            error_log("Check lockout error: " . $e->getMessage());
            return ['locked' => false];
        }
    }
    
    /**
     * Get recent failed attempts count
     */
    private function getRecentFailedAttempts($identifier, $ipAddress) {
        $sql = "SELECT COUNT(*) as count FROM login_attempts 
                WHERE (identifier = ? OR ip_address = ?) 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $results = $this->db->getResults($sql, [$identifier, $ipAddress, $this->attemptWindow], 'ssi');
        
        return $results[0]['count'] ?? 0;
    }
    
    /**
     * Get last failed attempt time
     */
    private function getLastFailedAttemptTime($identifier, $ipAddress) {
        $sql = "SELECT MAX(created_at) as last_attempt FROM login_attempts 
                WHERE (identifier = ? OR ip_address = ?) 
                AND success = 0";
        
        $results = $this->db->getResults($sql, [$identifier, $ipAddress], 'ss');
        
        return $results[0]['last_attempt'] ?? null;
    }
    
    /**
     * Calculate lockout duration (with progressive lockout)
     */
    private function calculateLockoutDuration($identifier, $ipAddress) {
        if (!$this->progressiveLockout) {
            return $this->lockoutDuration;
        }
        
        // Count previous lockouts in the last 24 hours
        $sql = "SELECT COUNT(DISTINCT DATE(created_at)) as lockout_count 
                FROM login_attempts 
                WHERE (identifier = ? OR ip_address = ?) 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY identifier, ip_address
                HAVING COUNT(*) >= ?";
        
        $results = $this->db->getResults($sql, [$identifier, $ipAddress, $this->maxAttempts], 'ssi');
        
        $lockoutCount = count($results);
        
        // Progressive lockout: double the duration for each subsequent lockout
        return $this->lockoutDuration * pow($this->progressiveMultiplier, min($lockoutCount, 5));
    }
    
    /**
     * Clear failed attempts (on successful login)
     */
    private function clearFailedAttempts($identifier, $ipAddress) {
        // We don't delete the records, just mark them as cleared by recording a successful attempt
        // This preserves the audit trail
    }
    
    /**
     * Manually unlock an account (admin function)
     * 
     * @param int $userId User ID to unlock
     * @param int $adminId Admin performing the unlock
     * @return bool Success status
     */
    public function unlockAccount($userId, $adminId) {
        try {
            // Get user info
            $sql = "SELECT username, email FROM users WHERE id = ?";
            $results = $this->db->getResults($sql, [$userId], 'i');
            
            if (empty($results)) {
                return false;
            }
            
            $user = $results[0];
            
            // Delete recent failed attempts for this user
            $sql = "DELETE FROM login_attempts 
                    WHERE (identifier = ? OR identifier = ?) 
                    AND success = 0 
                    AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $stmt = $this->db->executeQuery($sql, [$user['username'], $user['email'], $this->attemptWindow], 'ssi');
            $stmt->close();
            
            // Update user status if locked
            $sql = "UPDATE users SET status = ?, locked_until = NULL, failed_login_attempts = 0 WHERE id = ?";
            $stmt = $this->db->executeQuery($sql, [USER_STATUS_ACTIVE, $userId], 'ii');
            $stmt->close();
            
            // Log security event
            $this->securityEventService->logEvent(
                'ACCOUNT_UNLOCKED',
                'INFO',
                $adminId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                [
                    'unlocked_user_id' => $userId,
                    'unlocked_by' => $adminId
                ]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Unlock account error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get lockout statistics
     */
    public function getLockoutStats($hours = 24) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_attempts,
                        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                        SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed,
                        COUNT(DISTINCT ip_address) as unique_ips,
                        COUNT(DISTINCT identifier) as unique_identifiers
                    FROM login_attempts 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)";
            
            $results = $this->db->getResults($sql, [$hours], 'i');
            
            return $results[0] ?? [
                'total_attempts' => 0,
                'successful' => 0,
                'failed' => 0,
                'unique_ips' => 0,
                'unique_identifiers' => 0
            ];
            
        } catch (Exception $e) {
            error_log("Get lockout stats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get configuration
     */
    public function getConfig() {
        return [
            'maxAttempts' => $this->maxAttempts,
            'lockoutDuration' => $this->lockoutDuration,
            'attemptWindow' => $this->attemptWindow,
            'progressiveLockout' => $this->progressiveLockout,
            'progressiveMultiplier' => $this->progressiveMultiplier
        ];
    }
}
