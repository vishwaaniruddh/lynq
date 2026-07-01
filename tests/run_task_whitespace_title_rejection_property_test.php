<?php
/**
 * Runner for Task Whitespace Title Rejection Property Test
 */

require_once __DIR__ . '/TaskWhitespaceTitleRejectionPropertyTest.php';

$test = new TaskWhitespaceTitleRejectionPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
