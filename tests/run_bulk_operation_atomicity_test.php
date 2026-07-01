<?php
/**
 * Test Runner for Bulk Operation Atomicity Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 17: Bulk Operation Atomicity**
 * **Validates: Requirements 4.4**
 */

require_once __DIR__ . '/BulkOperationAtomicityTest.php';

echo "========================================\n";
echo "Bulk Operation Atomicity Property Test\n";
echo "========================================\n\n";

$test = new BulkOperationAtomicityTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "RESULT: ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "RESULT: SOME TESTS FAILED\n";
    exit(1);
}
