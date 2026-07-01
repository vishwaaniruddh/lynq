<?php
/**
 * Update Installation Status Enum Migration
 * Adds new statuses: pending_assignment, pending_eta, pending_ada
 * Updates default status to pending_assignment
 * 
 * Requirements: 1.4 - Installation delegation creates record with status "pending_assignment"
 * Requirements: 2.4 - Engineer assignment updates status to "pending_eta"
 * Requirements: 3.3 - ETA submission updates status to "pending_ada"
 * Requirements: 3.5 - ADA submission updates status to "pending_materials"
 */

require_once __DIR__ . '/Migration.php';

class UpdateInstallationStatusEnum extends Migration {
    
    public function up() {
        // Modify the status enum to include new workflow states
        // New workflow: pending_assignment -> pending_eta -> pending_ada -> pending_materials -> ...
        $sql = "ALTER TABLE `installations` 
            MODIFY COLUMN `status` ENUM(
                'pending_assignment',
                'pending_eta',
                'pending_ada',
                'pending_materials',
                'materials_received',
                'in_progress',
                'submitted',
                'pending_contractor_review',
                'contractor_approved',
                'contractor_rejected',
                'adv_approved',
                'adv_rejected'
            ) DEFAULT 'pending_assignment' COMMENT 'Installation workflow status'";
        
        $this->execute($sql);
        echo "✓ Updated status enum with new workflow states\n";
        echo "  - Added: pending_assignment (new default)\n";
        echo "  - Added: pending_eta\n";
        echo "  - Added: pending_ada\n";
    }
    
    public function down() {
        // Revert to original status enum
        // First update any records with new statuses to pending_materials
        $this->execute("UPDATE `installations` SET `status` = 'pending_materials' 
            WHERE `status` IN ('pending_assignment', 'pending_eta', 'pending_ada')");
        
        // Then modify the enum back
        $sql = "ALTER TABLE `installations` 
            MODIFY COLUMN `status` ENUM(
                'pending_materials',
                'materials_received',
                'in_progress',
                'submitted',
                'pending_contractor_review',
                'contractor_approved',
                'contractor_rejected',
                'adv_approved',
                'adv_rejected'
            ) DEFAULT 'pending_materials' COMMENT 'Installation workflow status'";
        
        $this->execute($sql);
    }
}
