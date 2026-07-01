<?php
/**
 * Runner for ADA Property Tests
 * **Feature: feasibility-module, Property 5**
 * **Validates: Requirements 3.4, 3.5**
 */

require_once __DIR__ . '/ADAPropertyTest.php';

echo "========================================\n";
echo "ADA Property Tests\n";
echo "========================================\n\n";

$test = new ADAPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All ADA property tests PASSED\n";
    exit(0);
} else {
    echo "Some ADA property tests FAILED\n";
    exit(1);
}
