<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance();
$sql = "SELECT p.*, 
               COALESCE(c.name, 'General') as category_name,
               (SELECT COUNT(*) FROM assets a WHERE a.product_id = p.id AND a.status IN ('IN_STOCK', 'ASSIGNED', 'AVAILABLE')) as asset_count,
               (SELECT COALESCE(SUM(s.quantity), 0) FROM stock s WHERE s.product_id = p.id) as stock_qty
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        WHERE p.status = 'active'";
$res = $db->getResults($sql);
echo "Successfully fetched " . count($res) . " products:\n";
foreach ($res as $r) {
    echo "  - [ID {$r['id']}] {$r['name']} | Category: {$r['category_name']} | Asset Count: {$r['asset_count']} | Stock Qty: {$r['stock_qty']}\n";
}
