<?php
/**
 * Contractor Dispatch Page
 * 
 * Allows contractors to dispatch materials to engineers or return to ADV
 * Includes:
 * - Destination selector showing engineers and ADV
 * - Product/quantity selector from contractor's inventory
 * - Inventory validation feedback
 * - Available quantities display
 * - Prevention of dispatching unavailable items
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
 */

require_once __DIR__ . '/../../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../../views/auth/login.php');
    exit;
}

// Check contractor access
if (!isContractorUser()) {
    $_SESSION['flash_error'] = 'Access denied. Contractor users only.';
    header('Location: ../../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '../..';
$pageTitle = 'Dispatch Materials';
$currentPage = 'contractor_dispatch';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../../contractor/dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Dispatch Materials']
];

ob_start();
?>

<div class="max-w-7xl mx-auto space-y-6">

    <!-- VIEW 1: All Dispatch Records View (Default) -->
    <div id="dispatch-list-view" class="space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Dispatch Management</h3>
                        <p class="text-sm text-gray-500">Track and manage all material dispatches to engineers or returns to ADV warehouse</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="showCreateView()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center shadow-sm font-semibold text-sm">
                            <i class="fas fa-plus mr-2"></i>Create New Dispatch
                        </button>
                        <a href="stocks.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center text-sm font-medium">
                            <i class="fas fa-boxes mr-2"></i>View My Stocks
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-lg">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Total Dispatches</p>
                    <p id="stat-total-dispatches" class="text-xl font-bold text-gray-800 mt-0.5">0</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 bg-blue-50 text-blue-700 rounded-xl flex items-center justify-center text-lg">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">In Transit</p>
                    <p id="stat-in-transit" class="text-xl font-bold text-blue-700 mt-0.5">0</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center text-lg">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Delivered</p>
                    <p id="stat-delivered" class="text-xl font-bold text-emerald-600 mt-0.5">0</p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center text-lg">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Pending Ack</p>
                    <p id="stat-pending-ack" class="text-xl font-bold text-amber-600 mt-0.5">0</p>
                </div>
            </div>
        </div>

        <!-- All Dispatch Records Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-lg font-bold text-gray-800">All Dispatch Records</h3>
                        <span id="contractor-dispatches-badge" class="px-2.5 py-0.5 bg-blue-100 text-primary font-bold text-xs rounded-full">0 Records</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">Historical record of all dispatches sent to field engineers and returns to ADV</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="loadContractorDispatches()" class="px-3.5 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-xs font-semibold transition flex items-center">
                        <i class="fas fa-sync-alt mr-1.5"></i>Refresh
                    </button>
                    <button type="button" onclick="showCreateView()" class="px-3.5 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 text-xs font-semibold transition flex items-center shadow-sm">
                        <i class="fas fa-plus mr-1.5"></i>New Dispatch
                    </button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="p-4 border-b bg-gray-50/80">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3.5 top-3 text-gray-400 text-xs"></i>
                            <input type="text" id="dispatch-search-input" placeholder="Search by dispatch #, receiver, site name, POD..." 
                                class="w-full pl-9 pr-4 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <select id="dispatch-status-filter" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-primary">
                            <option value="">All Statuses</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="pending">Pending</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <select id="dispatch-ack-filter" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-primary">
                            <option value="">All Acknowledgment</option>
                            <option value="acknowledged">Acknowledged</option>
                            <option value="pending">Pending Ack</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="w-10 px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Dispatch Info</th>
                            <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Destination & Site</th>
                            <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Items Dispatched</th>
                            <th class="px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Courier & POD</th>
                            <th class="w-24 px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                            <th class="w-20 px-4 py-3 text-center text-[11px] font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contractor-dispatch-tbody" class="divide-y divide-gray-100">
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Loading dispatches...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-gray-50/50">
                <div id="contractor-pagination-info" class="text-xs text-gray-500 font-medium">Showing 0 of 0 records</div>
                <div id="contractor-pagination-controls" class="flex items-center gap-1.5"></div>
            </div>
        </div>
    </div>

    <!-- VIEW 2: Create New Dispatch View (Shown on Click or URL params) -->
    <div id="dispatch-create-view" class="hidden space-y-6">
        <!-- Create Header with Back Button -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Create New Dispatch</h3>
                        <p class="text-sm text-gray-500">Send materials to field engineers or return to ADV warehouse</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="showListView()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center text-sm font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>Back to All Records
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispatch Form Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <form id="dispatch-form" class="p-6 space-y-6">
                <!-- Step 1: Destination Selection -->
                <div class="space-y-4">
                    <h4 class="text-md font-semibold text-gray-700 flex items-center">
                        <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm mr-2">1</span>
                        Select Destination
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Dispatch to Engineer -->
                        <label class="destination-option relative flex items-start p-4 border-2 rounded-xl cursor-pointer hover:border-primary/50 transition" data-type="engineer">
                            <input type="radio" name="destination_type" value="engineer" class="sr-only">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-user-hard-hat text-blue-500 text-xl"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">Dispatch to Engineer</p>
                                    <p class="text-sm text-gray-500">Send materials to field engineers</p>
                                </div>
                            </div>
                            <div class="absolute top-3 right-3 w-5 h-5 border-2 rounded-full destination-check hidden">
                                <i class="fas fa-check text-white text-xs"></i>
                            </div>
                        </label>
                        
                        <!-- Return to ADV -->
                        <label class="destination-option relative flex items-start p-4 border-2 rounded-xl cursor-pointer hover:border-primary/50 transition" data-type="adv">
                            <input type="radio" name="destination_type" value="adv" class="sr-only">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-warehouse text-green-500 text-xl"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">Return to ADV</p>
                                    <p class="text-sm text-gray-500">Return materials to ADV warehouse</p>
                                </div>
                            </div>
                            <div class="absolute top-3 right-3 w-5 h-5 border-2 rounded-full destination-check hidden">
                                <i class="fas fa-check text-white text-xs"></i>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Engineer Selection (shown when dispatching to engineer) -->
                    <div id="engineer-selection" class="hidden mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Engineer <span class="text-red-500">*</span></label>
                        <select id="engineer-select" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Loading engineers...</option>
                        </select>
                    </div>
                    
                    <!-- ADV Warehouse Selection (shown when returning to ADV) -->
                    <div id="warehouse-selection" class="hidden mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select ADV Warehouse <span class="text-red-500">*</span></label>
                        <select id="warehouse-select" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Loading warehouses...</option>
                        </select>
                    </div>
                </div>
                
                <!-- Step 2: Item Selection -->
                <div class="space-y-4 pt-4 border-t">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-3">
                        <h4 class="text-md font-semibold text-gray-700 flex items-center">
                            <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm mr-2">2</span>
                            Select Items to Dispatch
                        </h4>
                        
                        <!-- Dispatch Mode Switcher -->
                        <div class="inline-flex p-1 bg-gray-100 rounded-xl border border-gray-200">
                            <button type="button" id="btn-mode-bundle" onclick="setDispatchMode('bundle')" 
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center bg-white text-primary shadow-sm">
                                <i class="fas fa-boxes mr-1.5"></i>Site Material Bundle (Batch)
                            </button>
                            <button type="button" id="btn-mode-custom" onclick="setDispatchMode('custom')" 
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center text-gray-600 hover:text-gray-900">
                                <i class="fas fa-list-check mr-1.5"></i>Custom Item Picker
                            </button>
                        </div>
                    </div>

                    <!-- Mode 1: Site Material Bundle Picker -->
                    <div id="bundle-dispatch-section" class="space-y-4">
                        <div class="bg-blue-50/70 p-4 rounded-xl border border-blue-100 space-y-2">
                            <label class="block text-xs font-bold text-blue-900 uppercase tracking-wider">Select Site Material Bundle (Received from ADV)</label>
                            <select id="bundle-site-select" onchange="onSiteBundleSelected(this.value)" class="w-full px-4 py-3 text-xs border border-blue-200 rounded-lg bg-white focus:ring-2 focus:ring-primary font-medium text-gray-800">
                                <option value="">-- Choose a Site Material Bundle --</option>
                            </select>
                            <p class="text-[11px] text-blue-700"><i class="fas fa-info-circle mr-1"></i>Selecting a site bundle auto-populates all batch materials (router, SIM, rack, cables) dispatched by ADV for installation on that site.</p>
                        </div>

                        <!-- Bundle Card Details (hidden until selected) -->
                        <div id="bundle-details-card" class="hidden bg-white p-4 rounded-xl border border-blue-200 shadow-sm space-y-3">
                            <div class="flex items-center justify-between border-b pb-3">
                                <div>
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold uppercase tracking-wider">Site Material Batch Ready</span>
                                    <h5 id="bundle-site-title" class="text-sm font-bold text-gray-800 mt-1"></h5>
                                    <p id="bundle-manifest-info" class="text-xs font-mono text-gray-500"></p>
                                </div>
                                <button type="button" onclick="selectAllBundleItems()" class="px-3 py-1.5 bg-primary text-white rounded-lg text-xs font-semibold shadow-sm hover:bg-blue-700 transition flex items-center">
                                    <i class="fas fa-check-double mr-1.5"></i>Auto-Select Batch Items
                                </button>
                            </div>

                            <!-- Bundle Items Table -->
                            <div id="bundle-items-list" class="space-y-2"></div>
                        </div>
                    </div>

                    <!-- Mode 2: Custom / Individual Inventory List -->
                    <div id="custom-dispatch-section" class="hidden space-y-4">
                        <div id="inventory-summary" class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-gray-600">Your Available Inventory</span>
                                <button type="button" onclick="refreshInventory()" class="text-sm text-primary hover:underline">
                                    <i class="fas fa-sync-alt mr-1.5"></i>Refresh
                                </button>
                            </div>
                            <div id="inventory-loading" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin text-primary"></i>
                                <span class="ml-2 text-gray-500">Loading inventory...</span>
                            </div>
                            <div id="inventory-empty" class="hidden text-center py-4 text-gray-500">
                                <i class="fas fa-box-open text-3xl mb-2"></i>
                                <p>No inventory available for dispatch</p>
                            </div>
                            <div id="inventory-list" class="hidden space-y-2 max-h-64 overflow-y-auto"></div>
                        </div>
                    </div>
                    
                    <!-- Selected Items Container (Shared for both modes) -->
                    <div id="selected-items-container" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Selected Items for Dispatch</label>
                        <div id="selected-items" class="border rounded-lg divide-y"></div>
                        
                        <!-- Validation Messages -->
                        <div id="validation-messages" class="hidden mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-red-500 mt-0.5 mr-2"></i>
                                <div id="validation-text" class="text-sm text-red-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Courier POD & Shipping Capture -->
                <div class="space-y-4 pt-4 border-t">
                    <h4 class="text-md font-semibold text-gray-700 flex items-center justify-between">
                        <span class="flex items-center">
                            <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-sm mr-2">3</span>
                            Courier Shipping & POD Capture
                        </span>
                        <span class="text-xs text-red-500 font-semibold">* Shipping Details Required</span>
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50/80 p-4 rounded-xl border border-gray-200">
                        <!-- Courier Partner Selection -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Courier Partner / Service <span class="text-red-500">*</span></label>
                            <select id="courier-select" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-primary focus:border-transparent font-medium">
                                <option value="">Loading couriers...</option>
                            </select>
                        </div>

                        <!-- LR / POD Waybill / Tracking Number -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">LR / POD / Waybill Number <span class="text-red-500">*</span></label>
                            <input type="text" id="pod-number-input" placeholder="e.g. LR-88492019 / DTDC12345" 
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-primary font-mono">
                        </div>

                        <!-- Contact Person Name -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Recipient Contact Person Name <span class="text-red-500">*</span></label>
                            <input type="text" id="contact-name-input" placeholder="e.g. Rajesh Kumar" 
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-primary">
                        </div>

                        <!-- Contact Person Phone -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Recipient Contact Phone <span class="text-red-500">*</span></label>
                            <input type="tel" id="contact-phone-input" placeholder="e.g. +91 9876543210" 
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-primary">
                        </div>

                        <!-- LR Copy Upload -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1"><i class="fas fa-file-invoice mr-1 text-primary"></i>Upload LR Copy / Courier Receipt <span class="text-red-500">*</span></label>
                            <input type="file" id="lr-copy-input" accept="image/*,application/pdf" class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                            <p id="lr-file-name" class="text-[11px] text-emerald-600 font-semibold mt-1 hidden"><i class="fas fa-check-circle mr-1"></i><span id="lr-file-text"></span></p>
                        </div>

                        <!-- POD Receipt Document Upload -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1"><i class="fas fa-receipt mr-1 text-emerald-600"></i>Upload Courier POD Receipt Document <span class="text-red-500">*</span></label>
                            <input type="file" id="pod-receipt-input" accept="image/*,application/pdf" class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                            <p id="pod-file-name" class="text-[11px] text-emerald-600 font-semibold mt-1 hidden"><i class="fas fa-check-circle mr-1"></i><span id="pod-file-text"></span></p>
                        </div>
                    </div>

                    <!-- Site Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Site (Optional)</label>
                        <select id="site-select" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select a site (optional)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Associate this dispatch with a delegated site</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea id="dispatch-notes" rows="3" placeholder="Add any notes about this dispatch..." 
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                    </div>
                </div>
                
                <!-- Submit Section -->
                <div class="pt-4 border-t flex items-center justify-between">
                    <div id="dispatch-summary" class="text-sm text-gray-600">
                        <span id="summary-text">Select destination and items to dispatch</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="showListView()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            Cancel
                        </button>
                        <button type="button" onclick="resetForm()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-undo mr-2"></i>Reset
                        </button>
                        <button type="submit" id="submit-btn" disabled class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-paper-plane mr-2"></i>Create Dispatch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contractor View Dispatch Details Modal -->
<div id="contractor-view-dispatch-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeContractorViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10 overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 bg-gray-50/50">
                <div>
                    <h3 class="text-base font-bold text-gray-800">Dispatch Details</h3>
                    <p id="cv-dispatch-number" class="text-xs font-mono text-primary font-semibold mt-0.5"></p>
                </div>
                <button onclick="closeContractorViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-200 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="contractor-view-dispatch-content" class="p-5 max-h-[75vh] overflow-y-auto space-y-4">
                <!-- Loaded via JS -->
            </div>
            
            <div class="flex justify-end p-4 border-t border-gray-100 bg-gray-50">
                <button onclick="closeContractorViewModal()" class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-300 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeConfirmModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Confirm Dispatch</h3>
                <button onclick="closeConfirmModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-blue-800">Review Dispatch Details</p>
                            <p class="text-sm text-blue-600">Please confirm the details below before submitting</p>
                        </div>
                    </div>
                </div>
                
                <div id="confirm-details" class="space-y-3">
                    <!-- Details will be populated here -->
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="confirm-submit-btn" onclick="submitDispatch()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                    <i class="fas fa-check mr-2"></i>Confirm & Dispatch
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="success-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10 text-center p-8">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-green-500 text-3xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Dispatch Created!</h3>
            <p id="success-message" class="text-gray-600 mb-4">Your dispatch has been created successfully.</p>
            <p class="text-sm text-gray-500 mb-6">Dispatch Number: <span id="success-dispatch-number" class="font-mono font-medium text-primary"></span></p>
            <div class="flex justify-center gap-3">
                <button onclick="closeSuccessModal(); resetForm();" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Create Another
                </button>
                <a href="stocks.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                    View Stocks
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.destination-option.selected {
    border-color: var(--primary-color, #3b82f6);
    background-color: rgba(59, 130, 246, 0.05);
}
.destination-option.selected .destination-check {
    display: flex !important;
    align-items: center;
    justify-content: center;
    background-color: var(--primary-color, #3b82f6);
    border-color: var(--primary-color, #3b82f6);
}
</style>

<!-- Pass company ID from PHP to JavaScript -->
<script>
const COMPANY_ID = <?php echo json_encode($currentUser['company_id']); ?>;
// Get URL parameters
const urlParams = new URLSearchParams(window.location.search);
const URL_SITE_ID = urlParams.get('site_id') ? parseInt(urlParams.get('site_id')) : null;
const URL_DESTINATION = urlParams.get('destination'); // 'engineer' or 'adv'
</script>

<script>
// State management
const state = {
    dispatchMode: 'bundle', // 'bundle' or 'custom'
    destinationType: null,
    selectedEngineerId: null,
    selectedWarehouseId: null,
    selectedSiteId: null,
    inventory: [],
    siteBundles: [],
    selectedBundle: null,
    selectedItems: [],
    destinations: { users: [], warehouses: [] },
    sites: [],
    companyId: COMPANY_ID,
    // Dispatches history table state
    dispatches: [],
    dispatchPagination: { page: 1, limit: 15, total: 0, total_pages: 0 },
    dispatchFilters: { search: '', status: '', acknowledgment_status: '' }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDestinations();
    loadInventory();
    loadSites();
    loadSiteBundles();
    loadCouriers();
    loadContractorDispatches();
    setupEventListeners();
    
    // Default to Engineer destination for site installation dispatches
    selectDestinationType('engineer');

    // Handle URL parameters for view selection & pre-selection
    const urlAction = urlParams.get('action');
    if (urlAction === 'create' || URL_SITE_ID || URL_DESTINATION) {
        showCreateView();
        if (URL_DESTINATION) {
            setTimeout(() => {
                selectDestinationType(URL_DESTINATION);
            }, 100);
        }
    } else {
        showListView();
    }
});

function showCreateView() {
    const listView = document.getElementById('dispatch-list-view');
    const createView = document.getElementById('dispatch-create-view');
    if (listView) listView.classList.add('hidden');
    if (createView) createView.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showListView() {
    const createView = document.getElementById('dispatch-create-view');
    const listView = document.getElementById('dispatch-list-view');
    if (createView) createView.classList.add('hidden');
    if (listView) listView.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setDispatchMode(mode) {
    state.dispatchMode = mode;
    
    const btnBundle = document.getElementById('btn-mode-bundle');
    const btnCustom = document.getElementById('btn-mode-custom');
    const secBundle = document.getElementById('bundle-dispatch-section');
    const secCustom = document.getElementById('custom-dispatch-section');
    
    if (mode === 'bundle') {
        btnBundle.className = 'px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center bg-white text-primary shadow-sm';
        btnCustom.className = 'px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center text-gray-600 hover:text-gray-900';
        secBundle.classList.remove('hidden');
        secCustom.classList.add('hidden');
    } else {
        btnCustom.className = 'px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center bg-white text-primary shadow-sm';
        btnBundle.className = 'px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center text-gray-600 hover:text-gray-900';
        secCustom.classList.remove('hidden');
        secBundle.classList.add('hidden');
    }
}

async function loadSiteBundles() {
    try {
        const [recvRes, dashRes] = await Promise.all([
            fetch(`../../api/inventory/receive/pending.php?view=company`, { credentials: 'include' }),
            fetch(`../../api/inventory/dashboard/contractor.php`, { credentials: 'include' })
        ]);
        
        const recvData = await recvRes.json();
        const dashData = await dashRes.json();
        
        const outgoingDispatches = (dashData.success && dashData.data?.recent_activity?.recent_dispatches) ? dashData.data.recent_activity.recent_dispatches : [];
        
        if (recvData.success && recvData.data.pending_receives) {
            state.siteBundles = recvData.data.pending_receives
                .filter(pr => pr.status === 'accepted' && pr.items && pr.items.length > 0)
                .map(pr => {
                    const matchOut = outgoingDispatches.find(d => parseInt(d.site_id) === parseInt(pr.site_id) && d.status !== 'cancelled');
                    if (matchOut) {
                        pr.is_dispatched = true;
                        pr.dispatched_to_name = matchOut.to_user_name || matchOut.to_name || 'Engineer';
                        pr.dispatched_at = matchOut.created_at;
                    } else {
                        pr.is_dispatched = false;
                    }
                    return pr;
                });
            populateSiteBundlesDropdown();
        }
    } catch (error) {
        console.error('Error loading site bundles:', error);
    }
}

function populateSiteBundlesDropdown() {
    const select = document.getElementById('bundle-site-select');
    select.innerHTML = '<option value="">-- Choose a Site Material Bundle --</option>';
    
    if (!state.siteBundles || state.siteBundles.length === 0) {
        select.innerHTML = '<option value="">No site material bundles available from ADV</option>';
        return;
    }
    
    state.siteBundles.forEach((b, idx) => {
        const siteName = b.site_name || 'Site #' + (b.site_id || b.id);
        const lho = b.lho ? ` (${b.lho})` : '';
        const city = b.city ? ` - ${b.city}` : '';
        const manifest = b.dispatch_number || b.manifest_number || 'N/A';
        const itemCount = b.items ? b.items.length : 0;
        const statusTag = b.is_dispatched ? ` [✔ DISPATCHED to ${b.dispatched_to_name}]` : ' [READY]';
        
        select.innerHTML += `<option value="${idx}">Site: ${siteName}${lho}${city} | Manifest: ${manifest} (${itemCount} items)${statusTag}</option>`;
    });

    // Auto-select if URL_SITE_ID exists
    if (URL_SITE_ID) {
        const matchIdx = state.siteBundles.findIndex(b => parseInt(b.site_id) === URL_SITE_ID);
        if (matchIdx >= 0) {
            select.value = matchIdx;
            onSiteBundleSelected(matchIdx);
        }
    }
}

function onSiteBundleSelected(bundleIdxStr) {
    if (bundleIdxStr === '' || bundleIdxStr === null) {
        state.selectedBundle = null;
        document.getElementById('bundle-details-card').classList.add('hidden');
        state.selectedItems = [];
        renderSelectedItems();
        updateSubmitButton();
        updateSummary();
        return;
    }
    
    const idx = parseInt(bundleIdxStr);
    const bundle = state.siteBundles[idx];
    if (!bundle) return;
    
    state.selectedBundle = bundle;
    
    // Auto-select site in Step 3
    if (bundle.site_id) {
        state.selectedSiteId = parseInt(bundle.site_id);
        const siteSelect = document.getElementById('site-select');
        if (siteSelect) siteSelect.value = bundle.site_id;
    }
    
    // Auto-select engineer if assigned in site delegations
    if (bundle.site_id && state.sites && state.sites.length > 0) {
        const del = state.sites.find(s => parseInt(s.site_id) === parseInt(bundle.site_id));
        if (del && del.assigned_engineer_id) {
            const engSelect = document.getElementById('engineer-select');
            if (engSelect) {
                engSelect.value = del.assigned_engineer_id;
                state.selectedEngineerId = parseInt(del.assigned_engineer_id);
            }
        }
    }
    
    // Update bundle card UI
    document.getElementById('bundle-site-title').textContent = `${bundle.site_name || 'Site #' + bundle.site_id} (${bundle.lho || 'N/A'}, ${bundle.city || 'N/A'})`;
    document.getElementById('bundle-manifest-info').textContent = `ADV Manifest: ${bundle.dispatch_number || 'N/A'} | Received Date: ${bundle.acknowledged_at ? new Date(bundle.acknowledged_at).toLocaleDateString() : 'N/A'}`;
    
    // Render bundle status notice if already dispatched
    const statusBannerHtml = bundle.is_dispatched 
        ? `<div class="p-3 bg-purple-50 border border-purple-200 text-purple-800 rounded-xl text-xs font-semibold flex items-center justify-between">
                <span><i class="fas fa-check-circle text-purple-600 text-sm mr-2"></i>Material bundle for this site was ALREADY dispatched to Engineer (${bundle.dispatched_to_name}).</span>
                <span class="px-2 py-0.5 bg-purple-200 text-purple-900 rounded font-bold text-[10px]">DISPATCHED</span>
           </div>`
        : '';

    // Render bundle items summary
    const itemsListEl = document.getElementById('bundle-items-list');
    itemsListEl.innerHTML = statusBannerHtml + bundle.items.map(item => {
        const serials = item.serial_numbers && item.serial_numbers.length > 0
            ? `<div class="mt-1 flex flex-wrap gap-1">${item.serial_numbers.map(s => `<span class="px-1.5 py-0.5 bg-blue-50 text-blue-700 font-mono text-[10px] rounded">${s}</span>`).join('')}</div>`
            : '';
        return `
            <div class="p-2.5 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="font-semibold text-gray-800 text-xs">${item.product_name || 'Product #' + item.product_id}</p>
                    <p class="text-[11px] text-gray-500">Qty: ${item.received_quantity || item.expected_quantity || 1}</p>
                    ${serials}
                </div>
                <span class="px-2 py-1 ${bundle.is_dispatched ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'} font-semibold rounded text-[10px]">
                    ${bundle.is_dispatched ? 'Dispatched' : 'Batch Item'}
                </span>
            </div>`;
    }).join('');
    
    document.getElementById('bundle-details-card').classList.remove('hidden');
    
    // Auto-select all items from bundle into payload
    selectAllBundleItems();
}

function selectAllBundleItems() {
    if (!state.selectedBundle || !state.selectedBundle.items) return;
    
    state.selectedItems = [];
    
    state.selectedBundle.items.forEach(item => {
        const productId = parseInt(item.product_id);
        const quantity = parseInt(item.received_quantity || item.expected_quantity || 1);
        
        // Find matching inventory item to get available stock
        const invItem = state.inventory.find(i => parseInt(i.product_id) === productId);
        const availQty = invItem ? invItem.available_quantity : quantity;
        
        state.selectedItems.push({
            product_id: productId,
            product_name: item.product_name || 'Product #' + productId,
            quantity: Math.min(quantity, availQty),
            available_quantity: availQty,
            is_serializable: item.serial_numbers && item.serial_numbers.length > 0,
            serial_numbers: item.serial_numbers || []
        });
    });
    
    renderSelectedItems();
    updateSubmitButton();
    updateSummary();
    showToast(`Batch bundle auto-selected (${state.selectedItems.length} products ready for dispatch)`, 'info');
}

function toggleCreateForm() {
    const formCard = document.getElementById('create-dispatch-card');
    const toggleIcon = document.getElementById('create-toggle-icon');
    const toggleText = document.getElementById('create-toggle-text');
    if (!formCard) return;
    const isHidden = formCard.classList.contains('hidden');
    
    if (isHidden) {
        formCard.classList.remove('hidden');
        if (toggleIcon) toggleIcon.className = 'fas fa-chevron-up mr-1';
        if (toggleText) toggleText.textContent = 'Hide Form';
        formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        formCard.classList.add('hidden');
        if (toggleIcon) toggleIcon.className = 'fas fa-chevron-down mr-1';
        if (toggleText) toggleText.textContent = 'Show Form';
    }
}

function setupEventListeners() {
    // Destination type selection
    document.querySelectorAll('.destination-option').forEach(option => {
        option.addEventListener('click', function() {
            selectDestinationType(this.dataset.type);
        });
    });
    
    // Engineer selection
    document.getElementById('engineer-select').addEventListener('change', function() {
        state.selectedEngineerId = this.value ? parseInt(this.value) : null;
        updateSubmitButton();
        updateSummary();
    });
    
    // Warehouse selection
    document.getElementById('warehouse-select').addEventListener('change', function() {
        state.selectedWarehouseId = this.value ? parseInt(this.value) : null;
        updateSubmitButton();
        updateSummary();
    });
    
    // Site selection
    document.getElementById('site-select').addEventListener('change', function() {
        state.selectedSiteId = this.value ? parseInt(this.value) : null;
        updateSummary();
    });
    
    // Form submission
    document.getElementById('dispatch-form').addEventListener('submit', function(e) {
        e.preventDefault();
        showConfirmModal();
    });

    // Dispatches Table search & filter listeners
    let searchTimeout;
    const searchInput = document.getElementById('dispatch-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                state.dispatchFilters.search = e.target.value.trim();
                state.dispatchPagination.page = 1;
                loadContractorDispatches();
            }, 300);
        });
    }

    const statusFilter = document.getElementById('dispatch-status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function(e) {
            state.dispatchFilters.status = e.target.value;
            state.dispatchPagination.page = 1;
            loadContractorDispatches();
        });
    }

    const ackFilter = document.getElementById('dispatch-ack-filter');
    if (ackFilter) {
        ackFilter.addEventListener('change', function(e) {
            state.dispatchFilters.acknowledgment_status = e.target.value;
            state.dispatchPagination.page = 1;
            loadContractorDispatches();
        });
    }
}

