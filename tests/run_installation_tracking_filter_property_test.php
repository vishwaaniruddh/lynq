<?php
/**
 * Runner for Installation Tracking Filter Property Test
 * 
 * **Feature: installation-module, Property 28: Tracking filter returns correct results**
 * **Validates: Requirements 16.3**
 */

require_once __DIR__ . '/InstallationTrackingFilterPropertyTest.php';

$test = new InstallationTrackingFilterPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
