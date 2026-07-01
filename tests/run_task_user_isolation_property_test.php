<?php
/**
 * Runner for Task User Isolation Property Test
 */

require_once __DIR__ . '/TaskUserIsolationPropertyTest.php';

$test = new TaskUserIsolationPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