function selectDestinationType(type) {
    state.destinationType = type;
    
    // Update UI
    document.querySelectorAll('.destination-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.querySelector(`.destination-option[data-type="${type}"]`).classList.add('selected');
    
    // Show/hide selection dropdowns
    document.getElementById('engineer-selection').classList.toggle('hidden', type !== 'engineer');
    document.getElementById('warehouse-selection').classList.toggle('hidden', type !== 'adv');
    
    // Reset selections
    if (type === 'engineer') {
        state.selectedWarehouseId = null;
        document.getElementById('warehouse-select').value = '';
    } else {
        state.selectedEngineerId = null;
        document.getElementById('engineer-select').value = '';
    }
    
    updateSubmitButton();
    updateSummary();
}

async function loadDestinations() {
    try {
        // Load valid destinations using the company ID from PHP
        const response = await fetch(`../../api/inventory/dispatch/destinations.php?sender_type=company&sender_id=${state.companyId}`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (result.success) {
            state.destinations = result.data;
            populateDestinations();
        } else {
            console.error('Failed to load destinations:', result.message);
            showToast('Failed to load destinations', 'error');
        }
    } catch (error) {
        console.error('Error loading destinations:', error);
        showToast('Failed to load destinations', 'error');
    }
}

function populateDestinations() {
    // Populate engineers dropdown
    const engineerSelect = document.getElementById('engineer-select');
    engineerSelect.innerHTML = '<option value="">Select an engineer</option>';
    
    if (state.destinations.users && state.destinations.users.length > 0) {
        state.destinations.users.forEach(user => {
            const name = `${user.first_name} ${user.last_name}`.trim();
            engineerSelect.innerHTML += `<option value="${user.id}">${name} (${user.email || 'No email'})</option>`;
        });
    } else {
        engineerSelect.innerHTML = '<option value="">No engineers available</option>';
    }
    
    // Populate warehouses dropdown
    const warehouseSelect = document.getElementById('warehouse-select');
    warehouseSelect.innerHTML = '<option value="">Select a warehouse</option>';
    
    if (state.destinations.warehouses && state.destinations.warehouses.length > 0) {
        state.destinations.warehouses.forEach(warehouse => {
            warehouseSelect.innerHTML += `<option value="${warehouse.id}">${warehouse.name}</option>`;
        });
    } else {
        warehouseSelect.innerHTML = '<option value="">No ADV warehouses available</option>';
    }
}

async function loadSites() {
    try {
        // Load delegated sites for contractor
        const response = await fetch(`../../api/delegations/index.php?status=accepted&limit=100`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (result.success && result.data && result.data.delegations) {
            state.sites = result.data.delegations;
            populateSites();
        } else {
            console.error('Failed to load sites:', result.message);
        }
    } catch (error) {
        console.error('Error loading sites:', error);
    }
}

function populateSites() {
    const siteSelect = document.getElementById('site-select');
    siteSelect.innerHTML = '<option value="">Select a site (optional)</option>';
    
    if (state.sites && state.sites.length > 0) {
        state.sites.forEach(delegation => {
            const siteName = delegation.site_name || 'Unknown Site';
            const lho = delegation.lho ? ` (${delegation.lho})` : '';
            const city = delegation.city ? ` - ${delegation.city}` : '';
            siteSelect.innerHTML += `<option value="${delegation.site_id}">${siteName}${lho}${city}</option>`;
        });
        
        // Check for URL parameter first, then auto-select if only one site
        if (URL_SITE_ID) {
            const matchingSite = state.sites.find(s => parseInt(s.site_id) === URL_SITE_ID);
            if (matchingSite) {
                siteSelect.value = URL_SITE_ID;
                state.selectedSiteId = URL_SITE_ID;
                updateSummary();
            }
        } else if (state.sites.length === 1) {
            siteSelect.value = state.sites[0].site_id;
            state.selectedSiteId = parseInt(state.sites[0].site_id);
            updateSummary();
        }
    }
}

async function loadInventory() {
    const loadingEl = document.getElementById('inventory-loading');
    const emptyEl = document.getElementById('inventory-empty');
    const listEl = document.getElementById('inventory-list');
    
    loadingEl.classList.remove('hidden');
    emptyEl.classList.add('hidden');
    listEl.classList.add('hidden');
    
    try {
        const response = await fetch(`../../api/inventory/counter/get.php?entity_type=company&entity_id=${state.companyId}`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        loadingEl.classList.add('hidden');
        
        if (result.success && result.data.counters && result.data.counters.length > 0) {
            state.inventory = result.data.counters.filter(c => c.available_quantity > 0);
            
            if (state.inventory.length > 0) {
                renderInventoryList();
                listEl.classList.remove('hidden');
                
                // If site_id is provided via URL, load and auto-select site-specific products
                if (URL_SITE_ID) {
                    await loadSiteSpecificProducts();
                }
            } else {
                emptyEl.classList.remove('hidden');
            }
        } else {
            emptyEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading inventory:', error);
        loadingEl.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        emptyEl.innerHTML = '<i class="fas fa-exclamation-circle text-3xl mb-2 text-red-400"></i><p>Failed to load inventory</p>';
    }
}

// Load products received for a specific site and auto-select them
async function loadSiteSpecificProducts() {
    try {
        // Get all accepted pending receives for the company
        const response = await fetch(`../../api/inventory/receive/pending.php?view=company`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        if (result.success && result.data.pending_receives) {
            // Filter receives that have the matching site_id (from accepted dispatches)
            const siteReceives = result.data.pending_receives.filter(pr => {
                const prSiteId = pr.site_id ? parseInt(pr.site_id) : null;
                return prSiteId === URL_SITE_ID && pr.status === 'accepted';
            });
            
            console.log('Site receives found:', siteReceives);
            
            if (siteReceives.length > 0) {
                // Collect all products with quantities from site-specific receives
                const siteProducts = new Map(); // product_id -> total quantity
                
                siteReceives.forEach(receive => {
                    if (receive.items && receive.items.length > 0) {
                        receive.items.forEach(item => {
                            const productId = parseInt(item.product_id);
                            const qty = parseInt(item.received_quantity || item.expected_quantity || 1);
                            if (siteProducts.has(productId)) {
                                siteProducts.set(productId, siteProducts.get(productId) + qty);
                            } else {
                                siteProducts.set(productId, qty);
                            }
                        });
                    }
                });
                
                console.log('Site products:', Array.from(siteProducts.entries()));
                
                // Auto-select products that match site-specific receives
                siteProducts.forEach((quantity, productId) => {
                    const inventoryItem = state.inventory.find(i => parseInt(i.product_id) === productId);
                    if (inventoryItem && inventoryItem.available_quantity > 0) {
                        // Check if not already selected
                        if (!state.selectedItems.some(s => parseInt(s.product_id) === productId)) {
                            // Use the minimum of received quantity and available quantity
                            const selectQty = Math.min(quantity, inventoryItem.available_quantity);
                            state.selectedItems.push({
                                product_id: productId,
                                product_name: inventoryItem.product_name,
                                quantity: selectQty,
                                available_quantity: inventoryItem.available_quantity,
                                is_serializable: inventoryItem.is_serializable || false,
                                serial_numbers: inventoryItem.serial_numbers || []
                            });
                        }
                    }
                });
                
                // Update UI
                if (state.selectedItems.length > 0) {
                    renderInventoryList();
                    renderSelectedItems();
                    updateSubmitButton();
                    updateSummary();
                    showToast(`Auto-selected ${state.selectedItems.length} product(s) received for this site`, 'info');
                }
            } else {
                console.log('No site-specific receives found for site_id:', URL_SITE_ID);
            }
        }
    } catch (error) {
        console.error('Error loading site-specific products:', error);
    }
}

function refreshInventory() {
    loadInventory();
}

function renderInventoryList() {
    const listEl = document.getElementById('inventory-list');
    listEl.innerHTML = '';
    
    state.inventory.forEach(item => {
        const isSelected = state.selectedItems.some(s => s.product_id === item.product_id);
        const selectedItem = state.selectedItems.find(s => s.product_id === item.product_id);
        const remainingQty = isSelected ? item.available_quantity - (selectedItem?.quantity || 0) : item.available_quantity;
        const isOverLimit = isSelected && selectedItem.quantity > item.available_quantity;
        const isSerializable = item.is_serializable && item.serial_numbers && item.serial_numbers.length > 0;
        
        const itemEl = document.createElement('div');
        itemEl.className = `flex flex-col p-3 rounded-lg border ${isOverLimit ? 'bg-red-50 border-red-300' : isSelected ? 'bg-primary/5 border-primary/30' : 'bg-white hover:bg-gray-50'} transition cursor-pointer`;
        
        // Build serial numbers display
        let serialNumbersHtml = '';
        if (isSerializable) {
            const serialList = item.serial_numbers.slice(0, 5).map(sn => 
                `<span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded mr-1 mb-1" title="Status: ${sn.status}">${sn.serial_number}</span>`
            ).join('');
            const moreCount = item.serial_numbers.length > 5 ? `<span class="text-xs text-gray-400">+${item.serial_numbers.length - 5} more</span>` : '';
            serialNumbersHtml = `
                <div class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-barcode mr-1"></i>Serial Numbers:</p>
                    <div class="flex flex-wrap">${serialList}${moreCount}</div>
                </div>
            `;
        }
        
        itemEl.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 ${isOverLimit ? 'bg-red-100' : 'bg-gray-100'} rounded-lg flex items-center justify-center mr-3">
                        <i class="fas ${isOverLimit ? 'fa-exclamation-triangle text-red-500' : isSerializable ? 'fa-barcode text-gray-400' : 'fa-box text-gray-400'}"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">${item.product_name || 'Unknown Product'}</p>
                        <p class="text-xs text-gray-500">${item.category_name || 'Uncategorized'}${isSerializable ? ' <span class="text-blue-500">(Serializable)</span>' : ''}</p>
                        ${isOverLimit ? '<p class="text-xs text-red-600 font-medium mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Exceeds available quantity!</p>' : ''}
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-sm font-medium ${remainingQty > 0 ? 'text-green-600' : remainingQty < 0 ? 'text-red-600' : 'text-gray-400'}">
                            ${remainingQty >= 0 ? remainingQty : 0} available
                        </p>
                        ${isSelected ? `<p class="text-xs ${isOverLimit ? 'text-red-600 font-medium' : 'text-primary'}">${selectedItem.quantity} selected</p>` : ''}
                        <p class="text-xs text-gray-400">Total: ${item.available_quantity}</p>
                    </div>
                    <button type="button" onclick="toggleItemSelection(${item.product_id})" 
                        class="w-8 h-8 rounded-lg ${isSelected ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'} flex items-center justify-center transition">
                        <i class="fas ${isSelected ? 'fa-check' : 'fa-plus'}"></i>
                    </button>
                </div>
            </div>
            ${serialNumbersHtml}
        `;
        listEl.appendChild(itemEl);
    });
}

function toggleItemSelection(productId) {
    const existingIndex = state.selectedItems.findIndex(s => s.product_id === productId);
    
    if (existingIndex >= 0) {
        // Remove item
        state.selectedItems.splice(existingIndex, 1);
    } else {
        // Add item with default quantity of 1
        const inventoryItem = state.inventory.find(i => i.product_id === productId);
        if (inventoryItem && inventoryItem.available_quantity > 0) {
            state.selectedItems.push({
                product_id: productId,
                product_name: inventoryItem.product_name,
                quantity: 1,
                available_quantity: inventoryItem.available_quantity,
                is_serializable: inventoryItem.is_serializable || false,
                serial_numbers: inventoryItem.serial_numbers || []
            });
        }
    }
    
    renderInventoryList();
    renderSelectedItems();
    updateSubmitButton();
    updateSummary();
}

function renderSelectedItems() {
    const container = document.getElementById('selected-items-container');
    const itemsEl = document.getElementById('selected-items');
    
    if (state.selectedItems.length === 0) {
        container.classList.add('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    itemsEl.innerHTML = '';
    
    state.selectedItems.forEach((item, index) => {
        const isOverLimit = item.quantity > item.available_quantity;
        const isSerializable = item.is_serializable && item.serial_numbers && item.serial_numbers.length > 0;
        
        // Build serial numbers display for selected items
        let serialNumbersHtml = '';
        if (isSerializable) {
            const serialList = item.serial_numbers.slice(0, 3).map(sn => 
                `<span class="inline-block px-1.5 py-0.5 bg-blue-50 text-blue-600 text-xs rounded mr-1">${sn.serial_number}</span>`
            ).join('');
            const moreCount = item.serial_numbers.length > 3 ? `<span class="text-xs text-gray-400">+${item.serial_numbers.length - 3} more</span>` : '';
            serialNumbersHtml = `<div class="mt-1 flex flex-wrap items-center"><span class="text-xs text-gray-400 mr-1">S/N:</span>${serialList}${moreCount}</div>`;
        }
        
        const itemEl = document.createElement('div');
        itemEl.className = `flex items-center justify-between p-3 ${isOverLimit ? 'bg-red-50' : ''}`;
        itemEl.innerHTML = `
            <div class="flex items-center flex-1">
                <div class="w-8 h-8 ${isOverLimit ? 'bg-red-100' : 'bg-primary/10'} rounded-lg flex items-center justify-center mr-3">
                    <i class="fas ${isOverLimit ? 'fa-exclamation-triangle text-red-500' : isSerializable ? 'fa-barcode text-primary' : 'fa-box text-primary'} text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="font-medium text-gray-800">${item.product_name}</p>
                    <p class="text-xs ${isOverLimit ? 'text-red-600 font-medium' : 'text-gray-500'}">
                        ${isOverLimit ? '<i class="fas fa-exclamation-circle mr-1"></i>Only ' : 'Available: '}${item.available_quantity} available
                    </p>
                    ${serialNumbersHtml}
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center border ${isOverLimit ? 'border-red-300' : ''} rounded-lg">
                    <button type="button" onclick="adjustQuantity(${index}, -1)" 
                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-l-lg transition">
                        <i class="fas fa-minus text-xs"></i>
                    </button>
                    <input type="number" value="${item.quantity}" min="1" max="${item.available_quantity}" 
                        onchange="setQuantity(${index}, this.value)"
                        class="w-16 text-center border-x py-1 focus:outline-none ${isOverLimit ? 'text-red-600 font-medium' : ''}">
                    <button type="button" onclick="adjustQuantity(${index}, 1)" 
                        class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-r-lg transition ${item.quantity >= item.available_quantity ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${item.quantity >= item.available_quantity ? 'disabled' : ''}>
                        <i class="fas fa-plus text-xs"></i>
                    </button>
                </div>
                <button type="button" onclick="removeItem(${index})" 
                    class="w-8 h-8 rounded-lg bg-red-100 text-red-500 hover:bg-red-200 flex items-center justify-center transition">
                    <i class="fas fa-trash text-xs"></i>
                </button>
            </div>
        `;
        itemsEl.appendChild(itemEl);
    });
    
    validateItems();
}

function adjustQuantity(index, delta) {
    const item = state.selectedItems[index];
    const newQty = item.quantity + delta;
    
    if (newQty >= 1 && newQty <= item.available_quantity) {
        item.quantity = newQty;
        renderSelectedItems();
        renderInventoryList();
        updateSummary();
    }
}

function setQuantity(index, value) {
    const item = state.selectedItems[index];
    const newQty = parseInt(value) || 1;
    
    item.quantity = Math.max(1, Math.min(newQty, item.available_quantity));
    renderSelectedItems();
    renderInventoryList();
    updateSummary();
}

function removeItem(index) {
    state.selectedItems.splice(index, 1);
    renderSelectedItems();
    renderInventoryList();
    updateSubmitButton();
    updateSummary();
}

function validateItems() {
    const messagesEl = document.getElementById('validation-messages');
    const textEl = document.getElementById('validation-text');
    const errors = [];
    
    state.selectedItems.forEach(item => {
        if (item.quantity > item.available_quantity) {
            errors.push(`${item.product_name}: Requested ${item.quantity}, but only ${item.available_quantity} available`);
        }
        if (item.quantity < 1) {
            errors.push(`${item.product_name}: Quantity must be at least 1`);
        }
    });
    
    if (errors.length > 0) {
        textEl.innerHTML = errors.join('<br>');
        messagesEl.classList.remove('hidden');
        return false;
    } else {
        messagesEl.classList.add('hidden');
        return true;
    }
}

function updateSubmitButton() {
    const btn = document.getElementById('submit-btn');
    const hasDestination = (state.destinationType === 'engineer' && state.selectedEngineerId) ||
                          (state.destinationType === 'adv' && state.selectedWarehouseId);
    const hasItems = state.selectedItems.length > 0;
    const isValid = validateItems();
    
    btn.disabled = !(hasDestination && hasItems && isValid);
}

function updateSummary() {
    const summaryEl = document.getElementById('summary-text');
    const parts = [];
    
    if (state.destinationType === 'engineer' && state.selectedEngineerId) {
        const engineer = state.destinations.users.find(u => u.id == state.selectedEngineerId);
        if (engineer) {
            parts.push(`To: ${engineer.first_name} ${engineer.last_name}`);
        }
    } else if (state.destinationType === 'adv' && state.selectedWarehouseId) {
        const warehouse = state.destinations.warehouses.find(w => w.id == state.selectedWarehouseId);
        if (warehouse) {
            parts.push(`To: ${warehouse.name}`);
        }
    }
    
    if (state.selectedSiteId) {
        const site = state.sites.find(s => s.site_id == state.selectedSiteId);
        if (site) {
            parts.push(`Site: ${site.site_name}`);
        }
    }
    
    if (state.selectedItems.length > 0) {
        const totalQty = state.selectedItems.reduce((sum, item) => sum + item.quantity, 0);
        parts.push(`${state.selectedItems.length} product(s), ${totalQty} total qty`);
    }
    
    summaryEl.textContent = parts.length > 0 ? parts.join(' • ') : 'Select destination and items to dispatch';
}

function showConfirmModal() {
    const detailsEl = document.getElementById('confirm-details');
    
    // Build destination info
    let destinationHtml = '';
    if (state.destinationType === 'engineer') {
        const engineer = state.destinations.users.find(u => u.id == state.selectedEngineerId);
        destinationHtml = `
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-user-hard-hat text-blue-500"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Dispatching to Engineer</p>
                    <p class="font-medium text-gray-800">${engineer ? `${engineer.first_name} ${engineer.last_name}` : 'Unknown'}</p>
                </div>
            </div>
        `;
    } else {
        const warehouse = state.destinations.warehouses.find(w => w.id == state.selectedWarehouseId);
        destinationHtml = `
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-warehouse text-green-500"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Returning to ADV Warehouse</p>
                    <p class="font-medium text-gray-800">${warehouse ? warehouse.name : 'Unknown'}</p>
                </div>
            </div>
        `;
    }
    
    // Build site info if selected
    let siteHtml = '';
    if (state.selectedSiteId) {
        const site = state.sites.find(s => s.site_id == state.selectedSiteId);
        if (site) {
            siteHtml = `
                <div class="flex items-center p-3 bg-purple-50 rounded-lg mt-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-map-marker-alt text-purple-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Associated Site</p>
                        <p class="font-medium text-gray-800">${site.site_name}${site.lho ? ` (${site.lho})` : ''}</p>
                    </div>
                </div>
            `;
        }
    }
    
    // Build items list
    let itemsHtml = '<div class="border rounded-lg divide-y">';
    state.selectedItems.forEach(item => {
        itemsHtml += `
            <div class="flex items-center justify-between p-3">
                <span class="text-gray-700">${item.product_name}</span>
                <span class="font-medium text-gray-800">${item.quantity} qty</span>
            </div>
        `;
    });
    itemsHtml += '</div>';
    
    detailsEl.innerHTML = destinationHtml + siteHtml + '<div class="mt-3"><p class="text-sm font-medium text-gray-700 mb-2">Items:</p>' + itemsHtml + '</div>';
    
    document.getElementById('confirm-modal').classList.remove('hidden');
}

function closeConfirmModal() {
    document.getElementById('confirm-modal').classList.add('hidden');
}

async function loadCouriers() {
    try {
        const response = await fetch(`../../api/inventory/dispatch/couriers.php`, {
            credentials: 'include'
        });
        const result = await response.json();
        
        const select = document.getElementById('courier-select');
        select.innerHTML = '<option value="">-- Select Courier Partner --</option>';
        
        if (result.success && result.data && result.data.couriers && result.data.couriers.length > 0) {
            state.couriers = result.data.couriers;
            result.data.couriers.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading couriers:', error);
    }
}

// File Upload Preview Handlers
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'lr-copy-input') {
        const label = document.getElementById('lr-file-name');
        const text = document.getElementById('lr-file-text');
        if (e.target.files.length > 0) {
            text.textContent = 'Selected: ' + e.target.files[0].name;
            label.classList.remove('hidden');
        } else {
            label.classList.add('hidden');
        }
    }
    if (e.target && e.target.id === 'pod-receipt-input') {
        const label = document.getElementById('pod-file-name');
        const text = document.getElementById('pod-file-text');
        if (e.target.files.length > 0) {
            text.textContent = 'Selected: ' + e.target.files[0].name;
            label.classList.remove('hidden');
        } else {
            label.classList.add('hidden');
        }
    }
});

async function submitDispatch() {
    const courierId = document.getElementById('courier-select').value;
    const podNumber = document.getElementById('pod-number-input').value.trim();
    const contactName = document.getElementById('contact-name-input').value.trim();
    const contactPhone = document.getElementById('contact-phone-input').value.trim();
    const lrFileInput = document.getElementById('lr-copy-input');
    const podFileInput = document.getElementById('pod-receipt-input');

    if (!courierId) {
        showToast('Please select a courier partner', 'error');
        return;
    }
    if (!podNumber) {
        showToast('Please enter LR / POD / Waybill number', 'error');
        return;
    }
    if (!contactName || !contactPhone) {
        showToast('Please enter recipient contact name and phone number', 'error');
        return;
    }
    if (!lrFileInput || lrFileInput.files.length === 0) {
        showToast('Please upload LR Copy / Courier Receipt file', 'error');
        return;
    }
    if (!podFileInput || podFileInput.files.length === 0) {
        showToast('Please upload Courier POD Receipt file', 'error');
        return;
    }

    const btn = document.getElementById('confirm-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading POD & Creating Dispatch...';
    
    try {
        const formData = new FormData();
        formData.append('sender_type', 'company');
        formData.append('sender_id', state.companyId);
        
        if (state.destinationType === 'engineer') {
            formData.append('to_user_id', state.selectedEngineerId);
        } else {
            formData.append('to_warehouse_id', state.selectedWarehouseId);
        }
        
        if (state.selectedSiteId) {
            formData.append('site_id', state.selectedSiteId);
        }
        
        formData.append('courier_id', courierId);
        formData.append('pod_number', podNumber);
        formData.append('contact_person_name', contactName);
        formData.append('contact_person_phone', contactPhone);
        formData.append('notes', document.getElementById('dispatch-notes').value || '');
        
        const items = state.selectedItems.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity
        }));
        formData.append('items', JSON.stringify(items));

        if (lrFileInput.files.length > 0) {
            formData.append('lr_copy', lrFileInput.files[0]);
        }
        if (podFileInput.files.length > 0) {
            formData.append('pod_receipt', podFileInput.files[0]);
        }

        const response = await fetch('../../api/inventory/dispatch/create.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeConfirmModal();
            showSuccessModal(result.data.dispatch?.dispatch_number || 'N/A', result.message);
            loadContractorDispatches(); // Refresh table immediately
        } else {
            showToast(result.message || 'Failed to create dispatch', 'error');
        }
    } catch (error) {
        console.error('Error creating dispatch:', error);
        showToast('Failed to create dispatch', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm & Dispatch';
    }
}

function showSuccessModal(dispatchNumber, message) {
    document.getElementById('success-dispatch-number').textContent = dispatchNumber;
    document.getElementById('success-message').textContent = message || 'Your dispatch has been created successfully.';
    document.getElementById('success-modal').classList.remove('hidden');
}

function closeSuccessModal() {
    document.getElementById('success-modal').classList.add('hidden');
    showListView();
}

function resetForm() {
    state.destinationType = null;
    state.selectedEngineerId = null;
    state.selectedWarehouseId = null;
    state.selectedSiteId = null;
    state.selectedItems = [];
    
    document.querySelectorAll('.destination-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.getElementById('engineer-selection').classList.add('hidden');
    document.getElementById('warehouse-selection').classList.add('hidden');
    document.getElementById('engineer-select').value = '';
    document.getElementById('warehouse-select').value = '';
    document.getElementById('site-select').value = '';
    document.getElementById('dispatch-notes').value = '';
    document.getElementById('selected-items-container').classList.add('hidden');
    document.getElementById('validation-messages').classList.add('hidden');
    
    renderInventoryList();
    updateSubmitButton();
    updateSummary();
}

// --- All Dispatch Records Table & Details Modal Functions ---

async function loadContractorDispatches() {
    const tbody = document.getElementById('contractor-dispatch-tbody');
    if (!tbody) return;

    try {
        const params = new URLSearchParams({
            page: state.dispatchPagination.page,
            limit: state.dispatchPagination.limit
        });
        if (state.dispatchFilters.status) {
            params.append('status', state.dispatchFilters.status);
        }
        if (state.dispatchFilters.acknowledgment_status) {
            params.append('acknowledgment_status', state.dispatchFilters.acknowledgment_status);
        }

        const response = await fetch(`../../api/inventory/dispatch/index.php?${params.toString()}`, {
            credentials: 'include'
        });
        const result = await response.json();

        if (result.success) {
            let dispatches = result.data.dispatches || [];
            
            // Calculate and update stats counters
            const totalDispatches = (result.data.pagination && result.data.pagination.total !== undefined) ? result.data.pagination.total : dispatches.length;
            const inTransitCount = dispatches.filter(d => d.status === 'in_transit').length;
            const deliveredCount = dispatches.filter(d => d.status === 'delivered').length;
            const pendingAckCount = dispatches.filter(d => d.acknowledgment_status !== 'acknowledged' && d.status !== 'cancelled').length;

            const statTotalEl = document.getElementById('stat-total-dispatches');
            const statTransitEl = document.getElementById('stat-in-transit');
            const statDeliveredEl = document.getElementById('stat-delivered');
            const statPendingAckEl = document.getElementById('stat-pending-ack');

            if (statTotalEl) statTotalEl.textContent = totalDispatches;
            if (statTransitEl) statTransitEl.textContent = inTransitCount;
            if (statDeliveredEl) statDeliveredEl.textContent = deliveredCount;
            if (statPendingAckEl) statPendingAckEl.textContent = pendingAckCount;

            // Client-side search filtering
            if (state.dispatchFilters.search) {
                const q = state.dispatchFilters.search.toLowerCase();
                dispatches = dispatches.filter(d => {
                    const dnum = (d.dispatch_number || '').toLowerCase();
                    const receiver = (d.to_user_name || d.to_company_name || d.to_warehouse_name || '').toLowerCase();
                    const site = (d.site_name || '').toLowerCase();
                    const pod = (d.pod_number || '').toLowerCase();
                    const courier = (d.courier_name || '').toLowerCase();
                    return dnum.includes(q) || receiver.includes(q) || site.includes(q) || pod.includes(q) || courier.includes(q);
                });
            }

            state.dispatches = dispatches;
            state.dispatchPagination = result.data.pagination || {
                page: 1, limit: 15, total: dispatches.length, total_pages: 1
            };

            const badgeEl = document.getElementById('contractor-dispatches-badge');
            if (badgeEl) {
                const total = state.dispatchPagination.total || dispatches.length;
                badgeEl.textContent = `${total} ${total === 1 ? 'Record' : 'Records'}`;
            }

            renderContractorDispatchesTable();
            renderContractorPagination();
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Failed to load dispatches: ${escapeHtml(result.message || 'Error')}</td></tr>`;
        }
    } catch (err) {
        console.error('Error loading contractor dispatches:', err);
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400"><i class="fas fa-exclamation-circle mr-2"></i>Error loading dispatch history</td></tr>`;
    }
}

function renderContractorDispatchesTable() {
    const tbody = document.getElementById('contractor-dispatch-tbody');
    if (!tbody) return;

    if (!state.dispatches || state.dispatches.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400 text-xl">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <p class="font-medium text-gray-600">No dispatch records found</p>
                    <p class="text-xs text-gray-400 mt-1">Dispatches you create to field engineers or return to ADV will appear here</p>
                </td>
            </tr>
        `;
        return;
    }

    const statusBadgeMap = {
        in_transit: 'bg-blue-50 text-blue-700 border-blue-200',
        delivered: 'bg-green-50 text-green-700 border-green-200',
        pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
        cancelled: 'bg-red-50 text-red-700 border-red-200'
    };

    let rowsHtml = '';
    let startIdx = (state.dispatchPagination.page - 1) * state.dispatchPagination.limit;

    state.dispatches.forEach((d, i) => {
        const rowNum = startIdx + i + 1;
        const statusClass = statusBadgeMap[d.status] || 'bg-gray-100 text-gray-700 border-gray-200';
        const formattedStatus = (d.status || 'unknown').replace('_', ' ').toUpperCase();

        // Destination display
        let destName = d.to_user_name || d.to_warehouse_name || d.to_company_name || 'N/A';
        let destIcon = d.to_user_id ? 'fa-user-hard-hat text-blue-500' : (d.to_warehouse_id ? 'fa-warehouse text-emerald-500' : 'fa-building text-purple-500');
        let destTypeLabel = d.to_user_id ? 'Engineer' : (d.to_warehouse_id ? 'ADV Warehouse' : 'Company');

        // Items display
        const items = d.items || [];
        let itemsSummaryHtml = '';
        if (items.length === 0) {
            itemsSummaryHtml = '<span class="text-gray-400 text-xs italic">No item details</span>';
        } else {
            itemsSummaryHtml = `<div class="space-y-1">`;
            items.forEach(item => {
                const serialBadge = item.serial_number 
                    ? `<span class="px-1 py-0.5 bg-blue-50 text-blue-700 font-mono text-[10px] rounded border border-blue-100 ml-1">S/N: ${escapeHtml(item.serial_number)}</span>` 
                    : '';
                itemsSummaryHtml += `
                    <div class="text-xs text-gray-700 flex items-center">
                        <span class="font-semibold text-gray-900 mr-1">${item.quantity}x</span>
                        <span class="truncate max-w-[160px]">${escapeHtml(item.product_name || 'Item #' + item.product_id)}</span>
                        ${serialBadge}
                    </div>
                `;
            });
            itemsSummaryHtml += `</div>`;
        }

        // Shipping / POD display
        let shippingHtml = '';
        if (d.courier_name || d.pod_number) {
            shippingHtml = `
                <div class="text-xs space-y-0.5">
                    ${d.courier_name ? `<p class="font-medium text-gray-800"><i class="fas fa-truck text-gray-400 mr-1 text-[11px]"></i>${escapeHtml(d.courier_name)}</p>` : ''}
                    ${d.pod_number ? `<p class="font-mono text-[11px] text-gray-600"><i class="fas fa-hashtag text-gray-400 mr-1 text-[10px]"></i>${escapeHtml(d.pod_number)}</p>` : ''}
                    <div class="flex items-center gap-1 mt-1">
                        ${d.lr_copy_path ? `<a href="../../${escapeHtml(d.lr_copy_path)}" target="_blank" class="px-1.5 py-0.5 bg-blue-50 text-primary rounded text-[10px] font-semibold hover:bg-blue-100 flex items-center" title="View LR Copy"><i class="fas fa-file-invoice mr-1"></i>LR</a>` : ''}
                        ${d.pod_receipt_path ? `<a href="../../${escapeHtml(d.pod_receipt_path)}" target="_blank" class="px-1.5 py-0.5 bg-emerald-50 text-emerald-700 rounded text-[10px] font-semibold hover:bg-emerald-100 flex items-center" title="View POD Receipt"><i class="fas fa-receipt mr-1"></i>POD</a>` : ''}
                    </div>
                </div>
            `;
        } else {
            shippingHtml = `<span class="text-gray-400 text-xs">-</span>`;
        }

        // Acknowledgment badge
        const isAck = d.acknowledgment_status === 'acknowledged';
        const ackBadge = isAck 
            ? `<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full flex items-center gap-1 w-max mt-1"><i class="fas fa-check-circle"></i>Acknowledged</span>` 
            : `<span class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-semibold rounded-full flex items-center gap-1 w-max mt-1"><i class="fas fa-clock"></i>Ack Pending</span>`;

        rowsHtml += `
            <tr class="hover:bg-blue-50/30 transition">
                <td class="px-4 py-3 text-xs text-gray-400 font-mono align-top">${rowNum}</td>
                <td class="px-4 py-3 align-top">
                    <button type="button" onclick="viewContractorDispatchDetails(${d.id})" class="font-mono font-bold text-xs text-primary hover:underline block text-left">
                        ${escapeHtml(d.dispatch_number || '#' + d.id)}
                    </button>
                    <p class="text-[11px] text-gray-500 mt-0.5"><i class="fas fa-calendar-alt text-gray-400 mr-1"></i>${d.dispatch_date || (d.created_at ? d.created_at.split(' ')[0] : 'N/A')}</p>
                    ${d.created_by_name ? `<p class="text-[10px] text-gray-400 mt-0.5"><i class="fas fa-user-edit text-gray-300 mr-1"></i>${escapeHtml(d.created_by_name)}</p>` : ''}
                </td>
                <td class="px-4 py-3 align-top">
                    <div class="flex items-start gap-1.5">
                        <i class="fas ${destIcon} text-xs mt-0.5"></i>
                        <div>
                            <p class="text-xs font-bold text-gray-800">${escapeHtml(destName)}</p>
                            <p class="text-[10px] text-gray-400">${destTypeLabel}</p>
                            ${d.site_name ? `<span class="inline-block mt-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 border border-indigo-100 rounded text-[10px] font-semibold"><i class="fas fa-map-marker-alt mr-1"></i>${escapeHtml(d.site_name)}</span>` : ''}
                            ${d.contact_person_name ? `<p class="text-[11px] text-gray-600 mt-1"><i class="fas fa-phone-alt text-gray-400 text-[10px] mr-1"></i>${escapeHtml(d.contact_person_name)} ${d.contact_person_phone ? `(${escapeHtml(d.contact_person_phone)})` : ''}</p>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 align-top">
                    ${itemsSummaryHtml}
                </td>
                <td class="px-4 py-3 align-top">
                    ${shippingHtml}
                </td>
                <td class="px-4 py-3 align-top whitespace-nowrap">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border ${statusClass} inline-block">
                        ${formattedStatus}
                    </span>
                    ${ackBadge}
                </td>
                <td class="px-4 py-3 align-top text-center">
                    <button type="button" onclick="viewContractorDispatchDetails(${d.id})" class="p-1.5 bg-gray-100 hover:bg-primary hover:text-white text-gray-600 rounded-lg text-xs transition" title="View Full Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;
}

function renderContractorPagination() {
    const infoEl = document.getElementById('contractor-pagination-info');
    const controlsEl = document.getElementById('contractor-pagination-controls');
    if (!infoEl || !controlsEl) return;

    const { page, limit, total, total_pages } = state.dispatchPagination;
    const start = total === 0 ? 0 : (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);

    infoEl.textContent = `Showing ${start} to ${end} of ${total} dispatches`;

    if (total_pages <= 1) {
        controlsEl.innerHTML = '';
        return;
    }

    let btns = '';
    btns += `
        <button type="button" onclick="goToContractorPage(${page - 1})" ${page <= 1 ? 'disabled class="px-2.5 py-1 text-xs border rounded bg-gray-100 text-gray-400 cursor-not-allowed"' : 'class="px-2.5 py-1 text-xs border rounded bg-white text-gray-700 hover:bg-gray-50"'}>
            <i class="fas fa-chevron-left"></i>
        </button>
    `;

    for (let p = 1; p <= total_pages; p++) {
        if (p === 1 || p === total_pages || (p >= page - 1 && p <= page + 1)) {
            btns += `
                <button type="button" onclick="goToContractorPage(${p})" class="px-2.5 py-1 text-xs border rounded font-semibold ${p === page ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                    ${p}
                </button>
            `;
        } else if (p === page - 2 || p === page + 2) {
            btns += `<span class="px-1 text-gray-400 text-xs">...</span>`;
        }
    }

    btns += `
        <button type="button" onclick="goToContractorPage(${page + 1})" ${page >= total_pages ? 'disabled class="px-2.5 py-1 text-xs border rounded bg-gray-100 text-gray-400 cursor-not-allowed"' : 'class="px-2.5 py-1 text-xs border rounded bg-white text-gray-700 hover:bg-gray-50"'}>
            <i class="fas fa-chevron-right"></i>
        </button>
    `;

    controlsEl.innerHTML = btns;
}

function goToContractorPage(page) {
    if (page < 1 || page > state.dispatchPagination.total_pages) return;
    state.dispatchPagination.page = page;
    loadContractorDispatches();
}

async function viewContractorDispatchDetails(dispatchId) {
    const modal = document.getElementById('contractor-view-dispatch-modal');
    const content = document.getElementById('contractor-view-dispatch-content');
    const titleNum = document.getElementById('cv-dispatch-number');
    if (!modal || !content) return;

    modal.classList.remove('hidden');
    content.innerHTML = `<div class="text-center py-12 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl text-primary mb-2"></i><p>Loading dispatch details...</p></div>`;

    try {
        const response = await fetch(`../../api/inventory/dispatch/show.php?id=${dispatchId}`, {
            credentials: 'include'
        });
        const result = await response.json();

        if (result.success && result.data && result.data.dispatch) {
            const d = result.data.dispatch;
            const items = result.data.items || [];
            if (titleNum) titleNum.textContent = d.dispatch_number || '#' + d.id;

            const isDelivered = d.status === 'delivered';
            const isAck = d.acknowledgment_status === 'acknowledged';

            let itemsTableHtml = '';
            if (items.length === 0) {
                itemsTableHtml = `<p class="text-xs text-gray-400 italic py-2">No item records attached to this dispatch</p>`;
            } else {
                itemsTableHtml = `
                    <div class="border rounded-xl overflow-hidden mt-2">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-3 py-2 font-bold text-gray-600">Product</th>
                                    <th class="px-3 py-2 font-bold text-gray-600">Qty</th>
                                    <th class="px-3 py-2 font-bold text-gray-600">Serial Number</th>
                                    <th class="px-3 py-2 font-bold text-gray-600">Condition</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                ${items.map(item => `
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-gray-800">${escapeHtml(item.product_name || 'Product #' + item.product_id)}</td>
                                        <td class="px-3 py-2 font-bold text-primary">${item.quantity || 1}</td>
                                        <td class="px-3 py-2 font-mono text-[11px] text-gray-600">${item.serial_number ? `<span class="px-1.5 py-0.5 bg-blue-50 text-blue-700 rounded border border-blue-100">${escapeHtml(item.serial_number)}</span>` : '<span class="text-gray-400">-</span>'}</td>
                                        <td class="px-3 py-2 text-gray-500">${escapeHtml(item.working_condition || item.condition || 'good')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            content.innerHTML = `
                <!-- Status Banner -->
                <div class="p-3 bg-gray-50 rounded-xl border flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${d.status === 'delivered' ? 'bg-green-100 text-green-700' : d.status === 'in_transit' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700'}">
                                ${(d.status || 'unknown').toUpperCase()}
                            </span>
                            <span class="px-2 py-0.5 rounded text-xs font-semibold ${isAck ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
                                ${isAck ? '✔ Acknowledged' : '⏳ Acknowledgment Pending'}
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Dispatch Date</span>
                        <p class="text-xs font-bold text-gray-800 mt-0.5">${d.dispatch_date || d.created_at || 'N/A'}</p>
                    </div>
                </div>

                <!-- Destination & Sender Details -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 bg-blue-50/50 border border-blue-100 rounded-xl space-y-1">
                        <p class="font-bold text-blue-900 uppercase tracking-wider text-[10px]">Recipient Details</p>
                        <p class="font-semibold text-gray-800">${escapeHtml(d.to_user_name || d.to_warehouse_name || d.to_company_name || 'N/A')}</p>
                        ${d.contact_person_name ? `<p class="text-gray-600"><i class="fas fa-user text-gray-400 mr-1"></i>${escapeHtml(d.contact_person_name)}</p>` : ''}
                        ${d.contact_person_phone ? `<p class="text-gray-600"><i class="fas fa-phone text-gray-400 mr-1"></i>${escapeHtml(d.contact_person_phone)}</p>` : ''}
                    </div>
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl space-y-1">
                        <p class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">Site Details</p>
                        <p class="font-semibold text-gray-800">${escapeHtml(d.site_name || 'No Site Linked')}</p>
                        ${d.lho ? `<p class="text-gray-500">LHO: ${escapeHtml(d.lho)}</p>` : ''}
                    </div>
                </div>

                <!-- Shipping / POD Details -->
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl space-y-2 text-xs">
                    <p class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">Shipping & Logistics</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-gray-400">Courier Partner:</span>
                            <p class="font-semibold text-gray-800">${escapeHtml(d.courier_name || 'N/A')}</p>
                        </div>
                        <div>
                            <span class="text-gray-400">LR / POD Number:</span>
                            <p class="font-mono font-semibold text-gray-800">${escapeHtml(d.pod_number || 'N/A')}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pt-2 border-t border-gray-200">
                        ${d.lr_copy_path ? `<a href="../../${escapeHtml(d.lr_copy_path)}" target="_blank" class="px-2.5 py-1 bg-blue-100 text-blue-800 rounded-lg font-semibold hover:bg-blue-200 flex items-center gap-1.5"><i class="fas fa-file-invoice"></i>View LR Copy</a>` : '<span class="text-gray-400">No LR Copy uploaded</span>'}
                        ${d.pod_receipt_path ? `<a href="../../${escapeHtml(d.pod_receipt_path)}" target="_blank" class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-lg font-semibold hover:bg-emerald-200 flex items-center gap-1.5"><i class="fas fa-receipt"></i>View POD Receipt</a>` : ''}
                    </div>
                </div>

                <!-- Dispatched Items List -->
                <div>
                    <h5 class="font-bold text-gray-800 text-xs uppercase tracking-wider">Dispatched Items (${items.length})</h5>
                    ${itemsTableHtml}
                </div>

                ${d.notes ? `
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs">
                        <span class="font-bold text-amber-900 block mb-0.5">Notes:</span>
                        <p class="text-amber-800">${escapeHtml(d.notes)}</p>
                    </div>
                ` : ''}
            `;
        } else {
            content.innerHTML = `<div class="text-center py-8 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Failed to load details</div>`;
        }
    } catch (err) {
        console.error('Error viewing dispatch details:', err);
        content.innerHTML = `<div class="text-center py-8 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Error retrieving dispatch details</div>`;
    }
}

function closeContractorViewModal() {
    const modal = document.getElementById('contractor-view-dispatch-modal');
    if (modal) modal.classList.add('hidden');
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layouts/main.php';
?>
