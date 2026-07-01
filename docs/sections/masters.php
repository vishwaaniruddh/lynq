<?php
/**
 * Masters Documentation Section
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 13.1, 13.2 - Masters module documentation
 */

return [
    'id' => 'masters',
    'title' => 'Masters',
    'icon' => 'fas fa-database',
    'content' => '
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
    '
];