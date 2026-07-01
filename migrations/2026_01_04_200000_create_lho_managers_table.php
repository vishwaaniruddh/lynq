<?php
/**
 * Migration: Create LHO Managers Junction Table
 * 
 * Creates the lho_managers table for managing many-to-many relationships
 * between LHOs and ADV users (managers/contact persons)
 * 
 * Requirements: 4.1, 4.2 - Data integrity for LHO-manager relationships
 */

require_once __DIR__ . '/../config/autoload.php';

class CreateLhoManagersTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Run the migration
     */
    public function up(): bool {
        try {
            // Create lho_managers junction table
            $sql = "CREATE TABLE IF NOT EXISTS `lho_managers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `lho_id` INT NOT NULL,
                `user_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `created_by` INT NULL,
                
                UNIQUE KEY `uk_lho_user` (`lho_id`, `user_id`),
                INDEX `idx_lho_id` (`lho_id`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_created_by` (`created_by`),
                
                CONSTRAINT `fk_lho_managers_lho` 
                    FOREIGN KEY (`lho_id`) REFERENCES `lhos`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_lho_managers_user` 
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_lho_managers_created_by` 
                    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Created lho_managers junction table\n";
            
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
            // Drop table
            $sql = "DROP TABLE IF EXISTS `lho_managers`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Dropped lho_managers table\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $migration = new CreateLhoManagersTable();
    
    $action = $argv[1] ?? $_GET['action'] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
