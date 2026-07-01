<?php
/**
 * Sidebar Component
 * Uses MenuService for permission-based menu visibility
 * Supports collapsible sections with nested submenus
 * 
 * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
 * **Feature: crm-sidebar-restructure, Property 9: Active Menu Item Highlighting**
 */

// Initialize MenuService if not already done
if (!isset($menuService)) {
    $menuService = new MenuService();
}

// Get current user ID from session
$currentUserId = $sessionService->getCurrentUserId() ?? null;
$visibleMenus = $currentUserId ? $menuService->getVisibleMenus($currentUserId) : [];
$isAdvUserFlag = $currentUserId ? isAdvUser($currentUserId) : false;

/**
 * Helper function to get icon color based on menu item ID
 */
function getMenuIconColor($itemId) {
    $colors = [
        'dashboard' => 'text-primary',
        'users' => 'text-blue-400',
        'companies' => 'text-green-400',
        'roles' => 'text-purple-400',
        'permissions' => 'text-yellow-400',
        'delegation' => 'text-cyan-400',
        'audit' => 'text-orange-400',
        'settings' => 'text-gray-400',
        // Master modules
        'masters_companies' => 'text-green-400',
        'masters_banks' => 'text-indigo-400',
        'masters_customers' => 'text-blue-400',
        'masters_couriers' => 'text-orange-400',
        'masters_countries' => 'text-green-400',
        'masters_states' => 'text-teal-400',
        'masters_zones' => 'text-purple-400',
        'masters_cities' => 'text-cyan-400',
        // Location Master
        'location_master' => 'text-pink-400',
        // Legacy master data (for backward compatibility)
        'master_data_view' => 'text-indigo-400',
        'master_data_manage' => 'text-indigo-400',
        // Site Management (ADV)
        'sites_list' => 'text-indigo-400',
        'sites_add' => 'text-green-400',
        'sites_bulk_upload' => 'text-blue-400',
        'sites_delegate' => 'text-cyan-400',
        'sites_bulk_delegate' => 'text-teal-400',
        // Delegation Tracking (ADV)
        'delegations_list' => 'text-purple-400',
        'delegations_history' => 'text-pink-400',
        // IP Configuration (ADV)
        // **Feature: ip-configuration-management, Menu Integration**
        'ip_config_dashboard' => 'text-primary',
        'ip_config_ip_master' => 'text-blue-400',
        'ip_config_configure' => 'text-green-400',
        'ip_config_reports' => 'text-amber-400',
        'ip_config_audit' => 'text-purple-400',
        // Contractor Portal
        'contractor_delegations' => 'text-indigo-400',
        'contractor_dashboard' => 'text-cyan-400',
        'contractor_stocks' => 'text-amber-400',
        'contractor_assign' => 'text-green-400',
        'contractor_bulk_assign' => 'text-blue-400',
        'contractor_pending_receives' => 'text-cyan-400',
        'contractor_dispatch' => 'text-orange-400',
        // Engineer Portal
        'engineer_sites' => 'text-cyan-400',
        'engineer_feasibility' => 'text-emerald-400',
        'engineer_pending_receives' => 'text-cyan-400',
        'engineer_dispatch' => 'text-orange-400',
        // Feasibility Tracking (ADV)
        // **Feature: feasibility-module, Menu Integration**
        'feasibility_tracking' => 'text-teal-400',
        'feasibility_export' => 'text-green-400',
        // Inventory
        'inventory_warehouses' => 'text-amber-400',
        'inventory_products' => 'text-blue-400',
        'inventory_stock' => 'text-green-400',
        'inventory_dispatch' => 'text-orange-400',
        'inventory_transfers' => 'text-purple-400',
        'inventory_assets' => 'text-indigo-400',
        'inventory_item_history' => 'text-violet-400',
        'inventory_repairs' => 'text-red-400',
        // System
        'system_admin' => 'text-red-400',
        'system_backup' => 'text-teal-400',
        // Admin
        'admin_dashboard' => 'text-pink-400',
        'admin_health' => 'text-green-400',
        'admin_activity' => 'text-blue-400',
        'admin_backup' => 'text-teal-400',
        'admin_maintenance' => 'text-yellow-400',
        'admin_config' => 'text-gray-400',
        'admin_performance' => 'text-orange-400',
        'admin_reports' => 'text-amber-400'
    ];
    
    return $colors[$itemId] ?? 'text-gray-400';
}

