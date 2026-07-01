<?php
/**
 * Runner for Note User Isolation Property Test
 */

require_once __DIR__ . '/NoteUserIsolationPropertyTest.php';

$test = new NoteUserIsolationPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
