<?php
/**
 * Run the rejected status migration for material requests
 */

require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/migrations/Migration.php';
require_once __DIR__ . '/migrations/2026_01_04_100000_add_rejected_status_to_material_requests.php';

echo "Running rejected status migration...\n";

try {
    $migration = new AddRejectedStatusToMaterialRequests();
    $migration->up();
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
