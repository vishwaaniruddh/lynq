<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();

$res = $db->query("SELECT TABLE_NAME, COLUMN_NAME 
                   FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'product_id'");
echo "Tables with product_id column:\n";
while ($row = $res->fetch_assoc()) {
    $tbl = $row['TABLE_NAME'];
    $cntRes = $db->query("SELECT COUNT(*) as c FROM `{$tbl}` WHERE product_id IN (SELECT id FROM products WHERE name LIKE '%test%')");
    $c = $cntRes ? $cntRes->fetch_assoc()['c'] : 'Error';
    echo "  {$tbl} => {$c} test rows\n";
}
