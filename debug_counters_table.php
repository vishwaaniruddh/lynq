<?php
require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();

echo "=== Inventory Counters Table ===\n\n";

$counters = $db->getResults("SELECT * FROM inventory_counters WHERE entity_type = 'company'", [], '');
echo "Company counters: " . count($counters) . "\n";
foreach ($counters as $c) {
    echo "  - Product {$c['product_id']}, Entity: {$c['entity_type']} #{$c['entity_id']}, Qty: {$c['quantity']}\n";
}

echo "\n=== Non-serializable products dispatched to company 2 ===\n";
$sql = "SELECT p.id, p.name, p.is_serializable, SUM(di.quantity) as total_qty
        FROM dispatches d
        JOIN dispatch_items di ON di.dispatch_id = d.id
        JOIN products p ON di.product_id = p.id
        WHERE d.to_company_id = 2
        AND d.acknowledgment_status = 'acknowledged'
        AND p.is_serializable = 0
        GROUP BY p.id, p.name, p.is_serializable";

$products = $db->getResults($sql, [], '');
foreach ($products as $p) {
    echo "  - {$p['name']} (ID: {$p['id']}): {$p['total_qty']} units\n";
}

echo "\nDone.\n";
