<?php
/**
 * Test Runner for User Management Tests
 * Runs all property tests and unit tests related to user management
 */

require_once __DIR__ . '/../config/autoload.php';

echo "========================================\n";
echo "User Management Tests\n";
echo "========================================\n\n";

$results = [];

// Run ADV User Company Assignment Freedom Test (Property 1)
echo "Running Property Test 1: ADV User Company Assignment Freedom\n";
echo "----------------------------------------\n";
require_once __DIR__ . '/AdvUserCompanyAssignmentTest.php';
$test1 = new AdvUserCompanyAssignmentTest();
$results['Property 1: ADV User Company Assignment Freedom'] = $test1->runTests();
echo "\n";

// Run User CRUD Unit Tests
echo "Running Unit Tests: User CRUD Operations\n";
echo "----------------------------------------\n";
require_once __DIR__ . '/UserCrudTest.php';
$test2 = new UserCrudTest();
$results['Unit Tests: User CRUD Operations'] = $test2->runTests();
echo "\n";

// Summary
echo "========================================\n";
echo "Test Summary\n";
echo "========================================\n";

$passed = 0;
$failed = 0;

foreach ($results as $testName => $result) {
    if ($result) {
        echo "✓ $testName\n";
        $passed++;
    } else {
        echo "✗ $testName\n";
        $failed++;
    }
}

echo "\n";
echo "Total: $passed passed, $failed failed\n";

exit($failed === 0 ? 0 : 1);
