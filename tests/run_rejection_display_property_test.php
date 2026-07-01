<?php
/**
 * Runner for Rejection Display Property Test
 * 
 * **Feature: installation-module, Property 26: Rejection display with highlighted sections**
 * **Validates: Requirements 16.1, 16.2**
 */

require_once __DIR__ . '/RejectionDisplayPropertyTest.php';

$test = new RejectionDisplayPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
