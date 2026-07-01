<?php
/**
 * Create Sites Table Migration
 * Creates the sites table for the Site Management module
 * 
 * Requirements: 1.1, 1.5
 */

require_once __DIR__ . '/Migration.php';

class CreateSitesTable extends Migration {
    
    public function up() {
        $this->createSitesTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `sites`");
    }
    
    /**
     * Create sites table
     * Requirements: 1.1, 1.5
     */
    private function createSitesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `sites` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `site_name` VARCHAR(255) NOT NULL,
            `lho` VARCHAR(100) NOT NULL,
            `bank_name` VARCHAR(255) NULL,
            `customer_name` VARCHAR(255) NULL,
            `city` VARCHAR(100) NOT NULL,
            `state` VARCHAR(100) NOT NULL,
            `country` VARCHAR(100) NOT NULL,
            `zone` VARCHAR(100) NULL,
            `address` TEXT NULL,
            `latitude` DECIMAL(10, 8) NULL,
            `longitude` DECIMAL(11, 8) NULL,
            `company_id` INT NOT NULL COMMENT 'ADV company that owns the site',
            `status` ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `created_by` INT NOT NULL,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            `updated_by` INT NULL,
            UNIQUE KEY `unique_site_lho_company` (`site_name`, `lho`, `company_id`),
            INDEX `idx_lho` (`lho`),
            INDEX `idx_status` (`status`),
            INDEX `idx_company` (`company_id`),
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
