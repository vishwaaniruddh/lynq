<?php
/**
 * ADV User Documentation Page
 * Comprehensive in-app documentation for ADV CRM users
 * 
 * Requirements: 1.3, 2.1 - ADV-only access and page structure
 * **Feature: adv-user-documentation, Property 1: ADV-Only Icon Visibility**
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// ADV-only access control - redirect non-ADV users
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'ADV Documentation is only available to ADV users';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'ADV User Documentation';
$currentPage = 'documentation';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'ADV Documentation']
];

ob_start();
?>

<style>
/* Documentation Page Styles */
.doc-container {
    max-width: 1200px;
    margin: 0 auto;
}

.doc-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.doc-search {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.doc-search input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.doc-search input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.doc-search i {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.doc-toc {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e5e7eb;
}

.doc-toc h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 1rem;
}

.doc-toc-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.5rem;
}

.doc-toc-link {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    color: #6b7280;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
}

.doc-toc-link:hover {
    background: #f3f4f6;
    color: #6366f1;
}

.doc-toc-link i {
    width: 1.25rem;
    margin-right: 0.5rem;
    font-size: 0.75rem;
}

.doc-section {
    background: white;
    border-radius: 0.75rem;
    margin-bottom: 1rem;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    cursor: pointer;
    transition: background 0.2s;
}

.section-header:hover {
    background: #f9fafb;
}

.section-header h2 {
    display: flex;
    align-items: center;
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}

.section-header h2 i {
    width: 1.5rem;
    margin-right: 0.75rem;
    color: #6366f1;
}

.toggle-icon {
    color: #9ca3af;
    transition: transform 0.2s;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}

.section-content {
    padding: 0 1.5rem 1.5rem;
    display: none;
}

.section-content.expanded {
    display: block;
}

.feature-item {
    padding: 1rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
}

.feature-item:last-child {
    margin-bottom: 0;
}

.feature-item h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.role-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
    margin-bottom: 0.5rem;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 500;
    border-radius: 0.375rem;
}

.badge-superadmin {
    background: #fef3c7;
    color: #92400e;
}

.badge-admin {
    background: #dbeafe;
    color: #1e40af;
}

.badge-manager {
    background: #d1fae5;
    color: #065f46;
}

.badge-engineer {
    background: #e0e7ff;
    color: #3730a3;
}

.permission-ref {
    font-size: 0.75rem;
    color: #6b7280;
    font-family: monospace;
    background: #f3f4f6;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    display: inline-block;
    margin-bottom: 0.5rem;
}

.description {
    font-size: 0.875rem;
    color: #4b5563;
    line-height: 1.5;
}

.role-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.role-table th,
.role-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.role-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.role-table td {
    color: #4b5563;
}

.highlight {
    background: #fef08a;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
}

/* Print Styles */
@media print {
    .doc-header,
    .doc-toc,
    .no-print {
        display: none !important;
    }
    
    .section-content {
        display: block !important;
    }
    
    .doc-section {
        break-inside: avoid;
        border: 1px solid #ccc;
        margin-bottom: 1rem;
    }
    
    body {
        background: white;
    }
}
</style>

