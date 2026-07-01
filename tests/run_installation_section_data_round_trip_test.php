<?php
/**
 * Runner for Installation Section Data Round-Trip Property Test
 */

require_once __DIR__ . '/InstallationSectionDataRoundTripTest.php';

$test = new InstallationSectionDataRoundTripTest();
$success = $test->runTests();
exit($success ? 0 : 1);
