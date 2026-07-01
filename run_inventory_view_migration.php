<?php
/**
 * Run Inventory View Permissions Migration
 * This script adds the missing .view permissions for the inventory module
 */

require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/migrations/Migration.php';
require_once __DIR__ . '/migrations/2024_12_29_900000_add_inventory_view_permissions.php';

echo "Running Inventory View Permissions Migration...\n";

try {
    $migration = new AddInventoryViewPermissions();
    $migration->up();
    echo "Migration completed successfully!\n";
    
    // Verify permissions were added
    $db = DatabaseConfig::getInstance()->getConnection();
    $result = $db->query("SELECT name FROM permissions WHERE name LIKE 'inventory.%.view' ORDER BY name");
    
    echo "\nInventory view permissions in database:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['name'] . "\n";
    }
    
    // Check role assignments
    echo "\nRole assignments for inventory.warehouses.view:\n";
    $result = $db->query("
        SELECT r.name as role_name 
        FROM roles r 
        JOIN role_permissions rp ON r.id = rp.role_id 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE p.name = 'inventory.warehouses.view'
    ");
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['role_name'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
