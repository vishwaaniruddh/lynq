<?php
/**
 * Add Approval Status to Feasibility Checks Migration
 * Adds approval_status column to feasibility_checks table for approval workflow
 * 
 * Requirements: 10.6, 10.7, 11.3, 11.5
 */

require_once __DIR__ . '/Migration.php';

class AddApprovalStatusToFeasibilityChecks extends Migration {
    
    public function up() {
        $this->addApprovalStatusColumn();
    }
    
    public function down() {
        $this->removeApprovalStatusColumn();
    }
    
    /**
     * Add approval_status column to feasibility_checks table
     * Requirements: 10.6, 10.7, 11.3, 11.5
     * 
     * Status values:
     * - pending_contractor_review: Initial status after feasibility submission
     * - contractor_approved: Approved by Contractor Admin/Manager (10.7)
     * - contractor_rejected: Rejected by Contractor Admin/Manager (10.6)
     * - adv_approved: Final approval by ADV (11.3)
     * - adv_rejected: Rejected by ADV (11.5)
     */
    private function addApprovalStatusColumn() {
        // Check if column already exists
        if ($this->columnExists('feasibility_checks', 'approval_status')) {
            return;
        }
        
        $sql = "ALTER TABLE `feasibility_checks` 
                ADD COLUMN `approval_status` ENUM(
                    'pending_contractor_review',
                    'contractor_approved',
                    'contractor_rejected',
                    'adv_approved',
                    'adv_rejected'
                ) DEFAULT 'pending_contractor_review' 
                COMMENT 'Approval workflow status'
                AFTER `status`";
        
        $this->execute($sql);
        
        // Add index for approval_status for filtering
        $indexSql = "ALTER TABLE `feasibility_checks` ADD INDEX `idx_approval_status` (`approval_status`)";
        $this->execute($indexSql);
    }
    
    /**
     * Remove approval_status column
     */
    private function removeApprovalStatusColumn() {
        if (!$this->columnExists('feasibility_checks', 'approval_status')) {
            return;
        }
        
        // Drop index first
        $dropIndexSql = "ALTER TABLE `feasibility_checks` DROP INDEX `idx_approval_status`";
        try {
            $this->execute($dropIndexSql);
        } catch (Exception $e) {
            // Index might not exist, continue
        }
        
        $sql = "ALTER TABLE `feasibility_checks` DROP COLUMN `approval_status`";
        $this->execute($sql);
    }
}
