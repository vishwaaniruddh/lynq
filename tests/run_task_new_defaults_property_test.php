<?php
/**
 * Runner for Task New Defaults Property Test
 */

require_once __DIR__ . '/TaskNewDefaultsPropertyTest.php';

$test = new TaskNewDefaultsPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
