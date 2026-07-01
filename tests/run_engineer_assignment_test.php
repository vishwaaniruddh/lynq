<?php
/**
 * Runner script for Engineer Assignment Property Tests
 * 
 * Usage: php run_engineer_assignment_test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/EngineerAssignmentPropTest.php';

echo "Starting Engineer Assignment Property Tests...\n\n";

$test = new EngineerAssignmentPropTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
