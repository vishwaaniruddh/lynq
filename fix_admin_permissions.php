<?php
/**
 * Fix Admin Permissions Script
 * Ensures the Super Admin role has all permissions
 */

require_once __DIR__ . '/config/autoload.php';

echo "=== Fixing Admin Permissions ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Get Super Admin role
    $stmt = $db->query("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1");
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        echo "Super Admin role not found!\n";
        exit(1);
    }
    
    $roleId = $role['id'];
    echo "Found Super Admin role (ID: $roleId)\n";
    
    // Get all permissions
    $stmt = $db->query("SELECT id, name FROM permissions");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($permissions) . " permissions\n";
    
    // Clear existing role permissions for Super Admin
    $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);
    echo "Cleared existing role permissions\n";
    
    // Assign all permissions to Super Admin
    $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    $count = 0;
    foreach ($permissions as $perm) {
        $stmt->execute([$roleId, $perm['id']]);
        $count++;
        echo "  Assigned: {$perm['name']}\n";
    }
    
    echo "\nAssigned $count permissions to Super Admin role\n";
    echo "\n=== Done ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
