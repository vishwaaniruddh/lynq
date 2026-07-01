<?php
require_once 'config/autoload.php';

$db = DatabaseConfig::getInstance();

echo "Companies:\n";
$companies = $db->getResults('SELECT * FROM companies');
foreach ($companies as $company) {
    echo "ID: {$company['id']}, Name: {$company['name']}, Type: {$company['type']}\n";
}

echo "\nRoles:\n";
$roles = $db->getResults('SELECT * FROM roles');
foreach ($roles as $role) {
    echo "ID: {$role['id']}, Name: {$role['name']}, Company Type: {$role['company_type']}\n";
}

echo "\nUsers:\n";
$users = $db->getResults('SELECT u.*, c.name as company_name, c.type as company_type FROM users u LEFT JOIN companies c ON u.company_id = c.id');
foreach ($users as $user) {
    echo "ID: {$user['id']}, Username: {$user['username']}, Company: {$user['company_name']} ({$user['company_type']})\n";
}