/**
 * Check if any child item is active in a section
 * @param array $items Menu items to check
 * @param string $currentPage Current page ID
 * @return bool True if any child is active
 */
function isAnyChildActive($items, $currentPage) {
    foreach ($items as $item) {
        if (isset($item['collapsible']) && $item['collapsible'] && isset($item['items'])) {
            // Nested collapsible - check recursively
            if (isAnyChildActive($item['items'], $currentPage)) {
                return true;
            }
        } else {
            // Regular item
            if (isset($item['id']) && $item['id'] === $currentPage) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Render a single menu item
 * @param array $item Menu item data
 * @param string $currentPage Current page ID
 * @param string $baseUrl Base URL for links
 * @return string HTML for the menu item
 */
function renderMenuItem($item, $currentPage, $baseUrl) {
    $isActive = ($currentPage ?? '') === ($item['id'] ?? '');
    $activeClass = $isActive ? 'active text-white' : 'hover:text-white';
    $iconColor = getMenuIconColor($item['id'] ?? '');
    
    // Check for badge
    $badgeHtml = '';
    if (isset($item['badge_type']) && $item['badge_type'] === 'pending_receives') {
        // Badge will be populated via JavaScript
        $badgeHtml = sprintf(
            '<span class="pending-receives-badge ml-auto hidden px-1.5 py-0.5 text-xs font-medium bg-red-500 text-white rounded-full" data-badge-id="%s"></span>',
            htmlspecialchars($item['id'] ?? '')
        );
    }
    
    return sprintf(
        '<a href="%s%s" class="sidebar-link flex items-center px-3 py-2 rounded-md text-gray-300 text-sm %s" data-menu-id="%s">
            <i class="fas %s w-4 mr-2 text-xs %s"></i>
            <span>%s</span>
            %s
        </a>',
        htmlspecialchars($baseUrl),
        htmlspecialchars($item['url'] ?? ''),
        $activeClass,
        htmlspecialchars($item['id'] ?? ''),
        htmlspecialchars($item['icon'] ?? 'fa-circle'),
        $iconColor,
        htmlspecialchars($item['label'] ?? ''),
        $badgeHtml
    );
}

/**
 * Render a collapsible section with nested items
 * @param array $section Section configuration
 * @param string $currentPage Current page ID
 * @param string $baseUrl Base URL for links
 * @param bool $isNested Whether this is a nested section
 * @return string HTML for the collapsible section
 */
function renderCollapsibleSection($section, $currentPage, $baseUrl, $isNested = false) {
    $sectionId = $section['id'] ?? '';
    $label = $section['label'] ?? '';
    $icon = $section['icon'] ?? '';
    $items = $section['items'] ?? [];
    
    // Check if any child is active to auto-expand
    $isExpanded = isAnyChildActive($items, $currentPage);
    $expandedClass = $isExpanded ? '' : 'hidden';
    $chevronClass = $isExpanded ? 'rotate-90' : '';
    
    $marginClass = $isNested ? 'ml-2 mt-1' : 'mt-4 pt-3 border-t border-dark-600';
    $paddingClass = $isNested ? 'pl-3' : '';
    
    $html = '<div class="' . $marginClass . '" data-section-container="' . htmlspecialchars($sectionId) . '">';
    
    // Section header (clickable to toggle) - more compact
    $html .= sprintf(
        '<button type="button" class="collapsible-toggle w-full flex items-center justify-between px-3 py-1.5 text-xs font-semibold text-dark-500 uppercase tracking-wider hover:text-gray-300 transition" data-section="%s">
            <span class="flex items-center">
                <i class="fas %s mr-2 text-xs"></i>
                %s
            </span>
            <i class="fas fa-chevron-right text-xs transition-transform duration-200 %s" id="chevron-%s"></i>
        </button>',
        htmlspecialchars($sectionId),
        htmlspecialchars($icon),
        htmlspecialchars($label),
        $chevronClass,
        htmlspecialchars($sectionId)
    );
    
    // Section items container - reduced spacing
    $html .= sprintf(
        '<div id="section-%s" class="space-y-0.5 mt-1 %s %s" data-section-content="%s">',
        htmlspecialchars($sectionId),
        $paddingClass,
        $expandedClass,
        htmlspecialchars($sectionId)
    );
    
    foreach ($items as $item) {
        // Check if item is a nested collapsible section
        if (isset($item['collapsible']) && $item['collapsible'] && isset($item['items'])) {
            $html .= renderCollapsibleSection($item, $currentPage, $baseUrl, true);
        } else {
            $html .= renderMenuItem($item, $currentPage, $baseUrl);
        }
    }
    
    $html .= '</div></div>';
    
    return $html;
}
?>
<aside id="sidebar" class="sidebar fixed left-0 top-0 w-64 h-full bg-dark-800 z-50 flex flex-col shadow-2xl">
    <!-- Logo -->
    <div class="p-4 border-b border-dark-600" style="background-color: #e1e1e1;">
        <a href="<?php echo $baseUrl; ?>/dashboard.php" class="flex items-center justify-center">
            <img src="<?php echo $baseUrl; ?>/assets/lynq.png" alt="LYNQ" class="h-12 object-contain">
        </a>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-3 px-2">
        <!-- Main Menu Section (Dashboard) -->
        <?php if (!empty($visibleMenus['main'])): ?>
        <div class="space-y-1">
            <?php foreach ($visibleMenus['main'] as $item): ?>
            <?php echo renderMenuItem($item, $currentPage ?? '', $baseUrl); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Masters Section (Collapsible) - ADV Only -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['masters']) && !empty($visibleMenus['masters']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['masters'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- Users Section (Collapsible) -->
        <?php if (!empty($visibleMenus['users_section']) && !empty($visibleMenus['users_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['users_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- Site Management Section (Collapsible) - ADV Only -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['sites_section']) && !empty($visibleMenus['sites_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['sites_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- Delegation Tracking Section (Collapsible) - ADV Only -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['delegations_section']) && !empty($visibleMenus['delegations_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['delegations_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- IP Configuration Section (Collapsible) - ADV Only -->
        <!-- **Feature: ip-configuration-management, Menu Integration** -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['ip_configuration_section']) && !empty($visibleMenus['ip_configuration_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['ip_configuration_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- Feasibility Tracking Section (Collapsible) - ADV Only -->
        <!-- **Feature: feasibility-module, Menu Integration** -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['feasibility_section']) && !empty($visibleMenus['feasibility_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['feasibility_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- Inventory Section (Collapsible) - All users with permissions -->
        <?php if (!empty($visibleMenus['inventory_section']) && !empty($visibleMenus['inventory_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['inventory_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- Contractor Portal Section (Collapsible) - Contractor Only -->
        <?php if (!$isAdvUserFlag && !empty($visibleMenus['contractor_section']) && !empty($visibleMenus['contractor_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['contractor_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- Engineer Portal Section (Collapsible) - Contractor Only -->
        <?php if (!$isAdvUserFlag && !empty($visibleMenus['engineer_section']) && !empty($visibleMenus['engineer_section']['items'])): ?>
        <?php echo renderCollapsibleSection($visibleMenus['engineer_section'], $currentPage ?? '', $baseUrl); ?>
        <?php endif; ?>
        
        <!-- ADV Only Section - Only visible to ADV users -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['adv_only'])): ?>
        <div class="mt-4 pt-3 border-t border-dark-600">
            <p class="px-3 text-xs font-semibold text-dark-500 uppercase tracking-wider mb-2">ADV Only</p>
            <div class="space-y-0.5">
                <?php foreach ($visibleMenus['adv_only'] as $item): ?>
                <?php echo renderMenuItem($item, $currentPage ?? '', $baseUrl); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- System Section - ADV Only -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['system'])): ?>
        <div class="mt-4 pt-3 border-t border-dark-600">
            <p class="px-3 text-xs font-semibold text-dark-500 uppercase tracking-wider mb-2">System</p>
            <div class="space-y-0.5">
                <?php foreach ($visibleMenus['system'] as $item): ?>
                <?php echo renderMenuItem($item, $currentPage ?? '', $baseUrl); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Admin Section - ADV Only -->
        <?php if ($isAdvUserFlag && !empty($visibleMenus['admin'])): ?>
        <div class="mt-4 pt-3 border-t border-dark-600">
            <p class="px-3 text-xs font-semibold text-dark-500 uppercase tracking-wider mb-2">Admin</p>
            <div class="space-y-0.5">
                <?php foreach ($visibleMenus['admin'] as $item): ?>
                <?php echo renderMenuItem($item, $currentPage ?? '', $baseUrl); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </nav>
    
    <!-- User Section -->
    <div class="p-3 border-t border-dark-600 hidden">
        <!-- PWA Status Indicator -->
        <div class="mb-2 px-2">
            <div class="flex items-center justify-between text-xs">
                <span class="text-dark-500">Status:</span>
                <span class="connection-status online" id="sidebar-connection-status">Online</span>
            </div>
            <!-- Offline Queue Indicator -->
            <div id="sidebar-offline-queue" class="hidden mt-1">
                <div class="flex items-center justify-between text-xs text-yellow-400">
                    <span>Queued actions:</span>
                    <span id="sidebar-queue-count">0</span>
                </div>
            </div>
        </div>
        
        <div class="flex items-center space-x-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white text-xs font-semibold">
                <?php echo strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white text-xs font-medium truncate"><?php echo htmlspecialchars($currentUser['username'] ?? 'User'); ?></p>
                <p class="text-dark-500 text-xs truncate"><?php echo htmlspecialchars($currentUser['role_name'] ?? ''); ?></p>
            </div>
            <div class="flex items-center space-x-1">
                <a href="<?php echo $baseUrl; ?>/profile.php" class="p-1.5 text-gray-400 hover:text-primary transition nav-item" title="Profile">
                    <i class="fas fa-user-circle text-sm"></i>
                </a>
                <a href="<?php echo $baseUrl; ?>/logout.php" class="p-1.5 text-gray-400 hover:text-red-400 transition nav-item" title="Logout">
                    <i class="fas fa-sign-out-alt text-sm"></i>
                </a>
            </div>
        </div>
        <?php if ($isAdvUserFlag): ?>
        <div class="mt-1.5 px-1">
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-primary/20 text-primary">
                <i class="fas fa-shield-alt mr-1 text-xs"></i> ADV User
            </span>
        </div>
        <?php else: ?>
        <div class="mt-1.5 px-1">
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-500/20 text-green-400">
                <i class="fas fa-building mr-1 text-xs"></i> Contractor
            </span>
        </div>
        <?php endif; ?>
    </div>
</aside>

<!-- Sidebar Collapsible Section JavaScript -->
<script>
/**
 * Sidebar Collapsible Sections Manager
 * Handles toggle functionality, localStorage persistence, and auto-expand
 * 
 * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
 * **Feature: crm-sidebar-restructure, Property 9: Active Menu Item Highlighting**
 */
(function() {
    'use strict';
    
    const STORAGE_KEY = 'sidebar_state';
    const SCROLL_KEY = 'sidebar_scroll';
    
    /**
     * Get sidebar state from localStorage
     * @returns {Object} Sidebar state object
     */
    function getSidebarState() {
        try {
            const state = localStorage.getItem(STORAGE_KEY);
            return state ? JSON.parse(state) : {};
        } catch (e) {
            console.warn('Failed to parse sidebar state from localStorage:', e);
            return {};
        }
    }
    
    /**
     * Save sidebar state to localStorage
     * @param {Object} state Sidebar state object
     */
    function saveSidebarState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            console.warn('Failed to save sidebar state to localStorage:', e);
        }
    }
    
    /**
     * Toggle a section's expanded/collapsed state
     * @param {string} sectionId Section ID to toggle
     * @param {boolean} [forceState] Optional forced state (true = expanded, false = collapsed)
     */
    function toggleSection(sectionId, forceState) {
        const content = document.getElementById('section-' + sectionId);
        const chevron = document.getElementById('chevron-' + sectionId);
        
        if (!content) return;
        
        const isCurrentlyHidden = content.classList.contains('hidden');
        const shouldExpand = forceState !== undefined ? forceState : isCurrentlyHidden;
        
        if (shouldExpand) {
            content.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-90');
        } else {
            content.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-90');
        }
        
        // Save state to localStorage
        const state = getSidebarState();
        state[sectionId] = shouldExpand;
        saveSidebarState(state);
    }
    
    /**
     * Initialize collapsible sections
     */
    function initCollapsibleSections() {
        // Get all toggle buttons
        const toggleButtons = document.querySelectorAll('.collapsible-toggle');
        
        toggleButtons.forEach(function(button) {
            const sectionId = button.getAttribute('data-section');
            if (!sectionId) return;
            
            // Add click handler
            button.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSection(sectionId);
            });
        });
    }
    
    /**
     * Restore sidebar state from localStorage
     * Only restore state for sections that don't have an active child
     */
    function restoreSidebarState() {
        const state = getSidebarState();
        const toggleButtons = document.querySelectorAll('.collapsible-toggle');
        
        toggleButtons.forEach(function(button) {
            const sectionId = button.getAttribute('data-section');
            if (!sectionId) return;
            
            const content = document.getElementById('section-' + sectionId);
            if (!content) return;
            
            // Check if section has an active child (already expanded by PHP)
            const hasActiveChild = content.querySelector('.sidebar-link.active') !== null;
            
            // If section has active child, keep it expanded (don't override)
            // Otherwise, restore from localStorage
            if (!hasActiveChild && state.hasOwnProperty(sectionId)) {
                toggleSection(sectionId, state[sectionId]);
            }
        });
    }
    
    /**
     * Highlight active menu item and ensure parent sections are expanded
     */
    function highlightActiveItem() {
        const activeLinks = document.querySelectorAll('.sidebar-link.active');
        
        activeLinks.forEach(function(link) {
            // Find parent section containers and expand them
            let parent = link.parentElement;
            while (parent && parent.id !== 'sidebar') {
                if (parent.hasAttribute('data-section-content')) {
                    const sectionId = parent.getAttribute('data-section-content');
                    toggleSection(sectionId, true);
                }
                parent = parent.parentElement;
            }
        });
    }
    
    /**
     * Save sidebar scroll position
     */
    function saveScrollPosition() {
        const nav = document.querySelector('#sidebar nav');
        if (nav) {
            try {
                sessionStorage.setItem(SCROLL_KEY, nav.scrollTop.toString());
            } catch (e) {
                // Ignore storage errors
            }
        }
    }
    
    /**
     * Restore sidebar scroll position
     */
    function restoreScrollPosition() {
        const nav = document.querySelector('#sidebar nav');
        if (nav) {
            try {
                const scrollPos = sessionStorage.getItem(SCROLL_KEY);
                if (scrollPos) {
                    nav.scrollTop = parseInt(scrollPos, 10);
                } else {
                    // If no saved position, scroll to active item
                    scrollToActiveItem();
                }
            } catch (e) {
                scrollToActiveItem();
            }
        }
    }
    
    /**
     * Scroll sidebar to show active menu item
     */
    function scrollToActiveItem() {
        const activeLink = document.querySelector('.sidebar-link.active');
        const nav = document.querySelector('#sidebar nav');
        if (activeLink && nav) {
            // Wait a bit for sections to expand
            setTimeout(function() {
                const linkRect = activeLink.getBoundingClientRect();
                const navRect = nav.getBoundingClientRect();
                
                // Check if active item is outside visible area
                if (linkRect.top < navRect.top || linkRect.bottom > navRect.bottom) {
                    activeLink.scrollIntoView({ block: 'center', behavior: 'instant' });
                }
            }, 50);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initCollapsibleSections();
            restoreSidebarState();
            highlightActiveItem();
            restoreScrollPosition();
        });
    } else {
        initCollapsibleSections();
        restoreSidebarState();
        highlightActiveItem();
        restoreScrollPosition();
    }
    
    // Save scroll position before page unload
    window.addEventListener('beforeunload', saveScrollPosition);
    
    // Also save on link clicks within sidebar
    document.querySelectorAll('#sidebar .sidebar-link').forEach(function(link) {
        link.addEventListener('click', saveScrollPosition);
    });
    
    // Expose functions globally for external use
    window.SidebarManager = {
        toggleSection: toggleSection,
        getSidebarState: getSidebarState,
        saveSidebarState: saveSidebarState,
        scrollToActiveItem: scrollToActiveItem
    };
})();

/**
 * Pending Receives Badge Manager
 * Fetches and updates pending receives count badges in the sidebar
 * 
 * **Feature: inventory-dispatch-receive-flow, Requirements 2.1, 2.2**
 */
(function() {
    'use strict';
    
    const BADGE_REFRESH_INTERVAL = 60000; // Refresh every 60 seconds
    
    /**
     * Fetch pending receives count from API
     */
    async function fetchPendingReceivesCount() {
        try {
            // Get base URL from current page
            const baseUrl = document.querySelector('a[href*="/dashboard.php"]')?.href?.replace('/dashboard.php', '') || '';
            const response = await fetch(baseUrl + '/api/inventory/receive/pending.php?count_only=1', {
                credentials: 'include'
            });
            
            if (!response.ok) return null;
            
            const data = await response.json();
            if (data.success && data.data) {
                return data.data.count || 0;
            }
        } catch (error) {
            console.warn('Failed to fetch pending receives count:', error);
        }
        return null;
    }
    
    /**
     * Update badge elements with count
     * @param {number} count Pending receives count
     */
    function updateBadges(count) {
        const badges = document.querySelectorAll('.pending-receives-badge');
        badges.forEach(function(badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
    }
    
    /**
     * Initialize badge updates
     */
    async function initBadges() {
        // Only fetch if there are badge elements
        const badges = document.querySelectorAll('.pending-receives-badge');
        if (badges.length === 0) return;
        
        // Initial fetch
        const count = await fetchPendingReceivesCount();
        if (count !== null) {
            updateBadges(count);
        }
        
        // Set up periodic refresh
        setInterval(async function() {
            const count = await fetchPendingReceivesCount();
            if (count !== null) {
                updateBadges(count);
            }
        }, BADGE_REFRESH_INTERVAL);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBadges);
    } else {
        initBadges();
    }
    
    // Expose for manual refresh
    window.SidebarBadges = {
        refresh: async function() {
            const count = await fetchPendingReceivesCount();
            if (count !== null) {
                updateBadges(count);
            }
        }
    };
})();

/**
 * PWA Sidebar Integration
 * Handles offline states and PWA-specific functionality in sidebar
 */
(function() {
    'use strict';
    
    /**
     * Update sidebar connection status
     */
    function updateSidebarConnectionStatus(isOnline) {
        const statusElement = document.getElementById('sidebar-connection-status');
        const queueElement = document.getElementById('sidebar-offline-queue');
        const queueCountElement = document.getElementById('sidebar-queue-count');
        
        if (statusElement) {
            statusElement.textContent = isOnline ? 'Online' : 'Offline';
            statusElement.className = `connection-status ${isOnline ? 'online' : 'offline'}`;
        }
        
        // Update offline queue display
        if (queueElement && queueCountElement) {
            if (!isOnline && window.offlineUtils) {
                const queueLength = window.offlineUtils.getOfflineQueueLength();
                if (queueLength > 0) {
                    queueCountElement.textContent = queueLength;
                    queueElement.classList.remove('hidden');
                } else {
                    queueElement.classList.add('hidden');
                }
            } else {
                queueElement.classList.add('hidden');
            }
        }
        
        // Update navigation links
        const navLinks = document.querySelectorAll('#sidebar .sidebar-link');
        navLinks.forEach(link => {
            if (isOnline) {
                link.classList.remove('offline-disabled');
                link.removeAttribute('title');
            } else {
                link.classList.add('offline-disabled');
                link.setAttribute('title', 'Limited functionality while offline');
            }
        });
    }
    
    /**
     * Initialize PWA sidebar features
     */
    function initPWASidebar() {
        // Listen for connection changes
        window.addEventListener('pwa-connection-change', (event) => {
            updateSidebarConnectionStatus(event.detail.isOnline);
        });
        
        // Listen for offline queue changes
        window.addEventListener('offline-queue-updated', (event) => {
            const queueCountElement = document.getElementById('sidebar-queue-count');
            const queueElement = document.getElementById('sidebar-offline-queue');
            
            if (queueCountElement && queueElement) {
                const count = event.detail.count || 0;
                if (count > 0 && !navigator.onLine) {
                    queueCountElement.textContent = count;
                    queueElement.classList.remove('hidden');
                } else {
                    queueElement.classList.add('hidden');
                }
            }
        });
        
        // Initial status update
        updateSidebarConnectionStatus(navigator.onLine);
        
        // Add click handler for offline queue
        const queueElement = document.getElementById('sidebar-offline-queue');
        if (queueElement) {
            queueElement.addEventListener('click', () => {
                if (window.offlineUtils) {
                    window.offlineUtils.showQueueDetails();
                }
            });
            queueElement.style.cursor = 'pointer';
            queueElement.setAttribute('title', 'Click to view queued actions');
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPWASidebar);
    } else {
        initPWASidebar();
    }
    
    // Expose PWA sidebar functions
    window.SidebarPWA = {
        updateConnectionStatus: updateSidebarConnectionStatus
    };
})();
</script>

