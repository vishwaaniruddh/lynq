<?php
/**
 * Runner for LHO Manager Display Completeness Property Test
 */

require_once __DIR__ . '/LhoManagerDisplayCompletenessPropertyTest.php';

$test = new LhoManagerDisplayCompletenessPropertyTest();
$passed = $test->runTests();

exit($passed ? 0 : 1);
