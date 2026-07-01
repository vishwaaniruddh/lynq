<?php
/**
 * Create Engineer Assignments Table Migration
 * Creates the engineer_assignments table for tracking site assignments to engineers
 * 
 * Requirements: 5.1, 5.2
 */

require_once __DIR__ . '/Migration.php';

class CreateEngineerAssignmentsTable extends Migration {
    
    public function up() {
        $this->createEngineerAssignmentsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `engineer_assignments`");
    }
    
    /**
     * Create engineer_assignments table
     * Requirements: 5.1, 5.2
     */
    private function createEngineerAssignmentsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `engineer_assignments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `site_id` INT NOT NULL,
            `delegation_id` INT NOT NULL COMMENT 'Reference to accepted delegation',
            `engineer_id` INT NOT NULL COMMENT 'User ID of engineer',
            `assigned_by` INT NOT NULL COMMENT 'User ID who assigned',
            `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('assigned', 'in_progress', 'completed') DEFAULT 'assigned',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_engineer` (`engineer_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_site` (`site_id`),
            INDEX `idx_delegation` (`delegation_id`),
            FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`delegation_id`) REFERENCES `site_delegations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`engineer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
