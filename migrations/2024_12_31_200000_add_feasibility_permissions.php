<?php
/**
 * Migration: Add Feasibility Module Permissions
 * 
 * Adds permissions for feasibility module:
 * - engineer.eta.submit - Submit ETA for assigned sites
 * - engineer.ada.submit - Submit ADA with geolocation
 * - engineer.feasibility.submit - Submit feasibility check form
 * - feasibility.tracking.view - View feasibility tracking dashboard (ADV)
 * - feasibility.tracking.export - Export feasibility data to Excel (ADV)
 * 
 * **Feature: feasibility-module, Requirements 1.1, 8.1**
 */

require_once __DIR__ . '/../config/database.php';

class AddFeasibilityPermissions {
    
    private $db;
    
    /**
     * Feasibility module permissions to add
     */
    private $permissions = [
        // Engineer Feasibility permissions (not ADV-only)
        [
            'name' => 'engineer.eta.submit',
            'module' => 'engineer',
            'action' => 'eta_submit',
            'description' => 'Submit ETA for assigned sites',
            'is_adv_only' => 0
        ],
        [
            'name' => 'engineer.ada.submit',
            'module' => 'engineer',
            'action' => 'ada_submit',
            'description' => 'Submit ADA with geolocation for assigned sites',
            'is_adv_only' => 0
        ],
        [
            'name' => 'engineer.feasibility.submit',
            'module' => 'engineer',
            'action' => 'feasibility_submit',
            'description' => 'Submit feasibility check form for assigned sites',
            'is_adv_only' => 0
        ],
        
        // Contractor Feasibility Review permissions (not ADV-only)
        // **Feature: feasibility-module, Requirements 10.1**
        [
            'name' => 'feasibility.review.contractor',
            'module' => 'feasibility',
            'action' => 'review_contractor',
            'description' => 'Review and approve/reject feasibility checks (Contractor Admin/Manager)',
            'is_adv_only' => 0
        ],
        
        // ADV Feasibility Tracking permissions (ADV-only)
        [
            'name' => 'feasibility.tracking.view',
            'module' => 'feasibility',
            'action' => 'tracking_view',
            'description' => 'View feasibility tracking dashboard',
            'is_adv_only' => 1
        ],
        [
            'name' => 'feasibility.tracking.export',
            'module' => 'feasibility',
            'action' => 'tracking_export',
            'description' => 'Export feasibility data to Excel',
            'is_adv_only' => 1
        ],
        [
            'name' => 'feasibility.review.adv',
            'module' => 'feasibility',
            'action' => 'review_adv',
            'description' => 'ADV final approval for feasibility checks',
            'is_adv_only' => 1
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function up() {
        $addedPermissions = [];
        $advPermissions = [];
        $engineerPermissions = [];
        $contractorReviewPermissions = [];
        
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
            } elseif (strpos($perm['name'], 'engineer.') === 0) {
                $engineerPermissions[] = $permissionId;
            } elseif ($perm['name'] === 'feasibility.review.contractor') {
                $contractorReviewPermissions[] = $permissionId;
            }
        }
        
        // Assign ADV permissions to Super Admin and ADV Admin roles
        $this->assignToSuperAdmin($advPermissions);
        $this->assignToAdvAdmin($advPermissions);
        
        // Assign engineer permissions to Engineer role
        $this->assignToEngineer($engineerPermissions);
        
        // Assign contractor review permissions to Contractor Admin and Contractor Manager roles
        // **Feature: feasibility-module, Requirements 10.1**
        $this->assignToContractorAdmin($contractorReviewPermissions);
        $this->assignToContractorManager($contractorReviewPermissions);
        
        echo "\nFeasibility module permissions migration completed successfully.\n";
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
        
        echo "Assigned feasibility tracking permissions to Super Admin role\n";
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
        
        echo "Assigned feasibility tracking permissions to ADV Admin role\n";
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
        
        echo "Assigned feasibility permissions to Engineer role\n";
    }
    
    /**
     * Assign permissions to Contractor Admin role
     * **Feature: feasibility-module, Requirements 10.1**
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
        
        echo "Assigned feasibility review permissions to Contractor Admin role\n";
    }
    
    /**
     * Assign permissions to Contractor Manager role
     * **Feature: feasibility-module, Requirements 10.1**
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
        
        echo "Assigned feasibility review permissions to Contractor Manager role\n";
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
        
        echo "\nFeasibility module permissions rollback completed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $migration = new AddFeasibilityPermissions();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
