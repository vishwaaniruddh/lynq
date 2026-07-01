<?php
/**
 * Test Runner for Delegation Export Property Tests
 * **Feature: site-management-delegation, Property 7: Delegation export round-trip**
 * **Validates: Requirements 3.4**
 */

require_once __DIR__ . '/DelegationExportPropertyTest.php';

echo "Starting Delegation Export Property Tests...\n";
echo str_repeat("=", 60) . "\n\n";

$test = new DelegationExportPropertyTest();
$passed = $test->runTests();

echo "\n" . str_repeat("=", 60) . "\n";
echo $passed ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n";
echo str_repeat("=", 60) . "\n";

exit($passed ? 0 : 1);
