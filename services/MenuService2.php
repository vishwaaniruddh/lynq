<?php
/**
 * Menu Service 2
 * Enhanced, comprehensive menu service covering all system modules,
 * role-based portals, and collapsible submenus.
 */

require_once __DIR__ . '/../config/autoload.php';

class MenuService2 {
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
     * Initialize comprehensive menu configuration
     */
    private function initializeMenuConfig() {
        $this->menuConfig = [
            'main' => [
                [
                    'id' => 'dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'fa-home',
                    'url' => '/dashboard.php',
                    'permission' => null,
                    'adv_only' => false
                ]
            ],
            
            // 1. Masters Section (ADV only)
            'masters' => [
                'id' => 'masters_section',
                'label' => 'Masters',
                'icon' => 'fa-database',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'masters_companies', 'label' => 'Company Master', 'icon' => 'fa-building', 'url' => '/companies/index.php', 'permission' => 'companies.read', 'adv_only' => true],
                    ['id' => 'masters_banks', 'label' => 'Bank Master', 'icon' => 'fa-university', 'url' => '/masters/banks.php', 'permission' => 'masters.banks.view', 'adv_only' => true],
                    ['id' => 'masters_customers', 'label' => 'Customer Master', 'icon' => 'fa-users', 'url' => '/masters/customers.php', 'permission' => 'masters.customers.view', 'adv_only' => true],
                    ['id' => 'masters_projects', 'label' => 'Projects Master', 'icon' => 'fa-project-diagram', 'url' => '/masters/projects.php', 'permission' => 'masters.projects.view', 'adv_only' => true],
                    ['id' => 'masters_forms', 'label' => 'Forms Master', 'icon' => 'fa-wpforms', 'url' => '/masters/forms.php', 'permission' => 'masters.forms.view', 'adv_only' => true],
                    ['id' => 'masters_couriers', 'label' => 'Courier Master', 'icon' => 'fa-truck', 'url' => '/masters/couriers.php', 'permission' => 'masters.couriers.view', 'adv_only' => true],
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
            
            // 2. User & Access Control
            'users_section' => [
                'id' => 'users_section',
                'label' => 'Users & Roles',
                'icon' => 'fa-users-cog',
                'collapsible' => true,
                'adv_only' => false,
                'items' => [
                    ['id' => 'users', 'label' => 'Users', 'icon' => 'fa-user', 'url' => '/users/index.php', 'permission' => 'users.read', 'adv_only' => false],
                    ['id' => 'roles', 'label' => 'Roles', 'icon' => 'fa-user-tag', 'url' => '/roles/index.php', 'permission' => 'roles.read', 'adv_only' => false],
                    ['id' => 'permissions', 'label' => 'Permissions', 'icon' => 'fa-key', 'url' => '/permissions/index.php', 'permission' => 'permissions.read', 'adv_only' => false]
                ]
            ],
            
            // 3. Site Management
            'sites_section' => [
                'id' => 'sites_section',
                'label' => 'Site Management',
                'icon' => 'fa-map-marker-alt',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'sites_list', 'label' => 'All Sites', 'icon' => 'fa-list', 'url' => '/sites/index.php', 'permission' => 'sites.view', 'adv_only' => true],
                    ['id' => 'sites_add', 'label' => 'Add Site', 'icon' => 'fa-plus', 'url' => '/sites/site_add_custome_form.php', 'permission' => 'sites.create', 'adv_only' => true],
                    ['id' => 'sites_bulk_upload_dynamic', 'label' => 'Bulk Upload', 'icon' => 'fa-upload', 'url' => '/sites/bulk_upload_dynamic.php', 'permission' => 'sites.bulk_upload', 'adv_only' => true],
                    ['id' => 'sites_delegate', 'label' => 'Delegate Sites', 'icon' => 'fa-share-alt', 'url' => '/sites/delegate.php', 'permission' => 'sites.delegate', 'adv_only' => true],
                    ['id' => 'sites_bulk_delegate', 'label' => 'Bulk Delegate', 'icon' => 'fa-share-square', 'url' => '/sites/bulk_delegate.php', 'permission' => 'sites.delegate', 'adv_only' => true]
                ]
            ],
            
            // 4. Delegation Tracking
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
            
            // 5. Feasibility Survey Pipeline
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
            
            // 6. Installation Operations
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
            
