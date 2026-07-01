<?php
/**
 * IP Restriction Service
 * Implements IP-based access restrictions (whitelist/blacklist)
 * 
 * Requirements: 3.2 - Cross-company access prevention and security
 */

require_once __DIR__ . '/../config/autoload.php';

class IPRestrictionService {
    private $db;
    private $securityEventService;
    
    // Restriction modes
    const MODE_WHITELIST = 'WHITELIST';
    const MODE_BLACKLIST = 'BLACKLIST';
    
    // Default mode (BLACKLIST = block specific IPs, WHITELIST = allow only specific IPs)
    private $defaultMode = 'BLACKLIST';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->securityEventService = new SecurityEventService();
    }
    
    /**
     * Check if IP is allowed to access the system
     * 
     * @param string $ipAddress IP address to check
     * @return array Result with allowed status and reason
     */
    public function isIPAllowed($ipAddress) {
        try {
            // Validate IP format
            if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                return ['allowed' => false, 'reason' => 'Invalid IP address format'];
            }

            // Check blacklist first
            $blacklisted = $this->isBlacklisted($ipAddress);
            if ($blacklisted['found']) {
                $this->securityEventService->logEvent(
                    SecurityEventService::EVENT_IP_BLOCKED,
                    SecurityEventService::SEVERITY_WARNING,
                    null,
                    $ipAddress,
                    ['reason' => $blacklisted['reason']]
                );
                return ['allowed' => false, 'reason' => $blacklisted['reason'] ?? 'IP is blacklisted'];
            }
            
            // Check if whitelist mode is enabled
            if ($this->isWhitelistModeEnabled()) {
                $whitelisted = $this->isWhitelisted($ipAddress);
                if (!$whitelisted['found']) {
                    return ['allowed' => false, 'reason' => 'IP is not in whitelist'];
                }
            }
            
            return ['allowed' => true];
            
        } catch (Exception $e) {
            error_log("IP restriction check error: " . $e->getMessage());
            // Fail open - allow access if check fails (can be changed to fail closed)
            return ['allowed' => true, 'reason' => 'Check failed, defaulting to allow'];
        }
    }
    
    /**
     * Check if IP is blacklisted
     */
    private function isBlacklisted($ipAddress) {
        $sql = "SELECT * FROM ip_restrictions 
                WHERE ip_address = ? 
                AND restriction_type = 'BLACKLIST' 
                AND (expires_at IS NULL OR expires_at > NOW())";
        
        $results = $this->db->getResults($sql, [$ipAddress], 's');
        
        if (!empty($results)) {
            return ['found' => true, 'reason' => $results[0]['reason']];
        }
        
        // Check CIDR ranges (simplified - exact match only for now)
        return ['found' => false];
    }
    
    /**
     * Check if IP is whitelisted
     */
    private function isWhitelisted($ipAddress) {
        $sql = "SELECT * FROM ip_restrictions 
                WHERE ip_address = ? 
                AND restriction_type = 'WHITELIST' 
                AND (expires_at IS NULL OR expires_at > NOW())";
        
        $results = $this->db->getResults($sql, [$ipAddress], 's');
        
        return ['found' => !empty($results)];
    }
    
    /**
     * Check if whitelist mode is enabled
     */
    private function isWhitelistModeEnabled() {
        // Check if there are any whitelist entries
        $sql = "SELECT COUNT(*) as count FROM ip_restrictions WHERE restriction_type = 'WHITELIST'";
        $results = $this->db->getResults($sql);
        
        return ($results[0]['count'] ?? 0) > 0;
    }

    /**
     * Add IP to blacklist
     * 
     * @param string $ipAddress IP address
     * @param string|null $reason Reason for blacklisting
     * @param string|null $expiresAt Expiration datetime
     * @param int|null $createdBy User ID who created the restriction
     * @return bool Success status
     */
    public function blacklistIP($ipAddress, $reason = null, $expiresAt = null, $createdBy = null) {
        return $this->addRestriction($ipAddress, self::MODE_BLACKLIST, $reason, $expiresAt, $createdBy);
    }
    
    /**
     * Add IP to whitelist
     */
    public function whitelistIP($ipAddress, $reason = null, $expiresAt = null, $createdBy = null) {
        return $this->addRestriction($ipAddress, self::MODE_WHITELIST, $reason, $expiresAt, $createdBy);
    }
    
    /**
     * Add IP restriction
     */
    private function addRestriction($ipAddress, $type, $reason, $expiresAt, $createdBy) {
        try {
            if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                return false;
            }
            
            // Use INSERT ... ON DUPLICATE KEY UPDATE
            $sql = "INSERT INTO ip_restrictions (ip_address, restriction_type, reason, expires_at, created_by) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    restriction_type = VALUES(restriction_type),
                    reason = VALUES(reason),
                    expires_at = VALUES(expires_at),
                    created_by = VALUES(created_by)";
            
            $stmt = $this->db->executeQuery($sql, [$ipAddress, $type, $reason, $expiresAt, $createdBy], 'ssssi');
            $stmt->close();
            
            // Log event
            $eventType = $type === self::MODE_BLACKLIST 
                ? SecurityEventService::EVENT_IP_BLOCKED 
                : SecurityEventService::EVENT_IP_WHITELISTED;
            
            $this->securityEventService->logEvent(
                $eventType,
                SecurityEventService::SEVERITY_INFO,
                $createdBy,
                $ipAddress,
                ['reason' => $reason, 'expires_at' => $expiresAt]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Add IP restriction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove IP restriction
     */
    public function removeRestriction($ipAddress) {
        try {
            $sql = "DELETE FROM ip_restrictions WHERE ip_address = ?";
            $stmt = $this->db->executeQuery($sql, [$ipAddress], 's');
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Remove IP restriction error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all restrictions
     */
    public function getAllRestrictions($type = null) {
        try {
            $sql = "SELECT ir.*, u.username as created_by_username 
                    FROM ip_restrictions ir 
                    LEFT JOIN users u ON ir.created_by = u.id";
            
            if ($type) {
                $sql .= " WHERE ir.restriction_type = ?";
                return $this->db->getResults($sql, [$type], 's');
            }
            
            return $this->db->getResults($sql);
            
        } catch (Exception $e) {
            error_log("Get restrictions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up expired restrictions
     */
    public function cleanupExpired() {
        try {
            $sql = "DELETE FROM ip_restrictions WHERE expires_at IS NOT NULL AND expires_at < NOW()";
            $stmt = $this->db->executeQuery($sql);
            $deleted = $stmt->affected_rows;
            $stmt->close();
            return $deleted;
        } catch (Exception $e) {
            error_log("Cleanup expired restrictions error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Auto-blacklist IP after suspicious activity
     */
    public function autoBlacklistIP($ipAddress, $reason, $durationHours = 24) {
        $expiresAt = date('Y-m-d H:i:s', time() + ($durationHours * 3600));
        return $this->blacklistIP($ipAddress, "Auto-blocked: {$reason}", $expiresAt, null);
    }
}
