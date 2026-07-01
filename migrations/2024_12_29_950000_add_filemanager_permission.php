<?php
/**
 * Migration: Add filemanager.manage permission
 * 
 * Requirements: 6.1 - File Manager access control
 * - Verify user has ADV company type and appropriate permission
 * 
 * **Feature: file-manager-module, Menu Integration**
 */

require_once __DIR__ . '/Migration.php';

class AddFilemanagerPermission extends Migration {
    
    public function up() {
        // Check if permission already exists
        $result = $this->db->query("SELECT id FROM permissions WHERE name = 'filemanager.manage'");
        $existing = $result->fetch_assoc();
        
        if (!$existing) {
            // Add filemanager.manage permission
            $stmt = $this->db->prepare("
                INSERT INTO permissions (name, module, action, description, is_adv_only) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $name = 'filemanager.manage';
            $module = 'filemanager';
            $action = 'manage';
            $description = 'Access File Manager to browse, view, create, edit, and delete files within XAMPP server environment';
            $isAdvOnly = 1;
            $stmt->bind_param('ssssi', $name, $module, $action, $description, $isAdvOnly);
            $stmt->execute();
            
            $permissionId = $this->db->insert_id;
            
            // Assign to Super Admin role (role_id = 1)
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                VALUES (?, ?)
            ");
            $roleId = 1;
            $stmt->bind_param('ii', $roleId, $permissionId);
            $stmt->execute();
            
            echo "Added filemanager.manage permission (ID: {$permissionId}) and assigned to Super Admin role\n";
        } else {
            echo "filemanager.manage permission already exists (ID: {$existing['id']})\n";
            
            // Make sure it's assigned to Super Admin
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                VALUES (?, ?)
            ");
            $roleId = 1;
            $stmt->bind_param('ii', $roleId, $existing['id']);
            $stmt->execute();
        }
        
        return true;
    }
    
    public function down() {
        // Get permission ID
        $result = $this->db->query("SELECT id FROM permissions WHERE name = 'filemanager.manage'");
        $permission = $result->fetch_assoc();
        
        if ($permission) {
            // Remove from role_permissions
            $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
            $stmt->bind_param('i', $permission['id']);
            $stmt->execute();
            
            // Remove permission
            $stmt = $this->db->prepare("DELETE FROM permissions WHERE id = ?");
            $stmt->bind_param('i', $permission['id']);
            $stmt->execute();
            
            echo "Removed filemanager.manage permission\n";
        }
        
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../config/database.php';
    
    $migration = new AddFilemanagerPermission();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
