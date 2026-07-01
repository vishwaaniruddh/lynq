<?php
/**
 * Runner for Feasibility Check Property Tests
 * **Feature: feasibility-module, Property 7, 8**
 * **Validates: Requirements 4.3, 4.4**
 */

require_once __DIR__ . '/FeasibilityCheckPropertyTest.php';

echo "========================================\n";
echo "Feasibility Check Property Tests\n";
echo "========================================\n\n";

$test = new FeasibilityCheckPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All Feasibility Check property tests PASSED\n";
    exit(0);
} else {
    echo "Some Feasibility Check property tests FAILED\n";
    exit(1);
}
