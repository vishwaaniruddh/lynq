<?php
/**
 * Create Material Request Items Table Migration
 * Creates the material_request_items table for the Material Request Module
 * 
 * Requirements: 3.3, 4.3
 * - Tracks individual products in a material request
 * - Tracks quantities: requested, dispatched, and received
 */

require_once __DIR__ . '/Migration.php';

class CreateMaterialRequestItemsTable extends Migration {
    
    public function up() {
        $this->createMaterialRequestItemsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `material_request_items`");
    }
    
    /**
     * Create material_request_items table
     * Requirements: 3.3, 4.3
     */
    private function createMaterialRequestItemsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `material_request_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `material_request_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `quantity_requested` INT NOT NULL COMMENT 'Quantity requested from material master',
            `quantity_dispatched` INT DEFAULT 0 COMMENT 'Quantity actually dispatched',
            `quantity_received` INT DEFAULT 0 COMMENT 'Quantity confirmed received',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_material_request_id` (`material_request_id`),
            INDEX `idx_product_id` (`product_id`),
            UNIQUE KEY `unique_request_product` (`material_request_id`, `product_id`),
            FOREIGN KEY (`material_request_id`) REFERENCES `material_requests`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
