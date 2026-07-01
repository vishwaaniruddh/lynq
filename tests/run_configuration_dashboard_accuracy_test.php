<?php
/**
 * Test Runner: Configuration Dashboard Accuracy Property Test
 * 
 * **Feature: ip-configuration-management, Property 18 & 19**
 * **Validates: Requirements 7.1, 7.2**
 */

require_once __DIR__ . '/ConfigurationDashboardAccuracyTest.php';

echo "Starting Configuration Dashboard Accuracy Property Tests...\n\n";

$test = new ConfigurationDashboardAccuracyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
