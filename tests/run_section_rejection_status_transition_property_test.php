<?php
/**
 * Test Runner: Section Rejection Status Transition Property Test
 * 
 * **Feature: installation-module, Property 20: Section rejection status transition**
 * **Validates: Requirements 14.4, 14.6**
 */

require_once __DIR__ . '/SectionRejectionStatusTransitionPropertyTest.php';

$test = new SectionRejectionStatusTransitionPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
