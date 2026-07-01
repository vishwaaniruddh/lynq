<?php
/**
 * Migration: Add Product Category Master Permissions
 * 
 * Adds permissions for product category master module:
 * - masters.product_categories.view
 * - masters.product_categories.create
 * - masters.product_categories.edit
 * - masters.product_categories.delete
 */

require_once __DIR__ . '/../config/autoload.php';

class AddProductCategoryPermissionsMigration {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    public function up() {
        echo "Adding product category permissions...\n";
        
        $permissions = [
            [
                'name' => 'masters.product_categories.view',
                'module' => 'masters',
                'action' => 'product_categories.view',
                'description' => 'View product categories',
                'is_adv_only' => 1
            ],
            [
                'name' => 'masters.product_categories.create',
                'module' => 'masters',
                'action' => 'product_categories.create',
                'description' => 'Create product categories',
                'is_adv_only' => 1
            ],
            [
                'name' => 'masters.product_categories.edit',
                'module' => 'masters',
                'action' => 'product_categories.edit',
                'description' => 'Edit product categories',
                'is_adv_only' => 1
            ],
            [
                'name' => 'masters.product_categories.delete',
                'module' => 'masters',
                'action' => 'product_categories.delete',
                'description' => 'Delete product categories',
                'is_adv_only' => 1
            ]
        ];
        
        foreach ($permissions as $perm) {
            // Check if permission already exists
            $checkSql = "SELECT id FROM permissions WHERE name = ?";
            $existing = $this->db->getResults($checkSql, [$perm['name']], 's');
            
            if (empty($existing)) {
                $sql = "INSERT INTO permissions (name, module, action, description, is_adv_only, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $this->db->executeQuery($sql, [
                    $perm['name'],
                    $perm['module'],
                    $perm['action'],
                    $perm['description'],
                    $perm['is_adv_only']
                ], 'ssssi');
                $stmt->close();
                echo "  Added permission: {$perm['name']}\n";
            } else {
                echo "  Permission already exists: {$perm['name']}\n";
            }
        }
        
        // Assign all product category permissions to ADV Admin role
        $this->assignToAdvAdmin();
        
        echo "Product category permissions migration completed.\n";
        return true;
    }
    
    private function assignToAdvAdmin() {
        echo "Assigning permissions to ADV Admin role...\n";
        
        // Find ADV Admin role
        $roleSql = "SELECT id FROM roles WHERE name = 'ADV Admin' OR name = 'adv_admin' LIMIT 1";
        $roleResult = $this->db->getResults($roleSql, [], '');
        
        if (empty($roleResult)) {
            echo "  ADV Admin role not found, skipping role assignment.\n";
            return;
        }
        
        $roleId = $roleResult[0]['id'];
        
        // Get all product category permissions
        $permSql = "SELECT id FROM permissions WHERE name LIKE 'masters.product_categories.%'";
        $permissions = $this->db->getResults($permSql, [], '');
        
        foreach ($permissions as $perm) {
            // Check if already assigned
            $checkSql = "SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?";
            $existing = $this->db->getResults($checkSql, [$roleId, $perm['id']], 'ii');
            
            if (empty($existing)) {
                $sql = "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())";
                $stmt = $this->db->executeQuery($sql, [$roleId, $perm['id']], 'ii');
                $stmt->close();
                echo "  Assigned permission ID {$perm['id']} to ADV Admin role.\n";
            }
        }
    }
    
    public function down() {
        echo "Removing product category permissions...\n";
        
        // Remove role_permissions first
        $sql = "DELETE rp FROM role_permissions rp 
                INNER JOIN permissions p ON rp.permission_id = p.id 
                WHERE p.name LIKE 'masters.product_categories.%'";
        $this->db->executeQuery($sql, [], '');
        
        // Remove permissions
        $sql = "DELETE FROM permissions WHERE name LIKE 'masters.product_categories.%'";
        $this->db->executeQuery($sql, [], '');
        
        echo "Product category permissions removed.\n";
        return true;
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $migration = new AddProductCategoryPermissionsMigration();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
