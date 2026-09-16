<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();
$res = $db->query("SELECT id, name, category_id, status FROM products");
$count = 0;
$testCount = 0;
$realProducts = [];
$testProducts = [];
while ($row = $res->fetch_assoc()) {
    $count++;
    if (stripos($row['name'], 'test') !== false) {
        $testCount++;
        $testProducts[] = $row;
    } else {
        $realProducts[] = $row;
    }
}

echo "Total products: {$count}\n";
echo "Test products: {$testCount}\n";
echo "Real / Non-test products: " . count($realProducts) . "\n";
echo "\nReal Products:\n";
print_r($realProducts);
echo "\nSample Test Products (first 10):\n";
print_r(array_slice($testProducts, 0, 10));
