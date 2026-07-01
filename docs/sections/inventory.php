<?php
/**
 * Inventory Documentation Section
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 13.1, 13.2 - Inventory module documentation
 */

return [
    'id' => 'inventory',
    'title' => 'Inventory',
    'icon' => 'fas fa-boxes',
    'content' => '
        <p class="description mb-4">The Inventory module provides comprehensive management of products, assets, warehouses, stock operations, dispatches, transfers, and repairs. This module is essential for tracking physical resources, managing stock levels, coordinating dispatches to sites, and maintaining asset lifecycle information throughout the ADV CRM system.</p>
        
        <!-- ADV Dashboard -->
        <div class="feature-item">
            <h3>ADV Dashboard and Statistics</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: inventory.view</p>
            <p class="description mb-3">The Inventory Dashboard provides comprehensive oversight of all inventory operations with key performance indicators:</p>
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
                        <td><strong><i class="fas fa-warehouse text-indigo-500 mr-2"></i>Total Warehouses</strong></td>
                        <td>Number of active warehouses in the system</td>
                        <td>Track storage facility capacity</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-box text-blue-500 mr-2"></i>Total Products</strong></td>
                        <td>Number of product types in inventory</td>
                        <td>Monitor product catalog size</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-microchip text-green-500 mr-2"></i>Total Assets</strong></td>
                        <td>Individual asset items tracked in system</td>
                        <td>Asset inventory management</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-truck text-orange-500 mr-2"></i>Active Dispatches</strong></td>
                        <td>Dispatches currently in transit or pending</td>
                        <td>Monitor logistics operations</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-exchange-alt text-purple-500 mr-2"></i>Pending Transfers</strong></td>
                        <td>Inter-warehouse transfers awaiting completion</td>
                        <td>Track internal movements</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-tools text-red-500 mr-2"></i>Items Under Repair</strong></td>
                        <td>Assets currently being repaired</td>
                        <td>Maintenance tracking</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>Low Stock Alerts</strong></td>
                        <td>Products below minimum stock threshold</td>
                        <td>Prevent stockouts</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #eff6ff; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #3b82f6;">
                <p style="font-size: 0.8125rem; color: #1e40af;">
                    <i class="fas fa-chart-line mr-1"></i>
                    <strong>Visual Analytics:</strong> The dashboard includes charts showing stock levels by warehouse, asset status distribution, dispatch trends, and repair cycle analysis.
                </p>
            </div>
        </div>
        
        <!-- Warehouse Management -->
        <div class="feature-item">
            <h3>Warehouse Management</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: inventory.warehouses.create, inventory.warehouses.edit, inventory.warehouses.activate</p>
            <p class="description mb-3">Comprehensive warehouse facility management with location and capacity tracking:</p>
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
                        <td><strong><i class="fas fa-plus text-green-500 mr-2"></i>Create Warehouse</strong></td>
                        <td>Add new warehouse with name, location, capacity, and contact details</td>
                        <td><code>inventory.warehouses.create</code></td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-edit text-blue-500 mr-2"></i>Edit Warehouse</strong></td>
                        <td>Modify warehouse information including location, capacity, and manager details</td>
                        <td><code>inventory.warehouses.edit</code></td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-toggle-on text-indigo-500 mr-2"></i>Activate/Deactivate</strong></td>
                        <td>Control warehouse operational status for stock operations</td>
                        <td><code>inventory.warehouses.activate</code></td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-chart-bar text-purple-500 mr-2"></i>View Capacity</strong></td>
                        <td>Monitor warehouse utilization and available space</td>
                        <td><code>inventory.warehouses.view</code></td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #fef3c7; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; border-left: 3px solid #f59e0b;">
                <p style="font-size: 0.8125rem; color: #92400e;">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Note:</strong> Deactivating a warehouse prevents new stock entries and dispatches but preserves existing inventory data. Reactivation restores full functionality.
                </p>
            </div>
        </div>
        
        <!-- Product Category and Product Management -->
        <div class="feature-item">
            <h3>Product Category and Product Management</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
            </div>
            <p class="permission-ref">Permission: inventory.products.create, inventory.products.edit, inventory.categories.manage</p>
            <p class="description mb-3">Organize and manage product catalog with hierarchical categorization:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Component</th>
                        <th>Description</th>
                        <th>Key Features</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-tags text-indigo-500 mr-2"></i>Product Categories</strong></td>
                        <td>Hierarchical classification system for products</td>
                        <td>Create, edit, delete categories; nested structure support</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-box text-blue-500 mr-2"></i>Product Management</strong></td>
                        <td>Individual product definitions with specifications</td>
                        <td>SKU, name, description, category assignment, specifications</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-barcode text-green-500 mr-2"></i>SKU Management</strong></td>
                        <td>Unique product identification and tracking</td>
                        <td>Auto-generation, validation, duplicate prevention</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-image text-purple-500 mr-2"></i>Product Images</strong></td>
                        <td>Visual product documentation and identification</td>
                        <td>Multiple image upload, thumbnail generation, gallery view</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Stock Entry Operations -->
        <div class="feature-item">
            <h3>Stock Entry Operations</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: inventory.stock.create, inventory.stock.edit</p>
            <p class="description mb-3">Manage stock levels and inventory movements with comprehensive tracking:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Operation</th>
                        <th>Description</th>
                        <th>Tracking Information</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-plus-circle text-green-500 mr-2"></i>Stock In</strong></td>
                        <td>Add inventory to warehouse from suppliers or returns</td>
                        <td>Quantity, supplier, purchase order, receipt date, cost</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-minus-circle text-red-500 mr-2"></i>Stock Out</strong></td>
                        <td>Remove inventory for dispatches, damage, or other reasons</td>
                        <td>Quantity, reason, destination, authorization, date</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-adjust text-blue-500 mr-2"></i>Stock Adjustment</strong></td>
                        <td>Correct inventory levels based on physical counts</td>
                        <td>Previous quantity, new quantity, reason, approver</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-history text-purple-500 mr-2"></i>Stock History</strong></td>
                        <td>Complete audit trail of all stock movements</td>
                        <td>Transaction type, user, timestamp, quantities, references</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Dispatch Workflow -->
        <div class="feature-item">
            <h3>Dispatch Workflow</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: inventory.dispatch.create, inventory.dispatch.track, inventory.dispatch.deliver</p>
            <p class="description mb-3">End-to-end dispatch management from creation to delivery confirmation:</p>
            
            <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                <p style="font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem;"><strong>Dispatch Lifecycle:</strong></p>
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; font-size: 0.875rem;">
                    <span style="background: #fef3c7; color: #92400e; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-plus mr-1"></i>Created</span>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                    <span style="background: #dbeafe; color: #1e40af; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-truck mr-1"></i>In Transit</span>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                    <span style="background: #dcfce7; color: #166534; padding: 0.375rem 0.75rem; border-radius: 0.375rem;"><i class="fas fa-check mr-1"></i>Delivered</span>
                </div>
            </div>
            
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Description</th>
                        <th>Required Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Create Dispatch</strong></td>
                        <td>Initiate dispatch with items, destination, and shipping details</td>
                        <td>Select items, specify quantities, choose destination site, assign courier</td>
                    </tr>
                    <tr>
                        <td><strong>Track Dispatch</strong></td>
                        <td>Monitor dispatch progress and update status</td>
                        <td>Update tracking information, manage transit status, handle exceptions</td>
                    </tr>
                    <tr>
                        <td><strong>Deliver Dispatch</strong></td>
                        <td>Confirm delivery and update inventory status</td>
                        <td>Delivery confirmation, recipient signature, update asset status</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Transfer Operations -->
        <div class="feature-item">
            <h3>Transfer Operations Between Warehouses</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: inventory.transfer.create, inventory.transfer.approve</p>
            <p class="description mb-3">Manage inventory movements between warehouse locations:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Approval Required</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-exchange-alt text-blue-500 mr-2"></i>Create Transfer</strong></td>
                        <td>Initiate transfer request between warehouses</td>
                        <td>Manager approval for high-value items</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-check-circle text-green-500 mr-2"></i>Approve Transfer</strong></td>
                        <td>Review and approve transfer requests</td>
                        <td>Admin/Manager approval required</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-truck text-orange-500 mr-2"></i>Execute Transfer</strong></td>
                        <td>Physical movement and inventory updates</td>
                        <td>Confirmation from both warehouses</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-clipboard-check text-purple-500 mr-2"></i>Receive Transfer</strong></td>
                        <td>Confirm receipt and update destination inventory</td>
                        <td>Receiving warehouse confirmation</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Asset Management and Tracking -->
        <div class="feature-item">
            <h3>Asset Management and Tracking</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: inventory.assets.create, inventory.assets.track</p>
            <p class="description mb-3">Individual asset lifecycle management with detailed tracking:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Description</th>
                        <th>Tracking Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-microchip text-indigo-500 mr-2"></i>Asset Registration</strong></td>
                        <td>Create individual asset records with unique identifiers</td>
                        <td>Serial number, model, specifications, purchase info</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>Location Tracking</strong></td>
                        <td>Monitor current asset location and movement history</td>
                        <td>Current location, movement log, custody chain</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-heartbeat text-green-500 mr-2"></i>Status Management</strong></td>
                        <td>Track asset condition and operational status</td>
                        <td>Working/Not Working, In Stock/Dispatched/Assigned</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-calendar-alt text-purple-500 mr-2"></i>Lifecycle Events</strong></td>
                        <td>Record major asset lifecycle milestones</td>
                        <td>Purchase, deployment, maintenance, retirement</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Item History Tracking -->
        <div class="feature-item">
            <h3>Item History Tracking</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
                <span class="badge badge-engineer">Engineer</span>
            </div>
            <p class="permission-ref">Permission: inventory.history.view</p>
            <p class="description mb-3">Comprehensive audit trail for all inventory items and assets:</p>
            <ul style="font-size: 0.875rem; color: #4b5563; margin-left: 1rem; list-style-type: disc;">
                <li><strong>Movement History</strong> - Complete log of location changes and transfers</li>
                <li><strong>Status Changes</strong> - Record of all status updates with timestamps and reasons</li>
                <li><strong>Dispatch Records</strong> - History of all dispatches involving the item</li>
                <li><strong>Maintenance Log</strong> - Repair and maintenance activities performed</li>
                <li><strong>User Actions</strong> - Who performed each action with full audit trail</li>
                <li><strong>Document Attachments</strong> - Associated documents, images, and certificates</li>
            </ul>
        </div>
        
        <!-- Repairs Management -->
        <div class="feature-item">
            <h3>Repairs Management</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: inventory.repairs.create, inventory.repairs.manage</p>
            <p class="description mb-3">Manage asset repair lifecycle from initiation to completion:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Description</th>
                        <th>Information Tracked</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>Repair Request</strong></td>
                        <td>Initiate repair for damaged or malfunctioning assets</td>
                        <td>Issue description, priority, estimated cost, photos</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-tools text-blue-500 mr-2"></i>In Repair</strong></td>
                        <td>Asset is being repaired by technician or vendor</td>
                        <td>Repair vendor, progress updates, parts used, labor hours</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-check-circle text-green-500 mr-2"></i>Repair Complete</strong></td>
                        <td>Repair finished and asset ready for deployment</td>
                        <td>Final cost, completion date, warranty info, test results</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-times-circle text-gray-500 mr-2"></i>Beyond Repair</strong></td>
                        <td>Asset cannot be economically repaired</td>
                        <td>Scrap decision, disposal method, salvage value</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Material Masters and Material Requests -->
        <div class="feature-item">
            <h3>Material Masters and Material Requests</h3>
            <div class="role-badges">
                <span class="badge badge-superadmin">Superadmin</span>
                <span class="badge badge-admin">Admin</span>
                <span class="badge badge-manager">Manager</span>
            </div>
            <p class="permission-ref">Permission: inventory.materials.create, inventory.requests.manage</p>
            <p class="description mb-3">Standardized material management and procurement workflow:</p>
            <table class="role-table">
                <thead>
                    <tr>
                        <th>Component</th>
                        <th>Description</th>
                        <th>Key Features</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><i class="fas fa-list text-indigo-500 mr-2"></i>Material Masters</strong></td>
                        <td>Standardized material definitions and specifications</td>
                        <td>Material codes, descriptions, units, specifications, suppliers</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-shopping-cart text-blue-500 mr-2"></i>Material Requests</strong></td>
                        <td>Formal requests for materials needed for projects</td>
                        <td>Request creation, approval workflow, procurement tracking</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-check text-green-500 mr-2"></i>Request Approval</strong></td>
                        <td>Multi-level approval process for material requests</td>
                        <td>Budget approval, technical approval, procurement authorization</td>
                    </tr>
                    <tr>
                        <td><strong><i class="fas fa-truck text-orange-500 mr-2"></i>Procurement Tracking</strong></td>
                        <td>Track material requests through procurement process</td>
                        <td>Purchase orders, delivery tracking, receipt confirmation</td>
                    </tr>
                </tbody>
            </table>
        </div>
    '
];