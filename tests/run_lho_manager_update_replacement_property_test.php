<?php
/**
 * Runner for LHO Manager Update Replacement Property Test
 * **Feature: lho-manager-assignment, Property 2: Manager Update Replacement**
 * **Validates: Requirements 1.4**
 */

require_once __DIR__ . '/LhoManagerUpdateReplacementPropertyTest.php';

echo "Starting LHO Manager Update Replacement Property Tests...\n\n";

$test = new LhoManagerUpdateReplacementPropertyTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
