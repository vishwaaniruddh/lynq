<?php
/**
 * Runner for Inventory Counter Conservation Property Test
 * 
 * **Feature: dispatch-workflow-fixes, Property 4: Inventory Counter Conservation**
 * **Validates: Requirements 6.1, 6.2, 6.3**
 */

require_once __DIR__ . '/InventoryCounterConservationPropertyTest.php';

echo "========================================\n";
echo "Inventory Counter Conservation Property Test\n";
echo "========================================\n\n";

$test = new InventoryCounterConservationPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED\n";
    exit(1);
}
