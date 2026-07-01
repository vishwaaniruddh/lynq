<?php
/**
 * Engineer Pending Receives Page
 * 
 * Displays all pending receives for the engineer (user-level)
 * Includes:
 * - List of pending receives with dispatch details
 * - Sender, items, quantities, dispatch date
 * - Overdue item highlighting
 * - Accept/Reject action buttons
 * - Partial acceptance modal
 * 
 * Requirements: 2.2, 2.3, 3.2, 4.2, 10.1, 10.5
 */

require_once __DIR__ . '/../../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../../views/auth/login.php');
    exit;
}

// Check engineer access
if (!isEngineerUser()) {
    $_SESSION['flash_error'] = 'Access denied. Engineer users only.';
    header('Location: ../../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '../..';
$pageTitle = 'Pending Receives';
$currentPage = 'engineer_pending_receives';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../../engineer/dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Pending Receives']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Pending Receives</h3>
            <p class="text-sm text-gray-500">Review and accept materials dispatched to you</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="bulkAcceptSelected()" id="bulk-accept-btn" class="hidden px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-check-double mr-2"></i>Accept Selected (<span id="selected-count">0</span>)
            </button>
            <button onclick="refreshData()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="p-4 border-b bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <!-- All Pending Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-inbox text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">All Pending</p>
                        <p id="total-count" class="text-xl font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <!-- Pending Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-clock text-yellow-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Awaiting Action</p>
                        <p id="pending-count" class="text-xl font-semibold text-yellow-600">0</p>
                    </div>
                </div>
            </div>
            <!-- Overdue Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('overdue')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Overdue</p>
                        <p id="overdue-count" class="text-xl font-semibold text-red-600">0</p>
                    </div>
                </div>
            </div>
            <!-- Processed Card -->
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('processed')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Processed</p>
                        <p id="processed-count" class="text-xl font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by dispatch number, sender..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                    <option value="partial">Partial</option>
                </select>
            </div>
            <div>
                <input type="date" id="from-date" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="From Date">
            </div>
            <div>
                <input type="date" id="to-date" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="To Date">
            </div>
            <button onclick="clearFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </button>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading pending receives...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto overflow-y-visible">
        <table id="pending-receives-table" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300" onchange="toggleSelectAll()">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dispatch #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Sender</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Items</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dispatch Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Days Pending</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody id="pending-receives-tbody" class="divide-y">
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">Loading...</td>
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
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Dispatch Details</h3>
                    <p class="text-sm text-gray-500">Dispatch: <span id="view-dispatch-number" class="font-medium text-primary"></span></p>
                </div>
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

<!-- Accept Modal -->
<div id="accept-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAcceptModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Accept Materials</h3>
                    <p class="text-sm text-gray-500">Dispatch: <span id="accept-dispatch-number" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeAcceptModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <input type="hidden" id="accept-pending-receive-id">
                
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-green-800">Confirm Full Acceptance</p>
                            <p class="text-sm text-green-600">All items will be added to your inventory</p>
                        </div>
                    </div>
                </div>
                
                <div id="accept-items-summary" class="border rounded-lg p-4">
                    <!-- Items summary will be populated here -->
                </div>
                
                <div class="flex items-center text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>Need to report discrepancies? Use <a href="#" onclick="switchToPartialAccept()" class="text-primary hover:underline">Partial Accept</a> instead.</span>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeAcceptModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="accept-submit-btn" onclick="submitAccept()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Accept All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="reject-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRejectModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Reject Materials</h3>
                    <p class="text-sm text-gray-500">Dispatch: <span id="reject-dispatch-number" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closeRejectModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <input type="hidden" id="reject-pending-receive-id">
                
                <div class="bg-red-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-red-800">Reject Entire Dispatch</p>
                            <p class="text-sm text-red-600">All items will be returned to sender's inventory</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                    <select id="reject-reason-type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary mb-2" onchange="handleRejectReasonChange()">
                        <option value="">Select a reason</option>
                        <option value="damaged">Items Damaged</option>
                        <option value="wrong_items">Wrong Items Received</option>
                        <option value="quantity_mismatch">Quantity Mismatch</option>
                        <option value="not_ordered">Items Not Ordered</option>
                        <option value="quality_issue">Quality Issues</option>
                        <option value="other">Other</option>
                    </select>
                    <textarea id="reject-reason" rows="3" placeholder="Please provide detailed reason for rejection (minimum 5 characters)..." 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                    <p id="reject-reason-error" class="text-red-500 text-sm mt-1 hidden">Rejection reason is required (minimum 5 characters)</p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeRejectModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="reject-submit-btn" onclick="submitReject()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-times mr-2"></i>Reject
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Partial Accept Modal -->
<div id="partial-accept-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closePartialAcceptModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Partial Acceptance</h3>
                    <p class="text-sm text-gray-500">Dispatch: <span id="partial-dispatch-number" class="font-medium text-primary"></span></p>
                </div>
                <button onclick="closePartialAcceptModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <input type="hidden" id="partial-pending-receive-id">
                
                <div class="bg-amber-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-amber-500 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-amber-800">Specify Received Quantities</p>
                            <p class="text-sm text-amber-600">Enter the actual quantities received for each item. Discrepancies will be recorded.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Items Table -->
                <div class="border rounded-lg overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Expected</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Received</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Discrepancy</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody id="partial-items-tbody" class="divide-y">
                            <!-- Items will be populated here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Discrepancy Preview -->
                <div id="discrepancy-preview" class="hidden bg-red-50 p-4 rounded-lg">
                    <h4 class="font-medium text-red-800 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Discrepancy Summary</h4>
                    <div id="discrepancy-summary" class="text-sm text-red-700"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">General Notes (Optional)</label>
                    <textarea id="partial-notes" rows="2" placeholder="Add any general notes about this partial acceptance..." 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closePartialAcceptModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                <button id="partial-submit-btn" onclick="submitPartialAccept()" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">
                    <i class="fas fa-check-double mr-2"></i>Confirm Partial Accept
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    pendingReceives: [],
    allPendingReceives: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', fromDate: '', toDate: '' },
    counts: { total: 0, pending: 0, overdue: 0, processed: 0 },
    selectedIds: new Set(),
    currentPendingReceive: null
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPendingReceives();
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
    
    document.getElementById('from-date').addEventListener('change', function(e) {
        state.filters.fromDate = e.target.value;
        state.pagination.page = 1;
        applyFiltersAndRender();
    });
    
    document.getElementById('to-date').addEventListener('change', function(e) {
        state.filters.toDate = e.target.value;
        state.pagination.page = 1;
        applyFiltersAndRender();
    });
}

function filterByStatus(status) {
    if (status === 'overdue') {
        state.filters.status = 'pending';
        state.filters.overdue = true;
    } else if (status === 'processed') {
        state.filters.status = '';
        state.filters.processed = true;
    } else {
        state.filters.status = status;
        state.filters.overdue = false;
        state.filters.processed = false;
    }
    document.getElementById('status-filter').value = state.filters.status;
    state.pagination.page = 1;
    applyFiltersAndRender();
}

function clearFilters() {
    state.filters = { search: '', status: '', fromDate: '', toDate: '', overdue: false, processed: false };
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('from-date').value = '';
    document.getElementById('to-date').value = '';
    state.pagination.page = 1;
    applyFiltersAndRender();
}

function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
}

