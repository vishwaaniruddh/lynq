<?php
require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();
$result = $db->getResults('SELECT id, username FROM users LIMIT 5', [], '');
echo "Users in database:\n";
print_r($result);
