<?php
/**
 * Runner for Bio Length Constraint Property Test
 */

require_once __DIR__ . '/BioLengthConstraintPropertyTest.php';

$test = new BioLengthConstraintPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
