<?php
/**
 * Test Runner: ADV Approval Status Property Test
 * 
 * **Feature: installation-module, Property 18: ADV approval status transition**
 * **Validates: Requirements 13.3**
 */

require_once __DIR__ . '/AdvApprovalStatusPropertyTest.php';

$test = new AdvApprovalStatusPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
