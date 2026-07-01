<?php
/**
 * Create IP Master Table Migration
 * Creates the ip_master table for the IP Configuration Management module
 * 
 * Requirements: 1.1, 1.2
 * - Stores unique combinations of Network IP, Router IP, Site IP, and Subnet Mask
 * - Enforces uniqueness constraint on IP combination
 * - Tracks status (available, locked, configured)
 */

require_once __DIR__ . '/Migration.php';

class CreateIPMasterTable extends Migration {
    
    public function up() {
        $this->createIPMasterTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `ip_master`");
    }
    
    /**
     * Create ip_master table
     * Requirements: 1.1, 1.2
     */
    private function createIPMasterTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `ip_master` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `network_ip` VARCHAR(15) NOT NULL COMMENT 'Network IP address',
            `router_ip` VARCHAR(15) NOT NULL COMMENT 'Router IP address',
            `site_ip` VARCHAR(15) NOT NULL COMMENT 'Site IP address',
            `subnet_mask` VARCHAR(15) NOT NULL COMMENT 'Subnet mask',
            `status` ENUM('available', 'locked', 'configured') DEFAULT 'available' COMMENT 'Current status of IP combination',
            `created_by` INT NULL COMMENT 'User ID who created this record',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Unique constraint on IP combination to prevent duplicates (Requirement 1.2)
            UNIQUE KEY `unique_ip_combination` (`network_ip`, `router_ip`, `site_ip`, `subnet_mask`),
            
            -- Index for status filtering (for quick lookup of available IPs)
            INDEX `idx_status` (`status`),
            
            -- Foreign key to users table
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='IP Master table storing unique IP address combinations for router configuration'";
        
        $this->execute($sql);
    }
}
