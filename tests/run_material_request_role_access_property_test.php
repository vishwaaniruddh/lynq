<?php
/**
 * Runner for Material Request Role-Based Access Property Test
 * **Feature: material-request-module, Property 7: Role-Based Access Control**
 * **Validates: Requirements 6.1, 6.4, 7.1**
 */

require_once __DIR__ . '/MaterialRequestRoleAccessPropertyTest.php';

echo "Starting Material Request Role-Based Access Property Tests...\n\n";

$test = new MaterialRequestRoleAccessPropertyTest();
$passed = $test->runTests();

echo "\n";
if ($passed) {
    echo "All Material Request Role-Based Access property tests PASSED!\n";
    exit(0);
} else {
    echo "Some Material Request Role-Based Access property tests FAILED!\n";
    exit(1);
}
