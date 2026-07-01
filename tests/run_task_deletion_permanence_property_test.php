<?php
/**
 * Runner for Task Deletion Permanence Property Test
 * **Feature: task-checklist, Property 9: Task deletion permanence**
 * **Validates: Requirements 4.1, 4.3**
 */

require_once __DIR__ . '/TaskDeletionPermanencePropertyTest.php';

$test = new TaskDeletionPermanencePropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
