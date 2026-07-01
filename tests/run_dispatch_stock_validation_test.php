<?php
/**
 * Runner for Dispatch Stock Validation Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 4: Dispatch Stock Validation**
 * **Validates: Requirements 5.2**
 */

require_once __DIR__ . '/DispatchStockValidationTest.php';

echo "========================================\n";
echo "Dispatch Stock Validation Property Test\n";
echo "========================================\n\n";

$test = new DispatchStockValidationTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All property tests PASSED\n";
    exit(0);
} else {
    echo "Some property tests FAILED\n";
    exit(1);
}
