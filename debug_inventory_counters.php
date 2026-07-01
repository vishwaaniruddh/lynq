<?php
require_once __DIR__ . '/config/autoload.php';

$svc = new InventoryCounterService();
$result = $svc->getAllCounters('company', 2);

echo "=== Inventory Counters for Company 2 ===\n\n";
echo "Success: " . ($result['success'] ? 'yes' : 'no') . "\n";
echo "Message: " . ($result['message'] ?? 'N/A') . "\n\n";

if ($result['success']) {
    // The data is directly in 'data', not 'data.counters'
    $counters = $result['data'] ?? [];
    
    // Check if it's an array of counters or has a 'counters' key
    if (isset($counters['counters'])) {
        $counters = $counters['counters'];
    }
    
    echo "Found " . count($counters) . " counters\n\n";
    
    foreach ($counters as $counter) {
        echo "Product: " . ($counter['product_name'] ?? 'Unknown') . " (ID: " . ($counter['product_id'] ?? '?') . ")\n";
        echo "  - Quantity: " . ($counter['quantity'] ?? 0) . "\n";
        echo "  - Pending In: " . ($counter['pending_in'] ?? 0) . "\n";
        echo "  - Pending Out: " . ($counter['pending_out'] ?? 0) . "\n";
        echo "  - Is Serializable: " . (isset($counter['is_serializable']) ? ($counter['is_serializable'] ? 'yes' : 'no') : 'unknown') . "\n";
        echo "\n";
    }
} else {
    echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
}

echo "\nDone.\n";
