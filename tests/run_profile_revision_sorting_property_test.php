<?php
/**
 * Runner for Profile Revision Sorting Property Test
 */

require_once __DIR__ . '/ProfileRevisionSortingPropertyTest.php';

$test = new ProfileRevisionSortingPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
