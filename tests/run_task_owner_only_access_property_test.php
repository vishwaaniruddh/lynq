<?php
/**
 * Runner for Task Owner-Only Access Property Test
 */

require_once __DIR__ . '/TaskOwnerOnlyAccessPropertyTest.php';

$test = new TaskOwnerOnlyAccessPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
