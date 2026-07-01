<?php
/**
 * Run master module migration directly
 */

require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/migrations/Migration.php';
require_once __DIR__ . '/migrations/2024_12_28_400000_create_master_module_tables.php';

echo "Running master module migration...\n";
echo "==================================\n";

try {
    $migration = new CreateMasterModuleTables();
    $migration->up();
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
