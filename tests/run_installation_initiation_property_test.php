<?php
/**
 * Runner for Installation Initiation Property Test
 * 
 * **Feature: installation-module, Property 2: Installation initiation creates record with correct initial status**
 * **Validates: Requirements 1.2, 1.3**
 */

require_once __DIR__ . '/InstallationInitiationPropertyTest.php';

$test = new InstallationInitiationPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
