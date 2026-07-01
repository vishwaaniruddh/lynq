<?php
/**
 * Migration: Create Security Tables
 * Creates tables for advanced security features including:
 * - Security events logging
 * - IP restrictions
 * - Two-factor authentication preparation
 * - Password policy tracking
 */

require_once __DIR__ . '/../config/database.php';

class CreateSecurityTables {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
    }
    
    public function up() {
        // Security Events Log Table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS security_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                severity ENUM('INFO', 'WARNING', 'CRITICAL') DEFAULT 'INFO',
                user_id INT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                details JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_severity (severity),
                INDEX idx_user_id (user_id),
                INDEX idx_ip_address (ip_address),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // IP Restrictions Table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS ip_restrictions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                restriction_type ENUM('WHITELIST', 'BLACKLIST') NOT NULL,
                reason VARCHAR(255) NULL,
                expires_at TIMESTAMP NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_ip (ip_address),
                INDEX idx_restriction_type (restriction_type),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Two-Factor Authentication Preparation Table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS user_2fa (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                secret_key VARCHAR(255) NULL,
                is_enabled TINYINT(1) DEFAULT 0,
                backup_codes JSON NULL,
                last_used_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Password History Table (for password policy enforcement)
        $this->db->query("
            CREATE TABLE IF NOT EXISTS password_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Login Attempts Table (for detailed tracking)
        $this->db->query("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                success TINYINT(1) DEFAULT 0,
                failure_reason VARCHAR(100) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_identifier (identifier),
                INDEX idx_ip_address (ip_address),
                INDEX idx_created_at (created_at),
                INDEX idx_success (success)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "Security tables created successfully.\n";
    }
    
    public function down() {
        $this->db->query("DROP TABLE IF EXISTS login_attempts");
        $this->db->query("DROP TABLE IF EXISTS password_history");
        $this->db->query("DROP TABLE IF EXISTS user_2fa");
        $this->db->query("DROP TABLE IF EXISTS ip_restrictions");
        $this->db->query("DROP TABLE IF EXISTS security_events");
        
        echo "Security tables dropped successfully.\n";
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new CreateSecurityTables();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
