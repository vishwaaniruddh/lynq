<?php
/**
 * Runner for Section Approval Property Test
 * 
 * **Feature: installation-module, Property 13: Section approval creates review record**
 * **Validates: Requirements 12.2**
 */

require_once __DIR__ . '/SectionApprovalPropertyTest.php';

$test = new SectionApprovalPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
