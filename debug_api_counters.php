<?php
require_once __DIR__ . '/config/autoload.php';

$svc = new InventoryCounterService();
$result = $svc->getAllCounters('company', 2);

echo "=== Raw API Result ===\n";
echo "Success: " . ($result['success'] ? 'yes' : 'no') . "\n";
echo "Data type: " . gettype($result['data']) . "\n";

if (is_array($result['data'])) {
    echo "Data count: " . count($result['data']) . "\n";
    echo "First item keys: " . (count($result['data']) > 0 ? implode(', ', array_keys($result['data'][0])) : 'N/A') . "\n";
    
    // Check for non-serializable items
    $nonSerializable = array_filter($result['data'], function($c) {
        return !$c['is_serializable'] && $c['quantity'] > 0;
    });
    echo "\nNon-serializable items with qty > 0: " . count($nonSerializable) . "\n";
    foreach ($nonSerializable as $item) {
        echo "  - {$item['product_name']}: qty={$item['quantity']}, is_serializable=" . ($item['is_serializable'] ? 'yes' : 'no') . "\n";
    }
}

echo "\nDone.\n";
