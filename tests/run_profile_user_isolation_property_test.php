<?php
/**
 * Runner for Profile User Isolation Property Test
 */

require_once __DIR__ . '/ProfileUserIsolationPropertyTest.php';

$test = new ProfileUserIsolationPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
