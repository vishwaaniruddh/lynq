<?php
/**
 * Create Repairs Table Migration
 * Creates the repairs table for the ADV CRM Inventory Module
 * 
 * Requirements: 7.2, 7.3
 * - Record repair vendor, estimated cost, send date, and expected return date
 * - Update status to "In Stock" and record actual repair cost and completion date when repaired
 */

require_once __DIR__ . '/Migration.php';

class CreateRepairsTable extends Migration {
    
    public function up() {
        $this->createRepairsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `repairs`");
    }
    
    /**
     * Repairs table - tracks repair workflow for assets
     * Records vendor, costs, dates, and status
     */
    private function createRepairsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `repairs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `asset_id` INT NOT NULL,
            `repair_vendor` VARCHAR(150),
            `estimated_cost` DECIMAL(10,2),
            `actual_cost` DECIMAL(10,2),
            `send_date` DATE NOT NULL,
            `expected_return_date` DATE,
            `actual_return_date` DATE,
            `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            `repair_notes` TEXT COMMENT 'Notes about the repair work',
            `diagnosis` TEXT COMMENT 'Initial diagnosis of the issue',
            `resolution` TEXT COMMENT 'Description of repair work done',
            `created_by` INT,
            `updated_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_asset_id` (`asset_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_send_date` (`send_date`),
            INDEX `idx_expected_return_date` (`expected_return_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
