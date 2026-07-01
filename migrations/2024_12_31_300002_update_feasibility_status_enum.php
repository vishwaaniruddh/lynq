<?php
/**
 * Update Feasibility Status Enum in Engineer Assignments Migration
 * Adds new status values for approval workflow to feasibility_status column
 * 
 * Requirements: 10.6, 10.7, 11.3, 11.5
 */

require_once __DIR__ . '/Migration.php';

class UpdateFeasibilityStatusEnum extends Migration {
    
    public function up() {
        $this->updateFeasibilityStatusEnum();
    }
    
    public function down() {
        $this->revertFeasibilityStatusEnum();
    }
    
    /**
     * Update feasibility_status enum to include approval workflow statuses
     * Requirements: 10.6, 10.7, 11.3, 11.5
     * 
     * New status values added:
     * - pending_contractor_review: Feasibility submitted, awaiting contractor review
     * - contractor_approved: Approved by Contractor Admin/Manager (10.7)
     * - contractor_rejected: Rejected by Contractor Admin/Manager (10.6)
     * - adv_approved: Final approval by ADV (11.3)
     * - adv_rejected: Rejected by ADV (11.5)
     */
    private function updateFeasibilityStatusEnum() {
        // Check if column exists
        if (!$this->columnExists('engineer_assignments', 'feasibility_status')) {
            throw new Exception("feasibility_status column does not exist in engineer_assignments table");
        }
        
        // Modify the ENUM to include new values
        // Note: MySQL allows adding new values to ENUM without data loss
        $sql = "ALTER TABLE `engineer_assignments` 
                MODIFY COLUMN `feasibility_status` ENUM(
                    'pending_eta',
                    'eta_submitted',
                    'ada_submitted',
                    'feasibility_completed',
                    'pending_contractor_review',
                    'contractor_approved',
                    'contractor_rejected',
                    'adv_approved',
                    'adv_rejected'
                ) DEFAULT 'pending_eta' 
                COMMENT 'Feasibility workflow status including approval workflow'";
        
        $this->execute($sql);
    }
    
    /**
     * Revert feasibility_status enum to original values
     * Note: This will fail if any rows have the new status values
     */
    private function revertFeasibilityStatusEnum() {
        if (!$this->columnExists('engineer_assignments', 'feasibility_status')) {
            return;
        }
        
        // First, update any rows with new status values back to feasibility_completed
        $updateSql = "UPDATE `engineer_assignments` 
                      SET `feasibility_status` = 'feasibility_completed' 
                      WHERE `feasibility_status` IN (
                          'pending_contractor_review',
                          'contractor_approved',
                          'contractor_rejected',
                          'adv_approved',
                          'adv_rejected'
                      )";
        
        $this->execute($updateSql);
        
        // Now revert the ENUM
        $sql = "ALTER TABLE `engineer_assignments` 
                MODIFY COLUMN `feasibility_status` ENUM(
                    'pending_eta',
                    'eta_submitted',
                    'ada_submitted',
                    'feasibility_completed'
                ) DEFAULT 'pending_eta' 
                COMMENT 'Feasibility workflow status'";
        
        $this->execute($sql);
    }
}