function showError(message) {
    const tbody = document.getElementById('pending-receives-tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="px-6 py-8 text-center text-red-500">
                <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                <p>${message}</p>
                <button onclick="loadPendingReceives()" class="mt-3 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Retry</button>
            </td>
        </tr>
    `;
}

async function loadPendingReceives() {
    showLoading(true);
    
    try {
        // For engineer, use view=user to get user-level pending receives
        let url = '../../api/inventory/receive/pending.php?view=user';
        
        const response = await fetch(url, { credentials: 'include' });
        const result = await response.json();
        
        if (result.success) {
            state.allPendingReceives = result.data.pending_receives || [];
            
            // Calculate counts
            state.counts.total = state.allPendingReceives.length;
            state.counts.pending = state.allPendingReceives.filter(r => r.status === 'pending').length;
            state.counts.overdue = state.allPendingReceives.filter(r => r.status === 'pending' && r.is_overdue).length;
            state.counts.processed = state.allPendingReceives.filter(r => r.status !== 'pending').length;
            
            updateStats();
            applyFiltersAndRender();
        } else {
            showError(result.message || 'Failed to load pending receives');
        }
    } catch (error) {
        console.error('Error loading pending receives:', error);
        showError('Failed to load pending receives');
    } finally {
        showLoading(false);
    }
}

function applyFiltersAndRender() {
    let receives = [...state.allPendingReceives];
    
    // Apply search filter
    if (state.filters.search) {
        const s = state.filters.search.toLowerCase();
        receives = receives.filter(r => 
            (r.dispatch_number || '').toLowerCase().includes(s) ||
            (r.sender_name || '').toLowerCase().includes(s) ||
            (r.sender_company_name || '').toLowerCase().includes(s)
        );
    }
    
    // Apply status filter
    if (state.filters.status) {
        receives = receives.filter(r => r.status === state.filters.status);
    }
    
    // Apply overdue filter
    if (state.filters.overdue) {
        receives = receives.filter(r => r.status === 'pending' && r.is_overdue);
    }
    
    // Apply processed filter
    if (state.filters.processed) {
        receives = receives.filter(r => r.status !== 'pending');
    }
    
    // Apply date filters
    if (state.filters.fromDate) {
        receives = receives.filter(r => {
            const dispatchDate = (r.dispatch_date || r.created_at || '').substring(0, 10);
            return dispatchDate >= state.filters.fromDate;
        });
    }
    
    if (state.filters.toDate) {
        receives = receives.filter(r => {
            const dispatchDate = (r.dispatch_date || r.created_at || '').substring(0, 10);
            return dispatchDate <= state.filters.toDate;
        });
    }
    
    // Pagination
    state.pagination.total = receives.length;
    state.pagination.total_pages = Math.ceil(receives.length / state.pagination.limit);
    
    const start = (state.pagination.page - 1) * state.pagination.limit;
    const end = start + state.pagination.limit;
    state.pendingReceives = receives.slice(start, end);
    
    renderTable();
    renderPagination();
}

function updateStats() {
    document.getElementById('total-count').textContent = state.counts.total;
    document.getElementById('pending-count').textContent = state.counts.pending;
    document.getElementById('overdue-count').textContent = state.counts.overdue;
    document.getElementById('processed-count').textContent = state.counts.processed;
}

function renderTable() {
    const tbody = document.getElementById('pending-receives-tbody');
    
    if (state.pendingReceives.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                    <p>No pending receives found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = state.pendingReceives.map((receive, index) => {
        const isOverdue = receive.status === 'pending' && receive.is_overdue;
        const rowClass = isOverdue ? 'bg-red-50' : '';
        const statusBadge = getStatusBadge(receive.status, isOverdue);
        const daysPending = receive.days_pending || 0;
        const itemCount = receive.items?.length || receive.total_items || 0;
        const totalQty = receive.total_expected_quantity || 0;
        
        return `
            <tr class="${rowClass} hover:bg-gray-50 transition">
                <td class="px-4 py-3">
                    ${receive.status === 'pending' ? `
                        <input type="checkbox" class="row-checkbox rounded border-gray-300" 
                            data-id="${receive.id}" onchange="toggleRowSelection(${receive.id})">
                    ` : ''}
                </td>
                <td class="px-4 py-3">
                    <span class="font-medium text-gray-800">${receive.dispatch_number || 'N/A'}</span>
                </td>
                <td class="px-4 py-3">
                    <div>
                        <p class="font-medium text-gray-800">${receive.sender_name || receive.sender_company_name || 'ADV'}</p>
                        <p class="text-xs text-gray-500">${receive.sender_type || 'warehouse'}</p>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center">
                        <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-medium">
                            ${itemCount} item${itemCount !== 1 ? 's' : ''} (${totalQty} qty)
                        </span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="text-gray-600">${formatDate(receive.dispatch_date || receive.created_at)}</span>
                </td>
                <td class="px-4 py-3">
                    ${receive.status === 'pending' ? `
                        <span class="${isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600'}">
                            ${daysPending} day${daysPending !== 1 ? 's' : ''}
                            ${isOverdue ? '<i class="fas fa-exclamation-triangle ml-1"></i>' : ''}
                        </span>
                    ` : '-'}
                </td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <button onclick="viewDetails(${receive.id})" class="p-2 text-gray-500 hover:text-primary hover:bg-gray-100 rounded-lg transition" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${receive.status === 'pending' ? `
                            <button onclick="openAcceptModal(${receive.id})" class="p-2 text-green-500 hover:text-green-700 hover:bg-green-50 rounded-lg transition" title="Accept">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="openPartialAcceptModal(${receive.id})" class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition" title="Partial Accept">
                                <i class="fas fa-check-double"></i>
                            </button>
                            <button onclick="openRejectModal(${receive.id})" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition" title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getStatusBadge(status, isOverdue = false) {
    const badges = {
        'pending': isOverdue 
            ? '<span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Overdue</span>'
            : '<span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>',
        'accepted': '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Accepted</span>',
        'rejected': '<span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Rejected</span>',
        'partial': '<span class="px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Partial</span>'
    };
    return badges[status] || '<span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Unknown</span>';
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function renderPagination() {
    const info = document.getElementById('pagination-info');
    const controls = document.getElementById('pagination-controls');
    
    const start = (state.pagination.page - 1) * state.pagination.limit + 1;
    const end = Math.min(state.pagination.page * state.pagination.limit, state.pagination.total);
    
    info.textContent = state.pagination.total > 0 
        ? `Showing ${start} to ${end} of ${state.pagination.total} entries`
        : 'No entries';
    
    if (state.pagination.total_pages <= 1) {
        controls.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<button onclick="goToPage(${state.pagination.page - 1})" 
        class="px-3 py-1 rounded border ${state.pagination.page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}"
        ${state.pagination.page === 1 ? 'disabled' : ''}>
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    for (let i = 1; i <= state.pagination.total_pages; i++) {
        if (i === 1 || i === state.pagination.total_pages || (i >= state.pagination.page - 1 && i <= state.pagination.page + 1)) {
            html += `<button onclick="goToPage(${i})" 
                class="px-3 py-1 rounded border ${i === state.pagination.page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">
                ${i}
            </button>`;
        } else if (i === state.pagination.page - 2 || i === state.pagination.page + 2) {
            html += '<span class="px-2">...</span>';
        }
    }
    
    // Next button
    html += `<button onclick="goToPage(${state.pagination.page + 1})" 
        class="px-3 py-1 rounded border ${state.pagination.page === state.pagination.total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}"
        ${state.pagination.page === state.pagination.total_pages ? 'disabled' : ''}>
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    applyFiltersAndRender();
}

function refreshData() {
    loadPendingReceives();
}
</script>


<script>
// Selection functions
function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
        const id = parseInt(cb.dataset.id);
        if (selectAll.checked) {
            state.selectedIds.add(id);
        } else {
            state.selectedIds.delete(id);
        }
    });
    
    updateBulkAcceptButton();
}

