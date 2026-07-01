<?php
/**
 * Runner for Asset Status on Acceptance Property Test
 * **Feature: dispatch-workflow-fixes, Property 2: Asset Status Transition on Acceptance**
 * **Validates: Requirements 4.1, 5.2**
 */

require_once __DIR__ . '/AssetStatusOnAcceptancePropertyTest.php';

$test = new AssetStatusOnAcceptancePropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
