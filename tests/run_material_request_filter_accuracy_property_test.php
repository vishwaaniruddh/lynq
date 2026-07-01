<?php
/**
 * Runner for Material Request Filter Accuracy Property Test
 * **Feature: material-request-module, Property 9: Filter Accuracy**
 * **Validates: Requirements 4.2**
 */

require_once __DIR__ . '/MaterialRequestFilterAccuracyPropertyTest.php';

echo "Starting Material Request Filter Accuracy Property Tests...\n\n";

$test = new MaterialRequestFilterAccuracyPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "All Material Request Filter Accuracy property tests PASSED!\n";
    exit(0);
} else {
    echo "Some Material Request Filter Accuracy property tests FAILED!\n";
    exit(1);
}
