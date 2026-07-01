<?php
/**
 * Dispatch Management Page
 * 
 * Features:
 * - Warehouse-based product filtering with stock availability
 * - Serial number picker for serializable items
 * - Courier selection, POD, contact details
 * - File attachments (LR Copy, POD Receipt)
 * 
 * Requirements: 5.1, 5.3, 5.5
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission - ADV users always have access
if (!can('inventory.dispatch.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view dispatches';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Dispatch Management';
$currentPage = 'inventory_dispatch';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Dispatch']
];

// Get user permissions
$isAdv = isAdvUser();
$isContractor = isContractorAdmin();
$canCreate = can('inventory.dispatch.create') || $isAdv || $isContractor;
$canAcknowledge = can('inventory.dispatch.acknowledge') || $isAdv || $isContractor;

ob_start();
?>

<div class="space-y-6">
    <!-- Dispatch List Section -->
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Dispatch Management</h3>
                <p class="text-sm text-gray-500">Create and track inventory dispatches</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($canCreate): ?>
                <button onclick="openDispatchModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                    <i class="fas fa-paper-plane mr-2"></i>New Dispatch
                </button>
                <?php endif; ?>
                <button onclick="exportDispatches()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>Export
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="p-4 border-b bg-gray-50">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" id="search-input" placeholder="Search dispatch number..." 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <select id="warehouse-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Warehouses</option>
                    </select>
                </div>
                <div>
                    <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_transit">In Transit</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table id="dispatch-table" class="w-full">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="w-12 px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Dispatch Info</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Receiver</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Shipping</th>
                        <th class="w-24 px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="w-20 px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="dispatch-tbody" class="divide-y">
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div id="pagination-info" class="text-sm text-gray-500"></div>
            <div id="pagination-controls" class="flex items-center gap-2"></div>
        </div>
    </div>
</div>

<!-- Create Dispatch Modal -->
<div id="dispatch-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDispatchModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full relative z-10 max-h-[95vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Create New Dispatch</h3>
                <button onclick="closeDispatchModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="dispatch-form" onsubmit="saveDispatch(event)" enctype="multipart/form-data">
                <div class="p-5 space-y-4 overflow-y-auto" style="max-height: calc(95vh - 180px);">
                    <!-- Source & Date Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Warehouse <span class="text-red-500">*</span></label>
                            <select id="dispatch-from-warehouse" name="from_warehouse_id" required onchange="onWarehouseChange()"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select Source Warehouse</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dispatch Date <span class="text-red-500">*</span></label>
                            <input type="date" id="dispatch-date" name="dispatch_date" value="<?php echo date('Y-m-d'); ?>" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <!-- Destination Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Destination Type <span class="text-red-500">*</span></label>
                        <div class="flex gap-4">
                            <label class="flex items-center"><input type="radio" name="destination_type" value="company" checked onchange="onDestinationTypeChange()"><span class="ml-2">Company</span></label>
                            <label class="flex items-center"><input type="radio" name="destination_type" value="user" onchange="onDestinationTypeChange()"><span class="ml-2">User/Engineer</span></label>
                            <label class="flex items-center"><input type="radio" name="destination_type" value="warehouse" onchange="onDestinationTypeChange()"><span class="ml-2">Warehouse</span></label>
                        </div>
                    </div>
                    
                    <!-- Destination Fields -->
                    <div id="dest-company-field">
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Company <span class="text-red-500">*</span></label>
                        <select id="dispatch-to-company" name="to_company_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Destination Company</option>
                        </select>
                    </div>
                    <div id="dest-user-field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">To User <span class="text-red-500">*</span></label>
                        <select id="dispatch-to-user" name="to_user_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Destination User</option>
                        </select>
                    </div>
                    <div id="dest-warehouse-field" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Warehouse <span class="text-red-500">*</span></label>
                        <select id="dispatch-to-warehouse" name="to_warehouse_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Destination Warehouse</option>
                        </select>
                    </div>
                    
                    <!-- Items Section -->
                    <div class="border-t pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-sm font-medium text-gray-700">Items <span class="text-red-500">*</span></label>
                            <button type="button" onclick="addDispatchItem()" class="px-3 py-1 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 text-sm">
                                <i class="fas fa-plus mr-1"></i>Add Item
                            </button>
                        </div>
                        <div id="dispatch-items-container" class="space-y-3">
                            <p id="no-warehouse-msg" class="text-sm text-gray-500 italic">Please select a warehouse first to add items</p>
                        </div>
                    </div>
                    
                    <!-- Courier & Shipping Details -->
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Shipping Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Courier</label>
                                <select id="dispatch-courier" name="courier_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                    <option value="">Select Courier</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">POD Number</label>
                                <input type="text" id="dispatch-pod" name="pod_number" placeholder="Enter POD number"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Person Details -->
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Contact Person Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person Name</label>
                                <input type="text" id="dispatch-contact-name" name="contact_person_name" placeholder="Enter contact name"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                                <input type="text" id="dispatch-contact-phone" name="contact_person_phone" placeholder="Enter contact number"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attachments -->
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Attachments</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">LR Copy</label>
                                <input type="file" id="dispatch-lr-copy" name="lr_copy" accept=".pdf,.jpg,.jpeg,.png"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">POD Receipt</label>
                                <input type="file" id="dispatch-pod-receipt" name="pod_receipt" accept=".pdf,.jpg,.jpeg,.png"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="dispatch-notes" name="notes" rows="2" placeholder="Optional notes"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeDispatchModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition"><i class="fas fa-paper-plane mr-2"></i>Create Dispatch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Serial Number Picker Modal -->
<div id="serial-picker-modal" class="hidden fixed inset-0 z-[60] overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeSerialPickerModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Select Serial Numbers</h3>
                <button onclick="closeSerialPickerModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="mb-4">
                    <input type="text" id="serial-search" placeholder="Search serial numbers..." oninput="filterSerialNumbers()"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div id="serial-list" class="max-h-60 overflow-y-auto space-y-2 border rounded-lg p-2">
                    <p class="text-gray-500 text-sm text-center py-4">No serial numbers available</p>
                </div>
                <div class="mt-4 flex items-center justify-between text-sm">
                    <span id="serial-selected-count" class="text-gray-600">0 selected</span>
                    <span id="serial-available-count" class="text-gray-500">Available: 0</span>
                </div>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeSerialPickerModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button type="button" onclick="confirmSerialSelection()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-check mr-2"></i>Confirm Selection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Dispatch Modal -->
<div id="view-dispatch-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewDispatchModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Dispatch Details</h3>
                <button onclick="closeViewDispatchModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-dispatch-content" class="p-5 max-h-[70vh] overflow-y-auto"></div>
            <div id="view-dispatch-actions" class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewDispatchModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Close</button>
                <button id="process-transit-btn" onclick="processDispatch('in_transit')" class="hidden px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-truck mr-2"></i>Mark In Transit
                </button>
                <button id="process-delivered-btn" onclick="processDispatch('delivered')" class="hidden px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-box-open mr-2"></i>Mark Delivered
                </button>
                <button id="process-cancel-btn" onclick="processDispatch('cancelled')" class="hidden px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-times mr-2"></i>Cancel Dispatch
                </button>
                <button id="acknowledge-btn" onclick="acknowledgeDispatch()" class="hidden px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Acknowledge Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    dispatches: [],
    warehouses: [],
    warehouseStock: [], // Stock for selected warehouse
    warehouseAssets: [], // Assets for selected warehouse
    allWarehouseStock: [], // Stock from all warehouses (for material requests)
    allWarehouseAssets: [], // Assets from all warehouses (for material requests)
    couriers: [],
    companies: [],
    users: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', from_warehouse_id: '', status: '' },
    permissions: { create: <?php echo json_encode($canCreate); ?>, acknowledge: <?php echo json_encode($canAcknowledge); ?> },
    currentDispatch: null,
    dispatchItems: [],
    serialPicker: { itemIndex: null, productId: null, availableAssets: [], selectedAssets: [], isMaterialRequest: false, maxSelection: null },
    materialRequestId: null,
    materialRequestItems: [],
    materialRequestSiteId: null,
    materialRequestSiteName: null
};

const API_URL = '../api/inventory/dispatch';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadWarehouses();
    loadCouriers();
    loadCompanies();
    loadUsers();
    loadDispatches();
    setupEventListeners();
    
    // Check if coming from material request
    checkMaterialRequestParam();
});

// Check for material_request_id parameter and pre-fill dispatch form
async function checkMaterialRequestParam() {
    const urlParams = new URLSearchParams(window.location.search);
    const materialRequestId = urlParams.get('material_request_id');
    
    if (materialRequestId) {
        try {
            const response = await fetch(`../api/material-requests/detail.php?id=${materialRequestId}`, { credentials: 'include' });
            const data = await response.json();
            
            if (data.success) {
                const request = data.data.material_request;
                // Store material request ID for later use
                state.materialRequestId = materialRequestId;
                
                // Wait for warehouses to load, then open modal with pre-filled data
                setTimeout(() => {
                    openDispatchModalForMaterialRequest(request);
                }, 500);
            } else {
                showToast('Failed to load material request details', 'error');
            }
        } catch (error) {
            console.error('Error loading material request:', error);
            showToast('Failed to load material request', 'error');
        }
    }
}

// Open dispatch modal pre-filled with material request data
async function openDispatchModalForMaterialRequest(request) {
    document.getElementById('dispatch-form').reset();
    document.getElementById('dispatch-date').value = new Date().toISOString().split('T')[0];
    state.dispatchItems = [];
    onDestinationTypeChange();
    
    // Store request items for reference
    state.materialRequestItems = request.items || [];
    state.materialRequestSiteId = request.site_id;
    state.materialRequestSiteName = request.site_name || 'Unknown Site';
    
    // Hide the "From Warehouse" dropdown for material request dispatches
    const warehouseRow = document.getElementById('dispatch-from-warehouse').closest('.grid');
    if (warehouseRow) {
        warehouseRow.querySelector('div:first-child').innerHTML = `
            <label class="block text-sm font-medium text-gray-700 mb-1">Material Request</label>
            <div class="px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 font-medium">
                #${request.id} - ${escapeHtml(request.site_name || 'Unknown Site')}
            </div>
        `;
    }
    
    // Load stock from ALL warehouses to find where products are available
    const container = document.getElementById('dispatch-items-container');
    container.innerHTML = `
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
            <p class="mt-2 text-gray-500">Loading stock availability...</p>
        </div>
    `;
    
    document.getElementById('dispatch-modal').classList.remove('hidden');
    
    try {
        // Load stock from all warehouses
        const [stockRes, assetsRes] = await Promise.all([
            fetch(`../api/inventory/stock/index.php?limit=1000`, { credentials: 'include' }),
            fetch(`../api/inventory/assets/index.php?status=in_stock&limit=1000`, { credentials: 'include' })
        ]);
        
        const stockData = await stockRes.json();
        const assetsData = await assetsRes.json();
        
        state.allWarehouseStock = stockData.success ? (stockData.data.stock || []) : [];
        state.allWarehouseAssets = assetsData.success ? (assetsData.data.assets || []) : [];
        
        // Pre-fill items from material request
        prefillMaterialRequestItems(request);
        
    } catch (error) {
        console.error('Error loading stock:', error);
        showToast('Failed to load stock availability', 'error');
        container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load stock. Please try again.</p>';
    }
    
    // Add note about material request
    document.getElementById('dispatch-notes').value = `Material Request #${request.id} for site: ${request.site_name || 'Unknown'}`;
}

// Pre-fill dispatch items from material request
function prefillMaterialRequestItems(request) {
    const items = request.items || [];
    state.dispatchItems = [];
    
    items.forEach(item => {
        // Find stock availability for this product across all warehouses
        const stockOptions = findStockForProduct(item.product_id);
        
        // Check if product is serializable
        const isSerializable = state.allWarehouseAssets.some(a => a.product_id == item.product_id);
        
        state.dispatchItems.push({
            productId: item.product_id,
            productName: item.product_name,
            categoryName: item.category_name,
            requestedQuantity: item.quantity_requested || item.quantity || 1,
            quantity: item.quantity_requested || item.quantity || 1,
            isSerializable: isSerializable,
            selectedAssets: [],
            stockOptions: stockOptions, // Available warehouses with stock
            selectedWarehouseId: stockOptions.length > 0 ? stockOptions[0].warehouseId : null
        });
    });
    
    renderMaterialRequestDispatchItems();
}

// Find stock availability for a product across all warehouses
function findStockForProduct(productId) {
    const options = [];
    
    // Check quantity-based stock
    state.allWarehouseStock.forEach(stock => {
        if (stock.product_id == productId) {
            const available = stock.available_quantity || (stock.quantity - (stock.reserved_quantity || 0));
            if (available > 0) {
                options.push({
                    warehouseId: stock.warehouse_id,
                    warehouseName: stock.warehouse_name,
                    available: available,
                    type: stock.type || 'quantity'
                });
            }
        }
    });
    
    // Check serializable assets
    const assetsByWarehouse = {};
    state.allWarehouseAssets.forEach(asset => {
        if (asset.product_id == productId) {
            if (!assetsByWarehouse[asset.warehouse_id]) {
                assetsByWarehouse[asset.warehouse_id] = {
                    warehouseId: asset.warehouse_id,
                    warehouseName: asset.warehouse_name,
                    available: 0,
                    type: 'serializable',
                    assets: []
                };
            }
            assetsByWarehouse[asset.warehouse_id].available++;
            assetsByWarehouse[asset.warehouse_id].assets.push(asset);
        }
    });
    
    // Merge serializable options (avoid duplicates)
    Object.values(assetsByWarehouse).forEach(opt => {
        const existing = options.find(o => o.warehouseId == opt.warehouseId);
        if (existing) {
            existing.type = 'serializable';
            existing.assets = opt.assets;
            existing.available = opt.available;
        } else {
            options.push(opt);
        }
    });
    
    return options;
}

// Render dispatch items for material request (grouped by warehouse)
function renderMaterialRequestDispatchItems() {
    const container = document.getElementById('dispatch-items-container');
    
    if (state.dispatchItems.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No items in material request</p>';
        return;
    }
    
    // Get site name from stored data
    const siteName = state.materialRequestSiteName || 'Unknown Site';
    
    // Group items by selected warehouse
    const itemsByWarehouse = {};
    const itemsWithoutStock = [];
    
    state.dispatchItems.forEach((item, index) => {
        if (item.stockOptions.length === 0) {
            itemsWithoutStock.push({ ...item, index });
        } else {
            const whId = item.selectedWarehouseId;
            const whName = item.stockOptions.find(o => o.warehouseId == whId)?.warehouseName || 'Unknown';
            if (!itemsByWarehouse[whId]) {
                itemsByWarehouse[whId] = { name: whName, items: [] };
            }
            itemsByWarehouse[whId].items.push({ ...item, index });
        }
    });
    
    let html = `
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg p-4 mb-4">
            <div class="flex items-center">
                <i class="fas fa-map-marker-alt text-2xl mr-3"></i>
                <div>
                    <p class="text-xs text-blue-100">Dispatching to Site</p>
                    <h3 class="text-lg font-semibold">${escapeHtml(siteName)}</h3>
                </div>
            </div>
        </div>
    `;
    
    // Render items grouped by warehouse
    const warehouseIds = Object.keys(itemsByWarehouse);
    
    if (warehouseIds.length > 1) {
        html += `<p class="text-sm text-gray-600 mb-3"><i class="fas fa-info-circle mr-1 text-blue-500"></i>Items will be dispatched from <strong>${warehouseIds.length} warehouses</strong></p>`;
    }
    
    warehouseIds.forEach((whId, whIndex) => {
        const warehouse = itemsByWarehouse[whId];
        
        html += `
        <div class="border rounded-lg mb-4 overflow-hidden">
            <div class="bg-gray-100 px-4 py-3 border-b flex items-center">
                <i class="fas fa-warehouse text-gray-500 mr-2"></i>
                <span class="font-medium text-gray-800">${escapeHtml(warehouse.name)}</span>
                <span class="ml-auto text-xs text-gray-500">${warehouse.items.length} item(s)</span>
            </div>
            <div class="divide-y">
        `;
        
        warehouse.items.forEach(item => {
            const selectedWarehouse = item.stockOptions.find(o => o.warehouseId == item.selectedWarehouseId);
            const maxQty = selectedWarehouse ? selectedWarehouse.available : 0;
            
            // Get selected serial numbers
            let selectedSerialNumbers = [];
            if (item.isSerializable && item.selectedAssets.length > 0 && selectedWarehouse?.assets) {
                selectedSerialNumbers = selectedWarehouse.assets
                    .filter(a => item.selectedAssets.includes(a.id))
                    .map(a => a.serial_number);
            }
            
            const isComplete = item.isSerializable 
                ? item.selectedAssets.length >= item.requestedQuantity 
                : item.quantity >= item.requestedQuantity;
            
            html += `
            <div class="p-3 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center flex-1">
                        <div class="w-8 h-8 ${item.isSerializable ? 'bg-purple-100' : 'bg-blue-100'} rounded-lg flex items-center justify-center mr-3">
                            <i class="fas ${item.isSerializable ? 'fa-barcode text-purple-500' : 'fa-box text-blue-500'} text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center">
                                <span class="font-medium text-gray-800">${escapeHtml(item.productName)}</span>
                                <span class="text-xs text-gray-400 ml-2">${escapeHtml(item.categoryName || '')}</span>
                                ${isComplete ? '<i class="fas fa-check-circle text-green-500 ml-2"></i>' : ''}
                            </div>
                            <p class="text-xs text-gray-500">Requested: <span class="font-medium text-blue-600">${item.requestedQuantity}</span> | Available: <span class="font-medium">${maxQty}</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        ${item.stockOptions.length > 1 ? `
                        <select onchange="onMaterialRequestWarehouseChange(${item.index}, this.value)" class="px-2 py-1 border rounded text-xs focus:ring-2 focus:ring-primary">
                            ${item.stockOptions.map(opt => `
                                <option value="${opt.warehouseId}" ${opt.warehouseId == item.selectedWarehouseId ? 'selected' : ''}>
                                    ${escapeHtml(opt.warehouseName)} (${opt.available})
                                </option>
                            `).join('')}
                        </select>
                        ` : ''}
                        ${item.isSerializable ? `
                        <button type="button" onclick="openSerialPickerForMaterialRequest(${item.index})" 
                            class="px-3 py-1.5 ${item.selectedAssets.length > 0 ? 'bg-green-500 text-white' : 'bg-purple-100 text-purple-700'} rounded text-sm hover:opacity-90 font-medium min-w-[80px]">
                            <i class="fas fa-barcode mr-1"></i>${item.selectedAssets.length}/${item.requestedQuantity}
                        </button>
                        ` : `
                        <input type="number" value="${Math.min(item.quantity, maxQty)}" min="1" max="${maxQty}" 
                            onchange="onMaterialRequestQuantityChange(${item.index}, this.value)"
                            class="w-20 px-2 py-1.5 border rounded text-sm text-center focus:ring-2 focus:ring-primary font-medium">
                        `}
                    </div>
                </div>
                ${item.isSerializable && selectedSerialNumbers.length > 0 ? `
                <div class="mt-2 ml-11 flex flex-wrap gap-1">
                    ${selectedSerialNumbers.map(sn => `<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-mono">${escapeHtml(sn)}</span>`).join('')}
                </div>
                ` : ''}
            </div>
            `;
        });
        
        html += `</div></div>`;
    });
    
    // Show items without stock
    if (itemsWithoutStock.length > 0) {
        html += `
        <div class="border border-red-200 rounded-lg mb-4 overflow-hidden">
            <div class="bg-red-50 px-4 py-3 border-b border-red-200 flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                <span class="font-medium text-red-700">No Stock Available</span>
                <span class="ml-auto text-xs text-red-500">${itemsWithoutStock.length} item(s) will be skipped</span>
            </div>
            <div class="divide-y divide-red-100">
        `;
        
        itemsWithoutStock.forEach(item => {
            html += `
            <div class="p-3 bg-red-50/50">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-times text-red-500 text-sm"></i>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600">${escapeHtml(item.productName)}</span>
                        <p class="text-xs text-red-500">Requested: ${item.requestedQuantity} - Not available in any warehouse</p>
                    </div>
                </div>
            </div>
            `;
        });
        
        html += `</div></div>`;
    }
    
    // Summary
    const totalItems = state.dispatchItems.length;
    const readyItems = state.dispatchItems.filter(i => {
        if (i.stockOptions.length === 0) return false;
        if (i.isSerializable) return i.selectedAssets.length > 0;
        return i.quantity > 0;
    }).length;
    
    html += `
    <div class="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
        <span class="text-sm text-gray-600">Ready for dispatch:</span>
        <span class="font-semibold ${readyItems === totalItems ? 'text-green-600' : 'text-orange-500'}">${readyItems} of ${totalItems} items</span>
    </div>
    `;
    
    container.innerHTML = html;
}

function onMaterialRequestWarehouseChange(index, warehouseId) {
    state.dispatchItems[index].selectedWarehouseId = parseInt(warehouseId);
    state.dispatchItems[index].selectedAssets = []; // Reset selected assets when warehouse changes
    
    // Update quantity to not exceed available
    const selectedWarehouse = state.dispatchItems[index].stockOptions.find(o => o.warehouseId == warehouseId);
    if (selectedWarehouse && state.dispatchItems[index].quantity > selectedWarehouse.available) {
        state.dispatchItems[index].quantity = selectedWarehouse.available;
    }
    
    renderMaterialRequestDispatchItems();
}

function onMaterialRequestQuantityChange(index, quantity) {
    const item = state.dispatchItems[index];
    const selectedWarehouse = item.stockOptions.find(o => o.warehouseId == item.selectedWarehouseId);
    const maxQty = selectedWarehouse ? selectedWarehouse.available : 0;
    state.dispatchItems[index].quantity = Math.min(Math.max(1, parseInt(quantity) || 1), maxQty);
}

function openSerialPickerForMaterialRequest(itemIndex) {
    const item = state.dispatchItems[itemIndex];
    if (!item || !item.selectedWarehouseId) return;
    
    // Get assets for selected warehouse
    const selectedWarehouse = item.stockOptions.find(o => o.warehouseId == item.selectedWarehouseId);
    const availableAssets = selectedWarehouse?.assets || state.allWarehouseAssets.filter(
        a => a.product_id == item.productId && a.warehouse_id == item.selectedWarehouseId
    );
    
    state.serialPicker.itemIndex = itemIndex;
    state.serialPicker.productId = item.productId;
    state.serialPicker.availableAssets = availableAssets;
    state.serialPicker.selectedAssets = [...item.selectedAssets];
    state.serialPicker.isMaterialRequest = true;
    state.serialPicker.maxSelection = item.requestedQuantity || null; // Limit to requested quantity
    
    renderSerialList();
    document.getElementById('serial-picker-modal').classList.remove('hidden');
}

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', e => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { state.filters.search = e.target.value; state.pagination.page = 1; loadDispatches(); }, 300);
    });
    document.getElementById('warehouse-filter').addEventListener('change', e => { state.filters.from_warehouse_id = e.target.value; state.pagination.page = 1; loadDispatches(); });
    document.getElementById('status-filter').addEventListener('change', e => { state.filters.status = e.target.value; state.pagination.page = 1; loadDispatches(); });
}

// Load data
async function loadWarehouses() {
    try {
        const response = await fetch('../api/inventory/warehouses/index.php?limit=100', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.warehouses = (data.data.warehouses || []).filter(w => w.status === 'active');
            populateWarehouseDropdowns();
        }
    } catch (error) { console.error('Error loading warehouses:', error); }
}

function populateWarehouseDropdowns() {
    const options = state.warehouses.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
    document.getElementById('warehouse-filter').innerHTML = '<option value="">All Warehouses</option>' + options;
    document.getElementById('dispatch-from-warehouse').innerHTML = '<option value="">Select Source Warehouse</option>' + options;
    document.getElementById('dispatch-to-warehouse').innerHTML = '<option value="">Select Destination Warehouse</option>' + options;
}

async function loadCouriers() {
    try {
        const response = await fetch('../api/masters/couriers.php', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.couriers = data.data.couriers || data.data || [];
            const select = document.getElementById('dispatch-courier');
            select.innerHTML = '<option value="">Select Courier</option>' + state.couriers.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }
    } catch (error) { console.error('Error loading couriers:', error); }
}

async function loadCompanies() {
    try {
        const response = await fetch('../api/users/companies.php', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.companies = data.data.companies || [];
            document.getElementById('dispatch-to-company').innerHTML = '<option value="">Select Destination Company</option>' + 
                state.companies.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        }
    } catch (error) { console.error('Error loading companies:', error); }
}

async function loadUsers() {
    try {
        const response = await fetch('../api/users/index.php?limit=500', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.users = data.data.users || [];
            document.getElementById('dispatch-to-user').innerHTML = '<option value="">Select Destination User</option>' + 
                state.users.map(u => `<option value="${u.id}">${escapeHtml(u.name || u.first_name + ' ' + u.last_name)}</option>`).join('');
        }
    } catch (error) { console.error('Error loading users:', error); }
}

// Load warehouse stock and assets when warehouse changes
async function onWarehouseChange() {
    // Don't clear items if this is a material request dispatch
    if (state.materialRequestId) {
        return; // Material request items have their own warehouse selection per item
    }
    
    const warehouseEl = document.getElementById('dispatch-from-warehouse');
    if (!warehouseEl) return; // Element might be replaced for material requests
    
    const warehouseId = warehouseEl.value;
    state.dispatchItems = [];
    renderDispatchItems();
    
    if (!warehouseId) {
        state.warehouseStock = [];
        state.warehouseAssets = [];
        return;
    }
    
    // Load stock for this warehouse
    try {
        console.log('Loading inventory for warehouse:', warehouseId);
        const [stockRes, assetsRes] = await Promise.all([
            fetch(`../api/inventory/stock/index.php?warehouse_id=${warehouseId}&limit=500`, { credentials: 'include' }),
            fetch(`../api/inventory/assets/index.php?warehouse_id=${warehouseId}&status=in_stock&limit=500`, { credentials: 'include' })
        ]);
        
        const stockData = await stockRes.json();
        const assetsData = await assetsRes.json();
        
        console.log('Stock API response:', stockData);
        console.log('Assets API response:', assetsData);
        
        state.warehouseStock = stockData.success ? (stockData.data.stock || []) : [];
        state.warehouseAssets = assetsData.success ? (assetsData.data.assets || []) : [];
        
        console.log('Warehouse stock:', state.warehouseStock.length, 'items');
        console.log('Warehouse assets:', state.warehouseAssets.length, 'items');
        
        // Show message if no products available
        if (state.warehouseStock.length === 0 && state.warehouseAssets.length === 0) {
            showToast('No products available in this warehouse', 'info');
        }
    } catch (error) {
        console.error('Error loading warehouse inventory:', error);
        state.warehouseStock = [];
        state.warehouseAssets = [];
    }
}

function onDestinationTypeChange() {
    const type = document.querySelector('input[name="destination_type"]:checked').value;
    document.getElementById('dest-company-field').classList.toggle('hidden', type !== 'company');
    document.getElementById('dest-user-field').classList.toggle('hidden', type !== 'user');
    document.getElementById('dest-warehouse-field').classList.toggle('hidden', type !== 'warehouse');
}

// Dispatch Items Management
function addDispatchItem() {
    const warehouseId = document.getElementById('dispatch-from-warehouse').value;
    if (!warehouseId) { showToast('Please select a warehouse first', 'error'); return; }
    
    const itemIndex = state.dispatchItems.length;
    state.dispatchItems.push({ productId: null, quantity: 1, isSerializable: false, selectedAssets: [] });
    renderDispatchItems();
}

function renderDispatchItems() {
    const container = document.getElementById('dispatch-items-container');
    const warehouseEl = document.getElementById('dispatch-from-warehouse');
    const warehouseId = warehouseEl ? warehouseEl.value : null;
    
    // For material request dispatches, use the material request render function
    if (state.materialRequestId) {
        renderMaterialRequestDispatchItems();
        return;
    }
    
    if (!warehouseId) {
        container.innerHTML = '<p id="no-warehouse-msg" class="text-sm text-gray-500 italic">Please select a warehouse first to add items</p>';
        return;
    }
    
    if (state.dispatchItems.length === 0) {
        container.innerHTML = '<p class="text-sm text-gray-500 italic">Click "Add Item" to add products to dispatch</p>';
        return;
    }
    
    // Build product options with stock info
    const productOptions = buildProductOptions();
    
    container.innerHTML = state.dispatchItems.map((item, index) => {
        const product = getProductById(item.productId);
        const stockInfo = getStockInfo(item.productId);
        
        return `
        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg" data-index="${index}">
            <div class="flex-1">
                <select onchange="onProductChange(${index}, this.value)" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Select Product</option>
                    ${productOptions}
                </select>
                ${item.productId ? `<p class="text-xs text-gray-500 mt-1">${stockInfo}</p>` : ''}
            </div>
            <div class="w-24">
                ${item.isSerializable ? `
                    <button type="button" onclick="openSerialPicker(${index})" class="w-full px-3 py-2 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                        <i class="fas fa-barcode mr-1"></i>${item.selectedAssets.length} selected
                    </button>
                ` : `
                    <input type="number" value="${item.quantity}" min="1" max="${getMaxQuantity(item.productId)}" 
                        onchange="onQuantityChange(${index}, this.value)"
                        class="w-full px-3 py-2 border rounded-lg text-sm text-center">
                `}
            </div>
            <button type="button" onclick="removeDispatchItem(${index})" class="p-2 text-red-500 hover:bg-red-100 rounded-lg">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        `;
    }).join('');
    
    // Set selected values
    state.dispatchItems.forEach((item, index) => {
        if (item.productId) {
            const select = container.querySelector(`[data-index="${index}"] select`);
            if (select) select.value = item.productId;
        }
    });
}

function buildProductOptions() {
    const products = new Map();
    
    // Process stock data (includes both serializable and quantity types)
    state.warehouseStock.forEach(stock => {
        const available = stock.available_quantity || (stock.quantity - (stock.reserved_quantity || 0));
        if (available > 0) {
            products.set(stock.product_id, {
                id: stock.product_id,
                name: stock.product_name,
                isSerializable: stock.type === 'serializable',
                available: available
            });
        }
    });
    
    // Also add serializable products from assets API (for serial number selection)
    state.warehouseAssets.forEach(asset => {
        const existing = products.get(asset.product_id);
        if (existing) {
            // Update to serializable if we have assets
            existing.isSerializable = true;
        } else {
            products.set(asset.product_id, {
                id: asset.product_id,
                name: asset.product_name,
                isSerializable: true,
                available: 1
            });
        }
    });
    
    // Count assets per product for accurate serializable counts
    const assetCounts = {};
    state.warehouseAssets.forEach(asset => {
        assetCounts[asset.product_id] = (assetCounts[asset.product_id] || 0) + 1;
    });
    
    // Update serializable product counts from assets
    products.forEach((product, productId) => {
        if (product.isSerializable && assetCounts[productId]) {
            product.available = assetCounts[productId];
        }
    });
    
    return Array.from(products.values())
        .map(p => `<option value="${p.id}" data-serializable="${p.isSerializable}">${escapeHtml(p.name)} (${p.available} available)</option>`)
        .join('');
}

function getProductById(productId) {
    if (!productId) return null;
    const stock = state.warehouseStock.find(s => s.product_id == productId);
    if (stock) return { id: stock.product_id, name: stock.product_name, isSerializable: stock.type === 'serializable' };
    const asset = state.warehouseAssets.find(a => a.product_id == productId);
    if (asset) return { id: asset.product_id, name: asset.product_name, isSerializable: true };
    return null;
}

function getStockInfo(productId) {
    if (!productId) return '';
    const stock = state.warehouseStock.find(s => s.product_id == productId);
    if (stock) {
        const available = stock.available_quantity || (stock.quantity - (stock.reserved_quantity || 0));
        if (stock.type === 'serializable') {
            return `Available: ${available} serial numbers`;
        }
        return `Available: ${available} units`;
    }
    const assets = state.warehouseAssets.filter(a => a.product_id == productId);
    return `Available: ${assets.length} serial numbers`;
}

function getMaxQuantity(productId) {
    if (!productId) return 1;
    const stock = state.warehouseStock.find(s => s.product_id == productId);
    if (stock) {
        if (stock.type === 'serializable') return 1; // Serializable items use serial picker
        return stock.available_quantity || (stock.quantity - (stock.reserved_quantity || 0));
    }
    return 1;
}

function onProductChange(index, productId) {
    // Check if product is serializable from stock data or assets
    const stock = state.warehouseStock.find(s => s.product_id == productId);
    const isSerializable = (stock && stock.type === 'serializable') || state.warehouseAssets.some(a => a.product_id == productId);
    state.dispatchItems[index] = { productId: productId, quantity: 1, isSerializable: isSerializable, selectedAssets: [] };
    renderDispatchItems();
}

function onQuantityChange(index, quantity) {
    state.dispatchItems[index].quantity = parseInt(quantity) || 1;
}

function removeDispatchItem(index) {
    state.dispatchItems.splice(index, 1);
    renderDispatchItems();
}

// Serial Number Picker
function openSerialPicker(itemIndex) {
    const item = state.dispatchItems[itemIndex];
    if (!item || !item.productId) return;
    
    state.serialPicker.itemIndex = itemIndex;
    state.serialPicker.productId = item.productId;
    state.serialPicker.availableAssets = state.warehouseAssets.filter(a => a.product_id == item.productId);
    state.serialPicker.selectedAssets = [...item.selectedAssets];
    
    renderSerialList();
    document.getElementById('serial-picker-modal').classList.remove('hidden');
}

function renderSerialList() {
    const container = document.getElementById('serial-list');
    const search = (document.getElementById('serial-search').value || '').toLowerCase();
    const maxSelection = state.serialPicker.maxSelection;
    const selectedCount = state.serialPicker.selectedAssets.length;
    
    let assets = state.serialPicker.availableAssets;
    if (search) {
        assets = assets.filter(a => (a.serial_number || '').toLowerCase().includes(search));
    }
    
    if (assets.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm text-center py-4">No serial numbers found</p>';
    } else {
        container.innerHTML = assets.map(asset => {
            const isSelected = state.serialPicker.selectedAssets.includes(asset.id);
            const isDisabled = !isSelected && maxSelection && selectedCount >= maxSelection;
            return `
            <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer ${isSelected ? 'bg-blue-50' : ''} ${isDisabled ? 'opacity-50' : ''}">
                <input type="checkbox" ${isSelected ? 'checked' : ''} ${isDisabled ? 'disabled' : ''} onchange="toggleSerialSelection(${asset.id})" class="mr-3">
                <span class="font-mono text-sm">${escapeHtml(asset.serial_number)}</span>
                <span class="ml-auto text-xs text-gray-500">${escapeHtml(asset.working_condition || 'working')}</span>
            </label>
            `;
        }).join('');
    }
    
    const limitText = maxSelection ? ` / ${maxSelection} max` : '';
    document.getElementById('serial-selected-count').textContent = `${selectedCount} selected${limitText}`;
    document.getElementById('serial-available-count').textContent = `Available: ${state.serialPicker.availableAssets.length}`;
}

function filterSerialNumbers() {
    renderSerialList();
}

function toggleSerialSelection(assetId) {
    const index = state.serialPicker.selectedAssets.indexOf(assetId);
    if (index > -1) {
        // Always allow deselection
        state.serialPicker.selectedAssets.splice(index, 1);
    } else {
        // Check if we've reached the max selection limit
        const maxSelection = state.serialPicker.maxSelection;
        if (maxSelection && state.serialPicker.selectedAssets.length >= maxSelection) {
            showToast(`You can only select ${maxSelection} serial number(s) as requested`, 'error');
            return;
        }
        state.serialPicker.selectedAssets.push(assetId);
    }
    renderSerialList();
}

function confirmSerialSelection() {
    const itemIndex = state.serialPicker.itemIndex;
    if (itemIndex !== null && state.dispatchItems[itemIndex]) {
        state.dispatchItems[itemIndex].selectedAssets = [...state.serialPicker.selectedAssets];
        state.dispatchItems[itemIndex].quantity = state.serialPicker.selectedAssets.length;
    }
    closeSerialPickerModal();
    
    // Re-render appropriate view
    if (state.serialPicker.isMaterialRequest) {
        renderMaterialRequestDispatchItems();
    } else {
        renderDispatchItems();
    }
}

function closeSerialPickerModal() {
    document.getElementById('serial-picker-modal').classList.add('hidden');
    document.getElementById('serial-search').value = '';
    state.serialPicker.isMaterialRequest = false;
    state.serialPicker.maxSelection = null;
}

// Dispatch Modal
function openDispatchModal() {
    document.getElementById('dispatch-form').reset();
    document.getElementById('dispatch-date').value = new Date().toISOString().split('T')[0];
    state.dispatchItems = [];
    state.warehouseStock = [];
    state.warehouseAssets = [];
    onDestinationTypeChange();
    renderDispatchItems();
    document.getElementById('dispatch-modal').classList.remove('hidden');
}

function closeDispatchModal() {
    document.getElementById('dispatch-modal').classList.add('hidden');
    
    // Reset material request state
    if (state.materialRequestId) {
        state.materialRequestId = null;
        state.materialRequestItems = [];
        state.materialRequestSiteId = null;
        state.materialRequestSiteName = null;
        state.allWarehouseStock = [];
        state.allWarehouseAssets = [];
        
        // Restore the warehouse dropdown by rebuilding the entire row
        const warehouseRow = document.querySelector('#dispatch-form .grid');
        if (warehouseRow) {
            const firstDiv = warehouseRow.querySelector('div:first-child');
            if (firstDiv) {
                firstDiv.innerHTML = `
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Warehouse <span class="text-red-500">*</span></label>
                    <select id="dispatch-from-warehouse" name="from_warehouse_id" required onchange="onWarehouseChange()"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">Select Source Warehouse</option>
                    </select>
                `;
                // Re-populate warehouse options
                const warehouseSelect = document.getElementById('dispatch-from-warehouse');
                if (warehouseSelect) {
                    warehouseSelect.innerHTML = '<option value="">Select Source Warehouse</option>' + 
                        state.warehouses.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
                }
            }
        }
    }
    
    // Reset dispatch items
    state.dispatchItems = [];
}

async function saveDispatch(event) {
    event.preventDefault();
    
    // Validate items
    if (state.dispatchItems.length === 0) { showToast('Please add at least one item', 'error'); return; }
    
    // For material requests, items have their own warehouse selection
    const isMaterialRequestDispatch = state.materialRequestId && state.dispatchItems[0]?.selectedWarehouseId;
    
    let validItems;
    if (isMaterialRequestDispatch) {
        validItems = state.dispatchItems.filter(item => {
            // Must have product and warehouse
            if (!item.productId || !item.selectedWarehouseId || !item.stockOptions?.length) return false;
            // For serializable items, must have selected assets
            if (item.isSerializable) return item.selectedAssets.length > 0;
            // For non-serializable items, must have quantity > 0
            return item.quantity > 0;
        });
    } else {
        validItems = state.dispatchItems.filter(item => {
            if (!item.productId) return false;
            if (item.isSerializable) return item.selectedAssets.length > 0;
            return item.quantity > 0;
        });
    }
    
    if (validItems.length === 0) { showToast('Please select products and quantities (serializable items need serial numbers selected)', 'error'); return; }
    
    const form = document.getElementById('dispatch-form');
    const formData = new FormData(form);
    
    // For material requests with per-item warehouses, we need to create multiple dispatches (one per warehouse)
    if (isMaterialRequestDispatch) {
        // Group items by warehouse
        const itemsByWarehouse = {};
        validItems.forEach(item => {
            const whId = item.selectedWarehouseId;
            if (!itemsByWarehouse[whId]) itemsByWarehouse[whId] = [];
            itemsByWarehouse[whId].push(item);
        });
        
        let successCount = 0;
        let errorMsg = '';
        
        for (const [warehouseId, items] of Object.entries(itemsByWarehouse)) {
            const dispatchData = {
                from_warehouse_id: warehouseId,
                dispatch_date: formData.get('dispatch_date'),
                courier_id: formData.get('courier_id') || null,
                pod_number: formData.get('pod_number') || null,
                contact_person_name: formData.get('contact_person_name') || null,
                contact_person_phone: formData.get('contact_person_phone') || null,
                notes: formData.get('notes') || null,
                material_request_id: state.materialRequestId || null,
                site_id: state.materialRequestSiteId || null,
                items: items.map(item => ({
                    product_id: item.productId,
                    quantity: item.isSerializable ? item.selectedAssets.length : item.quantity,
                    asset_ids: item.isSerializable ? item.selectedAssets : []
                }))
            };
            
            // Add destination
            const destType = formData.get('destination_type');
            if (destType === 'company') dispatchData.to_company_id = formData.get('to_company_id');
            else if (destType === 'user') dispatchData.to_user_id = formData.get('to_user_id');
            else if (destType === 'warehouse') dispatchData.to_warehouse_id = formData.get('to_warehouse_id');
            
            try {
                const response = await fetch(`${API_URL}/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(dispatchData)
                });
                
                const result = await response.json();
                if (result.success) {
                    successCount++;
                } else {
                    errorMsg = result.error?.message || 'Failed to create dispatch';
                }
            } catch (error) {
                console.error('Error:', error);
                errorMsg = 'Failed to create dispatch';
            }
        }
        
        if (successCount > 0) {
            // Update material request status
            if (state.materialRequestId) {
                try {
                    await fetch('../api/material-requests/status.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ id: parseInt(state.materialRequestId), status: 'dispatched' })
                    });
                } catch (e) {
                    console.error('Failed to update material request status:', e);
                }
                state.materialRequestId = null;
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            showToast(`${successCount} dispatch(es) created successfully`, 'success');
            closeDispatchModal();
            loadDispatches();
        } else {
            showToast(errorMsg || 'Failed to create dispatches', 'error');
        }
        return;
    }
    
    // Standard dispatch (single warehouse)
    const dispatchData = {
        from_warehouse_id: formData.get('from_warehouse_id'),
        dispatch_date: formData.get('dispatch_date'),
        courier_id: formData.get('courier_id') || null,
        pod_number: formData.get('pod_number') || null,
        contact_person_name: formData.get('contact_person_name') || null,
        contact_person_phone: formData.get('contact_person_phone') || null,
        notes: formData.get('notes') || null,
        material_request_id: state.materialRequestId || null,
        site_id: state.materialRequestSiteId || null,
        items: validItems.map(item => ({
            product_id: item.productId,
            quantity: item.isSerializable ? item.selectedAssets.length : item.quantity,
            asset_ids: item.isSerializable ? item.selectedAssets : []
        }))
    };
    
    // Add destination
    const destType = formData.get('destination_type');
    if (destType === 'company') dispatchData.to_company_id = formData.get('to_company_id');
    else if (destType === 'user') dispatchData.to_user_id = formData.get('to_user_id');
    else if (destType === 'warehouse') dispatchData.to_warehouse_id = formData.get('to_warehouse_id');
    
    try {
        const response = await fetch(`${API_URL}/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(dispatchData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // If this was for a material request, update its status to dispatched
            if (state.materialRequestId) {
                try {
                    await fetch('../api/material-requests/status.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ id: parseInt(state.materialRequestId), status: 'dispatched' })
                    });
                } catch (e) {
                    console.error('Failed to update material request status:', e);
                }
                state.materialRequestId = null;
                // Clear URL parameter
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            showToast('Dispatch created successfully', 'success');
            closeDispatchModal();
            loadDispatches();
        } else {
            showToast(result.error?.message || 'Failed to create dispatch', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to create dispatch', 'error');
    }
}

// Load and render dispatches
async function loadDispatches() {
    try {
        const params = new URLSearchParams({ page: state.pagination.page, limit: state.pagination.limit });
        if (state.filters.from_warehouse_id) params.append('from_warehouse_id', state.filters.from_warehouse_id);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.dispatches = data.data.dispatches || [];
            state.pagination = data.data.pagination || state.pagination;
            
            if (state.filters.search) {
                const search = state.filters.search.toLowerCase();
                state.dispatches = state.dispatches.filter(d => (d.dispatch_number || '').toLowerCase().includes(search));
            }
            
            renderTable();
            renderPagination();
        }
    } catch (error) { console.error('Error loading dispatches:', error); }
}

function renderTable() {
    const tbody = document.getElementById('dispatch-tbody');
    
    if (state.dispatches.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-paper-plane text-4xl mb-3 text-gray-300"></i><p>No dispatches found</p></td></tr>';
        return;
    }
    
    const statusColors = { 
        pending: 'bg-yellow-100 text-yellow-700', 
        in_transit: 'bg-blue-100 text-blue-700', 
        delivered: 'bg-green-100 text-green-700', 
        cancelled: 'bg-red-100 text-red-700' 
    };
    
    const statusDots = {
        pending: 'bg-yellow-500',
        in_transit: 'bg-blue-500',
        delivered: 'bg-green-500',
        cancelled: 'bg-red-500'
    };
    
    let rows = [];
    let serialNum = (state.pagination.page - 1) * state.pagination.limit;
    
    state.dispatches.forEach(d => {
        serialNum++;
        const dest = d.to_user_name || d.to_company_name || d.to_warehouse_name || '-';
        const items = d.items || [];
        
        // Group items by product_id to consolidate serial numbers
        const groupedItems = {};
        items.forEach(item => {
            const productId = item.product_id;
            if (!groupedItems[productId]) {
                groupedItems[productId] = {
                    product_name: item.product_name,
                    quantity: 0,
                    serial_numbers: []
                };
            }
            groupedItems[productId].quantity += parseInt(item.quantity) || 1;
            if (item.serial_number) {
                groupedItems[productId].serial_numbers.push(item.serial_number);
            }
        });
        
        const productList = Object.values(groupedItems);
        
        // Build items display HTML
        let itemsHtml = '';
        if (productList.length === 0) {
            itemsHtml = '<span class="text-gray-400 text-[10px]">No items</span>';
        } else {
            itemsHtml = '<div class="space-y-0.5">';
            productList.forEach(item => {
                const serialDisplay = item.serial_numbers.length > 0 
                    ? item.serial_numbers.slice(0, 2).join(', ') + (item.serial_numbers.length > 2 ? ` +${item.serial_numbers.length - 2}` : '')
                    : '';
                itemsHtml += `
                    <div class="flex items-center gap-1 text-[10px]">
                        <span class="text-gray-600 font-medium">${item.quantity}x</span>
                        <span class="text-gray-800">${escapeHtml(item.product_name || 'Unknown')}</span>
                        ${serialDisplay ? `<span class="text-[9px] text-purple-600 font-mono">(${escapeHtml(serialDisplay)})</span>` : ''}
                    </div>
                `;
            });
            itemsHtml += '</div>';
        }
        
        // Build shipping info HTML
        let shippingHtml = '<div class="text-[10px] space-y-0.5">';
        if (d.courier_name) {
            shippingHtml += `<p class="text-gray-700"><i class="fas fa-truck text-gray-400 w-3"></i> ${escapeHtml(d.courier_name)}</p>`;
        }
        if (d.pod_number) {
            shippingHtml += `<p class="text-gray-600"><i class="fas fa-hashtag text-gray-400 w-3"></i> ${escapeHtml(d.pod_number)}</p>`;
        }
        if (!d.courier_name && !d.pod_number) {
            shippingHtml += '<span class="text-gray-400">-</span>';
        }
        shippingHtml += '</div>';
        
        // Build receiver info HTML
        let receiverHtml = '<div class="text-[10px]">';
        if (d.site_name) {
            receiverHtml += `<p class="text-blue-600 font-medium"><i class="fas fa-map-marker-alt mr-1"></i>${escapeHtml(d.site_name)}</p>`;
        }
        receiverHtml += `<p class="text-gray-800 ${d.site_name ? '' : 'font-medium'}">${escapeHtml(dest)}</p>`;
        if (d.contact_person_name) {
            receiverHtml += `<p class="text-gray-600"><i class="fas fa-user text-gray-400 mr-1"></i>${escapeHtml(d.contact_person_name)}</p>`;
        }
        if (d.contact_person_phone) {
            receiverHtml += `<p class="text-gray-500"><i class="fas fa-phone text-gray-400 mr-1"></i>${escapeHtml(d.contact_person_phone)}</p>`;
        }
        receiverHtml += '</div>';
        
        rows.push(`
        <tr class="hover:bg-gray-50/50 transition-colors align-top">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${serialNum}</td>
            <td class="px-4 py-2.5">
                <div>
                    <span class="font-medium text-xs text-primary cursor-pointer hover:underline" onclick="viewDispatch(${d.id})">
                        ${escapeHtml(d.dispatch_number)}
                    </span>
                    <p class="text-[10px] text-gray-500">${formatDate(d.dispatch_date)}</p>
                    <p class="text-[10px] text-gray-400">${escapeHtml(d.from_warehouse_name || '-')}</p>
                    ${d.notes ? `<p class="text-[10px] text-gray-400 italic mt-0.5 truncate max-w-[120px]" title="${escapeHtml(d.notes)}">${escapeHtml(d.notes.substring(0, 25))}${d.notes.length > 25 ? '...' : ''}</p>` : ''}
                </div>
            </td>
            <td class="px-4 py-2.5">${receiverHtml}</td>
            <td class="px-4 py-2.5">${itemsHtml}</td>
            <td class="px-4 py-2.5">${shippingHtml}</td>
            <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2 py-0.5 ${statusColors[d.status] || 'bg-gray-100'} rounded-full text-[10px] capitalize whitespace-nowrap">
                    <span class="w-1.5 h-1.5 ${statusDots[d.status] || 'bg-gray-500'} rounded-full mr-1.5"></span>${(d.status || '').replace('_', ' ')}
                </span>
            </td>
            <td class="px-4 py-2.5 text-center">
                <button onclick="viewDispatch(${d.id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="View Details">
                    <i class="fas fa-eye text-xs"></i>
                </button>
            </td>
        </tr>`);
    });
    
    tbody.innerHTML = rows.join('');
}

function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    document.getElementById('pagination-info').textContent = total > 0 ? `Showing ${(page-1)*limit+1} to ${Math.min(page*limit, total)} of ${total}` : 'No entries';
    
    if (total_pages <= 1) { document.getElementById('pagination-controls').innerHTML = ''; return; }
    
    let html = `<button onclick="goToPage(${page-1})" ${page===1?'disabled':''} class="px-3 py-1 rounded border ${page===1?'bg-gray-100 text-gray-400':'hover:bg-gray-100'}"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = Math.max(1, page-2); i <= Math.min(total_pages, page+2); i++) {
        html += `<button onclick="goToPage(${i})" class="px-3 py-1 rounded border ${i===page?'bg-primary text-white':'hover:bg-gray-100'}">${i}</button>`;
    }
    html += `<button onclick="goToPage(${page+1})" ${page===total_pages?'disabled':''} class="px-3 py-1 rounded border ${page===total_pages?'bg-gray-100 text-gray-400':'hover:bg-gray-100'}"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination-controls').innerHTML = html;
}

function goToPage(page) { if (page >= 1 && page <= state.pagination.total_pages) { state.pagination.page = page; loadDispatches(); } }

// View dispatch
async function viewDispatch(id) {
    try {
        const response = await fetch(`${API_URL}/show.php?id=${id}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.currentDispatch = data.data.dispatch;
            const d = state.currentDispatch;
            const dest = d.to_user_name || d.to_company_name || d.to_warehouse_name || '-';
            
            let shippingHtml = '';
            if (d.courier_name || d.pod_number) {
                shippingHtml = `
                <div class="border-t pt-4 mt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Shipping Details</h4>
                    <div class="grid grid-cols-2 gap-4">
                        ${d.courier_name ? `<div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">Courier</p><p class="font-semibold">${escapeHtml(d.courier_name)}</p></div>` : ''}
                        ${d.pod_number ? `<div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">POD Number</p><p class="font-semibold">${escapeHtml(d.pod_number)}</p></div>` : ''}
                    </div>
                </div>`;
            }
            
            let contactHtml = '';
            if (d.contact_person_name || d.contact_person_phone) {
                contactHtml = `
                <div class="border-t pt-4 mt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Contact Details</h4>
                    <div class="grid grid-cols-2 gap-4">
                        ${d.contact_person_name ? `<div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">Contact Person</p><p class="font-semibold">${escapeHtml(d.contact_person_name)}</p></div>` : ''}
                        ${d.contact_person_phone ? `<div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">Phone</p><p class="font-semibold">${escapeHtml(d.contact_person_phone)}</p></div>` : ''}
                    </div>
                </div>`;
            }
            
            let attachmentsHtml = '';
            if (d.lr_copy_path || d.pod_receipt_path) {
                attachmentsHtml = `
                <div class="border-t pt-4 mt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Attachments</h4>
                    <div class="flex gap-4">
                        ${d.lr_copy_path ? `<a href="${escapeHtml(d.lr_copy_path)}" target="_blank" class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200"><i class="fas fa-file mr-2"></i>LR Copy</a>` : ''}
                        ${d.pod_receipt_path ? `<a href="${escapeHtml(d.pod_receipt_path)}" target="_blank" class="px-3 py-2 bg-green-100 text-green-700 rounded-lg text-sm hover:bg-green-200"><i class="fas fa-file mr-2"></i>POD Receipt</a>` : ''}
                    </div>
                </div>`;
            }
            
            document.getElementById('view-dispatch-content').innerHTML = `
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">Dispatch #</p><p class="font-semibold">${escapeHtml(d.dispatch_number)}</p></div>
                    <div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">Date</p><p class="font-semibold">${formatDate(d.dispatch_date)}</p></div>
                    <div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">From</p><p class="font-semibold">${escapeHtml(d.from_warehouse_name || '-')}</p></div>
                    <div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">To</p><p class="font-semibold">${escapeHtml(dest)}</p></div>
                    <div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">Status</p><p class="font-semibold capitalize">${d.status}</p></div>
                    <div class="bg-gray-50 p-3 rounded"><p class="text-xs text-gray-500">Acknowledgment</p><p class="font-semibold capitalize">${d.acknowledgment_status || 'pending'}</p></div>
                </div>
                ${shippingHtml}
                ${contactHtml}
                ${attachmentsHtml}
                ${d.notes ? `<div class="border-t pt-4 mt-4"><p class="text-sm text-gray-600"><strong>Notes:</strong> ${escapeHtml(d.notes)}</p></div>` : ''}
            `;
            
            // Show/hide action buttons based on status and permissions
            const isAdv = <?php echo json_encode($isAdv); ?>;
            
            // Process buttons (ADV only)
            document.getElementById('process-transit-btn').classList.toggle('hidden', !(isAdv && d.status === 'pending'));
            document.getElementById('process-delivered-btn').classList.toggle('hidden', !(isAdv && d.status === 'in_transit'));
            document.getElementById('process-cancel-btn').classList.toggle('hidden', !(isAdv && (d.status === 'pending' || d.status === 'in_transit')));
            
            // Acknowledge button (for contractors receiving items)
            document.getElementById('acknowledge-btn').classList.toggle('hidden', !(state.permissions.acknowledge && d.status === 'delivered' && d.acknowledgment_status === 'pending'));
            
            document.getElementById('view-dispatch-modal').classList.remove('hidden');
        }
    } catch (error) { console.error('Error:', error); showToast('Failed to load dispatch details', 'error'); }
}

function closeViewDispatchModal() { document.getElementById('view-dispatch-modal').classList.add('hidden'); }

async function processDispatch(newStatus) {
    if (!state.currentDispatch) return;
    
    const statusLabels = { in_transit: 'In Transit', delivered: 'Delivered', cancelled: 'Cancelled' };
    if (!confirm(`Are you sure you want to mark this dispatch as "${statusLabels[newStatus]}"?`)) return;
    
    try {
        const response = await fetch(`${API_URL}/process.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ dispatch_id: state.currentDispatch.id, status: newStatus })
        });
        const result = await response.json();
        if (result.success) {
            showToast(`Dispatch marked as ${statusLabels[newStatus]}`, 'success');
            closeViewDispatchModal();
            loadDispatches();
        } else {
            showToast(result.error?.message || 'Failed to process dispatch', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to process dispatch', 'error');
    }
}

async function acknowledgeDispatch() {
    if (!state.currentDispatch) return;
    try {
        const response = await fetch(`${API_URL}/acknowledge.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ dispatch_id: state.currentDispatch.id })
        });
        const result = await response.json();
        if (result.success) { showToast('Dispatch acknowledged', 'success'); closeViewDispatchModal(); loadDispatches(); }
        else { showToast(result.error?.message || 'Failed to acknowledge', 'error'); }
    } catch (error) { showToast('Failed to acknowledge dispatch', 'error'); }
}

function exportDispatches() { window.open(`../api/inventory/export/index.php?type=dispatches`, '_blank'); }

// Utilities
function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'; }
function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function showToast(msg, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-[100] ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center`;
    toast.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'} mr-2"></i><span>${msg}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
