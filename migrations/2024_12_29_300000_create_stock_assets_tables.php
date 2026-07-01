<?php
/**
 * Create Stock and Assets Tables Migration
 * Creates the stock and assets tables for the ADV CRM Inventory Module
 * 
 * Requirements: 3.1, 3.2, 3.3
 * - Stock table for non-serializable items (quantity-based tracking)
 * - Assets table for serializable items (individual asset tracking with serial numbers)
 * - Unique constraint on serial_number to prevent duplicates
 */

require_once __DIR__ . '/Migration.php';

class CreateStockAssetsTables extends Migration {
    
    public function up() {
        $this->createStockTable();
        $this->createAssetsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `assets`");
        $this->execute("DROP TABLE IF EXISTS `stock`");
    }
    
    /**
     * Stock table for non-serializable items
     * Tracks quantity per product per warehouse
     */
    private function createStockTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `stock` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `warehouse_id` INT NOT NULL,
            `quantity` INT DEFAULT 0,
            `reserved_quantity` INT DEFAULT 0,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_product_warehouse` (`product_id`, `warehouse_id`),
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_warehouse_id` (`warehouse_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Assets table for serializable items
     * Each row represents a unique physical item with serial number
     */
    private function createAssetsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `assets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `serial_number` VARCHAR(100) NOT NULL,
            `warehouse_id` INT,
            `status` ENUM('in_stock', 'dispatched', 'assigned', 'in_use', 'returned', 'under_repair', 'scrapped', 'lost') DEFAULT 'in_stock',
            `working_condition` ENUM('working', 'not_working') DEFAULT 'working',
            `current_holder_type` ENUM('warehouse', 'company', 'user') DEFAULT 'warehouse',
            `current_holder_id` INT,
            `source_warehouse_id` INT COMMENT 'Original warehouse where asset was first added',
            `warranty_expiry` DATE,
            `notes` TEXT,
            `created_by` INT,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`source_warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_serial_number` (`serial_number`),
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_warehouse_id` (`warehouse_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_working_condition` (`working_condition`),
            INDEX `idx_current_holder` (`current_holder_type`, `current_holder_id`),
            INDEX `idx_source_warehouse_id` (`source_warehouse_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
