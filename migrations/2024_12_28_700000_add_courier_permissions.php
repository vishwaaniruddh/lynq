<?php
/**
 * Migration: Add Courier Module Permissions
 * 
 * Adds permissions for courier module:
 * - masters.couriers.view - View courier records
 * - masters.couriers.create - Create new courier records
 * - masters.couriers.edit - Edit existing courier records
 * - masters.couriers.delete - Delete courier records
 * 
 * Requirements: 5.1, 5.2, 5.3
 */

require_once __DIR__ . '/../config/database.php';

class AddCourierPermissions {
    
    private $db;
    
    /**
     * Courier module permissions to add
     * All are ADV-only (is_adv_only = 1)
     */
    private $permissions = [
        [
            'name' => 'masters.couriers.view',
            'module' => 'masters',
            'action' => 'couriers_view',
            'description' => 'View courier records in master data',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.couriers.create',
            'module' => 'masters',
            'action' => 'couriers_create',
            'description' => 'Create new courier records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.couriers.edit',
            'module' => 'masters',
            'action' => 'couriers_edit',
            'description' => 'Edit existing courier records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.couriers.delete',
            'module' => 'masters',
            'action' => 'couriers_delete',
            'description' => 'Delete courier records',
            'is_adv_only' => 1
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function up() {
        $addedPermissions = [];
        
        foreach ($this->permissions as $perm) {
            // Check if permission already exists
            $stmt = $this->db->prepare("SELECT id FROM permissions WHERE name = ?");
            $stmt->execute([$perm['name']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                // Add permission
                $stmt = $this->db->prepare("
                    INSERT INTO permissions (name, module, action, description, is_adv_only) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $perm['name'],
                    $perm['module'],
                    $perm['action'],
                    $perm['description'],
                    $perm['is_adv_only']
                ]);
                
                $permissionId = $this->db->lastInsertId();
                $addedPermissions[] = $permissionId;
                
                echo "Added permission: {$perm['name']}\n";
            } else {
                $addedPermissions[] = $existing['id'];
                echo "Permission already exists: {$perm['name']}\n";
            }
        }
        
        // Assign all courier permissions to Super Admin role
        $this->assignToSuperAdmin($addedPermissions);
        
        // Assign all courier permissions to ADV Admin role
        $this->assignToAdvAdmin($addedPermissions);
        
        echo "\nCourier module permissions migration completed successfully.\n";
        return true;
    }
    
    /**
     * Assign permissions to Super Admin role
     */
    private function assignToSuperAdmin($permissionIds) {
        // Get Super Admin role ID
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo "Warning: Super Admin role not found\n";
            return;
        }
        
        $roleId = $role['id'];
        
        foreach ($permissionIds as $permId) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$roleId, $permId]);
        }
        
        echo "Assigned courier permissions to Super Admin role\n";
    }
    
    /**
     * Assign permissions to ADV Admin role
     */
    private function assignToAdvAdmin($permissionIds) {
        // Get ADV Admin role ID
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'ADV Admin' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo "Warning: ADV Admin role not found\n";
            return;
        }
        
        $roleId = $role['id'];
        
        foreach ($permissionIds as $permId) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$roleId, $permId]);
        }
        
        echo "Assigned courier permissions to ADV Admin role\n";
    }
    
    public function down() {
        foreach ($this->permissions as $perm) {
            // Get permission ID
            $stmt = $this->db->prepare("SELECT id FROM permissions WHERE name = ?");
            $stmt->execute([$perm['name']]);
            $permission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($permission) {
                // Remove from role_permissions
                $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
                $stmt->execute([$permission['id']]);
                
                // Remove from company_permissions
                $stmt = $this->db->prepare("DELETE FROM company_permissions WHERE permission_id = ?");
                $stmt->execute([$permission['id']]);
                
                // Remove permission
                $stmt = $this->db->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->execute([$permission['id']]);
                
                echo "Removed permission: {$perm['name']}\n";
            }
        }
        
        echo "\nCourier module permissions rollback completed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddCourierPermissions();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
