<?php
/**
 * Runner for Material Request Pagination Consistency Property Test
 * **Feature: material-request-module, Property 10: Pagination Consistency**
 * **Validates: Requirements 4.5**
 */

require_once __DIR__ . '/MaterialRequestPaginationConsistencyPropertyTest.php';

echo "Starting Material Request Pagination Consistency Property Tests...\n\n";

$test = new MaterialRequestPaginationConsistencyPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "All Material Request Pagination Consistency property tests PASSED!\n";
    exit(0);
} else {
    echo "Some Material Request Pagination Consistency property tests FAILED!\n";
    exit(1);
}
