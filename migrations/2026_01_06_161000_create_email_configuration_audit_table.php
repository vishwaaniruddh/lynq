<?php
/**
 * Email Configuration Audit Log Table Migration
 * Creates audit log table for email configuration changes
 */

require_once __DIR__ . '/Migration.php';

class CreateEmailConfigurationAuditTable extends Migration {
    
    public function up() {
        if ($this->tableExists('email_configuration_audit_log')) {
            echo "Table email_configuration_audit_log already exists, skipping.\n";
            return;
        }
        
        $sql = "CREATE TABLE `email_configuration_audit_log` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `configuration_id` INT NOT NULL,
            `action` ENUM('created', 'updated', 'deleted', 'connection_tested') NOT NULL,
            `details` JSON,
            `user_id` INT NOT NULL,
            `ip_address` VARCHAR(45),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`configuration_id`) REFERENCES `email_configurations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            INDEX `idx_config_action` (`configuration_id`, `action`),
            INDEX `idx_user_created` (`user_id`, `created_at`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
        echo "Created table: email_configuration_audit_log\n";
    }
    
    public function down() {
        if ($this->tableExists('email_configuration_audit_log')) {
            $this->execute("DROP TABLE `email_configuration_audit_log`");
            echo "Dropped table: email_configuration_audit_log\n";
        }
    }
}