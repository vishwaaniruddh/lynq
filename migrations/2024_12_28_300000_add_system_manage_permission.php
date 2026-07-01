<?php
/**
 * Migration: Add system.manage permission
 * 
 * Requirements: 4.5, 7.4 - System administration permissions
 */

require_once __DIR__ . '/Migration.php';

class AddSystemManagePermission extends Migration {
    
    public function up() {
        // Check if permission already exists
        $result = $this->db->query("SELECT id FROM permissions WHERE name = 'system.manage'");
        $existing = $result->fetch_assoc();
        
        if (!$existing) {
            // Add system.manage permission
            $stmt = $this->db->prepare("
                INSERT INTO permissions (name, module, action, description, is_adv_only) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $name = 'system.manage';
            $module = 'system';
            $action = 'manage';
            $description = 'Access system administration tools including health monitoring, backups, and maintenance';
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
            
            echo "Added system.manage permission and assigned to Super Admin role\n";
        } else {
            echo "system.manage permission already exists\n";
        }
        
        return true;
    }
    
    public function down() {
        // Get permission ID
        $result = $this->db->query("SELECT id FROM permissions WHERE name = 'system.manage'");
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
            
            echo "Removed system.manage permission\n";
        }
        
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../config/database.php';
    
    $migration = new AddSystemManagePermission();
    
    if (isset($argv[1]) && $argv[1] === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
