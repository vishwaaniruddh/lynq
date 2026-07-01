<?php
/**
 * Migration: Add location ID columns to customers table
 * 
 * Adds country_id, state_id, city_id columns for proper location references
 */

require_once __DIR__ . '/../config/autoload.php';

class AddLocationIdsToCustomersMigration {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    public function up() {
        echo "Adding location ID columns to customers table...\n";
        
        // Check and add country_id
        if (!$this->columnExists('customers', 'country_id')) {
            $sql = "ALTER TABLE customers ADD COLUMN `country_id` INT NULL AFTER `postal_code`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            echo "  Added country_id column.\n";
        } else {
            echo "  country_id column already exists.\n";
        }
        
        // Check and add state_id
        if (!$this->columnExists('customers', 'state_id')) {
            $sql = "ALTER TABLE customers ADD COLUMN `state_id` INT NULL AFTER `country_id`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            echo "  Added state_id column.\n";
        } else {
            echo "  state_id column already exists.\n";
        }
        
        // Check and add city_id
        if (!$this->columnExists('customers', 'city_id')) {
            $sql = "ALTER TABLE customers ADD COLUMN `city_id` INT NULL AFTER `state_id`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            echo "  Added city_id column.\n";
        } else {
            echo "  city_id column already exists.\n";
        }
        
        // Add indexes
        $this->addIndexIfNotExists('customers', 'idx_country_id', 'country_id');
        $this->addIndexIfNotExists('customers', 'idx_state_id', 'state_id');
        $this->addIndexIfNotExists('customers', 'idx_city_id', 'city_id');
        
        echo "Migration completed.\n";
        return true;
    }
    
    private function columnExists($table, $column) {
        $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
        $result = $this->db->getResults($sql, [], '');
        return !empty($result);
    }
    
    private function addIndexIfNotExists($table, $indexName, $column) {
        $sql = "SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'";
        $result = $this->db->getResults($sql, [], '');
        
        if (empty($result)) {
            try {
                $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` (`$column`)";
                $stmt = $this->db->executeQuery($sql, [], '');
                $stmt->close();
                echo "  Added index $indexName.\n";
            } catch (Exception $e) {
                echo "  Warning: Could not add index $indexName: " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function down() {
        echo "Removing location ID columns from customers table...\n";
        
        $columns = ['city_id', 'state_id', 'country_id'];
        foreach ($columns as $column) {
            if ($this->columnExists('customers', $column)) {
                $sql = "ALTER TABLE customers DROP COLUMN `$column`";
                $stmt = $this->db->executeQuery($sql, [], '');
                $stmt->close();
                echo "  Removed $column column.\n";
            }
        }
        
        echo "Migration completed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $migration = new AddLocationIdsToCustomersMigration();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
