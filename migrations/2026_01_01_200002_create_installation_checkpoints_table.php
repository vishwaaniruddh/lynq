<?php
/**
 * Create Installation Checkpoints Table Migration
 * Creates the installation_checkpoints table for section-wise approval tracking
 * 
 * Requirements: 12.1-12.7, 13.1-13.6
 */

require_once __DIR__ . '/Migration.php';

class CreateInstallationCheckpointsTable extends Migration {
    
    public function up() {
        $this->createInstallationCheckpointsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `installation_checkpoints`");
    }
    
    /**
     * Create installation_checkpoints table
     * 
     * Requirements:
     * - 12.1-12.7: Contractor review with section-wise approve/reject
     * - 13.1-13.6: ADV final approval with section-wise options
     * 
     * Tracks approval status for each section at both contractor and ADV levels
     */
    private function createInstallationCheckpointsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `installation_checkpoints` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `installation_id` INT NOT NULL COMMENT 'Reference to installations table',
            `section` VARCHAR(50) NOT NULL COMMENT 'Section identifier (router_fixed, router_status, adaptor, etc.)',
            
            -- Contractor Review Status (Requirements 12.1-12.7)
            `contractor_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'Contractor review status',
            `contractor_reviewer_id` INT NULL COMMENT 'Contractor reviewer user ID',
            `contractor_reviewed_at` TIMESTAMP NULL COMMENT 'When contractor reviewed',
            
            -- ADV Review Status (Requirements 13.1-13.6)
            `adv_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'ADV review status',
            `adv_reviewer_id` INT NULL COMMENT 'ADV reviewer user ID',
            `adv_reviewed_at` TIMESTAMP NULL COMMENT 'When ADV reviewed',
            
            -- Audit fields
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            
            -- Constraints
            UNIQUE KEY `unique_installation_section` (`installation_id`, `section`),
            INDEX `idx_installation` (`installation_id`),
            INDEX `idx_section` (`section`),
            INDEX `idx_contractor_status` (`contractor_status`),
            INDEX `idx_adv_status` (`adv_status`),
            FOREIGN KEY (`installation_id`) REFERENCES `installations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`contractor_reviewer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`adv_reviewer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
