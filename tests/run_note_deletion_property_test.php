<?php
/**
 * Runner for Note Deletion Property Test
 */

require_once __DIR__ . '/NoteDeletionPropertyTest.php';

$test = new NoteDeletionPropertyTest();
$result = $test->runAllTests();

exit($result ? 0 : 1);
