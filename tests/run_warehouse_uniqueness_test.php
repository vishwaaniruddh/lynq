<?php
/**
 * Run Warehouse Name Uniqueness Property Tests
 * **Feature: adv-crm-inventory-module, Property 1: Warehouse Name Uniqueness Within Company**
 * **Validates: Requirements 1.4**
 */

require_once __DIR__ . '/WarehouseNameUniquenessTest.php';

echo "========================================\n";
echo "Warehouse Name Uniqueness Property Tests\n";
echo "========================================\n\n";

$test = new WarehouseNameUniquenessTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
