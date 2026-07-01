<?php
/**
 * Test Runner: ADV Rejection Status Property Test
 * 
 * **Feature: installation-module, Property 19: ADV rejection status transition**
 * **Validates: Requirements 13.4**
 */

require_once __DIR__ . '/AdvRejectionStatusPropertyTest.php';

$test = new AdvRejectionStatusPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
