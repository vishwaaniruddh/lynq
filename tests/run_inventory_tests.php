<?php
/**
 * Run All Inventory Module Tests
 * Checkpoint 3: Ensure all inventory tests pass
 * 
 * Tests:
 * - Property 1: Warehouse Name Uniqueness Within Company (Requirements 1.4)
 * - Property 2: Serial Number Global Uniqueness (Requirements 3.3)
 * - Property 11: Dashboard Count Accuracy (Requirements 9.1, 9.2)
 * - Unit Tests: Inventory Model Validation (Requirements 2.1, 6.1, 1.4)
 * - Integration Tests: Stock and Dispatch APIs (Requirements 3.1, 5.1, 5.4)
 * - Integration Tests: Dashboard APIs (Requirements 9.1, 10.1, 11.1)
 */

require_once __DIR__ . '/WarehouseNameUniquenessTest.php';
require_once __DIR__ . '/SerialNumberUniquenessTest.php';
require_once __DIR__ . '/InventoryModelValidationTest.php';
require_once __DIR__ . '/StockDispatchApiTest.php';
require_once __DIR__ . '/DashboardCountAccuracyTest.php';
require_once __DIR__ . '/DashboardApiTest.php';

echo "========================================\n";
echo "Running All Inventory Module Tests\n";
echo "========================================\n\n";

$allPassed = true;
$results = [];

// Test 1: Warehouse Name Uniqueness
echo "--- Property Test 1: Warehouse Name Uniqueness ---\n";
$test1 = new WarehouseNameUniquenessTest();
$result1 = $test1->runTests();
$results['Warehouse Name Uniqueness'] = $result1;
$allPassed = $allPassed && $result1;

// Test 2: Serial Number Uniqueness
echo "\n--- Property Test 2: Serial Number Uniqueness ---\n";
$test2 = new SerialNumberUniquenessTest();
$result2 = $test2->runTests();
$results['Serial Number Uniqueness'] = $result2;
$allPassed = $allPassed && $result2;

// Test 3: Inventory Model Validation
echo "\n--- Unit Tests: Inventory Model Validation ---\n";
$test3 = new InventoryModelValidationTest();
$result3 = $test3->runTests();
$results['Inventory Model Validation'] = $result3;
$allPassed = $allPassed && $result3;

// Test 4: Stock and Dispatch API Integration Tests
echo "\n--- Integration Tests: Stock and Dispatch APIs ---\n";
$test4 = new StockDispatchApiTest();
$result4 = $test4->runTests();
$results['Stock and Dispatch APIs'] = $result4;
$allPassed = $allPassed && $result4;

// Test 5: Dashboard Count Accuracy Property Test
echo "\n--- Property Test 11: Dashboard Count Accuracy ---\n";
$test5 = new DashboardCountAccuracyTest();
$result5 = $test5->runTests();
$results['Dashboard Count Accuracy'] = $result5;
$allPassed = $allPassed && $result5;

// Test 6: Dashboard API Integration Tests
echo "\n--- Integration Tests: Dashboard APIs ---\n";
$test6 = new DashboardApiTest();
$result6 = $test6->runTests();
$results['Dashboard APIs'] = $result6;
$allPassed = $allPassed && $result6;

// Summary
echo "\n========================================\n";
echo "Test Summary:\n";
echo "----------------------------------------\n";
foreach ($results as $name => $passed) {
    $status = $passed ? "PASSED" : "FAILED";
    echo "  $name: $status\n";
}
echo "----------------------------------------\n";

if ($allPassed) {
    echo "ALL INVENTORY TESTS PASSED!\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    echo "========================================\n";
    exit(1);
}
