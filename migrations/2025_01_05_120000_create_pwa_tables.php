<?php
/**
 * ADV Clarity Management System - PWA Tables Migration
 * Creates tables for PWA functionality: push subscriptions, sync queue, and analytics
 */

require_once __DIR__ . '/Migration.php';

class CreatePWATables extends Migration {
    
    public function up() {
        // Create push_subscriptions table
        $this->execute("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                company_id INT NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh_key TEXT NOT NULL,
                auth_key VARCHAR(255) NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                last_sent TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_company (user_id, company_id),
                INDEX idx_active (active),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create sync_queue table for offline synchronization
        $this->execute("
            CREATE TABLE IF NOT EXISTS sync_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                company_id INT NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NULL,
                action_data JSON NOT NULL,
                conflict_data JSON NULL,
                status ENUM('pending', 'processing', 'completed', 'failed', 'conflict', 'resolved') DEFAULT 'pending',
                resolution VARCHAR(20) NULL,
                retry_count INT DEFAULT 0,
                max_retries INT DEFAULT 3,
                resolved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_company_status (user_id, company_id, status),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_status_created (status, created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create pwa_analytics table for usage tracking
        $this->execute("
            CREATE TABLE IF NOT EXISTS pwa_analytics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                company_id INT NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                event_data JSON NULL,
                user_agent TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_company_event (user_id, company_id, event_type),
                INDEX idx_event_type_date (event_type, created_at),
                INDEX idx_company_date (company_id, created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "PWA tables created successfully.\n";
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS pwa_analytics");
        $this->execute("DROP TABLE IF EXISTS sync_queue");
        $this->execute("DROP TABLE IF EXISTS push_subscriptions");
        
        echo "PWA tables dropped successfully.\n";
    }
}

// Run migration if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $migration = new CreatePWATables();
    $migration->up();
}
?>