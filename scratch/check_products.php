<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();
$res = $db->query("SHOW COLUMNS FROM products");
echo "Products columns:\n";
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}

$res = $db->query("SELECT * FROM products");
echo "\nAll Products:\n";
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
