<?php
/**
 * Run Dispatch Site Fields Migration
 * Adds site_id and material_request_id columns to dispatches table
 */

require_once __DIR__ . '/migrations/2026_01_03_300000_add_dispatch_site_fields.php';

echo "Running dispatch site fields migration...\n";

try {
    $migration = new AddDispatchSiteFields();
    $migration->up();
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
