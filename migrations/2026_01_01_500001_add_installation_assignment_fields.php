<?php
/**
 * Add Installation Assignment Fields Migration
 * Adds assigned_engineer_id, assigned_by, assigned_at columns to installations table
 * 
 * Requirements: 2.4 - Engineer assignment updates status to pending_eta
 */

require_once __DIR__ . '/Migration.php';

class AddInstallationAssignmentFields extends Migration {
    
    public function up() {
        // Check if columns already exist
        if ($this->columnExists('installations', 'assigned_engineer_id')) {
            echo "Assignment fields already exist, skipping...\n";
            return;
        }
        
        // Add assigned_engineer_id column
        $sql1 = "ALTER TABLE `installations` 
            ADD COLUMN `assigned_engineer_id` INT NULL COMMENT 'Engineer assigned to perform installation' AFTER `delegated_at`";
        $this->execute($sql1);
        echo "✓ Added assigned_engineer_id column\n";
        
        // Add assigned_by column
        $sql2 = "ALTER TABLE `installations` 
            ADD COLUMN `assigned_by` INT NULL COMMENT 'Contractor user who assigned the engineer' AFTER `assigned_engineer_id`";
        $this->execute($sql2);
        echo "✓ Added assigned_by column\n";
        
        // Add assigned_at column
        $sql3 = "ALTER TABLE `installations` 
            ADD COLUMN `assigned_at` TIMESTAMP NULL COMMENT 'When engineer was assigned' AFTER `assigned_by`";
        $this->execute($sql3);
        echo "✓ Added assigned_at column\n";
        
        // Add foreign key for assigned_engineer_id to users table
        $sql4 = "ALTER TABLE `installations` 
            ADD CONSTRAINT `fk_installations_assigned_engineer` 
            FOREIGN KEY (`assigned_engineer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL";
        $this->execute($sql4);
        echo "✓ Added foreign key for assigned_engineer_id\n";
        
        // Add foreign key for assigned_by to users table
        $sql5 = "ALTER TABLE `installations` 
            ADD CONSTRAINT `fk_installations_assigned_by` 
            FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
        $this->execute($sql5);
        echo "✓ Added foreign key for assigned_by\n";
        
        // Add index on assigned_engineer_id
        $sql6 = "ALTER TABLE `installations` 
            ADD INDEX `idx_assigned_engineer` (`assigned_engineer_id`)";
        $this->execute($sql6);
        echo "✓ Added index on assigned_engineer_id\n";
    }
    
    public function down() {
        // Drop foreign keys first
        $this->execute("ALTER TABLE `installations` DROP FOREIGN KEY `fk_installations_assigned_engineer`");
        $this->execute("ALTER TABLE `installations` DROP FOREIGN KEY `fk_installations_assigned_by`");
        
        // Drop index
        $this->execute("ALTER TABLE `installations` DROP INDEX `idx_assigned_engineer`");
        
        // Drop columns
        $this->execute("ALTER TABLE `installations` DROP COLUMN `assigned_engineer_id`");
        $this->execute("ALTER TABLE `installations` DROP COLUMN `assigned_by`");
        $this->execute("ALTER TABLE `installations` DROP COLUMN `assigned_at`");
    }
}
