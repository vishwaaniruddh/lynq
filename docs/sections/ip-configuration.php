<?php
/**
 * IP Configuration Documentation Section
 * Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 13.1, 13.2 - IP Configuration module documentation
 */

return [
    'id' => 'ip-configuration',
    'title' => 'IP Configuration',
    'icon' => 'fas fa-network-wired',
    'content' => '
        <p class="description mb-4">The IP Configuration module provides comprehensive management of IP addresses, router configurations, and network resource allocation. This module enables ADV users to maintain an organized inventory of IP addresses, configure router bindings, implement IP locking mechanisms for temporary reservations, and generate detailed reports for network planning and audit purposes.</p>
        
        <!-- Dashboard Overview -->
        <div class="feature-item">
            <h3>Dashboard Overview</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: ip_configuration.view</p>
            <p class="description mb-3">The IP Configuration Dashboard provides a comprehensive overview of all network resources with key metrics and visual analytics:</p>
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
                        <td><strong><i class="fas fa-network-wired text-indigo-500 mr-2"></i>Total IPs</strong></td>
                        <td>Total number of IP addresses in the master database</td>
                        <td>Overall IP inventory tracking</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-check-circle text-green-500 mr-2"></i>Available IPs</strong></td>
                        <td>IP addresses ready for assignment to routers</td>
                        <td>Track available network resources</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-lock text-yellow-500 mr-2"></i>Locked IPs</strong></td>
                        <td>IP addresses temporarily reserved for specific purposes</td>
                        <td>Prevent conflicts during configuration</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-link text-blue-500 mr-2"></i>Configured IPs</strong></td>
                        <td>IP addresses currently bound to router configurations</td>
                        <td>Track active network assignments</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-router text-purple-500 mr-2"></i>Total Routers</strong></td>
                        <td>Total number of routers in the configuration database</td>
                        <td>Router inventory management</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Expired Locks</strong></td>
                        <td>IP locks that have exceeded their duration and need attention</td>
                        <td>Identify stale reservations for cleanup</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                <p style="font-size: 0.8125rem; color: #1e40af;">
                    <i class="fas fa-chart-pie mr-1"></i>
                    <strong>Visual Analytics:</strong> The dashboard includes pie charts showing IP status distribution, router utilization rates, and lock duration analysis. Click on any metric card to filter the detailed views.
                </p>
            </div>
        </div>
        
        <!-- IP Master Management -->
        <div class="feature-item">
            <h3>IP Master Management</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: ip_configuration.create, ip_configuration.edit, ip_configuration.delete</p>
            <p class="description mb-3">Comprehensive management of IP address inventory with full CRUD operations:</p>
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
                        <td><strong><i class="fas fa-plus text-green-500 mr-2"></i>Add IP Address</strong></td>
                        <td>Add individual IP addresses to the master database with subnet, gateway, and metadata</td>
                        <td><code>ip_configuration.create</code></td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-upload text-blue-500 mr-2"></i>Bulk IP Upload</strong></td>
                        <td>Import multiple IP addresses via CSV/Excel file with validation and error reporting</td>
                        <td><code>ip_configuration.create</code></td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-edit text-indigo-500 mr-2"></i>Edit IP Details</strong></td>
                        <td>Modify IP address information including subnet, gateway, description, and status</td>
                        <td><code>ip_configuration.edit</code></td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-trash text-red-500 mr-2"></i>Delete IP Address</strong></td>
                        <td>Remove IP addresses from the master database (only if not configured or locked)</td>
                        <td><code>ip_configuration.delete</code></td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-search text-gray-500 mr-2"></i>Search & Filter</strong></td>
                        <td>Advanced search by IP range, subnet, status, or configuration state</td>
                        <td><code>ip_configuration.view</code></td>
                    </tr>
                </tbody>
            </table>
            
            <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>IP Address Fields:</strong></p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.875rem;">
                    <div><strong>IP Address:</strong> IPv4 address (e.g., 192.168.1.100)</div>
                    <div><strong>Subnet Mask:</strong> Network subnet (e.g., 255.255.255.0)</div>
                    <div><strong>Gateway:</strong> Default gateway IP address</div>
                    <div><strong>Description:</strong> Optional description or purpose</div>
                    <div><strong>Status:</strong> Available, Locked, Configured</div>
                    <div><strong>Location:</strong> Physical or logical location reference</div>
                </div>
            </div>
            
            <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                <p style="font-size: 0.8125rem; color: #92400e;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Validation Rules:</strong> IP addresses must be valid IPv4 format, unique in the database, and within allowed ranges. Bulk uploads are validated with detailed error reporting for invalid entries.
                </p>
            </div>
        </div>
        
        <!-- Router Configuration and IP Binding -->
        <div class="feature-item">
            <h3>Router Configuration and IP Binding</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: ip_configuration.bind, ip_configuration.unbind</p>
            <p class="description mb-3">Manage router configurations and bind IP addresses to specific router instances:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Requirements</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-router text-blue-500 mr-2"></i>Add Router</strong></td>
                        <td>Register new router with model, serial number, location, and configuration details</td>
                        <td>Router must have unique serial number</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-link text-green-500 mr-2"></i>Bind IP to Router</strong></td>
                        <td>Assign available IP address to a specific router configuration</td>
                        <td>IP must be available (not locked or configured)</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-unlink text-orange-500 mr-2"></i>Unbind IP from Router</strong></td>
                        <td>Remove IP assignment from router, making IP available for reassignment</td>
                        <td>Must confirm unbinding action</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-exchange-alt text-purple-500 mr-2"></i>Transfer IP Assignment</strong></td>
                        <td>Move IP assignment from one router to another in a single operation</td>
                        <td>Target router must be available</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-cog text-indigo-500 mr-2"></i>Update Router Config</strong></td>
                        <td>Modify router details including location, model, and configuration parameters</td>
                        <td>Cannot change serial number if IP is bound</td>
                    </tr>
                </tbody>
            </table>
            
            <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                <p style="font-size: 0.8125rem; color: #166534;">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Binding Process:</strong> When binding an IP to a router, the system automatically updates the IP status to "Configured" and creates an audit trail entry. The binding includes timestamp and user information for tracking.
                </p>
            </div>
        </div>
        
        <!-- IP Locking and Unlocking Functionality -->
        <div class="feature-item">
            <h3>IP Locking and Unlocking Functionality</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: ip_configuration.lock, ip_configuration.unlock</p>
            <p class="description mb-3">Implement temporary IP reservations to prevent conflicts during configuration processes:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-lock text-yellow-500 mr-2"></i>Lock IP Address</strong></td>
                        <td>Reserve IP address temporarily for configuration work or planning</td>
                        <td>Default: 24 hours (configurable)</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-unlock text-green-500 mr-2"></i>Unlock IP Address</strong></td>
                        <td>Release IP lock manually, making the address available immediately</td>
                        <td>Immediate</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-clock text-blue-500 mr-2"></i>Extend Lock Duration</strong></td>
                        <td>Extend the lock period for an already locked IP address</td>
                        <td>Additional 24 hours (configurable)</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-history text-purple-500 mr-2"></i>Auto-Expire Locks</strong></td>
                        <td>System automatically releases expired locks based on duration</td>
                        <td>Runs every hour via cron job</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-list text-indigo-500 mr-2"></i>View Lock History</strong></td>
                        <td>Review complete history of lock/unlock actions for an IP address</td>
                        <td>Full audit trail available</td>
                    </tr>
                </tbody>
            </table>
            
            <div style="background: #fef3c7; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Lock Status Indicators:</strong></p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; font-size: 0.875rem;">
                    <div style="background: #dcfce7; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #22c55e;">
                        <strong style="color: #166534;"><i class="fas fa-check-circle mr-1"></i>Available</strong>
                        <p style="color: #14532d; font-size: 0.8125rem; margin-top: 0.25rem;">IP is free and ready for assignment or locking</p>
                    </div>
                    <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #f59e0b;">
                        <strong style="color: #92400e;"><i class="fas fa-lock mr-1"></i>Locked</strong>
                        <p style="color: #78350f; font-size: 0.8125rem; margin-top: 0.25rem;">IP is temporarily reserved with expiration time</p>
                    </div>
                    <div style="background: #dbeafe; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #3b82f6;">
                        <strong style="color: #1e40af;"><i class="fas fa-link mr-1"></i>Configured</strong>
                        <p style="color: #1e3a8a; font-size: 0.8125rem; margin-top: 0.25rem;">IP is bound to a router configuration</p>
                    </div>
                    <div style="background: #fee2e2; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid #ef4444;">
                        <strong style="color: #991b1b;"><i class="fas fa-exclamation-triangle mr-1"></i>Expired Lock</strong>
                        <p style="color: #7f1d1d; font-size: 0.8125rem; margin-top: 0.25rem;">Lock has expired and needs manual cleanup</p>
                    </div>
                </div>
            </div>
            
            <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                <p style="font-size: 0.8125rem; color: #1e40af;">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Best Practice:</strong> Use IP locks when planning network changes or during multi-step configuration processes to prevent other users from accidentally assigning the same IP address.
                </p>
            </div>
        </div>
        
        <!-- Reports and Audit History -->
        <div class="feature-item">
            <h3>Reports and Audit History Features</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: ip_configuration.reports, system.audit</p>
            <p class="description mb-3">Comprehensive reporting and audit capabilities for network resource management:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Report Type</th>
                        <th>Description</th>
                        <th>Export Formats</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-chart-bar text-indigo-500 mr-2"></i>IP Utilization Report</strong></td>
                        <td>Shows IP usage statistics, availability rates, and utilization trends over time</td>
                        <td>PDF, Excel, CSV</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-router text-blue-500 mr-2"></i>Router Configuration Report</strong></td>
                        <td>Complete list of routers with their IP bindings, locations, and configuration status</td>
                        <td>PDF, Excel, CSV</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-lock text-yellow-500 mr-2"></i>IP Lock Activity Report</strong></td>
                        <td>History of IP locking activities including duration, users, and expiration status</td>
                        <td>PDF, Excel, CSV</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-network-wired text-green-500 mr-2"></i>Subnet Analysis Report</strong></td>
                        <td>Breakdown of IP usage by subnet with availability and allocation statistics</td>
                        <td>PDF, Excel, CSV</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-history text-purple-500 mr-2"></i>Configuration Audit Trail</strong></td>
                        <td>Complete audit log of all IP configuration changes with user and timestamp details</td>
                        <td>PDF, Excel, CSV</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>Conflict Detection Report</strong></td>
                        <td>Identifies potential IP conflicts, duplicate assignments, and configuration issues</td>
                        <td>PDF, Excel, CSV</td>
                    </tr>
                </tbody>
            </table>
            
            <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Audit Trail Includes:</strong></p>
                <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                    <li>IP address additions, modifications, and deletions</li>
                    <li>Router binding and unbinding operations</li>
                    <li>IP lock and unlock activities with duration details</li>
                    <li>Bulk upload operations with success/failure counts</li>
                    <li>Configuration changes with before/after values</li>
                    <li>User information and timestamps for all actions</li>
                    <li>System-generated actions like automatic lock expiration</li>
                </ul>
            </div>
            
            <div style="background: #f0fdf4; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #22c55e;">
                <p style="font-size: 0.8125rem; color: #166534;">
                    <i class="fas fa-download mr-1"></i>
                    <strong>Export Options:</strong> All reports can be filtered by date range, IP range, router, or user. Scheduled reports can be configured to run automatically and email results to specified recipients.
                </p>
            </div>
        </div>
        
        <!-- Advanced Features -->
        <div class="feature-item">
            <h3>Advanced IP Configuration Features</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: ip_configuration.advanced</p>
            <p class="description mb-3">Advanced functionality for complex network management scenarios:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Description</th>
                        <th>Use Case</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-layer-group text-indigo-500 mr-2"></i>Subnet Management</strong></td>
                        <td>Organize IPs by subnet with automatic subnet detection and validation</td>
                        <td>Network segmentation and organization</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-copy text-blue-500 mr-2"></i>IP Range Operations</strong></td>
                        <td>Bulk operations on IP ranges including mass lock/unlock and status changes</td>
                        <td>Large-scale network maintenance</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-search-plus text-green-500 mr-2"></i>IP Discovery</strong></td>
                        <td>Scan network ranges to discover and import existing IP configurations</td>
                        <td>Network inventory and migration</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-shield-alt text-yellow-500 mr-2"></i>Conflict Prevention</strong></td>
                        <td>Automatic validation to prevent duplicate IP assignments and conflicts</td>
                        <td>Network integrity and reliability</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-calendar-alt text-purple-500 mr-2"></i>Scheduled Operations</strong></td>
                        <td>Schedule IP operations like lock expiration cleanup and status updates</td>
                        <td>Automated network maintenance</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- IP Configuration Notifications -->
        <div class="feature-item">
            <h3>IP Configuration Notifications</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="description mb-3">Stay informed about IP configuration activities through system notifications:</p>
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
                        <td><strong>IP Lock Expiring</strong></td>
                        <td>Lock Creator, IP Admins</td>
                        <td>Alert when IP locks are approaching expiration (24 hours before)</td>
                    </tr>
                    <tr>
                        <td><strong>IP Lock Expired</strong></td>
                        <td>IP Admins</td>
                        <td>Notification when IP locks have expired and need cleanup</td>
                    </tr>
                    <tr>
                        <td><strong>IP Conflict Detected</strong></td>
                        <td>IP Admins, System Admins</td>
                        <td>Alert when potential IP conflicts or duplicates are detected</td>
                    </tr>
                    <tr>
                        <td><strong>Bulk Upload Completed</strong></td>
                        <td>Upload Initiator</td>
                        <td>Summary of bulk IP upload results with success/failure counts</td>
                    </tr>
                    <tr>
                        <td><strong>Router Configuration Changed</strong></td>
                        <td>IP Admins</td>
                        <td>Notification when router IP bindings are modified</td>
                    </tr>
                    <tr>
                        <td><strong>Subnet Utilization High</strong></td>
                        <td>Network Admins</td>
                        <td>Alert when subnet utilization exceeds threshold (90%)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    '
];