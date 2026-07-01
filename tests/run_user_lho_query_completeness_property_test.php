<?php
/**
 * Runner for User LHO Query Completeness Property Test
 * **Feature: lho-manager-assignment, Property 5: User LHO Query Completeness**
 * **Validates: Requirements 3.1, 3.2**
 */

require_once __DIR__ . '/UserLhoQueryCompletenessPropertyTest.php';

echo "Starting User LHO Query Completeness Property Test...\n\n";

$test = new UserLhoQueryCompletenessPropertyTest();
$result = $test->runTests();

echo "\n";
if ($result) {
    echo "All property tests PASSED!\n";
    exit(0);
} else {
    echo "Some property tests FAILED!\n";
    exit(1);
}
