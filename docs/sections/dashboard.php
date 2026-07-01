<?php
/**
 * Dashboard Documentation Section
 * Requirements: 3.1, 3.2, 3.3, 3.4, 13.1 - Dashboard module documentation
 */

return [
    'id' => 'dashboard',
    'title' => 'Dashboard',
    'icon' => 'fas fa-home',
    'content' => '
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
    '
];