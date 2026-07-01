<?php
/**
 * Migration: Create API Access Log Table
 * Tracks API endpoint access for auditing and monitoring
 */

require_once __DIR__ . '/Migration.php';

class CreateApiAccessLogMigration extends Migration {
    
    public function up() {
        $sql = "CREATE TABLE IF NOT EXISTS api_access_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            endpoint VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            params TEXT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NULL,
            response_code INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_endpoint (endpoint),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        return $this->execute($sql);
    }
    
    public function down() {
        return $this->execute("DROP TABLE IF EXISTS api_access_log");
    }
}

// Run migration if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    require_once __DIR__ . '/../config/autoload.php';
    
    $migration = new CreateApiAccessLogMigration();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        echo "Rolling back API access log migration...\n";
        $migration->down();
        echo "Done.\n";
    } else {
        echo "Running API access log migration...\n";
        $migration->up();
        echo "Done.\n";
    }
}
