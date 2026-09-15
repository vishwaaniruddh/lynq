<?php
/**
 * Migration: Create Custom Forms and Form Fields Tables
 * Feature: Custom Form Master & Builder
 */

require_once __DIR__ . '/../config/autoload.php';

class CreateCustomFormsTables {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    public function up() {
        echo "Creating custom_forms table...\n";
        $sqlForms = "CREATE TABLE IF NOT EXISTS `custom_forms` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `form_code` VARCHAR(50) NOT NULL UNIQUE,
            `form_name` VARCHAR(255) NOT NULL,
            `purpose` VARCHAR(50) NOT NULL COMMENT 'site_add, feasibility, installation, other',
            `customer_id` INT NULL COMMENT 'NULL means Global / Default for this purpose',
            `description` TEXT NULL,
            `version` INT NOT NULL DEFAULT 1,
            `status` ENUM('active', 'inactive', 'draft') NOT NULL DEFAULT 'active',
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `company_id` INT NULL,
            `created_by` INT NULL,
            `updated_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` TIMESTAMP NULL,
            INDEX `idx_cf_purpose` (`purpose`),
            INDEX `idx_cf_customer` (`customer_id`),
            INDEX `idx_cf_status` (`status`),
            INDEX `idx_cf_company` (`company_id`),
            CONSTRAINT `fk_cf_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db->executeQuery($sqlForms);
        
        echo "Creating custom_form_fields table...\n";
        $sqlFields = "CREATE TABLE IF NOT EXISTS `custom_form_fields` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `form_id` INT NOT NULL,
            `section_title` VARCHAR(150) NOT NULL DEFAULT 'General Information',
            `field_key` VARCHAR(100) NOT NULL,
            `field_label` VARCHAR(255) NOT NULL,
            `field_type` VARCHAR(50) NOT NULL COMMENT 'text, number, email, textarea, select, radio, checkbox, file, date, datetime, heading',
            `placeholder` VARCHAR(255) NULL,
            `default_value` TEXT NULL,
            `help_text` VARCHAR(255) NULL,
            `is_required` TINYINT(1) NOT NULL DEFAULT 0,
            `options_json` LONGTEXT NULL COMMENT 'JSON array of options for select, radio, checkbox',
            `validation_rules_json` LONGTEXT NULL COMMENT 'JSON object for file extensions, max size, min, max, regex',
            `grid_width` INT NOT NULL DEFAULT 12 COMMENT '12=full, 6=half, 4=third, 3=quarter',
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_cff_form_id` (`form_id`),
            INDEX `idx_cff_sort` (`sort_order`),
            INDEX `idx_cff_status` (`status`),
            CONSTRAINT `fk_cff_form` FOREIGN KEY (`form_id`) REFERENCES `custom_forms` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db->executeQuery($sqlFields);
        
        echo "Registering permissions for Forms Master...\n";
        $permissions = [
            ['name' => 'masters.forms.view', 'module' => 'masters.forms', 'action' => 'view', 'description' => 'View forms master records'],
            ['name' => 'masters.forms.create', 'module' => 'masters.forms', 'action' => 'create', 'description' => 'Create forms master records'],
            ['name' => 'masters.forms.edit', 'module' => 'masters.forms', 'action' => 'edit', 'description' => 'Edit forms master records'],
            ['name' => 'masters.forms.delete', 'module' => 'masters.forms', 'action' => 'delete', 'description' => 'Delete forms master records'],
        ];
        
        foreach ($permissions as $perm) {
            $checkSql = "SELECT id FROM `permissions` WHERE `name` = ?";
            $res = $this->db->getResults($checkSql, [$perm['name']], 's');
            if (empty($res)) {
                $insSql = "INSERT INTO `permissions` (`name`, `module`, `action`, `description`, `is_adv_only`, `created_at`) VALUES (?, ?, ?, ?, 1, NOW())";
                $this->db->executeQuery($insSql, [$perm['name'], $perm['module'], $perm['action'], $perm['description']], 'ssss');
                echo "Inserted permission: {$perm['name']}\n";
            } else {
                $updSql = "UPDATE `permissions` SET `module` = ?, `action` = ?, `is_adv_only` = 1 WHERE `id` = ?";
                $this->db->executeQuery($updSql, [$perm['module'], $perm['action'], $res[0]['id']], 'ssi');
            }
        }
        
        // Grant permissions to super admin and ADV admin roles
        $rolesSql = "SELECT id, name FROM `roles` WHERE `name` IN ('Super Admin', 'Admin', 'ADV Admin', 'Administrator')";
        $adminRoles = $this->db->getResults($rolesSql);
        
        foreach ($adminRoles as $role) {
            $roleId = $role['id'];
            foreach ($permissions as $perm) {
                $permRes = $this->db->getResults("SELECT id FROM `permissions` WHERE `name` = ?", [$perm['name']], 's');
                if (!empty($permRes)) {
                    $permId = $permRes[0]['id'];
                    $checkRp = "SELECT id FROM `role_permissions` WHERE `role_id` = ? AND `permission_id` = ?";
                    $rpRes = $this->db->getResults($checkRp, [$roleId, $permId], 'ii');
                    if (empty($rpRes)) {
                        $insRp = "INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES (?, ?, NOW())";
                        $this->db->executeQuery($insRp, [$roleId, $permId], 'ii');
                        echo "Granted {$perm['name']} to role '{$role['name']}' (ID {$roleId})\n";
                    }
                }
            }
        }
        
        echo "Migration completed successfully!\n";
    }
}

$migration = new CreateCustomFormsTables();
$migration->up();
