<?php
/**
 * Clean up CRM tables from the old database
 */

// Connect to the old database
$host = "localhost";
$user = "root";
$pass = "";
$old_dbname = "u444388293_advantage";

try {
    $con = new mysqli($host, $user, $pass, $old_dbname);
    
    if ($con->connect_error) {
        throw new Exception("Connection failed: " . $con->connect_error);
    }
    
    echo "Connected to old database: $old_dbname\n";
    
    // List of CRM tables to remove
    $crmTables = [
        'permission_audit_log',
        'user_audit_log', 
        'user_sessions',
        'company_permissions',
        'role_permissions',
        'users', // Note: this might conflict with existing 'user' table
        'permissions',
        'roles',
        'companies',
        'migrations'
    ];
    
    echo "\nRemoving CRM tables from old database:\n";
    echo "=====================================\n";
    
    foreach ($crmTables as $table) {
        // Check if table exists first
        $result = $con->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            if ($con->query("DROP TABLE `$table`")) {
                echo "✓ Dropped table: $table\n";
            } else {
                echo "✗ Failed to drop table: $table - " . $con->error . "\n";
            }
        } else {
            echo "- Table '$table' not found (already removed)\n";
        }
    }
    
    $con->close();
    echo "\nCleanup completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}