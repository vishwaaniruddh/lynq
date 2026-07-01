<?php
/**
 * Create Core Tables Migration
 * Creates the foundational tables for the ADV CRM Users Module
 */

require_once __DIR__ . '/Migration.php';

class CreateCoreTables extends Migration {
    
    public function up() {
        $this->createCompaniesTable();
        $this->createRolesTable();
        $this->createPermissionsTable();
        $this->createUsersTable();
        $this->createRolePermissionsTable();
        $this->createCompanyPermissionsTable();
        $this->createUserSessionsTable();
        $this->createUserAuditLogTable();
        $this->createPermissionAuditLogTable();
        
        $this->insertDefaultData();
    }
    
    public function down() {
        $tables = [
            'permission_audit_log',
            'user_audit_log',
            'user_sessions',
            'company_permissions',
            'role_permissions',
            'users',
            'permissions',
            'roles',
            'companies'
        ];
        
        foreach ($tables as $table) {
            $this->execute("DROP TABLE IF EXISTS `$table`");
        }
    }
    
    private function createCompaniesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `companies` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `type` ENUM('ADV', 'CONTRACTOR') NOT NULL,
            `status` ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED') DEFAULT 'ACTIVE',
            `contact_email` VARCHAR(255),
            `contact_phone` VARCHAR(50),
            `address` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_type` (`type`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createRolesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `roles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `level` INT NOT NULL DEFAULT 1,
            `company_type` ENUM('ADV', 'CONTRACTOR', 'BOTH') NOT NULL,
            `description` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_name_company_type` (`name`, `company_type`),
            INDEX `idx_company_type` (`company_type`),
            INDEX `idx_level` (`level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createPermissionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE,
            `module` VARCHAR(50) NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `description` TEXT,
            `is_adv_only` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_module` (`module`),
            INDEX `idx_action` (`action`),
            INDEX `idx_adv_only` (`is_adv_only`),
            UNIQUE KEY `unique_module_action` (`module`, `action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `company_id` INT NOT NULL,
            `role_id` INT NOT NULL,
            `status` TINYINT(1) DEFAULT 1 COMMENT '0=inactive, 1=active, 2=locked',
            `last_login` TIMESTAMP NULL,
            `failed_login_attempts` INT DEFAULT 0,
            `locked_until` TIMESTAMP NULL,
            `password_changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT,
            INDEX `idx_company_id` (`company_id`),
            INDEX `idx_role_id` (`role_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createRolePermissionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `role_permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role_id` INT NOT NULL,
            `permission_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createCompanyPermissionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `company_permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT NOT NULL,
            `permission_id` INT NOT NULL,
            `granted_by` INT NOT NULL,
            `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `revoked_at` TIMESTAMP NULL,
            `revoked_by` INT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`revoked_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
            INDEX `idx_company_permission` (`company_id`, `permission_id`),
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createUserSessionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `session_token` VARCHAR(255) NOT NULL UNIQUE,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `expires_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_session_token` (`session_token`),
            INDEX `idx_expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createUserAuditLogTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `user_audit_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT,
            `target_user_id` INT,
            `action` VARCHAR(50) NOT NULL,
            `details` JSON,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `performed_by` INT,
            `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_target_user_id` (`target_user_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function createPermissionAuditLogTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `permission_audit_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `company_id` INT,
            `permission_id` INT,
            `action` VARCHAR(50) NOT NULL,
            `details` JSON,
            `performed_by` INT,
            `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_company_id` (`company_id`),
            INDEX `idx_permission_id` (`permission_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->execute($sql);
    }
    
    private function insertDefaultData() {
        // Insert default ADV company
        $this->execute("INSERT IGNORE INTO `companies` (`id`, `name`, `type`, `status`) VALUES (1, 'ADV Systems', 'ADV', 'ACTIVE')");
        
        // Insert default roles
        $roles = [
            ['Super Admin', 10, 'ADV', 'Full system administrator with all permissions'],
            ['ADV Admin', 8, 'ADV', 'ADV administrator with delegation capabilities'],
            ['ADV Manager', 6, 'ADV', 'ADV manager with limited administrative access'],
            ['ADV User', 4, 'ADV', 'Standard ADV user'],
            ['Contractor Admin', 7, 'CONTRACTOR', 'Contractor administrator'],
            ['Contractor Manager', 5, 'CONTRACTOR', 'Contractor manager'],
            ['Contractor User', 3, 'CONTRACTOR', 'Standard contractor user'],
            ['Engineer', 2, 'CONTRACTOR', 'Field engineer'],
            ['Viewer', 1, 'BOTH', 'Read-only access']
        ];
        
        foreach ($roles as $role) {
            $this->execute("INSERT IGNORE INTO `roles` (`name`, `level`, `company_type`, `description`) VALUES ('{$role[0]}', {$role[1]}, '{$role[2]}', '{$role[3]}')");
        }
        
        // Insert default permissions
        $permissions = [
            // User management
            ['users.create', 'users', 'create', 'Create new users', 0],
            ['users.read', 'users', 'read', 'View user information', 0],
            ['users.update', 'users', 'update', 'Update user information', 0],
            ['users.delete', 'users', 'delete', 'Delete users', 0],
            ['users.manage', 'users', 'manage', 'Full user management', 0],
            
            // Company management
            ['companies.create', 'companies', 'create', 'Create new companies', 1],
            ['companies.read', 'companies', 'read', 'View company information', 0],
            ['companies.update', 'companies', 'update', 'Update company information', 1],
            ['companies.delete', 'companies', 'delete', 'Delete companies', 1],
            ['companies.manage', 'companies', 'manage', 'Full company management', 1],
            
            // Role management
            ['roles.create', 'roles', 'create', 'Create new roles', 1],
            ['roles.read', 'roles', 'read', 'View role information', 0],
            ['roles.update', 'roles', 'update', 'Update role information', 1],
            ['roles.delete', 'roles', 'delete', 'Delete roles', 1],
            ['roles.manage', 'roles', 'manage', 'Full role management', 1],
            
            // Permission management
            ['permissions.delegate', 'permissions', 'delegate', 'Delegate permissions to contractors', 1],
            ['permissions.revoke', 'permissions', 'revoke', 'Revoke permissions from contractors', 1],
            ['permissions.read', 'permissions', 'read', 'View permission information', 0],
            ['permissions.manage', 'permissions', 'manage', 'Full permission management', 1],
            
            // System administration
            ['system.admin', 'system', 'admin', 'System administration access', 1],
            ['system.audit', 'system', 'audit', 'View audit logs', 1],
            ['system.backup', 'system', 'backup', 'System backup operations', 1],
            
            // Master data
            ['master_data.read', 'master_data', 'read', 'View master data', 1],
            ['master_data.manage', 'master_data', 'manage', 'Manage master data', 1],
            
            // Admin functions
            ['admin.dashboard', 'admin', 'dashboard', 'Access admin dashboard', 1],
            ['admin.reports', 'admin', 'reports', 'Generate admin reports', 1]
        ];
        
        foreach ($permissions as $perm) {
            $this->execute("INSERT IGNORE INTO `permissions` (`name`, `module`, `action`, `description`, `is_adv_only`) VALUES ('{$perm[0]}', '{$perm[1]}', '{$perm[2]}', '{$perm[3]}', {$perm[4]})");
        }
        
        // Assign all permissions to Super Admin role
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Super Admin'
        ");
        
        // Assign basic permissions to other ADV roles
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'ADV Admin' 
            AND p.name IN ('users.manage', 'companies.read', 'roles.read', 'permissions.delegate', 'permissions.revoke')
        ");
        
        // Assign basic permissions to contractor roles
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Contractor Admin' 
            AND p.name IN ('users.create', 'users.read', 'users.update', 'users.delete')
        ");
    }
}