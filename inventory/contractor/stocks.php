<?php
/**
 * Contractor Stocks Page
 * 
 * Displays all inventory stocks for contractor's company
 * Includes:
 * - All stocks (received materials)
 * - Material received (acceptance pending)
 * - Material dispatched
 * - Repair/Faulty materials
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
$pageTitle = 'My Stocks';
$currentPage = 'contractor_stocks';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../../contractor/dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'My Stocks']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">My Stocks</h3>
            <p class="text-sm text-gray-500">View all inventory items received from ADV</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="p-4 border-b bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
            <!-- All Stocks Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByCategory('')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-boxes text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">All Stocks</p>
                        <p id="total-count" class="text-xl font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <!-- Recent Material Received Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByCategory('recent_received')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-truck-loading text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Recent Received</p>
                        <p id="recent-received-count" class="text-xl font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
            <!-- Material Received (Pending Ack) Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByCategory('pending_ack')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-clipboard-check text-orange-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pending Ack.</p>
                        <p id="pending-ack-count" class="text-xl font-semibold text-orange-600">0</p>
                    </div>
                </div>
            </div>
            <!-- Material Dispatched Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByCategory('dispatched')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-user-hard-hat text-purple-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">With Engineers</p>
                        <p id="dispatched-count" class="text-xl font-semibold text-purple-600">0</p>
                    </div>
                </div>
            </div>
            <!-- Repair/Faulty Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByCategory('faulty')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-tools text-red-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Repair/Faulty</p>
                        <p id="faulty-count" class="text-xl font-semibold text-red-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by serial number, product name..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
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
                </select>
            </div>
            <div>
                <select id="condition-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Condition</option>
                    <option value="working">Working</option>
                    <option value="not_working">Not Working</option>
                </select>
            </div>
            <button onclick="clearFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </button>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading stocks...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto overflow-y-visible">
        <table id="stocks-table" class="w-full">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Available</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">With Engineers</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="stocks-tbody" class="divide-y">
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">Loading...</td>
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

<!-- View Details Modal -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Asset Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5 max-h-[60vh] overflow-y-auto"></div>
            <div class="flex justify-end p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Acknowledge Modal -->
<div id="acknowledge-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAcknowledgeModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Acknowledge Material Receipt</h3>
                    <p class="text-sm text-gray-500">Dispatch: <span id="ack-dispatch-number" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeAcknowledgeModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Proof (Photo/Video) <span class="text-red-500">*</span></label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-primary transition cursor-pointer" onclick="document.getElementById('proof-files').click()">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">Click to upload</p>
                        <input type="file" id="proof-files" multiple accept="image/*,video/*" class="hidden" onchange="handleFileSelect(event)">
                    </div>
                    <div id="file-preview-container" class="flex flex-wrap gap-2 mt-3"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Material Condition</label>
                    <select id="ack-condition" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="good">Good - All items in perfect condition</option>
                        <option value="minor_damage">Minor Damage</option>
                        <option value="damaged">Damaged</option>
                        <option value="missing">Missing Items</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea id="ack-notes" rows="3" placeholder="Add any notes..." class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeAcknowledgeModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="ack-submit-btn" onclick="submitAcknowledgment()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Confirm Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Change Condition Modal -->
<div id="condition-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeConditionModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Change Working Condition</h3>
                    <p class="text-sm text-gray-500">Asset: <span id="condition-serial" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeConditionModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <input type="hidden" id="condition-asset-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Working Condition</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-green-50 transition condition-option" data-value="working">
                            <input type="radio" name="new-condition" value="working" class="mr-3">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <span>Working</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-red-50 transition condition-option" data-value="not_working">
                            <input type="radio" name="new-condition" value="not_working" class="mr-3">
                            <i class="fas fa-times-circle text-red-500 mr-2"></i>
                            <span>Not Working</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Remarks (Optional)</label>
                    <textarea id="condition-remarks" rows="2" placeholder="Add any remarks..." class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                </div>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeConditionModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="condition-submit-btn" onclick="submitConditionChange()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-save mr-2"></i>Update Condition
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mark for Repair Modal -->
<div id="repair-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRepairModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Mark for Repair</h3>
                    <p class="text-sm text-gray-500">Asset: <span id="repair-serial" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeRepairModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <input type="hidden" id="repair-asset-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Issue Type <span class="text-red-500">*</span></label>
                    <select id="repair-issue-type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">Select issue type</option>
                        <option value="hardware">Hardware Issue</option>
                        <option value="software">Software Issue</option>
                        <option value="physical_damage">Physical Damage</option>
                        <option value="connectivity">Connectivity Issue</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Issue Description <span class="text-red-500">*</span></label>
                    <textarea id="repair-description" rows="3" placeholder="Describe the issue..." class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select id="repair-priority" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeRepairModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="repair-submit-btn" onclick="submitRepairRequest()" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                    <i class="fas fa-tools mr-2"></i>Submit Repair Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dispatch Modal -->
<div id="dispatch-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDispatchModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 id="dispatch-modal-title" class="text-lg font-semibold text-gray-800">Dispatch Asset</h3>
                    <p class="text-sm text-gray-500">Asset: <span id="dispatch-serial" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeDispatchModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <input type="hidden" id="dispatch-asset-id">
                <input type="hidden" id="dispatch-type">
                
                <div class="bg-blue-50 p-3 rounded-lg">
                    <p class="text-sm text-blue-700"><i class="fas fa-info-circle mr-2"></i>Product: <span id="dispatch-product" class="font-medium"></span></p>
                </div>
                
                <!-- Engineer Selection (shown when dispatching to engineer) -->
                <div id="engineer-selection" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Engineer <span class="text-red-500">*</span></label>
                    <select id="dispatch-engineer" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">Loading engineers...</option>
                    </select>
                </div>
                
                <!-- ADV Return Info (shown when returning to ADV) -->
                <div id="adv-return-info" class="hidden">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-green-700 font-medium"><i class="fas fa-warehouse mr-2"></i>Return to ADV Warehouse</p>
                        <p class="text-xs text-green-600 mt-1">This asset will be returned to the main ADV warehouse</p>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason / Notes</label>
                    <textarea id="dispatch-notes" rows="2" placeholder="Add dispatch notes..." class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                </div>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeDispatchModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="dispatch-submit-btn" onclick="submitDispatch()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                    <i class="fas fa-paper-plane mr-2"></i>Dispatch
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const state = {
    assets: [],
    allAssets: [],
    productStock: [], // Aggregated stock by product
    stock: [], // Current page of stock
    quantityStock: [], // Non-serializable products (quantity-based)
    dispatches: [],
    recentDispatches: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', condition: '', category: '' },
    counts: { total: 0, recent_received: 0, pending_ack: 0, dispatched: 0, faulty: 0 }
};

let currentDispatchId = null;
let uploadedFiles = [];

document.addEventListener('DOMContentLoaded', function() {
    loadStocks();
    setupEventListeners();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            applyFiltersAndRender();
        }, 300);
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        applyFiltersAndRender();
    });
    
    document.getElementById('condition-filter').addEventListener('change', function(e) {
        state.filters.condition = e.target.value;
        state.pagination.page = 1;
        applyFiltersAndRender();
    });
}

function filterByCategory(category) {
    state.filters.category = category;
    state.pagination.page = 1;
    
    if (category === 'pending_ack') {
        state.filters.status = '';
        state.filters.condition = '';
    } else if (category === 'dispatched') {
        state.filters.status = '';
        state.filters.condition = '';
    } else if (category === 'faulty') {
        state.filters.status = '';
        state.filters.condition = 'not_working';
    } else if (category === 'recent_received') {
        state.filters.status = '';
        state.filters.condition = '';
    } else {
        state.filters.status = '';
        state.filters.condition = '';
    }
    
    document.getElementById('status-filter').value = state.filters.status;
    document.getElementById('condition-filter').value = state.filters.condition;
    applyFiltersAndRender();
}

function clearFilters() {
    state.filters = { search: '', status: '', condition: '', category: '' };
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('condition-filter').value = '';
    state.pagination.page = 1;
    applyFiltersAndRender();
}

async function loadStocks() {
    showLoading(true);
    
    try {
        const response = await fetch('../../api/inventory/dashboard/contractor.php', { credentials: 'include' });
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            state.allAssets = data.received_inventory?.items || [];
            const pendingAck = data.recent_activity?.pending_acknowledgments || {};
            state.dispatches = data.recent_activity?.recent_dispatches || [];
            state.recentDispatches = state.dispatches;
            state.pendingAckItems = pendingAck.items || [];
            
            // Get inventory counters (includes both serializable and non-serializable)
            const counters = data.inventory_counters || [];
            
            // Build aggregated stock by product from counters
            state.productStock = counters.map(counter => {
                const isSerializable = counter.is_serializable;
                
                // For serializable products, count assets by status
                let inStockCount = 0;
                let withEngineersCount = 0;
                let notWorkingCount = 0;
                
                if (isSerializable) {
                    const productAssets = state.allAssets.filter(a => a.product_id == counter.product_id);
                    inStockCount = productAssets.filter(a => a.status === 'in_stock').length;
                    withEngineersCount = productAssets.filter(a => a.status === 'in_use' || a.status === 'assigned').length;
                    notWorkingCount = productAssets.filter(a => a.working_condition === 'not_working').length;
                } else {
                    inStockCount = counter.quantity;
                }
                
                return {
                    product_id: counter.product_id,
                    product_name: counter.product_name,
                    category_name: counter.category_name,
                    is_serializable: isSerializable,
                    total_quantity: counter.quantity,
                    available_quantity: isSerializable ? inStockCount : counter.available_quantity || counter.quantity,
                    with_engineers: withEngineersCount,
                    not_working: notWorkingCount,
                    serial_numbers: counter.serial_numbers || []
                };
            });
            
            // Get engineer assignments for "with engineers" count
            const engineerAssignments = data.engineer_assignments || [];
            let totalWithEngineers = 0;
            engineerAssignments.forEach(eng => {
                totalWithEngineers += eng.total_items || 0;
            });
            
            state.counts.total = state.productStock.length;
            state.counts.recent_received = state.recentDispatches.length;
            state.counts.pending_ack = pendingAck.count || 0;
            state.counts.dispatched = totalWithEngineers;
            state.counts.faulty = state.allAssets.filter(a => a.working_condition === 'not_working').length;
            
            updateStats();
            applyFiltersAndRender();
        } else {
            showError(result.message || 'Failed to load stocks');
        }
    } catch (error) {
        console.error('Error loading stocks:', error);
        showError('Failed to load stocks');
    } finally {
        showLoading(false);
    }
}

function applyFiltersAndRender() {
    if (state.filters.category === 'pending_ack') {
        renderPendingAckTable(state.pendingAckItems || []);
        return;
    }
    
    if (state.filters.category === 'recent_received') {
        renderRecentReceivedTable(state.recentDispatches || []);
        return;
    }
    
    // Use product stock for main view
    let stock = [...(state.productStock || [])];
    
    if (state.filters.search) {
        const s = state.filters.search.toLowerCase();
        stock = stock.filter(p => 
            (p.product_name || '').toLowerCase().includes(s) ||
            (p.category_name || '').toLowerCase().includes(s)
        );
    }
    
    if (state.filters.category === 'dispatched') {
        // Show only products with items assigned to engineers
        stock = stock.filter(p => p.with_engineers > 0);
    }
    
    if (state.filters.category === 'faulty') {
        // Show only products with not working items
        stock = stock.filter(p => p.not_working > 0);
    }
    
    state.pagination.total = stock.length;
    state.pagination.total_pages = Math.ceil(stock.length / state.pagination.limit);
    
    const start = (state.pagination.page - 1) * state.pagination.limit;
    const end = start + state.pagination.limit;
    state.stock = stock.slice(start, end);
    
    renderTable();
    renderPagination();
}

function updateStats() {
    document.getElementById('total-count').textContent = state.counts.total;
    document.getElementById('recent-received-count').textContent = state.counts.recent_received;
    document.getElementById('pending-ack-count').textContent = state.counts.pending_ack;
    document.getElementById('dispatched-count').textContent = state.counts.dispatched;
    document.getElementById('faulty-count').textContent = state.counts.faulty;
}

function renderTable() {
    const tbody = document.getElementById('stocks-tbody');
    
    if (!state.stock || state.stock.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-boxes text-4xl mb-3 text-gray-300"></i>
                    <p>No stocks found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.stock.map((stock, index) => {
        const isSerializable = stock.is_serializable;
        const available = stock.available_quantity || 0;
        const withEngineers = stock.with_engineers || 0;
        const hasIssues = stock.not_working > 0;
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 ${isSerializable ? 'bg-gradient-to-br from-purple-50 to-purple-100' : 'bg-gradient-to-br from-blue-50 to-blue-100'} rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas ${isSerializable ? 'fa-barcode' : 'fa-box'} ${isSerializable ? 'text-purple-500' : 'text-blue-500'} text-xs"></i>
                    </div>
                    <span class="font-medium text-xs text-gray-800">${escapeHtml(stock.product_name || '-')}</span>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(stock.category_name || '-')}</td>
            <td class="px-4 py-2.5">
                ${isSerializable 
                    ? '<span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-[10px]">Serializable</span>'
                    : '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">Quantity</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <span class="font-semibold text-xs text-green-600">${available}</span>
                ${isSerializable && stock.total_quantity ? `<span class="text-gray-400 text-[10px]">/ ${stock.total_quantity}</span>` : ''}
            </td>
            <td class="px-4 py-2.5">
                ${withEngineers > 0 
                    ? `<span class="font-semibold text-xs text-purple-600">${withEngineers}</span>`
                    : '<span class="text-xs text-gray-400">0</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                ${hasIssues 
                    ? `<span class="inline-flex items-center px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[10px]"><i class="fas fa-exclamation-triangle mr-1"></i>${stock.not_working} Faulty</span>`
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>OK</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center space-x-1">
                    ${isSerializable ? `
                    <button onclick="viewProductAssets(${stock.product_id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Assets">
                        <i class="fas fa-list text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `}).join('');
}

function viewProductAssets(productId) {
    // Find assets for this product
    const productAssets = state.allAssets.filter(a => a.product_id == productId);
    if (productAssets.length === 0) {
        showToast('No assets found for this product', 'info');
        return;
    }
    
    // Show modal with asset list
    const product = state.productStock.find(p => p.product_id == productId);
    const productName = product ? product.product_name : 'Unknown Product';
    
    let html = `
        <div class="p-4">
            <h4 class="font-semibold text-gray-800 mb-3">${escapeHtml(productName)} - Assets</h4>
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left text-gray-500">Serial Number</th>
                            <th class="px-3 py-2 text-left text-gray-500">Status</th>
                            <th class="px-3 py-2 text-left text-gray-500">Condition</th>
                            <th class="px-3 py-2 text-left text-gray-500">Holder</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        ${productAssets.map(asset => `
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-mono">${escapeHtml(asset.serial_number || 'N/A')}</td>
                                <td class="px-3 py-2">${getStatusBadge(asset.status)}</td>
                                <td class="px-3 py-2">${getConditionBadge(asset.working_condition)}</td>
                                <td class="px-3 py-2">${asset.current_holder_name ? escapeHtml(asset.current_holder_name) : '<span class="text-gray-400">In Stock</span>'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    showAssetListModal(html);
}

function showAssetListModal(content) {
    let modal = document.getElementById('asset-list-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'asset-list-modal';
        modal.className = 'hidden fixed inset-0 z-50 overflow-y-auto';
        modal.innerHTML = `
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAssetListModal()"></div>
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
                    <div class="flex items-center justify-between p-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Asset Details</h3>
                        <button onclick="closeAssetListModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="asset-list-content"></div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('asset-list-content').innerHTML = content;
    modal.classList.remove('hidden');
}

function closeAssetListModal() {
    const modal = document.getElementById('asset-list-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function renderPendingAckTable(items) {
    const tbody = document.getElementById('stocks-tbody');
    
    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-check-circle text-4xl mb-3 text-green-300"></i>
                    <p>All materials acknowledged</p>
                </td>
            </tr>
        `;
        state.pagination.total = 0;
        state.pagination.total_pages = 0;
        renderPagination();
        return;
    }
    
    tbody.innerHTML = items.map((dispatch, index) => `
        <tr class="hover:bg-gray-50/50 bg-orange-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${index + 1}</td>
            <td class="px-4 py-2.5">
                <span class="font-medium text-xs text-primary font-mono">${escapeHtml(dispatch.dispatch_number || 'N/A')}</span>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-truck text-orange-500 text-xs"></i>
                    </div>
                    <div>
                        <span class="text-xs text-gray-800">Material Received</span>
                        <p class="text-[10px] text-gray-500">From: ${escapeHtml(dispatch.from_warehouse_name || dispatch.from_company_name || 'ADV')}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5" colspan="3">
                <span class="inline-flex items-center px-2 py-0.5 bg-orange-100 text-orange-700 rounded-full text-[10px] font-medium">
                    <span class="w-1.5 h-1.5 bg-orange-500 rounded-full mr-1.5"></span>Pending Ack.
                </span>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${formatDate(dispatch.dispatch_date)}</td>
            <td class="px-4 py-2.5">
                <button onclick="openAcknowledgeModal(${dispatch.id}, '${escapeHtml(dispatch.dispatch_number)}')" 
                    class="px-2.5 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-[10px] transition">
                    <i class="fas fa-check mr-1"></i>Acknowledge
                </button>
            </td>
        </tr>
    `).join('');
    
    state.pagination.total = items.length;
    state.pagination.total_pages = 1;
    renderPagination();
}

function renderRecentReceivedTable(dispatches) {
    const tbody = document.getElementById('stocks-tbody');
    
    if (dispatches.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-truck-loading text-4xl mb-3 text-gray-300"></i>
                    <p>No recent material received</p>
                </td>
            </tr>
        `;
        state.pagination.total = 0;
        state.pagination.total_pages = 0;
        renderPagination();
        return;
    }
    
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-700',
        in_transit: 'bg-blue-100 text-blue-700',
        delivered: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700'
    };
    
    tbody.innerHTML = dispatches.map((dispatch, index) => `
        <tr class="hover:bg-gray-50/50 bg-green-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${index + 1}</td>
            <td class="px-4 py-2.5">
                <span class="font-medium text-xs text-primary font-mono">${escapeHtml(dispatch.dispatch_number || 'N/A')}</span>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-green-50 to-green-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-truck-loading text-green-500 text-xs"></i>
                    </div>
                    <div>
                        <span class="text-xs text-gray-800">Material Received</span>
                        <p class="text-[10px] text-gray-500">From: ${escapeHtml(dispatch.from_warehouse_name || dispatch.from_company_name || 'ADV')}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2 py-0.5 ${statusColors[dispatch.status] || 'bg-gray-100 text-gray-700'} rounded-full text-[10px] font-medium">
                    <span class="w-1.5 h-1.5 ${dispatch.status === 'delivered' ? 'bg-green-500' : dispatch.status === 'in_transit' ? 'bg-blue-500' : 'bg-gray-500'} rounded-full mr-1.5"></span>
                    ${(dispatch.status || '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </span>
            </td>
            <td class="px-4 py-2.5">
                ${dispatch.acknowledgment_status === 'acknowledged' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Acknowledged</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-orange-100 text-orange-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-orange-500 rounded-full mr-1.5"></span>Pending</span>'}
            </td>
            <td class="px-4 py-2.5"><span class="text-xs text-gray-400">-</span></td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${formatDate(dispatch.dispatch_date)}</td>
            <td class="px-4 py-2.5">
                ${dispatch.acknowledgment_status !== 'acknowledged' 
                    ? `<button onclick="openAcknowledgeModal(${dispatch.id}, '${escapeHtml(dispatch.dispatch_number)}')" 
                        class="px-2.5 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-[10px] transition">
                        <i class="fas fa-check mr-1"></i>Acknowledge
                    </button>`
                    : '<span class="text-green-600 text-[10px]"><i class="fas fa-check-circle mr-1"></i>Done</span>'}
            </td>
        </tr>
    `).join('');
    
    state.pagination.total = dispatches.length;
    state.pagination.total_pages = 1;
    renderPagination();
}

function getStatusBadge(status) {
    const badges = {
        'in_stock': '<span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>In Stock</span>',
        'dispatched': '<span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1.5"></span>Dispatched</span>',
        'assigned': '<span class="inline-flex items-center px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-1.5"></span>Assigned</span>',
        'in_use': '<span class="inline-flex items-center px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-indigo-500 rounded-full mr-1.5"></span>In Use</span>',
        'returned': '<span class="inline-flex items-center px-2 py-0.5 bg-orange-100 text-orange-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-orange-500 rounded-full mr-1.5"></span>Returned</span>',
        'under_repair': '<span class="inline-flex items-center px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-1.5"></span>Under Repair</span>'
    };
    return badges[status] || `<span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-[10px]">${status || '-'}</span>`;
}

function getConditionBadge(condition) {
    if (condition === 'working') {
        return '<span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Working</span>';
    } else if (condition === 'not_working') {
        return '<span class="inline-flex items-center px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>Not Working</span>';
    }
    return '<span class="text-xs text-gray-400">-</span>';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    const start = total > 0 ? (page - 1) * limit + 1 : 0;
    const end = Math.min(page * limit, total);
    
    document.getElementById('pagination-info').textContent = total > 0 ? `Showing ${start} to ${end} of ${total} entries` : 'No entries';
    
    const controls = document.getElementById('pagination-controls');
    if (total_pages <= 1) { controls.innerHTML = ''; return; }
    
    let html = `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i></button>`;
    
    for (let i = Math.max(1, page - 2); i <= Math.min(total_pages, page + 2); i++) {
        html += `<button onclick="goToPage(${i})" class="px-3 py-1 rounded border ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">${i}</button>`;
    }
    
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i></button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    applyFiltersAndRender();
}

function viewAsset(asset) {
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Serial Number</span>
                <span class="font-medium text-primary">${escapeHtml(asset.serial_number || 'N/A')}</span>
            </div>
            <div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Product</span>
                <span class="text-gray-800">${escapeHtml(asset.product_name || 'N/A')}</span>
            </div>
            <div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Status</span>
                ${getStatusBadge(asset.status)}
            </div>
            <div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Condition</span>
                ${getConditionBadge(asset.working_condition)}
            </div>
            ${asset.source_warehouse_name ? `<div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Source</span>
                <span class="text-gray-800">${escapeHtml(asset.source_warehouse_name)}</span>
            </div>` : ''}
            ${asset.dispatch_number ? `<div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Dispatch #</span>
                <span class="text-gray-800">${escapeHtml(asset.dispatch_number)}</span>
            </div>` : ''}
            ${asset.received_date ? `<div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Received</span>
                <span class="text-gray-800">${formatDate(asset.received_date)}</span>
            </div>` : ''}
        </div>
    `;
    document.getElementById('view-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
}

function showError(msg) { showToast(msg, 'error'); }

function showToast(message, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-[100] ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Acknowledge Modal Functions
function openAcknowledgeModal(dispatchId, dispatchNumber) {
    currentDispatchId = dispatchId;
    uploadedFiles = [];
    document.getElementById('ack-dispatch-number').textContent = dispatchNumber;
    document.getElementById('ack-notes').value = '';
    document.getElementById('ack-condition').value = 'good';
    document.getElementById('file-preview-container').innerHTML = '';
    document.getElementById('acknowledge-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAcknowledgeModal() {
    document.getElementById('acknowledge-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentDispatchId = null;
    uploadedFiles = [];
}

function handleFileSelect(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('file-preview-container');
    
    for (let file of files) {
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm'];
        if (!validTypes.includes(file.type)) { showToast('Invalid file type', 'error'); continue; }
        
        const maxSize = file.type.startsWith('video/') ? 50 * 1024 * 1024 : 10 * 1024 * 1024;
        if (file.size > maxSize) { showToast('File too large', 'error'); continue; }
        
        uploadedFiles.push(file);
        
        const previewDiv = document.createElement('div');
        previewDiv.className = 'relative';
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewDiv.innerHTML = `
                    <img src="${e.target.result}" class="w-16 h-16 object-cover rounded-lg border">
                    <button type="button" onclick="removeFile(${uploadedFiles.length - 1})" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs"><i class="fas fa-times"></i></button>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            previewDiv.innerHTML = `
                <div class="w-16 h-16 bg-gray-100 rounded-lg border flex items-center justify-center"><i class="fas fa-video text-gray-400"></i></div>
                <button type="button" onclick="removeFile(${uploadedFiles.length - 1})" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs"><i class="fas fa-times"></i></button>
            `;
        }
        previewContainer.appendChild(previewDiv);
    }
    event.target.value = '';
}

function removeFile(index) {
    uploadedFiles.splice(index, 1);
    const previewContainer = document.getElementById('file-preview-container');
    previewContainer.innerHTML = '';
    uploadedFiles.forEach((file, i) => {
        const previewDiv = document.createElement('div');
        previewDiv.className = 'relative';
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewDiv.innerHTML = `<img src="${e.target.result}" class="w-16 h-16 object-cover rounded-lg border">
                    <button type="button" onclick="removeFile(${i})" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs"><i class="fas fa-times"></i></button>`;
            };
            reader.readAsDataURL(file);
        } else {
            previewDiv.innerHTML = `<div class="w-16 h-16 bg-gray-100 rounded-lg border flex items-center justify-center"><i class="fas fa-video text-gray-400"></i></div>
                <button type="button" onclick="removeFile(${i})" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs"><i class="fas fa-times"></i></button>`;
        }
        previewContainer.appendChild(previewDiv);
    });
}

async function submitAcknowledgment() {
    if (!currentDispatchId) return;
    if (uploadedFiles.length === 0) { showToast('Please upload at least one photo or video as proof', 'error'); return; }
    
    const submitBtn = document.getElementById('ack-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    try {
        const formData = new FormData();
        formData.append('dispatch_id', currentDispatchId);
        formData.append('notes', document.getElementById('ack-notes').value.trim());
        formData.append('condition', document.getElementById('ack-condition').value);
        uploadedFiles.forEach((file, index) => formData.append(`proof_files[${index}]`, file));
        
        const response = await fetch('../../api/inventory/dispatch/acknowledge.php', {
            method: 'POST', credentials: 'include', body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showToast('Material acknowledged successfully!', 'success');
            closeAcknowledgeModal();
            loadStocks();
        } else {
            showToast(result.message || 'Failed to acknowledge', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to submit acknowledgment', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm Receipt';
    }
}

// Action Menu Functions
let activeMenuId = null;

function toggleActionMenu(assetId, event) {
    event.stopPropagation();
    
    // Remove any existing dropdown
    const existingDropdown = document.getElementById('action-dropdown-active');
    if (existingDropdown) {
        existingDropdown.remove();
    }
    
    // If clicking the same menu, just close it
    if (activeMenuId === assetId) {
        activeMenuId = null;
        return;
    }
    
    // Get the asset data
    const asset = state.allAssets.find(a => a.id === assetId) || state.assets.find(a => a.id === assetId);
    if (!asset) return;
    
    // Get button position
    const button = event.currentTarget;
    const rect = button.getBoundingClientRect();
    
    // Create dropdown
    const dropdown = document.createElement('div');
    dropdown.id = 'action-dropdown-active';
    dropdown.className = 'fixed w-52 bg-white rounded-lg shadow-xl border z-[9999]';
    dropdown.style.top = `${rect.bottom + 5}px`;
    dropdown.style.left = `${rect.left - 180}px`;
    
    dropdown.innerHTML = `
        <div class="py-2">
            <button onclick="openConditionModal(${asset.id}, '${escapeHtml(asset.serial_number)}', '${asset.working_condition || 'working'}')" 
                class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3">
                <i class="fas fa-heartbeat text-blue-500"></i>
                <span>Change Condition</span>
            </button>
            <button onclick="openRepairModal(${asset.id}, '${escapeHtml(asset.serial_number)}')" 
                class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3">
                <i class="fas fa-tools text-orange-500"></i>
                <span>Mark for Repair</span>
            </button>
            <hr class="my-1 border-gray-200">
            <button onclick="openDispatchModal(${asset.id}, '${escapeHtml(asset.serial_number)}', '${escapeHtml(asset.product_name || '')}', 'engineer')" 
                class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3">
                <i class="fas fa-user-hard-hat text-purple-500"></i>
                <span>Dispatch to Engineer</span>
            </button>
            <button onclick="openDispatchModal(${asset.id}, '${escapeHtml(asset.serial_number)}', '${escapeHtml(asset.product_name || '')}', 'adv')" 
                class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-3">
                <i class="fas fa-warehouse text-green-500"></i>
                <span>Return to ADV</span>
            </button>
        </div>
    `;
    
    document.body.appendChild(dropdown);
    activeMenuId = assetId;
    
    // Adjust position if dropdown goes off screen
    const dropdownRect = dropdown.getBoundingClientRect();
    if (dropdownRect.right > window.innerWidth) {
        dropdown.style.left = `${window.innerWidth - dropdownRect.width - 10}px`;
    }
    if (dropdownRect.bottom > window.innerHeight) {
        dropdown.style.top = `${rect.top - dropdownRect.height - 5}px`;
    }
}

// Close menu when clicking outside
document.addEventListener('click', function(e) {
    if (activeMenuId && !e.target.closest('#action-dropdown-active') && !e.target.closest('[onclick*="toggleActionMenu"]')) {
        const dropdown = document.getElementById('action-dropdown-active');
        if (dropdown) dropdown.remove();
        activeMenuId = null;
    }
});

// Helper to close active dropdown
function closeActiveDropdown() {
    const dropdown = document.getElementById('action-dropdown-active');
    if (dropdown) dropdown.remove();
    activeMenuId = null;
}

// Change Condition Modal Functions
function openConditionModal(assetId, serialNumber, currentCondition) {
    document.getElementById('condition-asset-id').value = assetId;
    document.getElementById('condition-serial').textContent = serialNumber;
    document.getElementById('condition-remarks').value = '';
    
    // Set current condition
    document.querySelectorAll('input[name="new-condition"]').forEach(radio => {
        radio.checked = radio.value === currentCondition;
        const label = radio.closest('.condition-option');
        if (radio.checked) {
            label.classList.add('border-primary', 'bg-primary/5');
        } else {
            label.classList.remove('border-primary', 'bg-primary/5');
        }
    });
    
    // Add change listeners
    document.querySelectorAll('input[name="new-condition"]').forEach(radio => {
        radio.onchange = function() {
            document.querySelectorAll('.condition-option').forEach(opt => {
                opt.classList.remove('border-primary', 'bg-primary/5');
            });
            this.closest('.condition-option').classList.add('border-primary', 'bg-primary/5');
        };
    });
    
    document.getElementById('condition-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    closeActiveDropdown();
}

function closeConditionModal() {
    document.getElementById('condition-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

async function submitConditionChange() {
    const assetId = document.getElementById('condition-asset-id').value;
    const newCondition = document.querySelector('input[name="new-condition"]:checked')?.value;
    const remarks = document.getElementById('condition-remarks').value.trim();
    
    if (!newCondition) {
        showToast('Please select a condition', 'error');
        return;
    }
    
    const submitBtn = document.getElementById('condition-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    
    try {
        const response = await fetch('../../api/inventory/assets/update-condition.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ asset_id: assetId, working_condition: newCondition, remarks: remarks })
        });
        
        const result = await response.json();
        if (result.success) {
            showToast('Condition updated successfully!', 'success');
            closeConditionModal();
            loadStocks();
        } else {
            showToast(result.message || 'Failed to update condition', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to update condition', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Update Condition';
    }
}

// Repair Modal Functions
function openRepairModal(assetId, serialNumber) {
    document.getElementById('repair-asset-id').value = assetId;
    document.getElementById('repair-serial').textContent = serialNumber;
    document.getElementById('repair-issue-type').value = '';
    document.getElementById('repair-description').value = '';
    document.getElementById('repair-priority').value = 'medium';
    
    document.getElementById('repair-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    closeActiveDropdown();
}

function closeRepairModal() {
    document.getElementById('repair-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

async function submitRepairRequest() {
    const assetId = document.getElementById('repair-asset-id').value;
    const issueType = document.getElementById('repair-issue-type').value;
    const description = document.getElementById('repair-description').value.trim();
    const priority = document.getElementById('repair-priority').value;
    
    if (!issueType) {
        showToast('Please select an issue type', 'error');
        return;
    }
    if (!description) {
        showToast('Please describe the issue', 'error');
        return;
    }
    
    const submitBtn = document.getElementById('repair-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    try {
        const response = await fetch('../../api/inventory/assets/mark-repair.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ asset_id: assetId, issue_type: issueType, description: description, priority: priority })
        });
        
        const result = await response.json();
        if (result.success) {
            showToast('Repair request submitted successfully!', 'success');
            closeRepairModal();
            loadStocks();
        } else {
            showToast(result.message || 'Failed to submit repair request', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to submit repair request', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-tools mr-2"></i>Submit Repair Request';
    }
}

// Dispatch Modal Functions
let engineersLoaded = false;

function openDispatchModal(assetId, serialNumber, productName, dispatchType) {
    document.getElementById('dispatch-asset-id').value = assetId;
    document.getElementById('dispatch-type').value = dispatchType;
    document.getElementById('dispatch-serial').textContent = serialNumber;
    document.getElementById('dispatch-product').textContent = productName;
    document.getElementById('dispatch-notes').value = '';
    
    if (dispatchType === 'engineer') {
        document.getElementById('dispatch-modal-title').textContent = 'Dispatch to Engineer';
        document.getElementById('engineer-selection').classList.remove('hidden');
        document.getElementById('adv-return-info').classList.add('hidden');
        document.getElementById('dispatch-submit-btn').innerHTML = '<i class="fas fa-user-hard-hat mr-2"></i>Dispatch to Engineer';
        if (!engineersLoaded) loadEngineers();
    } else {
        document.getElementById('dispatch-modal-title').textContent = 'Return to ADV';
        document.getElementById('engineer-selection').classList.add('hidden');
        document.getElementById('adv-return-info').classList.remove('hidden');
        document.getElementById('dispatch-submit-btn').innerHTML = '<i class="fas fa-warehouse mr-2"></i>Return to ADV';
    }
    
    document.getElementById('dispatch-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    closeActiveDropdown();
}

function closeDispatchModal() {
    document.getElementById('dispatch-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

async function loadEngineers() {
    const select = document.getElementById('dispatch-engineer');
    select.innerHTML = '<option value="">Loading engineers...</option>';
    
    try {
        const response = await fetch('../../api/users/engineers.php', { credentials: 'include' });
        const result = await response.json();
        
        if (result.success && result.data) {
            select.innerHTML = '<option value="">Select an engineer</option>';
            result.data.forEach(eng => {
                select.innerHTML += `<option value="${eng.id}">${escapeHtml(eng.name || eng.first_name + ' ' + eng.last_name)}</option>`;
            });
            engineersLoaded = true;
        } else {
            select.innerHTML = '<option value="">No engineers found</option>';
        }
    } catch (error) {
        console.error('Error loading engineers:', error);
        select.innerHTML = '<option value="">Failed to load engineers</option>';
    }
}

async function submitDispatch() {
    const assetId = document.getElementById('dispatch-asset-id').value;
    const dispatchType = document.getElementById('dispatch-type').value;
    const notes = document.getElementById('dispatch-notes').value.trim();
    
    let payload = { asset_id: assetId, notes: notes };
    
    if (dispatchType === 'engineer') {
        const engineerId = document.getElementById('dispatch-engineer').value;
        if (!engineerId) {
            showToast('Please select an engineer', 'error');
            return;
        }
        payload.to_user_id = engineerId;
        payload.dispatch_type = 'to_engineer';
    } else {
        payload.dispatch_type = 'return_to_adv';
    }
    
    const submitBtn = document.getElementById('dispatch-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch('../../api/inventory/contractor/dispatch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        if (result.success) {
            showToast(dispatchType === 'engineer' ? 'Asset dispatched to engineer!' : 'Asset returned to ADV!', 'success');
            closeDispatchModal();
            loadStocks();
        } else {
            showToast(result.message || 'Failed to dispatch', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to dispatch asset', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = dispatchType === 'engineer' 
            ? '<i class="fas fa-user-hard-hat mr-2"></i>Dispatch to Engineer'
            : '<i class="fas fa-warehouse mr-2"></i>Return to ADV';
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layouts/main.php';
?>
