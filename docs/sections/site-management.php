<?php
/**
 * Site Management Documentation Section
 * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 13.1, 13.2 - Site Management module documentation
 */

return [
    'id' => 'site-management',
    'title' => 'Site Management',
    'icon' => 'fas fa-map-marker-alt',
    'content' => '
        <p class="description mb-4">The Site Management module provides comprehensive tools for creating, managing, and delegating sites within the ADV CRM system. This module enables ADV users to maintain site information, perform bulk operations, delegate sites to contractors, and manage site status throughout the operational lifecycle.</p>
        
        <!-- Site Creation and Editing -->
        <div class="feature-item">
            <h3>Site Creation and Editing</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: sites.create, sites.edit</p>
            <p class="description mb-3">Create and manage individual site records with comprehensive information:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Field Category</th>
                        <th>Required Fields</th>
                        <th>Optional Fields</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Basic Information</strong></td>
                        <td>Site ID, Site Name, Customer</td>
                        <td>Site Description, Reference Number</td>
                    </tr>
                    <tr>
                        <td><strong>Location Details</strong></td>
                        <td>Country, State, City, Address</td>
                        <td>Zone, LHO, PIN Code, GPS Coordinates</td>
                    </tr>
                    <tr>
                        <td><strong>Contact Information</strong></td>
                        <td>Primary Contact Name, Phone</td>
                        <td>Secondary Contact, Email, Alternate Phone</td>
                    </tr>
                    <tr>
                        <td><strong>Technical Details</strong></td>
                        <td>Site Type, Service Category</td>
                        <td>Equipment Requirements, Special Instructions</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Bulk Upload Functionality -->
        <div class="feature-item">
            <h3>Bulk Upload Functionality</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: sites.bulk.upload</p>
            <p class="description mb-3">Efficiently import multiple sites using CSV or Excel files:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Description</th>
                        <th>Validation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Template Download</strong></td>
                        <td>Download standardized CSV/Excel template with required columns</td>
                        <td>Pre-formatted with validation rules</td>
                    </tr>
                    <tr>
                        <td><strong>Data Validation</strong></td>
                        <td>Comprehensive validation of all fields before import</td>
                        <td>Duplicate detection, format validation, reference checks</td>
                    </tr>
                    <tr>
                        <td><strong>Error Reporting</strong></td>
                        <td>Detailed error report for invalid or duplicate records</td>
                        <td>Line-by-line error descriptions with suggestions</td>
                    </tr>
                    <tr>
                        <td><strong>Preview and Confirm</strong></td>
                        <td>Preview import results before final confirmation</td>
                        <td>Summary of successful and failed imports</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Site Delegation Workflow -->
        <div class="feature-item">
            <h3>Site Delegation Workflow (ADV to Contractor)</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: sites.delegate</p>
            <p class="description mb-3">Delegate sites to contractor companies for execution:</p>
            <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Delegation Process:</strong></p>
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                    <span style="background: #dbeafe; color: #1e40af; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-plus mr-1"></i>Create</span>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                    <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-clock mr-1"></i>Pending</span>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                    <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-check mr-1"></i>Accepted</span>
                </div>
            </div>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Step</th>
                        <th>Action</th>
                        <th>Responsible Party</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>1. Site Selection</strong></td>
                        <td>Select sites for delegation with contractor assignment</td>
                        <td>ADV User</td>
                    </tr>
                    <tr>
                        <td><strong>2. Contractor Assignment</strong></td>
                        <td>Choose contractor company and add delegation notes</td>
                        <td>ADV User</td>
                    </tr>
                    <tr>
                        <td><strong>3. Delegation Creation</strong></td>
                        <td>Create delegation record with timestamp and details</td>
                        <td>System</td>
                    </tr>
                    <tr>
                        <td><strong>4. Contractor Response</strong></td>
                        <td>Accept or reject delegation with optional remarks</td>
                        <td>Contractor</td>
                    </tr>
                    <tr>
                        <td><strong>5. Engineer Assignment</strong></td>
                        <td>Assign engineers to accepted sites for execution</td>
                        <td>Contractor</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Bulk Delegation Functionality -->
        <div class="feature-item">
            <h3>Bulk Delegation Functionality</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: sites.bulk.delegate</p>
            <p class="description mb-3">Efficiently delegate multiple sites to contractors simultaneously:</p>
            <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                <li><strong>Multi-Site Selection</strong> - Select multiple sites using checkboxes or filters</li>
                <li><strong>Contractor Assignment</strong> - Assign all selected sites to a single contractor</li>
                <li><strong>Batch Processing</strong> - Process all delegations in a single operation</li>
                <li><strong>Progress Tracking</strong> - Monitor bulk delegation progress with success/failure counts</li>
                <li><strong>Error Handling</strong> - Detailed reporting of any failed delegations with reasons</li>
            </ul>
        </div>
        
        <!-- Site Status Management -->
        <div class="feature-item">
            <h3>Site Status Management (Active/Inactive)</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: sites.status.manage</p>
            <p class="description mb-3">Control site operational status and visibility:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #22c55e;">
                    <strong style="color: #166534;"><i class="fas fa-check-circle mr-2"></i>Active Status</strong>
                    <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.5rem;">
                        Site is operational and available for:
                    </p>
                    <ul style="font-size: 0.75rem; color: #166534; margin-top: 0.5rem; margin-left: 1rem;">
                        <li>Delegation to contractors</li>
                        <li>Engineer assignments</li>
                        <li>Feasibility and installation work</li>
                        <li>Reporting and analytics</li>
                    </ul>
                </div>
                <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #ef4444;">
                    <strong style="color: #991b1b;"><i class="fas fa-times-circle mr-2"></i>Inactive Status</strong>
                    <p style="font-size: 0.8125rem; color: #7f1d1d; margin-top: 0.5rem;">
                        Site is temporarily disabled:
                    </p>
                    <ul style="font-size: 0.75rem; color: #991b1b; margin-top: 0.5rem; margin-left: 1rem;">
                        <li>Hidden from contractor views</li>
                        <li>Existing delegations remain but are paused</li>
                        <li>No new work can be initiated</li>
                        <li>Historical data is preserved</li>
                    </ul>
                </div>
            </div>
        </div>
    '
];