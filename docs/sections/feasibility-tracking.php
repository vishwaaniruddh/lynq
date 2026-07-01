<?php
/**
 * Feasibility Tracking Documentation Section
 * Requirements: 8.1, 8.2, 8.3, 8.4, 13.1, 13.2 - Feasibility Tracking module documentation
 */

return [
    'id' => 'feasibility-tracking',
    'title' => 'Feasibility Tracking',
    'icon' => 'fas fa-clipboard-list',
    'content' => '
        <p class="description mb-4">The Feasibility Tracking module provides comprehensive monitoring and management of feasibility assessments conducted by engineers at delegated sites. This module enables ADV users to track progress, review submissions, and monitor ETA/ADA metrics.</p>
        
        <!-- Tracking Dashboard -->
        <div class="feature-item">
            <h3>Tracking Dashboard and Metrics</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: feasibility.view</p>
            <p class="description mb-3">Comprehensive overview with key performance indicators:</p>
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
                        <td><strong>Total Feasibilities</strong></td>
                        <td>Total number of feasibility checks in system</td>
                        <td>Overall volume tracking</td>
                    </tr>
                    <tr>
                        <td><strong>Pending Submission</strong></td>
                        <td>Assigned but not yet submitted by engineers</td>
                        <td>Identify delayed submissions</td>
                    </tr>
                    <tr>
                        <td><strong>Pending Review</strong></td>
                        <td>Submitted and awaiting contractor review</td>
                        <td>Track contractor review backlog</td>
                    </tr>
                    <tr>
                        <td><strong>Pending Final Approval</strong></td>
                        <td>Contractor approved, awaiting ADV approval</td>
                        <td>ADV approval queue</td>
                    </tr>
                    <tr>
                        <td><strong>Approved</strong></td>
                        <td>Fully approved by ADV</td>
                        <td>Ready for installation phase</td>
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
            <p class="permission-ref">Permission: feasibility.approve, feasibility.reject</p>
            <p class="description mb-3">ADV review and approval process for feasibility submissions:</p>
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
                        <td><strong>Review Submission</strong></td>
                        <td>Examine feasibility data and documentation</td>
                        <td>Technical details, images, measurements, contractor remarks</td>
                    </tr>
                    <tr>
                        <td><strong>Approve Feasibility</strong></td>
                        <td>Grant final approval for installation phase</td>
                        <td>Approval remarks, next phase instructions</td>
                    </tr>
                    <tr>
                        <td><strong>Reject Feasibility</strong></td>
                        <td>Return to contractor with feedback</td>
                        <td>Rejection reason, required corrections, resubmission instructions</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- ETA and ADA Tracking -->
        <div class="feature-item">
            <h3>ETA and ADA Tracking</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: feasibility.view</p>
            <p class="description mb-3">Monitor estimated vs actual completion timelines:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                <div style="background: #dbeafe; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #3b82f6;">
                    <strong style="color: #1e40af;"><i class="fas fa-calendar-alt mr-2"></i>ETA (Estimated Time of Arrival)</strong>
                    <p style="font-size: 0.8125rem; color: #1e3a8a; margin-top: 0.5rem;">
                        Projected completion date based on site complexity and resource availability
                    </p>
                </div>
                <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #22c55e;">
                    <strong style="color: #166534;"><i class="fas fa-check-circle mr-2"></i>ADA (Actual Date of Arrival)</strong>
                    <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.5rem;">
                        Actual completion date when feasibility is submitted and approved
                    </p>
                </div>
            </div>
            <p class="description mt-3">Performance metrics include variance analysis, on-time completion rates, and trend analysis for continuous improvement.</p>
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
            <p class="description mb-3">Export feasibility data for reporting and analysis:</p>
            <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                <li><strong>CSV Export</strong> - Download feasibility data in CSV format</li>
                <li><strong>Excel Export</strong> - Formatted Excel files with charts and pivot tables</li>
                <li><strong>Filtered Export</strong> - Export only filtered/visible records</li>
                <li><strong>Performance Reports</strong> - ETA vs ADA analysis with variance metrics</li>
                <li><strong>Status Reports</strong> - Breakdown by status with timeline analysis</li>
            </ul>
        </div>
    '
];