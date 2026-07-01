<?php
/**
 * Fix accepted assets - set in_stock status for assets that have been accepted
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();
$conn = $db->getConnection();

echo "=== Fixing Accepted Assets Status ===\n\n";

// Find assets that have been accepted but still have 'dispatched' status
$sql = "SELECT a.id, a.serial_number, a.status, pr.recipient_type, pr.recipient_id
        FROM assets a
        JOIN dispatch_items di ON di.asset_id = a.id
        JOIN pending_receive_items pri ON pri.dispatch_item_id = di.id
        JOIN pending_receives pr ON pr.id = pri.pending_receive_id
        WHERE pr.status = 'accepted' AND a.status = 'dispatched'
        ORDER BY a.id";

$assets = $db->getResults($sql, [], '');

echo "Found " . count($assets) . " accepted assets with incorrect 'dispatched' status\n\n";

if (count($assets) === 0) {
    echo "No assets need fixing.\n";
    exit(0);
}

foreach ($assets as $asset) {
    echo "Asset ID: {$asset['id']}, Serial: {$asset['serial_number']}\n";
}

echo "\nUpdating status to 'in_stock'...\n";

// Fix the status - update assets that have been accepted to in_stock
$updateSql = "UPDATE assets a
              JOIN dispatch_items di ON di.asset_id = a.id
              JOIN pending_receive_items pri ON pri.dispatch_item_id = di.id
              JOIN pending_receives pr ON pr.id = pri.pending_receive_id
              SET a.status = 'in_stock'
              WHERE pr.status = 'accepted' AND a.status = 'dispatched'";

$result = $conn->query($updateSql);

if ($result) {
    echo "Successfully updated " . $conn->affected_rows . " assets to 'in_stock' status.\n";
} else {
    echo "Failed to update assets: " . $conn->error . "\n";
}

// Verify the fix
$verifySql = "SELECT COUNT(*) as count 
              FROM assets a
              JOIN dispatch_items di ON di.asset_id = a.id
              JOIN pending_receive_items pri ON pri.dispatch_item_id = di.id
              JOIN pending_receives pr ON pr.id = pri.pending_receive_id
              WHERE pr.status = 'accepted' AND a.status = 'dispatched'";
$verifyResult = $db->getResults($verifySql, [], '');
$remaining = $verifyResult[0]['count'] ?? 0;

echo "\nVerification: $remaining accepted assets still have 'dispatched' status.\n";

echo "\nDone.\n";
