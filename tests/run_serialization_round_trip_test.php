<?php
/**
 * Test Runner: Serialization Round-Trip Property Test
 * 
 * **Feature: adv-crm-inventory-module, Property 16: Serialization Round-Trip**
 * **Validates: Requirements 15.4**
 */

require_once __DIR__ . '/SerializationRoundTripTest.php';

$test = new SerializationRoundTripTest();
$success = $test->runTests();
exit($success ? 0 : 1);
