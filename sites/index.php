<?php
/**
 * Site Management Page
 * 
 * Implements table with pagination, search, status filter, LHO filter
 * Add create/edit modal with form validation
 * Add view and delete confirmation modals
 * Include action buttons for view, edit, delete, delegate
 * 
 * Requirements: 1.1
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Site Management';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <!-- Header -->
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-800">Site Management</h3>
            <p class="text-xs text-gray-500 mt-0.5">Manage site records for delegation and feasibility</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="bulk_upload.php" class="px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center text-xs font-medium">
                <i class="fas fa-file-upload mr-1.5"></i>Bulk Upload
            </a>
            <a href="add.php" class="px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center text-xs font-medium">
                <i class="fas fa-plus mr-1.5"></i>Add Site
            </a>
            <button onclick="exportSites()" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center text-xs font-medium">
                <i class="fas fa-file-excel mr-1.5"></i>Export
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
            <div onclick="filterByStatus('')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-blue-300 hover:shadow-md transition-all" id="card-total">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-map-marker-alt text-blue-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Total Sites</p>
                        <p id="total-count" class="text-lg font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByStatus('active')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-emerald-300 hover:shadow-md transition-all" id="card-active">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Active</p>
                        <p id="active-count" class="text-lg font-semibold text-emerald-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByStatus('inactive')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-red-300 hover:shadow-md transition-all" id="card-inactive">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-red-50 to-red-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-times-circle text-red-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Inactive</p>
                        <p id="inactive-count" class="text-lg font-semibold text-red-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByDelegation('delegated')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-purple-300 hover:shadow-md transition-all" id="card-delegated">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-share-alt text-purple-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Delegated</p>
                        <p id="delegated-count" class="text-lg font-semibold text-purple-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-2">
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="search-input" placeholder="Search sites by name, address, city..." 
                        class="w-full pl-9 pr-4 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                </div>
            </div>
            <div>
                <select id="lho-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All LHOs</option>
                </select>
            </div>
            <div>
                <select id="status-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <select id="delegation-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Delegation</option>
                    <option value="delegated">Delegated</option>
                    <option value="not_delegated">Not Delegated</option>
                </select>
            </div>
            <div>
                <select id="material-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Material</option>
                    <option value="generated">Generated</option>
                    <option value="not_generated">Not Generated</option>
                </select>
            </div>
            <div>
                <select id="installation-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Installation</option>
                    <option value="done">Done</option>
                    <option value="not_done">Not Done</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading sites...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="sites-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">
                        ID <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="site_name">
                        Site Name <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="lho">
                        LHO <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="city">
                        Location <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">
                        Status <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Delegation</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Feasibility</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Installation</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Material</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="sites-tbody" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="10" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="site-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeSiteModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 sticky top-0 bg-white">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Site</h3>
                <button onclick="closeSiteModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="site-form" onsubmit="saveSite(event)">
                <input type="hidden" id="site-id" value="">
                <div class="p-5 space-y-4">
                    <!-- Row 1: Site Name & LHO -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="site-name" class="block text-sm font-medium text-gray-700 mb-1">Site Name <span class="text-red-500">*</span></label>
                            <input type="text" id="site-name" name="site_name" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter site name">
                            <p id="site_name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="site-lho" class="block text-sm font-medium text-gray-700 mb-1">LHO <span class="text-red-500">*</span></label>
                            <input type="text" id="site-lho" name="lho" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter LHO">
                            <p id="lho-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    
                    <!-- Row 2: Bank & Customer -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="site-bank" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                            <input type="text" id="site-bank" name="bank_name"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter bank name">
                        </div>
                        <div>
                            <label for="site-customer" class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                            <input type="text" id="site-customer" name="customer_name"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter customer name">
                        </div>
                    </div>

                    <!-- Row 3: City, State, Country -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="site-city" class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                            <input type="text" id="site-city" name="city" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter city">
                            <p id="city-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="site-state" class="block text-sm font-medium text-gray-700 mb-1">State <span class="text-red-500">*</span></label>
                            <input type="text" id="site-state" name="state" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter state">
                            <p id="state-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="site-country" class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                            <input type="text" id="site-country" name="country" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter country">
                            <p id="country-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    
                    <!-- Row 4: Zone & Address -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="site-zone" class="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                            <input type="text" id="site-zone" name="zone"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter zone">
                        </div>
                        <div>
                            <label for="site-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="site-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Row 5: Address -->
                    <div>
                        <label for="site-address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea id="site-address" name="address" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter full address"></textarea>
                    </div>

                    <!-- Row 6: Coordinates -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="site-latitude" class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                            <input type="number" step="any" id="site-latitude" name="latitude" min="-90" max="90"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="e.g., 28.6139">
                            <p id="latitude-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="site-longitude" class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                            <input type="number" step="any" id="site-longitude" name="longitude" min="-180" max="180"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="e.g., 77.2090">
                            <p id="longitude-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    
                    <!-- Map Preview (optional) -->
                    <div id="map-preview" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location Preview</label>
                        <div id="map-container" class="h-48 bg-gray-100 rounded-lg flex items-center justify-center">
                            <p class="text-gray-500">Enter coordinates to preview location</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl sticky bottom-0">
                    <button type="button" onclick="closeSiteModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Site Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5 max-h-[60vh] overflow-y-auto">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Generate Material Request Modal (Task 4.1) -->
<div id="material-request-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeMaterialRequestModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 sticky top-0 bg-white">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Generate Material Request</h3>
                    <p class="text-sm text-gray-500">Site: <span id="material-request-site-name" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeMaterialRequestModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <!-- Material Master Selection (Task 4.2) -->
                <div>
                    <label for="material-master-select" class="block text-sm font-medium text-gray-700 mb-1">
                        Select Material Master <span class="text-red-500">*</span>
                    </label>
                    <select id="material-master-select" onchange="onMaterialMasterChange()" 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">-- Select a Material Master --</option>
                    </select>
                    <p id="material-master-error" class="mt-1 text-sm text-red-500 hidden">Please select a Material Master</p>
                </div>
                
                <!-- Product Preview Section (Task 4.2) -->
                <div id="product-preview-section" class="hidden">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-700">Products in this Material Master</h4>
                        <span id="product-count-badge" class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium"></span>
                    </div>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">SKU</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Category</th>
                                    <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="product-preview-tbody" class="divide-y">
                                <!-- Products will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span>These products will be requested for the selected site.</span>
                        </div>
                    </div>
                </div>
                
                <!-- Empty State when no master selected -->
                <div id="no-master-selected" class="py-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
                    <p>Select a Material Master to preview products</p>
                </div>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl sticky bottom-0">
                <button type="button" onclick="closeMaterialRequestModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" onclick="confirmMaterialRequest()" id="confirm-material-request-btn" 
                    class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i class="fas fa-check mr-2"></i>Confirm Request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    sites: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', lho: '', delegation: '', material: '', installation: '' },
    sort: { field: 'site_name', direction: 'asc' },
    lhos: [],
    counts: { total: 0, active: 0, inactive: 0, delegated: 0 },
    // Material Request state (Task 4)
    materialRequest: {
        selectedSiteId: null,
        selectedSiteName: '',
        selectedMasterId: null,
        materialMasters: [],
        selectedMasterData: null
    }
};

// API base URLs
const API_URL = '../api/sites/index.php';
const MATERIAL_MASTERS_API = '../api/material-masters';
const MATERIAL_REQUESTS_API = '../api/material-requests';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadSites();
    setupEventListeners();
    loadMaterialMastersForSelection();
});


// Setup event listeners
function setupEventListeners() {
    // Search with debounce
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadSites();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        updateCardHighlights();
        loadSites();
    });
    
    // LHO filter
    document.getElementById('lho-filter').addEventListener('change', function(e) {
        state.filters.lho = e.target.value;
        state.pagination.page = 1;
        loadSites();
    });
    
    // Delegation filter
    document.getElementById('delegation-filter').addEventListener('change', function(e) {
        state.filters.delegation = e.target.value;
        state.pagination.page = 1;
        updateCardHighlights();
        loadSites();
    });
    
    // Material filter
    document.getElementById('material-filter').addEventListener('change', function(e) {
        state.filters.material = e.target.value;
        state.pagination.page = 1;
        loadSites();
    });
    
    // Installation filter
    document.getElementById('installation-filter').addEventListener('change', function(e) {
        state.filters.installation = e.target.value;
        state.pagination.page = 1;
        loadSites();
    });
    
    // Column sorting
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const field = this.dataset.sort;
            if (state.sort.field === field) {
                state.sort.direction = state.sort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort.field = field;
                state.sort.direction = 'asc';
            }
            loadSites();
            updateSortIndicators();
        });
    });
}

// Filter by status (from card click)
function filterByStatus(status) {
    state.filters.status = status;
    document.getElementById('status-filter').value = status;
    state.pagination.page = 1;
    updateCardHighlights();
    loadSites();
}

// Filter by delegation (from card click)
function filterByDelegation(delegation) {
    state.filters.delegation = delegation;
    document.getElementById('delegation-filter').value = delegation;
    state.pagination.page = 1;
    updateCardHighlights();
    loadSites();
}

// Update card highlights based on active filters
function updateCardHighlights() {
    // Reset all cards
    document.querySelectorAll('[id^="card-"]').forEach(card => {
        card.classList.remove('ring-2', 'ring-blue-400', 'ring-emerald-400', 'ring-red-400', 'ring-purple-400');
    });
    
    // Highlight active filter card
    if (state.filters.status === '') {
        // No status filter - could highlight total if no other filters
    } else if (state.filters.status === 'active') {
        document.getElementById('card-active').classList.add('ring-2', 'ring-emerald-400');
    } else if (state.filters.status === 'inactive') {
        document.getElementById('card-inactive').classList.add('ring-2', 'ring-red-400');
    }
    
    if (state.filters.delegation === 'delegated') {
        document.getElementById('card-delegated').classList.add('ring-2', 'ring-purple-400');
    }
}

// Update sort indicators
function updateSortIndicators() {
    document.querySelectorAll('[data-sort]').forEach(th => {
        const icon = th.querySelector('i');
        if (th.dataset.sort === state.sort.field) {
            icon.className = state.sort.direction === 'asc' ? 'fas fa-sort-up ml-1' : 'fas fa-sort-down ml-1';
        } else {
            icon.className = 'fas fa-sort ml-1';
        }
    });
}

// Load sites from API
async function loadSites() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit,
            orderBy: state.sort.field,
            orderDir: state.sort.direction
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.lho) params.append('lho', state.filters.lho);
        if (state.filters.delegation) params.append('delegation', state.filters.delegation);
        if (state.filters.material) params.append('material', state.filters.material);
        if (state.filters.installation) params.append('installation', state.filters.installation);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.sites = data.data.sites;
            // Fetch material status for sites
            await loadMaterialStatusForSites();
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            state.lhos = data.data.lhos || [];
            state.counts = data.data.counts || { total: 0, active: 0, inactive: 0, delegated: 0 };
            
            // Calculate delegated count from sites if not provided
            if (!state.counts.delegated) {
                state.counts.delegated = state.sites.filter(s => s.delegation_id).length;
            }
            
            renderTable();
            renderPagination();
            updateStats();
            updateLHOFilter();
        } else {
            showError(data.error?.message || 'Failed to load sites');
        }
    } catch (error) {
        console.error('Error loading sites:', error);
        showError('Failed to load sites. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Fetch material status for sites from API (Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6)
async function loadMaterialStatusForSites() {
    try {
        // Get all site IDs
        const siteIds = state.sites.map(s => s.id);
        if (siteIds.length === 0) return;
        
        // Fetch material requests to get status for each site
        const response = await fetch(`${MATERIAL_REQUESTS_API}/list.php?limit=500`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const requests = data.data.material_requests || [];
            
            // Create a map of site_id to material request status
            const statusMap = {};
            requests.forEach(req => {
                // Only keep the most recent/active request per site
                if (!statusMap[req.site_id] || req.status !== 'received') {
                    statusMap[req.site_id] = {
                        material_status: req.status,
                        material_request_date: req.requested_at,
                        material_request_id: req.id,
                        material_master_id: req.material_master_id,
                        material_master_name: req.material_master_name
                    };
                }
            });
            
            // Update sites with material status
            state.sites = state.sites.map(site => {
                const materialData = statusMap[site.id] || {
                    material_status: 'not_requested',
                    material_request_date: null,
                    material_request_id: null,
                    material_master_id: null,
                    material_master_name: null
                };
                return { ...site, ...materialData };
            });
        }
    } catch (error) {
        console.error('Error loading material status:', error);
        // Set default status for all sites on error
        state.sites = state.sites.map(site => ({
            ...site,
            material_status: 'not_requested',
            material_request_date: null,
            material_request_id: null,
            material_master_id: null,
            material_master_name: null
        }));
    }
}

// Update stats display
function updateStats() {
    document.getElementById('total-count').textContent = state.counts.total || 0;
    document.getElementById('active-count').textContent = state.counts.active || 0;
    document.getElementById('inactive-count').textContent = state.counts.inactive || 0;
    document.getElementById('delegated-count').textContent = state.counts.delegated || 0;
}

// Update LHO filter dropdown
function updateLHOFilter() {
    const select = document.getElementById('lho-filter');
    const currentValue = select.value;
    
    // Keep first option
    select.innerHTML = '<option value="">All LHOs</option>';
    
    state.lhos.forEach(lho => {
        const option = document.createElement('option');
        option.value = lho;
        option.textContent = lho;
        if (lho === currentValue) option.selected = true;
        select.appendChild(option);
    });
}

// Render table
function renderTable() {
    const tbody = document.getElementById('sites-tbody');
    
    if (state.sites.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-4 py-10 text-center text-gray-400">
                    <i class="fas fa-map-marker-alt text-3xl mb-2 text-gray-300"></i>
                    <p class="text-sm">No sites found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = state.sites.map(site => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${site.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-map-marker-alt text-blue-500 text-xs"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="font-medium text-gray-800 text-xs block truncate">${escapeHtml(site.site_name)}</span>
                        ${site.bank_name ? `<p class="text-[10px] text-gray-400 truncate">${escapeHtml(site.bank_name)}</p>` : ''}
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px] font-medium">${escapeHtml(site.lho)}</span>
            </td>
            <td class="px-4 py-2.5">
                <div class="text-xs text-gray-600">${escapeHtml(site.city)}, ${escapeHtml(site.state)}</div>
                <div class="text-[10px] text-gray-400">${escapeHtml(site.country)}</div>
            </td>
            <td class="px-4 py-2.5">
                ${site.status === 'active' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                ${getDelegationBadge(site)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getFeasibilityStatusBadge(site)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getInstallationStatusBadge(site)}
            </td>
            <td class="px-4 py-2.5">
                ${getMaterialStatusBadge(site)}
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <button onclick="viewSite(${site.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    <button onclick="editSite(${site.id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ${site.delegation_id 
                        ? `<button onclick="cancelDelegation(${site.delegation_id}, '${escapeHtml(site.site_name)}')" class="p-1.5 text-orange-400 hover:text-orange-600 hover:bg-orange-50 rounded transition-colors" title="Cancel Delegation">
                            <i class="fas fa-undo text-xs"></i>
                           </button>`
                        : `<a href="delegate.php?site_id=${site.id}" class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors" title="Delegate">
                            <i class="fas fa-share-alt text-xs"></i>
                           </a>`
                    }
                    ${getInstallationButton(site)}
                    <button onclick="confirmDelete(${site.id}, '${escapeHtml(site.site_name)}')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Get material status badge (Requirements 2.2, 2.3, 2.4, 2.5, 2.6)
function getMaterialStatusBadge(site) {
    const status = site.material_status || 'not_requested';
    const requestDate = site.material_request_date ? formatDate(site.material_request_date) : '';
    
    switch (status) {
        case 'not_requested':
            return `<div class="flex items-center gap-1.5">
                <span class="whitespace-nowrap px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-[10px]">Not Requested</span>
                <button onclick="openMaterialRequestModal(${site.id}, '${escapeHtml(site.site_name)}')" 
                    class="whitespace-nowrap px-2 py-0.5 bg-blue-500 text-white rounded text-[10px] hover:bg-blue-600 transition-colors">
                    <i class="fas fa-plus mr-0.5"></i>Generate
                </button>
            </div>`;
        case 'requested':
            return `<span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium" title="Requested on ${requestDate}">
                <i class="fas fa-clock mr-0.5"></i>Requested
            </span>`;
        case 'approved':
            return `<span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium">
                <i class="fas fa-check mr-0.5"></i>Approved
            </span>`;
        case 'dispatched':
            return `<span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-[10px] font-medium">
                <i class="fas fa-truck mr-0.5"></i>Dispatched
            </span>`;
        case 'received':
            return `<span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium">
                <i class="fas fa-check-double mr-0.5"></i>Received
            </span>`;
        default:
            return `<span class="px-2 py-0.5 bg-gray-100 text-gray-400 rounded-full text-[10px]">Unknown</span>`;
    }
}

// Get feasibility status badge
function getFeasibilityStatusBadge(site) {
    const status = site.feasibility_status || 'not_started';
    
    switch (status) {
        case 'not_started':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1"></span>Not Started</span>';
        case 'pending_eta':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full mr-1"></span>Pending ETA</span>';
        case 'eta_submitted':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-orange-50 text-orange-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-orange-400 rounded-full mr-1"></span>ETA Submitted</span>';
        case 'ada_submitted':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-purple-400 rounded-full mr-1"></span>ADA Submitted</span>';
        case 'feasibility_completed':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1"></span>Completed</span>';
        case 'pending_contractor_review':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-cyan-50 text-cyan-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-cyan-400 rounded-full mr-1"></span>Pending Review</span>';
        case 'contractor_approved':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-teal-50 text-teal-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-teal-500 rounded-full mr-1"></span>Contractor OK</span>';
        case 'contractor_rejected':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Rejected</span>';
        case 'adv_approved':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>ADV Approved</span>';
        case 'adv_rejected':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-rose-50 text-rose-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-rose-400 rounded-full mr-1"></span>ADV Rejected</span>';
        default:
            return '<span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1"></span>-</span>';
    }
}

// Get installation status badge
function getInstallationStatusBadge(site) {
    const status = site.installation_status || 'not_started';
    
    switch (status) {
        case 'not_started':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1"></span>Not Started</span>';
        case 'pending':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full mr-1"></span>Pending</span>';
        case 'in_progress':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1"></span>In Progress</span>';
        case 'pending_review':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-purple-400 rounded-full mr-1"></span>Pending Review</span>';
        case 'completed':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Completed</span>';
        case 'rejected':
            return '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Rejected</span>';
        default:
            return '<span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1"></span>-</span>';
    }
}

// ===========================================
// Material Request Modal Functions (Task 4)
// ===========================================

// Load Material Masters from API for selection
async function loadMaterialMastersForSelection() {
    try {
        const response = await fetch(`${MATERIAL_MASTERS_API}/list.php?status=active&limit=100`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.materialRequest.materialMasters = data.data.material_masters || [];
            updateMaterialMastersDropdown();
        } else {
            console.error('Failed to load material masters:', data.message);
        }
    } catch (error) {
        console.error('Error loading material masters:', error);
    }
}

// Update Material Masters dropdown
function updateMaterialMastersDropdown() {
    const select = document.getElementById('material-master-select');
    if (!select) return;
    
    // Clear existing options except the first one
    select.innerHTML = '<option value="">-- Select a Material Master --</option>';
    
    // Add active Material Masters
    state.materialRequest.materialMasters.forEach(master => {
        const option = document.createElement('option');
        option.value = master.id;
        option.textContent = `${master.name} (${master.product_count || 0} products)`;
        select.appendChild(option);
    });
}

// Open material request modal (Task 4.1)
function openMaterialRequestModal(siteId, siteName) {
    // Check if site already has an active request (Task 4.3 - prevent duplicates)
    const site = state.sites.find(s => s.id === siteId);
    if (site && site.material_status && site.material_status !== 'not_requested') {
        showToast('This site already has an active material request', 'warning');
        return;
    }
    
    // Store selected site info
    state.materialRequest.selectedSiteId = siteId;
    state.materialRequest.selectedSiteName = siteName;
    state.materialRequest.selectedMasterId = null;
    
    // Reset modal state
    document.getElementById('material-request-site-name').textContent = siteName;
    document.getElementById('material-master-select').value = '';
    document.getElementById('product-preview-section').classList.add('hidden');
    document.getElementById('no-master-selected').classList.remove('hidden');
    document.getElementById('confirm-material-request-btn').disabled = true;
    document.getElementById('material-master-error').classList.add('hidden');
    
    // Show modal
    document.getElementById('material-request-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close material request modal
function closeMaterialRequestModal() {
    document.getElementById('material-request-modal').classList.add('hidden');
    document.body.style.overflow = '';
    
    // Reset state
    state.materialRequest.selectedSiteId = null;
    state.materialRequest.selectedSiteName = '';
    state.materialRequest.selectedMasterId = null;
}

// Handle Material Master selection change (Task 4.2)
async function onMaterialMasterChange() {
    const select = document.getElementById('material-master-select');
    const masterId = parseInt(select.value);
    const previewSection = document.getElementById('product-preview-section');
    const noMasterSelected = document.getElementById('no-master-selected');
    const confirmBtn = document.getElementById('confirm-material-request-btn');
    const errorEl = document.getElementById('material-master-error');
    
    // Hide error
    errorEl.classList.add('hidden');
    
    if (!masterId) {
        // No master selected
        previewSection.classList.add('hidden');
        noMasterSelected.classList.remove('hidden');
        confirmBtn.disabled = true;
        state.materialRequest.selectedMasterId = null;
        state.materialRequest.selectedMasterData = null;
        return;
    }
    
    // Fetch master details from API
    try {
        const response = await fetch(`${MATERIAL_MASTERS_API}/detail.php?id=${masterId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const master = data.data.material_master;
            
            // Store selected master
            state.materialRequest.selectedMasterId = masterId;
            state.materialRequest.selectedMasterData = master;
            
            // Show product preview
            noMasterSelected.classList.add('hidden');
            previewSection.classList.remove('hidden');
            
            // Update product count badge
            const itemCount = master.items ? master.items.length : 0;
            document.getElementById('product-count-badge').textContent = `${itemCount} products`;
            
            // Render products table
            renderProductPreview(master);
            
            // Enable confirm button
            confirmBtn.disabled = false;
        } else {
            showToast(data.message || 'Failed to load material master details', 'error');
            previewSection.classList.add('hidden');
            noMasterSelected.classList.remove('hidden');
            confirmBtn.disabled = true;
        }
    } catch (error) {
        console.error('Error loading master details:', error);
        showToast('Failed to load material master details', 'error');
        previewSection.classList.add('hidden');
        noMasterSelected.classList.remove('hidden');
        confirmBtn.disabled = true;
    }
}

// Render product preview table (Task 4.2)
function renderProductPreview(master) {
    const tbody = document.getElementById('product-preview-tbody');
    const items = master.items || [];
    
    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="px-4 py-4 text-center text-gray-500">No products in this Material Master</td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = items.map(item => {
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-box text-blue-500 text-sm"></i>
                        </div>
                        <span class="font-medium text-gray-800">${escapeHtml(item.product_name || 'Unknown')}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-600 text-sm">${escapeHtml(item.product_sku || '-')}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">${escapeHtml(item.category_name || '-')}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <span class="font-semibold text-primary">${item.quantity}</span>
                </td>
            </tr>
        `;
    }).join('');
}

// Confirm material request (Task 4.3)
async function confirmMaterialRequest() {
    const { selectedSiteId, selectedMasterId, selectedMasterData } = state.materialRequest;
    
    // Validate
    if (!selectedSiteId) {
        showToast('No site selected', 'error');
        return;
    }
    
    if (!selectedMasterId) {
        document.getElementById('material-master-error').classList.remove('hidden');
        return;
    }
    
    const confirmBtn = document.getElementById('confirm-material-request-btn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    try {
        const response = await fetch(`${MATERIAL_REQUESTS_API}/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                site_id: selectedSiteId,
                material_master_id: selectedMasterId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update site's material status in local state
            const siteIndex = state.sites.findIndex(s => s.id === selectedSiteId);
            if (siteIndex !== -1) {
                state.sites[siteIndex] = {
                    ...state.sites[siteIndex],
                    material_status: 'requested',
                    material_request_date: new Date().toISOString(),
                    material_request_id: data.data.material_request.id,
                    material_master_id: selectedMasterId,
                    material_master_name: selectedMasterData?.name || 'Unknown'
                };
            }
            
            // Close modal
            closeMaterialRequestModal();
            
            // Show success toast
            showToast('Material request generated successfully', 'success');
            
            // Refresh table to reflect new status
            renderTable();
        } else {
            if (data.code === 'DUPLICATE_REQUEST') {
                showToast('This site already has an active material request', 'warning');
            } else {
                showToast(data.message || 'Failed to create material request', 'error');
            }
        }
    } catch (error) {
        console.error('Error creating material request:', error);
        showToast('Failed to create material request', 'error');
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm Request';
    }
}

// Get delegation status badge
function getDelegationBadge(site) {
    if (!site.delegation_id) {
        return '<span class="whitespace-nowrap px-2 py-0.5 bg-gray-100 text-gray-400 rounded-full text-[10px]"><i class="fas fa-minus mr-0.5"></i>Not Delegated</span>';
    }
    
    if (site.delegation_status === 'pending') {
        return `<span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium" title="Delegated to ${escapeHtml(site.contractor_name || 'Contractor')}">
            <i class="fas fa-clock mr-0.5"></i>Pending
        </span>`;
    }
    
    if (site.delegation_status === 'accepted') {
        return `<span class="whitespace-nowrap px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium" title="Accepted by ${escapeHtml(site.contractor_name || 'Contractor')}">
            <i class="fas fa-check mr-0.5"></i>Delegated
        </span>`;
    }
    
    return '<span class="px-2 py-0.5 bg-gray-100 text-gray-400 rounded-full text-[10px]">Unknown</span>';
}

// Get installation button based on feasibility status
function getInstallationButton(site) {
    // If installation already exists, show link to view it
    if (site.installation_id) {
        return `<a href="../installation/view.php?id=${site.installation_id}" class="p-2 text-blue-500 hover:text-blue-700" title="View Installation">
            <i class="fas fa-tools"></i>
        </a>`;
    }
    
    // If feasibility is ADV-approved and no installation exists, show initiate button
    // Link to delegation page instead of directly initiating (Requirements 1.1, 1.2, 1.6, 1.7)
    if (site.feasibility_approval_status === 'adv_approved' && site.feasibility_check_id) {
        return `<a href="../installation/delegate.php?site_id=${site.id}&feasibility_id=${site.feasibility_check_id}" class="p-2 text-green-500 hover:text-green-700" title="Initiate Installation">
            <i class="fas fa-play-circle"></i>
        </a>`;
    }
    
    // If feasibility exists but not approved, show status indicator
    if (site.feasibility_check_id) {
        return `<span class="p-2 text-gray-400" title="Feasibility: ${site.feasibility_approval_status || 'pending'}">
            <i class="fas fa-hourglass-half"></i>
        </span>`;
    }
    
    // No feasibility check yet
    return '';
}

// Initiate installation for a site
async function initiateInstallation(siteId, feasibilityId) {
    if (!confirm('Are you sure you want to initiate installation for this site?')) {
        return;
    }
    
    try {
        const response = await fetch('../api/installation/initiate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                site_id: siteId,
                feasibility_id: feasibilityId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Installation initiated successfully');
            // Redirect to installation form
            setTimeout(() => {
                window.location.href = `../installation/form.php?id=${data.data.installation_id}`;
            }, 1000);
        } else {
            showError(data.error?.message || 'Failed to initiate installation');
        }
    } catch (error) {
        console.error('Error initiating installation:', error);
        showError('Failed to initiate installation. Please try again.');
    }
}

// Cancel delegation
async function cancelDelegation(delegationId, siteName) {
    if (!confirm(`Are you sure you want to cancel the delegation for "${siteName}"? This will remove the site from the contractor's list.`)) {
        return;
    }
    
    try {
        const response = await fetch('../api/delegations/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                action: 'cancel',
                delegation_id: delegationId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Delegation cancelled successfully', 'success');
            loadSites();
        } else {
            showError(data.error?.message || 'Failed to cancel delegation');
        }
    } catch (error) {
        console.error('Error cancelling delegation:', error);
        showError('Failed to cancel delegation. Please try again.');
    }
}

// Render pagination
function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    const start = (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    
    document.getElementById('pagination-info').textContent = 
        total > 0 ? `Showing ${start} to ${end} of ${total} entries` : 'No entries';
    
    const controls = document.getElementById('pagination-controls');
    
    if (total_pages <= 1) {
        controls.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    const maxPages = 5;
    let startPage = Math.max(1, page - Math.floor(maxPages / 2));
    let endPage = Math.min(total_pages, startPage + maxPages - 1);
    
    if (endPage - startPage < maxPages - 1) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    if (startPage > 1) {
        html += `<button onclick="goToPage(1)" class="px-3 py-1 rounded border hover:bg-gray-100">1</button>`;
        if (startPage > 2) html += `<span class="px-2">...</span>`;
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button onclick="goToPage(${i})" 
            class="px-3 py-1 rounded border ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">
            ${i}
        </button>`;
    }
    
    if (endPage < total_pages) {
        if (endPage < total_pages - 1) html += `<span class="px-2">...</span>`;
        html += `<button onclick="goToPage(${total_pages})" class="px-3 py-1 rounded border hover:bg-gray-100">${total_pages}</button>`;
    }
    
    // Next button
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

// Go to page
function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadSites();
}

// Open create modal
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Site';
    document.getElementById('site-id').value = '';
    document.getElementById('site-form').reset();
    document.getElementById('site-status').value = 'active';
    clearErrors();
    document.getElementById('site-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Edit site
function editSite(id) {
    const site = state.sites.find(s => s.id === id);
    if (!site) return;
    
    document.getElementById('modal-title').textContent = 'Edit Site';
    document.getElementById('site-id').value = site.id;
    document.getElementById('site-name').value = site.site_name || '';
    document.getElementById('site-lho').value = site.lho || '';
    document.getElementById('site-bank').value = site.bank_name || '';
    document.getElementById('site-customer').value = site.customer_name || '';
    document.getElementById('site-city').value = site.city || '';
    document.getElementById('site-state').value = site.state || '';
    document.getElementById('site-country').value = site.country || '';
    document.getElementById('site-zone').value = site.zone || '';
    document.getElementById('site-address').value = site.address || '';
    document.getElementById('site-latitude').value = site.latitude || '';
    document.getElementById('site-longitude').value = site.longitude || '';
    document.getElementById('site-status').value = site.status || 'active';
    clearErrors();
    document.getElementById('site-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close site modal
function closeSiteModal() {
    document.getElementById('site-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Save site
async function saveSite(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('site-id').value;
    const data = {
        action: id ? 'update' : 'create',
        site_name: document.getElementById('site-name').value.trim(),
        lho: document.getElementById('site-lho').value.trim(),
        bank_name: document.getElementById('site-bank').value.trim(),
        customer_name: document.getElementById('site-customer').value.trim(),
        city: document.getElementById('site-city').value.trim(),
        state: document.getElementById('site-state').value.trim(),
        country: document.getElementById('site-country').value.trim(),
        zone: document.getElementById('site-zone').value.trim(),
        address: document.getElementById('site-address').value.trim(),
        latitude: document.getElementById('site-latitude').value,
        longitude: document.getElementById('site-longitude').value,
        status: document.getElementById('site-status').value
    };
    
    if (id) data.id = parseInt(id);
    
    // Client-side validation
    if (!data.site_name) { showFieldError('site_name', 'Site name is required'); return; }
    if (!data.lho) { showFieldError('lho', 'LHO is required'); return; }
    if (!data.city) { showFieldError('city', 'City is required'); return; }
    if (!data.state) { showFieldError('state', 'State is required'); return; }
    if (!data.country) { showFieldError('country', 'Country is required'); return; }
    
    // Validate coordinates
    if (data.latitude !== '' && (parseFloat(data.latitude) < -90 || parseFloat(data.latitude) > 90)) {
        showFieldError('latitude', 'Latitude must be between -90 and 90'); return;
    }
    if (data.longitude !== '' && (parseFloat(data.longitude) < -180 || parseFloat(data.longitude) > 180)) {
        showFieldError('longitude', 'Longitude must be between -180 and 180'); return;
    }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeSiteModal();
            showSuccess(result.message || 'Site saved successfully');
            loadSites();
        } else {
            if (result.errors) {
                Object.keys(result.errors).forEach(field => {
                    const error = Array.isArray(result.errors[field]) ? result.errors[field][0] : result.errors[field];
                    showFieldError(field, typeof error === 'object' ? error.message : error);
                });
            } else {
                showError(result.error?.message || result.message || 'Failed to save site');
            }
        }
    } catch (error) {
        console.error('Error saving site:', error);
        showError('Failed to save site. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

// View site
function viewSite(id) {
    const site = state.sites.find(s => s.id === id);
    if (!site) return;
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-map-marker-alt text-3xl text-blue-500"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">ID</p>
                    <p class="font-medium">${site.id}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p>${site.status === 'active' 
                        ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>'
                        : '<span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Inactive</span>'
                    }</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Site Name</p>
                    <p class="font-medium">${escapeHtml(site.site_name)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">LHO</p>
                    <p class="font-medium">${escapeHtml(site.lho)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Zone</p>
                    <p class="font-medium">${site.zone ? escapeHtml(site.zone) : '-'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Bank</p>
                    <p class="font-medium">${site.bank_name ? escapeHtml(site.bank_name) : '-'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Customer</p>
                    <p class="font-medium">${site.customer_name ? escapeHtml(site.customer_name) : '-'}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Location</p>
                    <p class="font-medium">${escapeHtml(site.city)}, ${escapeHtml(site.state)}, ${escapeHtml(site.country)}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Address</p>
                    <p class="font-medium">${site.address ? escapeHtml(site.address) : '-'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Latitude</p>
                    <p class="font-medium">${site.latitude || '-'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Longitude</p>
                    <p class="font-medium">${site.longitude || '-'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created</p>
                    <p class="font-medium">${formatDate(site.created_at)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Updated</p>
                    <p class="font-medium">${formatDate(site.updated_at)}</p>
                </div>
            </div>
            ${site.latitude && site.longitude ? `
            <div class="mt-4 pt-4 border-t">
                <a href="https://www.google.com/maps?q=${site.latitude},${site.longitude}" target="_blank" 
                   class="inline-flex items-center text-primary hover:underline">
                    <i class="fas fa-external-link-alt mr-2"></i>View on Google Maps
                </a>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('view-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close view modal
function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Confirm delete
function confirmDelete(id, name) {
    openConfirmModal(
        'Delete Site',
        `Are you sure you want to delete "${name}"? This will set the site status to deleted.`,
        function() {
            deleteSite(id);
        }
    );
}

// Delete site
async function deleteSite(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'Site deleted successfully');
            loadSites();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete site');
        }
    } catch (error) {
        console.error('Error deleting site:', error);
        showError('Failed to delete site. Please try again.');
    }
}

// Export sites
async function exportSites() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.lho) params.append('lho', state.filters.lho);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.sites, 'sites_export.csv');
            showSuccess('Sites exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export sites');
        }
    } catch (error) {
        console.error('Error exporting sites:', error);
        showError('Failed to export sites. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Site Name', 'LHO', 'Bank', 'Customer', 'City', 'State', 'Country', 'Zone', 'Address', 'Latitude', 'Longitude', 'Status', 'Created At'];
    const rows = data.map(site => [
        site.id,
        `"${(site.site_name || '').replace(/"/g, '""')}"`,
        `"${(site.lho || '').replace(/"/g, '""')}"`,
        `"${(site.bank_name || '').replace(/"/g, '""')}"`,
        `"${(site.customer_name || '').replace(/"/g, '""')}"`,
        `"${(site.city || '').replace(/"/g, '""')}"`,
        `"${(site.state || '').replace(/"/g, '""')}"`,
        `"${(site.country || '').replace(/"/g, '""')}"`,
        `"${(site.zone || '').replace(/"/g, '""')}"`,
        `"${(site.address || '').replace(/"/g, '""')}"`,
        site.latitude || '',
        site.longitude || '',
        site.status,
        site.created_at
    ]);
    
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Utility functions
function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('sites-table').classList.toggle('hidden', show);
}

function showError(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) {
        CRM.showAlert(message, 'error');
    } else {
        showToast(message, 'error');
    }
}

function showSuccess(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) {
        CRM.showAlert(message, 'success');
    } else {
        showToast(message, 'success');
    }
}

function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2 animate-fade-in`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 5000);
}

function showFieldError(field, message) {
    const errorEl = document.getElementById(`${field}-error`);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
