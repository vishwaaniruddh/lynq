<?php
/**
 * Runner for Task List Field Completeness Property Test
 * **Feature: task-checklist, Property 5: Task list field completeness**
 * **Validates: Requirements 2.2**
 */

require_once __DIR__ . '/TaskListFieldCompletenessPropertyTest.php';

$test = new TaskListFieldCompletenessPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
