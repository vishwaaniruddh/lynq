<?php
/**
 * Migration: Add description column to product_categories table
 */

require_once __DIR__ . '/../config/autoload.php';

class AddDescriptionToProductCategoriesMigration {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    public function up() {
        echo "Adding description column to product_categories table...\n";
        
        // Check if column already exists
        $checkSql = "SHOW COLUMNS FROM product_categories LIKE 'description'";
        $existing = $this->db->getResults($checkSql, [], '');
        
        if (empty($existing)) {
            $sql = "ALTER TABLE product_categories ADD COLUMN `description` TEXT NULL AFTER `name`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            echo "  Added description column.\n";
        } else {
            echo "  Description column already exists.\n";
        }
        
        echo "Migration completed.\n";
        return true;
    }
    
    public function down() {
        echo "Removing description column from product_categories table...\n";
        
        $sql = "ALTER TABLE product_categories DROP COLUMN `description`";
        $stmt = $this->db->executeQuery($sql, [], '');
        $stmt->close();
        
        echo "Migration completed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $migration = new AddDescriptionToProductCategoriesMigration();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
