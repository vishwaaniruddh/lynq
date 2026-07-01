<?php
/**
 * Setup System Admin Permission
 * Adds the system.manage permission required for system administration tools
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if permission already exists
    $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
    $stmt->execute(['system.manage']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        // Add system.manage permission
        $stmt = $db->prepare("
            INSERT INTO permissions (name, module, action, description, is_adv_only) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'system.manage',
            'system',
            'manage',
            'Access system administration tools including health monitoring, backups, and maintenance',
            1
        ]);
        
        $permissionId = $db->lastInsertId();
        echo "Created system.manage permission (ID: {$permissionId})\n";
        
        // Assign to Super Admin role (role_id = 1)
        $stmt = $db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([1, $permissionId]);
        echo "Assigned system.manage permission to Super Admin role\n";
    } else {
        echo "system.manage permission already exists (ID: {$existing['id']})\n";
        
        // Make sure it's assigned to Super Admin
        $stmt = $db->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([1, $existing['id']]);
        echo "Ensured permission is assigned to Super Admin role\n";
    }
    
    echo "\nSystem admin permission setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
