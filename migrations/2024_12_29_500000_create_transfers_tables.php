<?php
/**
 * Create Transfers and Transfer Items Tables Migration
 * Creates the transfers and transfer_items tables for the ADV CRM Inventory Module
 * 
 * Requirements: 5.4
 * - Inter-warehouse transfer: decrement source warehouse stock and increment destination warehouse stock
 */

require_once __DIR__ . '/Migration.php';

class CreateTransfersTables extends Migration {
    
    public function up() {
        $this->createTransfersTable();
        $this->createTransferItemsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `transfer_items`");
        $this->execute("DROP TABLE IF EXISTS `transfers`");
    }
    
    /**
     * Transfers table - main transfer header
     * Tracks inter-warehouse transfers
     */
    private function createTransfersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `transfers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transfer_number` VARCHAR(50) NOT NULL,
            `from_warehouse_id` INT NOT NULL,
            `to_warehouse_id` INT NOT NULL,
            `transfer_date` DATE NOT NULL,
            `status` ENUM('pending', 'in_transit', 'completed', 'cancelled') DEFAULT 'pending',
            `notes` TEXT,
            `created_by` INT,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_transfer_number` (`transfer_number`),
            INDEX `idx_from_warehouse_id` (`from_warehouse_id`),
            INDEX `idx_to_warehouse_id` (`to_warehouse_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_transfer_date` (`transfer_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Transfer Items table - line items for each transfer
     * Links transfers to products/assets with quantities
     */
    private function createTransferItemsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `transfer_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transfer_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `asset_id` INT NULL COMMENT 'For serializable items, references specific asset',
            `quantity` INT DEFAULT 1 COMMENT 'For non-serializable items, quantity transferred',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`transfer_id`) REFERENCES `transfers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE SET NULL,
            INDEX `idx_transfer_id` (`transfer_id`),
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_asset_id` (`asset_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
