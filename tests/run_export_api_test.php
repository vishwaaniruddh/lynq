<?php
/**
 * Test Runner: Export API Integration Tests
 * 
 * Requirements: 15.1, 15.2
 */

require_once __DIR__ . '/ExportApiTest.php';

$test = new ExportApiTest();
$success = $test->runTests();
exit($success ? 0 : 1);
