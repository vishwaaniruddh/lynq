<?php
/**
 * Runner for Installation Submission Property Test
 * 
 * **Feature: installation-module, Property 8: Installation submission updates status**
 * **Validates: Requirements 3.5**
 */

require_once __DIR__ . '/InstallationSubmissionPropertyTest.php';

$test = new InstallationSubmissionPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
