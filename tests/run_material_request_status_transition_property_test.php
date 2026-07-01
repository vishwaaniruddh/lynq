<?php
/**
 * Runner for Material Request Status Transition Property Test
 * **Feature: material-request-module, Property 6: Status Transition Validity**
 * **Validates: Requirements 5.2, 5.3, 5.4, 9.7**
 */

require_once __DIR__ . '/MaterialRequestStatusTransitionPropertyTest.php';

echo "Starting Material Request Status Transition Property Tests...\n\n";

$test = new MaterialRequestStatusTransitionPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "All Material Request Status Transition property tests PASSED!\n";
    exit(0);
} else {
    echo "Some Material Request Status Transition property tests FAILED!\n";
    exit(1);
}
