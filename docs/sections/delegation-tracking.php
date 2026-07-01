<?php
/**
 * Delegation Tracking Documentation Section
 * Requirements: 7.1, 7.2, 7.3, 13.1, 13.2 - Delegation Tracking module documentation
 */

return [
    'id' => 'delegation-tracking',
    'title' => 'Delegation Tracking',
    'icon' => 'fas fa-tasks',
    'content' => '
        <p class="description mb-4">The Delegation Tracking module provides comprehensive visibility into site delegations from ADV to contractors. This module allows you to monitor delegation statuses, track acceptance and rejection rates, and maintain a complete history of all delegation activities.</p>
        
        <!-- All Delegations View -->
        <div class="feature-item">
            <h3>All Delegations View and Filtering Options</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: delegations.view</p>
            <p class="description mb-3">Comprehensive delegation management with advanced filtering:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Filter Type</th>
                        <th>Options</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Status Filter</strong></td>
                        <td>All, Pending, Accepted, Rejected</td>
                        <td>Filter by current delegation status</td>
                    </tr>
                    <tr>
                        <td><strong>Contractor Filter</strong></td>
                        <td>Dropdown of all contractors</td>
                        <td>Show delegations for specific contractor</td>
                    </tr>
                    <tr>
                        <td><strong>Date Range</strong></td>
                        <td>From Date, To Date</td>
                        <td>Filter by delegation creation date</td>
                    </tr>
                    <tr>
                        <td><strong>Search</strong></td>
                        <td>Site ID, Site Name, Contractor</td>
                        <td>Free text search across key fields</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Delegation Statuses -->
        <div class="feature-item">
            <h3>Delegation Statuses (Pending, Accepted, Rejected)</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
                <span class="badge badge-engineer">Engineer</span>
            </div>
            <p class="permission-ref">Permission: delegations.view</p>
            <p class="description mb-3">Understanding delegation workflow and status transitions:</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
                <div style="background: #fef3c7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                    <strong style="color: #92400e;"><i class="fas fa-clock mr-2"></i>Pending</strong>
                    <p style="font-size: 0.8125rem; color: #78350f; margin-top: 0.5rem;">
                        Delegation created by ADV, awaiting contractor response. Contractor can accept or reject.
                    </p>
                </div>
                <div style="background: #dcfce7; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #22c55e;">
                    <strong style="color: #166534;"><i class="fas fa-check-circle mr-2"></i>Accepted</strong>
                    <p style="font-size: 0.8125rem; color: #14532d; margin-top: 0.5rem;">
                        Contractor accepted delegation. Site is now under contractor responsibility for engineer assignment.
                    </p>
                </div>
                <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #ef4444;">
                    <strong style="color: #991b1b;"><i class="fas fa-times-circle mr-2"></i>Rejected</strong>
                    <p style="font-size: 0.8125rem; color: #7f1d1d; margin-top: 0.5rem;">
                        Contractor declined delegation. Site remains unassigned and can be re-delegated.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Delegation History -->
        <div class="feature-item">
            <h3>Delegation History Feature</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: delegations.view</p>
            <p class="description mb-3">Complete audit trail of delegation activities:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Information</th>
                        <th>Description</th>
                        <th>Details Tracked</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Action Timeline</strong></td>
                        <td>Chronological list of all delegation actions</td>
                        <td>Creation, acceptance, rejection, re-delegation</td>
                    </tr>
                    <tr>
                        <td><strong>User Information</strong></td>
                        <td>Who performed each action</td>
                        <td>ADV user for delegation, contractor user for response</td>
                    </tr>
                    <tr>
                        <td><strong>Timestamps</strong></td>
                        <td>Exact date and time of each action</td>
                        <td>Creation time, response time, duration metrics</td>
                    </tr>
                    <tr>
                        <td><strong>Remarks</strong></td>
                        <td>Comments added during delegation or response</td>
                        <td>Delegation notes, rejection reasons, special instructions</td>
                    </tr>
                </tbody>
            </table>
        </div>
    '
];