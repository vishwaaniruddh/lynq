<?php
/**
 * Runner for Installation Data Round-Trip Property Test
 * 
 * **Feature: installation-module, Property 7: Installation data round-trip**
 * **Validates: Requirements 3.4, 17.1, 17.2, 17.3**
 */

require_once __DIR__ . '/InstallationDataRoundTripTest.php';

$test = new InstallationDataRoundTripTest();
$success = $test->runTests();
exit($success ? 0 : 1);
