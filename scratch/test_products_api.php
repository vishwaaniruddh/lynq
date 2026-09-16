<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';
require_once 'c:/xampp/htdocs/lynq/repositories/ProductRepository.php';

$repo = new ProductRepository();
$result = $repo->findAll();
echo "Products list result count: " . count($result) . "\n";
foreach ($result as $p) {
    echo "  - [ID {$p['id']}] {$p['name']} ({$p['inventory_type']}) - Status: {$p['status']}\n";
}
