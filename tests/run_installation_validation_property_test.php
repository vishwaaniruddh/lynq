<?php
/**
 * Runner for Installation Validation Property Test
 * 
 * **Feature: installation-module, Property 6: Installation form required field validation**
 * **Validates: Requirements 3.3**
 */

require_once __DIR__ . '/InstallationValidationPropertyTest.php';

$test = new InstallationValidationPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
