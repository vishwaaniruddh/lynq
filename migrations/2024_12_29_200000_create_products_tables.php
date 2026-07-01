<?php
/**
 * Create Product Categories and Products Tables Migration
 * Creates the product_categories and products tables for the ADV CRM Inventory Module
 * 
 * Requirements: 2.1, 2.2, 2.3
 * - Product name, category, unit of measure, inventory type (INTERNAL/SITE), serializable flag, repairable flag
 * - Serializable products require serial number entry for each unit
 * - Non-serializable products tracked by quantity only
 */

require_once __DIR__ . '/Migration.php';

class CreateProductsTables extends Migration {
    
    public function up() {
        $this->createProductCategoriesTable();
        $this->createProductsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `products`");
        $this->execute("DROP TABLE IF EXISTS `product_categories`");
    }
    
    private function createProductCategoriesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `product_categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `parent_id` INT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_by` INT,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_parent_id` (`parent_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createProductsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `category_id` INT,
            `unit_of_measure` VARCHAR(50) NOT NULL,
            `inventory_type` ENUM('INTERNAL', 'SITE') NOT NULL,
            `is_serializable` TINYINT(1) DEFAULT 0,
            `is_repairable` TINYINT(1) DEFAULT 0,
            `low_stock_threshold` INT DEFAULT 0,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `description` TEXT,
            `created_by` INT,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_category_id` (`category_id`),
            INDEX `idx_inventory_type` (`inventory_type`),
            INDEX `idx_is_serializable` (`is_serializable`),
            INDEX `idx_is_repairable` (`is_repairable`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
