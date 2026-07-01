<?php
/**
 * Run Material Request Module Migrations
 * Executes all migrations for the Material Request Module
 */

require_once __DIR__ . '/config/database.php';

echo "Running Material Request Module Migrations...\n\n";

$migrations = [
    '2026_01_03_100000_create_material_masters_table.php' => 'CreateMaterialMastersTable',
    '2026_01_03_100001_create_material_master_items_table.php' => 'CreateMaterialMasterItemsTable',
    '2026_01_03_100002_create_material_requests_table.php' => 'CreateMaterialRequestsTable',
    '2026_01_03_100003_create_material_request_items_table.php' => 'CreateMaterialRequestItemsTable',
    '2026_01_03_200000_add_material_request_permissions.php' => 'AddMaterialRequestPermissions',
];

$success = true;

foreach ($migrations as $file => $class) {
    echo "Running migration: $file\n";
    
    try {
        require_once __DIR__ . '/migrations/' . $file;
        
        $migration = new $class();
        $migration->up();
        
        echo "  ✓ Migration completed successfully\n";
    } catch (Exception $e) {
        echo "  ✗ Migration failed: " . $e->getMessage() . "\n";
        $success = false;
        break;
    }
}

echo "\n";
if ($success) {
    echo "All Material Request Module migrations completed successfully!\n";
} else {
    echo "Migration process stopped due to errors.\n";
}
