<?php
/**
 * Add Installation Delegation Fields Migration
 * Adds contractor_id, delegated_by, delegated_at columns to installations table
 * 
 * Requirements: 1.4 - Installation delegation creates record with correct initial status
 */

require_once __DIR__ . '/Migration.php';

class AddInstallationDelegationFields extends Migration {
    
    public function up() {
        // Check if columns already exist
        if ($this->columnExists('installations', 'contractor_id')) {
            echo "Delegation fields already exist, skipping...\n";
            return;
        }
        
        // Add contractor_id column
        $sql1 = "ALTER TABLE `installations` 
            ADD COLUMN `contractor_id` INT NULL COMMENT 'Contractor company ID for installation' AFTER `initiated_at`";
        $this->execute($sql1);
        echo "✓ Added contractor_id column\n";
        
        // Add delegated_by column
        $sql2 = "ALTER TABLE `installations` 
            ADD COLUMN `delegated_by` INT NULL COMMENT 'ADV user who delegated to contractor' AFTER `contractor_id`";
        $this->execute($sql2);
        echo "✓ Added delegated_by column\n";
        
        // Add delegated_at column
        $sql3 = "ALTER TABLE `installations` 
            ADD COLUMN `delegated_at` TIMESTAMP NULL COMMENT 'When installation was delegated to contractor' AFTER `delegated_by`";
        $this->execute($sql3);
        echo "✓ Added delegated_at column\n";
        
        // Add foreign key for contractor_id to companies table
        $sql4 = "ALTER TABLE `installations` 
            ADD CONSTRAINT `fk_installations_contractor` 
            FOREIGN KEY (`contractor_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL";
        $this->execute($sql4);
        echo "✓ Added foreign key for contractor_id\n";
        
        // Add foreign key for delegated_by to users table
        $sql5 = "ALTER TABLE `installations` 
            ADD CONSTRAINT `fk_installations_delegated_by` 
            FOREIGN KEY (`delegated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
        $this->execute($sql5);
        echo "✓ Added foreign key for delegated_by\n";
        
        // Add index on contractor_id
        $sql6 = "ALTER TABLE `installations` 
            ADD INDEX `idx_contractor` (`contractor_id`)";
        $this->execute($sql6);
        echo "✓ Added index on contractor_id\n";
    }
    
    public function down() {
        // Drop foreign keys first
        $this->execute("ALTER TABLE `installations` DROP FOREIGN KEY `fk_installations_contractor`");
        $this->execute("ALTER TABLE `installations` DROP FOREIGN KEY `fk_installations_delegated_by`");
        
        // Drop index
        $this->execute("ALTER TABLE `installations` DROP INDEX `idx_contractor`");
        
        // Drop columns
        $this->execute("ALTER TABLE `installations` DROP COLUMN `contractor_id`");
        $this->execute("ALTER TABLE `installations` DROP COLUMN `delegated_by`");
        $this->execute("ALTER TABLE `installations` DROP COLUMN `delegated_at`");
    }
}
