<?php
/**
 * Token Blacklist Repository
 * 
 * Manages blacklisted JWT access tokens for immediate invalidation.
 * Used when users logout or when tokens need to be revoked before expiration.
 * 
 * **Feature: jwt-authentication**
 * Requirements: 3.1, 3.3, 3.5
 */

require_once __DIR__ . '/../config/database.php';

class TokenBlacklistRepository {
    private $db;
    private $table = 'token_blacklist';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Add a token to the blacklist
     * 
     * @param string $jti Token ID (jti claim)
     * @param string $expiresAt When the token would naturally expire (Y-m-d H:i:s format)
     * @return bool Success status
     */
    public function add(string $jti, string $expiresAt): bool {
        // Check if already blacklisted to avoid duplicate key error
        if ($this->isBlacklisted($jti)) {
            return true; // Already blacklisted, consider it a success
        }
        
        $sql = "INSERT INTO `{$this->table}` (token_jti, expires_at, created_at) 
                VALUES (?, ?, NOW())";
        
        try {
            $stmt = $this->db->executeQuery($sql, [$jti, $expiresAt], 'ss');
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        } catch (Exception $e) {
            // Handle duplicate key gracefully
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return true;
            }
            error_log("TokenBlacklistRepository::add error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a token is blacklisted
     * 
     * @param string $jti Token ID (jti claim)
     * @return bool True if token is blacklisted
     */
    public function isBlacklisted(string $jti): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE token_jti = ?";
        
        $result = $this->db->getResults($sql, [$jti], 's');
        return isset($result[0]['count']) && $result[0]['count'] > 0;
    }
    
    /**
     * Remove expired entries from the blacklist
     * Only removes entries where expires_at is in the past.
     * This prevents unbounded growth of the blacklist table.
     * 
     * @return int Number of entries removed
     */
    public function cleanup(): int {
        $sql = "DELETE FROM `{$this->table}` WHERE expires_at < NOW()";
        
        try {
            $stmt = $this->db->executeQuery($sql, [], '');
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            error_log("TokenBlacklistRepository::cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get count of blacklisted tokens
     * 
     * @return int Total count of blacklisted tokens
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}`";
        
        $result = $this->db->getResults($sql, [], '');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get count of expired entries (for monitoring)
     * 
     * @return int Count of expired entries that can be cleaned up
     */
    public function countExpired(): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE expires_at < NOW()";
        
        $result = $this->db->getResults($sql, [], '');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Get count of active (non-expired) entries
     * 
     * @return int Count of active blacklist entries
     */
    public function countActive(): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE expires_at >= NOW()";
        
        $result = $this->db->getResults($sql, [], '');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Find blacklist entry by jti
     * 
     * @param string $jti Token ID
     * @return array|null Blacklist entry or null
     */
    public function findByJti(string $jti): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE token_jti = ?";
        
        $result = $this->db->getResults($sql, [$jti], 's');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Remove a specific token from blacklist (rarely used, mainly for testing)
     * 
     * @param string $jti Token ID
     * @return bool Success status
     */
    public function remove(string $jti): bool {
        $sql = "DELETE FROM `{$this->table}` WHERE token_jti = ?";
        
        try {
            $stmt = $this->db->executeQuery($sql, [$jti], 's');
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        } catch (Exception $e) {
            error_log("TokenBlacklistRepository::remove error: " . $e->getMessage());
            return false;
        }
    }
}
