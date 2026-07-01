<?php
/**
 * Runner for Task Update Timestamp Property Test
 * **Feature: task-checklist, Property 10: Update timestamp modification**
 * **Validates: Requirements 5.4**
 */

require_once __DIR__ . '/TaskUpdateTimestampPropertyTest.php';

$test = new TaskUpdateTimestampPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
