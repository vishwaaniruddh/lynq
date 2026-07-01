<?php
/**
 * Runner for Material Status Consistency Property Test
 * **Feature: material-request-module, Property 4: Material Status Consistency**
 * **Validates: Requirements 2.2, 2.3, 2.4, 2.5, 2.6**
 */

require_once __DIR__ . '/MaterialStatusConsistencyPropertyTest.php';

echo "Running Material Status Consistency Property Tests...\n";
echo str_repeat("=", 60) . "\n\n";

$test = new MaterialStatusConsistencyPropertyTest();
$passed = $test->runTests();

echo "\n" . str_repeat("=", 60) . "\n";
echo $passed ? "All tests PASSED!\n" : "Some tests FAILED!\n";

exit($passed ? 0 : 1);
