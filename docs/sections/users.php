<?php
/**
 * Users Documentation Section
 * Requirements: 5.1, 5.2, 5.3, 5.4, 13.1, 13.2 - Users module documentation
 */

return [
    'id' => 'users',
    'title' => 'Users',
    'icon' => 'fas fa-users',
    'content' => '
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
                    <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">User can log in and access assigned features</p>
                </div>
                <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #dc2626;">
                    <strong style="color: #374151;"><i class="fas fa-times-circle text-red-500 mr-1"></i>Inactive</strong>
                    <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">User cannot log in but data is preserved</p>
                </div>
                <div style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #f59e0b;">
                    <strong style="color: #374151;"><i class="fas fa-pause-circle text-yellow-500 mr-1"></i>Suspended</strong>
                    <p style="font-size: 0.8125rem; color: #6b7280; margin-top: 0.25rem;">Temporarily blocked due to policy violations</p>
                </div>
            </div>
        </div>
        
        <!-- Role Management and Hierarchy -->
        <div class="feature-item">
            <h3>Role Management and Hierarchy System</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: users.roles.manage</p>
            <p class="description mb-3">Manage user roles within the hierarchical permission system:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Role Level</th>
                        <th>Numeric Value</th>
                        <th>Capabilities</th>
                        <th>Assignment Rules</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge badge-superadmin">Superadmin</span></td>
                        <td>100</td>
                        <td>Complete system access, user management, system administration</td>
                        <td>Only assignable by existing Superadmin</td>
                    </tr>
                    <tr>
                        <td><span class="badge badge-admin">Admin</span></td>
                        <td>80</td>
                        <td>Full operational access, user management, reporting</td>
                        <td>Assignable by Superadmin or Admin</td>
                    </tr>
                    <tr>
                        <td><span class="badge badge-manager">Manager</span></td>
                        <td>60</td>
                        <td>Operational management, limited user access, reporting</td>
                        <td>Assignable by Admin or higher</td>
                    </tr>
                    <tr>
                        <td><span class="badge badge-engineer">Engineer</span></td>
                        <td>40</td>
                        <td>Field operations, assigned tasks, limited reporting</td>
                        <td>Assignable by Manager or higher</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                <p style="font-size: 0.8125rem; color: #92400e;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Hierarchy Rule:</strong> Users can only assign roles equal to or lower than their own role level. This prevents privilege escalation.
                </p>
            </div>
        </div>
        
        <!-- Permission Management -->
        <div class="feature-item">
            <h3>Permission Management (module.action format)</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: users.permissions.manage</p>
            <p class="description mb-3">Granular permission control using the module.action format:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Permission Format</th>
                        <th>Example</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>module.create</strong></td>
                        <td><code>users.create</code></td>
                        <td>Permission to create new records in the specified module</td>
                    </tr>
                    <tr>
                        <td><strong>module.edit</strong></td>
                        <td><code>sites.edit</code></td>
                        <td>Permission to modify existing records in the module</td>
                    </tr>
                    <tr>
                        <td><strong>module.view</strong></td>
                        <td><code>inventory.view</code></td>
                        <td>Permission to view records and access module interface</td>
                    </tr>
                    <tr>
                        <td><strong>module.delete</strong></td>
                        <td><code>companies.delete</code></td>
                        <td>Permission to delete records from the module</td>
                    </tr>
                    <tr>
                        <td><strong>module.export</strong></td>
                        <td><code>reports.export</code></td>
                        <td>Permission to export data from the module</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                <p style="font-size: 0.8125rem; color: #1e40af;">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Permission Inheritance:</strong> Higher role levels automatically inherit permissions from lower levels, plus additional capabilities specific to their role.
                </p>
            </div>
        </div>
        
        <!-- Role Assignment with Company Type Restrictions -->
        <div class="feature-item">
            <h3>Role Assignment with Company Type Restrictions</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: users.roles.assign</p>
            <p class="description mb-3">Role assignment rules based on company type and user hierarchy:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Company Type</th>
                        <th>Available Roles</th>
                        <th>Restrictions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>ADV Company</strong></td>
                        <td>Superadmin, Admin, Manager, Engineer</td>
                        <td>Full role hierarchy available</td>
                    </tr>
                    <tr>
                        <td><strong>Contractor Company</strong></td>
                        <td>Admin, Manager, Engineer</td>
                        <td>Cannot assign Superadmin role</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                <p style="font-size: 0.8125rem; color: #166534;">
                    <i class="fas fa-shield-alt mr-1"></i>
                    <strong>Security Note:</strong> Contractor companies cannot have Superadmin users to maintain system security and prevent unauthorized access to ADV-specific functions.
                </p>
            </div>
        </div>
        
        <!-- User Profile Management -->
        <div class="feature-item">
            <h3>User Profile Management</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
                <span class="badge badge-engineer">Engineer</span>
            </div>
            <p class="permission-ref">Permission: users.profile.edit (own profile)</p>
            <p class="description mb-3">Users can manage their own profile information:</p>
            <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                <li><strong>Personal Information</strong> - Name, email, phone number, address</li>
                <li><strong>Password Management</strong> - Change password with current password verification</li>
                <li><strong>Profile Picture</strong> - Upload and manage profile image</li>
                <li><strong>Notification Preferences</strong> - Configure email and system notifications</li>
                <li><strong>Session Management</strong> - View active sessions and logout from other devices</li>
            </ul>
        </div>
        
        <!-- User Activity Monitoring -->
        <div class="feature-item">
            <h3>User Activity Monitoring</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: system.audit</p>
            <p class="description mb-3">Monitor user activities and system access:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Activity Type</th>
                        <th>Information Tracked</th>
                        <th>Retention Period</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Login Activity</strong></td>
                        <td>Login time, IP address, device information, success/failure</td>
                        <td>90 days</td>
                    </tr>
                    <tr>
                        <td><strong>System Actions</strong></td>
                        <td>Module accessed, actions performed, data modified, timestamps</td>
                        <td>1 year</td>
                    </tr>
                    <tr>
                        <td><strong>Permission Changes</strong></td>
                        <td>Role changes, permission modifications, approver information</td>
                        <td>Permanent</td>
                    </tr>
                    <tr>
                        <td><strong>Security Events</strong></td>
                        <td>Failed login attempts, password changes, suspicious activities</td>
                        <td>2 years</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Bulk User Operations -->
        <div class="feature-item">
            <h3>Bulk User Operations</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: users.bulk.operations</p>
            <p class="description mb-3">Efficient management of multiple users simultaneously:</p>
            <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                <li><strong>Bulk Import</strong> - Import users from CSV/Excel files with validation</li>
                <li><strong>Bulk Status Update</strong> - Activate/deactivate multiple users at once</li>
                <li><strong>Bulk Role Assignment</strong> - Assign roles to multiple users simultaneously</li>
                <li><strong>Bulk Password Reset</strong> - Force password reset for selected users</li>
                <li><strong>Bulk Export</strong> - Export user data with filtering options</li>
            </ul>
        </div>
    '
];