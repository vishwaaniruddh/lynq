<?php
/**
 * Create Warehouses Table Migration
 * Creates the warehouses table for the ADV CRM Inventory Module
 * 
 * Requirements: 1.1, 1.4
 * - Store warehouse with name, location, company assignment, and active status
 * - Validate that warehouse name is unique within the same company
 */

require_once __DIR__ . '/Migration.php';

class CreateWarehousesTable extends Migration {
    
    public function up() {
        $this->createWarehousesTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `warehouses`");
    }
    
    private function createWarehousesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `warehouses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `location` VARCHAR(255),
            `company_id` INT NOT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_by` INT,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_warehouse_name_company` (`name`, `company_id`),
            INDEX `idx_company_id` (`company_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
