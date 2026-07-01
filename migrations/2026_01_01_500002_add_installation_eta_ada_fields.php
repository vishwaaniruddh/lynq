<?php
/**
 * Add Installation ETA/ADA Fields Migration
 * Adds eta_date, eta_submitted_at, ada_date, ada_submitted_at columns to installations table
 * 
 * Requirements: 3.3 - ETA submission updates status to pending_ada
 * Requirements: 3.5 - ADA submission updates status to pending_materials
 */

require_once __DIR__ . '/Migration.php';

class AddInstallationEtaAdaFields extends Migration {
    
    public function up() {
        // Check if columns already exist
        if ($this->columnExists('installations', 'eta_date')) {
            echo "ETA/ADA fields already exist, skipping...\n";
            return;
        }
        
        // Add eta_date column
        $sql1 = "ALTER TABLE `installations` 
            ADD COLUMN `eta_date` DATE NULL COMMENT 'Estimated Time of Arrival date' AFTER `assigned_at`";
        $this->execute($sql1);
        echo "✓ Added eta_date column\n";
        
        // Add eta_submitted_at column
        $sql2 = "ALTER TABLE `installations` 
            ADD COLUMN `eta_submitted_at` TIMESTAMP NULL COMMENT 'When ETA was submitted' AFTER `eta_date`";
        $this->execute($sql2);
        echo "✓ Added eta_submitted_at column\n";
        
        // Add ada_date column
        $sql3 = "ALTER TABLE `installations` 
            ADD COLUMN `ada_date` DATE NULL COMMENT 'Actual Date of Arrival' AFTER `eta_submitted_at`";
        $this->execute($sql3);
        echo "✓ Added ada_date column\n";
        
        // Add ada_submitted_at column
        $sql4 = "ALTER TABLE `installations` 
            ADD COLUMN `ada_submitted_at` TIMESTAMP NULL COMMENT 'When ADA was submitted' AFTER `ada_date`";
        $this->execute($sql4);
        echo "✓ Added ada_submitted_at column\n";
        
        // Add index on eta_date for filtering
        $sql5 = "ALTER TABLE `installations` 
            ADD INDEX `idx_eta_date` (`eta_date`)";
        $this->execute($sql5);
        echo "✓ Added index on eta_date\n";
        
        // Add index on ada_date for filtering
        $sql6 = "ALTER TABLE `installations` 
            ADD INDEX `idx_ada_date` (`ada_date`)";
        $this->execute($sql6);
        echo "✓ Added index on ada_date\n";
    }
    
    public function down() {
        // Drop indexes
        $this->execute("ALTER TABLE `installations` DROP INDEX `idx_eta_date`");
        $this->execute("ALTER TABLE `installations` DROP INDEX `idx_ada_date`");
        
        // Drop columns
        $this->execute("ALTER TABLE `installations` DROP COLUMN `eta_date`");
        $this->execute("ALTER TABLE `installations` DROP COLUMN `eta_submitted_at`");
        $this->execute("ALTER TABLE `installations` DROP COLUMN `ada_date`");
        $this->execute("ALTER TABLE `installations` DROP COLUMN `ada_submitted_at`");
    }
}
