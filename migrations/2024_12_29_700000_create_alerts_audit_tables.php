<?php
/**
 * Create Alerts and Audit Tables Migration
 * Creates the stock_alerts, stock_thresholds, and inventory_audit_log tables
 * 
 * Requirements: 12.1, 13.1, 13.2
 * - Log user, action type, timestamp, source location, and destination location for all inventory actions
 * - Generate low stock alert when product stock falls below defined threshold
 * - Allow per-product and per-warehouse threshold settings
 */

require_once __DIR__ . '/Migration.php';

class CreateAlertsAuditTables extends Migration {
    
    public function up() {
        $this->createStockAlertsTable();
        $this->createStockThresholdsTable();
        $this->createInventoryAuditLogTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `inventory_audit_log`");
        $this->execute("DROP TABLE IF EXISTS `stock_thresholds`");
        $this->execute("DROP TABLE IF EXISTS `stock_alerts`");
    }
    
    /**
     * Stock Alerts table - tracks low stock and overdue repair alerts
     */
    private function createStockAlertsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `stock_alerts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `warehouse_id` INT NOT NULL,
            `alert_type` ENUM('low_stock', 'overdue_repair') NOT NULL,
            `current_value` INT COMMENT 'Current stock quantity or days overdue',
            `threshold_value` INT COMMENT 'Threshold that triggered the alert',
            `status` ENUM('active', 'cleared') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `cleared_at` TIMESTAMP NULL,
            `cleared_by` INT,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`cleared_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_warehouse_id` (`warehouse_id`),
            INDEX `idx_alert_type` (`alert_type`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Stock Thresholds table - per-product per-warehouse threshold settings
     */
    private function createStockThresholdsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `stock_thresholds` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `warehouse_id` INT NULL COMMENT 'NULL means applies to all warehouses for this product',
            `threshold_quantity` INT NOT NULL,
            `created_by` INT,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_threshold` (`product_id`, `warehouse_id`),
            INDEX `idx_product_id` (`product_id`),
            INDEX `idx_warehouse_id` (`warehouse_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Inventory Audit Log table - complete action logging for compliance
     */
    private function createInventoryAuditLogTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `inventory_audit_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `action_type` VARCHAR(50) NOT NULL COMMENT 'stock_entry, dispatch, transfer, status_change, repair, etc.',
            `entity_type` VARCHAR(50) NOT NULL COMMENT 'asset, stock, dispatch, transfer, repair',
            `entity_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `from_location_type` VARCHAR(50) COMMENT 'warehouse, company, user',
            `from_location_id` INT,
            `to_location_type` VARCHAR(50) COMMENT 'warehouse, company, user',
            `to_location_id` INT,
            `old_values` JSON COMMENT 'Previous state before action',
            `new_values` JSON COMMENT 'New state after action',
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `notes` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            INDEX `idx_action_type` (`action_type`),
            INDEX `idx_entity_type` (`entity_type`),
            INDEX `idx_entity_id` (`entity_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_from_location` (`from_location_type`, `from_location_id`),
            INDEX `idx_to_location` (`to_location_type`, `to_location_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
