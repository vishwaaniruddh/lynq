<?php
/**
 * Check if the new CRM tables were created
 */

require_once __DIR__ . '/config/autoload.php';

try {
    $db = DatabaseConfig::getInstance()->getConnection();
    
    echo "Checking database: " . $db->query("SELECT DATABASE()")->fetch_row()[0] . "\n\n";
    
    $tables = [
        'companies',
        'roles', 
        'permissions',
        'users',
        'role_permissions',
        'company_permissions',
        'user_sessions',
        'user_audit_log',
        'permission_audit_log',
        'migrations'
    ];
    
    echo "Checking for new CRM tables:\n";
    echo "============================\n";
    
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✓ Table '$table' exists\n";
            
            // Show row count
            $countResult = $db->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $countResult->fetch_assoc()['count'];
            echo "  - Rows: $count\n";
        } else {
            echo "✗ Table '$table' NOT found\n";
        }
    }
    
    echo "\nShowing all tables in database:\n";
    echo "===============================\n";
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}