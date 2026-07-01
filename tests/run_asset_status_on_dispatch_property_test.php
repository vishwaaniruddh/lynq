<?php
/**
 * Runner for Asset Status on Dispatch Property Test
 * **Feature: dispatch-workflow-fixes, Property 1: Asset Status Transition on Dispatch**
 * **Validates: Requirements 2.1, 4.1**
 */

require_once __DIR__ . '/AssetStatusOnDispatchPropertyTest.php';

$test = new AssetStatusOnDispatchPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
