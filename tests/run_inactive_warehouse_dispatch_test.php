<?php
/**
 * Runner for Inactive Warehouse Dispatch Prevention Property Test
 * **Feature: adv-crm-inventory-module, Property 18: Inactive Warehouse Dispatch Prevention**
 * **Validates: Requirements 1.3**
 */

require_once __DIR__ . '/InactiveWarehouseDispatchPreventionTest.php';

echo "Starting Inactive Warehouse Dispatch Prevention Property Test...\n";
echo "================================================================\n\n";

$test = new InactiveWarehouseDispatchPreventionTest();
$success = $test->runTests();

echo "\n================================================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}

exit($success ? 0 : 1);
