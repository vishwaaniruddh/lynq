<?php
/**
 * Add Inventory View Permissions Migration
 * Adds .view permissions that are used by the menu system and page access checks
 * Also ensures proper role assignments for ADV Admin, Contractor Admin, and Engineer roles
 */

require_once __DIR__ . '/Migration.php';

class AddInventoryViewPermissions extends Migration {
    
    public function up() {
        $this->addViewPermissions();
        $this->assignViewPermissionsToRoles();
    }
    
    public function down() {
        // Remove view permissions
        $viewPermissions = [
            'inventory.warehouses.view',
            'inventory.products.view',
            'inventory.stock.view',
            'inventory.dispatch.view',
            'inventory.transfers.view',
            'inventory.assets.view',
            'inventory.repairs.view'
        ];
        
        $permList = "'" . implode("','", $viewPermissions) . "'";
        $this->execute("DELETE FROM `role_permissions` WHERE `permission_id` IN (SELECT `id` FROM `permissions` WHERE `name` IN ($permList))");
        $this->execute("DELETE FROM `permissions` WHERE `name` IN ($permList)");
    }
    
    private function addViewPermissions() {
        // Add .view permissions that are used by the menu system
        $permissions = [
            // Warehouse view
            ['inventory.warehouses.view', 'inventory', 'warehouses.view', 'View warehouses menu', 0],
            // Product view
            ['inventory.products.view', 'inventory', 'products.view', 'View products menu', 0],
            // Stock view
            ['inventory.stock.view', 'inventory', 'stock.view', 'View stock menu', 0],
            // Dispatch view
            ['inventory.dispatch.view', 'inventory', 'dispatch.view', 'View dispatch menu', 0],
            // Transfers view
            ['inventory.transfers.view', 'inventory', 'transfers.view', 'View transfers menu', 1],
            // Assets view
            ['inventory.assets.view', 'inventory', 'assets.view', 'View assets menu', 0],
            // Repairs view
            ['inventory.repairs.view', 'inventory', 'repairs.view', 'View repairs menu', 0]
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
    
    private function assignViewPermissionsToRoles() {
        // Assign all inventory view permissions to Super Admin
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Super Admin' 
            AND p.name IN (
                'inventory.warehouses.view',
                'inventory.products.view',
                'inventory.stock.view',
                'inventory.dispatch.view',
                'inventory.transfers.view',
                'inventory.assets.view',
                'inventory.repairs.view'
            )
        ");
        
        // Assign ADV Admin all inventory view permissions (full access)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'ADV Admin' 
            AND p.name IN (
                'inventory.warehouses.view',
                'inventory.products.view',
                'inventory.stock.view',
                'inventory.dispatch.view',
                'inventory.transfers.view',
                'inventory.assets.view',
                'inventory.repairs.view'
            )
        ");
        
        // Assign Contractor Admin inventory view permissions (limited - no stock entry, no transfers)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Contractor Admin' 
            AND p.name IN (
                'inventory.warehouses.view',
                'inventory.products.view',
                'inventory.dispatch.view',
                'inventory.assets.view',
                'inventory.repairs.view'
            )
        ");
        
        // Assign Engineer inventory view permissions (most limited)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Engineer' 
            AND p.name IN (
                'inventory.products.view',
                'inventory.dispatch.view',
                'inventory.assets.view',
                'inventory.repairs.view'
            )
        ");
    }
}
