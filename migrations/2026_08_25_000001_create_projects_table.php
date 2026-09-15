<?php
/**
 * Migration: Create Projects Master Table & Permissions
 */

require_once __DIR__ . '/../config/autoload.php';

class CreateProjectsTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    public function up() {
        echo "Creating projects table...\n";
        $sql = "CREATE TABLE IF NOT EXISTS `projects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `code` VARCHAR(50) NULL,
            `description` TEXT NULL,
            `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
            `created_by` INT NULL,
            `updated_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` TIMESTAMP NULL,
            INDEX `idx_proj_name` (`name`),
            INDEX `idx_proj_status` (`status`),
            INDEX `idx_proj_deleted` (`deleted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db->executeQuery($sql);
        
        echo "Registering permissions for Projects Master...\n";
        $permissions = [
            ['name' => 'masters.projects.view', 'module' => 'masters.projects', 'action' => 'view', 'description' => 'View project records'],
            ['name' => 'masters.projects.create', 'module' => 'masters.projects', 'action' => 'create', 'description' => 'Create project records'],
            ['name' => 'masters.projects.edit', 'module' => 'masters.projects', 'action' => 'edit', 'description' => 'Edit project records'],
            ['name' => 'masters.projects.delete', 'module' => 'masters.projects', 'action' => 'delete', 'description' => 'Delete project records (soft delete)'],
        ];
        
        foreach ($permissions as $perm) {
            $checkSql = "SELECT id FROM `permissions` WHERE `name` = ?";
            $res = $this->db->getResults($checkSql, [$perm['name']], 's');
            if (empty($res)) {
                $insSql = "INSERT INTO `permissions` (`name`, `module`, `action`, `description`, `is_adv_only`, `created_at`) VALUES (?, ?, ?, ?, 1, NOW())";
                $this->db->executeQuery($insSql, [$perm['name'], $perm['module'], $perm['action'], $perm['description']], 'ssss');
                echo "Inserted permission: {$perm['name']}\n";
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
        
        // Seed sample projects if empty
        $cnt = $this->db->getResults("SELECT COUNT(*) as total FROM `projects` WHERE `deleted_at` IS NULL");
        if ((int)($cnt[0]['total'] ?? 0) === 0) {
            echo "Seeding initial sample projects...\n";
            $sampleProjects = [
                ['name' => 'XTPL', 'code' => 'XTPL', 'description' => 'XTPL Core Project']
            ];
            
            foreach ($sampleProjects as $sp) {
                $this->db->executeQuery(
                    "INSERT INTO `projects` (`name`, `code`, `description`, `status`, `created_at`) VALUES (?, ?, ?, 1, NOW())",
                    [$sp['name'], $sp['code'], $sp['description']],
                    'sss'
                );
                echo "Seeded project: {$sp['name']}\n";
            }
        }
        
        echo "Projects migration completed successfully!\n";
    }
}

$migration = new CreateProjectsTable();
$migration->up();
