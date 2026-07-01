<?php
/**
 * Runner for Contact Number Validation Property Test
 */

require_once __DIR__ . '/ContactNumberValidationPropertyTest.php';

$test = new ContactNumberValidationPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
