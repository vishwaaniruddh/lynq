<?php
/**
 * Create Installations Table Migration
 * Creates the installations table for equipment installation tracking
 * 
 * Requirements: 3.1, 3.4, 4.1-4.5, 5.1-5.3, 6.1-6.3, 7.1-7.3, 8.1-8.3, 9.1-9.3, 10.1-10.4, 11.1-11.5
 */

require_once __DIR__ . '/Migration.php';

class CreateInstallationsTable extends Migration {
    
    public function up() {
        $this->createInstallationsTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `installations`");
    }
    
    /**
     * Create installations table
     * 
     * Sections:
     * - Site Information (3.1)
     * - Vendor/Engineer Information (3.2)
     * - Router Section (4.1-4.5)
     * - Adaptor Section (5.1-5.3)
     * - LAN Cable Section (6.1-6.3)
     * - Antenna Section (7.1-7.3)
     * - GPS Section (8.1-8.3)
     * - WiFi Section (9.1-9.3)
     * - SIM Sections - Airtel, Vodafone, JIO (10.1-10.4)
     * - Verification Section (11.1-11.5)
     * - Status and Audit fields
     */
    private function createInstallationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `installations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `site_id` INT NOT NULL COMMENT 'Reference to sites table',
            `feasibility_id` INT NOT NULL COMMENT 'Reference to feasibility_checks table',
            `initiated_by` INT NOT NULL COMMENT 'ADV user who initiated installation',
            `initiated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When installation was initiated',
            
            -- Site Information (Requirement 3.1)
            `atm_id` VARCHAR(100) NOT NULL COMMENT 'Primary ATM ID',
            `atm_id_2` VARCHAR(100) NULL COMMENT 'Second ATM ID',
            `atm_id_3` VARCHAR(100) NULL COMMENT 'Third ATM ID',
            `address` TEXT NULL COMMENT 'Site address',
            `city` VARCHAR(100) NULL COMMENT 'City name',
            `location` VARCHAR(200) NULL COMMENT 'Location details',
            `lho` VARCHAR(100) NULL COMMENT 'LHO name',
            `state` VARCHAR(100) NULL COMMENT 'State name',
            `atm_working_1` VARCHAR(50) NULL COMMENT 'First ATM working status',
            `atm_working_2` VARCHAR(50) NULL COMMENT 'Second ATM working status',
            `atm_working_3` VARCHAR(50) NULL COMMENT 'Third ATM working status',
            
            -- Vendor/Engineer Information (Requirement 3.2)
            `vendor_name` VARCHAR(200) NULL COMMENT 'Vendor company name',
            `engineer_name` VARCHAR(200) NULL COMMENT 'Engineer full name',
            `engineer_number` VARCHAR(50) NULL COMMENT 'Engineer contact number',
            
            -- Router Section (Requirements 4.1-4.5)
            `router_serial` VARCHAR(100) NULL COMMENT 'Router serial number',
            `router_make` VARCHAR(100) NULL COMMENT 'Router manufacturer',
            `router_model` VARCHAR(100) NULL COMMENT 'Router model',
            `router_fixed` ENUM('yes', 'no') NULL COMMENT 'Router fixed status',
            `router_fixed_remarks` TEXT NULL COMMENT 'Router fixed remarks',
            `router_fixed_snaps` TEXT NULL COMMENT 'Router fixed photo paths (comma-separated)',
            `router_status` ENUM('working', 'notWorking') NULL COMMENT 'Router working status',
            `router_status_remarks` TEXT NULL COMMENT 'Router status remarks',
            `router_status_snaps` TEXT NULL COMMENT 'Router status photo paths',

            -- Adaptor Section (Requirements 5.1-5.3)
            `adaptor_installed` ENUM('yes', 'no') NULL COMMENT 'Adaptor installed status',
            `adaptor_snaps` TEXT NULL COMMENT 'Adaptor installed photo paths',
            `adaptor_status` ENUM('working', 'notWorking') NULL COMMENT 'Adaptor working status',
            `adaptor_status_remarks` TEXT NULL COMMENT 'Adaptor status remarks',
            `adaptor_status_snaps` TEXT NULL COMMENT 'Adaptor status photo paths',
            
            -- LAN Cable Section (Requirements 6.1-6.3)
            `lan_cable_installed` ENUM('yes', 'no') NULL COMMENT 'LAN cable installed status',
            `lan_cable_install_remark` TEXT NULL COMMENT 'LAN cable installation remarks',
            `lan_cable_install_snap` TEXT NULL COMMENT 'LAN cable installation photo paths',
            `lan_cable_status` ENUM('working', 'notWorking') NULL COMMENT 'LAN cable working status',
            `lan_cable_status_not_working_reasons` TEXT NULL COMMENT 'LAN cable not working reasons',
            `lan_cable_status_remark` TEXT NULL COMMENT 'LAN cable status remarks',
            `lan_cable_status_snap` TEXT NULL COMMENT 'LAN cable status photo paths',
            
