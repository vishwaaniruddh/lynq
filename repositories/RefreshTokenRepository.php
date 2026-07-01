<?php
/**
 * Refresh Token Repository
 * 
 * Manages JWT refresh tokens in the database for revocation tracking.
 * Does not use company isolation as tokens are user-specific.
 * 
 * **Feature: jwt-authentication**
 * Requirements: 2.5, 3.2
 */

require_once __DIR__ . '/../config/database.php';

class RefreshTokenRepository {
    private $db;
    private $table = 'refresh_tokens';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Store a new refresh token
     * 
     * @param int $userId User ID
     * @param string $tokenId Unique token identifier (jti)
     * @param string $tokenHash SHA-256 hash of the token
     * @param string $expiresAt Expiration timestamp (Y-m-d H:i:s format)
     * @param string|null $ipAddress Client IP address
     * @param string|null $userAgent Client user agent
     * @return bool Success status
     */
    public function store(
        int $userId, 
        string $tokenId, 
        string $tokenHash, 
        string $expiresAt,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        $sql = "INSERT INTO `{$this->table}` 
                (user_id, token_id, token_hash, expires_at, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        try {
            $stmt = $this->db->executeQuery(
                $sql, 
                [$userId, $tokenId, $tokenHash, $expiresAt, $ipAddress, $userAgent], 
                'isssss'
            );
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        } catch (Exception $e) {
            error_log("RefreshTokenRepository::store error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find refresh token by token ID
     * 
     * @param string $tokenId Token identifier (jti)
     * @return array|null Token record or null if not found
     */
    public function findByTokenId(string $tokenId): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE token_id = ?";
        
        $result = $this->db->getResults($sql, [$tokenId], 's');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find all active (non-revoked, non-expired) refresh tokens for a user
     * 
     * @param int $userId User ID
     * @return array List of active tokens
     */
    public function findActiveByUserId(int $userId): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE user_id = ? 
                AND revoked_at IS NULL 
                AND expires_at > NOW()
                ORDER BY created_at DESC";
        
        return $this->db->getResults($sql, [$userId], 'i') ?: [];
    }
    
    /**
     * Revoke all refresh tokens for a user
     * Used when user changes password or admin revokes access
     * 
     * @param int $userId User ID
     * @return int Number of tokens revoked
     */
    public function revokeByUserId(int $userId): int {
        $sql = "UPDATE `{$this->table}` 
                SET revoked_at = NOW() 
                WHERE user_id = ? AND revoked_at IS NULL";
        
        try {
            $stmt = $this->db->executeQuery($sql, [$userId], 'i');
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            error_log("RefreshTokenRepository::revokeByUserId error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Revoke a specific refresh token
     * 
     * @param string $tokenId Token identifier (jti)
     * @return bool Success status
     */
    public function revokeByTokenId(string $tokenId): bool {
        $sql = "UPDATE `{$this->table}` 
                SET revoked_at = NOW() 
                WHERE token_id = ? AND revoked_at IS NULL";
        
        try {
            $stmt = $this->db->executeQuery($sql, [$tokenId], 's');
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        } catch (Exception $e) {
            error_log("RefreshTokenRepository::revokeByTokenId error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a token is valid (exists, not revoked, not expired)
     * 
     * @param string $tokenId Token identifier (jti)
     * @return bool True if token is valid
     */
    public function isValid(string $tokenId): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE token_id = ? 
                AND revoked_at IS NULL 
                AND expires_at > NOW()";
        
        $result = $this->db->getResults($sql, [$tokenId], 's');
        return isset($result[0]['count']) && $result[0]['count'] > 0;
    }
    
    /**
     * Verify token hash matches stored hash
     * 
     * @param string $tokenId Token identifier (jti)
     * @param string $tokenHash Hash to verify
     * @return bool True if hash matches
     */
    public function verifyTokenHash(string $tokenId, string $tokenHash): bool {
        $token = $this->findByTokenId($tokenId);
        if (!$token) {
            return false;
        }
        return hash_equals($token['token_hash'], $tokenHash);
    }
    
    /**
     * Clean up expired tokens
     * Removes tokens that have expired (past their expires_at time)
     * 
     * @return int Number of tokens removed
     */
    public function cleanup(): int {
        $sql = "DELETE FROM `{$this->table}` WHERE expires_at < NOW()";
        
        try {
            $stmt = $this->db->executeQuery($sql, [], '');
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            error_log("RefreshTokenRepository::cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get count of active tokens for a user
     * 
     * @param int $userId User ID
     * @return int Count of active tokens
     */
    public function countActiveByUserId(int $userId): int {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` 
                WHERE user_id = ? 
                AND revoked_at IS NULL 
                AND expires_at > NOW()";
        
        $result = $this->db->getResults($sql, [$userId], 'i');
        return $result[0]['count'] ?? 0;
    }
}
