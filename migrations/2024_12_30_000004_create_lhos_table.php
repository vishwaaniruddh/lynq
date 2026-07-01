<?php
/**
 * Migration: Create LHOs (Local Head Office) Table
 * 
 * Creates the lhos table for managing Local Head Office records
 * Simple master with just lho_name field
 */

require_once __DIR__ . '/../config/autoload.php';

class CreateLhosTable {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Run the migration
     */
    public function up(): bool {
        try {
            // Create lhos table
            $sql = "CREATE TABLE IF NOT EXISTS `lhos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `lho_name` VARCHAR(255) NOT NULL,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `created_by` INT NULL,
                `updated_by` INT NULL,
                UNIQUE KEY `uk_lho_name` (`lho_name`),
                INDEX `idx_status` (`status`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Created lhos table\n";
            
            // Add LHO permissions
            $permissions = [
                ['masters.lhos.view', 'View LHO records', 'masters', 'lhos.view'],
                ['masters.lhos.create', 'Create LHO records', 'masters', 'lhos.create'],
                ['masters.lhos.edit', 'Edit LHO records', 'masters', 'lhos.edit'],
                ['masters.lhos.delete', 'Delete LHO records', 'masters', 'lhos.delete']
            ];
            
            foreach ($permissions as $perm) {
                $checkSql = "SELECT id FROM permissions WHERE name = ?";
                $existing = $this->db->getResults($checkSql, [$perm[0]], 's');
                
                if (empty($existing)) {
                    $insertSql = "INSERT INTO permissions (name, description, module, action, is_adv_only, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())";
                    $stmt = $this->db->executeQuery($insertSql, [$perm[0], $perm[1], $perm[2], $perm[3]], 'ssss');
                    $stmt->close();
                    echo "✓ Added permission: {$perm[0]}\n";
                } else {
                    echo "  Permission already exists: {$perm[0]}\n";
                }
            }
            
            // Grant permissions to Super Admin role
            $superAdminSql = "SELECT id FROM roles WHERE name = 'Super Admin' OR name = 'ADV Admin' LIMIT 1";
            $superAdmin = $this->db->getResults($superAdminSql, [], '');
            
            if (!empty($superAdmin)) {
                $roleId = $superAdmin[0]['id'];
                
                foreach ($permissions as $perm) {
                    $permSql = "SELECT id FROM permissions WHERE name = ?";
                    $permResult = $this->db->getResults($permSql, [$perm[0]], 's');
                    
                    if (!empty($permResult)) {
                        $permId = $permResult[0]['id'];
                        
                        $checkRolePerm = "SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?";
                        $existingRolePerm = $this->db->getResults($checkRolePerm, [$roleId, $permId], 'ii');
                        
                        if (empty($existingRolePerm)) {
                            $insertRolePerm = "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())";
                            $stmt = $this->db->executeQuery($insertRolePerm, [$roleId, $permId], 'ii');
                            $stmt->close();
                        }
                    }
                }
                echo "✓ Granted LHO permissions to admin role\n";
            }
            
            return true;
        } catch (Exception $e) {
            echo "✗ Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Reverse the migration
     */
    public function down(): bool {
        try {
            // Remove role_permissions first
            $sql = "DELETE rp FROM role_permissions rp 
                    INNER JOIN permissions p ON rp.permission_id = p.id 
                    WHERE p.name LIKE 'masters.lhos.%'";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            // Remove permissions
            $sql = "DELETE FROM permissions WHERE name LIKE 'masters.lhos.%'";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            // Drop table
            $sql = "DROP TABLE IF EXISTS `lhos`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            echo "✓ Rolled back lhos table and permissions\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run migration if executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $migration = new CreateLhosTable();
    
    $action = $argv[1] ?? $_GET['action'] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