            -- Antenna Section (Requirements 7.1-7.3)
            `antenna_installed` ENUM('yes', 'no') NULL COMMENT 'Antenna installed status',
            `antenna_remarks` TEXT NULL COMMENT 'Antenna installation remarks',
            `antenna_snaps` TEXT NULL COMMENT 'Antenna installation photo paths',
            `antenna_status` ENUM('working', 'notWorking') NULL COMMENT 'Antenna working status',
            `antenna_status_remarks` TEXT NULL COMMENT 'Antenna status remarks',
            `antenna_status_snaps` TEXT NULL COMMENT 'Antenna status photo paths',
            
            -- GPS Section (Requirements 8.1-8.3)
            `gps_installed` ENUM('yes', 'no') NULL COMMENT 'GPS installed status',
            `gps_remarks` TEXT NULL COMMENT 'GPS installation remarks',
            `gps_snaps` TEXT NULL COMMENT 'GPS installation photo paths',
            `gps_status` ENUM('working', 'notWorking') NULL COMMENT 'GPS working status',
            `gps_status_remarks` TEXT NULL COMMENT 'GPS status remarks',
            `gps_status_snaps` TEXT NULL COMMENT 'GPS status photo paths',
            
            -- WiFi Section (Requirements 9.1-9.3)
            `wifi_installed` ENUM('yes', 'no') NULL COMMENT 'WiFi installed status',
            `wifi_remarks` TEXT NULL COMMENT 'WiFi installation remarks',
            `wifi_snaps` TEXT NULL COMMENT 'WiFi installation photo paths',
            `wifi_status` ENUM('working', 'notWorking') NULL COMMENT 'WiFi working status',
            `wifi_status_remarks` TEXT NULL COMMENT 'WiFi status remarks',
            `wifi_status_snaps` TEXT NULL COMMENT 'WiFi status photo paths',
            
            -- Airtel SIM Section (Requirements 10.1-10.4)
            `airtel_sim_installed` ENUM('yes', 'no') NULL COMMENT 'Airtel SIM installed status',
            `airtel_sim_remarks` TEXT NULL COMMENT 'Airtel SIM installation remarks',
            `airtel_sim_snaps` TEXT NULL COMMENT 'Airtel SIM installation photo paths',
            `airtel_sim_status` ENUM('working', 'notWorking') NULL COMMENT 'Airtel SIM working status',
            `airtel_sim_status_remarks` TEXT NULL COMMENT 'Airtel SIM status remarks',
            `airtel_sim_status_snaps` TEXT NULL COMMENT 'Airtel SIM status photo paths',
            
            -- Vodafone SIM Section (Requirements 10.1-10.4)
            `vodafone_sim_installed` ENUM('yes', 'no') NULL COMMENT 'Vodafone SIM installed status',
            `vodafone_sim_remarks` TEXT NULL COMMENT 'Vodafone SIM installation remarks',
            `vodafone_sim_snaps` TEXT NULL COMMENT 'Vodafone SIM installation photo paths',
            `vodafone_sim_status` ENUM('working', 'notWorking') NULL COMMENT 'Vodafone SIM working status',
            `vodafone_sim_status_remarks` TEXT NULL COMMENT 'Vodafone SIM status remarks',
            `vodafone_sim_status_snaps` TEXT NULL COMMENT 'Vodafone SIM status photo paths',
            
            -- JIO SIM Section (Requirements 10.1-10.4)
            `jio_sim_installed` ENUM('yes', 'no') NULL COMMENT 'JIO SIM installed status',
            `jio_sim_remarks` TEXT NULL COMMENT 'JIO SIM installation remarks',
            `jio_sim_snaps` TEXT NULL COMMENT 'JIO SIM installation photo paths',
            `jio_sim_status` ENUM('working', 'notWorking') NULL COMMENT 'JIO SIM working status',
            `jio_sim_status_remarks` TEXT NULL COMMENT 'JIO SIM status remarks',
            `jio_sim_status_snaps` TEXT NULL COMMENT 'JIO SIM status photo paths',
            
            -- Verification Section (Requirements 11.1-11.5)
            `signature_image` VARCHAR(500) NULL COMMENT 'Digital signature image path',
            `vendor_stamp` VARCHAR(500) NULL COMMENT 'Vendor stamp image path',
            
            -- Status
            `status` ENUM(
                'pending_materials',
                'materials_received',
                'in_progress',
                'submitted',
                'pending_contractor_review',
                'contractor_approved',
                'contractor_rejected',
                'adv_approved',
                'adv_rejected'
            ) DEFAULT 'pending_materials' COMMENT 'Installation workflow status',
            
            -- Audit fields
            `created_by` INT NOT NULL COMMENT 'User ID who created the record',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            `submitted_by` INT NULL COMMENT 'Engineer who submitted the installation',
            `submitted_at` TIMESTAMP NULL COMMENT 'When installation was submitted',
            
            -- Constraints
            UNIQUE KEY `unique_site_installation` (`site_id`),
            INDEX `idx_feasibility` (`feasibility_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_initiated_by` (`initiated_by`),
            INDEX `idx_created_by` (`created_by`),
            FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`feasibility_id`) REFERENCES `feasibility_checks`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`initiated_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