<div class="doc-container">
    <!-- Page Header with Search and Print -->
    <div class="doc-header">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ADV User Documentation</h1>
            <p class="text-gray-500 mt-1">Comprehensive guide for ADV CRM features and functionality</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="doc-search">
                <i class="fas fa-search"></i>
                <input type="text" id="docSearch" placeholder="Search documentation..." onkeyup="searchDocumentation(this.value)">
            </div>
            <button onclick="printDocumentation()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition no-print">
                <i class="fas fa-print mr-2"></i>Print
            </button>
        </div>
    </div>

    <!-- Table of Contents -->
    <div class="doc-toc no-print">
        <h3><i class="fas fa-list mr-2"></i>Table of Contents</h3>
        <div class="doc-toc-list">
            <a href="#role-overview" class="doc-toc-link"><i class="fas fa-users-cog"></i>Role Overview</a>
            <a href="#dashboard" class="doc-toc-link"><i class="fas fa-home"></i>Dashboard</a>
            <a href="#masters" class="doc-toc-link"><i class="fas fa-database"></i>Masters</a>
            <a href="#users" class="doc-toc-link"><i class="fas fa-users"></i>Users</a>
            <a href="#site-management" class="doc-toc-link"><i class="fas fa-map-marker-alt"></i>Site Management</a>
            <a href="#delegation-tracking" class="doc-toc-link"><i class="fas fa-tasks"></i>Delegation Tracking</a>
            <a href="#feasibility-tracking" class="doc-toc-link"><i class="fas fa-clipboard-list"></i>Feasibility Tracking</a>
            <a href="#installation-tracking" class="doc-toc-link"><i class="fas fa-tools"></i>Installation Tracking</a>
            <a href="#ip-configuration" class="doc-toc-link"><i class="fas fa-network-wired"></i>IP Configuration</a>
            <a href="#inventory" class="doc-toc-link"><i class="fas fa-boxes"></i>Inventory</a>
            <a href="#system-admin" class="doc-toc-link"><i class="fas fa-server"></i>System Administration</a>
        </div>
    </div>

    <!-- Role Overview Section -->
    <section id="role-overview" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-users-cog"></i>Role Overview</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">ADV CRM supports four role levels for ADV company users, each with different access levels and capabilities. Understanding these roles helps you know what features are available to you and your team members.</p>
            
            <!-- Role Definitions -->
            <div class="feature-item mb-4">
                <h3>ADV Role Hierarchy</h3>
                <p class="description mb-3">Roles are organized in a hierarchy where higher-level roles inherit all permissions from lower levels, plus additional capabilities.</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Level</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-superadmin">Superadmin</span></td>
                            <td>100</td>
                            <td>Complete system access including system administration, backup/restore, health monitoring, and all configuration options. Can manage all users and delegate permissions to contractors.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-admin">Admin</span></td>
                            <td>80</td>
                            <td>Full administrative access to most modules including user management, site management, inventory operations, and all tracking features. Cannot access system-level settings.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-manager">Manager</span></td>
                            <td>60</td>
                            <td>Management access to operational modules including site delegation, inventory dispatches, tracking dashboards, and reporting. Limited user management capabilities.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-engineer">Engineer</span></td>
                            <td>40</td>
                            <td>Access to field operation features including feasibility checks, installation tasks, and assigned site information. Read-only access to most dashboards.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Capabilities Comparison Table -->
            <div class="feature-item">
                <h3>Capabilities by Role Level</h3>
                <p class="description mb-3">The following table shows which modules and features are accessible to each ADV role level.</p>
                <div style="overflow-x: auto;">
                    <table class="role-table">
                        <thead>
                            <tr>
                                <th>Module / Feature</th>
                                <th class="text-center"><span class="badge badge-superadmin">Superadmin</span></th>
                                <th class="text-center"><span class="badge badge-admin">Admin</span></th>
                                <th class="text-center"><span class="badge badge-manager">Manager</span></th>
                                <th class="text-center"><span class="badge badge-engineer">Engineer</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Dashboard</strong></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Masters</strong> - Company Management</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Masters</strong> - Location Data</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Users</strong> - User Management</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Users</strong> - Role & Permission Management</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Site Management</strong> - Create/Edit Sites</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Site Management</strong> - Bulk Upload</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Site Management</strong> - Delegation</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Delegation Tracking</strong></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Feasibility Tracking</strong></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Installation Tracking</strong></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                            </tr>
                            <tr>
                                <td><strong>IP Configuration</strong></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Inventory</strong> - Warehouse Management</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Inventory</strong> - Stock & Dispatch</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                            </tr>
                            <tr>
                                <td><strong>Inventory</strong> - Asset Management</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-eye text-blue-600" title="View Only"></i></td>
                            </tr>
                            <tr>
                                <td><strong>System Admin</strong> - Permission Delegation</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>System Admin</strong> - Audit Trail</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>System Admin</strong> - Backup & Restore</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                            <tr>
                                <td><strong>System Admin</strong> - Health Monitor</td>
                                <td class="text-center"><i class="fas fa-check text-green-600"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                                <td class="text-center"><i class="fas fa-times text-gray-300"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="description mt-3" style="font-size: 0.75rem;">
                    <i class="fas fa-check text-green-600"></i> Full Access &nbsp;&nbsp;
                    <i class="fas fa-eye text-blue-600"></i> View Only &nbsp;&nbsp;
                    <i class="fas fa-times text-gray-300"></i> No Access
                </p>
            </div>
        </div>
    </section>

    <!-- Dashboard Section -->
    <section id="dashboard" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-home"></i>Dashboard</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">The Dashboard is your central hub for monitoring all key metrics and activities across the ADV CRM system. It provides real-time statistics, visual analytics, and quick access to important information.</p>
            
            <!-- Statistics Cards -->
            <div class="feature-item">
                <h3>Statistics Cards</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="description mb-3">The top row displays eight primary statistics cards, each clickable to navigate to the respective module:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Card</th>
                            <th>Description</th>
                            <th>Additional Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-map-marker-alt text-indigo-500 mr-2"></i>Sites</strong></td>
                            <td>Total number of sites in the system</td>
                            <td>Shows count of active sites as a badge</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-share-alt text-cyan-500 mr-2"></i>Delegations</strong></td>
                            <td>Total site delegations to contractors</td>
                            <td>Shows pending delegations count as a badge</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-users text-green-500 mr-2"></i>Users</strong></td>
                            <td>Total active users in the system</td>
                            <td>Click to view user details modal</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-building text-pink-500 mr-2"></i>Companies</strong></td>
                            <td>Total active companies (ADV and Contractors)</td>
                            <td>Click to view company details modal</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-box text-orange-500 mr-2"></i>Products</strong></td>
                            <td>Total active products in inventory</td>
                            <td>Links to Products management page</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-microchip text-violet-500 mr-2"></i>Assets</strong></td>
                            <td>Total assets tracked in the system</td>
                            <td>Shows working assets count as a badge</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-warehouse text-teal-500 mr-2"></i>Warehouses</strong></td>
                            <td>Total warehouses configured</td>
                            <td>Links to Warehouse management page</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-network-wired text-blue-500 mr-2"></i>IP Configs</strong></td>
                            <td>Total IP configurations in the system</td>
                            <td>Shows available IPs count as a badge</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Status Mini-Cards -->
            <div class="feature-item">
                <h3>Status Mini-Cards</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="description mb-3">Four detailed status cards provide quick insights into key operational areas:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #6366f1;">
                        <strong style="color: #374151;">Delegation Status</strong>
                        <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">
                            <span style="color: #ca8a04;">● Pending</span> - Awaiting contractor response<br>
                            <span style="color: #16a34a;">● Accepted</span> - Contractor accepted delegation<br>
                            <span style="color: #dc2626;">● Rejected</span> - Contractor declined delegation
                        </p>
                    </div>
                    <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #f59e0b;">
                        <strong style="color: #374151;">Dispatch Status</strong>
                        <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">
                            <span style="color: #ca8a04;">● Pending</span> - Dispatch created, not shipped<br>
                            <span style="color: #2563eb;">● In Transit</span> - Items being transported<br>
                            <span style="color: #16a34a;">● Delivered</span> - Successfully received<br>
                            <span style="color: #dc2626;">● Cancelled</span> - Dispatch cancelled
                        </p>
                    </div>
                    <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #10b981;">
                        <strong style="color: #374151;">IP Configuration</strong>
                        <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">
                            <span style="color: #16a34a;">● Available</span> - IPs ready for assignment<br>
                            <span style="color: #ca8a04;">● Locked</span> - IPs temporarily reserved<br>
                            <span style="color: #2563eb;">● Configured</span> - IPs assigned to routers
                        </p>
                    </div>
                    <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #8b5cf6;">
                        <strong style="color: #374151;">Stock Overview</strong>
                        <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">
                            <span style="color: #4f46e5;">● Total Qty</span> - Sum of all stock quantities<br>
                            <span style="color: #ea580c;">● Low Stock</span> - Items below threshold (10)<br>
                            <span style="color: #7c3aed;">● In Stock</span> - Assets currently in stock
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="feature-item">
                <h3>Analytics Charts</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="description mb-3">Four visual charts provide at-a-glance analytics for key metrics:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Chart</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>By Contractor</strong></td>
                            <td>Doughnut Chart</td>
                            <td>Shows distribution of site delegations across contractors. Displays top 5 contractors by delegation count, helping identify workload distribution.</td>
                        </tr>
                        <tr>
                            <td><strong>Asset Status</strong></td>
                            <td>Doughnut Chart</td>
                            <td>Visualizes asset distribution by status: In Stock, Dispatched, Assigned, Under Repair, and Scrapped. Useful for inventory health monitoring.</td>
                        </tr>
                        <tr>
                            <td><strong>Asset Condition</strong></td>
                            <td>Doughnut Chart</td>
                            <td>Shows the ratio of Working vs Not Working assets. Critical for maintenance planning and asset lifecycle management.</td>
                        </tr>
                        <tr>
                            <td><strong>Dispatch Overview</strong></td>
                            <td>Doughnut Chart</td>
                            <td>Displays dispatch status breakdown: Pending, In Transit, Delivered, and Cancelled. Helps track logistics efficiency.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Additional Stats Row -->
            <div class="feature-item">
                <h3>Secondary Statistics</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="description mb-3">A row of colored stat cards provides quick counts for operational metrics:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>Dispatched</strong> - Total assets currently dispatched</li>
                    <li><strong>Under Repair</strong> - Assets currently being repaired</li>
                    <li><strong>Not Working</strong> - Assets marked as non-functional</li>
                    <li><strong>Transfers</strong> - Total warehouse-to-warehouse transfers</li>
                    <li><strong>Repairs</strong> - Total repair records in the system</li>
                    <li><strong>Scrapped</strong> - Assets that have been decommissioned</li>
                </ul>
            </div>
            
            <!-- Recent Activity -->
            <div class="feature-item">
                <h3>Recent Activity</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Full audit: system.audit</p>
                <p class="description mb-3">The Recent Activity panel shows the latest system activities with the following information:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>Action Type</strong> - Create, Update, Delete, Login, etc. (with corresponding icon)</li>
                    <li><strong>Performed By</strong> - Username of the user who performed the action</li>
                    <li><strong>Timestamp</strong> - Date and time of the activity</li>
                </ul>
                <p class="description mt-2">Activities are filtered by company for non-ADV users. Users with <code>system.audit</code> permission can click "View All" to access the complete audit trail.</p>
            </div>
            
            <!-- Quick Actions -->
            <div class="feature-item">
                <h3>Quick Actions & Profile</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                    <span class="badge badge-engineer">Engineer</span>
                </div>
                <p class="description">The dashboard also includes quick action buttons for common tasks and a profile summary card showing your current user information, role, and company details.</p>
            </div>
        </div>
    </section>

    <!-- Masters Section -->
    <section id="masters" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-database"></i>Masters</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">The Masters module provides management of core reference data used throughout the ADV CRM system. This includes company management, banking information, customer data, courier services, and the complete location hierarchy. Proper master data management ensures data consistency across all modules.</p>
            
            <!-- Company Management -->
            <div class="feature-item">
                <h3>Company Management</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: companies.create, companies.edit, companies.view</p>
                <p class="description mb-3">Manage ADV and Contractor companies in the system. Companies are the foundation of the multi-tenant architecture:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Permission Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-building text-green-500 mr-2"></i>Create Company</strong></td>
                            <td>Add new ADV or Contractor companies with name, type, contact details, and address information</td>
                            <td><code>companies.create</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-edit text-blue-500 mr-2"></i>Edit Company</strong></td>
                            <td>Modify company details including name, contact information, address, and status</td>
                            <td><code>companies.edit</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-eye text-indigo-500 mr-2"></i>View Company</strong></td>
                            <td>View company details, associated users, and delegated sites</td>
                            <td><code>companies.view</code></td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Company Types:</strong> ADV companies have full system access, while Contractor companies can only access sites delegated to them and their own company data.
                    </p>
                </div>
            </div>
            
            <!-- Bank Master Data -->
            <div class="feature-item">
                <h3>Bank Master Data</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: masters.banks.create, masters.banks.edit, masters.banks.view</p>
                <p class="description mb-3">Manage bank information used for financial references and payment processing:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Description</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Bank Name</strong></td>
                            <td>Official name of the banking institution</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Bank Code</strong></td>
                            <td>Unique identifier code for the bank (e.g., IFSC prefix)</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>Active or Inactive status for the bank record</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
                <p class="description mt-3">Bank records are used as reference data when capturing banking details for companies, customers, or payment configurations.</p>
            </div>
            
            <!-- Customer Master Data -->
            <div class="feature-item">
                <h3>Customer Master Data</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: masters.customers.create, masters.customers.edit, masters.customers.view</p>
                <p class="description mb-3">Manage customer information for sites and service delivery:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Description</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Customer Name</strong></td>
                            <td>Name of the customer organization or individual</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Customer Code</strong></td>
                            <td>Unique identifier code for the customer</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Contact Details</strong></td>
                            <td>Phone number, email, and contact person information</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Location</strong></td>
                            <td>Country, State, City assignment from Location Master</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Address</strong></td>
                            <td>Full address details for the customer</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>Active or Inactive status</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
                <p class="description mt-3">Customers are linked to sites and used for service delivery tracking and reporting.</p>
            </div>
            
            <!-- Courier Master Data -->
            <div class="feature-item">
                <h3>Courier Master Data</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: masters.couriers.create, masters.couriers.edit, masters.couriers.view</p>
                <p class="description mb-3">Manage courier and shipping service providers used for inventory dispatches:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Description</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Courier Name</strong></td>
                            <td>Name of the courier or shipping service provider</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Courier Code</strong></td>
                            <td>Short code identifier for the courier</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Contact Number</strong></td>
                            <td>Primary contact phone number for the courier</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Tracking URL</strong></td>
                            <td>Base URL for shipment tracking (if available)</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>Active or Inactive status</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                    <p style="font-size: 0.8125rem; color: #166534;">
                        <i class="fas fa-truck mr-1"></i>
                        <strong>Usage:</strong> Couriers are selected when creating dispatches. The tracking URL can be used to generate shipment tracking links automatically.
                    </p>
                </div>
            </div>
            
            <!-- Location Master Hierarchy -->
            <div class="feature-item">
                <h3>Location Master Hierarchy</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: masters.locations.create, masters.locations.edit, masters.locations.view</p>
                <p class="description mb-3">The Location Master provides a hierarchical structure for geographic data used throughout the system:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Location Hierarchy:</strong></p>
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                        <span style="background: #dbeafe; color: #1e40af; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-globe mr-1"></i>Country</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-map mr-1"></i>State</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-layer-group mr-1"></i>Zone</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #fce7f3; color: #9d174d; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-city mr-1"></i>City</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #e0e7ff; color: #3730a3; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-building mr-1"></i>LHO</span>
                    </div>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Description</th>
                            <th>Parent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-globe text-blue-500 mr-2"></i>Countries</strong></td>
                            <td>Top-level geographic entity. Contains country name and code (e.g., IN for India)</td>
                            <td>None (Root)</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-map text-green-500 mr-2"></i>States</strong></td>
                            <td>State or province within a country. Contains state name and code</td>
                            <td>Country</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-layer-group text-yellow-500 mr-2"></i>Zones</strong></td>
                            <td>Regional grouping within a state for operational management</td>
                            <td>State</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-city text-pink-500 mr-2"></i>Cities</strong></td>
                            <td>City or town within a zone. Contains city name and optional PIN code</td>
                            <td>Zone</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-building text-indigo-500 mr-2"></i>LHO (Local Head Office)</strong></td>
                            <td>Local operational office or hub within a city. Used for site assignment and manager allocation</td>
                            <td>City</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                    <p style="font-size: 0.8125rem; color: #92400e;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Cascade Delete Warning:</strong> Deleting a location will affect all child locations. For example, deleting a State will remove all associated Zones, Cities, and LHOs.
                    </p>
                </div>
            </div>
            
            <!-- LHO Manager Assignment -->
            <div class="feature-item">
                <h3>LHO Manager Assignment</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: masters.lhos.edit</p>
                <p class="description mb-3">Assign managers to Local Head Offices (LHOs) for operational oversight:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>Manager Selection</strong> - Choose from available ADV users to assign as LHO manager</li>
                    <li><strong>Multiple Managers</strong> - An LHO can have multiple managers assigned</li>
                    <li><strong>Manager Responsibilities</strong> - LHO managers oversee sites within their assigned LHO area</li>
                    <li><strong>Reporting</strong> - Filter sites and reports by LHO manager assignment</li>
                </ul>
                <p class="description mt-3">LHO managers receive notifications for sites within their area and can be filtered in various reports.</p>
            </div>
            
            <!-- Master Data Export -->
            <div class="feature-item">
                <h3>Master Data Export</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: masters.export</p>
                <p class="description mb-3">Export master data for reporting and backup purposes:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>CSV Export</strong> - Download master data in CSV format for spreadsheet analysis</li>
                    <li><strong>Excel Export</strong> - Download formatted Excel files with proper column headers</li>
                    <li><strong>Filtered Export</strong> - Export only the currently filtered/visible records</li>
                </ul>
            </div>
            
            <!-- Audit Trail for Masters -->
            <div class="feature-item">
                <h3>Master Data Audit Trail</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: system.audit</p>
                <p class="description">All master data changes are logged in the audit trail, including:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; margin-top: 0.5rem; list-style-type: disc;">
                    <li>Company creation and modifications</li>
                    <li>Bank, Customer, and Courier record changes</li>
                    <li>Location hierarchy additions and deletions</li>
                    <li>LHO manager assignments and removals</li>
                    <li>Status changes for any master record</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Users Section -->
    <section id="users" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-users"></i>Users</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">The Users module provides comprehensive user management capabilities including creating and managing user accounts, assigning roles, and configuring permissions. This module is essential for controlling access to the ADV CRM system.</p>
            
            <!-- User Creation and Management -->
            <div class="feature-item">
                <h3>User Creation & Management</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: users.create, users.edit, users.delete</p>
                <p class="description mb-3">Create and manage user accounts with the following capabilities:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Permission Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-user-plus text-green-500 mr-2"></i>Create User</strong></td>
                            <td>Add new users to the system with username, email, password, and company assignment</td>
                            <td><code>users.create</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-user-edit text-blue-500 mr-2"></i>Edit User</strong></td>
                            <td>Modify user details including name, email, contact information, and company assignment</td>
                            <td><code>users.edit</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-user-times text-red-500 mr-2"></i>Delete User</strong></td>
                            <td>Remove user accounts from the system (soft delete - user data is preserved)</td>
                            <td><code>users.delete</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-eye text-indigo-500 mr-2"></i>View Users</strong></td>
                            <td>View user list with filtering by company, role, and status</td>
                            <td><code>users.view</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- User Status Management -->
            <div class="feature-item">
                <h3>User Status Management</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: users.edit</p>
                <p class="description mb-3">Control user access through status management:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #16a34a;">
                        <strong style="color: #374151;"><i class="fas fa-check-circle text-green-500 mr-1"></i>Active</strong>
                        <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">User can log in and access the system based on their assigned permissions</p>
                    </div>
                    <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #dc2626;">
                        <strong style="color: #374151;"><i class="fas fa-ban text-red-500 mr-1"></i>Inactive</strong>
                        <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">User account is disabled and cannot log in. Use this to temporarily suspend access.</p>
                    </div>
                </div>
                <p class="description mt-3">Changing a user's status to Inactive immediately prevents them from accessing the system. Their session will be terminated on their next request.</p>
            </div>
            
            <!-- Role Management -->
            <div class="feature-item">
                <h3>Role Management</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: roles.view, roles.edit</p>
                <p class="description mb-3">Roles define what users can do in the system. The role hierarchy ensures that higher-level roles have access to all features available to lower-level roles.</p>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Role Level</th>
                            <th>Role Name</th>
                            <th>Description</th>
                            <th>Company Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>100</strong></td>
                            <td><span class="badge badge-superadmin">ADV Superadmin</span></td>
                            <td>Complete system access including system administration</td>
                            <td>ADV Only</td>
                        </tr>
                        <tr>
                            <td><strong>80</strong></td>
                            <td><span class="badge badge-admin">ADV Admin</span></td>
                            <td>Full administrative access to most modules</td>
                            <td>ADV Only</td>
                        </tr>
                        <tr>
                            <td><strong>60</strong></td>
                            <td><span class="badge badge-manager">ADV Manager</span></td>
                            <td>Management access to operational modules</td>
                            <td>ADV Only</td>
                        </tr>
                        <tr>
                            <td><strong>40</strong></td>
                            <td><span class="badge badge-engineer">ADV Engineer</span></td>
                            <td>Access to field operation features</td>
                            <td>ADV Only</td>
                        </tr>
                        <tr>
                            <td><strong>70</strong></td>
                            <td><span class="badge" style="background: #fce7f3; color: #9d174d;">Contractor Admin</span></td>
                            <td>Full access within contractor's delegated scope</td>
                            <td>Contractor Only</td>
                        </tr>
                        <tr>
                            <td><strong>50</strong></td>
                            <td><span class="badge" style="background: #fce7f3; color: #9d174d;">Contractor Manager</span></td>
                            <td>Management access within contractor's scope</td>
                            <td>Contractor Only</td>
                        </tr>
                        <tr>
                            <td><strong>30</strong></td>
                            <td><span class="badge" style="background: #fce7f3; color: #9d174d;">Contractor Engineer</span></td>
                            <td>Field operations for assigned sites</td>
                            <td>Contractor Only</td>
                        </tr>
                    </tbody>
                </table>
                <p class="description mt-3"><strong>Note:</strong> Role levels determine the hierarchy. A user can only assign roles with a level lower than their own role level.</p>
            </div>
            
            <!-- Permission Management -->
            <div class="feature-item">
                <h3>Permission Management</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: permissions.view, permissions.delegate</p>
                <p class="description mb-3">Permissions control granular access to specific features. They follow the <code>module.action</code> format:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.5rem;"><strong>Permission Format:</strong> <code style="background: #e5e7eb; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">module.action</code></p>
                    <p style="font-size: 0.8125rem; color: #6b7280;">
                        <strong>Module</strong> - The functional area (e.g., users, sites, inventory)<br>
                        <strong>Action</strong> - The operation type (e.g., view, create, edit, delete)
                    </p>
                </div>
                
                <p class="description mb-2"><strong>Common Permission Actions:</strong></p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>view</code></td>
                            <td>Read-only access to view data</td>
                            <td><code>users.view</code>, <code>sites.view</code></td>
                        </tr>
                        <tr>
                            <td><code>create</code></td>
                            <td>Ability to create new records</td>
                            <td><code>users.create</code>, <code>inventory.create</code></td>
                        </tr>
                        <tr>
                            <td><code>edit</code></td>
                            <td>Ability to modify existing records</td>
                            <td><code>users.edit</code>, <code>sites.edit</code></td>
                        </tr>
                        <tr>
                            <td><code>delete</code></td>
                            <td>Ability to remove records</td>
                            <td><code>users.delete</code>, <code>products.delete</code></td>
                        </tr>
                        <tr>
                            <td><code>manage</code></td>
                            <td>Full control including special operations</td>
                            <td><code>system.manage</code>, <code>roles.manage</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Role Assignment with Company Type Restrictions -->
            <div class="feature-item">
                <h3>Role Assignment & Company Type Restrictions</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: users.edit, roles.view</p>
                <p class="description mb-3">When assigning roles to users, the system enforces company type restrictions to maintain proper access control:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #eff6ff; padding: 1rem; border-radius: 0.5rem; border: 1px solid #bfdbfe;">
                        <strong style="color: #1e40af;"><i class="fas fa-building text-blue-500 mr-2"></i>ADV Company Users</strong>
                        <p style="font-size: 0.8125rem; color: #3b82f6; margin-top: 0.5rem;">Can only be assigned ADV roles:</p>
                        <ul style="font-size: 0.8125rem; color: #1e40af; margin-left: 1rem; margin-top: 0.25rem; list-style-type: disc;">
                            <li>ADV Superadmin</li>
                            <li>ADV Admin</li>
                            <li>ADV Manager</li>
                            <li>ADV Engineer</li>
                        </ul>
                    </div>
                    <div style="background: #fdf2f8; padding: 1rem; border-radius: 0.5rem; border: 1px solid #fbcfe8;">
                        <strong style="color: #9d174d;"><i class="fas fa-handshake text-pink-500 mr-2"></i>Contractor Company Users</strong>
                        <p style="font-size: 0.8125rem; color: #db2777; margin-top: 0.5rem;">Can only be assigned Contractor roles:</p>
                        <ul style="font-size: 0.8125rem; color: #9d174d; margin-left: 1rem; margin-top: 0.25rem; list-style-type: disc;">
                            <li>Contractor Admin</li>
                            <li>Contractor Manager</li>
                            <li>Contractor Engineer</li>
                        </ul>
                    </div>
                </div>
                
                <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                    <p style="font-size: 0.8125rem; color: #92400e;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Important:</strong> The role dropdown will only show roles compatible with the user's company type. Attempting to assign an incompatible role will result in an error.
                    </p>
                </div>
            </div>
            
            <!-- User List and Filtering -->
            <div class="feature-item">
                <h3>User List & Filtering</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: users.view</p>
                <p class="description mb-3">The user list provides a comprehensive view of all users with powerful filtering options:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>Search</strong> - Filter by username, email, or name</li>
                    <li><strong>Company Filter</strong> - Show users from specific companies</li>
                    <li><strong>Role Filter</strong> - Filter by assigned role</li>
                    <li><strong>Status Filter</strong> - Show active or inactive users</li>
                    <li><strong>Export</strong> - Download user list as CSV or Excel</li>
                </ul>
                <p class="description mt-3"><strong>Note:</strong> Managers have view-only access to the user list and cannot create, edit, or delete users.</p>
            </div>
            
            <!-- Audit Trail for User Actions -->
            <div class="feature-item">
                <h3>User Activity Audit</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: system.audit</p>
                <p class="description">All user management actions are logged in the audit trail, including:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; margin-top: 0.5rem; list-style-type: disc;">
                    <li>User creation with initial role assignment</li>
                    <li>User profile modifications</li>
                    <li>Role changes</li>
                    <li>Status changes (active/inactive)</li>
                    <li>User deletion</li>
                    <li>Password resets</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Site Management Section -->
    <section id="site-management" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-map-marker-alt"></i>Site Management</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">The Site Management module is the foundation of the ADV CRM system, enabling you to create, manage, and delegate sites to contractors. Sites represent physical locations where services are delivered, and the delegation workflow ensures proper assignment of responsibilities from ADV to contractors and their engineers.</p>
            
            <!-- Site Creation and Editing -->
            <div class="feature-item">
                <h3>Site Creation & Editing</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: sites.create, sites.edit</p>
                <p class="description mb-3">Create and manage site records with comprehensive location and customer information:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Description</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Site ID</strong></td>
                            <td>Unique identifier for the site (auto-generated or manual entry)</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Site Name</strong></td>
                            <td>Descriptive name for the site location</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Customer</strong></td>
                            <td>Associated customer from Customer Master</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Location</strong></td>
                            <td>Country, State, Zone, City, LHO from Location Master hierarchy</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Address</strong></td>
                            <td>Full street address of the site</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Contact Person</strong></td>
                            <td>Primary contact name at the site</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Contact Number</strong></td>
                            <td>Phone number for site contact</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Latitude/Longitude</strong></td>
                            <td>GPS coordinates for mapping and location services</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>Active or Inactive status</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Tip:</strong> Use the Location Master hierarchy to ensure consistent geographic data. The cascading dropdowns will filter options based on your selections.
                    </p>
                </div>
            </div>
            
            <!-- Bulk Upload Functionality -->
            <div class="feature-item">
                <h3>Bulk Upload</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: sites.bulk_upload</p>
                <p class="description mb-3">Import multiple sites at once using CSV or Excel files for efficient data entry:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Bulk Upload Process:</strong></p>
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                        <span style="background: #dbeafe; color: #1e40af; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-download mr-1"></i>1. Download Template</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-file-excel mr-1"></i>2. Fill Data</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-upload mr-1"></i>3. Upload File</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #e0e7ff; color: #3730a3; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-check-circle mr-1"></i>4. Review Results</span>
                    </div>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-file-download text-blue-500 mr-2"></i>Template Download</strong></td>
                            <td>Download a pre-formatted CSV/Excel template with all required columns and sample data</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-check-double text-green-500 mr-2"></i>Validation</strong></td>
                            <td>System validates all rows before import, checking for required fields, duplicate Site IDs, and valid location references</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>Error Reporting</strong></td>
                            <td>Detailed error report showing which rows failed validation and why, allowing you to fix issues before re-uploading</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-history text-indigo-500 mr-2"></i>Upload History</strong></td>
                            <td>View history of all bulk uploads with success/failure counts and downloadable error logs</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                    <p style="font-size: 0.8125rem; color: #92400e;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Important:</strong> Ensure location data (Country, State, Zone, City, LHO) matches exactly with existing Location Master records. Use the exact names or codes as they appear in the system.
                    </p>
                </div>
            </div>
            
            <!-- Site Delegation Workflow -->
            <div class="feature-item">
                <h3>Site Delegation Workflow</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: sites.delegate</p>
                <p class="description mb-3">Delegate sites from ADV to contractor companies for service delivery. The delegation workflow ensures proper handoff and tracking:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Delegation Flow:</strong></p>
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                        <span style="background: #dbeafe; color: #1e40af; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-building mr-1"></i>ADV User</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-share-alt mr-1"></i>Delegates Site</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #fce7f3; color: #9d174d; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-handshake mr-1"></i>Contractor</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-user-hard-hat mr-1"></i>Engineer Assignment</span>
                    </div>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Step</th>
                            <th>Action</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>1</strong></td>
                            <td><i class="fas fa-hand-pointer text-blue-500 mr-2"></i>Select Site</td>
                            <td>Choose an undelegated site from the site list</td>
                        </tr>
                        <tr>
                            <td><strong>2</strong></td>
                            <td><i class="fas fa-building text-indigo-500 mr-2"></i>Choose Contractor</td>
                            <td>Select the contractor company to delegate the site to</td>
                        </tr>
                        <tr>
                            <td><strong>3</strong></td>
                            <td><i class="fas fa-comment text-green-500 mr-2"></i>Add Notes (Optional)</td>
                            <td>Include any special instructions or notes for the contractor</td>
                        </tr>
                        <tr>
                            <td><strong>4</strong></td>
                            <td><i class="fas fa-paper-plane text-cyan-500 mr-2"></i>Submit Delegation</td>
                            <td>Delegation is created with "Pending" status</td>
                        </tr>
                        <tr>
                            <td><strong>5</strong></td>
                            <td><i class="fas fa-check-circle text-green-500 mr-2"></i>Contractor Response</td>
                            <td>Contractor accepts or rejects the delegation</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #f59e0b;">
                        <strong style="color: #92400e;"><i class="fas fa-clock mr-1"></i>Pending</strong>
                        <p style="font-size: 0.8125rem; color: #78350f; margin-top: 0.25rem;">Awaiting contractor response. Site is reserved but not yet active for the contractor.</p>
                    </div>
                    <div style="background: #dcfce7; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #22c55e;">
                        <strong style="color: #166534;"><i class="fas fa-check-circle mr-1"></i>Accepted</strong>
                        <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.25rem;">Contractor accepted. Site is now visible to the contractor and can be assigned to engineers.</p>
                    </div>
                    <div style="background: #fee2e2; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #ef4444;">
                        <strong style="color: #991b1b;"><i class="fas fa-times-circle mr-1"></i>Rejected</strong>
                        <p style="font-size: 0.8125rem; color: #7f1d1d; margin-top: 0.25rem;">Contractor declined. Site returns to undelegated status and can be delegated to another contractor.</p>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Delegation -->
            <div class="feature-item">
                <h3>Bulk Delegation</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: sites.delegate</p>
                <p class="description mb-3">Delegate multiple sites to a contractor in a single operation for efficient workflow management:</p>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-check-square text-blue-500 mr-2"></i>Multi-Select</strong></td>
                            <td>Select multiple undelegated sites using checkboxes in the site list</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-filter text-indigo-500 mr-2"></i>Filter & Select</strong></td>
                            <td>Filter sites by location (State, Zone, City, LHO) before selecting for bulk delegation</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-building text-green-500 mr-2"></i>Single Contractor</strong></td>
                            <td>All selected sites are delegated to the same contractor company</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-clipboard-list text-yellow-500 mr-2"></i>Batch Processing</strong></td>
                            <td>System processes all delegations as a batch with a summary report of successes and failures</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                    <p style="font-size: 0.8125rem; color: #166534;">
                        <i class="fas fa-lightbulb mr-1"></i>
                        <strong>Tip:</strong> Use location filters to select all sites in a specific area (e.g., all sites in a particular city or zone) for delegation to a regional contractor.
                    </p>
                </div>
            </div>
            
            <!-- Site Status Management -->
            <div class="feature-item">
                <h3>Site Status Management</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: sites.edit</p>
                <p class="description mb-3">Control site availability through status management:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border: 1px solid #bbf7d0;">
                        <strong style="color: #166534;"><i class="fas fa-check-circle text-green-500 mr-2"></i>Active Status</strong>
                        <p style="font-size: 0.8125rem; color: #15803d; margin-top: 0.5rem;">Site is operational and available for:</p>
                        <ul style="font-size: 0.8125rem; color: #166534; margin-left: 1rem; margin-top: 0.25rem; list-style-type: disc;">
                            <li>Delegation to contractors</li>
                            <li>Engineer assignments</li>
                            <li>Feasibility checks</li>
                            <li>Installation work</li>
                            <li>IP configuration</li>
                        </ul>
                    </div>
                    <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; border: 1px solid #fecaca;">
                        <strong style="color: #991b1b;"><i class="fas fa-ban text-red-500 mr-2"></i>Inactive Status</strong>
                        <p style="font-size: 0.8125rem; color: #b91c1c; margin-top: 0.5rem;">Site is disabled and:</p>
                        <ul style="font-size: 0.8125rem; color: #991b1b; margin-left: 1rem; margin-top: 0.25rem; list-style-type: disc;">
                            <li>Cannot be delegated</li>
                            <li>Hidden from contractor views</li>
                            <li>Existing delegations remain but are paused</li>
                            <li>No new work can be initiated</li>
                            <li>Historical data is preserved</li>
                        </ul>
                    </div>
                </div>
                
                <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                    <p style="font-size: 0.8125rem; color: #92400e;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Note:</strong> Deactivating a site does not delete any associated data. All delegations, feasibility records, and installation history are preserved. Reactivating the site restores full functionality.
                    </p>
                </div>
            </div>
            
            <!-- Site List and Filtering -->
            <div class="feature-item">
                <h3>Site List & Filtering</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                    <span class="badge badge-engineer">Engineer</span>
                </div>
                <p class="permission-ref">Permission: sites.view</p>
                <p class="description mb-3">The site list provides comprehensive filtering and search capabilities:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Filter</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-search text-gray-500 mr-2"></i>Search</strong></td>
                            <td>Search by Site ID, Site Name, or Customer Name</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-globe text-blue-500 mr-2"></i>Location Filters</strong></td>
                            <td>Filter by Country, State, Zone, City, or LHO (cascading dropdowns)</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-building text-indigo-500 mr-2"></i>Customer Filter</strong></td>
                            <td>Show sites for a specific customer</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-share-alt text-green-500 mr-2"></i>Delegation Status</strong></td>
                            <td>Filter by delegation status: Undelegated, Pending, Accepted, Rejected</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-toggle-on text-yellow-500 mr-2"></i>Site Status</strong></td>
                            <td>Filter by Active or Inactive status</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-handshake text-pink-500 mr-2"></i>Contractor Filter</strong></td>
                            <td>Show sites delegated to a specific contractor</td>
                        </tr>
                    </tbody>
                </table>
                <p class="description mt-3"><strong>Note:</strong> Engineers can only view sites that have been assigned to them by their contractor.</p>
            </div>
            
            <!-- Site Export -->
            <div class="feature-item">
                <h3>Site Data Export</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: sites.export</p>
                <p class="description mb-3">Export site data for reporting and analysis:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>CSV Export</strong> - Download site data in CSV format</li>
                    <li><strong>Excel Export</strong> - Download formatted Excel file with all site details</li>
                    <li><strong>Filtered Export</strong> - Export only the currently filtered/visible sites</li>
                    <li><strong>Include Delegation Info</strong> - Option to include delegation status and contractor details</li>
                </ul>
            </div>
            
            <!-- Audit Trail for Sites -->
            <div class="feature-item">
                <h3>Site Audit Trail</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: system.audit</p>
                <p class="description">All site management actions are logged in the audit trail, including:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; margin-top: 0.5rem; list-style-type: disc;">
                    <li>Site creation and modifications</li>
                    <li>Bulk upload operations with success/failure counts</li>
                    <li>Delegation actions (create, accept, reject)</li>
                    <li>Status changes (active/inactive)</li>
                    <li>Engineer assignments and reassignments</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Delegation Tracking Section -->
    <section id="delegation-tracking" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-tasks"></i>Delegation Tracking</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">The Delegation Tracking module provides comprehensive visibility into site delegations from ADV to contractors. This module allows you to monitor delegation statuses, track acceptance and rejection rates, and maintain a complete history of all delegation activities. Effective delegation tracking ensures accountability and helps identify bottlenecks in the site assignment workflow.</p>
            
            <!-- All Delegations View -->
            <div class="feature-item">
                <h3>All Delegations View</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: delegations.view</p>
                <p class="description mb-3">The All Delegations view provides a comprehensive list of all site delegations with powerful filtering and sorting capabilities:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Description</th>
                            <th>Sortable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Site ID</strong></td>
                            <td>Unique identifier of the delegated site</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Site Name</strong></td>
                            <td>Name of the site being delegated</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Contractor</strong></td>
                            <td>Name of the contractor company receiving the delegation</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>Current delegation status (Pending, Accepted, Rejected)</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Delegated By</strong></td>
                            <td>ADV user who created the delegation</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Delegated Date</strong></td>
                            <td>Date and time when the delegation was created</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Response Date</strong></td>
                            <td>Date when contractor accepted or rejected (if applicable)</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Filtering Options -->
            <div class="feature-item">
                <h3>Filtering Options</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: delegations.view</p>
                <p class="description mb-3">Filter delegations to quickly find specific records:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Filter</th>
                            <th>Description</th>
                            <th>Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-filter text-indigo-500 mr-2"></i>Status Filter</strong></td>
                            <td>Filter by delegation status</td>
                            <td>All, Pending, Accepted, Rejected</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-building text-blue-500 mr-2"></i>Contractor Filter</strong></td>
                            <td>Filter by contractor company</td>
                            <td>Dropdown of all contractors</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-calendar text-green-500 mr-2"></i>Date Range</strong></td>
                            <td>Filter by delegation date range</td>
                            <td>From Date, To Date</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-search text-gray-500 mr-2"></i>Search</strong></td>
                            <td>Search by site ID, site name, or contractor name</td>
                            <td>Free text search</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-lightbulb mr-1"></i>
                        <strong>Tip:</strong> Combine multiple filters to narrow down results. For example, filter by "Pending" status and a specific contractor to see all pending delegations for that contractor.
                    </p>
                </div>
            </div>
            
            <!-- Delegation Statuses -->
            <div class="feature-item">
                <h3>Delegation Statuses</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                    <span class="badge badge-engineer">Engineer</span>
                </div>
                <p class="permission-ref">Permission: delegations.view</p>
                <p class="description mb-3">Understanding delegation statuses is crucial for tracking the site assignment workflow:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #fef3c7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                        <strong style="color: #92400e;"><i class="fas fa-clock mr-2"></i>Pending</strong>
                        <p style="font-size: 0.8125rem; color: #78350f; margin-top: 0.5rem;">
                            The delegation has been created by an ADV user and is awaiting contractor response. The contractor can either accept or reject the delegation.
                        </p>
                        <p style="font-size: 0.75rem; color: #92400e; margin-top: 0.5rem;">
                            <strong>Next Actions:</strong> Contractor accepts or rejects
                        </p>
                    </div>
                    <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #22c55e;">
                        <strong style="color: #166534;"><i class="fas fa-check-circle mr-2"></i>Accepted</strong>
                        <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.5rem;">
                            The contractor has accepted the delegation. The site is now under the contractor's responsibility and they can assign engineers to work on it.
                        </p>
                        <p style="font-size: 0.75rem; color: #166534; margin-top: 0.5rem;">
                            <strong>Next Actions:</strong> Contractor assigns engineers
                        </p>
                    </div>
                    <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #ef4444;">
                        <strong style="color: #991b1b;"><i class="fas fa-times-circle mr-2"></i>Rejected</strong>
                        <p style="font-size: 0.8125rem; color: #7f1d1d; margin-top: 0.5rem;">
                            The contractor has declined the delegation. The site remains unassigned and can be delegated to a different contractor.
                        </p>
                        <p style="font-size: 0.75rem; color: #991b1b; margin-top: 0.5rem;">
                            <strong>Next Actions:</strong> ADV re-delegates to another contractor
                        </p>
                    </div>
                </div>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Delegation Status Flow:</strong></p>
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                        <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-clock mr-1"></i>Pending</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-check-circle mr-1"></i>Accepted</span>
                        <span style="color: #6b7280; margin: 0 0.5rem;">or</span>
                        <span style="background: #fee2e2; color: #991b1b; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-times-circle mr-1"></i>Rejected</span>
                    </div>
                </div>
            </div>
            
            <!-- Delegation History -->
            <div class="feature-item">
                <h3>Delegation History</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: delegations.view</p>
                <p class="description mb-3">The Delegation History feature provides a complete audit trail of all delegation activities for a site:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Information</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-history text-indigo-500 mr-2"></i>Action Timeline</strong></td>
                            <td>Chronological list of all delegation actions for a site, including creation, acceptance, rejection, and re-delegation</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-user text-blue-500 mr-2"></i>User Information</strong></td>
                            <td>Name of the user who performed each action (ADV user for delegation, contractor user for response)</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-clock text-green-500 mr-2"></i>Timestamps</strong></td>
                            <td>Exact date and time of each action in the delegation lifecycle</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-comment text-yellow-500 mr-2"></i>Remarks</strong></td>
                            <td>Any notes or comments added during delegation or response (e.g., rejection reason)</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-exchange-alt text-purple-500 mr-2"></i>Previous Contractors</strong></td>
                            <td>List of all contractors the site was previously delegated to, useful for tracking re-delegations</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                    <p style="font-size: 0.8125rem; color: #166534;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Access:</strong> Click on any delegation row in the All Delegations view to open the detailed history panel for that site.
                    </p>
                </div>
            </div>
            
            <!-- Delegation Export -->
            <div class="feature-item">
                <h3>Delegation Export</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: delegations.export</p>
                <p class="description mb-3">Export delegation data for reporting and analysis:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>CSV Export</strong> - Download delegation data in CSV format for spreadsheet analysis</li>
                    <li><strong>Excel Export</strong> - Download formatted Excel files with proper column headers</li>
                    <li><strong>Filtered Export</strong> - Export only the currently filtered/visible records</li>
                    <li><strong>Full Export</strong> - Export all delegation records with complete history</li>
                </ul>
                <p class="description mt-3">Exported data includes site details, contractor information, status, dates, and any associated remarks.</p>
            </div>
            
            <!-- Delegation Notifications -->
            <div class="feature-item">
                <h3>Delegation Notifications</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="description mb-3">Stay informed about delegation activities through system notifications:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Notification Recipient</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>New Delegation</strong></td>
                            <td>Contractor Admin</td>
                            <td>Notification when a new site is delegated to the contractor</td>
                        </tr>
                        <tr>
                            <td><strong>Delegation Accepted</strong></td>
                            <td>ADV User (delegator)</td>
                            <td>Notification when contractor accepts a delegation</td>
                        </tr>
                        <tr>
                            <td><strong>Delegation Rejected</strong></td>
                            <td>ADV User (delegator)</td>
                            <td>Notification when contractor rejects a delegation with reason</td>
                        </tr>
                        <tr>
                            <td><strong>Pending Reminder</strong></td>
                            <td>Contractor Admin</td>
                            <td>Reminder for delegations pending response beyond threshold</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Delegation Audit Trail -->
            <div class="feature-item">
                <h3>Delegation Audit Trail</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: system.audit</p>
                <p class="description">All delegation activities are logged in the system audit trail, including:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; margin-top: 0.5rem; list-style-type: disc;">
                    <li>Delegation creation with site and contractor details</li>
                    <li>Contractor acceptance with timestamp and user</li>
                    <li>Contractor rejection with reason and timestamp</li>
                    <li>Re-delegation actions when sites are reassigned</li>
                    <li>Bulk delegation operations with success/failure counts</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Feasibility Tracking Section -->
    <section id="feasibility-tracking" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-clipboard-list"></i>Feasibility Tracking</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">The Feasibility Tracking module provides comprehensive monitoring and management of feasibility assessments conducted by engineers at delegated sites. This module enables ADV users to track the progress of feasibility checks, review submitted data, approve or reject submissions, and monitor ETA (Estimated Time of Arrival) and ADA (Actual Date of Arrival) metrics. Effective feasibility tracking ensures quality control and timely progression of site installations.</p>
            
            <!-- Tracking Dashboard -->
            <div class="feature-item">
                <h3>Tracking Dashboard</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: feasibility.view</p>
                <p class="description mb-3">The Feasibility Tracking Dashboard provides a comprehensive overview of all feasibility activities with key metrics and status breakdowns:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Description</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-clipboard-list text-indigo-500 mr-2"></i>Total Feasibilities</strong></td>
                            <td>Total number of feasibility checks in the system</td>
                            <td>Overall volume tracking</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-clock text-yellow-500 mr-2"></i>Pending Submission</strong></td>
                            <td>Feasibility checks assigned but not yet submitted by engineers</td>
                            <td>Identify delayed submissions</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-hourglass-half text-orange-500 mr-2"></i>Pending Review</strong></td>
                            <td>Feasibility checks submitted and awaiting contractor review</td>
                            <td>Track contractor review backlog</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-user-check text-blue-500 mr-2"></i>Pending Final Approval</strong></td>
                            <td>Feasibility checks approved by contractor, awaiting ADV final approval</td>
                            <td>ADV approval queue</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-check-circle text-green-500 mr-2"></i>Approved</strong></td>
                            <td>Feasibility checks fully approved by ADV</td>
                            <td>Completed feasibilities ready for installation</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-times-circle text-red-500 mr-2"></i>Rejected</strong></td>
                            <td>Feasibility checks rejected by contractor or ADV</td>
                            <td>Track quality issues requiring rework</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-chart-line mr-1"></i>
                        <strong>Dashboard Features:</strong> The dashboard includes visual charts showing feasibility distribution by status, contractor performance, and timeline trends. Click on any metric card to filter the detailed list view.
                    </p>
                </div>
            </div>
            
            <!-- Pending Final Approval Workflow -->
            <div class="feature-item">
                <h3>Pending Final Approval Workflow</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: feasibility.approve, feasibility.reject</p>
                <p class="description mb-3">The Pending Final Approval section is where ADV users review and approve feasibility checks that have been submitted by engineers and approved by contractors:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Approval Workflow:</strong></p>
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                        <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-user-edit mr-1"></i>Engineer Submits</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dbeafe; color: #1e40af; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-user-check mr-1"></i>Contractor Reviews</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #e0e7ff; color: #3730a3; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-clipboard-check mr-1"></i>ADV Final Approval</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-check-circle mr-1"></i>Approved</span>
                    </div>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Permission Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-eye text-indigo-500 mr-2"></i>Review Details</strong></td>
                            <td>View complete feasibility check data including site information, engineer responses, uploaded images, and contractor remarks</td>
                            <td><code>feasibility.view</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-check text-green-500 mr-2"></i>Approve</strong></td>
                            <td>Grant final approval to the feasibility check, allowing the site to proceed to installation phase</td>
                            <td><code>feasibility.approve</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-times text-red-500 mr-2"></i>Reject</strong></td>
                            <td>Reject the feasibility check with mandatory feedback explaining the reason for rejection. The check returns to the contractor for rework</td>
                            <td><code>feasibility.reject</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-comment text-blue-500 mr-2"></i>Add Remarks</strong></td>
                            <td>Add internal notes or comments visible to ADV users for tracking purposes</td>
                            <td><code>feasibility.approve</code></td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                    <p style="font-size: 0.8125rem; color: #92400e;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Important:</strong> When rejecting a feasibility check, always provide clear and specific feedback so the contractor and engineer understand what needs to be corrected or improved.
                    </p>
                </div>
            </div>
            
            <!-- ETA and ADA Tracking -->
            <div class="feature-item">
                <h3>ETA and ADA Tracking</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                    <span class="badge badge-engineer">Engineer</span>
                </div>
                <p class="permission-ref">Permission: feasibility.view, feasibility.eta.manage, feasibility.ada.manage</p>
                <p class="description mb-3">ETA (Estimated Time of Arrival) and ADA (Actual Date of Arrival) tracking helps monitor engineer site visits and ensure timely feasibility assessments:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #dbeafe; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #3b82f6;">
                        <strong style="color: #1e40af;"><i class="fas fa-calendar-alt mr-2"></i>ETA (Estimated Time of Arrival)</strong>
                        <p style="font-size: 0.8125rem; color: #1e3a8a; margin-top: 0.5rem;">
                            The planned date and time when the engineer expects to arrive at the site for the feasibility assessment. Set by the contractor when assigning the engineer.
                        </p>
                        <p style="font-size: 0.75rem; color: #1e40af; margin-top: 0.5rem;">
                            <strong>Set By:</strong> Contractor<br>
                            <strong>Purpose:</strong> Planning and scheduling
                        </p>
                    </div>
                    <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #22c55e;">
                        <strong style="color: #166534;"><i class="fas fa-calendar-check mr-2"></i>ADA (Actual Date of Arrival)</strong>
                        <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.5rem;">
                            The actual date and time when the engineer arrived at the site and began the feasibility assessment. Recorded by the engineer when starting the feasibility form.
                        </p>
                        <p style="font-size: 0.75rem; color: #166534; margin-top: 0.5rem;">
                            <strong>Set By:</strong> Engineer<br>
                            <strong>Purpose:</strong> Actual tracking and performance metrics
                        </p>
                    </div>
                </div>
                
                <table class="role-table" style="margin-top: 1rem;">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Calculation</th>
                            <th>Interpretation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-clock text-green-500 mr-2"></i>On Time</strong></td>
                            <td>ADA ≤ ETA</td>
                            <td>Engineer arrived on or before the estimated time</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>Delayed</strong></td>
                            <td>ADA > ETA</td>
                            <td>Engineer arrived after the estimated time</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-hourglass-half text-orange-500 mr-2"></i>Pending</strong></td>
                            <td>ADA not set</td>
                            <td>Engineer has not yet arrived or started the assessment</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-chart-line text-indigo-500 mr-2"></i>Delay Duration</strong></td>
                            <td>ADA - ETA</td>
                            <td>Time difference between estimated and actual arrival (in hours/days)</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                    <p style="font-size: 0.8125rem; color: #166534;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Performance Tracking:</strong> ETA vs ADA metrics help identify contractors and engineers with consistent delays, enabling better resource planning and accountability.
                    </p>
                </div>
            </div>
            
            <!-- Export Data Functionality -->
            <div class="feature-item">
                <h3>Export Data Functionality</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: feasibility.export</p>
                <p class="description mb-3">Export feasibility tracking data for reporting, analysis, and record-keeping purposes:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Export Type</th>
                            <th>Description</th>
                            <th>Included Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-file-csv text-green-500 mr-2"></i>CSV Export</strong></td>
                            <td>Download feasibility data in CSV format for spreadsheet analysis</td>
                            <td>Site details, status, dates, engineer info, contractor info</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-file-excel text-green-600 mr-2"></i>Excel Export</strong></td>
                            <td>Download formatted Excel files with proper column headers and styling</td>
                            <td>All CSV data plus formatted cells and filters</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-filter text-blue-500 mr-2"></i>Filtered Export</strong></td>
                            <td>Export only the currently filtered/visible records</td>
                            <td>Respects all active filters (status, contractor, date range)</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-database text-indigo-500 mr-2"></i>Full Export</strong></td>
                            <td>Export all feasibility records with complete details</td>
                            <td>All fields including remarks, timestamps, and approval history</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-chart-bar text-purple-500 mr-2"></i>Summary Report</strong></td>
                            <td>Export aggregated statistics and performance metrics</td>
                            <td>Status counts, ETA/ADA analysis, contractor performance</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-download mr-1"></i>
                        <strong>Export Options:</strong> Use the export button in the toolbar to access export options. You can choose to export the current view or all records, and select your preferred format.
                    </p>
                </div>
            </div>
            
            <!-- Feasibility Status Breakdown -->
            <div class="feature-item">
                <h3>Feasibility Status Breakdown</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                    <span class="badge badge-engineer">Engineer</span>
                </div>
                <p class="permission-ref">Permission: feasibility.view</p>
                <p class="description mb-3">Understanding feasibility statuses is essential for tracking the assessment workflow:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #fef3c7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                        <strong style="color: #92400e;"><i class="fas fa-clock mr-2"></i>Pending Submission</strong>
                        <p style="font-size: 0.8125rem; color: #78350f; margin-top: 0.5rem;">
                            Engineer has been assigned but has not yet submitted the feasibility check. The engineer needs to visit the site and complete the assessment form.
                        </p>
                    </div>
                    <div style="background: #fed7aa; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #ea580c;">
                        <strong style="color: #7c2d12;"><i class="fas fa-hourglass-half mr-2"></i>Pending Review</strong>
                        <p style="font-size: 0.8125rem; color: #7c2d12; margin-top: 0.5rem;">
                            Engineer has submitted the feasibility check. Awaiting contractor review and approval before ADV final approval.
                        </p>
                    </div>
                    <div style="background: #dbeafe; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #3b82f6;">
                        <strong style="color: #1e40af;"><i class="fas fa-user-check mr-2"></i>Pending Final Approval</strong>
                        <p style="font-size: 0.8125rem; color: #1e3a8a; margin-top: 0.5rem;">
                            Contractor has approved the feasibility check. Awaiting ADV final approval to proceed to installation phase.
                        </p>
                    </div>
                    <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #22c55e;">
                        <strong style="color: #166534;"><i class="fas fa-check-circle mr-2"></i>Approved</strong>
                        <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.5rem;">
                            ADV has granted final approval. The site is ready to proceed to the installation phase. No further feasibility actions required.
                        </p>
                    </div>
                    <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #ef4444;">
                        <strong style="color: #991b1b;"><i class="fas fa-times-circle mr-2"></i>Rejected</strong>
                        <p style="font-size: 0.8125rem; color: #7f1d1d; margin-top: 0.5rem;">
                            Feasibility check was rejected by contractor or ADV with feedback. Engineer must address the issues and resubmit.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Filtering and Search -->
            <div class="feature-item">
                <h3>Filtering and Search</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: feasibility.view</p>
                <p class="description mb-3">Powerful filtering options help you quickly find specific feasibility checks:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li><strong>Status Filter</strong> - Filter by feasibility status (Pending Submission, Pending Review, Pending Final Approval, Approved, Rejected)</li>
                    <li><strong>Contractor Filter</strong> - Filter by contractor company to see feasibilities for specific contractors</li>
                    <li><strong>Engineer Filter</strong> - Filter by assigned engineer to track individual performance</li>
                    <li><strong>Date Range Filter</strong> - Filter by submission date, ETA, or ADA date ranges</li>
                    <li><strong>Site Search</strong> - Search by site ID, site name, or location details</li>
                    <li><strong>Delay Filter</strong> - Filter to show only delayed feasibilities (ADA > ETA)</li>
                </ul>
            </div>
            
            <!-- Feasibility Notifications -->
            <div class="feature-item">
                <h3>Feasibility Notifications</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="description mb-3">Stay informed about feasibility activities through system notifications:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Notification Recipient</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Pending Final Approval</strong></td>
                            <td>ADV Admin/Manager</td>
                            <td>Notification when a feasibility check is ready for ADV final approval</td>
                        </tr>
                        <tr>
                            <td><strong>Feasibility Approved</strong></td>
                            <td>Contractor, Engineer</td>
                            <td>Notification when ADV approves a feasibility check</td>
                        </tr>
                        <tr>
                            <td><strong>Feasibility Rejected</strong></td>
                            <td>Contractor, Engineer</td>
                            <td>Notification when ADV rejects a feasibility check with feedback</td>
                        </tr>
                        <tr>
                            <td><strong>Overdue Submission</strong></td>
                            <td>ADV Manager, Contractor</td>
                            <td>Alert when feasibility submission is overdue based on ETA</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Feasibility Audit Trail -->
            <div class="feature-item">
                <h3>Feasibility Audit Trail</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: system.audit</p>
                <p class="description">All feasibility activities are logged in the system audit trail, including:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; margin-top: 0.5rem; list-style-type: disc;">
                    <li>Engineer assignment and ETA setting</li>
                    <li>Engineer submission with ADA timestamp</li>
                    <li>Contractor review and approval/rejection</li>
                    <li>ADV final approval or rejection with remarks</li>
                    <li>Status changes and resubmissions</li>
                    <li>Data exports and report generation</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Installation Tracking Section -->
    <section id="installation-tracking" class="doc-section" data-searchable="true">
        <div class="section-header" onclick="toggleSection(this)">
            <h2><i class="fas fa-tools"></i>Installation Tracking</h2>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="section-content">
            <p class="description mb-4">The Installation Tracking module provides comprehensive monitoring and management of installation activities conducted by engineers at approved sites. This module enables ADV users to track the progress of installations, review submitted installation data, approve or reject installation submissions, and monitor the complete installation lifecycle from initiation to completion. Effective installation tracking ensures quality control, timely completion, and proper documentation of all installation activities.</p>
            
            <!-- Tracking Dashboard -->
            <div class="feature-item">
                <h3>Tracking Dashboard</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: installation.view</p>
                <p class="description mb-3">The Installation Tracking Dashboard provides a comprehensive overview of all installation activities with key metrics and status breakdowns:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Description</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-tools text-indigo-500 mr-2"></i>Total Installations</strong></td>
                            <td>Total number of installation records in the system</td>
                            <td>Overall volume tracking</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-play text-blue-500 mr-2"></i>Not Started</strong></td>
                            <td>Installations assigned but not yet initiated by engineers</td>
                            <td>Identify pending installations</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-cog text-yellow-500 mr-2"></i>In Progress</strong></td>
                            <td>Installations currently being worked on by engineers</td>
                            <td>Track active installation work</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-clock text-orange-500 mr-2"></i>Pending Submission</strong></td>
                            <td>Installations completed but not yet submitted for review</td>
                            <td>Identify delayed submissions</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-hourglass-half text-purple-500 mr-2"></i>Pending Review</strong></td>
                            <td>Installations submitted and awaiting contractor review</td>
                            <td>Track contractor review backlog</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-user-check text-cyan-500 mr-2"></i>Pending Final Approval</strong></td>
                            <td>Installations approved by contractor, awaiting ADV final approval</td>
                            <td>ADV approval queue</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-check-circle text-green-500 mr-2"></i>Completed</strong></td>
                            <td>Installations fully approved and completed</td>
                            <td>Successfully completed installations</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-times-circle text-red-500 mr-2"></i>Rejected</strong></td>
                            <td>Installations rejected by contractor or ADV</td>
                            <td>Track quality issues requiring rework</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-chart-line mr-1"></i>
                        <strong>Dashboard Features:</strong> The dashboard includes visual charts showing installation distribution by status, contractor performance, timeline trends, and completion rates. Click on any metric card to filter the detailed list view.
                    </p>
                </div>
            </div>
            
            <!-- All Installations List and Filtering -->
            <div class="feature-item">
                <h3>All Installations List and Filtering</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: installation.view</p>
                <p class="description mb-3">The All Installations view provides a comprehensive list of all installation records with powerful filtering and search capabilities:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Available Filters:</strong></p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.875rem;">
                        <div>
                            <strong style="color: #6366f1;"><i class="fas fa-filter mr-1"></i>Status Filter</strong>
                            <p style="color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem;">Filter by installation status (Not Started, In Progress, Pending Review, etc.)</p>
                        </div>
                        <div>
                            <strong style="color: #6366f1;"><i class="fas fa-building mr-1"></i>Contractor Filter</strong>
                            <p style="color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem;">Filter by contractor company to see installations for specific contractors</p>
                        </div>
                        <div>
                            <strong style="color: #6366f1;"><i class="fas fa-user-hard-hat mr-1"></i>Engineer Filter</strong>
                            <p style="color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem;">Filter by assigned engineer to track individual performance</p>
                        </div>
                        <div>
                            <strong style="color: #6366f1;"><i class="fas fa-calendar mr-1"></i>Date Range Filter</strong>
                            <p style="color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem;">Filter by creation date, ETA, ADA, or completion date ranges</p>
                        </div>
                        <div>
                            <strong style="color: #6366f1;"><i class="fas fa-search mr-1"></i>Site Search</strong>
                            <p style="color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem;">Search by site ID, site name, or location details</p>
                        </div>
                        <div>
                            <strong style="color: #6366f1;"><i class="fas fa-exclamation-triangle mr-1"></i>Priority Filter</strong>
                            <p style="color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem;">Filter by installation priority (High, Medium, Low)</p>
                        </div>
                    </div>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>List Column</th>
                            <th>Description</th>
                            <th>Sortable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Site Information</strong></td>
                            <td>Site ID, name, location, and customer details</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Installation Status</strong></td>
                            <td>Current status with color-coded badges</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Assigned Engineer</strong></td>
                            <td>Engineer name and contact information</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Contractor</strong></td>
                            <td>Contractor company name</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>ETA / ADA</strong></td>
                            <td>Estimated and actual arrival dates</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td><strong>Progress</strong></td>
                            <td>Installation progress percentage and completed sections</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td><strong>Actions</strong></td>
                            <td>View details, approve/reject, export buttons</td>
                            <td>No</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                    <p style="font-size: 0.8125rem; color: #166534;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Bulk Actions:</strong> Select multiple installations to perform bulk operations like status updates, engineer reassignment, or bulk export.
                    </p>
                </div>
            </div>
            
            <!-- Pending Final Approval Workflow -->
            <div class="feature-item">
                <h3>Pending Final Approval Workflow</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: installation.approve, installation.reject</p>
                <p class="description mb-3">The Pending Final Approval section is where ADV users review and approve installation submissions that have been completed by engineers and approved by contractors:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Installation Approval Workflow:</strong></p>
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                        <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-play mr-1"></i>Engineer Initiates</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #fed7aa; color: #7c2d12; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-cog mr-1"></i>Work in Progress</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #e0e7ff; color: #3730a3; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-upload mr-1"></i>Engineer Submits</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dbeafe; color: #1e40af; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-user-check mr-1"></i>Contractor Reviews</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-clipboard-check mr-1"></i>ADV Final Approval</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span style="background: #d1fae5; color: #065f46; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-check-circle mr-1"></i>Completed</span>
                    </div>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Permission Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-eye text-indigo-500 mr-2"></i>Review Installation Details</strong></td>
                            <td>View complete installation data including all sections, uploaded images, material receipts, checkpoint verifications, and contractor remarks</td>
                            <td><code>installation.view</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-check text-green-500 mr-2"></i>Approve Installation</strong></td>
                            <td>Grant final approval to the installation, marking it as completed and ready for service activation</td>
                            <td><code>installation.approve</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-times text-red-500 mr-2"></i>Reject Installation</strong></td>
                            <td>Reject the installation with mandatory feedback explaining the reason for rejection. The installation returns to the contractor for rework</td>
                            <td><code>installation.reject</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-comment text-blue-500 mr-2"></i>Add Internal Remarks</strong></td>
                            <td>Add internal notes or comments visible to ADV users for tracking and quality assurance purposes</td>
                            <td><code>installation.approve</code></td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-download text-purple-500 mr-2"></i>Download Documentation</strong></td>
                            <td>Download complete installation documentation including images, receipts, and verification reports</td>
                            <td><code>installation.view</code></td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                    <p style="font-size: 0.8125rem; color: #92400e;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Quality Assurance:</strong> When rejecting an installation, provide detailed and specific feedback so the contractor and engineer understand exactly what needs to be corrected, replaced, or improved.
                    </p>
                </div>
            </div>
            
            <!-- Installation Review and Approval Process -->
            <div class="feature-item">
                <h3>Installation Review and Approval Process</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: installation.view, installation.approve, installation.reject</p>
                <p class="description mb-3">The installation review process ensures quality and completeness of all installation work through systematic verification:</p>
                
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Review Checklist Areas:</strong></p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; font-size: 0.875rem;">
                        <div style="background: #dbeafe; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #3b82f6;">
                            <strong style="color: #1e40af;"><i class="fas fa-clipboard-list mr-1"></i>Section Completion</strong>
                            <p style="color: #1e3a8a; font-size: 0.8125rem; margin-top: 0.25rem;">Verify all required installation sections are completed with proper data entry</p>
                        </div>
                        <div style="background: #dcfce7; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #22c55e;">
                            <strong style="color: #166534;"><i class="fas fa-images mr-1"></i>Image Documentation</strong>
                            <p style="color: #14532d; font-size: 0.8125rem; margin-top: 0.25rem;">Review uploaded images for quality, completeness, and compliance with standards</p>
                        </div>
                        <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #f59e0b;">
                            <strong style="color: #92400e;"><i class="fas fa-receipt mr-1"></i>Material Receipts</strong>
                            <p style="color: #78350f; font-size: 0.8125rem; margin-top: 0.25rem;">Verify material receipt confirmations and quantities match dispatch records</p>
                        </div>
                        <div style="background: #e0e7ff; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #6366f1;">
                            <strong style="color: #3730a3;"><i class="fas fa-check-double mr-1"></i>Checkpoint Verification</strong>
                            <p style="color: #312e81; font-size: 0.8125rem; margin-top: 0.25rem;">Review checkpoint completions and verification status for each installation phase</p>
                        </div>
                        <div style="background: #fce7f3; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #ec4899;">
                            <strong style="color: #9d174d;"><i class="fas fa-tools mr-1"></i>Technical Compliance</strong>
                            <p style="color: #831843; font-size: 0.8125rem; margin-top: 0.25rem;">Ensure installation meets technical specifications and quality standards</p>
                        </div>
                        <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #10b981;">
                            <strong style="color: #047857;"><i class="fas fa-file-alt mr-1"></i>Documentation Quality</strong>
                            <p style="color: #065f46; font-size: 0.8125rem; margin-top: 0.25rem;">Review remarks, notes, and additional documentation for completeness</p>
                        </div>
                    </div>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Review Stage</th>
                            <th>Reviewer</th>
                            <th>Focus Areas</th>
                            <th>Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Initial Review</strong></td>
                            <td>Contractor</td>
                            <td>Completeness, basic quality, material verification</td>
                            <td>Approve for ADV review or reject for rework</td>
                        </tr>
                        <tr>
                            <td><strong>Technical Review</strong></td>
                            <td>ADV Manager/Admin</td>
                            <td>Technical compliance, quality standards, documentation</td>
                            <td>Final approval or rejection with detailed feedback</td>
                        </tr>
                        <tr>
                            <td><strong>Quality Assurance</strong></td>
                            <td>ADV Superadmin/Admin</td>
                            <td>Overall quality, compliance verification, audit trail</td>
                            <td>Quality certification and completion marking</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-lightbulb mr-1"></i>
                        <strong>Best Practice:</strong> Use the review comments section to provide constructive feedback that helps engineers and contractors improve future installations and maintain quality standards.
                    </p>
                </div>
            </div>
            
            <!-- Installation Status Breakdown -->
            <div class="feature-item">
                <h3>Installation Status Breakdown</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                    <span class="badge badge-engineer">Engineer</span>
                </div>
                <p class="permission-ref">Permission: installation.view</p>
                <p class="description mb-3">Understanding installation statuses is essential for tracking the complete installation lifecycle:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                    <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #6b7280;">
                        <strong style="color: #374151;"><i class="fas fa-play mr-2"></i>Not Started</strong>
                        <p style="font-size: 0.8125rem; color: #4b5563; margin-top: 0.5rem;">
                            Installation has been assigned to an engineer but work has not yet begun. Engineer needs to initiate the installation process.
                        </p>
                    </div>
                    <div style="background: #dbeafe; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #3b82f6;">
                        <strong style="color: #1e40af;"><i class="fas fa-cog mr-2"></i>In Progress</strong>
                        <p style="font-size: 0.8125rem; color: #1e3a8a; margin-top: 0.5rem;">
                            Engineer is actively working on the installation. Some sections may be completed while others are still in progress.
                        </p>
                    </div>
                    <div style="background: #fef3c7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                        <strong style="color: #92400e;"><i class="fas fa-clock mr-2"></i>Pending Submission</strong>
                        <p style="font-size: 0.8125rem; color: #78350f; margin-top: 0.5rem;">
                            Installation work is completed but engineer has not yet submitted it for contractor review. Final submission pending.
                        </p>
                    </div>
                    <div style="background: #fed7aa; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #ea580c;">
                        <strong style="color: #7c2d12;"><i class="fas fa-hourglass-half mr-2"></i>Pending Review</strong>
                        <p style="font-size: 0.8125rem; color: #7c2d12; margin-top: 0.5rem;">
                            Engineer has submitted the installation. Awaiting contractor review and approval before ADV final approval.
                        </p>
                    </div>
                    <div style="background: #e0e7ff; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #6366f1;">
                        <strong style="color: #3730a3;"><i class="fas fa-user-check mr-2"></i>Pending Final Approval</strong>
                        <p style="font-size: 0.8125rem; color: #312e81; margin-top: 0.5rem;">
                            Contractor has approved the installation. Awaiting ADV final approval to mark the installation as completed.
                        </p>
                    </div>
                    <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #22c55e;">
                        <strong style="color: #166534;"><i class="fas fa-check-circle mr-2"></i>Completed</strong>
                        <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.5rem;">
                            ADV has granted final approval. Installation is completed and the site is ready for service activation.
                        </p>
                    </div>
                    <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #ef4444;">
                        <strong style="color: #991b1b;"><i class="fas fa-times-circle mr-2"></i>Rejected</strong>
                        <p style="font-size: 0.8125rem; color: #7f1d1d; margin-top: 0.5rem;">
                            Installation was rejected by contractor or ADV with feedback. Engineer must address the issues and resubmit.
                        </p>
                    </div>
                    <div style="background: #fef2f2; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #f87171;">
                        <strong style="color: #b91c1c;"><i class="fas fa-pause mr-2"></i>On Hold</strong>
                        <p style="font-size: 0.8125rem; color: #991b1b; margin-top: 0.5rem;">
                            Installation has been temporarily suspended due to external factors, material shortage, or other issues requiring resolution.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Installation Export and Reporting -->
            <div class="feature-item">
                <h3>Installation Export and Reporting</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="permission-ref">Permission: installation.export</p>
                <p class="description mb-3">Export installation tracking data for reporting, analysis, and record-keeping purposes:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Export Type</th>
                            <th>Description</th>
                            <th>Included Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><i class="fas fa-file-csv text-green-500 mr-2"></i>CSV Export</strong></td>
                            <td>Download installation data in CSV format for spreadsheet analysis</td>
                            <td>Site details, status, dates, engineer info, contractor info, progress</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-file-excel text-green-600 mr-2"></i>Excel Export</strong></td>
                            <td>Download formatted Excel files with proper column headers and styling</td>
                            <td>All CSV data plus formatted cells, charts, and pivot tables</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-file-pdf text-red-500 mr-2"></i>Installation Report</strong></td>
                            <td>Generate comprehensive PDF reports for individual installations</td>
                            <td>Complete installation details, images, receipts, verification status</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-chart-bar text-purple-500 mr-2"></i>Performance Report</strong></td>
                            <td>Export performance metrics and analytics</td>
                            <td>Completion rates, timeline analysis, contractor performance, quality metrics</td>
                        </tr>
                        <tr>
                            <td><strong><i class="fas fa-filter text-blue-500 mr-2"></i>Filtered Export</strong></td>
                            <td>Export only the currently filtered/visible records</td>
                            <td>Respects all active filters (status, contractor, date range, engineer)</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                    <p style="font-size: 0.8125rem; color: #1e40af;">
                        <i class="fas fa-download mr-1"></i>
                        <strong>Export Options:</strong> Use the export button in the toolbar to access export options. You can choose to export the current view or all records, select your preferred format, and include/exclude specific data fields.
                    </p>
                </div>
            </div>
            
            <!-- Installation Notifications -->
            <div class="feature-item">
                <h3>Installation Notifications</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                    <span class="badge badge-manager">Manager</span>
                </div>
                <p class="description mb-3">Stay informed about installation activities through system notifications:</p>
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Notification Recipient</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Installation Assigned</strong></td>
                            <td>Engineer, Contractor</td>
                            <td>Notification when a new installation is assigned to an engineer</td>
                        </tr>
                        <tr>
                            <td><strong>Installation Started</strong></td>
                            <td>Contractor, ADV Manager</td>
                            <td>Notification when engineer initiates installation work</td>
                        </tr>
                        <tr>
                            <td><strong>Pending Final Approval</strong></td>
                            <td>ADV Admin/Manager</td>
                            <td>Notification when an installation is ready for ADV final approval</td>
                        </tr>
                        <tr>
                            <td><strong>Installation Completed</strong></td>
                            <td>Contractor, Engineer, Customer</td>
                            <td>Notification when ADV approves and completes an installation</td>
                        </tr>
                        <tr>
                            <td><strong>Installation Rejected</strong></td>
                            <td>Contractor, Engineer</td>
                            <td>Notification when ADV rejects an installation with feedback</td>
                        </tr>
                        <tr>
                            <td><strong>Overdue Installation</strong></td>
                            <td>ADV Manager, Contractor</td>
                            <td>Alert when installation is overdue based on expected completion date</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Installation Audit Trail -->
            <div class="feature-item">
                <h3>Installation Audit Trail</h3>
                <div class="role-badges">
                    <span class="badge badge-superadmin">Superadmin</span>
                    <span class="badge badge-admin">Admin</span>
                </div>
                <p class="permission-ref">Permission: system.audit</p>
                <p class="description">All installation activities are logged in the system audit trail, including:</p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; margin-top: 0.5rem; list-style-type: disc;">
                    <li>Installation assignment and engineer allocation</li>
                    <li>Installation initiation and progress updates</li>
                    <li>Section completions and checkpoint verifications</li>
                    <li>Material receipt confirmations and image uploads</li>
                    <li>Engineer submission and contractor review</li>
                    <li>ADV final approval or rejection with remarks</li>
                    <li>Status changes and resubmissions</li>
                    <li>Data exports and report generation</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- IP Configuration Section -->
    <section id="ip-configuration" class="doc-section" data-searchable="true
    
