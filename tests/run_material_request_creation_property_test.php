<?php
/**
 * Runner for Material Request Creation Property Test
 * **Feature: material-request-module, Property 5: Material Request Creation and Duplicate Prevention**
 * **Validates: Requirements 3.3, 3.4, 3.5, 3.6**
 */

require_once __DIR__ . '/MaterialRequestCreationPropertyTest.php';

echo "Starting Material Request Creation Property Tests...\n\n";

$test = new MaterialRequestCreationPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "All Material Request Creation property tests PASSED!\n";
    exit(0);
} else {
    echo "Some Material Request Creation property tests FAILED!\n";
    exit(1);
}
