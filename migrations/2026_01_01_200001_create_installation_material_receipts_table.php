<?php
/**
 * Create Installation Material Receipts Table Migration
 * Creates the installation_material_receipts table for tracking material receipt confirmations
 * 
 * Requirements: 2.2, 2.3
 */

require_once __DIR__ . '/Migration.php';

class CreateInstallationMaterialReceiptsTable extends Migration {
    
    public function up() {
        $this->createInstallationMaterialReceiptsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `installation_material_receipts`");
    }
    
    /**
     * Create installation_material_receipts table
     * 
     * Requirements:
     * - 2.2: Record confirmation with timestamp and engineer ID
     * - 2.3: Update installation status to "materials_received"
     */
    private function createInstallationMaterialReceiptsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `installation_material_receipts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `installation_id` INT NOT NULL COMMENT 'Reference to installations table',
            `confirmed_by` INT NOT NULL COMMENT 'Engineer who confirmed material receipt',
            `confirmed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When materials were confirmed received',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Constraints
            UNIQUE KEY `unique_installation_receipt` (`installation_id`),
            INDEX `idx_installation` (`installation_id`),
            INDEX `idx_confirmed_by` (`confirmed_by`),
            FOREIGN KEY (`installation_id`) REFERENCES `installations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
