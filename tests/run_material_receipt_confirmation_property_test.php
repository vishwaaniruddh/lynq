<?php
/**
 * Runner for Material Receipt Confirmation Property Test
 * 
 * **Feature: installation-module, Property 8: Material receipt confirmation updates status and records data**
 * **Validates: Requirements 4.2, 4.3**
 */

require_once __DIR__ . '/MaterialReceiptConfirmationPropertyTest.php';

$test = new MaterialReceiptConfirmationPropertyTest();
$success = $test->runTests();
exit($success ? 0 : 1);
