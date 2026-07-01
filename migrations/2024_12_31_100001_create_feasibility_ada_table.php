<?php
/**
 * Create Feasibility ADA Table Migration
 * Creates the feasibility_ada table for tracking engineer actual date of arrival with geolocation
 * 
 * Requirements: 3.4
 */

require_once __DIR__ . '/Migration.php';

class CreateFeasibilityAdaTable extends Migration {
    
    public function up() {
        $this->createFeasibilityAdaTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `feasibility_ada`");
    }
    
    /**
     * Create feasibility_ada table
     * Requirements: 3.4
     * 
     * Columns:
     * - id: Primary key
     * - assignment_id: Reference to engineer_assignments (unique - one ADA per assignment)
     * - ada_datetime: The actual date/time of arrival
     * - latitude: GPS latitude coordinate
     * - longitude: GPS longitude coordinate
     * - submitted_by: User ID of engineer who submitted
     * - submitted_at: Timestamp when ADA was submitted
     * - created_at: Record creation timestamp
     */
    private function createFeasibilityAdaTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `feasibility_ada` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `assignment_id` INT NOT NULL COMMENT 'Reference to engineer_assignments',
            `ada_datetime` DATETIME NOT NULL COMMENT 'Actual date/time of arrival',
            `latitude` DECIMAL(10, 8) NOT NULL COMMENT 'GPS latitude coordinate',
            `longitude` DECIMAL(11, 8) NOT NULL COMMENT 'GPS longitude coordinate',
            `submitted_by` INT NOT NULL COMMENT 'User ID of engineer who submitted',
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When ADA was submitted',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_assignment_ada` (`assignment_id`),
            INDEX `idx_assignment` (`assignment_id`),
            FOREIGN KEY (`assignment_id`) REFERENCES `engineer_assignments`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
