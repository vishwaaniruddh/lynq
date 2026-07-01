<?php
/**
 * Runner for Section Rejection Validation Property Test
 * 
 * **Feature: installation-module, Property 14: Section rejection validation**
 * **Validates: Requirements 12.3**
 */

require_once __DIR__ . '/SectionRejectionValidationPropertyTest.php';

$test = new SectionRejectionValidationPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
