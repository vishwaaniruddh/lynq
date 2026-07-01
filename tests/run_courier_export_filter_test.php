<?php
/**
 * Runner script for Courier Export Filter Consistency Property Test
 * 
 * **Feature: crm-sidebar-restructure, Property 11: Courier Export Filter Consistency**
 * **Validates: Requirements 2.6**
 */

require_once __DIR__ . '/CourierExportFilterConsistencyTest.php';

echo "=================================================\n";
echo "Courier Export Filter Consistency Property Test\n";
echo "=================================================\n";
echo "**Feature: crm-sidebar-restructure, Property 11: Courier Export Filter Consistency**\n";
echo "**Validates: Requirements 2.6**\n";
echo "=================================================\n\n";

$test = new CourierExportFilterConsistencyTest();
$success = $test->runAllTests();

echo "\n=================================================\n";
if ($success) {
    echo "ALL TESTS PASSED\n";
} else {
    echo "SOME TESTS FAILED\n";
}
echo "=================================================\n";

exit($success ? 0 : 1);
