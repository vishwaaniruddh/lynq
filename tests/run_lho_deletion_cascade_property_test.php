<?php
/**
 * Runner for LHO Deletion Cascade Property Test
 * **Feature: lho-manager-assignment, Property 7: LHO Deletion Cascade**
 * **Validates: Requirements 4.2**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/LhoDeletionCascadePropertyTest.php';

echo "Starting LHO Deletion Cascade Property Tests...\n";
echo "================================================\n\n";

$test = new LhoDeletionCascadePropertyTest();
$result = $test->runTests();

echo "\n================================================\n";
echo $result ? "All tests PASSED\n" : "Some tests FAILED\n";
echo "================================================\n";

exit($result ? 0 : 1);
