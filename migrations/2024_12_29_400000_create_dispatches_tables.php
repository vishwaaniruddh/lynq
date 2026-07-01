<?php
/**
 * Create Dispatches and Dispatch Items Tables Migration
 * Creates the dispatches and dispatch_items tables for the ADV CRM Inventory Module
 * 
 * Requirements: 5.1, 5.3, 5.5
 * - Dispatch with source warehouse, destination (company/user/warehouse), items, quantities
 * - Update item status from "In Stock" to "Dispatched" and record from/to details
 * - Require selection of specific serial numbers for serializable items
 */

require_once __DIR__ . '/Migration.php';

class CreateDispatchesTables extends Migration {
    
    public function up() {
        $this->createDispatchesTable();
        $this->createDispatchItemsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `dispatch_items`");
        $this->execute("DROP TABLE IF EXISTS `dispatches`");
    }
    
    /**
     * Dispatches table - main dispatch header
     * Tracks dispatch from source to destination with status
     */
    private function createDispatchesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `dispatches` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `dispatch_number` VARCHAR(50) NOT NULL,
            `from_company_id` INT NOT NULL,
            `from_warehouse_id` INT NOT NULL,
            `to_company_id` INT,
            `to_user_id` INT,
            `to_warehouse_id` INT,
            `dispatch_date` DATE NOT NULL,
            `status` ENUM('pending', 'in_transit', 'delivered', 'cancelled') DEFAULT 'pending',
            `acknowledgment_status` ENUM('pending', 'acknowledged') DEFAULT 'pending',
            `acknowledged_at` TIMESTAMP NULL,
            `acknowledged_by` INT,
            `notes` TEXT,
            `created_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`from_company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`to_company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_dispatch_number` (`dispatch_number`),
            INDEX `idx_from_company_id` (`from_company_id`),
            INDEX `idx_from_warehouse_id` (`from_warehouse_id`),
            INDEX `idx_to_company_id` (`to_company_id`),
            INDEX `idx_to_user_id` (`to_user_id`),
            INDEX `idx_to_warehouse_id` (`to_warehouse_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_acknowledgment_status` (`acknowledgment_status`),
            INDEX `idx_dispatch_date` (`dispatch_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Dispatch Items table - line items for each dispatch
     * Links dispatches to products/assets with quantities
     */
    private function createDispatchItemsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `dispatch_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `dispatch_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `asset_id` INT NULL COMMENT 'For serializable items, references specific asset',
            `quantity` INT DEFAULT 1 COMMENT 'For non-serializable items, quantity dispatched',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE SET NULL,
            INDEX `idx_dispatch_id` (`dispatch_id`),
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_asset_id` (`asset_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
