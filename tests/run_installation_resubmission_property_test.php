<?php
/**
 * Test Runner for Installation Resubmission Property Test
 * 
 * **Feature: installation-module, Property 24: Installation resubmission status reset**
 * **Validates: Requirements 14.5**
 */

require_once __DIR__ . '/InstallationResubmissionPropertyTest.php';

$test = new InstallationResubmissionPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
