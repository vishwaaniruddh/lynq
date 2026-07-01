<?php
/**
 * Migration: Add Installation Module Permissions
 * 
 * Adds permissions for installation module:
 * - installation.view - View installation records
 * - installation.create - Create/initiate installation
 * - installation.edit - Edit installation form data
 * - installation.review - Review installation submissions (Contractor Admin/Manager)
 * - installation.approve - Final approval for installations (ADV)
 * 
 * **Feature: installation-module, Requirements 1.1, 2.1, 3.3, 12.1, 13.1**
 */

require_once __DIR__ . '/../config/database.php';

class AddInstallationPermissions {
    
    private $db;
    
    /**
     * Installation module permissions to add
     */
    private $permissions = [
        // Installation view permission (available to all relevant roles)
        [
            'name' => 'installation.view',
            'module' => 'installation',
            'action' => 'view',
            'description' => 'View installation records and details',
            'is_adv_only' => 0
        ],
        // Installation create permission (ADV only - initiate installation)
        // **Feature: installation-module, Requirements 1.1**
        [
            'name' => 'installation.create',
            'module' => 'installation',
            'action' => 'create',
            'description' => 'Initiate installation for sites with approved feasibility',
            'is_adv_only' => 1
        ],
        // Installation delegate permission (ADV only - delegate to contractor)
        // **Feature: installation-module, Requirements 1.1, 1.3**
        [
            'name' => 'installation.delegate',
            'module' => 'installation',
            'action' => 'delegate',
            'description' => 'Delegate installation to contractors',
            'is_adv_only' => 1
        ],
        // Installation assign permission (Contractor - assign engineer)
        // **Feature: installation-module, Requirements 2.3**
        [
            'name' => 'installation.assign',
            'module' => 'installation',
            'action' => 'assign',
            'description' => 'Assign engineers to installation sites',
            'is_adv_only' => 0
        ],
        // Installation ETA permission (Engineer - submit ETA/ADA)
        // **Feature: installation-module, Requirements 3.2**
        [
            'name' => 'installation.eta',
            'module' => 'installation',
            'action' => 'eta',
            'description' => 'Submit ETA and ADA for installation sites',
            'is_adv_only' => 0
        ],
        // Installation edit permission (Engineer - fill form)
        // **Feature: installation-module, Requirements 2.1, 3.3**
        [
            'name' => 'installation.edit',
            'module' => 'installation',
            'action' => 'edit',
            'description' => 'Edit installation form data and confirm materials',
            'is_adv_only' => 0
        ],
        // Installation review permission (Contractor Admin/Manager)
        // **Feature: installation-module, Requirements 12.1**
        [
            'name' => 'installation.review',
            'module' => 'installation',
            'action' => 'review',
            'description' => 'Review and approve/reject installation submissions (Contractor)',
            'is_adv_only' => 0
        ],
        // Installation approve permission (ADV final approval)
        // **Feature: installation-module, Requirements 13.1**
        [
            'name' => 'installation.approve',
            'module' => 'installation',
            'action' => 'approve',
            'description' => 'Final approval for installation submissions (ADV)',
            'is_adv_only' => 1
        ],
        // Installation tracking permission (ADV)
        [
            'name' => 'installation.tracking.view',
            'module' => 'installation',
            'action' => 'tracking_view',
            'description' => 'View installation tracking dashboard',
            'is_adv_only' => 1
        ],
        // Installation export permission (ADV)
        [
            'name' => 'installation.tracking.export',
            'module' => 'installation',
            'action' => 'tracking_export',
            'description' => 'Export installation data to Excel',
            'is_adv_only' => 1
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function up() {
        $advPermissions = [];
        $engineerPermissions = [];
        $contractorReviewPermissions = [];
        $viewPermissions = [];
        
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
                echo "Added permission: {$perm['name']}\n";
            } else {
                $permissionId = $existing['id'];
                echo "Permission already exists: {$perm['name']}\n";
            }
            
            // Categorize permissions for role assignment
            if ($perm['is_adv_only']) {
                $advPermissions[] = $permissionId;
            }
            
            // View permission goes to all roles
            if ($perm['name'] === 'installation.view') {
                $viewPermissions[] = $permissionId;
            }
            
            // Edit and ETA permissions for engineers
            // **Feature: installation-module, Requirements 3.2**
            if ($perm['name'] === 'installation.edit' || $perm['name'] === 'installation.eta') {
                $engineerPermissions[] = $permissionId;
            }
            
            // Review and assign permissions for contractor admin/manager
            // **Feature: installation-module, Requirements 2.3**
            if ($perm['name'] === 'installation.review' || $perm['name'] === 'installation.assign') {
                $contractorReviewPermissions[] = $permissionId;
            }
        }
        
        // Assign ADV permissions to Super Admin and ADV Admin roles
        $this->assignToSuperAdmin(array_merge($advPermissions, $viewPermissions));
        $this->assignToAdvAdmin(array_merge($advPermissions, $viewPermissions));
        
        // Assign engineer permissions to Engineer role
        $this->assignToEngineer(array_merge($engineerPermissions, $viewPermissions));
        
        // Assign contractor review permissions to Contractor Admin and Contractor Manager roles
        $this->assignToContractorAdmin(array_merge($contractorReviewPermissions, $viewPermissions));
        $this->assignToContractorManager(array_merge($contractorReviewPermissions, $viewPermissions));
        
        echo "\nInstallation module permissions migration completed successfully.\n";
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
        
        echo "Assigned installation permissions to Super Admin role\n";
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
        
        echo "Assigned installation permissions to ADV Admin role\n";
    }
    
    /**
     * Assign permissions to Engineer role
     */
    private function assignToEngineer($permissionIds) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'Engineer' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo "Warning: Engineer role not found\n";
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
        
        echo "Assigned installation permissions to Engineer role\n";
    }
    
    /**
     * Assign permissions to Contractor Admin role
     * **Feature: installation-module, Requirements 12.1**
     */
    private function assignToContractorAdmin($permissionIds) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'Contractor Admin' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo "Warning: Contractor Admin role not found\n";
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
        
        echo "Assigned installation review permissions to Contractor Admin role\n";
    }
    
    /**
     * Assign permissions to Contractor Manager role
     * **Feature: installation-module, Requirements 12.1**
     */
    private function assignToContractorManager($permissionIds) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'Contractor Manager' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            echo "Warning: Contractor Manager role not found\n";
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
        
        echo "Assigned installation review permissions to Contractor Manager role\n";
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
        
        echo "\nInstallation module permissions rollback completed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddInstallationPermissions();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
