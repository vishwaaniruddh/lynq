<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "Deleting test products from database...\n";
$db->query("DELETE FROM products WHERE name LIKE '%test%' OR name LIKE '%Test%'");
$affected = $db->affected_rows;
echo "Successfully deleted {$affected} test products from `products` table.\n";

$res = $db->query("SELECT id, name, category_id, unit_of_measure, inventory_type, status FROM products ORDER BY id ASC");
echo "\nRemaining Products in database:\n";
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Name: {$row['name']} | Cat ID: {$row['category_id']} | Type: {$row['inventory_type']} | Status: {$row['status']}\n";
}
