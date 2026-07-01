<?php
/**
 * Add Inventory Module Permissions Migration
 * Adds permissions for the ADV CRM Inventory Module
 * 
 * Requirements: 8.1, 8.2, 8.3, 8.4
 * - ADV users: full access to all warehouses, contractor allocations, repair/scrap history
 * - Contractor users: access only to inventory delegated to their company and engineers
 * - Engineers: access only to items assigned to them with limited status update capabilities
 */

require_once __DIR__ . '/Migration.php';

class AddInventoryPermissions extends Migration {
    
    public function up() {
        $this->insertInventoryPermissions();
        $this->assignPermissionsToRoles();
    }
    
    public function down() {
        // Remove inventory permissions
        $this->execute("DELETE FROM `role_permissions` WHERE `permission_id` IN (SELECT `id` FROM `permissions` WHERE `module` = 'inventory')");
        $this->execute("DELETE FROM `permissions` WHERE `module` = 'inventory'");
    }
    
    private function insertInventoryPermissions() {
        $permissions = [
            // Warehouse management
            ['inventory.warehouses.create', 'inventory', 'warehouses.create', 'Create warehouses', 1],
            ['inventory.warehouses.read', 'inventory', 'warehouses.read', 'View warehouses', 0],
            ['inventory.warehouses.update', 'inventory', 'warehouses.update', 'Update warehouses', 1],
            ['inventory.warehouses.delete', 'inventory', 'warehouses.delete', 'Delete warehouses', 1],
            ['inventory.warehouses.manage', 'inventory', 'warehouses.manage', 'Full warehouse management', 1],
            
            // Product management
            ['inventory.products.create', 'inventory', 'products.create', 'Create products', 1],
            ['inventory.products.read', 'inventory', 'products.read', 'View products', 0],
            ['inventory.products.update', 'inventory', 'products.update', 'Update products', 1],
            ['inventory.products.delete', 'inventory', 'products.delete', 'Delete products', 1],
            ['inventory.products.manage', 'inventory', 'products.manage', 'Full product management', 1],
            
            // Stock management
            ['inventory.stock.create', 'inventory', 'stock.create', 'Add stock entries', 1],
            ['inventory.stock.read', 'inventory', 'stock.read', 'View stock levels', 0],
            ['inventory.stock.update', 'inventory', 'stock.update', 'Update stock entries', 1],
            ['inventory.stock.bulk_upload', 'inventory', 'stock.bulk_upload', 'Bulk stock upload', 1],
            ['inventory.stock.manage', 'inventory', 'stock.manage', 'Full stock management', 1],
            
            // Dispatch management
            ['inventory.dispatch.create', 'inventory', 'dispatch.create', 'Create dispatches', 0],
            ['inventory.dispatch.read', 'inventory', 'dispatch.read', 'View dispatches', 0],
            ['inventory.dispatch.update', 'inventory', 'dispatch.update', 'Update dispatches', 0],
            ['inventory.dispatch.acknowledge', 'inventory', 'dispatch.acknowledge', 'Acknowledge dispatch receipt', 0],
            ['inventory.dispatch.manage', 'inventory', 'dispatch.manage', 'Full dispatch management', 1],
            
            // Transfer management
            ['inventory.transfer.create', 'inventory', 'transfer.create', 'Create transfers', 1],
            ['inventory.transfer.read', 'inventory', 'transfer.read', 'View transfers', 0],
            ['inventory.transfer.update', 'inventory', 'transfer.update', 'Update transfers', 1],
            ['inventory.transfer.manage', 'inventory', 'transfer.manage', 'Full transfer management', 1],
            
            // Asset status management
            ['inventory.assets.read', 'inventory', 'assets.read', 'View assets', 0],
            ['inventory.assets.update_status', 'inventory', 'assets.update_status', 'Update asset status', 0],
            ['inventory.assets.update_status_full', 'inventory', 'assets.update_status_full', 'Update asset status (all statuses)', 1],
            ['inventory.assets.manage', 'inventory', 'assets.manage', 'Full asset management', 1],
            
            // Repair management
            ['inventory.repairs.create', 'inventory', 'repairs.create', 'Create repair requests', 0],
            ['inventory.repairs.read', 'inventory', 'repairs.read', 'View repairs', 0],
            ['inventory.repairs.update', 'inventory', 'repairs.update', 'Update repairs', 1],
            ['inventory.repairs.complete', 'inventory', 'repairs.complete', 'Complete repairs', 1],
            ['inventory.repairs.manage', 'inventory', 'repairs.manage', 'Full repair management', 1],
            
            // Dashboard access
            ['inventory.dashboard.adv', 'inventory', 'dashboard.adv', 'Access ADV inventory dashboard', 1],
            ['inventory.dashboard.contractor', 'inventory', 'dashboard.contractor', 'Access contractor inventory dashboard', 0],
            ['inventory.dashboard.engineer', 'inventory', 'dashboard.engineer', 'Access engineer inventory dashboard', 0],
            
            // Reports and exports
            ['inventory.reports.read', 'inventory', 'reports.read', 'View inventory reports', 0],
            ['inventory.reports.export', 'inventory', 'reports.export', 'Export inventory data', 0],
            ['inventory.audit.read', 'inventory', 'audit.read', 'View inventory audit logs', 1],
            
            // Alerts management
            ['inventory.alerts.read', 'inventory', 'alerts.read', 'View inventory alerts', 0],
            ['inventory.alerts.manage', 'inventory', 'alerts.manage', 'Manage inventory alerts and thresholds', 1]
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
        // Assign all inventory permissions to Super Admin
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Super Admin' AND p.module = 'inventory'
        ");
        
        // Assign ADV Admin inventory permissions (full management)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'ADV Admin' 
            AND p.name IN (
                'inventory.warehouses.manage',
                'inventory.products.manage',
                'inventory.stock.manage',
                'inventory.dispatch.manage',
                'inventory.transfer.manage',
                'inventory.assets.manage',
                'inventory.repairs.manage',
                'inventory.dashboard.adv',
                'inventory.reports.read',
                'inventory.reports.export',
                'inventory.audit.read',
                'inventory.alerts.manage'
            )
        ");
        
        // Assign Contractor Admin inventory permissions
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Contractor Admin' 
            AND p.name IN (
                'inventory.warehouses.read',
                'inventory.products.read',
                'inventory.stock.read',
                'inventory.dispatch.create',
                'inventory.dispatch.read',
                'inventory.dispatch.acknowledge',
                'inventory.assets.read',
                'inventory.assets.update_status',
                'inventory.repairs.create',
                'inventory.repairs.read',
                'inventory.dashboard.contractor',
                'inventory.reports.read',
                'inventory.reports.export',
                'inventory.alerts.read'
            )
        ");
        
        // Assign Engineer inventory permissions (limited)
        $this->execute("
            INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
            SELECT r.id, p.id 
            FROM `roles` r 
            CROSS JOIN `permissions` p 
            WHERE r.name = 'Engineer' 
            AND p.name IN (
                'inventory.products.read',
                'inventory.dispatch.read',
                'inventory.dispatch.acknowledge',
                'inventory.assets.read',
                'inventory.assets.update_status',
                'inventory.repairs.create',
                'inventory.repairs.read',
                'inventory.dashboard.engineer',
                'inventory.alerts.read'
            )
        ");
    }
}
