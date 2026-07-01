<?php
/**
 * Migration: Create Profile Revisions Table
 * 
 * Creates the profile_revisions table for tracking user profile changes.
 * Stores changed fields, old values, new values, and timestamps.
 * 
 * Requirements: 8.1, 8.3, 8.4
 */

require_once __DIR__ . '/../config/autoload.php';

class CreateProfileRevisionsTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Run the migration
     */
    public function up(): bool {
        try {
            // Create profile_revisions table
            $sql = "CREATE TABLE IF NOT EXISTS `profile_revisions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `changed_fields` JSON NOT NULL,
                `old_values` JSON NOT NULL,
                `new_values` JSON NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_created_at` (`created_at`),
                
                CONSTRAINT `fk_profile_revisions_user` 
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Created profile_revisions table\n";
            
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
            $sql = "DROP TABLE IF EXISTS `profile_revisions`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Dropped profile_revisions table\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $migration = new CreateProfileRevisionsTable();
    
    $action = $argv[1] ?? $_GET['action'] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
