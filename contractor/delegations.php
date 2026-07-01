<?php
/**
 * Contractor Delegations List
 * 
 * Displays delegations for contractor's company
 * Includes accept/reject action buttons
 * 
 * Requirements: 4.1
 * - Display only sites delegated to contractor's company with pending status
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check contractor access - only contractor users can access this page
if (!isContractorUser()) {
    $_SESSION['flash_error'] = 'Access denied. Contractor users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Delegated Sites';
$currentPage = 'contractor_delegations';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Delegated Sites']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <!-- Header -->
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-800">Delegated Sites</h3>
            <p class="text-xs text-gray-500 mt-0.5">View and respond to site delegations from ADV</p>
        </div>
        <div class="flex items-center gap-2">
            <div id="bulk-actions" class="hidden flex items-center gap-2">
                <span id="selected-count" class="text-xs text-gray-600">0 selected</span>
                <button onclick="bulkAccept()" class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center text-xs font-medium">
                    <i class="fas fa-check-double mr-1.5"></i>Accept Selected
                </button>
                <button onclick="clearSelection()" class="px-2 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <button onclick="exportDelegations()" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center text-xs font-medium">
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
                        <i class="fas fa-list text-blue-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Total</p>
                        <p id="total-count" class="text-lg font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByStatus('pending')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-yellow-300 hover:shadow-md transition-all" id="card-pending">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-clock text-yellow-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Pending</p>
                        <p id="pending-count" class="text-lg font-semibold text-yellow-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByStatus('accepted')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-green-300 hover:shadow-md transition-all" id="card-accepted">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-green-50 to-green-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-check-circle text-green-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Accepted</p>
                        <p id="accepted-count" class="text-lg font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByStatus('rejected')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-red-300 hover:shadow-md transition-all" id="card-rejected">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-red-50 to-red-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-times-circle text-red-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Rejected</p>
                        <p id="rejected-count" class="text-lg font-semibold text-red-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-2">
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="search-input" placeholder="Search by site name, LHO, city..." 
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
                    <option value="pending">Pending</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <button onclick="clearFilters()" class="px-3 py-2 text-xs bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </button>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading delegations...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="delegations-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left">
                        <input type="checkbox" id="select-all-checkbox" onchange="toggleSelectAll()" 
                            class="w-3.5 h-3.5 text-primary border-gray-300 rounded focus:ring-primary">
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site Name</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">LHO</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Engineer</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Feasibility</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Material</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Delegated</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="delegations-tbody" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="11" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td>
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

<!-- Accept/Reject Modal -->
<div id="response-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeResponseModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Respond to Delegation</h3>
                <button onclick="closeResponseModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div id="modal-site-info" class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <!-- Site info will be populated by JavaScript -->
                </div>
                
                <div id="rejection-notes-container" class="hidden mb-4">
                    <label for="rejection-notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Rejection Notes <span class="text-red-500">*</span>
                    </label>
                    <textarea id="rejection-notes" rows="4" 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="Please provide a reason for rejection..."></textarea>
                    <p class="mt-1 text-sm text-gray-500">Notes are required when rejecting a delegation.</p>
                </div>
                
                <input type="hidden" id="modal-delegation-id" value="">
                <input type="hidden" id="modal-action" value="">
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeResponseModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button id="modal-submit-btn" onclick="submitResponse()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Delegation Details</h3>
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

<!-- Bulk Accept Confirmation Modal -->
<div id="bulk-confirm-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeBulkConfirmModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10 transform transition-all">
            <div class="p-6">
                <div class="w-14 h-14 bg-gradient-to-br from-green-50 to-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-double text-green-500 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 text-center mb-2">Confirm Bulk Accept</h3>
                <p id="bulk-confirm-message" class="text-sm text-gray-600 text-center mb-6">
                    Are you sure you want to accept <span id="bulk-confirm-count" class="font-semibold text-green-600">0</span> delegation(s)?
                </p>
                <div class="flex gap-3">
                    <button onclick="closeBulkConfirmModal()" class="flex-1 px-4 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium text-sm">
                        Cancel
                    </button>
                    <button id="bulk-confirm-btn" onclick="confirmBulkAccept()" class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm">
                        <i class="fas fa-check mr-1.5"></i>Accept All
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dispatch Material Modal -->
<div id="dispatch-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDispatchModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Dispatch Material</h3>
                <button onclick="closeDispatchModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div id="dispatch-site-info" class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <!-- Site info will be populated -->
                </div>
                
                <p class="text-sm text-gray-600 mb-4">Choose where to dispatch the material received for this site:</p>
                
                <div class="space-y-3">
                    <button onclick="dispatchToEngineer()" class="w-full flex items-center p-4 border-2 rounded-xl hover:border-blue-400 hover:bg-blue-50 transition group">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-blue-200 transition">
                            <i class="fas fa-user-hard-hat text-blue-500 text-xl"></i>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-gray-800">Dispatch to Engineer</p>
                            <p class="text-sm text-gray-500">Send material to assigned engineer</p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 ml-auto"></i>
                    </button>
                    
                    <button onclick="returnToAdv()" class="w-full flex items-center p-4 border-2 rounded-xl hover:border-green-400 hover:bg-green-50 transition group">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-green-200 transition">
                            <i class="fas fa-warehouse text-green-500 text-xl"></i>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-gray-800">Return to ADV</p>
                            <p class="text-sm text-gray-500">Return material to ADV warehouse</p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 ml-auto"></i>
                    </button>
                </div>
                
                <input type="hidden" id="dispatch-delegation-id" value="">
                <input type="hidden" id="dispatch-site-id" value="">
            </div>
            <div class="flex justify-end p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeDispatchModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    delegations: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', lho: '' },
    counts: { total: 0, pending: 0, accepted: 0, rejected: 0 },
    lhos: [],
    selectedIds: new Set()
};

// API base URL
const API_URL = '../api/delegations/index.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDelegations();
    setupEventListeners();
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
            loadDelegations();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        updateCardHighlights();
        loadDelegations();
    });
    
    // LHO filter
    document.getElementById('lho-filter').addEventListener('change', function(e) {
        state.filters.lho = e.target.value;
        state.pagination.page = 1;
        loadDelegations();
    });
}

// Filter by status (from stats cards)
function filterByStatus(status) {
    state.filters.status = status;
    document.getElementById('status-filter').value = status;
    state.pagination.page = 1;
    updateCardHighlights();
    loadDelegations();
}

// Update card highlights based on active filters
function updateCardHighlights() {
    // Reset all cards
    document.querySelectorAll('[id^="card-"]').forEach(card => {
        card.classList.remove('ring-2', 'ring-blue-400', 'ring-yellow-400', 'ring-green-400', 'ring-red-400');
    });
    
    // Highlight active filter card
    if (state.filters.status === 'pending') {
        document.getElementById('card-pending').classList.add('ring-2', 'ring-yellow-400');
    } else if (state.filters.status === 'accepted') {
        document.getElementById('card-accepted').classList.add('ring-2', 'ring-green-400');
    } else if (state.filters.status === 'rejected') {
        document.getElementById('card-rejected').classList.add('ring-2', 'ring-red-400');
    }
}

// Clear all filters
function clearFilters() {
    state.filters = { search: '', status: '', lho: '' };
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('lho-filter').value = '';
    state.pagination.page = 1;
    updateCardHighlights();
    loadDelegations();
}

// Load delegations from API
async function loadDelegations() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.lho) params.append('lho', state.filters.lho);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.delegations = data.data.delegations;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            state.counts = data.data.counts || { total: 0, pending: 0, accepted: 0, rejected: 0 };
            state.lhos = data.data.lhos || [];
            
            renderTable();
            renderPagination();
            updateStats();
            updateLHOFilter();
        } else {
            showError(data.error?.message || 'Failed to load delegations');
        }
    } catch (error) {
        console.error('Error loading delegations:', error);
        showError('Failed to load delegations. Please try again.');
    } finally {
        showLoading(false);
    }
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

// Update stats display
function updateStats() {
    document.getElementById('total-count').textContent = state.counts.total || 0;
    document.getElementById('pending-count').textContent = state.counts.pending || 0;
    document.getElementById('accepted-count').textContent = state.counts.accepted || 0;
    document.getElementById('rejected-count').textContent = state.counts.rejected || 0;
}

// Render table
function renderTable() {
    const tbody = document.getElementById('delegations-tbody');
    
    if (state.delegations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="px-4 py-10 text-center text-gray-400">
                    <i class="fas fa-share-alt text-3xl mb-2 text-gray-300"></i>
                    <p class="text-sm">No delegations found</p>
                </td>
            </tr>
        `;
        updateBulkActionsVisibility();
        return;
    }
    
    tbody.innerHTML = state.delegations.map((delegation, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5">
                ${delegation.status === 'pending' ? `
                    <input type="checkbox" class="delegation-checkbox w-3.5 h-3.5 text-primary border-gray-300 rounded focus:ring-primary"
                        data-id="${delegation.id}" onchange="toggleSelection(${delegation.id})"
                        ${state.selectedIds.has(delegation.id) ? 'checked' : ''}>
                ` : '<span class="text-gray-300 text-xs">-</span>'}
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">${(state.pagination.page - 1) * state.pagination.limit + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-map-marker-alt text-blue-500 text-xs"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="font-medium text-gray-800 text-xs block truncate">${escapeHtml(delegation.site_name || 'N/A')}</span>
                        ${delegation.bank_name ? `<p class="text-[10px] text-gray-400 truncate">${escapeHtml(delegation.bank_name)}</p>` : ''}
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px] font-medium">${escapeHtml(delegation.lho || '-')}</span>
            </td>
            <td class="px-4 py-2.5">
                <div class="text-xs text-gray-600">${escapeHtml(delegation.city || '')}, ${escapeHtml(delegation.state || '')}</div>
                <div class="text-[10px] text-gray-400">${escapeHtml(delegation.country || '')}</div>
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getStatusBadge(delegation.status)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getEngineerBadge(delegation)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getFeasibilityBadge(delegation)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getMaterialBadge(delegation)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                <div class="text-xs text-gray-600">${formatDateShort(delegation.delegated_at)}</div>
                <div class="text-[10px] text-gray-400">by ${escapeHtml(delegation.delegated_by_name || 'N/A')}</div>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <button onclick="viewDelegation(${delegation.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${delegation.status === 'pending' ? `
                        <button onclick="openAcceptModal(${delegation.id})" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Accept">
                            <i class="fas fa-check text-xs"></i>
                        </button>
                        <button onclick="openRejectModal(${delegation.id})" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Reject">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    ` : ''}
                    ${delegation.status === 'accepted' && !delegation.engineer_assignment_id ? `
                        <a href="assign.php?delegation_id=${delegation.id}" class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors" title="Assign Engineer">
                            <i class="fas fa-user-plus text-xs"></i>
                        </a>
                    ` : ''}
                    ${delegation.status === 'accepted' && delegation.engineer_assignment_id ? `
                        <span class="p-1.5 text-green-500" title="Assigned to ${escapeHtml(delegation.engineer_name || 'Engineer')}">
                            <i class="fas fa-user-check text-xs"></i>
                        </span>
                    ` : ''}
                    ${delegation.status === 'accepted' && delegation.material_receive_status === 'accepted' ? `
                        <button onclick="openDispatchModal(${delegation.id}, ${delegation.site_id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Dispatch Material">
                            <i class="fas fa-truck text-xs"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
    
    updateBulkActionsVisibility();
    updateSelectAllCheckbox();
}

// Get engineer assignment badge
function getEngineerBadge(delegation) {
    if (delegation.status !== 'accepted') {
        return '<span class="text-gray-400 text-[10px]">-</span>';
    }
    
    if (delegation.engineer_assignment_id) {
        return `<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium" title="Assigned to ${escapeHtml(delegation.engineer_name || 'Engineer')}">
            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>${escapeHtml(delegation.engineer_name || 'Assigned')}
        </span>`;
    }
    
    return `<span class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium">
        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full mr-1"></span>Not Assigned
    </span>`;
}

// Get feasibility status badge
function getFeasibilityBadge(delegation) {
    if (delegation.status !== 'accepted') {
        return '<span class="text-gray-400 text-[10px]">-</span>';
    }
    
    if (!delegation.engineer_assignment_id) {
        return '<span class="text-gray-400 text-[10px]">-</span>';
    }
    
    const status = delegation.feasibility_status || 'pending_eta';
    const badges = {
        'pending_eta': '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Pending ETA</span>',
        'eta_submitted': '<span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-yellow-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-yellow-400 rounded-full mr-1"></span>ETA Submitted</span>',
        'ada_submitted': '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-400 rounded-full mr-1"></span>ADA Submitted</span>',
        'feasibility_completed': '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Completed</span>',
        'installation_pending': '<span class="inline-flex items-center px-2 py-0.5 bg-orange-50 text-orange-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-orange-400 rounded-full mr-1"></span>Install Pending</span>',
        'installation_in_progress': '<span class="inline-flex items-center px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-indigo-400 rounded-full mr-1"></span>Installing</span>',
        'installation_completed': '<span class="inline-flex items-center px-2 py-0.5 bg-green-50 text-green-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>Installed</span>'
    };
    return badges[status] || `<span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-[10px]">${status}</span>`;
}

// Get material status badge
function getMaterialBadge(delegation) {
    if (delegation.status !== 'accepted') {
        return '<span class="text-gray-400 text-[10px]">-</span>';
    }
    
    const dispatchCount = delegation.dispatch_count || 0;
    const receiveStatus = delegation.material_receive_status;
    
    if (dispatchCount === 0) {
        return '<span class="inline-flex items-center px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-gray-400 rounded-full mr-1"></span>No Material</span>';
    }
    
    const badges = {
        'pending': '<span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-yellow-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-yellow-400 rounded-full mr-1"></span>Pending</span>',
        'accepted': '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Received</span>',
        'rejected': '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Rejected</span>',
        'partial': '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-400 rounded-full mr-1"></span>Partial</span>'
    };
    
    return badges[receiveStatus] || '<span class="inline-flex items-center px-2 py-0.5 bg-orange-50 text-orange-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-orange-400 rounded-full mr-1"></span>In Transit</span>';
}

// Get status badge HTML
function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full mr-1"></span>Pending</span>',
        'accepted': '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Accepted</span>',
        'rejected': '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Rejected</span>'
    };
    return badges[status] || `<span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-[10px]">${status}</span>`;
}

// Format date (full)
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Format date (short)
function formatDateShort(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric'
    });
}

// Export delegations
async function exportDelegations() {
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
            downloadCSV(data.data.delegations, 'delegations_export.csv');
            showToast('Delegations exported successfully', 'success');
        } else {
            showError(data.error?.message || 'Failed to export delegations');
        }
    } catch (error) {
        console.error('Error exporting delegations:', error);
        showError('Failed to export delegations. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Site Name', 'LHO', 'City', 'State', 'Country', 'Status', 'Engineer', 'Delegated At', 'Delegated By'];
    const rows = data.map(d => [
        d.id,
        `"${(d.site_name || '').replace(/"/g, '""')}"`,
        `"${(d.lho || '').replace(/"/g, '""')}"`,
        `"${(d.city || '').replace(/"/g, '""')}"`,
        `"${(d.state || '').replace(/"/g, '""')}"`,
        `"${(d.country || '').replace(/"/g, '""')}"`,
        d.status,
        `"${(d.engineer_name || '').replace(/"/g, '""')}"`,
        d.delegated_at,
        `"${(d.delegated_by_name || '').replace(/"/g, '""')}"`
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
    loadDelegations();
}

// View delegation details
function viewDelegation(id) {
    const delegation = state.delegations.find(d => d.id === id);
    if (!delegation) return;
    
    const content = document.getElementById('view-content');
    content.innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Status</span>
                ${getStatusBadge(delegation.status)}
            </div>
            
            <div class="pb-3 border-b">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Site Information</h4>
                <p class="font-medium text-gray-800">${escapeHtml(delegation.site_name || 'N/A')}</p>
                <p class="text-sm text-gray-600">${escapeHtml(delegation.lho || '')} • ${escapeHtml(delegation.city || '')}, ${escapeHtml(delegation.state || '')}</p>
                ${delegation.address ? `<p class="text-sm text-gray-500 mt-1">${escapeHtml(delegation.address)}</p>` : ''}
            </div>
            
            <div class="pb-3 border-b">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Delegation Details</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span class="text-gray-500">Delegated At:</span>
                    <span class="text-gray-800">${formatDate(delegation.delegated_at)}</span>
                    <span class="text-gray-500">Delegated By:</span>
                    <span class="text-gray-800">${escapeHtml(delegation.delegated_by_name || 'N/A')}</span>
                </div>
            </div>
            
            ${delegation.responded_at ? `
            <div class="pb-3 border-b">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Response Details</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <span class="text-gray-500">Responded At:</span>
                    <span class="text-gray-800">${formatDate(delegation.responded_at)}</span>
                    <span class="text-gray-500">Responded By:</span>
                    <span class="text-gray-800">${escapeHtml(delegation.responded_by_name || 'N/A')}</span>
                </div>
            </div>
            ` : ''}
            
            ${delegation.rejection_notes ? `
            <div>
                <h4 class="text-sm font-medium text-gray-500 mb-2">Rejection Notes</h4>
                <p class="text-sm text-red-600 bg-red-50 p-3 rounded-lg">${escapeHtml(delegation.rejection_notes)}</p>
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
    document.body.style.overflow = 'auto';
}

// Open accept modal
function openAcceptModal(id) {
    const delegation = state.delegations.find(d => d.id === id);
    if (!delegation) return;
    
    document.getElementById('modal-title').textContent = 'Accept Delegation';
    document.getElementById('modal-site-info').innerHTML = `
        <div class="flex items-center">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-check text-green-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(delegation.site_name || 'N/A')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(delegation.lho || '')} • ${escapeHtml(delegation.city || '')}</p>
            </div>
        </div>
        <p class="mt-3 text-sm text-gray-600">Are you sure you want to accept this delegation?</p>
    `;
    
    document.getElementById('rejection-notes-container').classList.add('hidden');
    document.getElementById('modal-delegation-id').value = id;
    document.getElementById('modal-action').value = 'accept';
    document.getElementById('modal-submit-btn').textContent = 'Accept';
    document.getElementById('modal-submit-btn').className = 'px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition';
    
    document.getElementById('response-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Open reject modal
function openRejectModal(id) {
    const delegation = state.delegations.find(d => d.id === id);
    if (!delegation) return;
    
    document.getElementById('modal-title').textContent = 'Reject Delegation';
    document.getElementById('modal-site-info').innerHTML = `
        <div class="flex items-center">
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-times text-red-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(delegation.site_name || 'N/A')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(delegation.lho || '')} • ${escapeHtml(delegation.city || '')}</p>
            </div>
        </div>
    `;
    
    document.getElementById('rejection-notes-container').classList.remove('hidden');
    document.getElementById('rejection-notes').value = '';
    document.getElementById('modal-delegation-id').value = id;
    document.getElementById('modal-action').value = 'reject';
    document.getElementById('modal-submit-btn').textContent = 'Reject';
    document.getElementById('modal-submit-btn').className = 'px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition';
    
    document.getElementById('response-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close response modal
function closeResponseModal() {
    document.getElementById('response-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Submit response (accept or reject)
async function submitResponse() {
    const delegationId = document.getElementById('modal-delegation-id').value;
    const action = document.getElementById('modal-action').value;
    const notes = document.getElementById('rejection-notes').value;
    
    // Validate rejection notes
    if (action === 'reject' && !notes.trim()) {
        showToast('Please provide rejection notes', 'error');
        return;
    }
    
    const btn = document.getElementById('modal-submit-btn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                action: action,
                delegation_id: parseInt(delegationId),
                notes: notes
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || `Delegation ${action}ed successfully`, 'success');
            closeResponseModal();
            loadDelegations();
        } else {
            showError(data.error?.message || `Failed to ${action} delegation`);
        }
    } catch (error) {
        console.error('Error submitting response:', error);
        showError(`Failed to ${action} delegation. Please try again.`);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Show loading indicator
function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    const table = document.getElementById('delegations-table');
    
    if (show) {
        indicator.classList.remove('hidden');
        table.classList.add('opacity-50');
    } else {
        indicator.classList.add('hidden');
        table.classList.remove('opacity-50');
    }
}

// Show error message
function showError(message) {
    showToast(message, 'error');
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closeResponseModal();
        closeBulkConfirmModal();
    }
});

// Toggle individual selection
function toggleSelection(id) {
    if (state.selectedIds.has(id)) {
        state.selectedIds.delete(id);
    } else {
        state.selectedIds.add(id);
    }
    updateBulkActionsVisibility();
    updateSelectAllCheckbox();
}

// Toggle select all pending delegations
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const pendingDelegations = state.delegations.filter(d => d.status === 'pending');
    
    if (selectAllCheckbox.checked) {
        // Select all pending
        pendingDelegations.forEach(d => state.selectedIds.add(d.id));
    } else {
        // Deselect all
        pendingDelegations.forEach(d => state.selectedIds.delete(d.id));
    }
    
    // Update checkboxes in table
    document.querySelectorAll('.delegation-checkbox').forEach(cb => {
        const id = parseInt(cb.dataset.id);
        cb.checked = state.selectedIds.has(id);
    });
    
    updateBulkActionsVisibility();
}

// Update select all checkbox state
function updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const pendingDelegations = state.delegations.filter(d => d.status === 'pending');
    
    if (pendingDelegations.length === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.disabled = true;
    } else {
        selectAllCheckbox.disabled = false;
        const selectedPending = pendingDelegations.filter(d => state.selectedIds.has(d.id));
        
        if (selectedPending.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (selectedPending.length === pendingDelegations.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
}

// Update bulk actions visibility
function updateBulkActionsVisibility() {
    const bulkActions = document.getElementById('bulk-actions');
    const selectedCount = document.getElementById('selected-count');
    
    if (state.selectedIds.size > 0) {
        bulkActions.classList.remove('hidden');
        selectedCount.textContent = `${state.selectedIds.size} selected`;
    } else {
        bulkActions.classList.add('hidden');
    }
}

// Clear selection
function clearSelection() {
    state.selectedIds.clear();
    document.querySelectorAll('.delegation-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select-all-checkbox').checked = false;
    document.getElementById('select-all-checkbox').indeterminate = false;
    updateBulkActionsVisibility();
}

// Bulk accept - show confirmation modal
function bulkAccept() {
    if (state.selectedIds.size === 0) {
        showToast('No delegations selected', 'warning');
        return;
    }
    
    // Update modal with count and show
    document.getElementById('bulk-confirm-count').textContent = state.selectedIds.size;
    document.getElementById('bulk-confirm-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close bulk confirm modal
function closeBulkConfirmModal() {
    document.getElementById('bulk-confirm-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Confirm and process bulk accept
async function confirmBulkAccept() {
    closeBulkConfirmModal();
    
    const bulkBtn = document.querySelector('#bulk-actions button');
    const originalText = bulkBtn.innerHTML;
    bulkBtn.disabled = true;
    bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    let successCount = 0;
    let errorCount = 0;
    const errors = [];
    
    // Process each selected delegation
    for (const delegationId of state.selectedIds) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'accept',
                    delegation_id: delegationId,
                    notes: ''
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                successCount++;
            } else {
                errorCount++;
                errors.push(`ID ${delegationId}: ${data.error?.message || 'Failed'}`);
            }
        } catch (error) {
            errorCount++;
            errors.push(`ID ${delegationId}: Network error`);
        }
    }
    
    bulkBtn.disabled = false;
    bulkBtn.innerHTML = originalText;
    
    // Show result
    if (successCount > 0 && errorCount === 0) {
        showToast(`Successfully accepted ${successCount} delegation(s)`, 'success');
    } else if (successCount > 0 && errorCount > 0) {
        showToast(`Accepted ${successCount}, failed ${errorCount}`, 'warning');
    } else {
        showToast(`Failed to accept delegations`, 'error');
    }
    
    // Clear selection and reload
    clearSelection();
    loadDelegations();
}

// Open dispatch modal
function openDispatchModal(delegationId, siteId) {
    const delegation = state.delegations.find(d => d.id === delegationId);
    if (!delegation) return;
    
    document.getElementById('dispatch-site-info').innerHTML = `
        <div class="flex items-center">
            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-map-marker-alt text-indigo-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(delegation.site_name || 'N/A')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(delegation.lho || '')} • ${escapeHtml(delegation.city || '')}</p>
            </div>
        </div>
    `;
    
    document.getElementById('dispatch-delegation-id').value = delegationId;
    document.getElementById('dispatch-site-id').value = siteId;
    
    document.getElementById('dispatch-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close dispatch modal
function closeDispatchModal() {
    document.getElementById('dispatch-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Dispatch to engineer - redirect to dispatch page with site pre-selected
function dispatchToEngineer() {
    const siteId = document.getElementById('dispatch-site-id').value;
    closeDispatchModal();
    // Redirect to contractor dispatch page with site pre-selected
    window.location.href = `../inventory/contractor/dispatch.php?site_id=${siteId}&destination=engineer`;
}

// Return to ADV - redirect to dispatch page with ADV destination
function returnToAdv() {
    const siteId = document.getElementById('dispatch-site-id').value;
    closeDispatchModal();
    // Redirect to contractor dispatch page with ADV destination
    window.location.href = `../inventory/contractor/dispatch.php?site_id=${siteId}&destination=adv`;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
