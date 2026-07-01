<?php
/**
 * Runner for Manager Validation Property Test
 * **Feature: lho-manager-assignment, Property 8: Manager Validation**
 * **Validates: Requirements 4.3, 4.4**
 */

require_once __DIR__ . '/ManagerValidationPropertyTest.php';

$test = new ManagerValidationPropertyTest();
$passed = $test->runTests();

exit($passed ? 0 : 1);
