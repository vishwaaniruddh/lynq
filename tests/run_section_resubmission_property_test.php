<?php
/**
 * Runner for Section Resubmission Property Test
 * 
 * **Feature: installation-module, Property 28: Section resubmission status reset**
 * **Validates: Requirements 16.4**
 */

require_once __DIR__ . '/SectionResubmissionPropertyTest.php';

$test = new SectionResubmissionPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
