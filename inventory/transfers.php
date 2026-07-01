<?php
/**
 * Transfer Management Page
 * 
 * Inter-warehouse transfer form
 * Transfer history with status tracking
 * 
 * Requirements: 5.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission - ADV users always have access
if (!can('inventory.transfers.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view transfers';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Transfer Management';
$currentPage = 'inventory_transfers';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Transfers']
];

// Get user permissions - ADV users have all permissions
$isAdv = isAdvUser();
$canCreate = can('inventory.transfers.create') || $isAdv;

ob_start();
?>

<div class="space-y-6">
    <!-- Transfer List Section -->
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Inter-Warehouse Transfers</h3>
                <p class="text-sm text-gray-500">Transfer inventory between warehouses</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($canCreate): ?>
                <button onclick="openTransferModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                    <i class="fas fa-exchange-alt mr-2"></i>New Transfer
                </button>
                <?php endif; ?>
                <button onclick="exportTransfers()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>Export
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="p-4 border-b bg-gray-50">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" id="search-input" placeholder="Search transfer number..." 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <select id="from-warehouse-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">From: All Warehouses</option>
                    </select>
                </div>
                <div>
                    <select id="to-warehouse-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">To: All Warehouses</option>
                    </select>
                </div>
                <div>
                    <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_transit">In Transit</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading indicator -->
        <div id="loading-indicator" class="hidden p-8 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
            <p class="mt-2 text-gray-500">Loading transfers...</p>
        </div>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table id="transfer-table" class="w-full">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Transfer #</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">From Warehouse</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">To Warehouse</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="transfer-tbody" class="divide-y">
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
</div>

<!-- Create Transfer Modal -->
<div id="transfer-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeTransferModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Create Inter-Warehouse Transfer</h3>
                <button onclick="closeTransferModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="transfer-form" onsubmit="saveTransfer(event)">
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- Warehouses -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="transfer-from-warehouse" class="block text-sm font-medium text-gray-700 mb-1">From Warehouse <span class="text-red-500">*</span></label>
                            <select id="transfer-from-warehouse" name="from_warehouse_id" required onchange="onFromWarehouseChange()"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select Source Warehouse</option>
                            </select>
                            <p id="from_warehouse_id-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="transfer-to-warehouse" class="block text-sm font-medium text-gray-700 mb-1">To Warehouse <span class="text-red-500">*</span></label>
                            <select id="transfer-to-warehouse" name="to_warehouse_id" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select Destination Warehouse</option>
                            </select>
                            <p id="to_warehouse_id-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="transfer-date" class="block text-sm font-medium text-gray-700 mb-1">Transfer Date</label>
                            <input type="date" id="transfer-date" name="transfer_date" value="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                                <input type="checkbox" id="process-immediately" name="process_immediately" class="w-4 h-4 text-primary rounded">
                                <span class="ml-2 text-sm text-gray-700">Process immediately</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Items Section -->
                    <div class="border-t pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-sm font-medium text-gray-700">Items <span class="text-red-500">*</span></label>
                            <button type="button" onclick="addTransferItem()" class="px-3 py-1 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm">
                                <i class="fas fa-plus mr-1"></i>Add Item
                            </button>
                        </div>
                        <div id="transfer-items-container" class="space-y-3">
                            <!-- Items will be added here -->
                        </div>
                        <p id="items-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <div>
                        <label for="transfer-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="transfer-notes" name="notes" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeTransferModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-transfer-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-exchange-alt mr-2"></i>Create Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Transfer Modal -->
<div id="view-transfer-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewTransferModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Transfer Details</h3>
                <button onclick="closeViewTransferModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-transfer-content" class="p-5 max-h-[70vh] overflow-y-auto">
                <!-- Content populated by JavaScript -->
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewTransferModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
                <button id="process-transfer-btn" onclick="processTransfer()" class="hidden px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Process Transfer
                </button>
            </div>
        </div>
    </div>
</div>


<script>
// State management
const state = {
    transfers: [],
    warehouses: [],
    products: [],
    availableAssets: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', from_warehouse_id: '', to_warehouse_id: '', status: '' },
    permissions: {
        create: <?php echo json_encode($canCreate); ?>
    },
    currentTransfer: null,
    transferItemCounter: 0
};

const API_URL = '../api/inventory/transfers';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadWarehouses();
    loadProducts();
    loadTransfers();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadTransfers();
        }, 300);
    });
    
    document.getElementById('from-warehouse-filter').addEventListener('change', function(e) {
        state.filters.from_warehouse_id = e.target.value;
        state.pagination.page = 1;
        loadTransfers();
    });
    
    document.getElementById('to-warehouse-filter').addEventListener('change', function(e) {
        state.filters.to_warehouse_id = e.target.value;
        state.pagination.page = 1;
        loadTransfers();
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadTransfers();
    });
}

// Load data for dropdowns
async function loadWarehouses() {
    try {
        const response = await fetch('../api/inventory/warehouses/index.php?limit=100', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.warehouses = data.data.warehouses || [];
            populateWarehouseDropdowns();
        }
    } catch (error) {
        console.error('Error loading warehouses:', error);
    }
}

function populateWarehouseDropdowns() {
    const fromFilter = document.getElementById('from-warehouse-filter');
    const toFilter = document.getElementById('to-warehouse-filter');
    const fromSelect = document.getElementById('transfer-from-warehouse');
    const toSelect = document.getElementById('transfer-to-warehouse');
    
    const activeWarehouses = state.warehouses.filter(w => w.status === 'active');
    const options = activeWarehouses.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
    
    if (fromFilter) fromFilter.innerHTML = '<option value="">From: All Warehouses</option>' + options;
    if (toFilter) toFilter.innerHTML = '<option value="">To: All Warehouses</option>' + options;
    if (fromSelect) fromSelect.innerHTML = '<option value="">Select Source Warehouse</option>' + options;
    if (toSelect) toSelect.innerHTML = '<option value="">Select Destination Warehouse</option>' + options;
}

async function loadProducts() {
    try {
        const response = await fetch('../api/inventory/products/index.php?limit=500', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.products = data.data.products || [];
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

// Load transfers from API
async function loadTransfers() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.from_warehouse_id) params.append('from_warehouse_id', state.filters.from_warehouse_id);
        if (state.filters.to_warehouse_id) params.append('to_warehouse_id', state.filters.to_warehouse_id);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.transfers = data.data.transfers;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            
            // Apply client-side search filter
            if (state.filters.search) {
                const search = state.filters.search.toLowerCase();
                state.transfers = state.transfers.filter(t => 
                    (t.transfer_number || '').toLowerCase().includes(search)
                );
            }
            
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load transfers');
        }
    } catch (error) {
        console.error('Error loading transfers:', error);
        showError('Failed to load transfers. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render table
function renderTable() {
    const tbody = document.getElementById('transfer-tbody');
    
    if (state.transfers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-exchange-alt text-4xl mb-3 text-gray-300"></i>
                    <p>No transfers found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.transfers.map((transfer, index) => {
        const statusColors = {
            pending: 'bg-yellow-100 text-yellow-700',
            in_transit: 'bg-blue-100 text-blue-700',
            completed: 'bg-green-100 text-green-700',
            cancelled: 'bg-red-100 text-red-700'
        };
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <span class="font-medium text-xs text-primary">${escapeHtml(transfer.transfer_number)}</span>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(transfer.from_warehouse_name || '-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(transfer.to_warehouse_name || '-')}</td>
            <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-[10px]">${transfer.item_count || 0} items</span>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${formatDate(transfer.transfer_date)}</td>
            <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2 py-0.5 ${statusColors[transfer.status] || 'bg-gray-100 text-gray-700'} rounded-full text-[10px] capitalize">
                    <span class="w-1.5 h-1.5 ${transfer.status === 'completed' ? 'bg-green-500' : transfer.status === 'in_transit' ? 'bg-blue-500' : transfer.status === 'cancelled' ? 'bg-red-500' : 'bg-yellow-500'} rounded-full mr-1.5"></span>${transfer.status.replace('_', ' ')}
                </span>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center space-x-1">
                    <button onclick="viewTransfer(${transfer.id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
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
    loadTransfers();
}

// Transfer Modal functions
function openTransferModal() {
    document.getElementById('transfer-form').reset();
    document.getElementById('transfer-items-container').innerHTML = '';
    state.transferItemCounter = 0;
    addTransferItem(); // Add first item row
    clearErrors();
    document.getElementById('transfer-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTransferModal() {
    document.getElementById('transfer-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function onFromWarehouseChange() {
    // Update destination warehouse options to exclude source
    const fromId = document.getElementById('transfer-from-warehouse').value;
    const toSelect = document.getElementById('transfer-to-warehouse');
    const currentTo = toSelect.value;
    
    const activeWarehouses = state.warehouses.filter(w => w.status === 'active' && w.id != fromId);
    toSelect.innerHTML = '<option value="">Select Destination Warehouse</option>' +
        activeWarehouses.map(w => `<option value="${w.id}" ${w.id == currentTo ? 'selected' : ''}>${escapeHtml(w.name)}</option>`).join('');
}

function addTransferItem() {
    const container = document.getElementById('transfer-items-container');
    const index = state.transferItemCounter++;
    
    const div = document.createElement('div');
    div.className = 'transfer-item p-4 bg-gray-50 rounded-lg';
    div.dataset.index = index;
    div.innerHTML = `
        <div class="flex items-start gap-4">
            <div class="flex-1 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Product</label>
                    <select name="items[${index}][product_id]" required onchange="onTransferItemProductChange(${index})"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary">
                        <option value="">Select Product</option>
                        ${state.products.map(p => {
                            const badge = p.is_serializable ? ' [S]' : '';
                            return `<option value="${p.id}" data-serializable="${p.is_serializable}">${escapeHtml(p.name)}${badge}</option>`;
                        }).join('')}
                    </select>
                </div>
                <div class="item-quantity-field">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Quantity</label>
                    <input type="number" name="items[${index}][quantity]" min="1" value="1"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary">
                </div>
                <div class="item-serial-field hidden col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Serial Number</label>
                    <input type="text" name="items[${index}][serial_number]" placeholder="Enter serial number"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary">
                </div>
            </div>
            <button type="button" onclick="removeTransferItem(${index})" class="mt-6 p-2 text-red-500 hover:bg-red-50 rounded-lg">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

function removeTransferItem(index) {
    const item = document.querySelector(`.transfer-item[data-index="${index}"]`);
    if (item) {
        item.remove();
    }
    
    // Ensure at least one item exists
    if (document.querySelectorAll('.transfer-item').length === 0) {
        addTransferItem();
    }
}

function onTransferItemProductChange(index) {
    const item = document.querySelector(`.transfer-item[data-index="${index}"]`);
    const select = item.querySelector(`select[name="items[${index}][product_id]"]`);
    const option = select.options[select.selectedIndex];
    const isSerializable = option && option.dataset.serializable === '1';
    
    const quantityField = item.querySelector('.item-quantity-field');
    const serialField = item.querySelector('.item-serial-field');
    
    if (isSerializable) {
        quantityField.classList.add('hidden');
        serialField.classList.remove('hidden');
    } else {
        quantityField.classList.remove('hidden');
        serialField.classList.add('hidden');
    }
}

async function saveTransfer(event) {
    event.preventDefault();
    clearErrors();
    
    const fromWarehouseId = document.getElementById('transfer-from-warehouse').value;
    const toWarehouseId = document.getElementById('transfer-to-warehouse').value;
    const transferDate = document.getElementById('transfer-date').value;
    const processImmediately = document.getElementById('process-immediately').checked;
    const notes = document.getElementById('transfer-notes').value.trim();
    
    if (!fromWarehouseId) {
        showFieldError('from_warehouse_id', 'Source warehouse is required');
        return;
    }
    if (!toWarehouseId) {
        showFieldError('to_warehouse_id', 'Destination warehouse is required');
        return;
    }
    if (fromWarehouseId === toWarehouseId) {
        showFieldError('to_warehouse_id', 'Source and destination must be different');
        return;
    }
    
    const payload = {
        from_warehouse_id: parseInt(fromWarehouseId),
        to_warehouse_id: parseInt(toWarehouseId),
        transfer_date: transferDate,
        process_immediately: processImmediately,
        notes: notes || undefined,
        items: []
    };
    
    // Collect items
    const itemElements = document.querySelectorAll('.transfer-item');
    for (const itemEl of itemElements) {
        const productSelect = itemEl.querySelector('select[name^="items"]');
        const productId = productSelect.value;
        
        if (!productId) continue;
        
        const product = state.products.find(p => p.id == productId);
        const isSerializable = product && product.is_serializable == 1;
        
        if (isSerializable) {
            const serialInput = itemEl.querySelector('input[name$="[serial_number]"]');
            const serialNumber = serialInput.value.trim();
            
            if (!serialNumber) {
                showFieldError('items', 'Serial number is required for serializable products');
                return;
            }
            
            payload.items.push({
                product_id: parseInt(productId),
                serial_number: serialNumber,
                quantity: 1
            });
        } else {
            const quantityInput = itemEl.querySelector('input[name$="[quantity]"]');
            const quantity = parseInt(quantityInput.value) || 1;
            
            payload.items.push({
                product_id: parseInt(productId),
                quantity: quantity
            });
        }
    }
    
    if (payload.items.length === 0) {
        showFieldError('items', 'At least one item is required');
        return;
    }
    
    const saveBtn = document.getElementById('save-transfer-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    try {
        const response = await fetch(`${API_URL}/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeTransferModal();
            showSuccess(data.message || 'Transfer created successfully');
            loadTransfers();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => showFieldError(field, data.errors[field]));
            } else {
                showError(data.error?.message || 'Failed to create transfer');
            }
        }
    } catch (error) {
        console.error('Error creating transfer:', error);
        showError('Failed to create transfer. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-exchange-alt mr-2"></i>Create Transfer';
    }
}


// View Transfer
async function viewTransfer(id) {
    try {
        const response = await fetch(`${API_URL}/show.php?id=${id}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.currentTransfer = data.data.transfer;
            renderTransferDetails(data.data.transfer);
            
            // Show process button if applicable
            const processBtn = document.getElementById('process-transfer-btn');
            if (state.permissions.create && data.data.transfer.status === 'pending') {
                processBtn.classList.remove('hidden');
            } else {
                processBtn.classList.add('hidden');
            }
            
            document.getElementById('view-transfer-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            showError(data.error?.message || 'Failed to load transfer details');
        }
    } catch (error) {
        console.error('Error loading transfer:', error);
        showError('Failed to load transfer details');
    }
}

function renderTransferDetails(transfer) {
    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-700',
        in_transit: 'bg-blue-100 text-blue-700',
        completed: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700'
    };
    
    let itemsHtml = '';
    if (transfer.items && transfer.items.length > 0) {
        itemsHtml = transfer.items.map(item => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center">
                    <div class="w-8 h-8 ${item.asset_id ? 'bg-purple-100' : 'bg-blue-100'} rounded flex items-center justify-center mr-3">
                        <i class="fas ${item.asset_id ? 'fa-barcode' : 'fa-box'} ${item.asset_id ? 'text-purple-500' : 'text-blue-500'} text-sm"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">${escapeHtml(item.product_name || 'Unknown Product')}</p>
                        ${item.serial_number ? `<p class="text-xs text-gray-500">S/N: ${escapeHtml(item.serial_number)}</p>` : ''}
                    </div>
                </div>
                <span class="text-gray-600">x${item.quantity || 1}</span>
            </div>
        `).join('');
    } else {
        itemsHtml = '<p class="text-gray-500 text-center py-4">No items</p>';
    }
    
    document.getElementById('view-transfer-content').innerHTML = `
        <div class="space-y-6">
            <!-- Header Info -->
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Transfer Number</p>
                    <p class="text-xl font-bold text-primary">${escapeHtml(transfer.transfer_number)}</p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 ${statusColors[transfer.status] || 'bg-gray-100 text-gray-700'} rounded-full text-sm capitalize">
                        ${transfer.status}
                    </span>
                </div>
            </div>
            
            <!-- Transfer Flow -->
            <div class="flex items-center justify-center gap-4 p-4 bg-gray-50 rounded-lg">
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-warehouse text-blue-500"></i>
                    </div>
                    <p class="font-medium text-gray-800">${escapeHtml(transfer.from_warehouse_name || '-')}</p>
                    <p class="text-xs text-gray-500">Source</p>
                </div>
                <div class="flex-1 flex items-center justify-center">
                    <i class="fas fa-arrow-right text-2xl text-gray-400"></i>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-warehouse text-green-500"></i>
                    </div>
                    <p class="font-medium text-gray-800">${escapeHtml(transfer.to_warehouse_name || '-')}</p>
                    <p class="text-xs text-gray-500">Destination</p>
                </div>
            </div>
            
            <!-- Details -->
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 uppercase mb-1">Transfer Date</p>
                    <p class="font-medium">${formatDate(transfer.transfer_date)}</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-500 uppercase mb-1">Created By</p>
                    <p class="font-medium">${escapeHtml(transfer.created_by_name || '-')}</p>
                </div>
            </div>
            
            <!-- Items -->
            <div>
                <h4 class="font-medium text-gray-800 mb-3">Items (${transfer.items?.length || 0})</h4>
                <div class="space-y-2">
                    ${itemsHtml}
                </div>
            </div>
            
            ${transfer.notes ? `
            <div>
                <h4 class="font-medium text-gray-800 mb-2">Notes</h4>
                <p class="text-gray-600 bg-gray-50 p-3 rounded-lg">${escapeHtml(transfer.notes)}</p>
            </div>
            ` : ''}
        </div>
    `;
}

function closeViewTransferModal() {
    document.getElementById('view-transfer-modal').classList.add('hidden');
    document.body.style.overflow = '';
    state.currentTransfer = null;
}

async function processTransfer() {
    if (!state.currentTransfer) return;
    
    const processBtn = document.getElementById('process-transfer-btn');
    processBtn.disabled = true;
    processBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch(`${API_URL}/process.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id: state.currentTransfer.id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeViewTransferModal();
            showSuccess(data.message || 'Transfer processed successfully');
            loadTransfers();
        } else {
            showError(data.error?.message || 'Failed to process transfer');
        }
    } catch (error) {
        console.error('Error processing transfer:', error);
        showError('Failed to process transfer');
    } finally {
        processBtn.disabled = false;
        processBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Process Transfer';
    }
}

// Export transfers
async function exportTransfers() {
    try {
        const params = new URLSearchParams({ limit: 1000 });
        if (state.filters.from_warehouse_id) params.append('from_warehouse_id', state.filters.from_warehouse_id);
        if (state.filters.to_warehouse_id) params.append('to_warehouse_id', state.filters.to_warehouse_id);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            downloadTransferCSV(data.data.transfers, 'transfers_export.csv');
            showSuccess('Transfers exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export transfers');
        }
    } catch (error) {
        console.error('Error exporting transfers:', error);
        showError('Failed to export transfers');
    }
}

function downloadTransferCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['Transfer #', 'From Warehouse', 'To Warehouse', 'Items', 'Date', 'Status'];
    const rows = data.map(t => [
        `"${(t.transfer_number || '').replace(/"/g, '""')}"`,
        `"${(t.from_warehouse_name || '').replace(/"/g, '""')}"`,
        `"${(t.to_warehouse_name || '').replace(/"/g, '""')}"`,
        t.item_count || 0,
        t.transfer_date,
        t.status
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
    document.getElementById('transfer-table').classList.toggle('hidden', show);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function showError(message) { showToast(message, 'error'); }
function showSuccess(message) { showToast(message, 'success'); }

function showToast(message, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
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

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.classList.add('hidden');
        el.textContent = '';
    });
}

function showFieldError(field, message) {
    const errorEl = document.getElementById(`${field}-error`);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
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
include __DIR__ . '/../views/layouts/main.php';
?>
