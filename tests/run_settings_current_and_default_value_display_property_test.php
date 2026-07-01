<?php
/**
 * Test Runner for Settings Current and Default Value Display Property Test
 */

require_once 'SettingsCurrentAndDefaultValueDisplayPropertyTest.php';

$test = new SettingsCurrentAndDefaultValueDisplayPropertyTest();
$success = $test->runAllTests();

exit($success ? 0 : 1);