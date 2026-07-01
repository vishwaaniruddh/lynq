<?php
/**
 * Runner for Sidebar Menu Structure Property Tests
 * 
 * **Feature: crm-sidebar-restructure, Property 1: Masters Section Structure**
 * **Feature: crm-sidebar-restructure, Property 2: Users Section Structure**
 * **Feature: crm-sidebar-restructure, Property 3: Location Master Submenu Structure**
 * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
 * **Feature: crm-sidebar-restructure, Property 9: Active Menu Item Highlighting**
 */

require_once __DIR__ . '/SidebarMenuStructureTest.php';

echo "===========================================\n";
echo "Sidebar Menu Structure Property Tests\n";
echo "===========================================\n";

$test = new SidebarMenuStructureTest();
$success = $test->runAllTests();

echo "\n===========================================\n";
if ($success) {
    echo "All tests PASSED!\n";
} else {
    echo "Some tests FAILED!\n";
}
echo "===========================================\n";

exit($success ? 0 : 1);
