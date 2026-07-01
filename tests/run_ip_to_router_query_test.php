<?php
/**
 * Test Runner for IP-to-Router Query Property Test
 * 
 * **Feature: ip-configuration-management, Property 15: IP-to-Router Query**
 * **Validates: Requirements 5.4**
 */

require_once __DIR__ . '/IPToRouterQueryTest.php';

echo "=== IP-to-Router Query Property Test ===\n";
echo "Testing Property 15: For any configured IP_Master query, the response SHALL include the bound router serial number.\n\n";

$test = new IPToRouterQueryTest();
$result = $test->runAllTests();

echo "\n=== Test Complete ===\n";
exit($result ? 0 : 1);
