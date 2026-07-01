<?php
/**
 * Run Inventory Model Validation Unit Tests
 * Tests Product validation (required fields, enum values)
 * Tests Asset status transitions
 * Tests Warehouse unique constraint
 * 
 * Requirements: 2.1, 6.1, 1.4
 */

require_once __DIR__ . '/InventoryModelValidationTest.php';

echo "========================================\n";
echo "Inventory Model Validation Unit Tests\n";
echo "========================================\n\n";

$test = new InventoryModelValidationTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
