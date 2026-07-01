<?php
/**
 * Role Overview Documentation Section
 * Requirements: 2.5, 13.3 - Role overview content
 */

return [
    'id' => 'role-overview',
    'title' => 'Role Overview',
    'icon' => 'fas fa-users-cog',
    'content' => '
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
    '
];