<?php
/**
 * Fix asset status - set dispatched status for assets with NULL warehouse_id
 * BUT only if they haven't been accepted yet
 * 
 * Assets with warehouse_id = NULL but status = 'in_stock' are in an inconsistent state
 * UNLESS they have been accepted by the recipient (pending_receives.status = 'accepted')
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();
$conn = $db->getConnection();

echo "=== Fixing Asset Status ===\n\n";

// Find assets with inconsistent state (NULL warehouse but in_stock)
// EXCLUDE assets that have been accepted (those should remain in_stock)
$sql = "SELECT a.id, a.serial_number, a.status, a.warehouse_id, a.source_warehouse_id 
        FROM assets a
        LEFT JOIN dispatch_items di ON di.asset_id = a.id
        LEFT JOIN pending_receive_items pri ON pri.dispatch_item_id = di.id
        LEFT JOIN pending_receives pr ON pr.id = pri.pending_receive_id AND pr.status = 'accepted'
        WHERE a.warehouse_id IS NULL 
        AND a.status = 'in_stock'
        AND pr.id IS NULL";  // No accepted pending receive

$assets = $db->getResults($sql, [], '');

echo "Found " . count($assets) . " assets with inconsistent status (not accepted)\n\n";

if (count($assets) === 0) {
    echo "No assets need fixing.\n";
    exit(0);
}

// Show what will be fixed
foreach ($assets as $asset) {
    echo "Asset ID: {$asset['id']}, Serial: {$asset['serial_number']}, Status: {$asset['status']}, Source Warehouse: " . ($asset['source_warehouse_id'] ?? 'NULL') . "\n";
}

echo "\nUpdating status to 'dispatched'...\n";

// Get the IDs to update
$ids = array_column($assets, 'id');
$placeholders = implode(',', $ids);

// Fix the status using mysqli
$updateSql = "UPDATE assets SET status = 'dispatched' WHERE id IN ($placeholders)";
$result = $conn->query($updateSql);

if ($result) {
    echo "Successfully updated " . $conn->affected_rows . " assets to 'dispatched' status.\n";
} else {
    echo "Failed to update assets: " . $conn->error . "\n";
}

// Verify the fix
$verifySql = "SELECT COUNT(*) as count FROM assets a
              LEFT JOIN dispatch_items di ON di.asset_id = a.id
              LEFT JOIN pending_receive_items pri ON pri.dispatch_item_id = di.id
              LEFT JOIN pending_receives pr ON pr.id = pri.pending_receive_id AND pr.status = 'accepted'
              WHERE a.warehouse_id IS NULL AND a.status = 'in_stock' AND pr.id IS NULL";
$verifyResult = $db->getResults($verifySql, [], '');
$remaining = $verifyResult[0]['count'] ?? 0;

echo "\nVerification: $remaining assets still have inconsistent status.\n";

echo "\nDone.\n";
