<?php
/**
 * Runner for Contractor Review Panel Visibility Property Test
 * 
 * **Feature: installation-module, Property 17: Contractor review panel visibility**
 * **Validates: Requirements 14.1**
 */

require_once __DIR__ . '/ContractorReviewPanelVisibilityPropertyTest.php';

$test = new ContractorReviewPanelVisibilityPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
