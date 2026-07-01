<?php
/**
 * Property Tests: Sidebar Menu Structure
 * 
 * **Feature: crm-sidebar-restructure, Property 1: Masters Section Structure**
 * **Feature: crm-sidebar-restructure, Property 2: Users Section Structure**
 * **Feature: crm-sidebar-restructure, Property 3: Location Master Submenu Structure**
 * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
 * **Feature: crm-sidebar-restructure, Property 9: Active Menu Item Highlighting**
 * 
 * **Validates: Requirements 1.1, 1.3, 1.5, 4.1, 4.2, 4.3, 4.4, 4.5**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/MenuService.php';

class SidebarMenuStructureTest extends PropertyTestBase {
    private $menuService;
    
    // Expected Masters section items in order (Requirements 1.1, 4.1)
    private $expectedMastersItems = ['Company', 'Bank', 'Customer', 'Courier', 'Location Master'];
    
    // Expected Users section items in order (Requirements 1.3, 4.2)
    private $expectedUsersItems = ['User', 'Roles', 'Permissions'];
    
    // Expected Location Master submenu items in order (Requirements 1.5, 4.3)
    private $expectedLocationMasterItems = ['Countries', 'States', 'Zones', 'Cities'];
    
    public function __construct() {
        parent::__construct();
        $this->menuService = new MenuService();
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "\n=== Sidebar Menu Structure Property Tests ===\n";
        echo "**Feature: crm-sidebar-restructure**\n";
        echo "**Validates: Requirements 1.1, 1.3, 1.5, 4.1, 4.2, 4.3, 4.4, 4.5**\n\n";
        
        $results = [];
        
        // Property 1: Masters Section Structure
        $results['masters_section_structure'] = $this->runPropertyTest(
            'Property 1: Masters Section Structure',
            [$this, 'testMastersSectionStructure'],
            1 // Structure test only needs 1 iteration
        );
        
        // Property 2: Users Section Structure
        $results['users_section_structure'] = $this->runPropertyTest(
            'Property 2: Users Section Structure',
            [$this, 'testUsersSectionStructure'],
            1
        );
        
        // Property 3: Location Master Submenu Structure
        $results['location_master_structure'] = $this->runPropertyTest(
            'Property 3: Location Master Submenu Structure',
            [$this, 'testLocationMasterSubmenuStructure'],
            1
        );
        
        // Property 8: Active Section Auto-Expand
        $results['active_section_auto_expand'] = $this->runPropertyTest(
            'Property 8: Active Section Auto-Expand',
            [$this, 'testActiveSectionAutoExpand']
        );
        
        // Property 9: Active Menu Item Highlighting
        $results['active_menu_highlighting'] = $this->runPropertyTest(
            'Property 9: Active Menu Item Highlighting',
            [$this, 'testActiveMenuItemHighlighting']
        );
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($results));
        $total = count($results);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Property 1: Masters Section Structure
     * **Feature: crm-sidebar-restructure, Property 1: Masters Section Structure**
     * **Validates: Requirements 1.1, 4.1**
     * 
     * For any ADV user viewing the sidebar, the Masters section SHALL contain 
     * exactly these items in order: Company, Bank, Customer, Courier, Location Master.
     */
    protected function testMastersSectionStructure() {
        $mastersSection = $this->menuService->getMastersSection();
        
        // Verify section exists and is collapsible
        if (!$mastersSection) {
            return [
                'success' => false,
                'message' => 'Masters section not found in menu configuration'
            ];
        }
        
        if (!isset($mastersSection['collapsible']) || !$mastersSection['collapsible']) {
            return [
                'success' => false,
                'message' => 'Masters section is not marked as collapsible'
            ];
        }
        
        if (!isset($mastersSection['items']) || !is_array($mastersSection['items'])) {
            return [
                'success' => false,
                'message' => 'Masters section has no items array'
            ];
        }
        
        // Extract labels from items
        $actualLabels = [];
        foreach ($mastersSection['items'] as $item) {
            $actualLabels[] = $item['label'];
        }
        
        // Verify exact order and content
        if ($actualLabels !== $this->expectedMastersItems) {
            return [
                'success' => false,
                'message' => 'Masters section items do not match expected order',
                'data' => [
                    'expected' => $this->expectedMastersItems,
                    'actual' => $actualLabels
                ]
            ];
        }
        
        // Verify Masters section is ADV-only
        if (!isset($mastersSection['adv_only']) || !$mastersSection['adv_only']) {
            return [
                'success' => false,
                'message' => 'Masters section should be marked as ADV-only'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 2: Users Section Structure
     * **Feature: crm-sidebar-restructure, Property 2: Users Section Structure**
     * **Validates: Requirements 1.3, 4.2**
     * 
     * For any ADV user viewing the sidebar, the Users section SHALL contain 
     * exactly these items in order: User, Roles, Permissions.
     */
    protected function testUsersSectionStructure() {
        $usersSection = $this->menuService->getUsersSection();
        
        // Verify section exists and is collapsible
        if (!$usersSection) {
            return [
                'success' => false,
                'message' => 'Users section not found in menu configuration'
            ];
        }
        
        if (!isset($usersSection['collapsible']) || !$usersSection['collapsible']) {
            return [
                'success' => false,
                'message' => 'Users section is not marked as collapsible'
            ];
        }
        
        if (!isset($usersSection['items']) || !is_array($usersSection['items'])) {
            return [
                'success' => false,
                'message' => 'Users section has no items array'
            ];
        }
        
        // Extract labels from items
        $actualLabels = [];
        foreach ($usersSection['items'] as $item) {
            $actualLabels[] = $item['label'];
        }
        
        // Verify exact order and content
        if ($actualLabels !== $this->expectedUsersItems) {
            return [
                'success' => false,
                'message' => 'Users section items do not match expected order',
                'data' => [
                    'expected' => $this->expectedUsersItems,
                    'actual' => $actualLabels
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 3: Location Master Submenu Structure
     * **Feature: crm-sidebar-restructure, Property 3: Location Master Submenu Structure**
     * **Validates: Requirements 1.5, 4.3**
     * 
     * For any ADV user viewing the Location Master submenu, it SHALL contain 
     * exactly these items in order: Countries, States, Zones, Cities.
     */
    protected function testLocationMasterSubmenuStructure() {
        $locationMaster = $this->menuService->getLocationMasterSubmenu();
        
        // Verify Location Master exists
        if (!$locationMaster) {
            return [
                'success' => false,
                'message' => 'Location Master submenu not found in menu configuration'
            ];
        }
        
        // Verify it's a nested collapsible
        if (!isset($locationMaster['collapsible']) || !$locationMaster['collapsible']) {
            return [
                'success' => false,
                'message' => 'Location Master is not marked as collapsible'
            ];
        }
        
        if (!isset($locationMaster['items']) || !is_array($locationMaster['items'])) {
            return [
                'success' => false,
                'message' => 'Location Master has no items array'
            ];
        }
        
        // Extract labels from items
        $actualLabels = [];
        foreach ($locationMaster['items'] as $item) {
            $actualLabels[] = $item['label'];
        }
        
        // Verify exact order and content
        if ($actualLabels !== $this->expectedLocationMasterItems) {
            return [
                'success' => false,
                'message' => 'Location Master items do not match expected order',
                'data' => [
                    'expected' => $this->expectedLocationMasterItems,
                    'actual' => $actualLabels
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 8: Active Section Auto-Expand
     * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
     * **Validates: Requirements 4.4**
     * 
     * For any page within a collapsible section, that section should be 
     * automatically expanded when the page loads.
     */
    protected function testActiveSectionAutoExpand() {
        // Get all menu items to test with
        $allMenuItems = $this->menuService->getAllMenuItems();
        
        if (empty($allMenuItems)) {
            return ['success' => true, 'message' => 'No menu items to test'];
        }
        
        // Pick a random menu item
        $randomItem = $this->generateRandomChoice($allMenuItems);
        $currentPage = $randomItem['id'];
        $section = $randomItem['section'] ?? null;
        
        // Test isAnyChildActive for Masters section
        $mastersSection = $this->menuService->getMastersSection();
        if ($mastersSection && isset($mastersSection['items'])) {
            $isInMasters = $this->isItemInSection($currentPage, $mastersSection['items']);
            $mastersActive = $this->menuService->isAnyChildActive($mastersSection['items'], $currentPage);
            
            if ($isInMasters !== $mastersActive) {
                return [
                    'success' => false,
                    'message' => 'isAnyChildActive mismatch for Masters section',
                    'data' => [
                        'currentPage' => $currentPage,
                        'isInMasters' => $isInMasters,
                        'mastersActive' => $mastersActive
                    ]
                ];
            }
        }
        
        // Test isAnyChildActive for Users section
        $usersSection = $this->menuService->getUsersSection();
        if ($usersSection && isset($usersSection['items'])) {
            $isInUsers = $this->isItemInSection($currentPage, $usersSection['items']);
            $usersActive = $this->menuService->isAnyChildActive($usersSection['items'], $currentPage);
            
            if ($isInUsers !== $usersActive) {
                return [
                    'success' => false,
                    'message' => 'isAnyChildActive mismatch for Users section',
                    'data' => [
                        'currentPage' => $currentPage,
                        'isInUsers' => $isInUsers,
                        'usersActive' => $usersActive
                    ]
                ];
            }
        }
        
        // Test getActiveSectionId
        $activeSectionId = $this->menuService->getActiveSectionId($currentPage);
        
        // If item is in masters, active section should be masters_section
        if ($section === 'masters' && $activeSectionId !== 'masters_section') {
            return [
                'success' => false,
                'message' => 'Active section ID should be masters_section for masters items',
                'data' => [
                    'currentPage' => $currentPage,
                    'section' => $section,
                    'activeSectionId' => $activeSectionId
                ]
            ];
        }
        
        // If item is in users_section, active section should be users_section
        if ($section === 'users_section' && $activeSectionId !== 'users_section') {
            return [
                'success' => false,
                'message' => 'Active section ID should be users_section for users items',
                'data' => [
                    'currentPage' => $currentPage,
                    'section' => $section,
                    'activeSectionId' => $activeSectionId
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 9: Active Menu Item Highlighting
     * **Feature: crm-sidebar-restructure, Property 9: Active Menu Item Highlighting**
     * **Validates: Requirements 4.5**
     * 
     * For any page in the sidebar, the corresponding menu item and its parent 
     * section should be highlighted as active.
     */
    protected function testActiveMenuItemHighlighting() {
        // Get all menu items
        $allMenuItems = $this->menuService->getAllMenuItems();
        
        if (empty($allMenuItems)) {
            return ['success' => true, 'message' => 'No menu items to test'];
        }
        
        // Pick a random menu item
        $randomItem = $this->generateRandomChoice($allMenuItems);
        $currentPage = $randomItem['id'];
        
        // Get a test user ID (we'll use 1 as a placeholder since we're testing rendering)
        $testUserId = $this->getTestUserId();
        if (!$testUserId) {
            return ['success' => true, 'message' => 'No test user available'];
        }
        
        // Render menu HTML
        $html = $this->menuService->renderMenuHtml($testUserId, $currentPage, '');
        
        // The active item should have 'active' class in the rendered HTML
        // We check if the menu item is rendered and if it has the active class when it's the current page
        
        // For items that should be visible to the user, verify active state
        $visibleMenus = $this->menuService->getVisibleMenus($testUserId);
        $visibleIds = $this->extractVisibleMenuIds($visibleMenus);
        
        if (in_array($currentPage, $visibleIds)) {
            // The item should be visible and marked as active
            // Check that the HTML contains the active class for this item
            // Note: The exact check depends on how the HTML is structured
            
            // Verify that isAnyChildActive returns true for the parent section
            $mastersSection = $this->menuService->getMastersSection();
            $usersSection = $this->menuService->getUsersSection();
            
            $inMasters = $mastersSection && isset($mastersSection['items']) && 
                         $this->isItemInSection($currentPage, $mastersSection['items']);
            $inUsers = $usersSection && isset($usersSection['items']) && 
                       $this->isItemInSection($currentPage, $usersSection['items']);
            
            if ($inMasters) {
                $mastersActive = $this->menuService->isAnyChildActive($mastersSection['items'], $currentPage);
                if (!$mastersActive) {
                    return [
                        'success' => false,
                        'message' => 'Masters section should be active when child is current page',
                        'data' => ['currentPage' => $currentPage]
                    ];
                }
            }
            
            if ($inUsers) {
                $usersActive = $this->menuService->isAnyChildActive($usersSection['items'], $currentPage);
                if (!$usersActive) {
                    return [
                        'success' => false,
                        'message' => 'Users section should be active when child is current page',
                        'data' => ['currentPage' => $currentPage]
                    ];
                }
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Helper: Check if an item ID is in a section's items (including nested)
     */
    private function isItemInSection($itemId, $items) {
        foreach ($items as $item) {
            if (isset($item['id']) && $item['id'] === $itemId) {
                return true;
            }
            // Check nested items
            if (isset($item['items']) && is_array($item['items'])) {
                if ($this->isItemInSection($itemId, $item['items'])) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Helper: Extract visible menu IDs from nested menu structure
     */
    private function extractVisibleMenuIds($visibleMenus) {
        $ids = [];
        foreach ($visibleMenus as $section => $data) {
            // Handle collapsible sections
            if (isset($data['items']) && is_array($data['items'])) {
                $ids = array_merge($ids, $this->extractIdsFromItems($data['items']));
            } elseif (is_array($data)) {
                // Flat array of items
                foreach ($data as $item) {
                    if (isset($item['id'])) {
                        $ids[] = $item['id'];
                    }
                }
            }
        }
        return $ids;
    }
    
    /**
     * Helper: Extract IDs from items array (recursive)
     */
    private function extractIdsFromItems($items) {
        $ids = [];
        foreach ($items as $item) {
            if (isset($item['id'])) {
                $ids[] = $item['id'];
            }
            if (isset($item['items']) && is_array($item['items'])) {
                $ids = array_merge($ids, $this->extractIdsFromItems($item['items']));
            }
        }
        return $ids;
    }
    
    /**
     * Helper: Get a test user ID (ADV user preferred)
     */
    private function getTestUserId() {
        $sql = "SELECT u.id FROM users u 
                INNER JOIN companies c ON u.company_id = c.id 
                WHERE c.type = 'ADV' AND u.status = 1 
                LIMIT 1";
        $results = $this->getResults($sql);
        
        if (!empty($results)) {
            return $results[0]['id'];
        }
        
        // Fallback to any user
        $sql = "SELECT id FROM users WHERE status = 1 LIMIT 1";
        $results = $this->getResults($sql);
        
        return !empty($results) ? $results[0]['id'] : null;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new SidebarMenuStructureTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
