<?php
require_once __DIR__ . '/../config/autoload.php';

$db = DatabaseConfig::getInstance();

$banks = [
    'The Faridkot Central Cooperative Bank Ltd., Faridkot',
    'The Beed District Central Co-operative Bank Ltd., Beed',
    'Pithoragarh Zila Sahakari Bank Ltd., Pithoragarh',
    'Uttarkashi Zila Sahakari Bank Ltd., Uttarkashi',
    'Nainital District Cooperative Bank Ltd., Nainital',
    'Zila Sahkari Bank Ltd., Garhwal, Kotdwar',
    'Chamoli Zila Sahakari Bank Ltd., Chamoli',
    'The Amritsar Central Cooperative Bank Ltd., Amritsar',
    'The Gurdaspur Central Cooperative Bank Ltd., Gurdaspur',
    'The Hoshiarpur Central Cooperative Bank Ltd., Hoshiarpur',
    'The Mansa Central Cooperative Bank Ltd., Mansa',
    'The Patiala Central Cooperative Bank Ltd., Patiala',
    'The Jalandhar Central Cooperative Bank Ltd., Jalandhar'
];

echo "1. Truncating banks table...\n";
$db->executeQuery("SET FOREIGN_KEY_CHECKS = 0");
$db->executeQuery("TRUNCATE TABLE banks");
$db->executeQuery("SET FOREIGN_KEY_CHECKS = 1");
echo "Table truncated.\n";

echo "\n2. Inserting cleaned and standardized banks...\n";
$now = date('Y-m-d H:i:s');
$inserted = 0;
foreach ($banks as $bankName) {
    $sql = "INSERT INTO `banks` (`name`, `status`, `created_at`, `updated_at`) VALUES (?, 1, ?, ?)";
    $db->executeQuery($sql, [$bankName, $now, $now], 'sss');
    $inserted++;
    echo "Inserted #{$inserted}: {$bankName}\n";
}

echo "\n3. Verifying records in database:\n";
$results = $db->getResults("SELECT id, name, status FROM banks ORDER BY id ASC");
echo "Total banks in database: " . count($results) . "\n";
foreach ($results as $row) {
    echo "ID {$row['id']}: {$row['name']} (Status: {$row['status']})\n";
}

echo "\nBanks update completed successfully!\n";
