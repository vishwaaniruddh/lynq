<?php
/**
 * Migration: Create Notes Table
 * 
 * Creates the notes table for personal user notes functionality.
 * Each note belongs to a user and contains a title and content.
 * 
 * Requirements: 3.3, 8.3 - Note persistence with user association
 */

require_once __DIR__ . '/../config/autoload.php';

class CreateNotesTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Run the migration
     */
    public function up(): bool {
        try {
            // Create notes table
            $sql = "CREATE TABLE IF NOT EXISTS `notes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `title` VARCHAR(255) NOT NULL DEFAULT '',
                `content` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_updated_at` (`updated_at`),
                
                CONSTRAINT `fk_notes_user` 
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Created notes table\n";
            
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
            $sql = "DROP TABLE IF EXISTS `notes`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Dropped notes table\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $migration = new CreateNotesTable();
    
    $action = $argv[1] ?? $_GET['action'] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
