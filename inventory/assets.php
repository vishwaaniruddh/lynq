<?php
/**
 * Asset Management Page
 * 
 * Display current status, holder, warehouse, condition
 * Show complete movement history timeline
 * Status update UI with visual indicators
 * 
 * Requirements: 6.2, 12.2, 12.4, 6.1, 6.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission - ADV users always have access
if (!can('inventory.assets.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view assets';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Asset Management';
$currentPage = 'inventory_assets';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Assets']
];

// Get user permissions - ADV users have all permissions
$isAdv = isAdvUser();
$canUpdateStatus = can('inventory.assets.status') || $isAdv;
$canCreateRepair = can('inventory.repairs.create') || $isAdv;
$isEngineer = strtolower($currentUser['role_name'] ?? '') === 'engineer';

ob_start();
?>

<!-- QR Code and Barcode Libraries -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<style>
/* QR Code and Barcode specific styles */
.code-container {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    display: inline-block;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.code-container canvas {
    display: block;
    margin: 0 auto;
}

.code-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
}

@media (max-width: 768px) {
    .code-actions {
        flex-direction: column;
    }
    
    .code-actions button {
        width: 100%;
    }
    
    .grid.grid-cols-1.md\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
}

/* Print styles for codes */
@media print {
    .code-container {
        border: 1px solid #000;
        box-shadow: none;
    }
}
</style>

<div class="space-y-6">
    <!-- Asset List Section -->
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Serializable Assets</h3>
                <p class="text-sm text-gray-500">Track and manage serialized inventory items</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="exportAssets()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>Export
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="p-4 border-b bg-gray-50">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" id="search-input" placeholder="Search serial number..." 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <select id="warehouse-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Warehouses</option>
                    </select>
                </div>
                <div>
                    <select id="product-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Products</option>
                    </select>
                </div>
                <div>
                    <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Status</option>
                        <option value="in_stock">In Stock</option>
                        <option value="dispatched">Dispatched</option>
                        <option value="assigned">Assigned</option>
                        <option value="in_use">In Use</option>
                        <option value="returned">Returned</option>
                        <option value="under_repair">Under Repair</option>
                        <option value="scrapped">Scrapped</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div>
                    <select id="condition-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Conditions</option>
                        <option value="working">Working</option>
                        <option value="not_working">Not Working</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading indicator -->
        <div id="loading-indicator" class="hidden p-8 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
            <p class="mt-2 text-gray-500">Loading assets...</p>
        </div>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table id="assets-table" class="w-full">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Serial Number</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Warehouse</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Condition</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Dispatched To</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Dispatch Info</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="assets-tbody" class="divide-y">
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">Loading...</td>
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
</div>

<!-- Asset Detail Modal -->
<div id="asset-detail-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAssetDetailModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Asset Details</h3>
                <button onclick="closeAssetDetailModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="asset-detail-content" class="p-5 max-h-[75vh] overflow-y-auto">
                <!-- Content populated by JavaScript -->
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeAssetDetailModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="status-update-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeStatusUpdateModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Update Asset Status</h3>
                <button onclick="closeStatusUpdateModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="status-update-form" onsubmit="saveStatusUpdate(event)">
                <input type="hidden" id="status-asset-id" name="asset_id">
                <div class="p-5 space-y-4">
                    <div id="current-status-display" class="p-4 bg-gray-50 rounded-lg">
                        <!-- Current status info -->
                    </div>
                    
                    <div>
                        <label for="new-status" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                        <select id="new-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Status</option>
                        </select>
                        <p id="status-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <div>
                        <label for="new-condition" class="block text-sm font-medium text-gray-700 mb-1">Working Condition</label>
                        <select id="new-condition" name="working_condition" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">No Change</option>
                            <option value="working">Working</option>
                            <option value="not_working">Not Working</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="status-notes" name="notes" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Optional notes about this status change"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeStatusUpdateModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-status-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-save mr-2"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    assets: [],
    warehouses: [],
    products: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { serial_number: '', warehouse_id: '', product_id: '', status: '', working_condition: '' },
    permissions: {
        updateStatus: <?php echo json_encode($canUpdateStatus); ?>,
        createRepair: <?php echo json_encode($canCreateRepair); ?>,
        isEngineer: <?php echo json_encode($isEngineer); ?>
    },
    currentAsset: null
};

