<?php
/**
 * Test Runner for Settings Category Ordering Consistency Property Test
 */

require_once 'SettingsCategoryOrderingConsistencyPropertyTest.php';

$test = new SettingsCategoryOrderingConsistencyPropertyTest();
$success = $test->runAllTests();

exit($success ? 0 : 1);