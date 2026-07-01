<?php
/**
 * Runner for Installation Verification Data Round-Trip Property Test
 */

require_once __DIR__ . '/InstallationVerificationDataRoundTripTest.php';

$test = new InstallationVerificationDataRoundTripTest();
$success = $test->runTests();
exit($success ? 0 : 1);
