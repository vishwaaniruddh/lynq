<?php
/**
 * Runner for Installation Form Access Property Test
 * 
 * **Feature: installation-module, Property 4: Form access control based on material receipt status**
 * **Validates: Requirements 2.4, 2.5**
 */

require_once __DIR__ . '/InstallationFormAccessPropertyTest.php';

$test = new InstallationFormAccessPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
