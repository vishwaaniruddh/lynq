<?php
/**
 * Run Warehouse and Product API Integration Tests
 * Tests CRUD operations, permission filtering, and validation errors
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.4
 */

require_once __DIR__ . '/WarehouseProductApiTest.php';

echo "========================================\n";
echo "Running Warehouse and Product API Tests\n";
echo "========================================\n\n";

$test = new WarehouseProductApiTest();
$result = $test->runTests();

echo "\n========================================\n";
if ($result) {
    echo "ALL API TESTS PASSED!\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "SOME API TESTS FAILED!\n";
    echo "========================================\n";
    exit(1);
}
