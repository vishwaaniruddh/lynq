<?php
/**
 * Menu Service
 * Handles menu configuration and visibility based on user permissions
 * Supports collapsible sections with nested submenus
 * **Feature: adv-crm-users-module, Property 10: Permission-Based Menu Visibility**
 * **Feature: crm-sidebar-restructure, Property 1: Masters Section Structure**
 * **Feature: crm-sidebar-restructure, Property 2: Users Section Structure**
 * **Feature: crm-sidebar-restructure, Property 3: Location Master Submenu Structure**
 * **Feature: site-management-delegation, Menu Integration**
 */

require_once __DIR__ . '/../config/autoload.php';

class MenuService {
    private $permissionEngine;
    private $userModel;
    
    // ADV-only modules that contractors should never see
    private $advOnlyModules = [
        'masters',
        'system',
        'admin',
        'sites',
        'delegations',
        'ip_configuration',
        'feasibility',
        'installation_tracking'
    ];
    
    // Menu configuration with permissions
    private $menuConfig = [];
    
    public function __construct() {
        $this->permissionEngine = new PermissionEngine();
        $this->userModel = new User();
        $this->initializeMenuConfig();
    }
    
    /**
     * Initialize menu configuration with collapsible sections
     */
    private function initializeMenuConfig() {
        $this->menuConfig = [
            'main' => [
                [
                    'id' => 'dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'fa-home',
                    'url' => '/dashboard.php',
                    'permission' => null, // Always visible when logged in
                    'adv_only' => false
                ]
            ],
            'masters' => [
                'id' => 'masters_section',
                'label' => 'Masters',
                'icon' => 'fa-database',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'masters_companies', 'label' => 'Company', 'icon' => 'fa-building', 'url' => '/companies/index.php', 'permission' => 'companies.read', 'adv_only' => true],
                    ['id' => 'masters_banks', 'label' => 'Bank', 'icon' => 'fa-university', 'url' => '/masters/banks.php', 'permission' => 'masters.banks.view', 'adv_only' => true],
                    ['id' => 'masters_customers', 'label' => 'Customer', 'icon' => 'fa-users', 'url' => '/masters/customers.php', 'permission' => 'masters.customers.view', 'adv_only' => true],
                    ['id' => 'masters_couriers', 'label' => 'Courier', 'icon' => 'fa-truck', 'url' => '/masters/couriers.php', 'permission' => 'masters.couriers.view', 'adv_only' => true],
                    [
                        'id' => 'location_master',
                        'label' => 'Location Master',
                        'icon' => 'fa-map-marker-alt',
                        'collapsible' => true,
                        'adv_only' => true,
                        'items' => [
                            ['id' => 'masters_countries', 'label' => 'Countries', 'icon' => 'fa-globe', 'url' => '/masters/countries.php', 'permission' => 'masters.locations.view', 'adv_only' => true],
                            ['id' => 'masters_states', 'label' => 'States', 'icon' => 'fa-map', 'url' => '/masters/states.php', 'permission' => 'masters.locations.view', 'adv_only' => true],
                            ['id' => 'masters_zones', 'label' => 'Zones', 'icon' => 'fa-layer-group', 'url' => '/masters/zones.php', 'permission' => 'masters.locations.view', 'adv_only' => true],
                            ['id' => 'masters_cities', 'label' => 'Cities', 'icon' => 'fa-city', 'url' => '/masters/cities.php', 'permission' => 'masters.locations.view', 'adv_only' => true],
                            ['id' => 'masters_lhos', 'label' => 'LHO', 'icon' => 'fa-building', 'url' => '/masters/lhos.php', 'permission' => 'masters.lhos.view', 'adv_only' => true]
                        ]
                    ]
                ]
            ],
            'users_section' => [
                'id' => 'users_section',
                'label' => 'Users',
                'icon' => 'fa-users-cog',
                'collapsible' => true,
                'adv_only' => false,
                'items' => [
                    ['id' => 'users', 'label' => 'User', 'icon' => 'fa-user', 'url' => '/users/index.php', 'permission' => 'users.read', 'adv_only' => false],
                    ['id' => 'roles', 'label' => 'Roles', 'icon' => 'fa-user-tag', 'url' => '/roles/index.php', 'permission' => 'roles.read', 'adv_only' => false],
                    ['id' => 'permissions', 'label' => 'Permissions', 'icon' => 'fa-key', 'url' => '/permissions/index.php', 'permission' => 'permissions.read', 'adv_only' => false]
                ]
            ],
            // Site Management section (ADV only)
            'sites_section' => [
                'id' => 'sites_section',
                'label' => 'Site Management',
                'icon' => 'fa-map-marker-alt',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'sites_list', 'label' => 'Sites', 'icon' => 'fa-list', 'url' => '/sites/index.php', 'permission' => 'sites.view', 'adv_only' => true],
                    ['id' => 'sites_add', 'label' => 'Add Site', 'icon' => 'fa-plus', 'url' => '/sites/add.php', 'permission' => 'sites.create', 'adv_only' => true],
                    ['id' => 'sites_bulk_upload', 'label' => 'Bulk Upload', 'icon' => 'fa-upload', 'url' => '/sites/bulk_upload.php', 'permission' => 'sites.bulk_upload', 'adv_only' => true],
                    ['id' => 'sites_delegate', 'label' => 'Delegate Sites', 'icon' => 'fa-share-alt', 'url' => '/sites/delegate.php', 'permission' => 'sites.delegate', 'adv_only' => true],
                    ['id' => 'sites_bulk_delegate', 'label' => 'Bulk Delegate', 'icon' => 'fa-share-square', 'url' => '/sites/bulk_delegate.php', 'permission' => 'sites.delegate', 'adv_only' => true]
                ]
            ],
            // Delegation Tracking section (ADV only)
            'delegations_section' => [
                'id' => 'delegations_section',
                'label' => 'Delegation Tracking',
                'icon' => 'fa-tasks',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'delegations_list', 'label' => 'All Delegations', 'icon' => 'fa-list-alt', 'url' => '/delegations/index.php', 'permission' => 'delegations.view', 'adv_only' => true],
                    ['id' => 'delegations_history', 'label' => 'Delegation History', 'icon' => 'fa-history', 'url' => '/delegations/history.php', 'permission' => 'delegations.view', 'adv_only' => true]
                ]
            ],
            // Feasibility Tracking section (ADV only)
            // **Feature: feasibility-module, Menu Integration**
            'feasibility_section' => [
                'id' => 'feasibility_section',
                'label' => 'Feasibility Tracking',
                'icon' => 'fa-clipboard-list',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'feasibility_tracking', 'label' => 'Tracking Dashboard', 'icon' => 'fa-chart-line', 'url' => '/admin/feasibility_tracking.php', 'permission' => 'feasibility.tracking.view', 'adv_only' => true],
                    ['id' => 'adv_pending_reviews', 'label' => 'Pending Final Approval', 'icon' => 'fa-clipboard-check', 'url' => '/adv/pending_reviews.php', 'permission' => 'feasibility.review.adv', 'adv_only' => true],
                    ['id' => 'feasibility_export', 'label' => 'Export Data', 'icon' => 'fa-file-export', 'url' => '/admin/feasibility_tracking.php?export=1', 'permission' => 'feasibility.tracking.export', 'adv_only' => true]
                ]
            ],
            // Installation Tracking section (ADV only)
            // **Feature: installation-module, Menu Integration**
            'installation_section' => [
                'id' => 'installation_section',
                'label' => 'Installation Tracking',
                'icon' => 'fa-tools',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'installation_tracking', 'label' => 'Tracking Dashboard', 'icon' => 'fa-chart-line', 'url' => '/installation/tracking.php', 'permission' => 'installation.tracking.view', 'adv_only' => true],
                    ['id' => 'installation_list', 'label' => 'All Installations', 'icon' => 'fa-list', 'url' => '/installation/index.php', 'permission' => 'installation.view', 'adv_only' => true],
                    ['id' => 'adv_installation_reviews', 'label' => 'Pending Final Approval', 'icon' => 'fa-clipboard-check', 'url' => '/installation/index.php?status=contractor_approved', 'permission' => 'installation.approve', 'adv_only' => true]
                ]
            ],
            // IP Configuration section (ADV only)
            // **Feature: ip-configuration-management, Menu Integration**
            'ip_configuration_section' => [
                'id' => 'ip_configuration_section',
                'label' => 'IP Configuration',
                'icon' => 'fa-network-wired',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'ip_config_dashboard', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/configuration/dashboard.php', 'permission' => 'ip_configuration.view', 'adv_only' => true],
                    ['id' => 'ip_config_ip_master', 'label' => 'IP Master', 'icon' => 'fa-server', 'url' => '/configuration/ip_master.php', 'permission' => 'ip_configuration.ip_master.view', 'adv_only' => true],
                    ['id' => 'ip_config_configure', 'label' => 'Configure Router', 'icon' => 'fa-cogs', 'url' => '/configuration/configure.php', 'permission' => 'ip_configuration.configure', 'adv_only' => true],
                    ['id' => 'ip_config_reports', 'label' => 'Reports', 'icon' => 'fa-chart-bar', 'url' => '/configuration/reports.php', 'permission' => 'ip_configuration.reports.view', 'adv_only' => true],
                    ['id' => 'ip_config_audit', 'label' => 'Audit History', 'icon' => 'fa-history', 'url' => '/configuration/audit.php', 'permission' => 'ip_configuration.audit.view', 'adv_only' => true]
                ]
            ],
            // Inventory Management section (All users with different views)
            'inventory_section' => [
                'id' => 'inventory_section',
                'label' => 'Inventory',
                'icon' => 'fa-boxes',
                'collapsible' => true,
                'adv_only' => false,
                'items' => [
                    ['id' => 'inventory_dashboard_adv', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/inventory/dashboard_adv.php', 'permission' => 'inventory.dashboard.adv', 'adv_only' => true],
                    ['id' => 'inventory_dashboard_contractor', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/inventory/dashboard_contractor.php', 'permission' => 'inventory.dashboard.contractor', 'adv_only' => false, 'contractor_only' => true],
                    ['id' => 'inventory_dashboard_engineer', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/inventory/dashboard_engineer.php', 'permission' => 'inventory.dashboard.engineer', 'adv_only' => false, 'engineer_only' => true],
                    ['id' => 'inventory_warehouses', 'label' => 'Warehouses', 'icon' => 'fa-warehouse', 'url' => '/inventory/warehouses.php', 'permission' => 'inventory.warehouses.view', 'adv_only' => false],
                    ['id' => 'inventory_product_categories', 'label' => 'Product Category', 'icon' => 'fa-folder', 'url' => '/masters/product_categories.php', 'permission' => 'masters.product_categories.view', 'adv_only' => true],
                    ['id' => 'inventory_products', 'label' => 'Products', 'icon' => 'fa-box', 'url' => '/inventory/products.php', 'permission' => 'inventory.products.view', 'adv_only' => false],
                    ['id' => 'inventory_stock', 'label' => 'Stock Entry', 'icon' => 'fa-plus-circle', 'url' => '/inventory/stock.php', 'permission' => 'inventory.stock.create', 'adv_only' => true],
                    ['id' => 'inventory_dispatch', 'label' => 'Dispatch', 'icon' => 'fa-truck', 'url' => '/inventory/dispatch.php', 'permission' => 'inventory.dispatch.view', 'adv_only' => false],
                    ['id' => 'inventory_transfers', 'label' => 'Transfers', 'icon' => 'fa-exchange-alt', 'url' => '/inventory/transfers.php', 'permission' => 'inventory.transfers.view', 'adv_only' => true],
                    ['id' => 'inventory_assets', 'label' => 'Assets', 'icon' => 'fa-barcode', 'url' => '/inventory/assets.php', 'permission' => 'inventory.assets.view', 'adv_only' => false],
                    ['id' => 'inventory_item_history', 'label' => 'Item History', 'icon' => 'fa-history', 'url' => '/inventory/item-history.php', 'permission' => 'inventory.assets.view', 'adv_only' => true],
                    ['id' => 'inventory_repairs', 'label' => 'Repairs', 'icon' => 'fa-tools', 'url' => '/inventory/repairs.php', 'permission' => 'inventory.repairs.view', 'adv_only' => false],
                    ['id' => 'inventory_material_masters', 'label' => 'Material Masters', 'icon' => 'fa-clipboard-list', 'url' => '/inventory/material-masters.php', 'permission' => 'inventory.material_masters.view', 'adv_only' => true],
                    ['id' => 'inventory_material_requests', 'label' => 'Material Requests', 'icon' => 'fa-file-invoice', 'url' => '/inventory/material-requests.php', 'permission' => 'inventory.material_requests.view', 'adv_only' => true]
                ]
            ],
            // Contractor Portal section (Contractor only)
            'contractor_section' => [
                'id' => 'contractor_section',
                'label' => 'Contractor Portal',
                'icon' => 'fa-hard-hat',
                'collapsible' => true,
                'adv_only' => false,
                'contractor_only' => true,
                'items' => [
                    ['id' => 'contractor_dashboard', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/contractor/dashboard.php', 'permission' => 'contractor.delegations.view', 'adv_only' => false],
                    ['id' => 'contractor_delegations', 'label' => 'My Delegations', 'icon' => 'fa-inbox', 'url' => '/contractor/delegations.php', 'permission' => 'contractor.delegations.view', 'adv_only' => false],
                    ['id' => 'contractor_feasibility_tracking', 'label' => 'Feasibility Tracking', 'icon' => 'fa-chart-line', 'url' => '/contractor/feasibility_tracking.php', 'permission' => 'feasibility.review.contractor', 'adv_only' => false],
                    ['id' => 'contractor_pending_reviews', 'label' => 'Pending Reviews', 'icon' => 'fa-clipboard-check', 'url' => '/contractor/pending_reviews.php', 'permission' => 'feasibility.review.contractor', 'adv_only' => false],
                    ['id' => 'contractor_installation_management', 'label' => 'Installation Management', 'icon' => 'fa-tools', 'url' => '/installation/contractor/index.php', 'permission' => 'installation.assign', 'adv_only' => false],
                    ['id' => 'contractor_installation_reviews', 'label' => 'Installation Reviews', 'icon' => 'fa-clipboard-list', 'url' => '/installation/contractor/index.php?status=submitted', 'permission' => 'installation.review', 'adv_only' => false],
                    ['id' => 'contractor_stocks', 'label' => 'My Stocks', 'icon' => 'fa-boxes', 'url' => '/inventory/contractor/stocks.php', 'permission' => 'contractor.delegations.view', 'adv_only' => false],
                    ['id' => 'contractor_pending_receives', 'label' => 'Pending Receives', 'icon' => 'fa-inbox', 'url' => '/inventory/contractor/pending-receives.php', 'permission' => 'contractor.delegations.view', 'adv_only' => false, 'badge_type' => 'pending_receives'],
                    ['id' => 'contractor_dispatch', 'label' => 'Dispatch', 'icon' => 'fa-truck', 'url' => '/inventory/contractor/dispatch.php', 'permission' => 'contractor.delegations.view', 'adv_only' => false],
                    ['id' => 'contractor_assign', 'label' => 'Assign to Engineer', 'icon' => 'fa-user-plus', 'url' => '/contractor/assign.php', 'permission' => 'contractor.assignments.manage', 'adv_only' => false],
                    ['id' => 'contractor_bulk_assign', 'label' => 'Bulk Assign', 'icon' => 'fa-users', 'url' => '/contractor/bulk_assign.php', 'permission' => 'contractor.assignments.bulk', 'adv_only' => false]
                ]
            ],
            // Engineer Portal section (Engineer only)
            'engineer_section' => [
                'id' => 'engineer_section',
                'label' => 'Engineer Portal',
                'icon' => 'fa-user-hard-hat',
                'collapsible' => true,
                'adv_only' => false,
                'engineer_only' => true,
                'items' => [
                    ['id' => 'engineer_dashboard', 'label' => 'Dashboard', 'icon' => 'fa-home', 'url' => '/engineer/dashboard.php', 'permission' => 'engineer.sites.view', 'adv_only' => false],
                    ['id' => 'engineer_sites', 'label' => 'My Assigned Sites', 'icon' => 'fa-map-marked-alt', 'url' => '/engineer/sites.php', 'permission' => 'engineer.sites.view', 'adv_only' => false],
                    ['id' => 'engineer_feasibility', 'label' => 'My Feasibility Checks', 'icon' => 'fa-clipboard-check', 'url' => '/engineer/feasibility_list.php', 'permission' => 'engineer.feasibility.submit', 'adv_only' => false],
                    ['id' => 'engineer_installations', 'label' => 'My Installations', 'icon' => 'fa-tools', 'url' => '/installation/engineer/index.php', 'permission' => 'installation.eta', 'adv_only' => false],
                    ['id' => 'engineer_pending_receives', 'label' => 'Pending Receives', 'icon' => 'fa-inbox', 'url' => '/inventory/engineer/pending-receives.php', 'permission' => 'engineer.sites.view', 'adv_only' => false, 'badge_type' => 'pending_receives'],
                    ['id' => 'engineer_dispatch', 'label' => 'Dispatch', 'icon' => 'fa-truck', 'url' => '/inventory/engineer/dispatch.php', 'permission' => 'engineer.sites.view', 'adv_only' => false]
                ]
            ],
            'adv_only' => [
                [
                    'id' => 'delegation',
                    'label' => 'Delegation',
                    'icon' => 'fa-share-alt',
                    'url' => '/permissions/delegate.php',
                    'permission' => 'permissions.delegate',
                    'adv_only' => true
                ],
                [
                    'id' => 'audit',
                    'label' => 'Audit Trail',
                    'icon' => 'fa-history',
                    'url' => '/audit/index.php',
                    'permission' => 'audit.view',
                    'adv_only' => true
                ],
                [
                    'id' => 'settings',
                    'label' => 'Settings',
                    'icon' => 'fa-cog',
                    'url' => '/settings/index.php',
                    'permission' => 'system.admin',
                    'adv_only' => true
                ]
            ],
            'system' => [
                [
                    'id' => 'system_admin',
                    'label' => 'System Admin',
                    'icon' => 'fa-server',
                    'url' => '/system/admin.php',
                    'permission' => 'system.admin',
                    'adv_only' => true
                ],
                [
                    'id' => 'system_backup',
                    'label' => 'Backup',
                    'icon' => 'fa-cloud-upload-alt',
                    'url' => '/system/backup.php',
                    'permission' => 'system.backup',
                    'adv_only' => true
                ]
            ],
            'admin' => [
                [
                    'id' => 'admin_dashboard',
                    'label' => 'System Admin',
                    'icon' => 'fa-server',
                    'url' => '/admin/index.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ],
                [
                    'id' => 'admin_health',
                    'label' => 'Health Monitor',
                    'icon' => 'fa-heartbeat',
                    'url' => '/admin/health.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ],
                [
                    'id' => 'admin_activity',
                    'label' => 'Activity Report',
                    'icon' => 'fa-chart-line',
                    'url' => '/admin/activity.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ],
                [
                    'id' => 'admin_backup',
                    'label' => 'Backup & Restore',
                    'icon' => 'fa-database',
                    'url' => '/admin/backup.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ],
                [
                    'id' => 'admin_maintenance',
                    'label' => 'Maintenance',
                    'icon' => 'fa-broom',
                    'url' => '/admin/maintenance.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ],
                [
                    'id' => 'admin_config',
                    'label' => 'Configuration',
                    'icon' => 'fa-cog',
                    'url' => '/admin/config.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ],
                [
                    'id' => 'admin_performance',
                    'label' => 'Performance',
                    'icon' => 'fa-tachometer-alt',
                    'url' => '/admin/performance.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ],
                [
                    'id' => 'admin_filemanager',
                    'label' => 'File Manager',
                    'icon' => 'fa-folder-open',
                    'url' => '/filemanager/index.php',
                    'permission' => 'system.manage',
                    'adv_only' => true
                ]
            ]
        ];
    }
    
    /**
     * Get visible menus for a user
     * 
     * @param int $userId User ID
     * @return array Visible menu items
     */
    public function getVisibleMenus($userId) {
        $user = $this->userModel->findWithRelations($userId);
        if (!$user) {
            return [];
        }
        
        $isAdvUser = strtoupper($user['company_type']) === 'ADV';
        $isContractorUser = strtoupper($user['company_type']) === 'CONTRACTOR';
        $isEngineer = $isContractorUser && !isContractorAdmin($userId);
        $visibleMenus = [];
        
        // Process main menus (Dashboard) - hide for engineers (they have their own dashboard)
        if (!$isEngineer) {
            $visibleMenus['main'] = $this->filterMenusByPermission(
                $this->menuConfig['main'], 
                $userId, 
                $isAdvUser
            );
        }
        
        // Masters section - ADV only (collapsible)
        if ($isAdvUser) {
            $mastersSection = $this->filterCollapsibleSection(
                $this->menuConfig['masters'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($mastersSection['items'])) {
                $visibleMenus['masters'] = $mastersSection;
            }
        }
        
        // Users section (collapsible) - available to ADV and contractor admins, NOT engineers
        if (!$isEngineer) {
            $usersSection = $this->filterCollapsibleSection(
                $this->menuConfig['users_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($usersSection['items'])) {
                $visibleMenus['users_section'] = $usersSection;
            }
        }
        
        // Site Management section - ADV only (collapsible)
        if ($isAdvUser) {
            $sitesSection = $this->filterCollapsibleSection(
                $this->menuConfig['sites_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($sitesSection['items'])) {
                $visibleMenus['sites_section'] = $sitesSection;
            }
            
            // Delegation Tracking section - ADV only (collapsible)
            $delegationsSection = $this->filterCollapsibleSection(
                $this->menuConfig['delegations_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($delegationsSection['items'])) {
                $visibleMenus['delegations_section'] = $delegationsSection;
            }
            
            // IP Configuration section - ADV only (collapsible)
            // **Feature: ip-configuration-management, Menu Integration**
            $ipConfigSection = $this->filterCollapsibleSection(
                $this->menuConfig['ip_configuration_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($ipConfigSection['items'])) {
                $visibleMenus['ip_configuration_section'] = $ipConfigSection;
            }
            
            // Feasibility Tracking section - ADV only (collapsible)
            // **Feature: feasibility-module, Menu Integration**
            $feasibilitySection = $this->filterCollapsibleSection(
                $this->menuConfig['feasibility_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($feasibilitySection['items'])) {
                $visibleMenus['feasibility_section'] = $feasibilitySection;
            }
            
            // Installation Tracking section - ADV only (collapsible)
            // **Feature: installation-module, Menu Integration**
            $installationSection = $this->filterCollapsibleSection(
                $this->menuConfig['installation_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($installationSection['items'])) {
                $visibleMenus['installation_section'] = $installationSection;
            }
        }
        
        // Inventory section - available to all users with appropriate permissions
        $inventorySection = $this->filterCollapsibleSection(
            $this->menuConfig['inventory_section'], 
            $userId, 
            $isAdvUser
        );
        if (!empty($inventorySection['items'])) {
            $visibleMenus['inventory_section'] = $inventorySection;
        }
        
        // Contractor Portal section - Contractor admins only (not engineers)
        if ($isContractorUser && isContractorAdmin($userId)) {
            $contractorSection = $this->filterCollapsibleSection(
                $this->menuConfig['contractor_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($contractorSection['items'])) {
                $visibleMenus['contractor_section'] = $contractorSection;
            }
        }
        
        // Engineer Portal section - ONLY for engineers (not contractor admins)
        if ($isEngineer) {
            $engineerSection = $this->filterCollapsibleSection(
                $this->menuConfig['engineer_section'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($engineerSection['items'])) {
                $visibleMenus['engineer_section'] = $engineerSection;
            }
        }
        
        // ADV-only section - only for ADV users
        if ($isAdvUser) {
            $advOnlyMenus = $this->filterMenusByPermission(
                $this->menuConfig['adv_only'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($advOnlyMenus)) {
                $visibleMenus['adv_only'] = $advOnlyMenus;
            }
            
            // System section - ADV only
            $systemMenus = $this->filterMenusByPermission(
                $this->menuConfig['system'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($systemMenus)) {
                $visibleMenus['system'] = $systemMenus;
            }
            
            // Admin section - ADV only
            $adminMenus = $this->filterMenusByPermission(
                $this->menuConfig['admin'], 
                $userId, 
                $isAdvUser
            );
            if (!empty($adminMenus)) {
                $visibleMenus['admin'] = $adminMenus;
            }
        }
        
        return $visibleMenus;
    }
    
    /**
     * Filter collapsible section items by permission
     */
    private function filterCollapsibleSection($section, $userId, $isAdvUser) {
        // Check if section is ADV-only
        if (isset($section['adv_only']) && $section['adv_only'] && !$isAdvUser) {
            return ['items' => []];
        }
        
        $result = [
            'id' => $section['id'] ?? '',
            'label' => $section['label'] ?? '',
            'icon' => $section['icon'] ?? '',
            'collapsible' => $section['collapsible'] ?? false,
            'items' => []
        ];
        
        if (!isset($section['items'])) {
            return $result;
        }
        
        foreach ($section['items'] as $item) {
            // Check if item has nested items (nested collapsible)
            if (isset($item['collapsible']) && $item['collapsible'] && isset($item['items'])) {
                $nestedSection = $this->filterCollapsibleSection($item, $userId, $isAdvUser);
                if (!empty($nestedSection['items'])) {
                    $result['items'][] = $nestedSection;
                }
            } else {
                // Regular menu item
                if ($this->isMenuItemVisible($item, $userId, $isAdvUser)) {
                    $result['items'][] = $item;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Filter menu items by permission
     */
    private function filterMenusByPermission($menuItems, $userId, $isAdvUser) {
        $visible = [];
        
        foreach ($menuItems as $item) {
            if ($this->isMenuItemVisible($item, $userId, $isAdvUser)) {
                $visible[] = $item;
            }
        }
        
        return $visible;
    }
    
    /**
     * Check if a menu item is visible for a user
     */
    public function isMenuItemVisible($menuItem, $userId, $isAdvUser = null) {
        // If isAdvUser not provided, look it up
        if ($isAdvUser === null) {
            $user = $this->userModel->findWithRelations($userId);
            if (!$user) {
                return false;
            }
            $isAdvUser = strtoupper($user['company_type']) === 'ADV';
        } else {
            $user = $this->userModel->findWithRelations($userId);
        }
        
        // ADV-only items are never visible to contractors
        if (isset($menuItem['adv_only']) && $menuItem['adv_only'] && !$isAdvUser) {
            return false;
        }
        
        // Contractor-only items are only visible to contractor users (not ADV)
        if (isset($menuItem['contractor_only']) && $menuItem['contractor_only']) {
            if ($isAdvUser) {
                return false;
            }
            // Also check if user is a contractor admin (not engineer)
            if (!isContractorAdmin($userId)) {
                return false;
            }
        }
        
        // Engineer-only items are only visible to engineers
        if (isset($menuItem['engineer_only']) && $menuItem['engineer_only']) {
            $isContractorUser = $user && strtoupper($user['company_type']) === 'CONTRACTOR';
            $isEngineer = $isContractorUser && !isContractorAdmin($userId);
            if (!$isEngineer) {
                return false;
            }
        }
        
        // If no permission required, item is visible
        if (empty($menuItem['permission'])) {
            return true;
        }
        
        // ADV users have access to all inventory menu items
        if ($isAdvUser && strpos($menuItem['permission'], 'inventory.') === 0) {
            return true;
        }
        
        // ADV users have access to all IP configuration menu items
        // **Feature: ip-configuration-management, Menu Integration**
        if ($isAdvUser && strpos($menuItem['permission'], 'ip_configuration.') === 0) {
            return true;
        }
        
        // ADV users have access to all feasibility menu items
        // **Feature: feasibility-module, Menu Integration**
        if ($isAdvUser && strpos($menuItem['permission'], 'feasibility.') === 0) {
            return true;
        }
        
        // ADV users have access to all installation menu items
        // **Feature: installation-module, Menu Integration**
        if ($isAdvUser && strpos($menuItem['permission'], 'installation.') === 0) {
            return true;
        }
        
        // Check if user has the required permission
        return $this->permissionEngine->can($userId, $menuItem['permission']);
    }
    
    /**
     * Check if a module section is visible for a user
     */
    public function isModuleSectionVisible($moduleName, $userId) {
        $user = $this->userModel->findWithRelations($userId);
        if (!$user) {
            return false;
        }
        
        $isAdvUser = strtoupper($user['company_type']) === 'ADV';
        
        // ADV-only modules are never visible to contractors
        if (in_array($moduleName, $this->advOnlyModules) && !$isAdvUser) {
            return false;
        }
        
        // Check if user has any permission in this module
        if (!isset($this->menuConfig[$moduleName])) {
            return false;
        }
        
        $section = $this->menuConfig[$moduleName];
        
        // Handle collapsible sections
        if (isset($section['collapsible']) && $section['collapsible']) {
            foreach ($section['items'] as $item) {
                if (isset($item['collapsible']) && $item['collapsible']) {
                    // Nested collapsible
                    foreach ($item['items'] as $nestedItem) {
                        if ($this->isMenuItemVisible($nestedItem, $userId, $isAdvUser)) {
                            return true;
                        }
                    }
                } else {
                    if ($this->isMenuItemVisible($item, $userId, $isAdvUser)) {
                        return true;
                    }
                }
            }
            return false;
        }
        
        // Handle flat menu arrays
        foreach ($section as $item) {
            if ($this->isMenuItemVisible($item, $userId, $isAdvUser)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all menu configuration
     */
    public function getMenuConfig() {
        return $this->menuConfig;
    }
    
    /**
     * Get Masters section configuration
     * **Feature: crm-sidebar-restructure, Property 1: Masters Section Structure**
     */
    public function getMastersSection() {
        return $this->menuConfig['masters'];
    }
    
    /**
     * Get Users section configuration
     * **Feature: crm-sidebar-restructure, Property 2: Users Section Structure**
     */
    public function getUsersSection() {
        return $this->menuConfig['users_section'];
    }
    
    /**
     * Get Location Master submenu configuration
     * **Feature: crm-sidebar-restructure, Property 3: Location Master Submenu Structure**
     */
    public function getLocationMasterSubmenu() {
        $mastersSection = $this->menuConfig['masters'];
        foreach ($mastersSection['items'] as $item) {
            if ($item['id'] === 'location_master') {
                return $item;
            }
        }
        return null;
    }
    
    /**
     * Get ADV-only modules list
     */
    public function getAdvOnlyModules() {
        return $this->advOnlyModules;
    }
    
    /**
     * Check if a module is ADV-only
     */
    public function isAdvOnlyModule($moduleName) {
        return in_array($moduleName, $this->advOnlyModules);
    }
    
    /**
     * Get flat list of all menu items
     */
    public function getAllMenuItems() {
        $allItems = [];
        foreach ($this->menuConfig as $section => $data) {
            // Handle collapsible sections
            if (isset($data['collapsible']) && $data['collapsible']) {
                foreach ($data['items'] as $item) {
                    if (isset($item['collapsible']) && $item['collapsible']) {
                        // Nested collapsible
                        foreach ($item['items'] as $nestedItem) {
                            $nestedItem['section'] = $section;
                            $allItems[] = $nestedItem;
                        }
                    } else {
                        $item['section'] = $section;
                        $allItems[] = $item;
                    }
                }
            } else {
                // Flat menu array
                foreach ($data as $item) {
                    if (is_array($item) && isset($item['id'])) {
                        $item['section'] = $section;
                        $allItems[] = $item;
                    }
                }
            }
        }
        return $allItems;
    }
    
    /**
     * Check if any child item is active in a section
     * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
     */
    public function isAnyChildActive($items, $currentPage) {
        foreach ($items as $item) {
            if (isset($item['collapsible']) && $item['collapsible'] && isset($item['items'])) {
                // Nested collapsible - check recursively
                if ($this->isAnyChildActive($item['items'], $currentPage)) {
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
     * Get the section ID that contains the active page
     * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
     */
    public function getActiveSectionId($currentPage) {
        // Check masters section
        if (isset($this->menuConfig['masters']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['masters']['items'], $currentPage)) {
                return 'masters_section';
            }
        }
        
        // Check users section
        if (isset($this->menuConfig['users_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['users_section']['items'], $currentPage)) {
                return 'users_section';
            }
        }
        
        // Check sites section
        if (isset($this->menuConfig['sites_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['sites_section']['items'], $currentPage)) {
                return 'sites_section';
            }
        }
        
        // Check delegations section
        if (isset($this->menuConfig['delegations_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['delegations_section']['items'], $currentPage)) {
                return 'delegations_section';
            }
        }
        
        // Check IP Configuration section
        // **Feature: ip-configuration-management, Menu Integration**
        if (isset($this->menuConfig['ip_configuration_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['ip_configuration_section']['items'], $currentPage)) {
                return 'ip_configuration_section';
            }
        }
        
        // Check Feasibility Tracking section
        // **Feature: feasibility-module, Menu Integration**
        if (isset($this->menuConfig['feasibility_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['feasibility_section']['items'], $currentPage)) {
                return 'feasibility_section';
            }
        }
        
        // Check Installation Tracking section
        // **Feature: installation-module, Menu Integration**
        if (isset($this->menuConfig['installation_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['installation_section']['items'], $currentPage)) {
                return 'installation_section';
            }
        }
        
        // Check inventory section
        if (isset($this->menuConfig['inventory_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['inventory_section']['items'], $currentPage)) {
                return 'inventory_section';
            }
        }
        
        // Check contractor section
        if (isset($this->menuConfig['contractor_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['contractor_section']['items'], $currentPage)) {
                return 'contractor_section';
            }
        }
        
        // Check engineer section
        if (isset($this->menuConfig['engineer_section']['items'])) {
            if ($this->isAnyChildActive($this->menuConfig['engineer_section']['items'], $currentPage)) {
                return 'engineer_section';
            }
        }
        
        return null;
    }
    
    /**
     * Get the nested section ID that contains the active page (for Location Master)
     */
    public function getActiveNestedSectionId($currentPage) {
        // Check Location Master submenu
        $locationMaster = $this->getLocationMasterSubmenu();
        if ($locationMaster && isset($locationMaster['items'])) {
            if ($this->isAnyChildActive($locationMaster['items'], $currentPage)) {
                return 'location_master';
            }
        }
        
        return null;
    }
    
    /**
     * Render menu HTML for a user
     */
    public function renderMenuHtml($userId, $currentPage = '', $baseUrl = '') {
        $visibleMenus = $this->getVisibleMenus($userId);
        $user = $this->userModel->findWithRelations($userId);
        $isAdvUser = $user && strtoupper($user['company_type']) === 'ADV';
        $isContractorUser = $user && strtoupper($user['company_type']) === 'CONTRACTOR';
        
        $html = '<nav class="flex-1 overflow-y-auto py-4 px-3">';
        
        // Main menu section (Dashboard)
        if (!empty($visibleMenus['main'])) {
            $html .= '<div class="space-y-1">';
            foreach ($visibleMenus['main'] as $item) {
                $html .= $this->renderMenuItem($item, $currentPage, $baseUrl);
            }
            $html .= '</div>';
        }
        
        // Masters section (collapsible) - ADV only
        if ($isAdvUser && !empty($visibleMenus['masters'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['masters'], $currentPage, $baseUrl);
        }
        
        // Users section (collapsible)
        if (!empty($visibleMenus['users_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['users_section'], $currentPage, $baseUrl);
        }
        
        // Site Management section (collapsible) - ADV only
        if ($isAdvUser && !empty($visibleMenus['sites_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['sites_section'], $currentPage, $baseUrl);
        }
        
        // Delegation Tracking section (collapsible) - ADV only
        if ($isAdvUser && !empty($visibleMenus['delegations_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['delegations_section'], $currentPage, $baseUrl);
        }
        
        // IP Configuration section (collapsible) - ADV only
        // **Feature: ip-configuration-management, Menu Integration**
        if ($isAdvUser && !empty($visibleMenus['ip_configuration_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['ip_configuration_section'], $currentPage, $baseUrl);
        }
        
        // Feasibility Tracking section (collapsible) - ADV only
        // **Feature: feasibility-module, Menu Integration**
        if ($isAdvUser && !empty($visibleMenus['feasibility_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['feasibility_section'], $currentPage, $baseUrl);
        }
        
        // Installation Tracking section (collapsible) - ADV only
        // **Feature: installation-module, Menu Integration**
        if ($isAdvUser && !empty($visibleMenus['installation_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['installation_section'], $currentPage, $baseUrl);
        }
        
        // Inventory section (collapsible) - All users with appropriate permissions
        if (!empty($visibleMenus['inventory_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['inventory_section'], $currentPage, $baseUrl);
        }
        
        // Contractor Portal section (collapsible) - Contractor only
        if ($isContractorUser && !empty($visibleMenus['contractor_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['contractor_section'], $currentPage, $baseUrl);
        }
        
        // Engineer Portal section (collapsible) - Contractor users with engineer permissions
        if ($isContractorUser && !empty($visibleMenus['engineer_section'])) {
            $html .= $this->renderCollapsibleSection($visibleMenus['engineer_section'], $currentPage, $baseUrl);
        }
        
        // ADV-only section
        if ($isAdvUser && !empty($visibleMenus['adv_only'])) {
            $html .= '<div class="mt-6 pt-6 border-t border-dark-600">';
            $html .= '<p class="px-4 text-xs font-semibold text-dark-500 uppercase tracking-wider mb-3">ADV Only</p>';
            $html .= '<div class="space-y-1">';
            foreach ($visibleMenus['adv_only'] as $item) {
                $html .= $this->renderMenuItem($item, $currentPage, $baseUrl);
            }
            $html .= '</div></div>';
        }
        
        // System section
        if ($isAdvUser && !empty($visibleMenus['system'])) {
            $html .= '<div class="mt-6 pt-6 border-t border-dark-600">';
            $html .= '<p class="px-4 text-xs font-semibold text-dark-500 uppercase tracking-wider mb-3">System</p>';
            $html .= '<div class="space-y-1">';
            foreach ($visibleMenus['system'] as $item) {
                $html .= $this->renderMenuItem($item, $currentPage, $baseUrl);
            }
            $html .= '</div></div>';
        }
        
        // Admin section
        if ($isAdvUser && !empty($visibleMenus['admin'])) {
            $html .= '<div class="mt-6 pt-6 border-t border-dark-600">';
            $html .= '<p class="px-4 text-xs font-semibold text-dark-500 uppercase tracking-wider mb-3">Admin</p>';
            $html .= '<div class="space-y-1">';
            foreach ($visibleMenus['admin'] as $item) {
                $html .= $this->renderMenuItem($item, $currentPage, $baseUrl);
            }
            $html .= '</div></div>';
        }
        
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Render a collapsible section
     * **Feature: crm-sidebar-restructure, Property 8: Active Section Auto-Expand**
     * **Feature: crm-sidebar-restructure, Property 9: Active Menu Item Highlighting**
     */
    public function renderCollapsibleSection($section, $currentPage, $baseUrl, $isNested = false) {
        $sectionId = $section['id'] ?? '';
        $label = $section['label'] ?? '';
        $icon = $section['icon'] ?? '';
        $items = $section['items'] ?? [];
        
        // Check if any child is active to auto-expand
        $isExpanded = $this->isAnyChildActive($items, $currentPage);
        $expandedClass = $isExpanded ? '' : 'hidden';
        $chevronClass = $isExpanded ? 'rotate-90' : '';
        
        $marginClass = $isNested ? 'ml-4' : 'mt-6 pt-6 border-t border-dark-600';
        $paddingClass = $isNested ? 'pl-4' : '';
        
        $html = '<div class="' . $marginClass . '">';
        
        // Section header (clickable to toggle)
        $html .= sprintf(
            '<button type="button" class="collapsible-toggle w-full flex items-center justify-between px-4 py-2 text-xs font-semibold text-dark-500 uppercase tracking-wider hover:text-gray-300 transition" data-section="%s">
                <span class="flex items-center">
                    <i class="fas %s mr-2"></i>
                    %s
                </span>
                <i class="fas fa-chevron-right transition-transform duration-200 %s" id="chevron-%s"></i>
            </button>',
            htmlspecialchars($sectionId),
            htmlspecialchars($icon),
            htmlspecialchars($label),
            $chevronClass,
            htmlspecialchars($sectionId)
        );
        
        // Section items container
        $html .= sprintf(
            '<div id="section-%s" class="space-y-1 mt-2 %s %s">',
            htmlspecialchars($sectionId),
            $paddingClass,
            $expandedClass
        );
        
        foreach ($items as $item) {
            // Check if item is a nested collapsible section
            if (isset($item['collapsible']) && $item['collapsible'] && isset($item['items'])) {
                $html .= $this->renderCollapsibleSection($item, $currentPage, $baseUrl, true);
            } else {
                $html .= $this->renderMenuItem($item, $currentPage, $baseUrl);
            }
        }
        
        $html .= '</div></div>';
        
        return $html;
    }
    
    /**
     * Render a single menu item
     * **Feature: crm-sidebar-restructure, Property 9: Active Menu Item Highlighting**
     */
    private function renderMenuItem($item, $currentPage, $baseUrl) {
        $isActive = $currentPage === $item['id'];
        $activeClass = $isActive ? 'active text-white' : 'hover:text-white';
        $iconColor = $this->getIconColor($item['id']);
        
        return sprintf(
            '<a href="%s%s" class="sidebar-link flex items-center px-4 py-3 rounded-lg text-gray-300 %s">
                <i class="fas %s w-5 mr-3 %s"></i>
                <span>%s</span>
            </a>',
            htmlspecialchars($baseUrl),
            htmlspecialchars($item['url']),
            $activeClass,
            htmlspecialchars($item['icon']),
            $iconColor,
            htmlspecialchars($item['label'])
        );
    }
    
    /**
     * Get icon color based on menu item ID
     */
    private function getIconColor($itemId) {
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
            // Site Management
            'sites_list' => 'text-emerald-400',
            'sites_add' => 'text-green-400',
            'sites_bulk_upload' => 'text-teal-400',
            'sites_delegate' => 'text-cyan-400',
            'sites_bulk_delegate' => 'text-sky-400',
            // Delegation Tracking
            'delegations_list' => 'text-violet-400',
            'delegations_history' => 'text-purple-400',
            // IP Configuration
            // **Feature: ip-configuration-management, Menu Integration**
            'ip_config_dashboard' => 'text-primary',
            'ip_config_ip_master' => 'text-blue-400',
            'ip_config_configure' => 'text-green-400',
            'ip_config_reports' => 'text-amber-400',
            'ip_config_audit' => 'text-purple-400',
            // Contractor Portal
            'contractor_delegations' => 'text-amber-400',
            'contractor_pending_reviews' => 'text-yellow-400',
            'contractor_assign' => 'text-orange-400',
            'contractor_bulk_assign' => 'text-yellow-400',
            'contractor_pending_receives' => 'text-cyan-400',
            'contractor_dispatch' => 'text-orange-400',
            // Engineer Portal
            'engineer_sites' => 'text-lime-400',
            'engineer_feasibility' => 'text-emerald-400',
            'engineer_pending_receives' => 'text-cyan-400',
            'engineer_dispatch' => 'text-orange-400',
            // Feasibility Tracking (ADV)
            // **Feature: feasibility-module, Menu Integration**
            'feasibility_tracking' => 'text-teal-400',
            'adv_pending_reviews' => 'text-blue-400',
            'feasibility_export' => 'text-green-400',
            // Installation Tracking (ADV)
            // **Feature: installation-module, Menu Integration**
            'installation_tracking' => 'text-emerald-400',
            'installation_list' => 'text-teal-400',
            'adv_installation_reviews' => 'text-blue-400',
            'contractor_installation_list' => 'text-emerald-400',
            'contractor_installation_reviews' => 'text-cyan-400',
            'engineer_installations' => 'text-emerald-400',
            // Inventory
            'inventory_dashboard_adv' => 'text-primary',
            'inventory_dashboard_contractor' => 'text-primary',
            'inventory_dashboard_engineer' => 'text-primary',
            'inventory_warehouses' => 'text-amber-400',
            'inventory_products' => 'text-blue-400',
            'inventory_stock' => 'text-green-400',
            'inventory_dispatch' => 'text-orange-400',
            'inventory_transfers' => 'text-purple-400',
            'inventory_assets' => 'text-indigo-400',
            'inventory_item_history' => 'text-violet-400',
            'inventory_repairs' => 'text-red-400',
            'inventory_material_masters' => 'text-lime-400',
            'inventory_material_requests' => 'text-cyan-400',
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
            'admin_reports' => 'text-amber-400',
            'admin_filemanager' => 'text-cyan-400'
        ];
        
        return $colors[$itemId] ?? 'text-gray-400';
    }
    
    /**
     * Get Sites section configuration
     * **Feature: site-management-delegation, Menu Integration**
     */
    public function getSitesSection() {
        return $this->menuConfig['sites_section'] ?? null;
    }
    
    /**
     * Get Delegations section configuration
     * **Feature: site-management-delegation, Menu Integration**
     */
    public function getDelegationsSection() {
        return $this->menuConfig['delegations_section'] ?? null;
    }
    
    /**
     * Get Contractor section configuration
     * **Feature: site-management-delegation, Menu Integration**
     */
    public function getContractorSection() {
        return $this->menuConfig['contractor_section'] ?? null;
    }
    
    /**
     * Get Engineer section configuration
     * **Feature: site-management-delegation, Menu Integration**
     */
    public function getEngineerSection() {
        return $this->menuConfig['engineer_section'] ?? null;
    }
    
    /**
     * Get IP Configuration section configuration
     * **Feature: ip-configuration-management, Menu Integration**
     */
    public function getIPConfigurationSection() {
        return $this->menuConfig['ip_configuration_section'] ?? null;
    }
    
    /**
     * Get File Manager menu item configuration
     * **Feature: file-manager-module, Menu Integration**
     */
    public function getFileManagerMenuItem() {
        if (isset($this->menuConfig['admin'])) {
            foreach ($this->menuConfig['admin'] as $item) {
                if ($item['id'] === 'admin_filemanager') {
                    return $item;
                }
            }
        }
        return null;
    }
    
    /**
     * Get Feasibility Tracking section configuration
     * **Feature: feasibility-module, Menu Integration**
     */
    public function getFeasibilitySection() {
        return $this->menuConfig['feasibility_section'] ?? null;
    }
    
    /**
     * Get Installation Tracking section configuration
     * **Feature: installation-module, Menu Integration**
     */
    public function getInstallationSection() {
        return $this->menuConfig['installation_section'] ?? null;
    }
}
