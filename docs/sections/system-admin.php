<?php
/**
 * System Administration Documentation Section
 * Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 13.1, 13.2 - System Administration module documentation
 */

return [
    'id' => 'system-admin',
    'title' => 'System Administration',
    'icon' => 'fas fa-server',
    'content' => '
        <p class="description mb-4">The System Administration module provides advanced system management capabilities including permission delegation to contractors, audit trail monitoring, system settings configuration, backup and restore operations, health monitoring, and file management. This module is primarily accessible to Superadmin users for maintaining system integrity and performance.</p>
        
        <!-- Permission Delegation -->
        <div class="feature-item">
            <h3>Permission Delegation to Contractors</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
            </div>
            <p class="permission-ref">Permission: system.permissions.delegate</p>
            <p class="description mb-3">Grant specific permissions to contractor companies for enhanced functionality:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Permission Category</th>
                        <th>Available Permissions</th>
                        <th>Use Case</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Site Management</strong></td>
                        <td>sites.create, sites.edit, sites.bulk.upload</td>
                        <td>Allow contractors to manage their own sites</td>
                    </tr>
                    <tr>
                        <td><strong>User Management</strong></td>
                        <td>users.create, users.edit (limited)</td>
                        <td>Enable contractor user administration</td>
                    </tr>
                    <tr>
                        <td><strong>Reporting</strong></td>
                        <td>reports.view, reports.export</td>
                        <td>Provide access to contractor-specific reports</td>
                    </tr>
                    <tr>
                        <td><strong>Inventory</strong></td>
                        <td>inventory.view, inventory.request</td>
                        <td>Allow inventory visibility and material requests</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                <p style="font-size: 0.8125rem; color: #92400e;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Security Note:</strong> Delegated permissions are scoped to the contractor\'s own company data and cannot access ADV-specific functions or other contractors\' data.
                </p>
            </div>
        </div>
        
        <!-- Audit Trail -->
        <div class="feature-item">
            <h3>Audit Trail Viewing and Filtering</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: system.audit</p>
            <p class="description mb-3">Comprehensive system activity monitoring and analysis:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Audit Category</th>
                        <th>Information Tracked</th>
                        <th>Retention Period</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>User Activities</strong></td>
                        <td>Login/logout, password changes, profile updates</td>
                        <td>2 years</td>
                    </tr>
                    <tr>
                        <td><strong>Data Changes</strong></td>
                        <td>Create, update, delete operations with before/after values</td>
                        <td>5 years</td>
                    </tr>
                    <tr>
                        <td><strong>System Events</strong></td>
                        <td>System startup, configuration changes, errors</td>
                        <td>1 year</td>
                    </tr>
                    <tr>
                        <td><strong>Security Events</strong></td>
                        <td>Failed login attempts, permission changes, suspicious activities</td>
                        <td>7 years</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                <p style="font-size: 0.8125rem; color: #1e40af;">
                    <i class="fas fa-filter mr-1"></i>
                    <strong>Advanced Filtering:</strong> Filter audit logs by user, module, action type, date range, IP address, and result status for detailed analysis.
                </p>
            </div>
        </div>
        
        <!-- System Settings -->
        <div class="feature-item">
            <h3>System Settings Configuration</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
            </div>
            <p class="permission-ref">Permission: system.settings.manage</p>
            <p class="description mb-3">Configure system-wide settings and parameters:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Setting Category</th>
                        <th>Configurable Options</th>
                        <th>Impact</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Security Settings</strong></td>
                        <td>Password policy, session timeout, login attempts</td>
                        <td>System security and access control</td>
                    </tr>
                    <tr>
                        <td><strong>Email Configuration</strong></td>
                        <td>SMTP settings, notification templates, sender addresses</td>
                        <td>System notifications and communications</td>
                    </tr>
                    <tr>
                        <td><strong>File Upload Settings</strong></td>
                        <td>Maximum file size, allowed formats, storage paths</td>
                        <td>Document and image upload functionality</td>
                    </tr>
                    <tr>
                        <td><strong>Performance Settings</strong></td>
                        <td>Cache duration, query limits, timeout values</td>
                        <td>System performance and responsiveness</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Backup and Restore -->
        <div class="feature-item">
            <h3>Backup and Restore Operations</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
            </div>
            <p class="permission-ref">Permission: system.backup.manage</p>
            <p class="description mb-3">Comprehensive data protection and recovery capabilities:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Operation</th>
                        <th>Description</th>
                        <th>Frequency Options</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Full System Backup</strong></td>
                        <td>Complete database and file system backup</td>
                        <td>Daily, Weekly, Monthly, On-demand</td>
                    </tr>
                    <tr>
                        <td><strong>Incremental Backup</strong></td>
                        <td>Backup only changed data since last backup</td>
                        <td>Hourly, Daily, Custom schedule</td>
                    </tr>
                    <tr>
                        <td><strong>Selective Restore</strong></td>
                        <td>Restore specific modules or data ranges</td>
                        <td>Point-in-time recovery options</td>
                    </tr>
                    <tr>
                        <td><strong>Backup Verification</strong></td>
                        <td>Automated backup integrity checking</td>
                        <td>After each backup operation</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                <p style="font-size: 0.8125rem; color: #166534;">
                    <i class="fas fa-shield-alt mr-1"></i>
                    <strong>Data Protection:</strong> All backups are encrypted and stored in multiple locations with automatic rotation and cleanup of old backups.
                </p>
            </div>
        </div>
        
        <!-- Health Monitor -->
        <div class="feature-item">
            <h3>Health Monitor and Performance Monitoring</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
            </div>
            <p class="permission-ref">Permission: system.monitor.view</p>
            <p class="description mb-3">Real-time system health and performance monitoring:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Monitor Type</th>
                        <th>Metrics Tracked</th>
                        <th>Alert Thresholds</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Server Resources</strong></td>
                        <td>CPU usage, memory consumption, disk space</td>
                        <td>CPU >80%, Memory >85%, Disk >90%</td>
                    </tr>
                    <tr>
                        <td><strong>Database Performance</strong></td>
                        <td>Query response time, connection count, deadlocks</td>
                        <td>Response >2s, Connections >100, Deadlocks >5/hour</td>
                    </tr>
                    <tr>
                        <td><strong>Application Health</strong></td>
                        <td>Error rates, response times, active sessions</td>
                        <td>Error rate >5%, Response >3s, Sessions >500</td>
                    </tr>
                    <tr>
                        <td><strong>Security Monitoring</strong></td>
                        <td>Failed login attempts, suspicious activities</td>
                        <td>Failed logins >10/hour, Suspicious patterns</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- File Manager -->
        <div class="feature-item">
            <h3>File Manager Functionality</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
            </div>
            <p class="permission-ref">Permission: system.files.manage</p>
            <p class="description mb-3">Comprehensive file system management capabilities:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Description</th>
                        <th>Security Controls</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>File Browser</strong></td>
                        <td>Navigate system directories and view file listings</td>
                        <td>Restricted to safe directories only</td>
                    </tr>
                    <tr>
                        <td><strong>Upload Management</strong></td>
                        <td>Manage uploaded files, organize by module and date</td>
                        <td>File type validation, size limits</td>
                    </tr>
                    <tr>
                        <td><strong>Cleanup Operations</strong></td>
                        <td>Remove temporary files, old logs, unused uploads</td>
                        <td>Confirmation required for deletions</td>
                    </tr>
                    <tr>
                        <td><strong>Storage Analytics</strong></td>
                        <td>Disk usage analysis, file type distribution</td>
                        <td>Read-only analysis and reporting</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #fee2e2; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #ef4444;">
                <p style="font-size: 0.8125rem; color: #991b1b;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Security Warning:</strong> File manager access is restricted to system directories only. Direct access to system files, configuration files, or application code is prevented.
                </p>
            </div>
        </div>
    '
];