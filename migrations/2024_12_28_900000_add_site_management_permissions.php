<?php
/**
 * Migration: Add Site Management Permissions
 * 
 * Adds permissions for site management module:
 * - sites.view - View site records
 * - sites.create - Create new site records
 * - sites.edit - Edit existing site records
 * - sites.delete - Delete site records
 * - sites.delegate - Delegate sites to contractors
 * - sites.bulk_upload - Bulk upload sites via Excel
 * 
 * Also adds permissions for delegation tracking, contractor portal, and engineer portal:
 * - delegations.view - View delegation tracking
 * - delegations.export - Export delegation data
 * - contractor.delegations.view - View delegated sites (contractor)
 * - contractor.delegations.respond - Accept/reject delegations
 * - contractor.assignments.manage - Assign sites to engineers
 * - engineer.sites.view - View assigned sites (engineer)
 * 
 * Requirements: 1.1, 2.1
 */

require_once __DIR__ . '/../config/database.php';

class AddSiteManagementPermissions {
    
    private $db;
    
    /**
     * Site management permissions to add
     */
    private $permissions = [
        // ADV Site Management permissions (ADV-only)
        [
            'name' => 'sites.view',
            'module' => 'sites',
            'action' => 'view',
            'description' => 'View site records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'sites.create',
            'module' => 'sites',
            'action' => 'create',
            'description' => 'Create new site records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'sites.edit',
            'module' => 'sites',
            'action' => 'edit',
            'description' => 'Edit existing site records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'sites.delete',
            'module' => 'sites',
            'action' => 'delete',
            'description' => 'Delete site records',
            'is_adv_only' => 1
        ],
        [
            'name' => 'sites.delegate',
            'module' => 'sites',
            'action' => 'delegate',
            'description' => 'Delegate sites to contractors',
            'is_adv_only' => 1
        ],
        [
            'name' => 'sites.bulk_upload',
            'module' => 'sites',
            'action' => 'bulk_upload',
            'description' => 'Bulk upload sites via Excel',
            'is_adv_only' => 1
        ],
        
        // ADV Delegation Tracking permissions (ADV-only)
        [
            'name' => 'delegations.view',
            'module' => 'delegations',
            'action' => 'view',
            'description' => 'View delegation tracking dashboard',
            'is_adv_only' => 1
        ],
        [
            'name' => 'delegations.export',
            'module' => 'delegations',
            'action' => 'export',
            'description' => 'Export delegation data to Excel',
            'is_adv_only' => 1
        ],
        
        // Contractor Portal permissions (not ADV-only)
        [
            'name' => 'contractor.delegations.view',
            'module' => 'contractor',
            'action' => 'delegations_view',
            'description' => 'View sites delegated to contractor',
            'is_adv_only' => 0
        ],
        [
            'name' => 'contractor.delegations.respond',
            'module' => 'contractor',
            'action' => 'delegations_respond',
            'description' => 'Accept or reject site delegations',
            'is_adv_only' => 0
        ],
        [
            'name' => 'contractor.assignments.manage',
            'module' => 'contractor',
            'action' => 'assignments_manage',
            'description' => 'Assign sites to engineers',
            'is_adv_only' => 0
        ],
        [
            'name' => 'contractor.assignments.bulk',
            'module' => 'contractor',
            'action' => 'assignments_bulk',
            'description' => 'Bulk assign sites to engineers via Excel',
            'is_adv_only' => 0
        ],
        
        // Engineer Portal permissions (not ADV-only)
        [
            'name' => 'engineer.sites.view',
            'module' => 'engineer',
            'action' => 'sites_view',
            'description' => 'View sites assigned to engineer',
            'is_adv_only' => 0
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function up() {
        $addedPermissions = [];
        $advPermissions = [];
        $contractorPermissions = [];
        $engineerPermissions = [];
        
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
                $permissionId = $existing['id'];
                $addedPermissions[] = $permissionId;
                echo "Permission already exists: {$perm['name']}\n";
            }
            
            // Categorize permissions for role assignment
            if ($perm['is_adv_only']) {
                $advPermissions[] = $permissionId;
            } elseif (strpos($perm['name'], 'contractor.') === 0) {
                $contractorPermissions[] = $permissionId;
            } elseif (strpos($perm['name'], 'engineer.') === 0) {
                $engineerPermissions[] = $permissionId;
            }
        }
        
        // Assign ADV permissions to Super Admin and ADV Admin roles
        $this->assignToSuperAdmin($advPermissions);
        $this->assignToAdvAdmin($advPermissions);
        
        // Assign contractor permissions to Contractor Admin role
        $this->assignToContractorAdmin($contractorPermissions);
        
        // Assign engineer permissions to Engineer role
        $this->assignToEngineer($engineerPermissions);
        
        echo "\nSite management permissions migration completed successfully.\n";
        return true;
    }
    
    /**
     * Assign permissions to Super Admin role
     */
    private function assignToSuperAdmin($permissionIds) {
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
        
        echo "Assigned site management permissions to Super Admin role\n";
    }
    
    /**
     * Assign permissions to ADV Admin role
     */
    private function assignToAdvAdmin($permissionIds) {
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
        
        echo "Assigned site management permissions to ADV Admin role\n";
    }
    
    /**
     * Assign permissions to Contractor Admin role
     */
    private function assignToContractorAdmin($permissionIds) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'Contractor Admin' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            // Try alternative name
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE name LIKE '%Contractor%Admin%' LIMIT 1");
            $stmt->execute();
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$role) {
            echo "Warning: Contractor Admin role not found - creating it\n";
            $stmt = $this->db->prepare("
                INSERT INTO roles (name, description, is_system_role, company_type) 
                VALUES ('Contractor Admin', 'Administrator for contractor companies', 1, 'Contractor')
            ");
            $stmt->execute();
            $roleId = $this->db->lastInsertId();
            echo "Created Contractor Admin role with ID: $roleId\n";
        } else {
            $roleId = $role['id'];
        }
        
        foreach ($permissionIds as $permId) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$roleId, $permId]);
        }
        
        echo "Assigned contractor permissions to Contractor Admin role\n";
    }
    
    /**
     * Assign permissions to Engineer role
     */
    private function assignToEngineer($permissionIds) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'Engineer' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo "Warning: Engineer role not found - creating it\n";
            $stmt = $this->db->prepare("
                INSERT INTO roles (name, description, is_system_role, company_type) 
                VALUES ('Engineer', 'Field engineer for site feasibility', 1, 'Contractor')
            ");
            $stmt->execute();
            $roleId = $this->db->lastInsertId();
            echo "Created Engineer role with ID: $roleId\n";
        } else {
            $roleId = $role['id'];
        }
        
        foreach ($permissionIds as $permId) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$roleId, $permId]);
        }
        
        echo "Assigned engineer permissions to Engineer role\n";
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
        
        echo "\nSite management permissions rollback completed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddSiteManagementPermissions();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
