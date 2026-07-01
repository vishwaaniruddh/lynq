<?php
/**
 * Create Material Requests Table Migration
 * Creates the material_requests table for the Material Request Module
 * 
 * Requirements: 3.3, 9.6
 * - Tracks material requests from request to receipt
 * - Status workflow: requested -> approved -> dispatched -> received
 * - Links to sites and material masters
 */

require_once __DIR__ . '/Migration.php';

class CreateMaterialRequestsTable extends Migration {
    
    public function up() {
        $this->createMaterialRequestsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `material_requests`");
    }
    
    /**
     * Create material_requests table
     * Requirements: 3.3, 9.6
     */
    private function createMaterialRequestsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `material_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `site_id` INT NOT NULL,
            `material_master_id` INT NOT NULL,
            `status` ENUM('requested', 'approved', 'dispatched', 'received') DEFAULT 'requested',
            `company_id` INT NOT NULL COMMENT 'Company isolation',
            `requested_by` INT NOT NULL COMMENT 'User who created the request',
            `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `approved_by` INT NULL COMMENT 'User who approved',
            `approved_at` TIMESTAMP NULL,
            `dispatched_at` TIMESTAMP NULL,
            `received_at` TIMESTAMP NULL,
            `received_by` INT NULL COMMENT 'Engineer who confirmed receipt',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_site_id` (`site_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_company_id` (`company_id`),
            INDEX `idx_material_master_id` (`material_master_id`),
            INDEX `idx_requested_by` (`requested_by`),
            INDEX `idx_site_status` (`site_id`, `status`),
            INDEX `idx_company_status` (`company_id`, `status`),
            FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`material_master_id`) REFERENCES `material_masters`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