            // 7. IP Configuration & Network
            'ip_configuration_section' => [
                'id' => 'ip_configuration_section',
                'label' => 'IP Configuration',
                'icon' => 'fa-network-wired',
                'collapsible' => true,
                'adv_only' => true,
                'items' => [
                    ['id' => 'ip_config_dashboard', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/configuration/dashboard.php', 'permission' => 'ip_configuration.view', 'adv_only' => true],
                    ['id' => 'ip_config_ip_master', 'label' => 'IP Master Pool', 'icon' => 'fa-server', 'url' => '/configuration/ip_master.php', 'permission' => 'ip_configuration.ip_master.view', 'adv_only' => true],
                    ['id' => 'ip_config_configure', 'label' => 'Configure Router', 'icon' => 'fa-cogs', 'url' => '/configuration/configure.php', 'permission' => 'ip_configuration.configure', 'adv_only' => true],
                    ['id' => 'ip_config_reports', 'label' => 'Reports', 'icon' => 'fa-chart-bar', 'url' => '/configuration/reports.php', 'permission' => 'ip_configuration.reports.view', 'adv_only' => true],
                    ['id' => 'ip_config_audit', 'label' => 'Audit History', 'icon' => 'fa-history', 'url' => '/configuration/audit.php', 'permission' => 'ip_configuration.audit.view', 'adv_only' => true]
                ]
            ],
            
            // 8. Inventory & Supply Chain
            'inventory_section' => [
                'id' => 'inventory_section',
                'label' => 'Inventory & Supply Chain',
                'icon' => 'fa-boxes',
                'collapsible' => true,
                'adv_only' => false,
                'items' => [
                    ['id' => 'inventory_dashboard_adv', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/inventory/dashboard_adv.php', 'permission' => 'inventory.dashboard.adv', 'adv_only' => true],
                    ['id' => 'inventory_dashboard_contractor', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/inventory/dashboard_contractor.php', 'permission' => 'inventory.dashboard.contractor', 'adv_only' => false, 'contractor_only' => true],
                    ['id' => 'inventory_dashboard_engineer', 'label' => 'Dashboard', 'icon' => 'fa-tachometer-alt', 'url' => '/inventory/dashboard_engineer.php', 'permission' => 'inventory.dashboard.engineer', 'adv_only' => false, 'engineer_only' => true],
                    ['id' => 'inventory_warehouses', 'label' => 'Warehouses (Stock Hubs)', 'icon' => 'fa-warehouse', 'url' => '/inventory/warehouses.php', 'permission' => 'inventory.warehouses.view', 'adv_only' => false],
                    ['id' => 'inventory_product_categories', 'label' => 'Product Categories', 'icon' => 'fa-folder', 'url' => '/masters/product_categories.php', 'permission' => 'masters.product_categories.view', 'adv_only' => true],
                    ['id' => 'inventory_products', 'label' => 'Products Catalog', 'icon' => 'fa-box', 'url' => '/inventory/products.php', 'permission' => 'inventory.products.view', 'adv_only' => false],
                    ['id' => 'inventory_stock', 'label' => 'Stock Entry', 'icon' => 'fa-plus-circle', 'url' => '/inventory/stock.php', 'permission' => 'inventory.stock.create', 'adv_only' => true],
                    ['id' => 'inventory_dispatch', 'label' => 'Dispatch Logistics', 'icon' => 'fa-truck', 'url' => '/inventory/dispatch.php', 'permission' => 'inventory.dispatch.view', 'adv_only' => false],
                    ['id' => 'inventory_transfers', 'label' => 'Stock Transfers', 'icon' => 'fa-exchange-alt', 'url' => '/inventory/transfers.php', 'permission' => 'inventory.transfers.view', 'adv_only' => true],
                    ['id' => 'inventory_assets', 'label' => 'Serialized Assets', 'icon' => 'fa-barcode', 'url' => '/inventory/assets.php', 'permission' => 'inventory.assets.view', 'adv_only' => false],
                    ['id' => 'inventory_item_history', 'label' => 'Item History', 'icon' => 'fa-history', 'url' => '/inventory/item-history.php', 'permission' => 'inventory.assets.view', 'adv_only' => true],
                    ['id' => 'inventory_repairs', 'label' => 'Asset Repairs', 'icon' => 'fa-tools', 'url' => '/inventory/repairs.php', 'permission' => 'inventory.repairs.view', 'adv_only' => false],
                    ['id' => 'inventory_material_masters', 'label' => 'Material Masters', 'icon' => 'fa-clipboard-list', 'url' => '/inventory/material-masters.php', 'permission' => 'inventory.material_masters.view', 'adv_only' => true],
                    ['id' => 'inventory_material_requests', 'label' => 'Material Requests', 'icon' => 'fa-file-invoice', 'url' => '/inventory/material-requests.php', 'permission' => 'inventory.material_requests.view', 'adv_only' => true]
                ]
            ],
            
            // 9. Contractor Portal
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
            
            // 10. Engineer Portal
            'engineer_section' => [
                'id' => 'engineer_section',
                'label' => 'Engineer Portal',
                'icon' => 'fa-user-cog',
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
            
            // 11. ADV Governance
            'adv_only' => [
                [
                    'id' => 'delegation',
                    'label' => 'Permission Delegation',
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
            
            // 12. System Admin
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
            ]
        ];
    }
    
    /**
     * Get visible menus for a user
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
        
        if (!$isEngineer) {
            $visibleMenus['main'] = $this->filterMenusByPermission($this->menuConfig['main'], $userId, $isAdvUser);
        }
        
        if ($isAdvUser) {
            $mastersSection = $this->filterCollapsibleSection($this->menuConfig['masters'], $userId, $isAdvUser);
            if (!empty($mastersSection['items'])) $visibleMenus['masters'] = $mastersSection;
        }
        
        if (!$isEngineer) {
            $usersSection = $this->filterCollapsibleSection($this->menuConfig['users_section'], $userId, $isAdvUser);
            if (!empty($usersSection['items'])) $visibleMenus['users_section'] = $usersSection;
        }
        
        if ($isAdvUser) {
            $sitesSection = $this->filterCollapsibleSection($this->menuConfig['sites_section'], $userId, $isAdvUser);
            if (!empty($sitesSection['items'])) $visibleMenus['sites_section'] = $sitesSection;
            
            $delegationsSection = $this->filterCollapsibleSection($this->menuConfig['delegations_section'], $userId, $isAdvUser);
            if (!empty($delegationsSection['items'])) $visibleMenus['delegations_section'] = $delegationsSection;
            
            $ipConfigSection = $this->filterCollapsibleSection($this->menuConfig['ip_configuration_section'], $userId, $isAdvUser);
            if (!empty($ipConfigSection['items'])) $visibleMenus['ip_configuration_section'] = $ipConfigSection;
            
            $feasibilitySection = $this->filterCollapsibleSection($this->menuConfig['feasibility_section'], $userId, $isAdvUser);
            if (!empty($feasibilitySection['items'])) $visibleMenus['feasibility_section'] = $feasibilitySection;
            
            $installationSection = $this->filterCollapsibleSection($this->menuConfig['installation_section'], $userId, $isAdvUser);
            if (!empty($installationSection['items'])) $visibleMenus['installation_section'] = $installationSection;
        }
        
        $inventorySection = $this->filterCollapsibleSection($this->menuConfig['inventory_section'], $userId, $isAdvUser);
        if (!empty($inventorySection['items'])) $visibleMenus['inventory_section'] = $inventorySection;
        
        if ($isContractorUser && isContractorAdmin($userId)) {
            $contractorSection = $this->filterCollapsibleSection($this->menuConfig['contractor_section'], $userId, $isAdvUser);
            if (!empty($contractorSection['items'])) $visibleMenus['contractor_section'] = $contractorSection;
        }
        
        if ($isEngineer) {
            $engineerSection = $this->filterCollapsibleSection($this->menuConfig['engineer_section'], $userId, $isAdvUser);
            if (!empty($engineerSection['items'])) $visibleMenus['engineer_section'] = $engineerSection;
        }
        
        if ($isAdvUser) {
            $advOnlyMenus = $this->filterMenusByPermission($this->menuConfig['adv_only'], $userId, $isAdvUser);
            if (!empty($advOnlyMenus)) $visibleMenus['adv_only'] = $advOnlyMenus;
            
            $systemMenus = $this->filterMenusByPermission($this->menuConfig['system'], $userId, $isAdvUser);
            if (!empty($systemMenus)) $visibleMenus['system'] = $systemMenus;
        }
        
        return $visibleMenus;
    }
    
    private function filterCollapsibleSection(array $section, $userId, bool $isAdvUser) {
        $filteredSection = $section;
        $filteredSection['items'] = $this->filterMenusByPermission($section['items'], $userId, $isAdvUser);
        return $filteredSection;
    }
    
    private function filterMenusByPermission(array $items, $userId, bool $isAdvUser) {
        $filtered = [];
        $user = $this->userModel->findWithRelations($userId);
        $isContractorUser = strtoupper($user['company_type']) === 'CONTRACTOR';
        $isEngineer = $isContractorUser && !isContractorAdmin($userId);
        
        foreach ($items as $item) {
            if (isset($item['collapsible']) && $item['collapsible']) {
                $subSection = $this->filterCollapsibleSection($item, $userId, $isAdvUser);
                if (!empty($subSection['items'])) {
                    $filtered[] = $subSection;
                }
                continue;
            }
            
            if (isset($item['adv_only']) && $item['adv_only'] && !$isAdvUser) continue;
            if (isset($item['contractor_only']) && $item['contractor_only'] && (!$isContractorUser || $isEngineer)) continue;
            if (isset($item['engineer_only']) && $item['engineer_only'] && !$isEngineer) continue;
            
            if ($item['permission'] === null || $this->hasPermission($userId, $item['permission'])) {
                $filtered[] = $item;
            }
        }
        
        return $filtered;
    }
    
    public function isMenuItemVisible($menuItem, $userId, $isAdvUser = null) {
        if ($isAdvUser === null) {
            $user = $this->userModel->findWithRelations($userId);
            if (!$user) return false;
            $isAdvUser = strtoupper($user['company_type']) === 'ADV';
        } else {
            $user = $this->userModel->findWithRelations($userId);
        }
        
        if (isset($menuItem['adv_only']) && $menuItem['adv_only'] && !$isAdvUser) return false;
        if (isset($menuItem['contractor_only']) && $menuItem['contractor_only']) {
            if ($isAdvUser) return false;
            if (!isContractorAdmin($userId)) return false;
        }
        if (isset($menuItem['engineer_only']) && $menuItem['engineer_only']) {
            $isContractorUser = $user && strtoupper($user['company_type']) === 'CONTRACTOR';
            $isEngineer = $isContractorUser && !isContractorAdmin($userId);
            if (!$isEngineer) return false;
        }
        
        if (empty($menuItem['permission'])) return true;
        
        if ($isAdvUser && (
            strpos($menuItem['permission'], 'inventory.') === 0 ||
            strpos($menuItem['permission'], 'ip_configuration.') === 0 ||
            strpos($menuItem['permission'], 'feasibility.') === 0 ||
            strpos($menuItem['permission'], 'installation.') === 0
        )) {
            return true;
        }
        
        return $this->permissionEngine->can($userId, $menuItem['permission']);
    }
    
    public function isModuleSectionVisible($moduleName, $userId) {
        $user = $this->userModel->findWithRelations($userId);
        if (!$user) return false;
        $isAdvUser = strtoupper($user['company_type']) === 'ADV';
        
        if (in_array($moduleName, $this->advOnlyModules) && !$isAdvUser) return false;
        if (!isset($this->menuConfig[$moduleName])) return false;
        
        $section = $this->menuConfig[$moduleName];
        if (isset($section['collapsible']) && $section['collapsible']) {
            foreach ($section['items'] as $item) {
                if (isset($item['collapsible']) && $item['collapsible']) {
                    foreach ($item['items'] as $nestedItem) {
                        if ($this->isMenuItemVisible($nestedItem, $userId, $isAdvUser)) return true;
                    }
                } else {
                    if ($this->isMenuItemVisible($item, $userId, $isAdvUser)) return true;
                }
            }
            return false;
        }
        
        foreach ($section as $item) {
            if ($this->isMenuItemVisible($item, $userId, $isAdvUser)) return true;
        }
        return false;
    }
    
    public function getMenuConfig() {
        return $this->menuConfig;
    }
    
    public function getMastersSection() {
        return $this->menuConfig['masters'] ?? null;
    }
    
    public function getUsersSection() {
        return $this->menuConfig['users_section'] ?? null;
    }
    
    public function getLocationMasterSubmenu() {
        $mastersSection = $this->menuConfig['masters'] ?? null;
        if (!$mastersSection || empty($mastersSection['items'])) return null;
        foreach ($mastersSection['items'] as $item) {
            if ($item['id'] === 'location_master') return $item;
        }
        return null;
    }
    
    public function getAdvOnlyModules() {
        return $this->advOnlyModules;
    }
    
    public function isAdvOnlyModule($moduleName) {
        return in_array($moduleName, $this->advOnlyModules);
    }
    
    public function getAllMenuItems() {
        $allItems = [];
        foreach ($this->menuConfig as $section => $data) {
            if (isset($data['collapsible']) && $data['collapsible']) {
                foreach ($data['items'] as $item) {
                    if (isset($item['collapsible']) && $item['collapsible']) {
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
                foreach ($data as $item) {
                    $item['section'] = $section;
                    $allItems[] = $item;
                }
            }
        }
        return $allItems;
    }
    
    public function hasPermission($userId, $permission) {
        if ($permission === null) return true;
        return $this->permissionEngine->can($userId, $permission);
    }
}
