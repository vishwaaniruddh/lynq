<?php
/**
 * Migration: Create company_access_log table
 * For logging cross-company access attempts
 */

require_once __DIR__ . '/Migration.php';

class CreateCompanyAccessLog extends Migration {
    
    public function up() {
        $sql = "CREATE TABLE IF NOT EXISTS `company_access_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `target_company_id` INT NOT NULL,
            `access_result` ENUM('GRANTED', 'DENIED') NOT NULL,
            `reason` VARCHAR(255) NULL,
            `ip_address` VARCHAR(45) NULL,
            `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_target_company_id` (`target_company_id`),
            INDEX `idx_access_result` (`access_result`),
            INDEX `idx_timestamp` (`timestamp`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`target_company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        return $this->execute($sql);
    }
    
    public function down() {
        $sql = "DROP TABLE IF EXISTS `company_access_log`";
        return $this->execute($sql);
    }
}
