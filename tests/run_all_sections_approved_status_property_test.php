<?php
/**
 * Test Runner: All Sections Approved Status Property Test
 * 
 * **Feature: installation-module, Property 16: All sections approved triggers contractor_approved status**
 * **Validates: Requirements 12.5, 12.7**
 */

require_once __DIR__ . '/AllSectionsApprovedStatusPropertyTest.php';

$test = new AllSectionsApprovedStatusPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
