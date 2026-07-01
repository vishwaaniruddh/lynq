<?php
/**
 * Create Feasibility ETA Table Migration
 * Creates the feasibility_eta table for tracking engineer estimated time of arrival
 * 
 * Requirements: 2.2, 2.5
 */

require_once __DIR__ . '/Migration.php';

class CreateFeasibilityEtaTable extends Migration {
    
    public function up() {
        $this->createFeasibilityEtaTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `feasibility_eta`");
    }
    
    /**
     * Create feasibility_eta table
     * Requirements: 2.2, 2.5
     * 
     * Columns:
     * - id: Primary key
     * - assignment_id: Reference to engineer_assignments
     * - eta_datetime: The estimated date/time of arrival
     * - submitted_by: User ID of engineer who submitted
     * - submitted_at: Timestamp when ETA was submitted
     * - is_current: Boolean flag to track current vs historical ETAs
     * - created_at: Record creation timestamp
     * - updated_at: Record update timestamp
     */
    private function createFeasibilityEtaTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `feasibility_eta` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `assignment_id` INT NOT NULL COMMENT 'Reference to engineer_assignments',
            `eta_datetime` DATETIME NOT NULL COMMENT 'Estimated time of arrival',
            `submitted_by` INT NOT NULL COMMENT 'User ID of engineer who submitted',
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When ETA was submitted',
            `is_current` BOOLEAN DEFAULT TRUE COMMENT 'Flag for current vs historical ETA',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_assignment` (`assignment_id`),
            INDEX `idx_current` (`is_current`),
            INDEX `idx_assignment_current` (`assignment_id`, `is_current`),
            FOREIGN KEY (`assignment_id`) REFERENCES `engineer_assignments`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
