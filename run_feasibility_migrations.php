<?php
/**
 * Run Feasibility Module Migrations
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();

echo "Running Feasibility Module Migrations...\n";
echo "==========================================\n\n";

// Migration 1: Create feasibility_eta table
echo "Creating feasibility_eta table...\n";
$sql1 = "CREATE TABLE IF NOT EXISTS `feasibility_eta` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `assignment_id` INT NOT NULL,
    `eta_datetime` DATETIME NOT NULL,
    `submitted_by` INT NOT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_current` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_assignment` (`assignment_id`),
    INDEX `idx_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql1);
    echo "✓ feasibility_eta table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Migration 2: Create feasibility_ada table
echo "\nCreating feasibility_ada table...\n";
$sql2 = "CREATE TABLE IF NOT EXISTS `feasibility_ada` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `assignment_id` INT NOT NULL,
    `ada_datetime` DATETIME NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `submitted_by` INT NOT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_assignment_ada` (`assignment_id`),
    INDEX `idx_assignment` (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql2);
    echo "✓ feasibility_ada table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Migration 3: Create feasibility_checks table
echo "\nCreating feasibility_checks table...\n";
$sql3 = "CREATE TABLE IF NOT EXISTS `feasibility_checks` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `assignment_id` INT NOT NULL,
    `site_id` INT NOT NULL,
    
    -- ATM Information
    `no_of_atm` INT DEFAULT 0,
    `atm_id_1` VARCHAR(100),
    `atm_id_2` VARCHAR(100),
    `atm_id_3` VARCHAR(100),
    `atm_1_status` VARCHAR(50),
    `atm_2_status` VARCHAR(50),
    `atm_3_status` VARCHAR(50),
    
    -- Network Information
    `operator` VARCHAR(100),
    `signal_status` VARCHAR(50),
    `operator_2` VARCHAR(100),
    `signal_status_2` VARCHAR(50),
    `backroom_network_remark` TEXT,
    
    -- Power Infrastructure
    `ups_available` VARCHAR(10),
    `no_of_ups` INT,
    `ups_battery_backup` VARCHAR(50),
    `ups_working_1` VARCHAR(50),
    `ups_working_2` VARCHAR(50),
    `ups_working_3` VARCHAR(50),
    `power_socket_availability` VARCHAR(50),
    `power_socket_availability_ups` VARCHAR(50),
    
    -- Electrical Measurements
    `earthing` VARCHAR(50),
    `earthing_voltage` VARCHAR(50),
    `power_fluctuation_en` VARCHAR(50),
    `power_fluctuation_pe` VARCHAR(50),
    `power_fluctuation_pn` VARCHAR(50),
    `frequent_power_cut` VARCHAR(10),
    `frequent_power_cut_from` TIME,
    `frequent_power_cut_to` TIME,
    `frequent_power_cut_remark` TEXT,
    
    -- Site Access
    `em_lock_available` VARCHAR(10),
    `em_lock_password` VARCHAR(100),
    `password_received` VARCHAR(10),
    `backroom_key_name` VARCHAR(100),
    `backroom_key_number` VARCHAR(50),
    `backroom_key_status` VARCHAR(50),
    
    -- Environmental
    `antenna_routing_detail` TEXT,
    `router_antenna_position` VARCHAR(100),
    `router_position` VARCHAR(100),
    `nearest_shop_name` VARCHAR(200),
    `nearest_shop_number` VARCHAR(50),
    `nearest_shop_distance` VARCHAR(50),
    `backroom_disturbing_material` VARCHAR(10),
    `backroom_disturbing_material_remark` TEXT,
    
    -- Remarks
    `remarks` TEXT,
    
    -- Image paths
    `backroom_network_snap` VARCHAR(500),
    `router_antenna_snap` VARCHAR(500),
    `antenna_routing_snap` VARCHAR(500),
    `ups_available_snap` VARCHAR(500),
    `no_of_ups_snap` VARCHAR(500),
    `ups_working_snap` VARCHAR(500),
    `power_socket_availability_snap` VARCHAR(500),
    `earthing_snap` VARCHAR(500),
    `power_fluctuation_snap` VARCHAR(500),
    `remarks_snap` VARCHAR(500),
    
    -- Audit
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_assignment_check` (`assignment_id`),
    INDEX `idx_site` (`site_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql3);
    echo "✓ feasibility_checks table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Migration 4: Add feasibility_status column to engineer_assignments
echo "\nAdding feasibility_status column to engineer_assignments...\n";

// Check if column exists
$checkColumn = $db->getResults("SHOW COLUMNS FROM engineer_assignments LIKE 'feasibility_status'");
if (empty($checkColumn)) {
    $sql4 = "ALTER TABLE `engineer_assignments` 
             ADD COLUMN `feasibility_status` ENUM('pending_eta', 'eta_submitted', 'ada_submitted', 'feasibility_completed') 
             DEFAULT 'pending_eta' AFTER `status`";
    
    try {
        $db->getConnection()->query($sql4);
        echo "✓ feasibility_status column added\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ feasibility_status column already exists\n";
}

echo "\n==========================================\n";
echo "Feasibility migrations completed!\n";

// Migration 5: Add feasibility permissions
echo "\n==========================================\n";
echo "Running Feasibility Permissions Migration...\n";
echo "==========================================\n\n";

require_once __DIR__ . '/migrations/2024_12_31_200000_add_feasibility_permissions.php';

try {
    $permissionMigration = new AddFeasibilityPermissions();
    $permissionMigration->up();
    echo "✓ Feasibility permissions added\n";
} catch (Exception $e) {
    echo "✗ Error adding permissions: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "Running Approval Workflow Migrations...\n";
echo "==========================================\n\n";

// Migration 6: Create feasibility_reviews table
echo "Creating feasibility_reviews table...\n";
$sql6 = "CREATE TABLE IF NOT EXISTS `feasibility_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `feasibility_id` INT NOT NULL COMMENT 'Reference to feasibility_checks',
    `reviewer_id` INT NOT NULL COMMENT 'User ID of the reviewer',
    `reviewer_role` ENUM('contractor_admin', 'contractor_manager', 'adv') NOT NULL COMMENT 'Role of the reviewer',
    `review_type` ENUM('approval', 'rejection') NOT NULL COMMENT 'Type of review action',
    `rejection_type` ENUM('overall', 'section_specific') NULL COMMENT 'Type of rejection (null if approval)',
    `rejected_sections` JSON NULL COMMENT 'Array of section names that were rejected',
    `reason` TEXT NULL COMMENT 'Required for rejections, min 10 characters',
    `comments` TEXT NULL COMMENT 'Optional comments for approvals',
    `reviewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When the review was submitted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_feasibility` (`feasibility_id`),
    INDEX `idx_reviewer` (`reviewer_id`),
    INDEX `idx_review_type` (`review_type`),
    INDEX `idx_reviewed_at` (`reviewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->getConnection()->query($sql6);
    echo "✓ feasibility_reviews table created\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Migration 7: Add approval_status column to feasibility_checks
echo "\nAdding approval_status column to feasibility_checks...\n";
$checkApprovalColumn = $db->getResults("SHOW COLUMNS FROM feasibility_checks LIKE 'approval_status'");
if (empty($checkApprovalColumn)) {
    $sql7 = "ALTER TABLE `feasibility_checks` 
             ADD COLUMN `approval_status` ENUM(
                 'pending_contractor_review',
                 'contractor_approved',
                 'contractor_rejected',
                 'adv_approved',
                 'adv_rejected'
             ) DEFAULT 'pending_contractor_review' 
             COMMENT 'Approval workflow status'
             AFTER `status`";
    
    try {
        $db->getConnection()->query($sql7);
        echo "✓ approval_status column added to feasibility_checks\n";
        
        // Add index
        $db->getConnection()->query("ALTER TABLE `feasibility_checks` ADD INDEX `idx_approval_status` (`approval_status`)");
        echo "✓ approval_status index added\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✓ approval_status column already exists\n";
}

// Migration 8: Update feasibility_status enum in engineer_assignments
echo "\nUpdating feasibility_status enum in engineer_assignments...\n";
$sql8 = "ALTER TABLE `engineer_assignments` 
         MODIFY COLUMN `feasibility_status` ENUM(
             'pending_eta',
             'eta_submitted',
             'ada_submitted',
             'feasibility_completed',
             'pending_contractor_review',
             'contractor_approved',
             'contractor_rejected',
             'adv_approved',
             'adv_rejected'
         ) DEFAULT 'pending_eta' 
         COMMENT 'Feasibility workflow status including approval workflow'";

try {
    $db->getConnection()->query($sql8);
    echo "✓ feasibility_status enum updated with approval workflow statuses\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "All feasibility migrations completed!\n";
