<?php
/**
 * Test Runner: Export Permission Consistency Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 15: Export Permission Consistency**
 * **Validates: Requirements 15.2**
 */

require_once __DIR__ . '/ExportPermissionConsistencyTest.php';

$test = new ExportPermissionConsistencyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
