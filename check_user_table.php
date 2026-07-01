<?php
require_once __DIR__ . '/config/database.php';

$db = DatabaseConfig::getInstance()->getConnection();

echo "Users table structure:\n";
echo "======================\n";
$result = $db->query('DESCRIBE users');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\nUser Audit Log table structure:\n";
echo "================================\n";
$result = $db->query('DESCRIBE user_audit_log');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
