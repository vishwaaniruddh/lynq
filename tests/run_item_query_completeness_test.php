<?php
/**
 * Test Runner for Item Query Completeness Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 20: Item Query Completeness**
 * **Validates: Requirements 6.2, 12.4**
 * 
 * Run this script to execute the property test:
 * php run_item_query_completeness_test.php
 */

require_once __DIR__ . '/ItemQueryCompletenessTest.php';

echo "========================================\n";
echo "Item Query Completeness Property Test\n";
echo "========================================\n\n";

$test = new ItemQueryCompletenessTest();
$result = $test->runTests();

echo "\n========================================\n";
if ($result) {
    echo "TEST RESULT: PASSED\n";
    exit(0);
} else {
    echo "TEST RESULT: FAILED\n";
    exit(1);
}
