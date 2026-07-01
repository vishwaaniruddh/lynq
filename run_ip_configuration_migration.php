<?php
/**
 * IP Configuration Management - Database Migration Runner
 * 
 * This script executes all migrations for the IP Configuration Management module.
 * 
 * Usage: php run_ip_configuration_migration.php
 */

require_once __DIR__ . '/config/database.php';

echo "===========================================\n";
echo "IP Configuration Management - Migration Runner\n";
echo "===========================================\n\n";

// Migration classes to run in order
$migrations = [
    '2024_12_30_200000_create_ip_master_table.php' => 'CreateIPMasterTable',
    '2024_12_30_200001_create_ip_locks_table.php' => 'CreateIPLocksTable',
    '2024_12_30_200002_create_router_ip_bindings_table.php' => 'CreateRouterIPBindingsTable',
    '2024_12_30_200003_create_configuration_audit_log_table.php' => 'CreateConfigurationAuditLogTable'
];

$successCount = 0;
$errorCount = 0;

foreach ($migrations as $file => $className) {
    $filePath = __DIR__ . '/migrations/' . $file;
    
    if (!file_exists($filePath)) {
        echo "❌ Migration file not found: {$file}\n";
        $errorCount++;
        continue;
    }
    
    echo "Running migration: {$file}...\n";
    
    try {
        require_once $filePath;
        
        $migration = new $className();
        $migration->up();
        
        echo "✅ Successfully executed: {$file}\n";
        $successCount++;
    } catch (Exception $e) {
        echo "❌ Error in {$file}: " . $e->getMessage() . "\n";
        $errorCount++;
    }
    
    echo "\n";
}

echo "===========================================\n";
echo "Migration Summary\n";
echo "===========================================\n";
echo "Successful: {$successCount}\n";
echo "Failed: {$errorCount}\n";
echo "Total: " . count($migrations) . "\n";

if ($errorCount === 0) {
    echo "\n✅ All IP Configuration Management migrations completed successfully!\n";
} else {
    echo "\n⚠️ Some migrations failed. Please check the errors above.\n";
}
