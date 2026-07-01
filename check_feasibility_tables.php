<?php
require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();
$result = $db->getResults("SHOW TABLES LIKE 'feasibility%'");
echo "Feasibility tables:\n";
print_r($result);

// Check if engineer_assignments has feasibility_status column
$columns = $db->getResults("SHOW COLUMNS FROM engineer_assignments LIKE 'feasibility_status'");
echo "\nFeasibility status column in engineer_assignments:\n";
print_r($columns);