</div>

<script>
/**
 * Toggle section expand/collapse
 * Requirements: 2.3, 2.4 - Collapsible sections
 */
function toggleSection(header) {
    const content = header.nextElementSibling;
    const icon = header.querySelector('.toggle-icon');
    
    content.classList.toggle('expanded');
    icon.classList.toggle('rotated');
    
    // Store state in localStorage
    const sectionId = header.closest('.doc-section').id;
    const expandedSections = JSON.parse(localStorage.getItem('docExpandedSections') || '{}');
    expandedSections[sectionId] = content.classList.contains('expanded');
    localStorage.setItem('docExpandedSections', JSON.stringify(expandedSections));
}

/**
 * Search documentation
 * Requirements: 14.2, 14.3, 14.4 - Search functionality
 */
function searchDocumentation(query) {
    const sections = document.querySelectorAll('.doc-section');
    const searchTerm = query.toLowerCase().trim();
    
    // Clear previous highlights
    document.querySelectorAll('.highlight').forEach(el => {
        el.outerHTML = el.textContent;
    });
    
    if (!searchTerm) {
        // Show all sections when search is cleared
        sections.forEach(section => {
            section.style.display = '';
        });
        return;
    }
    
    sections.forEach(section => {
        const content = section.textContent.toLowerCase();
        if (content.includes(searchTerm)) {
            section.style.display = '';
            // Expand section if it contains match
            const sectionContent = section.querySelector('.section-content');
            const icon = section.querySelector('.toggle-icon');
            if (sectionContent && !sectionContent.classList.contains('expanded')) {
                sectionContent.classList.add('expanded');
                icon.classList.add('rotated');
            }
            // Highlight matches
            highlightMatches(section, searchTerm);
        } else {
            section.style.display = 'none';
        }
    });
}

