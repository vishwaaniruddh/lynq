<?php
/**
 * Debug contractor stock - check what products were dispatched
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();

echo "=== Checking dispatched items to contractor (company_id=2) ===\n\n";

$sql = "SELECT d.id, d.dispatch_number, di.product_id, di.quantity, di.asset_id, 
               p.name, p.is_serializable 
        FROM dispatches d 
        JOIN dispatch_items di ON di.dispatch_id = d.id 
        JOIN products p ON di.product_id = p.id 
        WHERE d.to_company_id = 2 
        AND d.acknowledgment_status = 'acknowledged'
        ORDER BY d.id, di.id";

$items = $db->getResults($sql, [], '');

echo "Found " . count($items) . " dispatch items\n\n";

$serializable = 0;
$nonSerializable = 0;

foreach ($items as $item) {
    $type = $item['is_serializable'] ? 'SERIALIZABLE' : 'QUANTITY';
    echo "{$item['dispatch_number']} - {$item['name']} ($type) qty: {$item['quantity']} asset_id: " . ($item['asset_id'] ?? 'NULL') . "\n";
    
    if ($item['is_serializable']) {
        $serializable++;
    } else {
        $nonSerializable++;
    }
}

echo "\n=== Summary ===\n";
echo "Serializable items: $serializable\n";
echo "Non-serializable items: $nonSerializable\n";

echo "\nDone.\n";
