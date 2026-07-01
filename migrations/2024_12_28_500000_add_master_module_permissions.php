<?php
/**
 * Migration: Add Master Module Permissions
 * 
 * Adds permissions for master modules:
 * - masters.banks.view, masters.banks.create, masters.banks.edit, masters.banks.delete
 * - masters.customers.view, masters.customers.create, masters.customers.edit, masters.customers.delete
 * - masters.locations.view, masters.locations.create, masters.locations.edit, masters.locations.delete
 * 
 * Requirements: 8.1, 8.3
 */

require_once __DIR__ . '/../config/database.php';

class AddMasterModulePermissions {
    
    private $db;
    
    /**
     * Master module permissions to add
     * All are ADV-only (is_adv_only = 1)
     */
    private $permissions = [
        // Banks permissions
        [
            'name' => 'masters.banks.view',
            'module' => 'masters',
            'action' => 'banks_view',
            'description' => 'View bank records in master data',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.banks.create',
            'module' => 'masters',
            'action' => 'banks_create',
            'description' => 'Create new bank records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.banks.edit',
            'module' => 'masters',
            'action' => 'banks_edit',
            'description' => 'Edit existing bank records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.banks.delete',
            'module' => 'masters',
            'action' => 'banks_delete',
            'description' => 'Delete bank records',
            'is_adv_only' => 1
        ],
        
        // Customers permissions
        [
            'name' => 'masters.customers.view',
            'module' => 'masters',
            'action' => 'customers_view',
            'description' => 'View customer records in master data',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.customers.create',
            'module' => 'masters',
            'action' => 'customers_create',
            'description' => 'Create new customer records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.customers.edit',
            'module' => 'masters',
            'action' => 'customers_edit',
            'description' => 'Edit existing customer records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.customers.delete',
            'module' => 'masters',
            'action' => 'customers_delete',
            'description' => 'Delete customer records',
            'is_adv_only' => 1
        ],
        
        // Locations permissions (covers countries, states, zones, cities)
        [
            'name' => 'masters.locations.view',
            'module' => 'masters',
            'action' => 'locations_view',
            'description' => 'View location records (countries, states, zones, cities)',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.locations.create',
            'module' => 'masters',
            'action' => 'locations_create',
            'description' => 'Create new location records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.locations.edit',
            'module' => 'masters',
            'action' => 'locations_edit',
            'description' => 'Edit existing location records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'masters.locations.delete',
            'module' => 'masters',
            'action' => 'locations_delete',
            'description' => 'Delete location records',
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
        
        // Assign all master permissions to Super Admin role (role_id = 1)
        $this->assignToSuperAdmin($addedPermissions);
        
        // Assign all master permissions to ADV Admin role (role_id = 2)
        $this->assignToAdvAdmin($addedPermissions);
        
        echo "\nMaster module permissions migration completed successfully.\n";
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
        
        echo "Assigned master permissions to Super Admin role\n";
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
        
        echo "Assigned master permissions to ADV Admin role\n";
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
        
        echo "\nMaster module permissions rollback completed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddMasterModulePermissions();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
