<?php
/**
 * Cleanup IP restrictions for testing
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();

// Check current restrictions
echo "Current IP restrictions:\n";
$results = $db->getResults("SELECT * FROM ip_restrictions");
print_r($results);

// Clean up test restrictions
echo "\nCleaning up test IP restrictions...\n";
$sql = "DELETE FROM ip_restrictions WHERE ip_address LIKE '203.0.113.%' OR ip_address LIKE '198.51.100.%'";
$stmt = $db->executeQuery($sql);
echo "Deleted: " . $stmt->affected_rows . " rows\n";
$stmt->close();

// Check remaining restrictions
echo "\nRemaining IP restrictions:\n";
$results = $db->getResults("SELECT * FROM ip_restrictions");
print_r($results);
