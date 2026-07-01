<?php
/**
 * Run Installation Module Migrations
 * 
 * Creates all tables required for the Installation Module:
 * - installations: Main installation records with all section data
 * - installation_material_receipts: Material receipt confirmations
 * - installation_checkpoints: Section-wise approval status tracking
 * - installation_section_remarks: Review comments and history
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();

echo "Running Installation Module Migrations...\n";
echo "==========================================\n\n";

// Migration 1: Create installations table
echo "Creating installations table...\n";
$sql1 = "CREATE TABLE IF NOT EXISTS `installations` (
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
    `router_fixed_snaps` VARCHAR(1000) NULL COMMENT 'Router fixed photo paths (comma-separated)',
    `router_status` ENUM('working', 'notWorking') NULL COMMENT 'Router working status',
    `router_status_remarks` TEXT NULL COMMENT 'Router status remarks',
    `router_status_snaps` VARCHAR(1000) NULL COMMENT 'Router status photo paths',

    -- Adaptor Section (Requirements 5.1-5.3)
    `adaptor_installed` ENUM('yes', 'no') NULL COMMENT 'Adaptor installed status',
    `adaptor_snaps` VARCHAR(1000) NULL COMMENT 'Adaptor installed photo paths',
    `adaptor_status` ENUM('working', 'notWorking') NULL COMMENT 'Adaptor working status',
    `adaptor_status_remarks` TEXT NULL COMMENT 'Adaptor status remarks',
    `adaptor_status_snaps` VARCHAR(1000) NULL COMMENT 'Adaptor status photo paths',
    
    -- LAN Cable Section (Requirements 6.1-6.3)
    `lan_cable_installed` ENUM('yes', 'no') NULL COMMENT 'LAN cable installed status',
    `lan_cable_install_remark` TEXT NULL COMMENT 'LAN cable installation remarks',
    `lan_cable_install_snap` VARCHAR(1000) NULL COMMENT 'LAN cable installation photo paths',
    `lan_cable_status` ENUM('working', 'notWorking') NULL COMMENT 'LAN cable working status',
    `lan_cable_status_not_working_reasons` TEXT NULL COMMENT 'LAN cable not working reasons',
    `lan_cable_status_remark` TEXT NULL COMMENT 'LAN cable status remarks',
    `lan_cable_status_snap` VARCHAR(1000) NULL COMMENT 'LAN cable status photo paths',
    
    -- Antenna Section (Requirements 7.1-7.3)
    `antenna_installed` ENUM('yes', 'no') NULL COMMENT 'Antenna installed status',
    `antenna_remarks` TEXT NULL COMMENT 'Antenna installation remarks',
    `antenna_snaps` VARCHAR(1000) NULL COMMENT 'Antenna installation photo paths',
    `antenna_status` ENUM('working', 'notWorking') NULL COMMENT 'Antenna working status',
    `antenna_status_remarks` TEXT NULL COMMENT 'Antenna status remarks',
    `antenna_status_snaps` VARCHAR(1000) NULL COMMENT 'Antenna status photo paths',
    
    -- GPS Section (Requirements 8.1-8.3)
    `gps_installed` ENUM('yes', 'no') NULL COMMENT 'GPS installed status',
    `gps_remarks` TEXT NULL COMMENT 'GPS installation remarks',
    `gps_snaps` VARCHAR(1000) NULL COMMENT 'GPS installation photo paths',
    `gps_status` ENUM('working', 'notWorking') NULL COMMENT 'GPS working status',
    `gps_status_remarks` TEXT NULL COMMENT 'GPS status remarks',
    `gps_status_snaps` VARCHAR(1000) NULL COMMENT 'GPS status photo paths',
    
    -- WiFi Section (Requirements 9.1-9.3)
    `wifi_installed` ENUM('yes', 'no') NULL COMMENT 'WiFi installed status',
    `wifi_remarks` TEXT NULL COMMENT 'WiFi installation remarks',
    `wifi_snaps` VARCHAR(1000) NULL COMMENT 'WiFi installation photo paths',
    `wifi_status` ENUM('working', 'notWorking') NULL COMMENT 'WiFi working status',
    `wifi_status_remarks` TEXT NULL COMMENT 'WiFi status remarks',
    `wifi_status_snaps` VARCHAR(1000) NULL COMMENT 'WiFi status photo paths',
    
    -- Airtel SIM Section (Requirements 10.1-10.4)
    `airtel_sim_installed` ENUM('yes', 'no') NULL COMMENT 'Airtel SIM installed status',
    `airtel_sim_remarks` TEXT NULL COMMENT 'Airtel SIM installation remarks',
    `airtel_sim_snaps` VARCHAR(1000) NULL COMMENT 'Airtel SIM installation photo paths',
    `airtel_sim_status` ENUM('working', 'notWorking') NULL COMMENT 'Airtel SIM working status',
    `airtel_sim_status_remarks` TEXT NULL COMMENT 'Airtel SIM status remarks',
    `airtel_sim_status_snaps` VARCHAR(1000) NULL COMMENT 'Airtel SIM status photo paths',
    
    -- Vodafone SIM Section (Requirements 10.1-10.4)
    `vodafone_sim_installed` ENUM('yes', 'no') NULL COMMENT 'Vodafone SIM installed status',
    `vodafone_sim_remarks` TEXT NULL COMMENT 'Vodafone SIM installation remarks',
    `vodafone_sim_snaps` VARCHAR(1000) NULL COMMENT 'Vodafone SIM installation photo paths',
    `vodafone_sim_status` ENUM('working', 'notWorking') NULL COMMENT 'Vodafone SIM working status',
    `vodafone_sim_status_remarks` TEXT NULL COMMENT 'Vodafone SIM status remarks',
    `vodafone_sim_status_snaps` VARCHAR(1000) NULL COMMENT 'Vodafone SIM status photo paths',
    
    -- JIO SIM Section (Requirements 10.1-10.4)
    `jio_sim_installed` ENUM('yes', 'no') NULL COMMENT 'JIO SIM installed status',
    `jio_sim_remarks` TEXT NULL COMMENT 'JIO SIM installation remarks',
    `jio_sim_snaps` VARCHAR(1000) NULL COMMENT 'JIO SIM installation photo paths',
    `jio_sim_status` ENUM('working', 'notWorking') NULL COMMENT 'JIO SIM working status',
    `jio_sim_status_remarks` TEXT NULL COMMENT 'JIO SIM status remarks',
    `jio_sim_status_snaps` VARCHAR(1000) NULL COMMENT 'JIO SIM status photo paths',
    
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
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql1);
    echo "✓ installations table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}


// Migration 2: Create installation_material_receipts table
echo "\nCreating installation_material_receipts table...\n";
$sql2 = "CREATE TABLE IF NOT EXISTS `installation_material_receipts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `installation_id` INT NOT NULL COMMENT 'Reference to installations table',
    `confirmed_by` INT NOT NULL COMMENT 'Engineer who confirmed material receipt',
    `confirmed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When materials were confirmed received',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    UNIQUE KEY `unique_installation_receipt` (`installation_id`),
    INDEX `idx_installation` (`installation_id`),
    INDEX `idx_confirmed_by` (`confirmed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql2);
    echo "✓ installation_material_receipts table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Migration 3: Create installation_checkpoints table
echo "\nCreating installation_checkpoints table...\n";
$sql3 = "CREATE TABLE IF NOT EXISTS `installation_checkpoints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `installation_id` INT NOT NULL COMMENT 'Reference to installations table',
    `section` VARCHAR(50) NOT NULL COMMENT 'Section identifier (router_fixed, router_status, adaptor, etc.)',
    
    -- Contractor Review Status (Requirements 12.1-12.7)
    `contractor_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'Contractor review status',
    `contractor_reviewer_id` INT NULL COMMENT 'Contractor reviewer user ID',
    `contractor_reviewed_at` TIMESTAMP NULL COMMENT 'When contractor reviewed',
    
    -- ADV Review Status (Requirements 13.1-13.6)
    `adv_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'ADV review status',
    `adv_reviewer_id` INT NULL COMMENT 'ADV reviewer user ID',
    `adv_reviewed_at` TIMESTAMP NULL COMMENT 'When ADV reviewed',
    
    -- Audit fields
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    UNIQUE KEY `unique_installation_section` (`installation_id`, `section`),
    INDEX `idx_installation` (`installation_id`),
    INDEX `idx_section` (`section`),
    INDEX `idx_contractor_status` (`contractor_status`),
    INDEX `idx_adv_status` (`adv_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql3);
    echo "✓ installation_checkpoints table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Migration 4: Create installation_section_remarks table
echo "\nCreating installation_section_remarks table...\n";
$sql4 = "CREATE TABLE IF NOT EXISTS `installation_section_remarks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `installation_id` INT NOT NULL COMMENT 'Reference to installations table',
    `section` VARCHAR(50) NOT NULL COMMENT 'Section identifier',
    `reviewer_id` INT NOT NULL COMMENT 'User who performed the review',
    `reviewer_level` ENUM('contractor', 'adv') NOT NULL COMMENT 'Level of reviewer',
    `review_type` ENUM('approval', 'rejection') NOT NULL COMMENT 'Type of review action',
    `remark` TEXT NULL COMMENT 'Review remarks (required for rejections, min 10 chars)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    INDEX `idx_installation` (`installation_id`),
    INDEX `idx_section` (`section`),
    INDEX `idx_reviewer` (`reviewer_id`),
    INDEX `idx_reviewer_level` (`reviewer_level`),
    INDEX `idx_review_type` (`review_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql4);
    echo "✓ installation_section_remarks table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "Installation module migrations completed!\n";
echo "\nTables created:\n";
echo "  - installations\n";
echo "  - installation_material_receipts\n";
echo "  - installation_checkpoints\n";
echo "  - installation_section_remarks\n";

// Migration 5: Add installation permissions
echo "\nAdding installation permissions...\n";
require_once __DIR__ . '/migrations/2026_01_01_300000_add_installation_permissions.php';
$permissionMigration = new AddInstallationPermissions();
$permissionMigration->up();

// Migration 6: Create installation_notifications table
echo "\nCreating installation_notifications table...\n";
$sql6 = "CREATE TABLE IF NOT EXISTS `installation_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'User to notify',
    `notification_type` ENUM('installation_initiated', 'section_rejected', 'adv_rejected', 'contractor_rejected', 'adv_approved', 'contractor_approved') NOT NULL COMMENT 'Type of notification',
    `installation_id` INT NOT NULL COMMENT 'Reference to installations table',
    `site_id` INT NULL COMMENT 'Reference to sites table',
    `section` VARCHAR(50) NULL COMMENT 'Section identifier (for rejection notifications)',
    `title` VARCHAR(255) NOT NULL COMMENT 'Notification title',
    `message` TEXT NULL COMMENT 'Notification message',
    `is_read` BOOLEAN DEFAULT FALSE COMMENT 'Whether notification has been read',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    INDEX `idx_user_unread` (`user_id`, `is_read`),
    INDEX `idx_notification_type` (`notification_type`),
    INDEX `idx_installation` (`installation_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql6);
    echo "✓ installation_notifications table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "Running new workflow field migrations...\n";
echo "==========================================\n\n";

// Migration 7: Add delegation fields to installations table
echo "Adding delegation fields to installations table...\n";
require_once __DIR__ . '/migrations/2026_01_01_500000_add_installation_delegation_fields.php';
try {
    $delegationMigration = new AddInstallationDelegationFields();
    $delegationMigration->up();
    echo "✓ Delegation fields migration completed\n";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

// Migration 8: Add assignment fields to installations table
echo "\nAdding assignment fields to installations table...\n";
require_once __DIR__ . '/migrations/2026_01_01_500001_add_installation_assignment_fields.php';
try {
    $assignmentMigration = new AddInstallationAssignmentFields();
    $assignmentMigration->up();
    echo "✓ Assignment fields migration completed\n";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

// Migration 9: Add ETA/ADA fields to installations table
echo "\nAdding ETA/ADA fields to installations table...\n";
require_once __DIR__ . '/migrations/2026_01_01_500002_add_installation_eta_ada_fields.php';
try {
    $etaAdaMigration = new AddInstallationEtaAdaFields();
    $etaAdaMigration->up();
    echo "✓ ETA/ADA fields migration completed\n";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

// Migration 10: Update status enum
echo "\nUpdating installation status enum...\n";
require_once __DIR__ . '/migrations/2026_01_01_500003_update_installation_status_enum.php';
try {
    $statusMigration = new UpdateInstallationStatusEnum();
    $statusMigration->up();
    echo "✓ Status enum migration completed\n";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "All installation module migrations completed!\n";
echo "\nTables created:\n";
echo "  - installations\n";
echo "  - installation_material_receipts\n";
echo "  - installation_checkpoints\n";
echo "  - installation_section_remarks\n";
echo "  - installation_notifications\n";
echo "\nNew fields added to installations table:\n";
echo "  - contractor_id, delegated_by, delegated_at (delegation)\n";
echo "  - assigned_engineer_id, assigned_by, assigned_at (assignment)\n";
echo "  - eta_date, eta_submitted_at, ada_date, ada_submitted_at (ETA/ADA)\n";
echo "\nStatus enum updated with new workflow states:\n";
echo "  - pending_assignment (new default)\n";
echo "  - pending_eta\n";
echo "  - pending_ada\n";
