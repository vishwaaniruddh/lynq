<?php
/**
 * Test Runner for Export Round-Trip Configuration Test
 * 
 * **Feature: ip-configuration-management, Property 23: Export Data Round-Trip**
 * **Validates: Requirements 8.4**
 */

require_once __DIR__ . '/ExportRoundTripConfigTest.php';

echo "=== Export Round-Trip Configuration Property Tests ===\n";
echo "**Feature: ip-configuration-management, Property 23: Export Data Round-Trip**\n";
echo "**Validates: Requirements 8.4**\n\n";

$test = new ExportRoundTripConfigTest();
$results = $test->runAllTests();

$allPassed = !in_array(false, $results, true);

echo "\n=== Final Result ===\n";
if ($allPassed) {
    echo "✓ All export round-trip property tests PASSED\n";
    exit(0);
} else {
    echo "✗ Some export round-trip property tests FAILED\n";
    exit(1);
}
