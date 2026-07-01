<?php
/**
 * Create Feasibility Reviews Table Migration
 * Creates the feasibility_reviews table for approval workflow
 * 
 * Requirements: 10.2, 10.3, 10.4, 10.5
 */

require_once __DIR__ . '/Migration.php';

class CreateFeasibilityReviewsTable extends Migration {
    
    public function up() {
        $this->createFeasibilityReviewsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `feasibility_reviews`");
    }
    
    /**
     * Create feasibility_reviews table
     * Requirements: 10.2, 10.3, 10.4, 10.5
     * 
     * Stores review records for the approval workflow including:
     * - Contractor Admin/Manager reviews
     * - ADV final reviews
     * - Rejection details with section-specific information
     */
    private function createFeasibilityReviewsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `feasibility_reviews` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `feasibility_id` INT NOT NULL COMMENT 'Reference to feasibility_checks',
            `reviewer_id` INT NOT NULL COMMENT 'User ID of the reviewer',
            `reviewer_role` ENUM('contractor_admin', 'contractor_manager', 'adv') NOT NULL COMMENT 'Role of the reviewer',
            `review_type` ENUM('approval', 'rejection') NOT NULL COMMENT 'Type of review action',
            `rejection_type` ENUM('overall', 'section_specific') NULL COMMENT 'Type of rejection (null if approval)',
            `rejected_sections` JSON NULL COMMENT 'Array of section names that were rejected',
            `reason` TEXT NULL COMMENT 'Required for rejections, min 10 characters',
            `comments` TEXT NULL COMMENT 'Optional comments for approvals',
            `reviewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the review was submitted',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Indexes for performance
            INDEX `idx_feasibility` (`feasibility_id`),
            INDEX `idx_reviewer` (`reviewer_id`),
            INDEX `idx_review_type` (`review_type`),
            INDEX `idx_reviewed_at` (`reviewed_at`),
            
            -- Foreign keys
            FOREIGN KEY (`feasibility_id`) REFERENCES `feasibility_checks`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
