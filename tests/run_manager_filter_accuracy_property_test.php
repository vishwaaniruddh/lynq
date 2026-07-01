<?php
/**
 * Runner for Manager Filter Accuracy Property Test
 */

require_once __DIR__ . '/ManagerFilterAccuracyPropertyTest.php';

$test = new ManagerFilterAccuracyPropertyTest();
$passed = $test->runTests();

exit($passed ? 0 : 1);
