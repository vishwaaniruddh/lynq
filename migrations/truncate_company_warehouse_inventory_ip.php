<?php
require_once __DIR__ . '/../config/autoload.php';

$db = DatabaseConfig::getInstance();
$conn = $db->getConnection();

echo "Starting truncation of Company, Warehouse, Inventory, and IP Configuration Records...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tables = [
    'warehouses',
    'ip_master',
    'ip_restrictions',
    'ip_locks',
    'material_master_items',
    'material_masters',
    'products',
    'product_categories',
    'companies'
];

foreach ($tables as $table) {
    if ($conn->query("TRUNCATE TABLE `{$table}`")) {
        echo "✔ Truncated: {$table}\n";
    } else {
        echo "✖ Error truncating {$table}: " . $conn->error . "\n";
    }
}

// Re-seed essential primary companies for system operation
echo "\nRe-seeding core companies...\n";
$conn->query("INSERT INTO `companies` (`id`, `name`, `type`, `status`, `contact_email`) VALUES
    (1, 'ADV', 'ADV', 'ACTIVE', 'admin@advantagesb.com'),
    (2, 'Cleared Secured Services', 'CONTRACTOR', 'ACTIVE', 'contact@clearedsecured.com')
");
echo "✔ Seeded Company ID 1 (ADV) and Company ID 2 (Cleared Secured Services)\n";

// Update any test users referencing non-existent company IDs to Company 2
$conn->query("UPDATE `users` SET `company_id` = 2 WHERE `company_id` NOT IN (1, 2)");
echo "✔ Aligned user company references to active companies\n";

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\nCompleted successfully!\n";
