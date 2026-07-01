<?php
/**
 * Runner for Installation Assignment Property Test
 * 
 * **Feature: installation-module, Property 4: Engineer assignment updates status to pending_eta**
 * **Validates: Requirements 2.4**
 */

require_once __DIR__ . '/InstallationAssignmentPropertyTest.php';

$test = new InstallationAssignmentPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
