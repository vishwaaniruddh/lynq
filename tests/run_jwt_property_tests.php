<?php
/**
 * Runner for JWT Property Tests
 * 
 * **Feature: jwt-authentication**
 */

require_once __DIR__ . '/JWTPropertyTest.php';

$test = new JWTPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
