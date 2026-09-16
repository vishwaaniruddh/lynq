<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();

$testIds = $db->query("SELECT id FROM products WHERE name LIKE '%test%'")->fetch_all(MYSQLI_ASSOC);
$ids = array_column($testIds, 'id');
echo "Found " . count($ids) . " test product IDs.\n";

if (!empty($ids)) {
    $idList = implode(',', array_map('intval', $ids));
    
    // Check references in related tables
    $tables = [
        'inventory' => 'product_id',
        'inventory_items' => 'product_id',
        'inventory_serial_numbers' => 'product_id',
        'material_requests' => 'product_id',
        'material_request_items' => 'product_id',
        'stock_movements' => 'product_id',
        'dispatch_items' => 'product_id'
    ];
    
    foreach ($tables as $tbl => $col) {
        $check = $db->query("SHOW TABLES LIKE '{$tbl}'");
        if ($check && $check->num_rows > 0) {
            $colCheck = $db->query("SHOW COLUMNS FROM `{$tbl}` LIKE '{$col}'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $countRes = $db->query("SELECT COUNT(*) as c FROM `{$tbl}` WHERE `{$col}` IN ({$idList})");
                $c = $countRes->fetch_assoc()['c'];
                echo "Table `{$tbl}`: {$c} rows referencing test products\n";
            }
        }
    }
}
