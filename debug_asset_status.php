<?php
/**
 * Debug script to check asset status after dispatch
 * Run this to verify if assets are being updated correctly
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();
$conn = $db->getConnection();

// Get all Jio Sim assets
$sql = "SELECT a.id, a.serial_number, a.status, a.warehouse_id, a.source_warehouse_id, a.current_holder_type, a.current_holder_id,
               p.name as product_name, w.name as warehouse_name, sw.name as source_warehouse_name
        FROM assets a
        LEFT JOIN products p ON a.product_id = p.id
        LEFT JOIN warehouses w ON a.warehouse_id = w.id
        LEFT JOIN warehouses sw ON a.source_warehouse_id = sw.id
        WHERE p.name LIKE '%Jio%' OR a.serial_number LIKE '%Jio%'
        ORDER BY a.id";

$result = $conn->query($sql);

echo "<h2>Jio Sim Assets Status</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Serial Number</th><th>Product</th><th>Status</th><th>Warehouse ID</th><th>Warehouse Name</th><th>Source WH ID</th><th>Source WH</th><th>Holder Type</th><th>Holder ID</th></tr>";

while ($row = $result->fetch_assoc()) {
    $statusColor = $row['status'] === 'in_stock' ? 'green' : ($row['status'] === 'dispatched' ? 'orange' : 'gray');
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['serial_number']}</td>";
    echo "<td>{$row['product_name']}</td>";
    echo "<td style='color: {$statusColor}; font-weight: bold;'>{$row['status']}</td>";
    echo "<td>" . ($row['warehouse_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['warehouse_name'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['source_warehouse_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['source_warehouse_name'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['current_holder_type'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['current_holder_id'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Also check recent dispatches
echo "<h2>Recent Dispatches (last 10)</h2>";
$sql2 = "SELECT d.id, d.dispatch_number, d.status, d.created_at,
                di.product_id, di.asset_id, di.quantity,
                p.name as product_name, a.serial_number
         FROM dispatches d
         LEFT JOIN dispatch_items di ON d.id = di.dispatch_id
         LEFT JOIN products p ON di.product_id = p.id
         LEFT JOIN assets a ON di.asset_id = a.id
         ORDER BY d.created_at DESC
         LIMIT 10";

$result2 = $conn->query($sql2);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Dispatch ID</th><th>Dispatch #</th><th>Status</th><th>Created</th><th>Product</th><th>Asset ID</th><th>Serial #</th><th>Qty</th></tr>";

while ($row = $result2->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['dispatch_number']}</td>";
    echo "<td>{$row['status']}</td>";
    echo "<td>{$row['created_at']}</td>";
    echo "<td>{$row['product_name']}</td>";
    echo "<td>" . ($row['asset_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['serial_number'] ?? 'N/A') . "</td>";
    echo "<td>{$row['quantity']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check stock calculation
echo "<h2>Stock Calculation Check</h2>";
$sql3 = "SELECT 
            a.product_id,
            p.name as product_name,
            a.warehouse_id,
            w.name as warehouse_name,
            COUNT(*) as total_count,
            SUM(CASE WHEN a.status = 'in_stock' THEN 1 ELSE 0 END) as in_stock_count,
            SUM(CASE WHEN a.status = 'dispatched' THEN 1 ELSE 0 END) as dispatched_count
         FROM assets a
         LEFT JOIN products p ON a.product_id = p.id
         LEFT JOIN warehouses w ON a.warehouse_id = w.id
         WHERE p.name LIKE '%Jio%'
         GROUP BY a.product_id, a.warehouse_id, p.name, w.name";

$result3 = $conn->query($sql3);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Product</th><th>Warehouse ID</th><th>Warehouse</th><th>Total</th><th>In Stock</th><th>Dispatched</th></tr>";

while ($row = $result3->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['product_name']}</td>";
    echo "<td>" . ($row['warehouse_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['warehouse_name'] ?? 'NULL (dispatched)') . "</td>";
    echo "<td>{$row['total_count']}</td>";
    echo "<td style='color: green; font-weight: bold;'>{$row['in_stock_count']}</td>";
    echo "<td style='color: orange;'>{$row['dispatched_count']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Note:</strong> If dispatched assets show warehouse_id as NULL, they should NOT appear in the stock page count.</p>";
echo "<p>If you see assets with status='dispatched' but warehouse_id is NOT NULL, that's the bug!</p>";
