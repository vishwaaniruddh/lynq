<?php
/**
 * Runner for Task Completion Toggle Property Test
 * **Feature: task-checklist, Property 7: Completion toggle round-trip**
 * **Validates: Requirements 3.1, 3.2**
 */

require_once __DIR__ . '/TaskCompletionTogglePropertyTest.php';

$test = new TaskCompletionTogglePropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
