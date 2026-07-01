<?php
/**
 * Create Material Master Items Table Migration
 * Creates the material_master_items table for the Material Request Module
 * 
 * Requirements: 1.3, 1.4
 * - Links products to material masters with quantities
 * - Each material master can have multiple products with specified quantities
 */

require_once __DIR__ . '/Migration.php';

class CreateMaterialMasterItemsTable extends Migration {
    
    public function up() {
        $this->createMaterialMasterItemsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `material_master_items`");
    }
    
    /**
     * Create material_master_items table
     * Requirements: 1.3, 1.4
     */
    private function createMaterialMasterItemsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `material_master_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `material_master_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `quantity` INT NOT NULL COMMENT 'Required quantity for this product',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_material_master_id` (`material_master_id`),
            INDEX `idx_product_id` (`product_id`),
            UNIQUE KEY `unique_master_product` (`material_master_id`, `product_id`),
            FOREIGN KEY (`material_master_id`) REFERENCES `material_masters`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
