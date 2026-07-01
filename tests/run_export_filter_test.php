<?php
/**
 * Run Export Filter Consistency Property Test
 * 
 * **Feature: crm-master-modules, Property 13: Export Filter Consistency**
 * **Validates: Requirements 1.6, 10.3**
 */

require_once __DIR__ . '/ExportFilterConsistencyTest.php';

$test = new ExportFilterConsistencyTest();
$success = $test->runAllTests();
exit($success ? 0 : 1);
