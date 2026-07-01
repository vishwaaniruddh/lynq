<?php
/**
 * Runner for ETA Property Tests
 * **Feature: feasibility-module, Property 2, 3, 4**
 * **Validates: Requirements 2.2, 2.3, 2.4, 2.5**
 */

require_once __DIR__ . '/ETAPropertyTest.php';

echo "========================================\n";
echo "ETA Property Tests\n";
echo "========================================\n\n";

$test = new ETAPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All ETA property tests PASSED\n";
    exit(0);
} else {
    echo "Some ETA property tests FAILED\n";
    exit(1);
}
