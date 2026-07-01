<?php
/**
 * Create Router IP Bindings Table Migration
 * Creates the router_ip_bindings table for permanent router-to-IP associations
 * 
 * Requirements: 5.1, 6.2
 * - Permanent bindings between routers and IP_Master records
 * - Tracks configuration details (timestamp, user, notes)
 * - Supports unbinding with reason tracking
 */

require_once __DIR__ . '/Migration.php';

class CreateRouterIPBindingsTable extends Migration {
    
    public function up() {
        $this->createRouterIPBindingsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `router_ip_bindings`");
    }
    
    /**
     * Create router_ip_bindings table
     * Requirements: 5.1, 6.2
     */
    private function createRouterIPBindingsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `router_ip_bindings` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `router_serial_number` VARCHAR(100) NOT NULL COMMENT 'Serial number of the configured router',
            `ip_master_id` INT NOT NULL COMMENT 'Reference to ip_master table',
            `configured_by` INT NOT NULL COMMENT 'User ID who performed the configuration',
            `configured_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the configuration was completed',
            `notes` TEXT NULL COMMENT 'Optional notes about the configuration',
            `status` ENUM('active', 'unbound') DEFAULT 'active' COMMENT 'Binding status',
            
            -- Unbind tracking columns (Requirement 6.2)
            `unbound_by` INT NULL COMMENT 'User ID who unbound the IP',
            `unbound_at` TIMESTAMP NULL COMMENT 'When the IP was unbound',
            `unbind_reason` TEXT NULL COMMENT 'Reason for unbinding',
            
            -- Foreign keys
            FOREIGN KEY (`ip_master_id`) REFERENCES `ip_master`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`configured_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`unbound_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            
            -- Index for router serial number queries
            INDEX `idx_router_serial` (`router_serial_number`),
            
            -- Index for status filtering
            INDEX `idx_status` (`status`),
            
            -- Index for IP master queries
            INDEX `idx_ip_master` (`ip_master_id`),
            
            -- Unique constraint for active router bindings (one active binding per router)
            UNIQUE KEY `unique_active_router` (`router_serial_number`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Router IP Bindings table for permanent router-to-IP associations'";
        
        $this->execute($sql);
    }
}
