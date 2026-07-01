<?php
/**
 * Runner for Role-Based Inventory Visibility Property Test
 * **Feature: adv-crm-inventory-module, Property 5: Role-Based Inventory Visibility**
 * **Validates: Requirements 8.1, 8.2, 8.3**
 */

require_once __DIR__ . '/RoleBasedInventoryVisibilityTest.php';

$test = new RoleBasedInventoryVisibilityTest();
$success = $test->runTests();
exit($success ? 0 : 1);
