<?php
/**
 * Runner for User Deletion Cascade Property Test
 * **Feature: lho-manager-assignment, Property 6: User Deletion Cascade**
 * **Validates: Requirements 4.1**
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/UserDeletionCascadePropertyTest.php';

echo "Starting User Deletion Cascade Property Tests...\n";
echo "================================================\n\n";

$test = new UserDeletionCascadePropertyTest();
$result = $test->runTests();

echo "\n================================================\n";
echo $result ? "All tests PASSED\n" : "Some tests FAILED\n";
echo "================================================\n";

exit($result ? 0 : 1);
