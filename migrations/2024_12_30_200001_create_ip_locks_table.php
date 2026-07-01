<?php
/**
 * Create IP Locks Table Migration
 * Creates the ip_locks table for temporary locking during configuration
 * 
 * Requirements: 4.1, 4.2
 * - Temporary locking during 20-minute configuration process
 * - Prevents other users from selecting locked IPs
 * - Tracks lock status (active, released, expired)
 */

require_once __DIR__ . '/Migration.php';

class CreateIPLocksTable extends Migration {
    
    public function up() {
        $this->createIPLocksTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `ip_locks`");
    }
    
    /**
     * Create ip_locks table
     * Requirements: 4.1, 4.2
     */
    private function createIPLocksTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `ip_locks` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `ip_master_id` INT NOT NULL COMMENT 'Reference to ip_master table',
            `router_serial_number` VARCHAR(100) NOT NULL COMMENT 'Serial number of router being configured',
            `locked_by` INT NOT NULL COMMENT 'User ID who acquired the lock',
            `locked_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When the lock was acquired',
            `expires_at` DATETIME NOT NULL COMMENT 'When the lock expires (locked_at + 20 minutes)',
            `status` ENUM('active', 'released', 'expired') DEFAULT 'active' COMMENT 'Current lock status',
            `released_at` DATETIME NULL COMMENT 'When the lock was released (if applicable)',
            
            -- Foreign key to ip_master
            FOREIGN KEY (`ip_master_id`) REFERENCES `ip_master`(`id`) ON DELETE CASCADE,
            
            -- Foreign key to users table
            FOREIGN KEY (`locked_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            
            -- Index for lock status queries (Requirement 4.2 - prevent other users from selecting locked IPs)
            INDEX `idx_status_expires` (`status`, `expires_at`),
            
            -- Index for querying locks by IP master
            INDEX `idx_ip_master_status` (`ip_master_id`, `status`),
            
            -- Index for querying locks by router serial number
            INDEX `idx_router_serial` (`router_serial_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='IP Locks table for temporary locking during 20-minute configuration process'";
        
        $this->execute($sql);
    }
}
