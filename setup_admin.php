<?php
/**
 * Setup Admin User Script
 * Creates a default Super Admin user for initial login
 * 
 * Run this once after database migration: php setup_admin.php
 */

require_once __DIR__ . '/config/autoload.php';

echo "=== ADV CRM Admin Setup ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if admin user already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    
    if ($stmt->fetch()) {
        echo "Admin user already exists!\n";
        echo "\nLogin credentials:\n";
        echo "  Username: admin\n";
        echo "  Password: Admin@123\n";
        exit(0);
    }
    
    // Get ADV company
    $stmt = $db->query("SELECT id FROM companies WHERE type = 'ADV' LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        // Create ADV company if not exists
        $stmt = $db->prepare("INSERT INTO companies (name, type, status, created_at, updated_at) VALUES (?, 'ADV', 'active', NOW(), NOW())");
        $stmt->execute(['ADV Systems']);
        $companyId = $db->lastInsertId();
        echo "Created ADV company (ID: $companyId)\n";
    } else {
        $companyId = $company['id'];
        echo "Using existing ADV company (ID: $companyId)\n";
    }
    
    // Get Super Admin role
    $stmt = $db->query("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1");
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        // Create Super Admin role if not exists
        $stmt = $db->prepare("INSERT INTO roles (name, level, company_type, description, created_at, updated_at) VALUES (?, 10, 'ADV', 'Full system administrator', NOW(), NOW())");
        $stmt->execute(['Super Admin']);
        $roleId = $db->lastInsertId();
        echo "Created Super Admin role (ID: $roleId)\n";
    } else {
        $roleId = $role['id'];
        echo "Using existing Super Admin role (ID: $roleId)\n";
    }
    
    // Create admin user
    $password = 'Admin@123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status, created_at, updated_at)
        VALUES (?, ?, ?, 'Admin', 'User', ?, ?, 1, NOW(), NOW())
    ");
    $stmt->execute(['admin', 'admin@advcrm.local', $passwordHash, $companyId, $roleId]);
    
    $userId = $db->lastInsertId();
    echo "Created admin user (ID: $userId)\n";
    
    // Ensure Super Admin has all permissions
    $stmt = $db->query("SELECT id FROM permissions");
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($permissions as $permId) {
        $stmt->execute([$roleId, $permId]);
    }
    echo "Assigned all permissions to Super Admin role\n";
    
    echo "\n========================================\n";
    echo "Admin user created successfully!\n";
    echo "========================================\n";
    echo "\nLogin credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: Admin@123\n";
    echo "\nURL: http://localhost/clarity/new_crm/\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
