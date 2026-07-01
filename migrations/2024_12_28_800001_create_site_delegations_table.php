<?php
/**
 * Create Site Delegations Table Migration
 * Creates the site_delegations table for tracking site assignments to contractors
 * 
 * Requirements: 2.1, 2.2
 */

require_once __DIR__ . '/Migration.php';

class CreateSiteDelegationsTable extends Migration {
    
    public function up() {
        $this->createSiteDelegationsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `site_delegations`");
    }
    
    /**
     * Create site_delegations table
     * Requirements: 2.1, 2.2
     */
    private function createSiteDelegationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `site_delegations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `site_id` INT NOT NULL,
            `contractor_id` INT NOT NULL COMMENT 'Company ID of contractor',
            `delegated_by` INT NOT NULL COMMENT 'User ID who delegated',
            `delegated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
            `rejection_notes` TEXT NULL,
            `responded_by` INT NULL,
            `responded_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_active_delegation` (`site_id`, `contractor_id`, `status`),
            INDEX `idx_contractor` (`contractor_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_site` (`site_id`),
            FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`contractor_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`delegated_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`responded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
