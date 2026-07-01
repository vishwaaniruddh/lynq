<?php
/**
 * Create Feasibility Checks Table Migration
 * Creates the feasibility_checks table for comprehensive site feasibility assessments
 * 
 * Requirements: 4.4, 5.1-5.6, 6.5, 7.4
 */

require_once __DIR__ . '/Migration.php';

class CreateFeasibilityChecksTable extends Migration {
    
    public function up() {
        $this->createFeasibilityChecksTable();
    }
    
    public function down() {
        $this->execute("DROP TABLE IF EXISTS `feasibility_checks`");
    }
    
    /**
     * Create feasibility_checks table
     * Requirements: 4.4, 5.1-5.6, 6.5, 7.4
     * 
     * Sections:
     * - ATM Information (5.1)
     * - Network Information (5.2)
     * - Power Infrastructure (5.3)
     * - Electrical Measurements (5.4)
     * - Site Access (5.5)
     * - Environmental (5.6)
     * - Remarks (7.4)
     * - Image paths (6.5)
     */
    private function createFeasibilityChecksTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `feasibility_checks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `assignment_id` INT NOT NULL COMMENT 'Reference to engineer_assignments',
            `site_id` INT NOT NULL COMMENT 'Reference to sites table',
            
            -- ATM Information (Requirement 5.1)
            `no_of_atm` INT DEFAULT 0 COMMENT 'Number of ATMs at site',
            `atm_id_1` VARCHAR(100) NULL COMMENT 'First ATM ID',
            `atm_id_2` VARCHAR(100) NULL COMMENT 'Second ATM ID',
            `atm_id_3` VARCHAR(100) NULL COMMENT 'Third ATM ID',
            `atm_1_status` VARCHAR(50) NULL COMMENT 'Status of first ATM',
            `atm_2_status` VARCHAR(50) NULL COMMENT 'Status of second ATM',
            `atm_3_status` VARCHAR(50) NULL COMMENT 'Status of third ATM',
            
            -- Network Information (Requirement 5.2)
            `operator` VARCHAR(100) NULL COMMENT 'Primary network operator',
            `signal_status` VARCHAR(50) NULL COMMENT 'Primary signal status',
            `operator_2` VARCHAR(100) NULL COMMENT 'Secondary network operator',
            `signal_status_2` VARCHAR(50) NULL COMMENT 'Secondary signal status',
            `backroom_network_remark` TEXT NULL COMMENT 'Network remarks',
            
            -- Power Infrastructure (Requirement 5.3)
            `ups_available` VARCHAR(10) NULL COMMENT 'UPS availability (yes/no)',
            `no_of_ups` INT NULL COMMENT 'Number of UPS units',
            `ups_battery_backup` VARCHAR(50) NULL COMMENT 'UPS battery backup duration',
            `ups_working_1` VARCHAR(50) NULL COMMENT 'First UPS working status',
            `ups_working_2` VARCHAR(50) NULL COMMENT 'Second UPS working status',
            `ups_working_3` VARCHAR(50) NULL COMMENT 'Third UPS working status',
            `power_socket_availability` VARCHAR(50) NULL COMMENT 'Power socket availability',
            `power_socket_availability_ups` VARCHAR(50) NULL COMMENT 'UPS power socket availability',
            
            -- Electrical Measurements (Requirement 5.4)
            `earthing` VARCHAR(50) NULL COMMENT 'Earthing status',
            `earthing_voltage` VARCHAR(50) NULL COMMENT 'Earthing voltage reading',
            `power_fluctuation_en` VARCHAR(50) NULL COMMENT 'Power fluctuation E-N reading',
            `power_fluctuation_pe` VARCHAR(50) NULL COMMENT 'Power fluctuation P-E reading',
            `power_fluctuation_pn` VARCHAR(50) NULL COMMENT 'Power fluctuation P-N reading',
            `frequent_power_cut` VARCHAR(10) NULL COMMENT 'Frequent power cut (yes/no)',
            `frequent_power_cut_from` TIME NULL COMMENT 'Power cut start time',
            `frequent_power_cut_to` TIME NULL COMMENT 'Power cut end time',
            `frequent_power_cut_remark` TEXT NULL COMMENT 'Power cut remarks',
            
            -- Site Access (Requirement 5.5)
            `em_lock_available` VARCHAR(10) NULL COMMENT 'EM lock availability (yes/no)',
            `em_lock_password` VARCHAR(100) NULL COMMENT 'EM lock password',
            `password_received` VARCHAR(10) NULL COMMENT 'Password received status (yes/no)',
            `backroom_key_name` VARCHAR(100) NULL COMMENT 'Backroom key holder name',
            `backroom_key_number` VARCHAR(50) NULL COMMENT 'Backroom key holder contact',
            `backroom_key_status` VARCHAR(50) NULL COMMENT 'Backroom key status',
            
            -- Environmental (Requirement 5.6)
            `antenna_routing_detail` TEXT NULL COMMENT 'Antenna routing details',
            `router_antenna_position` VARCHAR(100) NULL COMMENT 'Router/antenna position',
            `router_position` VARCHAR(100) NULL COMMENT 'Router position',
            `nearest_shop_name` VARCHAR(200) NULL COMMENT 'Nearest shop name',
            `nearest_shop_number` VARCHAR(50) NULL COMMENT 'Nearest shop contact',
            `nearest_shop_distance` VARCHAR(50) NULL COMMENT 'Distance to nearest shop',
            `backroom_disturbing_material` VARCHAR(10) NULL COMMENT 'Disturbing material present (yes/no)',
            `backroom_disturbing_material_remark` TEXT NULL COMMENT 'Disturbing material remarks',
            
            -- Remarks (Requirement 7.4)
            `remarks` TEXT NULL COMMENT 'General remarks (max 2000 chars)',
            
            -- Image paths (Requirement 6.5)
            `backroom_network_snap` VARCHAR(500) NULL COMMENT 'Network snapshot image path',
            `router_antenna_snap` VARCHAR(500) NULL COMMENT 'Router/antenna snapshot path',
            `antenna_routing_snap` VARCHAR(500) NULL COMMENT 'Antenna routing snapshot path',
            `ups_available_snap` VARCHAR(500) NULL COMMENT 'UPS availability snapshot path',
            `no_of_ups_snap` VARCHAR(500) NULL COMMENT 'UPS count snapshot path',
            `ups_working_snap` VARCHAR(500) NULL COMMENT 'UPS working snapshot path',
            `power_socket_availability_snap` VARCHAR(500) NULL COMMENT 'Power socket snapshot path',
            `earthing_snap` VARCHAR(500) NULL COMMENT 'Earthing snapshot path',
            `power_fluctuation_snap` VARCHAR(500) NULL COMMENT 'Power fluctuation snapshot path',
            `remarks_snap` VARCHAR(500) NULL COMMENT 'Remarks snapshot path',
            
            -- Audit fields
            `status` ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Record status',
            `created_by` INT NOT NULL COMMENT 'User ID who created the record',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            
            -- Constraints
            UNIQUE KEY `unique_assignment_check` (`assignment_id`),
            INDEX `idx_site` (`site_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_by` (`created_by`),
            FOREIGN KEY (`assignment_id`) REFERENCES `engineer_assignments`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
}