const API_URL = '../api/inventory/assets';

// Status configuration with colors
const STATUS_CONFIG = {
    in_stock: { label: 'In Stock', color: 'bg-green-100 text-green-700', icon: 'fa-warehouse' },
    dispatched: { label: 'Dispatched', color: 'bg-blue-100 text-blue-700', icon: 'fa-truck' },
    assigned: { label: 'Assigned', color: 'bg-purple-100 text-purple-700', icon: 'fa-user-check' },
    in_use: { label: 'In Use', color: 'bg-indigo-100 text-indigo-700', icon: 'fa-tools' },
    returned: { label: 'Returned', color: 'bg-teal-100 text-teal-700', icon: 'fa-undo' },
    under_repair: { label: 'Under Repair', color: 'bg-yellow-100 text-yellow-700', icon: 'fa-wrench' },
    scrapped: { label: 'Scrapped', color: 'bg-red-100 text-red-700', icon: 'fa-trash' },
    lost: { label: 'Lost', color: 'bg-gray-100 text-gray-700', icon: 'fa-question-circle' }
};

const CONDITION_CONFIG = {
    working: { label: 'Working', color: 'bg-green-100 text-green-700' },
    not_working: { label: 'Not Working', color: 'bg-red-100 text-red-700' }
};

// Engineer allowed statuses
const ENGINEER_STATUSES = ['in_use', 'returned'];

// Read URL parameters and apply to filters
function initFiltersFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('product_id')) {
        state.filters.product_id = urlParams.get('product_id');
    }
    if (urlParams.has('warehouse_id')) {
        state.filters.warehouse_id = urlParams.get('warehouse_id');
    }
    if (urlParams.has('status')) {
        state.filters.status = urlParams.get('status');
    }
    if (urlParams.has('working_condition')) {
        state.filters.working_condition = urlParams.get('working_condition');
    }
    if (urlParams.has('serial_number') || urlParams.has('serial')) {
        state.filters.serial_number = urlParams.get('serial_number') || urlParams.get('serial');
    }
}

// Update dropdown selections to match filters
function syncDropdownsWithFilters() {
    const warehouseSelect = document.getElementById('warehouse-filter');
    const productSelect = document.getElementById('product-filter');
    const statusSelect = document.getElementById('status-filter');
    const conditionSelect = document.getElementById('condition-filter');
    const searchInput = document.getElementById('search-input');
    
    if (warehouseSelect && state.filters.warehouse_id) {
        warehouseSelect.value = state.filters.warehouse_id;
    }
    if (productSelect && state.filters.product_id) {
        productSelect.value = state.filters.product_id;
    }
    if (statusSelect && state.filters.status) {
        statusSelect.value = state.filters.status;
    }
    if (conditionSelect && state.filters.working_condition) {
        conditionSelect.value = state.filters.working_condition;
    }
    if (searchInput && state.filters.serial_number) {
        searchInput.value = state.filters.serial_number;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', async function() {
    // Read URL parameters first
    initFiltersFromUrl();
    
    // Load dropdowns and then sync with URL params
    await Promise.all([loadWarehouses(), loadProducts()]);
    syncDropdownsWithFilters();
    
    // Load assets with filters
    await loadAssets();
    
    // If serial parameter is provided, try to open that asset's detail modal
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('serial')) {
        const serialNumber = urlParams.get('serial');
        const asset = state.assets.find(a => a.serial_number === serialNumber);
        if (asset) {
            setTimeout(() => viewAssetDetail(asset.id), 500);
        }
    }
    
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.serial_number = e.target.value;
            state.pagination.page = 1;
            loadAssets();
        }, 300);
    });
    
    document.getElementById('warehouse-filter').addEventListener('change', function(e) {
        state.filters.warehouse_id = e.target.value;
        state.pagination.page = 1;
        loadAssets();
    });
    
    document.getElementById('product-filter').addEventListener('change', function(e) {
        state.filters.product_id = e.target.value;
        state.pagination.page = 1;
        loadAssets();
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadAssets();
    });
    
    document.getElementById('condition-filter').addEventListener('change', function(e) {
        state.filters.working_condition = e.target.value;
        state.pagination.page = 1;
        loadAssets();
    });
}

