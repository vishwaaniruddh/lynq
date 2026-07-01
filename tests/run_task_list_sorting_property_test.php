<?php
/**
 * Runner for Task List Sorting Property Test
 * **Feature: task-checklist, Property 6: Task list sorting**
 * **Validates: Requirements 2.4**
 */

require_once __DIR__ . '/TaskListSortingPropertyTest.php';

$test = new TaskListSortingPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
