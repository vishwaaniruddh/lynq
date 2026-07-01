<?php
/**
 * Run Feasibility Export Property Tests
 * Tests Property 15 for feasibility export round-trip
 */

require_once __DIR__ . '/FeasibilityExportPropertyTest.php';

echo "========================================\n";
echo "Feasibility Export Property Tests\n";
echo "========================================\n\n";

$test = new FeasibilityExportPropertyTest();
$passed = $test->runTests();

echo "\n========================================\n";
if ($passed) {
    echo "All feasibility export property tests PASSED\n";
    exit(0);
} else {
    echo "Some feasibility export property tests FAILED\n";
    exit(1);
}
