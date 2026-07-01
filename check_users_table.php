<?php
require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();
$result = $db->query('DESCRIBE users');

echo "Users table structure:\n";
while($row = $result->fetch_assoc()) {
    echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
}
