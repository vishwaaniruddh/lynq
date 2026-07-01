<?php
/**
 * Migration: Create Tasks Table
 * 
 * Creates the tasks table for the Task Checklist System.
 * Each task belongs to a user and contains title, description, completion status.
 * 
 * Requirements: 1.1, 1.3, 2.2 - Task creation, defaults, and display fields
 */

require_once __DIR__ . '/../config/autoload.php';

class CreateTasksTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Run the migration
     */
    public function up(): bool {
        try {
            // Create tasks table
            $sql = "CREATE TABLE IF NOT EXISTS `tasks` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
                `completed_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX `idx_tasks_user_id` (`user_id`),
                INDEX `idx_tasks_user_created` (`user_id`, `created_at` DESC),
                
                CONSTRAINT `fk_tasks_user` 
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Created tasks table\n";
            
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
            $sql = "DROP TABLE IF EXISTS `tasks`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Dropped tasks table\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $migration = new CreateTasksTable();
    
    $action = $argv[1] ?? $_GET['action'] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
