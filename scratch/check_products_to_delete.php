<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();

$res = $db->query("SELECT id, name, category_id, unit_of_measure, inventory_type, status FROM products ORDER BY id ASC");
echo "All non-test products:\n";
while ($row = $res->fetch_assoc()) {
    if (stripos($row['name'], 'test') === false) {
        echo "ID: {$row['id']} | Name: {$row['name']} | Cat: {$row['category_id']} | Type: {$row['inventory_type']} | Status: {$row['status']}\n";
    }
}

echo "\nSummary of products to delete:\n";
$delRes = $db->query("SELECT COUNT(*) as c FROM products WHERE name LIKE '%test%' OR name LIKE '%Test%'");
echo "Count of test products to delete: " . $delRes->fetch_assoc()['c'] . "\n";
