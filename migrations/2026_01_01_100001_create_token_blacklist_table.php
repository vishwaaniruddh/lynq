<?php
/**
 * Migration: Create Token Blacklist Table
 * 
 * Creates the token_blacklist table for invalidating JWT access tokens
 * before their natural expiration (e.g., on logout or password change).
 * 
 * Requirements: 3.1, 3.5
 */

require_once __DIR__ . '/../config/database.php';

class CreateTokenBlacklistTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    public function up() {
        // Create token_blacklist table
        $sql = "
            CREATE TABLE IF NOT EXISTS token_blacklist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token_jti VARCHAR(64) NOT NULL COMMENT 'Token ID (jti claim) of blacklisted token',
                expires_at DATETIME NOT NULL COMMENT 'When the token would naturally expire',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When token was blacklisted',
                INDEX idx_token_jti (token_jti),
                INDEX idx_expires_at (expires_at),
                UNIQUE KEY unique_token_jti (token_jti)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores blacklisted JWT access tokens for immediate invalidation'
        ";
        
        if (!$this->db->query($sql)) {
            throw new Exception("Failed to create token_blacklist table: " . $this->db->error);
        }
        
        echo "Token blacklist table created successfully.\n";
    }
    
    public function down() {
        $sql = "DROP TABLE IF EXISTS token_blacklist";
        
        if (!$this->db->query($sql)) {
            throw new Exception("Failed to drop token_blacklist table: " . $this->db->error);
        }
        
        echo "Token blacklist table dropped successfully.\n";
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new CreateTokenBlacklistTable();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
