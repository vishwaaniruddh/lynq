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
            
            // Check if account should now be locked
            $lockStatus = $this->isAccountLocked($identifier);
            if ($lockStatus['locked']) {
                $this->securityEventService->logEvent(
                    'ACCOUNT_LOCKOUT',
                    'CRITICAL',
                    null,
                    $ipAddress,
                    [
                        'identifier' => $identifier,
                        'failed_attempts' => $this->getFailedAttemptsCount($identifier),
                        'lockout_duration' => $lockStatus['remaining_seconds'] ?? $this->lockoutDuration
                    ]
                );
                return $lockStatus;
            }
            
            $attemptsUsed = $this->getFailedAttemptsCount($identifier);
            return [
                'locked' => false,
                'attempts_remaining' => max(0, $this->maxAttempts - $attemptsUsed)
            ];
            
        } catch (Exception $e) {
            error_log("Record login attempt error: " . $e->getMessage());
            return ['locked' => false, 'attempts_remaining' => $this->maxAttempts];
        }
    }
    
    /**
     * Check if account or network IP is currently locked
     * 
     * @param string $identifier Username or email
     * @param string $ipAddress IP address
     * @return array Lockout status
     */
    public function isLocked($identifier, $ipAddress) {
        try {
            // 1. Check account-level lockout (protects specific user credentials)
            $accountStatus = $this->isAccountLocked($identifier);
            if ($accountStatus['locked']) {
                return $accountStatus;
            }
            
            // 2. Check IP-level rate limiting (protects system against massive brute force)
            $ipStatus = $this->isIpLocked($ipAddress);
            if ($ipStatus['locked']) {
                return $ipStatus;
            }
            
            return ['locked' => false];
            
        } catch (Exception $e) {
            error_log("Check lockout error: " . $e->getMessage());
            return ['locked' => false];
        }
    }

    /**
     * Check if a specific account is locked
     */
    public function isAccountLocked($identifier) {
        $prefix = strpos($identifier, '@') !== false ? explode('@', $identifier)[0] : $identifier;
        
        // Find user by username or email
        $sql = "SELECT id, username, email, status, locked_until, failed_login_attempts 
                FROM users 
                WHERE username = ? OR email = ? OR username = ? 
                LIMIT 1";
        $users = $this->db->getResults($sql, [$identifier, $identifier, $prefix], 'sss');
        
        if (!empty($users)) {
            $user = $users[0];
            // Check users table status & locked_until
            if ($user['status'] == 2 && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $rem = strtotime($user['locked_until']) - time();
                return [
                    'locked' => true,
                    'error' => 'ACCOUNT_LOCKED',
                    'message' => 'Account is temporarily locked. Please try again later.',
                    'lockout_until' => $user['locked_until'],
                    'remaining_seconds' => $rem
                ];
            }
            
            // Check login_attempts table
            $sqlAttempts = "SELECT COUNT(*) as count, MAX(created_at) as last_attempt 
                            FROM login_attempts 
                            WHERE (identifier = ? OR identifier = ? OR identifier = ?) 
                            AND success = 0 
                            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $results = $this->db->getResults($sqlAttempts, [$user['username'], $user['email'], $prefix, $this->attemptWindow], 'sssi');
        } else {
            // Unknown user identifier
            $sqlAttempts = "SELECT COUNT(*) as count, MAX(created_at) as last_attempt 
                            FROM login_attempts 
                            WHERE identifier = ? 
                            AND success = 0 
                            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $results = $this->db->getResults($sqlAttempts, [$identifier, $this->attemptWindow], 'si');
        }
        
        $failedAttempts = $results[0]['count'] ?? 0;
        $lastAttempt = $results[0]['last_attempt'] ?? null;
        
        if ($failedAttempts >= $this->maxAttempts && $lastAttempt) {
            $lockoutDuration = $this->calculateLockoutDurationByIdentifier($identifier);
            $lockoutUntil = strtotime($lastAttempt) + $lockoutDuration;
            
            if (time() < $lockoutUntil) {
                return [
                    'locked' => true,
                    'error' => 'ACCOUNT_LOCKED',
                    'message' => 'Account is temporarily locked due to multiple failed login attempts.',
                    'lockout_until' => date('Y-m-d H:i:s', $lockoutUntil),
                    'remaining_seconds' => $lockoutUntil - time()
                ];
            }
        }
        
        return ['locked' => false];
    }

    /**
     * Check if an IP address is rate-limited / locked due to massive failed attempts
     */
    public function isIpLocked($ipAddress) {
        if (empty($ipAddress) || $ipAddress === '127.0.0.1' || $ipAddress === '::1' || $ipAddress === 'unknown') {
            return ['locked' => false];
        }
        
        // Threshold for entire IP network: 20 failed attempts in window
        $ipThreshold = 20;
        $sql = "SELECT COUNT(*) as count, MAX(created_at) as last_attempt 
                FROM login_attempts 
                WHERE ip_address = ? 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $results = $this->db->getResults($sql, [$ipAddress, $this->attemptWindow], 'si');
        
        $failures = $results[0]['count'] ?? 0;
        $lastAttempt = $results[0]['last_attempt'] ?? null;
        
        if ($failures >= $ipThreshold && $lastAttempt) {
            $lockoutUntil = strtotime($lastAttempt) + $this->lockoutDuration;
            if (time() < $lockoutUntil) {
                return [
                    'locked' => true,
                    'error' => 'IP_RATE_LIMITED',
                    'message' => 'Too many failed login attempts from this network. Please wait a few minutes before trying again.',
                    'lockout_until' => date('Y-m-d H:i:s', $lockoutUntil),
                    'remaining_seconds' => $lockoutUntil - time()
                ];
            }
        }
        
        return ['locked' => false];
    }
    
    /**
     * Get recent failed attempts count for an identifier
     */
    public function getFailedAttemptsCount($identifier) {
        $prefix = strpos($identifier, '@') !== false ? explode('@', $identifier)[0] : $identifier;
        $sql = "SELECT COUNT(*) as count FROM login_attempts 
                WHERE (identifier = ? OR identifier = ?) 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $results = $this->db->getResults($sql, [$identifier, $prefix, $this->attemptWindow], 'ssi');
        return $results[0]['count'] ?? 0;
    }
    
    /**
     * Calculate lockout duration for identifier
     */
    private function calculateLockoutDurationByIdentifier($identifier) {
        if (!$this->progressiveLockout) {
            return $this->lockoutDuration;
        }
        
        $prefix = strpos($identifier, '@') !== false ? explode('@', $identifier)[0] : $identifier;
        $sql = "SELECT COUNT(DISTINCT DATE(created_at)) as lockout_count 
                FROM login_attempts 
                WHERE (identifier = ? OR identifier = ?) 
                AND success = 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY identifier
                HAVING COUNT(*) >= ?";
        
        $results = $this->db->getResults($sql, [$identifier, $prefix, $this->maxAttempts], 'ssi');
        $lockoutCount = count($results);
        
        return $this->lockoutDuration * pow($this->progressiveMultiplier, min($lockoutCount, 5));
    }
    
    /**
     * Clear failed attempts (on successful login)
     */
    private function clearFailedAttempts($identifier, $ipAddress) {
        // Successful login recorded in login_attempts
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
            $prefix = explode('@', $user['email'])[0];
            
            // Delete recent failed attempts for this user (including username prefix/alias)
            $sql = "DELETE FROM login_attempts 
                    WHERE (identifier = ? OR identifier = ? OR identifier = ?) 
                    AND success = 0";
            $stmt = $this->db->executeQuery($sql, [$user['username'], $user['email'], $prefix], 'sss');
            if ($stmt) $stmt->close();
            
            // Update user status if locked
            $sql = "UPDATE users SET status = ?, locked_until = NULL, failed_login_attempts = 0 WHERE id = ?";
            $stmt = $this->db->executeQuery($sql, [USER_STATUS_ACTIVE, $userId], 'ii');
            if ($stmt) $stmt->close();
            
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
     * Manually clear failed attempts and unlock an IP address
     * 
     * @param string $ipAddress IP address to unlock
     * @param int $adminId Admin performing the unlock
     * @return bool Success status
     */
    public function unlockIp($ipAddress, $adminId) {
        try {
            $sql = "DELETE FROM login_attempts WHERE ip_address = ? AND success = 0";
            $stmt = $this->db->executeQuery($sql, [$ipAddress], 's');
            if ($stmt) $stmt->close();
            
            $this->securityEventService->logEvent(
                'IP_UNLOCKED',
                'INFO',
                $adminId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                [
                    'unlocked_ip' => $ipAddress,
                    'unlocked_by' => $adminId
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log("Unlock IP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unlock all locked accounts and clear failed attempts
     */
    public function unlockAll($adminId) {
        try {
            // Reset all locked / monitoring users
            $sql = "UPDATE users SET status = ?, locked_until = NULL, failed_login_attempts = 0 
                    WHERE status = 2 OR failed_login_attempts > 0 OR locked_until IS NOT NULL";
            $stmt = $this->db->executeQuery($sql, [USER_STATUS_ACTIVE], 'i');
            if ($stmt) $stmt->close();

            // Clear all failed login attempts
            $db = Database::getInstance()->getConnection();
            $db->exec("DELETE FROM login_attempts WHERE success = 0");

            $this->securityEventService->logEvent(
                'ALL_ACCOUNTS_UNLOCKED',
                'INFO',
                $adminId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ['unlocked_by' => $adminId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Unlock all error: " . $e->getMessage());
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
