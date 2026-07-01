<?php
/**
 * Create Configuration Audit Log Table Migration
 * Creates the configuration_audit_log table for tracking all IP configuration activities
 * 
 * Requirements: 9.1, 9.2
 * - Logs all configuration actions (locks, bindings, unbindings)
 * - Supports filtering by action type, timestamp, router, and user
 * - Stores additional context in JSON format
 */

require_once __DIR__ . '/Migration.php';

class CreateConfigurationAuditLogTable extends Migration {
    
    public function up() {
        $this->createConfigurationAuditLogTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `configuration_audit_log`");
    }
    
    /**
     * Create configuration_audit_log table
     * Requirements: 9.1, 9.2
     */
    private function createConfigurationAuditLogTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `configuration_audit_log` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `action_type` ENUM(
                'lock_acquired',
                'lock_released', 
                'lock_expired',
                'configured',
                'unbound',
                'ip_created',
                'ip_updated',
                'ip_deleted',
                'bulk_upload'
            ) NOT NULL COMMENT 'Type of configuration action',
            `user_id` INT NOT NULL COMMENT 'User ID who performed the action',
            `router_serial_number` VARCHAR(100) NULL COMMENT 'Router serial number (if applicable)',
            `ip_master_id` INT NULL COMMENT 'Reference to ip_master (if applicable)',
            `details` JSON NULL COMMENT 'Additional context in JSON format',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action occurred',
            
            -- Foreign keys
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`ip_master_id`) REFERENCES `ip_master`(`id`) ON DELETE SET NULL,
            
            -- Index for filtering by action type (Requirement 9.2)
            INDEX `idx_action_type` (`action_type`),
            
            -- Index for filtering by timestamp (for reporting)
            INDEX `idx_created_at` (`created_at`),
            
            -- Index for filtering by router serial number
            INDEX `idx_router` (`router_serial_number`),
            
            -- Index for filtering by user
            INDEX `idx_user_id` (`user_id`),
            
            -- Composite index for common query patterns
            INDEX `idx_action_created` (`action_type`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Configuration audit log for tracking all IP configuration activities'";
        
        $this->execute($sql);
    }
}
