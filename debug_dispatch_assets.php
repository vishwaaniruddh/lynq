<?php
/**
 * Debug script to check why dispatched assets are showing in serial picker
 */

require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/repositories/AssetRepository.php';

$assetRepository = new AssetRepository();

echo "=== Checking assets with status filter ===\n\n";

// Test 1: Get all assets with status=in_stock
$filters = ['status' => 'in_stock'];
$inStockAssets = $assetRepository->search($filters);
echo "Assets with status='in_stock': " . count($inStockAssets) . "\n";

// Test 2: Get all assets (no filter)
$allAssets = $assetRepository->search([]);
echo "All assets: " . count($allAssets) . "\n";

// Test 3: Count by status
$statusCounts = [];
foreach ($allAssets as $asset) {
    $status = $asset['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}
echo "\nAssets by status:\n";
foreach ($statusCounts as $status => $count) {
    echo "  - $status: $count\n";
}

// Test 4: Check if any in_stock assets have NULL warehouse_id
echo "\n=== Checking in_stock assets with NULL warehouse_id ===\n";
$nullWarehouseCount = 0;
foreach ($inStockAssets as $asset) {
    if (empty($asset['warehouse_id'])) {
        $nullWarehouseCount++;
        echo "Asset ID {$asset['id']}, Serial: {$asset['serial_number']}, Status: {$asset['status']}, Warehouse ID: " . ($asset['warehouse_id'] ?? 'NULL') . "\n";
    }
}
echo "Total in_stock assets with NULL warehouse_id: $nullWarehouseCount\n";

// Test 5: Check dispatched assets
echo "\n=== Checking dispatched assets ===\n";
$dispatchedAssets = $assetRepository->search(['status' => 'dispatched']);
echo "Dispatched assets: " . count($dispatchedAssets) . "\n";
foreach (array_slice($dispatchedAssets, 0, 5) as $asset) {
    echo "  - ID: {$asset['id']}, Serial: {$asset['serial_number']}, warehouse_id: " . ($asset['warehouse_id'] ?? 'NULL') . ", source_warehouse_id: " . ($asset['source_warehouse_id'] ?? 'NULL') . "\n";
}

echo "\nDone.\n";
