<?php
/**
 * Test Runner for Zone Deletion Cascade Property Test
 * 
 * **Feature: crm-master-modules, Property 6: Zone Deletion Cascade**
 * **Validates: Requirements 5.5**
 * 
 * For any zone that is deleted, all states and cities referencing that zone 
 * should have their zone_id set to NULL while preserving the state and city records.
 */

echo "========================================\n";
echo "Property 6: Zone Deletion Cascade Test\n";
echo "========================================\n\n";

require_once __DIR__ . '/ZoneDeletionCascadeTest.php';

$test = new ZoneDeletionCascadeTest();
$result = $test->runTests();
$test->cleanupTestData();

echo "\n========================================\n";
echo "Test Summary\n";
echo "========================================\n";

if ($result) {
    echo "✓ PASSED: Property 6 - Zone Deletion Cascade\n";
    exit(0);
} else {
    echo "✗ FAILED: Property 6 - Zone Deletion Cascade\n";
    exit(1);
}
