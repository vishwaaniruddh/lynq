<?php
/**
 * Repair Management Page
 * 
 * Repair request form
 * Repair tracking list with status
 * Complete repair action
 * 
 * Requirements: 7.2, 7.3
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission - ADV users always have access
if (!can('inventory.repairs.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view repairs';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Repair Management';
$currentPage = 'inventory_repairs';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Repairs']
];

// Get user permissions - ADV users have all permissions
$isAdv = isAdvUser();
$canCreate = can('inventory.repairs.create') || $isAdv;
$canComplete = can('inventory.repairs.complete') || $isAdv;

ob_start();
?>

<div class="space-y-6">
    <!-- Repair List Section -->
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Repair Management</h3>
                <p class="text-sm text-gray-500">Track and manage repair requests for assets</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($canCreate): ?>
                <button onclick="openCreateRepairModal()" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition flex items-center">
                    <i class="fas fa-wrench mr-2"></i>New Repair Request
                </button>
                <?php endif; ?>
                <button onclick="exportRepairs()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-file-excel mr-2"></i>Export
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="p-4 border-b bg-gray-50">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" id="search-input" placeholder="Search serial number or vendor..." 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <select id="overdue-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Repairs</option>
                        <option value="overdue">Overdue Only</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-yellow-50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-yellow-600 font-medium">Pending</p>
                        <p id="pending-count" class="text-2xl font-bold text-yellow-700">0</p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-blue-600 font-medium">In Progress</p>
                        <p id="in-progress-count" class="text-2xl font-bold text-blue-700">0</p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-tools text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-green-600 font-medium">Completed</p>
                        <p id="completed-count" class="text-2xl font-bold text-green-700">0</p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-red-50 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-red-600 font-medium">Overdue</p>
                        <p id="overdue-count" class="text-2xl font-bold text-red-700">0</p>
                    </div>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading indicator -->
        <div id="loading-indicator" class="hidden p-8 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
            <p class="mt-2 text-gray-500">Loading repairs...</p>
        </div>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table id="repairs-table" class="w-full">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Asset</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Vendor</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Send Date</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Expected Return</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Cost</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="repairs-tbody" class="divide-y">
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


<!-- Create Repair Modal -->
<div id="create-repair-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeCreateRepairModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-wrench mr-2 text-yellow-500"></i>Create Repair Request</h3>
                <button onclick="closeCreateRepairModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="create-repair-form" onsubmit="saveNewRepair(event)">
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label for="repair-asset-select" class="block text-sm font-medium text-gray-700 mb-1">Select Asset <span class="text-red-500">*</span></label>
                        <select id="repair-asset-select" name="asset_id" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select an asset...</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Only non-working, repairable assets are shown</p>
                        <p id="asset-select-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <div>
                        <label for="new-repair-vendor" class="block text-sm font-medium text-gray-700 mb-1">Repair Vendor <span class="text-red-500">*</span></label>
                        <input type="text" id="new-repair-vendor" name="repair_vendor" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter vendor name">
                        <p id="vendor-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="new-repair-cost" class="block text-sm font-medium text-gray-700 mb-1">Estimated Cost</label>
                            <input type="number" id="new-repair-cost" name="estimated_cost" step="0.01" min="0"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="0.00">
                        </div>
                        <div>
                            <label for="new-repair-send-date" class="block text-sm font-medium text-gray-700 mb-1">Send Date</label>
                            <input type="date" id="new-repair-send-date" name="send_date"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    
                    <div>
                        <label for="new-repair-expected" class="block text-sm font-medium text-gray-700 mb-1">Expected Return Date</label>
                        <input type="date" id="new-repair-expected" name="expected_return_date"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="new-repair-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="new-repair-notes" name="notes" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Describe the issue or repair needed"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeCreateRepairModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-new-repair-btn" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                        <i class="fas fa-wrench mr-2"></i>Create Repair
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Repair Modal -->
<div id="view-repair-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewRepairModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Repair Details</h3>
                <button onclick="closeViewRepairModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-repair-content" class="p-5 max-h-[70vh] overflow-y-auto">
                <!-- Content populated by JavaScript -->
            </div>
            <div id="view-repair-actions" class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewRepairModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Complete Repair Modal -->
<div id="complete-repair-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeCompleteRepairModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-check-circle mr-2 text-green-500"></i>Complete Repair</h3>
                <button onclick="closeCompleteRepairModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="complete-repair-form" onsubmit="submitCompleteRepair(event)">
                <input type="hidden" id="complete-repair-id" name="repair_id">
                <div class="p-5 space-y-4">
                    <div id="complete-repair-info" class="p-4 bg-gray-50 rounded-lg">
                        <!-- Repair info populated by JavaScript -->
                    </div>
                    
                    <div>
                        <label for="actual-cost" class="block text-sm font-medium text-gray-700 mb-1">Actual Repair Cost</label>
                        <input type="number" id="actual-cost" name="actual_cost" step="0.01" min="0"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="0.00">
                    </div>
                    
                    <div>
                        <label for="return-date" class="block text-sm font-medium text-gray-700 mb-1">Return Date</label>
                        <input type="date" id="return-date" name="actual_return_date"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="return-warehouse" class="block text-sm font-medium text-gray-700 mb-1">Return to Warehouse <span class="text-red-500">*</span></label>
                        <select id="return-warehouse" name="return_warehouse_id" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select warehouse...</option>
                        </select>
                        <p id="warehouse-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <div>
                        <label for="complete-notes" class="block text-sm font-medium text-gray-700 mb-1">Completion Notes</label>
                        <textarea id="complete-notes" name="notes" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Notes about the completed repair"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeCompleteRepairModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="submit-complete-btn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-check mr-2"></i>Complete Repair
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// State management
const state = {
    repairs: [],
    assets: [],
    warehouses: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', overdue: '' },
    summary: { pending: 0, in_progress: 0, completed: 0, overdue: 0 },
    permissions: {
        canCreate: <?php echo json_encode($canCreate); ?>,
        canComplete: <?php echo json_encode($canComplete); ?>
    },
    currentRepair: null
};

const API_URL = '../api/inventory/repairs';

const STATUS_CONFIG = {
    pending: { label: 'Pending', color: 'bg-yellow-100 text-yellow-700', icon: 'fa-clock' },
    in_progress: { label: 'In Progress', color: 'bg-blue-100 text-blue-700', icon: 'fa-spinner' },
    completed: { label: 'Completed', color: 'bg-green-100 text-green-700', icon: 'fa-check' },
    cancelled: { label: 'Cancelled', color: 'bg-red-100 text-red-700', icon: 'fa-times' }
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadWarehouses();
    loadRepairs();
    setupEventListeners();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadRepairs();
        }, 300);
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadRepairs();
    });
    
    document.getElementById('overdue-filter').addEventListener('change', function(e) {
        state.filters.overdue = e.target.value;
        state.pagination.page = 1;
        loadRepairs();
    });
}

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
    const selects = document.querySelectorAll('#return-warehouse');
    selects.forEach(select => {
        select.innerHTML = '<option value="">Select warehouse...</option>' +
            state.warehouses.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
    });
}

async function loadRepairs() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.overdue === 'overdue') params.append('overdue', '1');
        if (state.filters.search) params.append('search', state.filters.search);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.repairs = data.data.repairs || [];
            state.pagination = data.data.pagination || state.pagination;
            state.summary = data.data.summary || state.summary;
            
            updateSummaryCards();
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load repairs');
        }
    } catch (error) {
        console.error('Error loading repairs:', error);
        showError('Failed to load repairs');
    } finally {
        showLoading(false);
    }
}

function updateSummaryCards() {
    document.getElementById('pending-count').textContent = state.summary.pending || 0;
    document.getElementById('in-progress-count').textContent = state.summary.in_progress || 0;
    document.getElementById('completed-count').textContent = state.summary.completed || 0;
    document.getElementById('overdue-count').textContent = state.summary.overdue || 0;
}

function renderTable() {
    const tbody = document.getElementById('repairs-tbody');
    
    if (state.repairs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-tools text-4xl mb-3 text-gray-300"></i>
                    <p>No repairs found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.repairs.map((repair, index) => {
        const statusConfig = STATUS_CONFIG[repair.status] || { label: repair.status, color: 'bg-gray-100 text-gray-700', icon: 'fa-circle' };
        const isOverdue = repair.is_overdue || (repair.expected_return_date && new Date(repair.expected_return_date) < new Date() && repair.status !== 'completed');
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors ${isOverdue ? 'bg-red-50/50' : ''}">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <span class="font-medium text-xs text-primary">${escapeHtml(repair.serial_number || '-')}</span>
                <p class="text-[10px] text-gray-500">${escapeHtml(repair.product_name || '-')}</p>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(repair.repair_vendor || '-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${formatDate(repair.send_date)}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">
                ${repair.expected_return_date ? `<span class="${isOverdue ? 'text-red-600 font-medium' : ''}">${formatDate(repair.expected_return_date)}</span>` : '-'}
                ${isOverdue ? '<i class="fas fa-exclamation-triangle text-red-500 ml-1 text-[10px]"></i>' : ''}
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${repair.estimated_cost ? '₹' + parseFloat(repair.estimated_cost).toFixed(2) : '-'}</td>
            <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2 py-0.5 ${statusConfig.color} rounded-full text-[10px] font-medium">
                    <i class="fas ${statusConfig.icon} mr-1"></i>${statusConfig.label}
                </span>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center space-x-1">
                    <button onclick="viewRepairDetail(${repair.id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="View Details">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${state.permissions.canComplete && repair.status !== 'completed' && repair.status !== 'cancelled' ? `
                    <button onclick="openCompleteRepairModal(${repair.id})" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Complete Repair">
                        <i class="fas fa-check-circle text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `}).join('');
}$' + parseFloat(repair.estimated_cost).toFixed(2) : '-'}</td>
            <td class="px-6 py-4">
                <span class="inline-flex items-center px-2.5 py-1 ${statusConfig.color} rounded-full text-xs font-medium">
                    <i class="fas ${statusConfig.icon} mr-1.5"></i>${statusConfig.label}
                </span>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center space-x-2">
                    <button onclick="viewRepairDetail(${repair.id})" class="p-2 text-gray-500 hover:text-primary" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${state.permissions.canComplete && repair.status !== 'completed' && repair.status !== 'cancelled' ? `
                    <button onclick="openCompleteRepairModal(${repair.id})" class="p-2 text-gray-500 hover:text-green-600" title="Complete Repair">
                        <i class="fas fa-check-circle"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `}).join('');
}

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
    
    let html = `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    for (let i = Math.max(1, page - 2); i <= Math.min(total_pages, page + 2); i++) {
        html += `<button onclick="goToPage(${i})" 
            class="px-3 py-1 rounded border ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">
            ${i}
        </button>`;
    }
    
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadRepairs();
}

async function viewRepairDetail(repairId) {
    try {
        const response = await fetch(`${API_URL}/show.php?id=${repairId}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            const repair = data.data.repair;
            showToast(`Repair #${repair.id}: ${repair.serial_number} - ${repair.status}`, 'info');
        } else {
            showError(data.error?.message || 'Failed to load repair details');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to load repair details');
    }
}

// Create Repair Modal
function openCreateRepairModal() {
    document.getElementById('create-repair-modal').classList.remove('hidden');
    loadAssetsForRepair();
}

function closeCreateRepairModal() {
    document.getElementById('create-repair-modal').classList.add('hidden');
    document.getElementById('create-repair-form').reset();
}

async function loadAssetsForRepair() {
    try {
        const response = await fetch('../api/inventory/assets/index.php?working_condition=not_working&limit=100', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('repair-asset');
            select.innerHTML = '<option value="">Select asset...</option>' +
                (data.data.assets || []).map(a => 
                    `<option value="${a.id}">${escapeHtml(a.serial_number)} - ${escapeHtml(a.product_name || '')}</option>`
                ).join('');
        }
    } catch (error) {
        console.error('Error loading assets:', error);
    }
}

async function submitCreateRepair(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    if (!data.asset_id) {
        showError('Please select an asset');
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Repair request created successfully', 'success');
            closeCreateRepairModal();
            loadRepairs();
        } else {
            showError(result.error?.message || 'Failed to create repair request');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to create repair request');
    }
}

// Complete Repair Modal
function openCompleteRepairModal(repairId) {
    const repair = state.repairs.find(r => r.id === repairId);
    if (!repair) return;
    
    state.currentRepair = repair;
    document.getElementById('complete-repair-id').value = repairId;
    document.getElementById('complete-repair-info').innerHTML = `
        <p class="text-sm"><strong>Serial:</strong> ${escapeHtml(repair.serial_number)}</p>
        <p class="text-sm"><strong>Product:</strong> ${escapeHtml(repair.product_name || '-')}</p>
        <p class="text-sm"><strong>Vendor:</strong> ${escapeHtml(repair.repair_vendor || '-')}</p>
    `;
    
    document.getElementById('actual-cost').value = repair.estimated_cost || '';
    document.getElementById('return-date').value = new Date().toISOString().split('T')[0];
    
    document.getElementById('complete-repair-modal').classList.remove('hidden');
}

function closeCompleteRepairModal() {
    document.getElementById('complete-repair-modal').classList.add('hidden');
    document.getElementById('complete-repair-form').reset();
}

async function submitCompleteRepair(event) {
    event.preventDefault();
    
    const repairId = document.getElementById('complete-repair-id').value;
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    if (!data.return_warehouse_id) {
        showError('Please select a return warehouse');
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/complete.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                repair_id: repairId,
                ...data
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Repair completed successfully', 'success');
            closeCompleteRepairModal();
            loadRepairs();
        } else {
            showError(result.error?.message || 'Failed to complete repair');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to complete repair');
    }
}

// Export
function exportRepairs() {
    const params = new URLSearchParams();
    if (state.filters.status) params.append('status', state.filters.status);
    window.open(`../api/inventory/export/index.php?type=repairs&${params}`, '_blank');
}

// Utility functions
function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    const table = document.getElementById('repairs-table');
    if (show) {
        indicator?.classList.remove('hidden');
        table?.classList.add('opacity-50');
    } else {
        indicator?.classList.add('hidden');
        table?.classList.remove('opacity-50');
    }
}

function showError(message) {
    showToast(message, 'error');
}

function showToast(message, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
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

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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