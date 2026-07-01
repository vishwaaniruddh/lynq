<?php
/**
 * Runner for Notes List Sorting Property Test
 */

require_once __DIR__ . '/NotesListSortingPropertyTest.php';

$test = new NotesListSortingPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