function toggleRowSelection(id) {
    if (state.selectedIds.has(id)) {
        state.selectedIds.delete(id);
    } else {
        state.selectedIds.add(id);
    }
    updateBulkAcceptButton();
}

function updateBulkAcceptButton() {
    const btn = document.getElementById('bulk-accept-btn');
    const count = state.selectedIds.size;
    
    if (count > 0) {
        btn.classList.remove('hidden');
        document.getElementById('selected-count').textContent = count;
    } else {
        btn.classList.add('hidden');
    }
}

// View Details Modal
function viewDetails(id) {
    const receive = state.allPendingReceives.find(r => r.id === id);
    if (!receive) return;
    
    document.getElementById('view-dispatch-number').textContent = receive.dispatch_number || 'N/A';
    
    const items = receive.items || [];
    let itemsHtml = items.length > 0 ? `
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Product</th>
                    <th class="px-3 py-2 text-center">Quantity</th>
                    <th class="px-3 py-2 text-left">Serial #</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                ${items.map(item => `
                    <tr>
                        <td class="px-3 py-2">${item.product_name || 'N/A'}</td>
                        <td class="px-3 py-2 text-center">${item.expected_quantity || item.quantity || 1}</td>
                        <td class="px-3 py-2">${item.serial_number || '-'}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    ` : '<p class="text-gray-500 text-center py-4">No items found</p>';
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Sender</p>
                    <p class="font-medium">${receive.sender_name || receive.sender_company_name || 'ADV'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Dispatch Date</p>
                    <p class="font-medium">${formatDate(receive.dispatch_date || receive.created_at)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p>${getStatusBadge(receive.status, receive.is_overdue)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Days Pending</p>
                    <p class="font-medium">${receive.days_pending || 0} days</p>
                </div>
            </div>
            
            <div>
                <p class="text-sm text-gray-500 mb-2">Items</p>
                <div class="border rounded-lg overflow-hidden">
                    ${itemsHtml}
                </div>
            </div>
            
            ${receive.rejection_reason ? `
                <div class="bg-red-50 p-3 rounded-lg">
                    <p class="text-sm text-red-600 font-medium">Rejection Reason:</p>
                    <p class="text-sm text-red-700">${receive.rejection_reason}</p>
                </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('view-modal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
}

// Accept Modal
function openAcceptModal(id) {
    const receive = state.allPendingReceives.find(r => r.id === id);
    if (!receive) return;
    
    state.currentPendingReceive = receive;
    document.getElementById('accept-pending-receive-id').value = id;
    document.getElementById('accept-dispatch-number').textContent = receive.dispatch_number || 'N/A';
    
    const items = receive.items || [];
    document.getElementById('accept-items-summary').innerHTML = `
        <p class="text-sm text-gray-500 mb-2">Items to Accept:</p>
        <ul class="space-y-1">
            ${items.map(item => `
                <li class="flex justify-between text-sm">
                    <span>${item.product_name || 'Unknown Product'}</span>
                    <span class="font-medium">${item.expected_quantity || item.quantity || 1} qty</span>
                </li>
            `).join('')}
        </ul>
        <div class="mt-3 pt-3 border-t flex justify-between font-medium">
            <span>Total Items:</span>
            <span>${items.length} (${receive.total_expected_quantity || 0} qty)</span>
        </div>
    `;
    
    document.getElementById('accept-modal').classList.remove('hidden');
}

function closeAcceptModal() {
    document.getElementById('accept-modal').classList.add('hidden');
    state.currentPendingReceive = null;
}

async function submitAccept() {
    const id = document.getElementById('accept-pending-receive-id').value;
    const btn = document.getElementById('accept-submit-btn');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch('../../api/inventory/receive/accept.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pending_receive_id: parseInt(id) })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeAcceptModal();
            showToast('Materials accepted successfully', 'success');
            loadPendingReceives();
        } else {
            showToast(result.message || 'Failed to accept materials', 'error');
        }
    } catch (error) {
        console.error('Error accepting:', error);
        showToast('Failed to accept materials', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Accept All';
    }
}

// Reject Modal
function openRejectModal(id) {
    const receive = state.allPendingReceives.find(r => r.id === id);
    if (!receive) return;
    
    state.currentPendingReceive = receive;
    document.getElementById('reject-pending-receive-id').value = id;
    document.getElementById('reject-dispatch-number').textContent = receive.dispatch_number || 'N/A';
    document.getElementById('reject-reason-type').value = '';
    document.getElementById('reject-reason').value = '';
    document.getElementById('reject-reason-error').classList.add('hidden');
    
    document.getElementById('reject-modal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('reject-modal').classList.add('hidden');
    state.currentPendingReceive = null;
}

function handleRejectReasonChange() {
    const type = document.getElementById('reject-reason-type').value;
    const textarea = document.getElementById('reject-reason');
    
    const prefixes = {
        'damaged': 'Items received were damaged: ',
        'wrong_items': 'Wrong items received: ',
        'quantity_mismatch': 'Quantity does not match dispatch: ',
        'not_ordered': 'Items were not ordered: ',
        'quality_issue': 'Quality issues found: ',
        'other': ''
    };
    
    if (prefixes[type] !== undefined) {
        textarea.value = prefixes[type];
        textarea.focus();
    }
}

async function submitReject() {
    const id = document.getElementById('reject-pending-receive-id').value;
    const reason = document.getElementById('reject-reason').value.trim();
    const btn = document.getElementById('reject-submit-btn');
    
    if (reason.length < 5) {
        document.getElementById('reject-reason-error').classList.remove('hidden');
        return;
    }
    
    document.getElementById('reject-reason-error').classList.add('hidden');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch('../../api/inventory/receive/reject.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ 
                pending_receive_id: parseInt(id),
                reason: reason
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeRejectModal();
            showToast('Materials rejected successfully', 'success');
            loadPendingReceives();
        } else {
            showToast(result.message || 'Failed to reject materials', 'error');
        }
    } catch (error) {
        console.error('Error rejecting:', error);
        showToast('Failed to reject materials', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-times mr-2"></i>Reject';
    }
}

// Partial Accept Modal
function openPartialAcceptModal(id) {
    const receive = state.allPendingReceives.find(r => r.id === id);
    if (!receive) return;
    
    state.currentPendingReceive = receive;
    document.getElementById('partial-pending-receive-id').value = id;
    document.getElementById('partial-dispatch-number').textContent = receive.dispatch_number || 'N/A';
    document.getElementById('partial-notes').value = '';
    
    const items = receive.items || [];
    const tbody = document.getElementById('partial-items-tbody');
    
    tbody.innerHTML = items.map((item, index) => `
        <tr>
            <td class="px-4 py-3">
                <div>
                    <p class="font-medium text-gray-800">${item.product_name || 'Unknown Product'}</p>
                    ${item.serial_number ? `<p class="text-xs text-gray-500">S/N: ${item.serial_number}</p>` : ''}
                </div>
                <input type="hidden" class="item-dispatch-id" value="${item.dispatch_item_id || item.id}">
            </td>
            <td class="px-4 py-3 text-center">
                <span class="font-medium text-gray-800 expected-qty">${item.expected_quantity || item.quantity || 1}</span>
            </td>
            <td class="px-4 py-3 text-center">
                <input type="number" class="received-qty w-20 px-2 py-1 border rounded text-center focus:ring-2 focus:ring-primary" 
                    value="${item.expected_quantity || item.quantity || 1}" 
                    min="0" max="${item.expected_quantity || item.quantity || 1}"
                    onchange="updateDiscrepancyPreview()">
            </td>
            <td class="px-4 py-3 text-center">
                <span class="discrepancy-display text-gray-500">0</span>
            </td>
            <td class="px-4 py-3">
                <input type="text" class="item-notes w-full px-2 py-1 border rounded text-sm focus:ring-2 focus:ring-primary" 
                    placeholder="Notes...">
            </td>
        </tr>
    `).join('');
    
    document.getElementById('discrepancy-preview').classList.add('hidden');
    document.getElementById('partial-accept-modal').classList.remove('hidden');
    
    updateDiscrepancyPreview();
}

function closePartialAcceptModal() {
    document.getElementById('partial-accept-modal').classList.add('hidden');
    state.currentPendingReceive = null;
}

function updateDiscrepancyPreview() {
    const rows = document.querySelectorAll('#partial-items-tbody tr');
    let hasDiscrepancy = false;
    let summaryHtml = '';
    
    rows.forEach(row => {
        const expected = parseInt(row.querySelector('.expected-qty').textContent) || 0;
        const received = parseInt(row.querySelector('.received-qty').value) || 0;
        const discrepancy = expected - received;
        
        const discrepancyDisplay = row.querySelector('.discrepancy-display');
        if (discrepancy > 0) {
            discrepancyDisplay.textContent = `-${discrepancy}`;
            discrepancyDisplay.className = 'discrepancy-display text-red-600 font-medium';
            hasDiscrepancy = true;
            
            const productName = row.querySelector('td:first-child p').textContent;
            summaryHtml += `<p>• ${productName}: Missing ${discrepancy} unit(s)</p>`;
        } else if (discrepancy < 0) {
            discrepancyDisplay.textContent = `+${Math.abs(discrepancy)}`;
            discrepancyDisplay.className = 'discrepancy-display text-green-600 font-medium';
        } else {
            discrepancyDisplay.textContent = '0';
            discrepancyDisplay.className = 'discrepancy-display text-gray-500';
        }
    });
    
    const preview = document.getElementById('discrepancy-preview');
    if (hasDiscrepancy) {
        document.getElementById('discrepancy-summary').innerHTML = summaryHtml;
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
    }
}

async function submitPartialAccept() {
    const id = document.getElementById('partial-pending-receive-id').value;
    const notes = document.getElementById('partial-notes').value.trim();
    const btn = document.getElementById('partial-submit-btn');
    
    // Collect items
    const rows = document.querySelectorAll('#partial-items-tbody tr');
    const items = [];
    
    rows.forEach(row => {
        const dispatchItemId = row.querySelector('.item-dispatch-id').value;
        const receivedQty = parseInt(row.querySelector('.received-qty').value) || 0;
        const itemNotes = row.querySelector('.item-notes').value.trim();
        
        items.push({
            dispatch_item_id: parseInt(dispatchItemId),
            received_quantity: receivedQty,
            notes: itemNotes || null
        });
    });
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch('../../api/inventory/receive/partial.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ 
                pending_receive_id: parseInt(id),
                items: items,
                notes: notes || null
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closePartialAcceptModal();
            showToast('Partial acceptance recorded successfully', 'success');
            loadPendingReceives();
        } else {
            showToast(result.message || 'Failed to process partial acceptance', 'error');
        }
    } catch (error) {
        console.error('Error partial accepting:', error);
        showToast('Failed to process partial acceptance', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-double mr-2"></i>Confirm Partial Accept';
    }
}

function switchToPartialAccept() {
    if (state.currentPendingReceive) {
        closeAcceptModal();
        openPartialAcceptModal(state.currentPendingReceive.id);
    }
}

// Bulk Accept
async function bulkAcceptSelected() {
    if (state.selectedIds.size === 0) return;
    
    if (!confirm(`Are you sure you want to accept ${state.selectedIds.size} pending receive(s)?`)) {
        return;
    }
    
    const btn = document.getElementById('bulk-accept-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch('../../api/inventory/receive/bulk-accept.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ 
                pending_receive_ids: Array.from(state.selectedIds)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(`Successfully accepted ${result.data.accepted_count || state.selectedIds.size} pending receive(s)`, 'success');
            state.selectedIds.clear();
            updateBulkAcceptButton();
            loadPendingReceives();
        } else {
            showToast(result.message || 'Failed to bulk accept', 'error');
        }
    } catch (error) {
        console.error('Error bulk accepting:', error);
        showToast('Failed to bulk accept', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-check-double mr-2"></i>Accept Selected (<span id="selected-count">${state.selectedIds.size}</span>)`;
    }
}

// Toast notification
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
