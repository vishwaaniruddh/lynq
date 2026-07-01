<?php
/**
 * Add Material Request Module Permissions Migration
 * Adds permissions for the Material Request Module
 * 
 * Requirements: 8.1, 8.2, 8.3
 * - ADV users: full access to material masters and requests
 * - Contractor users: view access to material requests for delegated sites
 * - Engineer users: view access and receipt confirmation for assigned sites
 */

require_once __DIR__ . '/Migration.php';

class AddMaterialRequestPermissions extends Migration {
    
    public function up() {
        $this->insertMaterialRequestPermissions();
        $this->assignPermissionsToRoles();
    }
    
    public function down() {
        // Remove material request permissions from role_permissions
        $this->execute("DELETE FROM `role_permissions` WHERE `permission_id` IN (
            SELECT `id` FROM `permissions` WHERE `name` LIKE 'inventory.material_masters.%' OR `name` LIKE 'inventory.material_requests.%'
        )");
        // Remove material request permissions
        $this->execute("DELETE FROM `permissions` WHERE `name` LIKE 'inventory.material_masters.%' OR `name` LIKE 'inventory.material_requests.%'");
    }
    
    private function insertMaterialRequestPermissions() {
        $permissions = [
            // Material Masters permissions (ADV only)
            ['inventory.material_masters.view', 'inventory', 'material_masters.view', 'View material masters', 1],
            ['inventory.material_masters.create', 'inventory', 'material_masters.create', 'Create material masters', 1],
            ['inventory.material_masters.edit', 'inventory', 'material_masters.edit', 'Edit material masters', 1],
            ['inventory.material_masters.delete', 'inventory', 'material_masters.delete', 'Delete material masters', 1],
            
            // Material Requests permissions
            ['inventory.material_requests.view', 'inventory', 'material_requests.view', 'View material requests', 0],
            ['inventory.material_requests.create', 'inventory', 'material_requests.create', 'Create material requests', 1],
            ['inventory.material_requests.approve', 'inventory', 'material_requests.approve', 'Approve material requests', 1],
            ['inventory.material_requests.receive', 'inventory', 'material_requests.receive', 'Confirm material receipt', 0],
        ];
        
        foreach ($permissions as $perm) {
            $name = $this->db->real_escape_string($perm[0]);
            $module = $this->db->real_escape_string($perm[1]);
            $action = $this->db->real_escape_string($perm[2]);
            $description = $this->db->real_escape_string($perm[3]);
            $isAdvOnly = (int)$perm[4];
            
            $this->execute("INSERT IGNORE INTO `permissions` (`name`, `module`, `action`, `description`, `is_adv_only`) 
                           VALUES ('$name', '$module', '$action', '$description', $isAdvOnly)");
        }
    }
    
    private function assignPermissionsToRoles() {
        // Assign all material request permissions to Super Admin
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Super Admin' 
            AND (p.name LIKE 'inventory.material_masters.%' OR p.name LIKE 'inventory.material_requests.%')
        ");
        
        // Assign ADV Admin material request permissions (full management)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'ADV Admin' 
            AND p.name IN (
                'inventory.material_masters.view',
                'inventory.material_masters.create',
                'inventory.material_masters.edit',
                'inventory.material_masters.delete',
                'inventory.material_requests.view',
                'inventory.material_requests.create',
                'inventory.material_requests.approve'
            )
        ");
        
        // Assign Contractor Admin material request permissions (view only)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Contractor Admin' 
            AND p.name IN (
                'inventory.material_requests.view'
            )
        ");
        
        // Assign Engineer material request permissions (view and receive)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Engineer' 
            AND p.name IN (
                'inventory.material_requests.view',
                'inventory.material_requests.receive'
            )
        ");
    }
}
