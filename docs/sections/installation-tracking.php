<?php
/**
 * Installation Tracking Documentation Section
 * Requirements: 9.1, 9.2, 9.3, 9.4, 13.1, 13.2 - Installation Tracking module documentation
 */

return [
    'id' => 'installation-tracking',
    'title' => 'Installation Tracking',
    'icon' => 'fas fa-tools',
    'content' => '
        <p class="description mb-4">The Installation Tracking module provides comprehensive monitoring and management of installation activities conducted by engineers at approved feasibility sites. This module enables ADV users to track installation progress, review completed work, and manage the final approval process.</p>
        
        <!-- Tracking Dashboard -->
        <div class="feature-item">
            <h3>Tracking Dashboard and Installation Statuses</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: installation.view</p>
            <p class="description mb-3">Comprehensive dashboard with installation status breakdown:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Next Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Not Started</strong></td>
                        <td>Installation assigned but work not yet begun</td>
                        <td>Engineer initiates installation</td>
                    </tr>
                    <tr>
                        <td><strong>In Progress</strong></td>
                        <td>Engineer actively working on installation</td>
                        <td>Continue installation work</td>
                    </tr>
                    <tr>
                        <td><strong>Pending Submission</strong></td>
                        <td>Work completed, awaiting engineer submission</td>
                        <td>Engineer submits for review</td>
                    </tr>
                    <tr>
                        <td><strong>Pending Review</strong></td>
                        <td>Submitted, awaiting contractor review</td>
                        <td>Contractor reviews and approves</td>
                    </tr>
                    <tr>
                        <td><strong>Pending Final Approval</strong></td>
                        <td>Contractor approved, awaiting ADV approval</td>
                        <td>ADV final approval</td>
                    </tr>
                    <tr>
                        <td><strong>Completed</strong></td>
                        <td>ADV approved, installation complete</td>
                        <td>Site ready for service</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- All Installations List -->
        <div class="feature-item">
            <h3>All Installations List and Filtering</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: installation.view</p>
            <p class="description mb-3">Comprehensive installation list with advanced filtering options:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Filter Type</th>
                        <th>Options</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Status Filter</strong></td>
                        <td>All statuses, specific status selection</td>
                        <td>Focus on installations in specific phases</td>
                    </tr>
                    <tr>
                        <td><strong>Contractor Filter</strong></td>
                        <td>All contractors, specific contractor</td>
                        <td>Monitor contractor performance</td>
                    </tr>
                    <tr>
                        <td><strong>Engineer Filter</strong></td>
                        <td>All engineers, specific engineer</td>
                        <td>Track individual engineer workload</td>
                    </tr>
                    <tr>
                        <td><strong>Date Range</strong></td>
                        <td>Start date, end date, date type</td>
                        <td>Analyze installations by time period</td>
                    </tr>
                    <tr>
                        <td><strong>Location Filter</strong></td>
                        <td>State, city, zone selection</td>
                        <td>Geographic analysis and reporting</td>
                    </tr>
                </tbody>
            </table>
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
            <p class="description mb-3">ADV final approval process for completed installations:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Required Information</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Review Installation</strong></td>
                        <td>Examine complete installation documentation</td>
                        <td>Images, material receipts, checkpoint verifications, contractor remarks</td>
                    </tr>
                    <tr>
                        <td><strong>Approve Installation</strong></td>
                        <td>Grant final approval, mark as completed</td>
                        <td>Approval remarks, completion certification</td>
                    </tr>
                    <tr>
                        <td><strong>Reject Installation</strong></td>
                        <td>Return to contractor with detailed feedback</td>
                        <td>Rejection reason, required corrections, quality standards</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Installation Review Process -->
        <div class="feature-item">
            <h3>Installation Review and Approval Process</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: installation.view, installation.approve</p>
            <p class="description mb-3">Systematic review process ensuring quality and completeness:</p>
            <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Review Checklist:</strong></p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.875rem;">
                    <div><i class="fas fa-check text-green-500 mr-1"></i>Section Completion</div>
                    <div><i class="fas fa-check text-green-500 mr-1"></i>Image Documentation</div>
                    <div><i class="fas fa-check text-green-500 mr-1"></i>Material Receipts</div>
                    <div><i class="fas fa-check text-green-500 mr-1"></i>Checkpoint Verification</div>
                    <div><i class="fas fa-check text-green-500 mr-1"></i>Technical Compliance</div>
                    <div><i class="fas fa-check text-green-500 mr-1"></i>Documentation Quality</div>
                </div>
            </div>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Review Stage</th>
                        <th>Reviewer</th>
                        <th>Focus Areas</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Initial Review</strong></td>
                        <td>Contractor</td>
                        <td>Completeness, basic quality, material verification</td>
                    </tr>
                    <tr>
                        <td><strong>Technical Review</strong></td>
                        <td>ADV Manager/Admin</td>
                        <td>Technical compliance, quality standards, documentation</td>
                    </tr>
                    <tr>
                        <td><strong>Quality Assurance</strong></td>
                        <td>ADV Superadmin/Admin</td>
                        <td>Overall quality, compliance verification, audit trail</td>
                    </tr>
                </tbody>
            </table>
        </div>
    '
];