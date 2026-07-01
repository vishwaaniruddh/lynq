-- Add Inventory View Permissions
-- Run this SQL to add the missing .view permissions for the inventory module

-- Insert view permissions
INSERT IGNORE INTO `permissions` (`name`, `module`, `action`, `description`, `is_adv_only`) VALUES
('inventory.warehouses.view', 'inventory', 'warehouses.view', 'View warehouses menu', 0),
('inventory.products.view', 'inventory', 'products.view', 'View products menu', 0),
('inventory.stock.view', 'inventory', 'stock.view', 'View stock menu', 0),
('inventory.dispatch.view', 'inventory', 'dispatch.view', 'View dispatch menu', 0),
('inventory.transfers.view', 'inventory', 'transfers.view', 'View transfers menu', 1),
('inventory.assets.view', 'inventory', 'assets.view', 'View assets menu', 0),
('inventory.repairs.view', 'inventory', 'repairs.view', 'View repairs menu', 0);

-- Assign all inventory view permissions to Super Admin
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
);

-- Assign ADV Admin all inventory view permissions (full access)
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
);

-- Assign Contractor Admin inventory view permissions (limited - no stock entry, no transfers)
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
);

-- Assign Engineer inventory view permissions (most limited)
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
);

-- Verify the permissions were added
SELECT 'Inventory view permissions:' as '';
SELECT name FROM permissions WHERE name LIKE 'inventory.%.view' ORDER BY name;

-- Verify role assignments
SELECT 'Role assignments for inventory.warehouses.view:' as '';
SELECT r.name as role_name 
FROM roles r 
JOIN role_permissions rp ON r.id = rp.role_id 
JOIN permissions p ON rp.permission_id = p.id 
WHERE p.name = 'inventory.warehouses.view';
