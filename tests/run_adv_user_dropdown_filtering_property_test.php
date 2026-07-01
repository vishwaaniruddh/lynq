<?php
/**
 * Runner for ADV User Dropdown Filtering Property Test
 * **Feature: lho-manager-assignment, Property 11: ADV User Dropdown Filtering**
 * **Validates: Requirements 1.1**
 */

require_once __DIR__ . '/AdvUserDropdownFilteringPropertyTest.php';

$test = new AdvUserDropdownFilteringPropertyTest();
$passed = $test->runTests();

exit($passed ? 0 : 1);