// Load warehouses for dropdown
async function loadWarehouses() {
    try {
        const response = await fetch('../api/inventory/warehouses/index.php?limit=100', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.warehouses = data.data.warehouses || [];
            populateWarehouseDropdown();
            // Sync dropdown with filter after populating
            if (state.filters.warehouse_id) {
                document.getElementById('warehouse-filter').value = state.filters.warehouse_id;
            }
        }
    } catch (error) {
        console.error('Error loading warehouses:', error);
    }
}

function populateWarehouseDropdown() {
    const select = document.getElementById('warehouse-filter');
    if (select) {
        select.innerHTML = '<option value="">All Warehouses</option>' +
            state.warehouses.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
    }
}

// Load products for dropdown
async function loadProducts() {
    try {
        const response = await fetch('../api/inventory/products/index.php?limit=500&is_serializable=1', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.products = (data.data.products || []).filter(p => p.is_serializable == 1);
            populateProductDropdown();
            // Sync dropdown with filter after populating
            if (state.filters.product_id) {
                document.getElementById('product-filter').value = state.filters.product_id;
            }
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function populateProductDropdown() {
    const select = document.getElementById('product-filter');
    if (select) {
        select.innerHTML = '<option value="">All Products</option>' +
            state.products.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
    }
}

// Load assets from API
async function loadAssets() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.warehouse_id) params.append('warehouse_id', state.filters.warehouse_id);
        if (state.filters.product_id) params.append('product_id', state.filters.product_id);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.working_condition) params.append('working_condition', state.filters.working_condition);
        if (state.filters.serial_number) params.append('serial_number', state.filters.serial_number);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.assets = data.data.assets;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load assets');
        }
    } catch (error) {
        console.error('Error loading assets:', error);
        showError('Failed to load assets. Please try again.');
    } finally {
        showLoading(false);
    }
}


