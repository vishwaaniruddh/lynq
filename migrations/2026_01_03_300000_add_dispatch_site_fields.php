<?php
/**
 * Migration: Add Site and Material Request Fields to Dispatches
 * 
 * Adds site_id and material_request_id to dispatches table to track
 * which site the dispatch is for and which material request it fulfills.
 */

require_once __DIR__ . '/../config/database.php';

class AddDispatchSiteFields {
    
    private $db;
    
    public function up() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        
        // Add site_id and material_request_id columns
        $sql = "ALTER TABLE `dispatches` 
            ADD COLUMN `site_id` INT NULL AFTER `to_warehouse_id`,
            ADD COLUMN `material_request_id` INT NULL AFTER `site_id`,
            ADD INDEX `idx_site_id` (`site_id`),
            ADD INDEX `idx_material_request_id` (`material_request_id`)";
        
        if (!$this->db->query($sql)) {
            // Columns might already exist, try adding them individually
            $this->db->query("ALTER TABLE `dispatches` ADD COLUMN `site_id` INT NULL AFTER `to_warehouse_id`");
            $this->db->query("ALTER TABLE `dispatches` ADD COLUMN `material_request_id` INT NULL AFTER `site_id`");
            $this->db->query("ALTER TABLE `dispatches` ADD INDEX `idx_site_id` (`site_id`)");
            $this->db->query("ALTER TABLE `dispatches` ADD INDEX `idx_material_request_id` (`material_request_id`)");
        }
        
        echo "Added site_id and material_request_id columns to dispatches table\n";
    }
    
    public function down() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        
        $sql = "ALTER TABLE `dispatches` 
            DROP INDEX IF EXISTS `idx_site_id`,
            DROP INDEX IF EXISTS `idx_material_request_id`,
            DROP COLUMN IF EXISTS `site_id`,
            DROP COLUMN IF EXISTS `material_request_id`";
        
        $this->db->query($sql);
        echo "Removed site_id and material_request_id columns from dispatches table\n";
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    $migration = new AddDispatchSiteFields();
    $migration->up();
}
