<?php
/**
 * Runner for Date of Birth Validation Property Test
 */

require_once __DIR__ . '/DateOfBirthValidationPropertyTest.php';

$test = new DateOfBirthValidationPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
