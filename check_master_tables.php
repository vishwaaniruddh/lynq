<?php
/**
 * Check if master module tables exist
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();

$tables = ['banks', 'customers', 'countries', 'zones', 'states', 'cities'];

echo "Checking master module tables...\n";
echo "================================\n";

foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    $exists = $result->num_rows > 0;
    echo "$table: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}