// Render table
function renderTable() {
    const tbody = document.getElementById('assets-tbody');
    
    if (state.assets.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-barcode text-4xl mb-3 text-gray-300"></i>
                    <p>No assets found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.assets.map((asset, index) => {
        const statusConfig = STATUS_CONFIG[asset.status] || { label: asset.status, color: 'bg-gray-100 text-gray-700', icon: 'fa-circle' };
        const conditionConfig = CONDITION_CONFIG[asset.working_condition] || { label: asset.working_condition || '-', color: 'bg-gray-100 text-gray-700' };
        
        const canUpdate = state.permissions.updateStatus && !['scrapped', 'lost'].includes(asset.status);
        const canRepair = state.permissions.createRepair && asset.working_condition === 'not_working' && asset.is_repairable && asset.status !== 'under_repair';
        
        // Dispatch info for dispatched assets
        const dispatchInfo = asset.dispatch_info;
        let dispatchedTo = '-';
        let dispatchDetails = '-';
        let warehouseDisplay = escapeHtml(asset.warehouse_name || '-');
        
        if (asset.status === 'dispatched') {
            // Add "(Dispatched)" suffix to warehouse name
            if (asset.warehouse_name) {
                warehouseDisplay = `${escapeHtml(asset.warehouse_name)} <span class="text-orange-500 text-[9px]">(Dispatched)</span>`;
            }
            
            // Use the new dispatched_to_name field if available
            if (asset.dispatched_to_name) {
                const iconMap = {
                    'company': 'fa-building',
                    'user': 'fa-user',
                    'warehouse': 'fa-warehouse'
                };
                const colorMap = {
                    'company': 'text-purple-600',
                    'user': 'text-blue-600',
                    'warehouse': 'text-green-600'
                };
                const icon = iconMap[asset.dispatched_to_type] || 'fa-arrow-right';
                const color = colorMap[asset.dispatched_to_type] || 'text-gray-600';
                dispatchedTo = `<span class="${color}"><i class="fas ${icon} mr-1"></i>${escapeHtml(asset.dispatched_to_name)}</span>`;
            } else if (dispatchInfo) {
                // Fallback to dispatch_info if dispatched_to_name not set
                if (dispatchInfo.to_company_name) {
                    dispatchedTo = `<span class="text-purple-600"><i class="fas fa-building mr-1"></i>${escapeHtml(dispatchInfo.to_company_name)}</span>`;
                } else if (dispatchInfo.to_user_name) {
                    dispatchedTo = `<span class="text-blue-600"><i class="fas fa-user mr-1"></i>${escapeHtml(dispatchInfo.to_user_name)}</span>`;
                } else if (dispatchInfo.to_warehouse_name) {
                    dispatchedTo = `<span class="text-green-600"><i class="fas fa-warehouse mr-1"></i>${escapeHtml(dispatchInfo.to_warehouse_name)}</span>`;
                }
            }
            
            // Build dispatch details using courier_info if available
            const details = [];
            if (dispatchInfo) {
                if (dispatchInfo.dispatch_number) {
                    details.push(`<span class="text-primary font-mono text-[10px]">${escapeHtml(dispatchInfo.dispatch_number)}</span>`);
                }
                if (dispatchInfo.dispatch_date) {
                    details.push(`<span class="text-gray-500"><i class="fas fa-calendar mr-1"></i>${dispatchInfo.dispatch_date}</span>`);
                }
            }
            
            // Use courier_info object if available
            if (asset.courier_info) {
                if (asset.courier_info.courier_name) {
                    details.push(`<span class="text-orange-600"><i class="fas fa-truck mr-1"></i>${escapeHtml(asset.courier_info.courier_name)}</span>`);
                }
                if (asset.courier_info.pod_number) {
                    details.push(`<span class="text-indigo-600 font-mono text-[10px]">POD: ${escapeHtml(asset.courier_info.pod_number)}</span>`);
                }
            } else if (dispatchInfo) {
                // Fallback to dispatch_info
                if (dispatchInfo.courier_name) {
                    details.push(`<span class="text-orange-600"><i class="fas fa-truck mr-1"></i>${escapeHtml(dispatchInfo.courier_name)}</span>`);
                }
                if (dispatchInfo.pod_number) {
                    details.push(`<span class="text-indigo-600 font-mono text-[10px]">POD: ${escapeHtml(dispatchInfo.pod_number)}</span>`);
                }
            }
            dispatchDetails = details.length > 0 ? details.join('<br>') : '-';
        }
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <span class="font-medium text-xs text-primary cursor-pointer hover:underline font-mono" onclick="viewAssetDetail(${asset.id})">${escapeHtml(asset.serial_number)}</span>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(asset.product_name || '-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${warehouseDisplay}</td>
            <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2 py-0.5 ${statusConfig.color} rounded-full text-[10px] font-medium">
                    <i class="fas ${statusConfig.icon} mr-1"></i>${statusConfig.label}
                </span>
            </td>
            <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 ${conditionConfig.color} rounded-full text-[10px]">${conditionConfig.label}</span>
            </td>
            <td class="px-4 py-2.5 text-xs">${dispatchedTo}</td>
            <td class="px-4 py-2.5 text-[10px]">${dispatchDetails}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center space-x-1">
                    <button onclick="viewAssetDetail(${asset.id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="View Details">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${canUpdate ? `
                    <button onclick="openStatusUpdateModal(${asset.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Update Status">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `}).join('');
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
    html += `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
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
    
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadAssets();
}

// View asset detail
async function viewAssetDetail(assetId) {
    try {
        const response = await fetch(`${API_URL}/show.php?id=${assetId}`, { credentials: 'include' });
        const data = await response.json();
        
        if (!data.success) {
            showError(data.error?.message || 'Failed to load asset details');
            return;
        }
        
        const asset = data.data.asset;
        state.currentAsset = asset;
        
        const statusConfig = STATUS_CONFIG[asset.status] || { label: asset.status, color: 'bg-gray-100 text-gray-700', icon: 'fa-circle' };
        const conditionConfig = CONDITION_CONFIG[asset.working_condition] || { label: asset.working_condition || '-', color: 'bg-gray-100 text-gray-700' };
        
        const content = document.getElementById('asset-detail-content');
        content.innerHTML = `
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Serial Number</p>
                    <p class="font-semibold text-gray-800">${escapeHtml(asset.serial_number)}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Product</p>
                    <p class="font-semibold text-gray-800">${escapeHtml(asset.product_name || '-')}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Status</p>
                    <span class="inline-flex items-center px-2.5 py-1 ${statusConfig.color} rounded-full text-xs font-medium">
                        <i class="fas ${statusConfig.icon} mr-1.5"></i>${statusConfig.label}
                    </span>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Condition</p>
                    <span class="px-2 py-1 ${conditionConfig.color} rounded-full text-xs">${conditionConfig.label}</span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Warehouse</p>
                    <p class="font-medium text-gray-800">${escapeHtml(asset.warehouse_name || '-')}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Current Holder</p>
                    <p class="font-medium text-gray-800">${escapeHtml(asset.current_holder_name || asset.current_holder_type || '-')}</p>
                </div>
            </div>
            
            <!-- QR Code and Barcode Section -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-800">Asset Codes</h4>
                    <div class="code-actions">
                        <button onclick="downloadQRCode('${escapeHtml(asset.serial_number)}')" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-download mr-1"></i>Download QR
                        </button>
                        <button onclick="downloadBarcode('${escapeHtml(asset.serial_number)}')" class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-download mr-1"></i>Download Barcode
                        </button>
                        <button onclick="printCodes('${escapeHtml(asset.serial_number)}')" class="px-3 py-1.5 bg-purple-600 text-white text-xs rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-print mr-1"></i>Print
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- QR Code -->
                    <div class="text-center">
                        <h5 class="text-sm font-medium text-gray-700 mb-3">QR Code</h5>
                        <div class="code-container relative">
                            <div id="qr-loading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75">
                                <i class="fas fa-spinner fa-spin text-gray-400"></i>
                            </div>
                            <canvas id="qr-code-canvas" class="max-w-full" width="150" height="150"></canvas>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Scan to view asset details</p>
                    </div>
                    
                    <!-- Barcode -->
                    <div class="text-center">
                        <h5 class="text-sm font-medium text-gray-700 mb-3">Barcode</h5>
                        <div class="code-container relative">
                            <div id="barcode-loading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75">
                                <i class="fas fa-spinner fa-spin text-gray-400"></i>
                            </div>
                            <canvas id="barcode-canvas" class="max-w-full" width="200" height="80"></canvas>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 font-mono">${escapeHtml(asset.serial_number)}</p>
                    </div>
                </div>
            </div>
        `;
        
        // Generate QR Code and Barcode after content is rendered
        setTimeout(() => {
            console.log('Attempting to generate codes for:', asset.serial_number);
            console.log('QRCode library available:', typeof QRCode !== 'undefined');
            console.log('JsBarcode library available:', typeof JsBarcode !== 'undefined');
            
            // Check if canvases exist
            const qrCanvas = document.getElementById('qr-code-canvas');
            const barcodeCanvas = document.getElementById('barcode-canvas');
            
            if (!qrCanvas) {
                console.error('QR Code canvas not found');
                return;
            }
            if (!barcodeCanvas) {
                console.error('Barcode canvas not found');
                return;
            }
            
            generateQRCode(asset.serial_number, asset.id);
            generateBarcode(asset.serial_number);
        }, 100); // Reduced timeout for faster loading
        
        document.getElementById('asset-detail-modal').classList.remove('hidden');
    } catch (error) {
        console.error('Error loading asset details:', error);
        showError('Failed to load asset details');
    }
}

function closeAssetDetailModal() {
    document.getElementById('asset-detail-modal').classList.add('hidden');
}

// Status update modal
async function openStatusUpdateModal(assetId) {
    const asset = state.assets.find(a => a.id === assetId);
    if (!asset) return;
    
    state.currentAsset = asset;
    document.getElementById('status-asset-id').value = assetId;
    
    const statusConfig = STATUS_CONFIG[asset.status] || { label: asset.status, color: 'bg-gray-100 text-gray-700' };
    document.getElementById('current-status-display').innerHTML = `
        <p class="text-sm text-gray-600">Current Status: <span class="px-2 py-1 ${statusConfig.color} rounded-full text-xs ml-2">${statusConfig.label}</span></p>
        <p class="text-sm text-gray-600 mt-2">Serial: <span class="font-medium">${escapeHtml(asset.serial_number)}</span></p>
    `;
    
    // Populate status options
    const statusSelect = document.getElementById('new-status');
    let options = '<option value="">Select Status</option>';
    
    const allowedStatuses = state.permissions.isEngineer ? ENGINEER_STATUSES : Object.keys(STATUS_CONFIG);
    allowedStatuses.forEach(status => {
        if (status !== asset.status) {
            const config = STATUS_CONFIG[status];
            options += `<option value="${status}">${config.label}</option>`;
        }
    });
    statusSelect.innerHTML = options;
    
    document.getElementById('status-update-modal').classList.remove('hidden');
}

function closeStatusUpdateModal() {
    document.getElementById('status-update-modal').classList.add('hidden');
    document.getElementById('status-update-form').reset();
}

async function saveStatusUpdate(event) {
    event.preventDefault();
    
    const assetId = document.getElementById('status-asset-id').value;
    const newStatus = document.getElementById('new-status').value;
    const newCondition = document.getElementById('new-condition').value;
    const notes = document.getElementById('status-notes').value;
    
    if (!newStatus && !newCondition) {
        showError('Please select a new status or condition');
        return;
    }
    
    try {
        const body = { asset_id: assetId };
        if (newStatus) body.status = newStatus;
        if (newCondition) body.working_condition = newCondition;
        if (notes) body.notes = notes;
        
        const response = await fetch(`${API_URL}/status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Asset status updated successfully', 'success');
            closeStatusUpdateModal();
            loadAssets();
        } else {
            showError(data.error?.message || 'Failed to update status');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showError('Failed to update status');
    }
}

// Export assets
async function exportAssets() {
    try {
        const params = new URLSearchParams();
        if (state.filters.warehouse_id) params.append('warehouse_id', state.filters.warehouse_id);
        if (state.filters.product_id) params.append('product_id', state.filters.product_id);
        if (state.filters.status) params.append('status', state.filters.status);
        
        window.open(`../api/inventory/export/index.php?type=assets&${params}`, '_blank');
    } catch (error) {
        console.error('Error exporting:', error);
        showError('Failed to export assets');
    }
}

// QR Code and Barcode Generation Functions
function generateQRCode(serialNumber, assetId) {
    const canvas = document.getElementById('qr-code-canvas');
    const loading = document.getElementById('qr-loading');
    
    if (!canvas) {
        console.error('QR Code canvas not found');
        return;
    }
    
    // Create public asset URL for QR code (no authentication required)
    const assetUrl = `${window.location.origin}/public/asset.php?serial=${encodeURIComponent(serialNumber)}`;
    console.log('Generating QR code for URL:', assetUrl);
    
    // Generate QR code using qrcode.js library
    if (typeof QRCode !== 'undefined') {
        try {
            QRCode.toCanvas(canvas, assetUrl, {
                width: 150,
                height: 150,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            }, function (error) {
                if (loading) loading.style.display = 'none';
                
                if (error) {
                    console.error('QR Code generation error:', error);
                    // Fallback to online service
                    generateQRCodeFallback(canvas, assetUrl);
                } else {
                    console.log('QR Code generated successfully');
                }
            });
        } catch (error) {
            if (loading) loading.style.display = 'none';
            console.error('QR Code library error:', error);
            generateQRCodeFallback(canvas, assetUrl);
        }
    } else {
        if (loading) loading.style.display = 'none';
        console.warn('QRCode library not loaded, using fallback');
        generateQRCodeFallback(canvas, assetUrl);
    }
}

function generateQRCodeFallback(canvas, assetUrl) {
    console.log('Using QR Code fallback method');
    
    // Fallback: Use online QR code service
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(assetUrl)}`;
    const img = new Image();
    img.crossOrigin = 'anonymous';
    
    img.onload = function() {
        try {
            const ctx = canvas.getContext('2d');
            canvas.width = 150;
            canvas.height = 150;
            ctx.drawImage(img, 0, 0, 150, 150);
            console.log('QR Code fallback generated successfully');
        } catch (error) {
            console.error('QR Code fallback canvas error:', error);
            drawQRCodePlaceholder(canvas);
        }
    };
    
    img.onerror = function() {
        console.error('QR Code fallback service failed');
        drawQRCodePlaceholder(canvas);
    };
    
    // Set timeout for fallback
    setTimeout(() => {
        if (!img.complete) {
            console.warn('QR Code service timeout, using placeholder');
            drawQRCodePlaceholder(canvas);
        }
    }, 5000);
    
    img.src = qrUrl;
}

function drawQRCodePlaceholder(canvas) {
    // Final fallback: draw placeholder
    const ctx = canvas.getContext('2d');
    canvas.width = 150;
    canvas.height = 150;
    
    // Draw border
    ctx.fillStyle = '#f3f4f6';
    ctx.fillRect(0, 0, 150, 150);
    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 2;
    ctx.strokeRect(1, 1, 148, 148);
    
    // Draw QR-like pattern
    ctx.fillStyle = '#374151';
    for (let i = 0; i < 10; i++) {
        for (let j = 0; j < 10; j++) {
            if ((i + j) % 2 === 0) {
                ctx.fillRect(15 + i * 12, 15 + j * 12, 10, 10);
            }
        }
    }
    
    // Add text
    ctx.fillStyle = '#6b7280';
    ctx.font = '10px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('QR Code', 75, 140);
}

function generateBarcode(serialNumber) {
    const canvas = document.getElementById('barcode-canvas');
    const loading = document.getElementById('barcode-loading');
    
    if (!canvas) {
        console.error('Barcode canvas not found');
        return;
    }
    
    console.log('Generating barcode for:', serialNumber);
    
    // Generate barcode using JsBarcode library
    if (typeof JsBarcode !== 'undefined') {
        try {
            JsBarcode(canvas, serialNumber, {
                format: "CODE128",
                width: 2,
                height: 60,
                displayValue: true,
                fontSize: 12,
                margin: 10,
                background: "#ffffff",
                lineColor: "#000000"
            });
            
            if (loading) loading.style.display = 'none';
            console.log('Barcode generated successfully');
        } catch (error) {
            if (loading) loading.style.display = 'none';
            console.error('Barcode generation error:', error);
            generateBarcodeFallback(canvas, serialNumber);
        }
    } else {
        if (loading) loading.style.display = 'none';
        console.warn('JsBarcode library not loaded, using fallback');
        generateBarcodeFallback(canvas, serialNumber);
    }
}

function generateBarcodeFallback(canvas, serialNumber) {
    console.log('Using barcode fallback method');
    
    // Fallback: Draw barcode-like representation
    const ctx = canvas.getContext('2d');
    canvas.width = 200;
    canvas.height = 80;
    
    // Clear canvas with white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 200, 80);
    
    // Draw border
    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 1;
    ctx.strokeRect(0, 0, 200, 80);
    
    // Draw barcode-like pattern
    ctx.fillStyle = '#000000';
    const barWidth = 2;
    let x = 10;
    
    // Create pattern based on serial number
    for (let i = 0; i < serialNumber.length; i++) {
        const charCode = serialNumber.charCodeAt(i);
        const numBars = (charCode % 5) + 3; // 3-7 bars per character
        
        for (let j = 0; j < numBars; j++) {
            const height = (j % 2 === 0) ? 40 : 35;
            const y = 10;
            
            if ((i + j) % 2 === 0) {
                ctx.fillRect(x, y, barWidth, height);
            }
            x += barWidth + 1;
            
            if (x > 180) break; // Don't exceed canvas width
        }
        
        if (x > 180) break;
    }
    
    // Add serial number text
    ctx.fillStyle = '#000000';
    ctx.font = '12px monospace';
    ctx.textAlign = 'center';
    ctx.fillText(serialNumber, 100, 70);
    
    console.log('Barcode fallback generated successfully');
}

function downloadQRCode(serialNumber) {
    const canvas = document.getElementById('qr-code-canvas');
    if (!canvas) return;
    
    // Create download link
    const link = document.createElement('a');
    link.download = `QR_${serialNumber}.png`;
    link.href = canvas.toDataURL();
    link.click();
}

function downloadBarcode(serialNumber) {
    const canvas = document.getElementById('barcode-canvas');
    if (!canvas) return;
    
    // Create download link
    const link = document.createElement('a');
    link.download = `Barcode_${serialNumber}.png`;
    link.href = canvas.toDataURL();
    link.click();
}

function printCodes(serialNumber) {
    const qrCanvas = document.getElementById('qr-code-canvas');
    const barcodeCanvas = document.getElementById('barcode-canvas');
    
    if (!qrCanvas || !barcodeCanvas) return;
    
    // Create print window
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Asset Codes - ${serialNumber}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    text-align: center;
                }
                .code-section { 
                    margin: 30px 0; 
                    page-break-inside: avoid;
                }
                .serial-number { 
                    font-size: 18px; 
                    font-weight: bold; 
                    margin: 20px 0;
                    font-family: monospace;
                }
                .code-title { 
                    font-size: 14px; 
                    margin: 10px 0; 
                    color: #666;
                }
                canvas { 
                    border: 1px solid #ddd; 
                    margin: 10px;
                }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h2>Asset Identification Codes</h2>
            <div class="serial-number">Serial Number: ${serialNumber}</div>
            
            <div class="code-section">
                <div class="code-title">QR Code</div>
                <canvas id="print-qr"></canvas>
            </div>
            
            <div class="code-section">
                <div class="code-title">Barcode</div>
                <canvas id="print-barcode"></canvas>
            </div>
            
            <div class="no-print" style="margin-top: 30px;">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Copy canvas content to print window
    printWindow.onload = function() {
        const printQR = printWindow.document.getElementById('print-qr');
        const printBarcode = printWindow.document.getElementById('print-barcode');
        
        // Copy QR code
        const qrCtx = printQR.getContext('2d');
        printQR.width = qrCanvas.width;
        printQR.height = qrCanvas.height;
        qrCtx.drawImage(qrCanvas, 0, 0);
        
        // Copy barcode
        const barcodeCtx = printBarcode.getContext('2d');
        printBarcode.width = barcodeCanvas.width;
        printBarcode.height = barcodeCanvas.height;
        barcodeCtx.drawImage(barcodeCanvas, 0, 0);
        
        // Auto-print after a short delay
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };
}

// Utility functions
function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    const table = document.getElementById('assets-table');
    if (show) {
        indicator.classList.remove('hidden');
        table.classList.add('opacity-50');
    } else {
        indicator.classList.add('hidden');
        table.classList.remove('opacity-50');
    }
}

function showError(message) {
    showToast(message, 'error');
}

function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75"><i class="fas fa-times"></i></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
