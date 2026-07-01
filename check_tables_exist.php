<?php
require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();
$result = $db->query('SHOW TABLES');

echo "Existing tables:\n";
while($row = $result->fetch_array()) {
    echo "  - " . $row[0] . "\n";
}

// Check if sites table exists
$result = $db->query("SHOW TABLES LIKE 'sites'");
if ($result->num_rows > 0) {
    echo "\nSites table EXISTS\n";
} else {
    echo "\nSites table DOES NOT EXIST\n";
}
