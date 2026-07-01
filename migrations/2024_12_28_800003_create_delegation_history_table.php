<?php
/**
 * Create Delegation History Table Migration
 * Creates the delegation_history table for audit trail of delegation activities
 * 
 * Requirements: 3.3
 */

require_once __DIR__ . '/Migration.php';

class CreateDelegationHistoryTable extends Migration {
    
    public function up() {
        $this->createDelegationHistoryTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `delegation_history`");
    }
    
    /**
     * Create delegation_history table
     * Requirements: 3.3
     */
    private function createDelegationHistoryTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `delegation_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `delegation_id` INT NOT NULL,
            `action` ENUM('created', 'accepted', 'rejected', 'reassigned') NOT NULL,
            `performed_by` INT NOT NULL,
            `notes` TEXT NULL,
            `performed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_delegation` (`delegation_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_performed_at` (`performed_at`),
            FOREIGN KEY (`delegation_id`) REFERENCES `site_delegations`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
