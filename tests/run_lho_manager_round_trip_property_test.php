<?php
/**
 * Runner for LHO Manager Round-Trip Property Test
 * **Feature: lho-manager-assignment, Property 1: Manager Assignment Round Trip**
 * **Validates: Requirements 1.3**
 */

require_once __DIR__ . '/LhoManagerRoundTripPropertyTest.php';

echo "Starting LHO Manager Round-Trip Property Tests...\n\n";

$test = new LhoManagerRoundTripPropertyTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
