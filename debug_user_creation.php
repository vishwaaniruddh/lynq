<?php
require_once 'config/autoload.php';

$userModel = new User();
$companyModel = new Company();

echo "Testing user creation and lookup...\n";

// Get existing ADV company
$advCompanies = $companyModel->findByType('ADV');
if (empty($advCompanies)) {
    echo "No ADV companies found!\n";
    exit(1);
}

$advCompany = $advCompanies[0];
echo "Using ADV company: {$advCompany['name']} (ID: {$advCompany['id']})\n";

// Create test user
$userData = [
    'username' => 'debuguser_' . time(),
    'email' => 'debuguser_' . time() . '@test.com',
    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
    'first_name' => 'Debug',
    'last_name' => 'User',
    'company_id' => $advCompany['id'],
    'role_id' => 1,
    'status' => 1
];

echo "Creating user with data:\n";
print_r($userData);

try {
    $userId = $userModel->create($userData);
    echo "User created with ID: $userId\n";
    
    // Try to find the user
    $user = $userModel->find($userId);
    echo "User found with find():\n";
    print_r($user);
    
    // Try to find with relations
    $userWithRelations = $userModel->findWithRelations($userId);
    echo "User found with findWithRelations():\n";
    print_r($userWithRelations);
    
    // Clean up
    $userModel->delete($userId);
    echo "User deleted\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}