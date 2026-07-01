<?php
/**
 * Test Runner: Installation Export Round-Trip Property Test
 * 
 * **Feature: installation-module, Property 29: Installation export round-trip**
 * **Validates: Requirements 16.4**
 * 
 * Run this script to execute the installation export round-trip property tests.
 * 
 * Usage: php tests/run_installation_export_round_trip_test.php
 */

require_once __DIR__ . '/InstallationExportRoundTripTest.php';

echo "Starting Installation Export Round-Trip Property Tests...\n";
echo "============================================================\n";

$test = new InstallationExportRoundTripTest();
$success = $test->runTests();

echo "\n============================================================\n";
if ($success) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
