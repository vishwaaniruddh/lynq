<?php
/**
 * Test Runner for ADV-Approved Immutability Property Test
 * 
 * **Feature: installation-module, Property 20: ADV-approved immutability**
 * **Validates: Requirements 13.6**
 */

require_once __DIR__ . '/AdvApprovedImmutabilityPropertyTest.php';

$test = new AdvApprovedImmutabilityPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
