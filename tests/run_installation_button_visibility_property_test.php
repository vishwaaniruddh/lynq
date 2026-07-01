<?php
/**
 * Runner for Installation Button Visibility Property Test
 * 
 * **Feature: installation-module, Property 1: Initiate Installation button visibility based on feasibility status**
 * **Validates: Requirements 1.1, 1.6, 1.7**
 */

require_once __DIR__ . '/InstallationButtonVisibilityPropertyTest.php';

$test = new InstallationButtonVisibilityPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
