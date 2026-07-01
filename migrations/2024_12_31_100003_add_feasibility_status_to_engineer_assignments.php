<?php
/**
 * Add Feasibility Status to Engineer Assignments Migration
 * Adds feasibility_status column to engineer_assignments table for tracking feasibility workflow
 * 
 * Requirements: 1.5, 2.4, 3.5, 4.5
 */

require_once __DIR__ . '/Migration.php';

class AddFeasibilityStatusToEngineerAssignments extends Migration {
    
    public function up() {
        $this->addFeasibilityStatusColumn();
    }
    
    public function down() {
        $this->removeFeasibilityStatusColumn();
    }
    
    /**
     * Add feasibility_status column to engineer_assignments table
     * Requirements: 1.5, 2.4, 3.5, 4.5
     * 
     * Status values:
     * - pending_eta: Initial state, waiting for ETA submission
     * - eta_submitted: ETA has been submitted
     * - ada_submitted: ADA has been submitted (actual arrival confirmed)
     * - feasibility_completed: Feasibility check has been completed
     */
    private function addFeasibilityStatusColumn() {
        // Check if column already exists
        if ($this->columnExists('engineer_assignments', 'feasibility_status')) {
            return;
        }
        
        $sql = "ALTER TABLE `engineer_assignments` 
                ADD COLUMN `feasibility_status` ENUM('pending_eta', 'eta_submitted', 'ada_submitted', 'feasibility_completed') 
                DEFAULT 'pending_eta' 
                COMMENT 'Feasibility workflow status' 
                AFTER `status`";
        
        $this->execute($sql);
        
        // Add index for feasibility_status for efficient filtering
        $indexSql = "ALTER TABLE `engineer_assignments` 
                     ADD INDEX `idx_feasibility_status` (`feasibility_status`)";
        
        $this->execute($indexSql);
    }
    
    /**
     * Remove feasibility_status column from engineer_assignments table
     */
    private function removeFeasibilityStatusColumn() {
        if (!$this->columnExists('engineer_assignments', 'feasibility_status')) {
            return;
        }
        
        // Drop index first
        $dropIndexSql = "ALTER TABLE `engineer_assignments` 
                         DROP INDEX `idx_feasibility_status`";
        
        try {
            $this->execute($dropIndexSql);
        } catch (Exception $e) {
            // Index might not exist, continue
        }
        
        $sql = "ALTER TABLE `engineer_assignments` 
                DROP COLUMN `feasibility_status`";
        
        $this->execute($sql);
    }
}
