<?php
/**
 * Setup File Manager Permission
 * Adds the filemanager.manage permission required for File Manager access
 * 
 * Requirements: 6.1 - File Manager access control
 * 
 * **Feature: file-manager-module, Menu Integration**
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = getDbConnection();
    
    // Check if permission already exists
    $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
    $stmt->execute(['filemanager.manage']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        // Add filemanager.manage permission
        $stmt = $db->prepare("
            INSERT INTO permissions (name, module, action, description, is_adv_only) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'filemanager.manage',
            'filemanager',
            'manage',
            'Access File Manager to browse, view, create, edit, and delete files within XAMPP server environment',
            1
        ]);
        
        $permissionId = $db->lastInsertId();
        echo "Created filemanager.manage permission (ID: {$permissionId})\n";
        
        // Assign to Super Admin role (role_id = 1)
        $stmt = $db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([1, $permissionId]);
        echo "Assigned filemanager.manage permission to Super Admin role\n";
    } else {
        echo "filemanager.manage permission already exists (ID: {$existing['id']})\n";
        
        // Make sure it's assigned to Super Admin
        $stmt = $db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([1, $existing['id']]);
        echo "Ensured filemanager.manage permission is assigned to Super Admin role\n";
    }
    
    echo "\nFile Manager permission setup complete!\n";
    echo "Users with Super Admin role can now access the File Manager.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
