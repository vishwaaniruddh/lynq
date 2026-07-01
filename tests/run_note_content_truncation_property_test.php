<?php
/**
 * Runner for Note Content Truncation Property Test
 * **Feature: notes-module, Property 6: Content Preview Truncation**
 * **Validates: Requirements 5.2**
 */

require_once __DIR__ . '/NoteContentTruncationPropertyTest.php';

$test = new NoteContentTruncationPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
