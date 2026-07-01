<?php
/**
 * Fix Asset Holder Status
 * 
 * Fixes assets that have incorrect status:
 * - Assets held by company/user should have status 'assigned' and warehouse_id = NULL
 * - Assets held by warehouse should have status 'in_stock' and warehouse_id set
 */

require_once __DIR__ . '/config/autoload.php';

echo "=== Fixing Asset Holder Status ===\n\n";

$db = DatabaseConfig::getInstance();

// Find assets with inconsistent status
// Case 1: Assets held by company/user but status is 'in_stock' or warehouse_id is set
$sql1 = "SELECT a.id, a.serial_number, a.status, a.warehouse_id, a.current_holder_type, a.current_holder_id,
                p.name as product_name
         FROM assets a
         LEFT JOIN products p ON a.product_id = p.id
         WHERE a.current_holder_type IN ('company', 'user')
         AND (a.status = 'in_stock' OR a.warehouse_id IS NOT NULL)";

$inconsistentAssets = $db->getResults($sql1, [], '');

echo "Found " . count($inconsistentAssets) . " assets with inconsistent status (held by company/user but status=in_stock or warehouse_id set)\n\n";

$fixed = 0;
foreach ($inconsistentAssets as $asset) {
    echo "Fixing asset #{$asset['id']} ({$asset['serial_number']}) - {$asset['product_name']}\n";
    echo "  Current: status={$asset['status']}, warehouse_id={$asset['warehouse_id']}, holder={$asset['current_holder_type']}:{$asset['current_holder_id']}\n";
    
    // Update to correct status
    $db->executeQuery(
        "UPDATE assets SET status = 'assigned', warehouse_id = NULL WHERE id = ?",
        [$asset['id']],
        'i'
    );
    
    echo "  Fixed: status=assigned, warehouse_id=NULL\n\n";
    $fixed++;
}

// Case 2: Assets held by warehouse but status is not 'in_stock' or warehouse_id doesn't match
$sql2 = "SELECT a.id, a.serial_number, a.status, a.warehouse_id, a.current_holder_type, a.current_holder_id,
                p.name as product_name
         FROM assets a
         LEFT JOIN products p ON a.product_id = p.id
         WHERE a.current_holder_type = 'warehouse'
         AND (a.status != 'in_stock' OR a.warehouse_id != a.current_holder_id OR a.warehouse_id IS NULL)
         AND a.status NOT IN ('dispatched', 'under_repair', 'scrapped', 'lost')";

$inconsistentWarehouseAssets = $db->getResults($sql2, [], '');

echo "Found " . count($inconsistentWarehouseAssets) . " assets with inconsistent warehouse status\n\n";

foreach ($inconsistentWarehouseAssets as $asset) {
    echo "Fixing asset #{$asset['id']} ({$asset['serial_number']}) - {$asset['product_name']}\n";
    echo "  Current: status={$asset['status']}, warehouse_id={$asset['warehouse_id']}, holder={$asset['current_holder_type']}:{$asset['current_holder_id']}\n";
    
    // Update to correct status
    $db->executeQuery(
        "UPDATE assets SET status = 'in_stock', warehouse_id = current_holder_id WHERE id = ?",
        [$asset['id']],
        'i'
    );
    
    echo "  Fixed: status=in_stock, warehouse_id={$asset['current_holder_id']}\n\n";
    $fixed++;
}

echo "=== Summary ===\n";
echo "Total assets fixed: $fixed\n";
echo "\nDone!\n";
