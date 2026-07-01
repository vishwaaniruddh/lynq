<?php
/**
 * Run Pagination State Preservation Property Test
 * 
 * **Feature: crm-master-modules, Property 14: Pagination State Preservation**
 * **Validates: Requirements 10.4**
 */

require_once __DIR__ . '/PaginationStatePreservationTest.php';

$test = new PaginationStatePreservationTest();
$success = $test->runAllTests();
exit($success ? 0 : 1);
