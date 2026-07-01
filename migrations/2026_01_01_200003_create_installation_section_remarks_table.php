<?php
/**
 * Create Installation Section Remarks Table Migration
 * Creates the installation_section_remarks table for storing review comments and history
 * 
 * Requirements: 12.2, 12.3, 13.4
 */

require_once __DIR__ . '/Migration.php';

class CreateInstallationSectionRemarksTable extends Migration {
    
    public function up() {
        $this->createInstallationSectionRemarksTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `installation_section_remarks`");
    }
    
    /**
     * Create installation_section_remarks table
     * 
     * Requirements:
     * - 12.2: Record approval with reviewer ID, timestamp, and optional remarks
     * - 12.3: Require rejection reason (minimum 10 characters)
     * - 13.4: ADV rejection requires reason
     * 
     * Stores the history of all review actions (approvals and rejections) for each section
     */
    private function createInstallationSectionRemarksTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `installation_section_remarks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `installation_id` INT NOT NULL COMMENT 'Reference to installations table',
            `section` VARCHAR(50) NOT NULL COMMENT 'Section identifier',
            `reviewer_id` INT NOT NULL COMMENT 'User who performed the review',
            `reviewer_level` ENUM('contractor', 'adv') NOT NULL COMMENT 'Level of reviewer',
            `review_type` ENUM('approval', 'rejection') NOT NULL COMMENT 'Type of review action',
            `remark` TEXT NULL COMMENT 'Review remarks (required for rejections, min 10 chars)',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Constraints
            INDEX `idx_installation` (`installation_id`),
            INDEX `idx_section` (`section`),
            INDEX `idx_reviewer` (`reviewer_id`),
            INDEX `idx_reviewer_level` (`reviewer_level`),
            INDEX `idx_review_type` (`review_type`),
            FOREIGN KEY (`installation_id`) REFERENCES `installations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
