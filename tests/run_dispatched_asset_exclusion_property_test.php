<?php
/**
 * Runner for Dispatched Asset Exclusion Property Test
 * **Feature: dispatch-workflow-fixes, Property 6: Dispatched Asset Exclusion from Available Inventory**
 * **Validates: Requirements 2.3**
 */

require_once __DIR__ . '/DispatchedAssetExclusionPropertyTest.php';

$test = new DispatchedAssetExclusionPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
