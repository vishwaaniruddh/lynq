<?php
/**
 * Create Master Module Tables Migration
 * Creates tables for Banks, Customers, Countries, States, Zones, and Cities
 * 
 * Requirements: 1.2, 2.2, 3.2, 4.2, 5.2, 6.2
 */

require_once __DIR__ . '/Migration.php';

class CreateMasterModuleTables extends Migration {
    
    public function up() {
        // Create tables in order of dependencies
        $this->createBanksTable();
        $this->createCustomersTable();
        $this->createCountriesTable();
        $this->createZonesTable();
        $this->createStatesTable();
        $this->createCitiesTable();
        
        // Insert master module permissions
        $this->insertMasterPermissions();
    }
    
    public function down() {
        // Drop tables in reverse order of dependencies
        $tables = [
            'cities',
            'states',
            'zones',
            'countries',
            'customers',
            'banks'
        ];
        
        foreach ($tables as $table) {
            $this->execute("DROP TABLE IF EXISTS `$table`");
        }
        
        // Remove master module permissions
        $this->execute("DELETE FROM `permissions` WHERE `module` LIKE 'masters.%'");
    }
    
    /**
     * Create banks table
     * Requirements: 1.2
     */
    private function createBanksTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `banks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `status` TINYINT(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            UNIQUE KEY `unique_bank_name` (`name`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Create customers table
     * Requirements: 2.2
     */
    private function createCustomersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `customers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(50) NULL,
            `address` TEXT NULL,
            `city` VARCHAR(100) NULL,
            `state` VARCHAR(100) NULL,
            `country` VARCHAR(100) NULL DEFAULT 'India',
            `postal_code` VARCHAR(20) NULL,
            `status` TINYINT(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            UNIQUE KEY `unique_customer_email` (`email`),
            INDEX `idx_status` (`status`),
            INDEX `idx_name` (`name`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }

    
    /**
     * Create countries table
     * Requirements: 3.2
     */
    private function createCountriesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `countries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            UNIQUE KEY `unique_country_name` (`name`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Create zones table
     * Requirements: 5.2
     * Note: Created before states because states reference zones
     */
    private function createZonesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `zones` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            UNIQUE KEY `unique_zone_name` (`name`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Create states table
     * Requirements: 4.2
     */
    private function createStatesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `states` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `country_id` INT NOT NULL,
            `zone_id` INT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`zone_id`) REFERENCES `zones`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_state_country` (`name`, `country_id`),
            INDEX `idx_country` (`country_id`),
            INDEX `idx_zone` (`zone_id`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    /**
     * Create cities table
     * Requirements: 6.2
     */
    private function createCitiesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `cities` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `state_id` INT NOT NULL,
            `country_id` INT NOT NULL,
            `zone_id` INT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            FOREIGN KEY (`state_id`) REFERENCES `states`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`zone_id`) REFERENCES `zones`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `unique_city_state` (`name`, `state_id`),
            INDEX `idx_state` (`state_id`),
            INDEX `idx_country` (`country_id`),
            INDEX `idx_zone` (`zone_id`),
            INDEX `idx_status` (`status`),
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }

    
    /**
     * Insert master module permissions
     * Requirements: 8.1, 8.3
     */
    private function insertMasterPermissions() {
        $permissions = [
            // Bank permissions
            ['masters.banks.view', 'masters.banks', 'view', 'View bank records', 1],
            ['masters.banks.create', 'masters.banks', 'create', 'Create bank records', 1],
            ['masters.banks.edit', 'masters.banks', 'edit', 'Edit bank records', 1],
            ['masters.banks.delete', 'masters.banks', 'delete', 'Delete bank records', 1],
            
            // Customer permissions
            ['masters.customers.view', 'masters.customers', 'view', 'View customer records', 1],
            ['masters.customers.create', 'masters.customers', 'create', 'Create customer records', 1],
            ['masters.customers.edit', 'masters.customers', 'edit', 'Edit customer records', 1],
            ['masters.customers.delete', 'masters.customers', 'delete', 'Delete customer records', 1],
            
            // Location permissions (shared for countries, states, zones, cities)
            ['masters.locations.view', 'masters.locations', 'view', 'View location records', 1],
            ['masters.locations.create', 'masters.locations', 'create', 'Create location records', 1],
            ['masters.locations.edit', 'masters.locations', 'edit', 'Edit location records', 1],
            ['masters.locations.delete', 'masters.locations', 'delete', 'Delete location records', 1]
        ];
        
        foreach ($permissions as $perm) {
            $name = $this->db->real_escape_string($perm[0]);
            $module = $this->db->real_escape_string($perm[1]);
            $action = $this->db->real_escape_string($perm[2]);
            $description = $this->db->real_escape_string($perm[3]);
            $isAdvOnly = (int)$perm[4];
            
            $this->execute("INSERT IGNORE INTO `permissions` (`name`, `module`, `action`, `description`, `is_adv_only`) 
                           VALUES ('$name', '$module', '$action', '$description', $isAdvOnly)");
        }
        
        // Assign master module permissions to Super Admin role
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Super Admin' 
            AND p.module LIKE 'masters.%'
        ");
        
        // Assign master module permissions to ADV Admin role
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'ADV Admin' 
            AND p.module LIKE 'masters.%'
        ");
    }
}
