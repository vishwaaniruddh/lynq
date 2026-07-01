<?php
/**
 * Runner for Profile Update Timestamp Property Test
 */

require_once __DIR__ . '/ProfileUpdateTimestampPropertyTest.php';

$test = new ProfileUpdateTimestampPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