/**
 * Highlight matching text
 */
function highlightMatches(element, searchTerm) {
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
    const textNodes = [];
    
    while (walker.nextNode()) {
        if (walker.currentNode.textContent.toLowerCase().includes(searchTerm)) {
            textNodes.push(walker.currentNode);
        }
    }
    
    textNodes.forEach(node => {
        const text = node.textContent;
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        if (regex.test(text)) {
            const span = document.createElement('span');
            span.innerHTML = text.replace(regex, '<span class="highlight">$1</span>');
            node.parentNode.replaceChild(span, node);
        }
    });
}

/**
 * Clear search
 */
function clearSearch() {
    document.getElementById('docSearch').value = '';
    searchDocumentation('');
}

/**
 * Print documentation
 * Requirements: 15.1, 15.2, 15.3 - Print functionality
 */
function printDocumentation() {
    // Expand all sections before printing
    document.querySelectorAll('.section-content').forEach(content => {
        content.classList.add('expanded');
    });
    document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.classList.add('rotated');
    });
    
    // Trigger print
    window.print();
}

/**
 * Restore expanded sections from localStorage
 */
function restoreExpandedSections() {
    const expandedSections = JSON.parse(localStorage.getItem('docExpandedSections') || '{}');
    
    Object.keys(expandedSections).forEach(sectionId => {
        if (expandedSections[sectionId]) {
            const section = document.getElementById(sectionId);
            if (section) {
                const content = section.querySelector('.section-content');
                const icon = section.querySelector('.toggle-icon');
                if (content) {
                    content.classList.add('expanded');
                    icon.classList.add('rotated');
                }
            }
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    restoreExpandedSections();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
