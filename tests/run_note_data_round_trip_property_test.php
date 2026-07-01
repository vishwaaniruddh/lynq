<?php
/**
 * Runner for Note Data Round-Trip Property Test
 */

require_once __DIR__ . '/NoteDataRoundTripPropertyTest.php';

$test = new NoteDataRoundTripPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
