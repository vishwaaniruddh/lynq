<?php
/**
 * Runner for Profile Data Round-Trip Property Test
 */

require_once __DIR__ . '/ProfileDataRoundTripPropertyTest.php';

$test = new ProfileDataRoundTripPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
