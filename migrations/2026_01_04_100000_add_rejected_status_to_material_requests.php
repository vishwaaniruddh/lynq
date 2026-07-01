<?php
/**
 * Add Rejected Status to Material Requests Migration
 * Adds rejected status and related columns to material_requests table
 * 
 * Requirements: 1.2 - ADV users can reject material requests
 */

require_once __DIR__ . '/Migration.php';

class AddRejectedStatusToMaterialRequests extends Migration {
    
    public function up() {
        // Modify status ENUM to include 'rejected'
        $sql = "ALTER TABLE `material_requests` 
                MODIFY COLUMN `status` ENUM('requested', 'approved', 'rejected', 'dispatched', 'received') DEFAULT 'requested'";
        $this->execute($sql);
        
        // Add rejected_by column
        $sql = "ALTER TABLE `material_requests` 
                ADD COLUMN `rejected_by` INT NULL COMMENT 'User who rejected' AFTER `approved_at`";
        $this->execute($sql);
        
        // Add rejected_at column
        $sql = "ALTER TABLE `material_requests` 
                ADD COLUMN `rejected_at` TIMESTAMP NULL AFTER `rejected_by`";
        $this->execute($sql);
        
        // Add foreign key for rejected_by
        $sql = "ALTER TABLE `material_requests` 
                ADD CONSTRAINT `fk_material_requests_rejected_by` 
                FOREIGN KEY (`rejected_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
        $this->execute($sql);
        
        // Add index for rejected status queries
        $sql = "ALTER TABLE `material_requests` 
                ADD INDEX `idx_rejected_by` (`rejected_by`)";
        $this->execute($sql);
    }
    
    public function down() {
        // Remove foreign key
        $sql = "ALTER TABLE `material_requests` 
                DROP FOREIGN KEY `fk_material_requests_rejected_by`";
        $this->execute($sql);
        
        // Remove index
        $sql = "ALTER TABLE `material_requests` 
                DROP INDEX `idx_rejected_by`";
        $this->execute($sql);
        
        // Remove rejected_at column
        $sql = "ALTER TABLE `material_requests` 
                DROP COLUMN `rejected_at`";
        $this->execute($sql);
        
        // Remove rejected_by column
        $sql = "ALTER TABLE `material_requests` 
                DROP COLUMN `rejected_by`";
        $this->execute($sql);
        
        // Revert status ENUM
        $sql = "ALTER TABLE `material_requests` 
                MODIFY COLUMN `status` ENUM('requested', 'approved', 'dispatched', 'received') DEFAULT 'requested'";
        $this->execute($sql);
    }
}
