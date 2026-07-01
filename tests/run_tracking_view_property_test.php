<?php
/**
 * Run Tracking View Property Tests
 * Tests Property 13 and Property 14 for feasibility tracking
 */

require_once __DIR__ . '/TrackingViewPropertyTest.php';

echo "========================================\n";
echo "Tracking View Property Tests\n";
echo "========================================\n\n";

$test = new TrackingViewPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All tracking view property tests PASSED\n";
    exit(0);
} else {
    echo "Some tracking view property tests FAILED\n";
    exit(1);
}
