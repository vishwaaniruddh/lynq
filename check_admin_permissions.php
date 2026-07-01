<?php
require_once __DIR__ . '/config/database.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "=== Checking Admin User Permissions ===\n\n";

// Find admin user
$result = $db->query("SELECT u.*, r.name as role_name, r.id as role_id, c.name as company_name, c.type as company_type 
                      FROM users u 
                      LEFT JOIN roles r ON u.role_id = r.id 
                      LEFT JOIN companies c ON u.company_id = c.id 
                      WHERE u.username = 'admin'");
$admin = $result->fetch_assoc();

if ($admin) {
    echo "Admin User Found:\n";
    echo "  ID: {$admin['id']}\n";
    echo "  Username: {$admin['username']}\n";
    echo "  Role ID: {$admin['role_id']}\n";
    echo "  Role Name: {$admin['role_name']}\n";
    echo "  Company: {$admin['company_name']} ({$admin['company_type']})\n\n";
    
    // Check role permissions
    echo "Role Permissions for role_id={$admin['role_id']}:\n";
    $result = $db->query("SELECT p.* FROM permissions p 
                          INNER JOIN role_permissions rp ON p.id = rp.permission_id 
                          WHERE rp.role_id = {$admin['role_id']}
                          ORDER BY p.module, p.action");
    
    $count = 0;
    while ($perm = $result->fetch_assoc()) {
        echo "  - {$perm['name']} ({$perm['module']}.{$perm['action']})\n";
        $count++;
    }
    
    if ($count == 0) {
        echo "  NO PERMISSIONS FOUND!\n";
    }
    echo "\nTotal permissions: $count\n";
    
    // Check if users.read permission exists
    echo "\n=== Checking users.read permission ===\n";
    $result = $db->query("SELECT * FROM permissions WHERE name = 'users.read'");
    $perm = $result->fetch_assoc();
    if ($perm) {
        echo "Permission exists: ID={$perm['id']}, Name={$perm['name']}\n";
        
        // Check if it's assigned to Super Admin role
        $result = $db->query("SELECT * FROM role_permissions WHERE role_id = 1 AND permission_id = {$perm['id']}");
        if ($result->fetch_assoc()) {
            echo "Permission IS assigned to Super Admin role (role_id=1)\n";
        } else {
            echo "Permission is NOT assigned to Super Admin role!\n";
        }
    } else {
        echo "Permission 'users.read' does NOT exist!\n";
    }
    
} else {
    echo "Admin user not found!\n";
}

echo "\n=== All Roles ===\n";
$result = $db->query("SELECT * FROM roles ORDER BY level DESC");
while ($role = $result->fetch_assoc()) {
    echo "  ID={$role['id']}, Name={$role['name']}, Level={$role['level']}, Type={$role['company_type']}\n";
}

echo "\n=== Role Permissions Count ===\n";
$result = $db->query("SELECT r.id, r.name, COUNT(rp.permission_id) as perm_count 
                      FROM roles r 
                      LEFT JOIN role_permissions rp ON r.id = rp.role_id 
                      GROUP BY r.id, r.name 
                      ORDER BY r.level DESC");
while ($row = $result->fetch_assoc()) {
    echo "  Role '{$row['name']}' (ID={$row['id']}): {$row['perm_count']} permissions\n";
}
