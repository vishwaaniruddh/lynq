<?php
/**
 * Run Site Management Migrations
 * Creates the sites, site_delegations, engineer_assignments, and delegation_history tables
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "Running Site Management Migrations...\n\n";

// Migration 1: Create sites table
echo "Creating sites table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `sites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `site_name` VARCHAR(255) NOT NULL,
    `lho` VARCHAR(100) NOT NULL,
    `bank_name` VARCHAR(255) NULL,
    `customer_name` VARCHAR(255) NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `country` VARCHAR(100) NOT NULL,
    `zone` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `company_id` INT NOT NULL COMMENT 'ADV company that owns the site',
    `status` ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT NOT NULL,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT NULL,
    UNIQUE KEY `unique_site_lho_company` (`site_name`, `lho`, `company_id`),
    INDEX `idx_lho` (`lho`),
    INDEX `idx_status` (`status`),
    INDEX `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($db->query($sql)) {
    echo "  Sites table created successfully.\n";
} else {
    echo "  Error creating sites table: " . $db->error . "\n";
}

// Migration 2: Create site_delegations table
echo "Creating site_delegations table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `site_delegations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `site_id` INT NOT NULL,
    `contractor_id` INT NOT NULL COMMENT 'Company ID of contractor',
    `delegated_by` INT NOT NULL COMMENT 'User ID who delegated',
    `delegated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    `rejection_notes` TEXT NULL,
    `responded_by` INT NULL,
    `responded_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_site` (`site_id`),
    INDEX `idx_contractor` (`contractor_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_delegated_at` (`delegated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($db->query($sql)) {
    echo "  Site delegations table created successfully.\n";
} else {
    echo "  Error creating site_delegations table: " . $db->error . "\n";
}

// Migration 3: Create engineer_assignments table
echo "Creating engineer_assignments table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `engineer_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `site_id` INT NOT NULL,
    `delegation_id` INT NOT NULL COMMENT 'Reference to accepted delegation',
    `engineer_id` INT NOT NULL COMMENT 'User ID of engineer',
    `assigned_by` INT NOT NULL COMMENT 'User ID who assigned',
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('assigned', 'in_progress', 'completed') DEFAULT 'assigned',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_site` (`site_id`),
    INDEX `idx_delegation` (`delegation_id`),
    INDEX `idx_engineer` (`engineer_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($db->query($sql)) {
    echo "  Engineer assignments table created successfully.\n";
} else {
    echo "  Error creating engineer_assignments table: " . $db->error . "\n";
}

// Migration 4: Create delegation_history table
echo "Creating delegation_history table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `delegation_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `delegation_id` INT NOT NULL,
    `action` ENUM('created', 'accepted', 'rejected', 'reassigned') NOT NULL,
    `performed_by` INT NOT NULL,
    `notes` TEXT NULL,
    `performed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_delegation` (`delegation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($db->query($sql)) {
    echo "  Delegation history table created successfully.\n";
} else {
    echo "  Error creating delegation_history table: " . $db->error . "\n";
}

echo "\nSite Management Migrations completed!\n";
