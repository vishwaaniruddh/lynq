<?php
/**
 * Migration: Add Profile Fields to Users Table
 * 
 * Adds new profile fields to the users table for enhanced user profile functionality.
 * Fields: contact_number, address, date_of_birth, sex, profile_picture, bio
 * 
 * Requirements: 2.3, 3.2, 4.3, 5.2, 6.4, 7.3
 */

require_once __DIR__ . '/../config/autoload.php';

class AddProfileFieldsToUsers {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Run the migration
     */
    public function up(): bool {
        try {
            $conn = $this->db->getConnection();
            
            // Check and add contact_number column
            if (!$this->columnExists('users', 'contact_number')) {
                $sql = "ALTER TABLE `users` ADD COLUMN `contact_number` VARCHAR(20) NULL AFTER `last_name`";
                $conn->query($sql);
                echo "✓ Added contact_number column\n";
            } else {
                echo "- contact_number column already exists\n";
            }
            
            // Check and add address column
            if (!$this->columnExists('users', 'address')) {
                $sql = "ALTER TABLE `users` ADD COLUMN `address` TEXT NULL AFTER `contact_number`";
                $conn->query($sql);
                echo "✓ Added address column\n";
            } else {
                echo "- address column already exists\n";
            }
            
            // Check and add date_of_birth column
            if (!$this->columnExists('users', 'date_of_birth')) {
                $sql = "ALTER TABLE `users` ADD COLUMN `date_of_birth` DATE NULL AFTER `address`";
                $conn->query($sql);
                echo "✓ Added date_of_birth column\n";
            } else {
                echo "- date_of_birth column already exists\n";
            }
            
            // Check and add sex column
            if (!$this->columnExists('users', 'sex')) {
                $sql = "ALTER TABLE `users` ADD COLUMN `sex` ENUM('male', 'female', 'other') NULL AFTER `date_of_birth`";
                $conn->query($sql);
                echo "✓ Added sex column\n";
            } else {
                echo "- sex column already exists\n";
            }
            
            // Check and add profile_picture column
            if (!$this->columnExists('users', 'profile_picture')) {
                $sql = "ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) NULL AFTER `sex`";
                $conn->query($sql);
                echo "✓ Added profile_picture column\n";
            } else {
                echo "- profile_picture column already exists\n";
            }
            
            // Check and add bio column
            if (!$this->columnExists('users', 'bio')) {
                $sql = "ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL AFTER `profile_picture`";
                $conn->query($sql);
                echo "✓ Added bio column\n";
            } else {
                echo "- bio column already exists\n";
            }
            
            echo "\n✓ Profile fields migration completed successfully\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Reverse the migration
     */
    public function down(): bool {
        try {
            $conn = $this->db->getConnection();
            
            // Remove columns in reverse order
            $columns = ['bio', 'profile_picture', 'sex', 'date_of_birth', 'address', 'contact_number'];
            
            foreach ($columns as $column) {
                if ($this->columnExists('users', $column)) {
                    $sql = "ALTER TABLE `users` DROP COLUMN `$column`";
                    $conn->query($sql);
                    echo "✓ Dropped $column column\n";
                }
            }
            
            echo "\n✓ Profile fields rollback completed successfully\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Check if column exists in table
     */
    private function columnExists(string $tableName, string $columnName): bool {
        $conn = $this->db->getConnection();
        $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
        return $result && $result->num_rows > 0;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $migration = new AddProfileFieldsToUsers();
    
    $action = $argv[1] ?? $_GET['action'] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
