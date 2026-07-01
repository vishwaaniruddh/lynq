<?php
/**
 * Runner for Installation Button After ADA Property Test
 * 
 * **Feature: installation-module, Property 7: Installation button visibility after ADA**
 * **Validates: Requirements 3.6**
 */

require_once __DIR__ . '/InstallationButtonAfterADAPropertyTest.php';

$test = new InstallationButtonAfterADAPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
