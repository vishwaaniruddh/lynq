<?php
/**
 * Runner for Note Search Filter Property Test
 */

require_once __DIR__ . '/NoteSearchFilterPropertyTest.php';

$test = new NoteSearchFilterPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
