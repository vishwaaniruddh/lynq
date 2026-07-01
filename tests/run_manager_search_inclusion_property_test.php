<?php
/**
 * Runner for Manager Search Inclusion Property Test
 */

require_once __DIR__ . '/ManagerSearchInclusionPropertyTest.php';

$test = new ManagerSearchInclusionPropertyTest();
$passed = $test->runTests();

exit($passed ? 0 : 1);
