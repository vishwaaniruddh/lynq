<?php
/**
 * Migration: Create Refresh Tokens Table
 * 
 * Creates the refresh_tokens table for storing JWT refresh tokens.
 * Enables token revocation and audit trail for refresh token usage.
 * 
 * Requirements: 2.5
 */

require_once __DIR__ . '/../config/database.php';

class CreateRefreshTokensTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    public function up() {
        // Create refresh_tokens table
        $sql = "
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_id VARCHAR(64) NOT NULL COMMENT 'Unique token identifier (jti claim)',
                token_hash VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of the token',
                expires_at DATETIME NOT NULL COMMENT 'Token expiration timestamp',
                revoked_at DATETIME NULL COMMENT 'When token was revoked (NULL if active)',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL COMMENT 'Client IP at token creation',
                user_agent VARCHAR(255) NULL COMMENT 'Client user agent at token creation',
                INDEX idx_user_id (user_id),
                INDEX idx_token_id (token_id),
                INDEX idx_expires_at (expires_at),
                INDEX idx_revoked_at (revoked_at),
                UNIQUE KEY unique_token_id (token_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores JWT refresh tokens for revocation tracking'
        ";
        
        if (!$this->db->query($sql)) {
            throw new Exception("Failed to create refresh_tokens table: " . $this->db->error);
        }
        
        echo "Refresh tokens table created successfully.\n";
    }
    
    public function down() {
        $sql = "DROP TABLE IF EXISTS refresh_tokens";
        
        if (!$this->db->query($sql)) {
            throw new Exception("Failed to drop refresh_tokens table: " . $this->db->error);
        }
        
        echo "Refresh tokens table dropped successfully.\n";
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new CreateRefreshTokensTable();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
