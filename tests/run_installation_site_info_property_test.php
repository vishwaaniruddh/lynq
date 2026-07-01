<?php
/**
 * Runner for Installation Site Info Property Test
 * 
 * **Feature: installation-module, Property 5: Installation form displays pre-populated site information**
 * **Validates: Requirements 3.1**
 */

require_once __DIR__ . '/InstallationSiteInfoPropertyTest.php';

$test = new InstallationSiteInfoPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
