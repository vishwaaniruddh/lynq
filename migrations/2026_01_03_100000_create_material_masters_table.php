<?php
/**
 * Create Material Masters Table Migration
 * Creates the material_masters table for the Material Request Module
 * 
 * Requirements: 1.4, 9.2
 * - Material Master is a reusable template defining a set of products required for site installations
 * - Supports soft delete for maintaining historical data
 */

require_once __DIR__ . '/Migration.php';

class CreateMaterialMastersTable extends Migration {
    
    public function up() {
        $this->createMaterialMastersTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `material_masters`");
    }
    
    /**
     * Create material_masters table
     * Requirements: 1.4, 9.2
     */
    private function createMaterialMastersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `material_masters` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(500) NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `company_id` INT NOT NULL COMMENT 'Company isolation',
            `created_by` INT NOT NULL COMMENT 'User who created',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` TIMESTAMP NULL COMMENT 'Soft delete timestamp',
            INDEX `idx_company_id` (`company_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_deleted_at` (`deleted_at`),
            INDEX `idx_company_status` (`company_id`, `status`, `deleted_at`),
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
