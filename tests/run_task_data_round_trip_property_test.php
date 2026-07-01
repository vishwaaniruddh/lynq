<?php
/**
 * Runner for Task Data Round-Trip Property Test
 * **Feature: task-checklist, Property 1: Task data round-trip**
 * **Validates: Requirements 1.1, 1.4, 5.2**
 */

require_once __DIR__ . '/TaskDataRoundTripPropertyTest.php';

$test = new TaskDataRoundTripPropertyTest();
$passed = $test->runAllTests();

exit($passed ? 0 : 1);
