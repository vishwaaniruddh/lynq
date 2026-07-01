<?php
/**
 * Runner for Profile Revision Creation Property Test
 */

require_once __DIR__ . '/ProfileRevisionCreationPropertyTest.php';

$test = new ProfileRevisionCreationPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
