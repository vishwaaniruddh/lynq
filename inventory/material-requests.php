<?php
/**
 * Material Requests Management Page
 * 
 * Centralized view of all material requests with filtering and details
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Determine user role - ADV, Contractor, or Engineer can access
// Requirements: 4.1 (ADV), 6.1 (Contractor), 7.1 (Engineer)
$userRole = 'engineer'; // Default
if (isAdvUser()) {
    $userRole = 'adv';
} elseif (isContractorUser()) {
    // Check if contractor admin or engineer
    if (isContractorAdmin()) {
        $userRole = 'contractor';
    } else {
        $userRole = 'engineer';
    }
}

// All authenticated users can access material requests (filtered by role via API)

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Material Requests';
$currentPage = 'inventory_material_requests';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Material Requests']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header (Task 5.1) -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Material Requests</h3>
            <?php if ($userRole === 'adv'): ?>
            <p class="text-sm text-gray-500">Track and manage material procurement across all sites</p>
            <?php elseif ($userRole === 'contractor'): ?>
            <p class="text-sm text-gray-500">View material requests for your delegated sites</p>
            <?php else: ?>
            <p class="text-sm text-gray-500">View material requests for your assigned sites</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Cards (Task 5.2) - ADV only -->
    <?php if ($userRole === 'adv'): ?>
    <div class="p-4 border-b bg-gray-50">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
            <div class="bg-white p-4 rounded-lg border">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-clipboard-list text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total</p>
                        <p id="total-count" class="text-xl font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-clock text-yellow-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Requested</p>
                        <p id="requested-count" class="text-xl font-semibold text-yellow-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-check text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Approved</p>
                        <p id="approved-count" class="text-xl font-semibold text-blue-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-truck text-purple-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Dispatched</p>
                        <p id="dispatched-count" class="text-xl font-semibold text-purple-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-check-double text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Received</p>
                        <p id="received-count" class="text-xl font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
    <div class="p-4 border-b bg-gray-50">
    <?php endif; ?>
        
        <!-- Filter Bar (Task 5.3) -->
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by site name..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="requested">Requested</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="received">Received</option>
                </select>
            </div>
            <div>
                <input type="date" id="date-from" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="From Date">
            </div>
            <div>
                <input type="date" id="date-to" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="To Date">
            </div>
        </div>
    </div>

    <!-- Loading indicator (Task 5.4) -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading material requests...</p>
    </div>
    
    <!-- Empty state (Task 5.4) -->
    <div id="empty-state" class="hidden p-8 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-clipboard-list text-3xl text-gray-400"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-700 mb-2">No Material Requests Found</h3>
        <p class="text-gray-500 mb-4">There are no material requests matching your criteria.</p>
        <p class="text-sm text-gray-400">Generate material requests from the Sites page to get started.</p>
    </div>
    
    <!-- Table (Task 5.4) -->
    <div id="table-container" class="overflow-x-auto">
        <table id="requests-table" class="w-full">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Material Master</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Requested Date</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Products</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="requests-tbody" class="divide-y">
                <!-- Table rows will be populated by JavaScript -->
            </tbody>
        </table>
    </div>
    
    <!-- Pagination (Task 5.4) -->
    <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<!-- View Request Details Modal (Task 5.5) -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 sticky top-0 bg-white">
                <h3 class="text-lg font-semibold text-gray-800">Material Request Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl sticky bottom-0">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>


<script>
// ===========================================
// State Management
// ===========================================
const state = {
    requests: [],
    pagination: { page: 1, limit: 10, total: 0, totalPages: 0 },
    filters: { search: '', status: '', dateFrom: '', dateTo: '' },
    counts: { total: 0, requested: 0, approved: 0, dispatched: 0, received: 0 },
    loading: false,
    userRole: '<?php echo $userRole; ?>'
};

// API Base URL
const API_BASE = '../api/material-requests';

// ===========================================
// Utility Functions
// ===========================================
function showToast(message, type = 'success') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75"><i class="fas fa-times"></i></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

/**
 * Show confirmation toast with title, message, and confirm/cancel buttons
 * Requirements: 1.1, 1.2, 1.3, 1.4
 * @param {Object} options - Configuration options
 * @param {string} options.title - Toast title
 * @param {string} options.message - Toast message
 * @param {string} options.confirmText - Text for confirm button
 * @param {string} options.confirmClass - CSS class for confirm button (e.g., 'bg-green-600', 'bg-red-600')
 * @param {Function} options.onConfirm - Callback function when user confirms
 */
function showConfirmToast(options) {
    const { title, message, confirmText, confirmClass = 'bg-blue-600', onConfirm } = options;
    
    // Remove any existing confirm toast
    const existingToast = document.getElementById('confirm-toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create backdrop
    const backdrop = document.createElement('div');
    backdrop.id = 'confirm-toast-backdrop';
    backdrop.className = 'fixed inset-0 bg-black/40 backdrop-blur-sm z-[60]';
    backdrop.onclick = closeConfirmToast;
    
    // Create toast
    const toast = document.createElement('div');
    toast.id = 'confirm-toast';
    toast.className = 'fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-[70] bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4';
    toast.innerHTML = `
        <div class="text-center">
            <div class="w-12 h-12 ${confirmClass.includes('red') ? 'bg-red-100' : 'bg-blue-100'} rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas ${confirmClass.includes('red') ? 'fa-exclamation-triangle text-red-500' : 'fa-question-circle text-blue-500'} text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">${escapeHtml(title)}</h3>
            <p class="text-gray-600 mb-6">${escapeHtml(message)}</p>
            <div class="flex justify-center gap-3">
                <button onclick="closeConfirmToast()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                    Cancel
                </button>
                <button id="confirm-toast-btn" class="px-4 py-2 ${confirmClass} text-white rounded-lg hover:opacity-90 transition font-medium">
                    ${escapeHtml(confirmText)}
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(backdrop);
    document.body.appendChild(toast);
    
    // Attach confirm handler
    document.getElementById('confirm-toast-btn').onclick = function() {
        closeConfirmToast();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    };
}

/**
 * Close the confirmation toast
 * Requirements: 1.4
 */
function closeConfirmToast() {
    const toast = document.getElementById('confirm-toast');
    const backdrop = document.getElementById('confirm-toast-backdrop');
    if (toast) toast.remove();
    if (backdrop) backdrop.remove();
}

/**
 * Show confirmation before approving a material request
 * Requirements: 1.1, 1.3
 * @param {number} requestId - The material request ID
 */
function confirmApprove(requestId) {
    showConfirmToast({
        title: 'Confirm Approval',
        message: 'Are you sure you want to approve this material request?',
        confirmText: 'Approve',
        confirmClass: 'bg-green-600',
        onConfirm: () => updateStatus(requestId, 'approved')
    });
}

/**
 * Show confirmation before rejecting a material request
 * Requirements: 1.2, 1.3
 * @param {number} requestId - The material request ID
 */
function confirmReject(requestId) {
    showConfirmToast({
        title: 'Confirm Rejection',
        message: 'Are you sure you want to reject this material request?',
        confirmText: 'Reject',
        confirmClass: 'bg-red-600',
        onConfirm: () => updateStatus(requestId, 'rejected')
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStatusBadge(status) {
    const badges = {
        requested: '<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-yellow-100 text-yellow-700"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-1.5"></span>Requested</span>',
        approved: '<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-blue-100 text-blue-700"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1.5"></span>Approved</span>',
        rejected: '<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-red-100 text-red-700"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>Rejected</span>',
        dispatched: '<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-purple-100 text-purple-700"><span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-1.5"></span>Dispatched</span>',
        received: '<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-green-100 text-green-700"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Received</span>'
    };
    return badges[status] || '<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-gray-100 text-gray-600">Unknown</span>';
}

// ===========================================
// Loading and Empty State Functions
// ===========================================
function showLoading() {
    state.loading = true;
    document.getElementById('loading-indicator').classList.remove('hidden');
    document.getElementById('table-container').classList.add('hidden');
    document.getElementById('empty-state').classList.add('hidden');
}

function hideLoading() {
    state.loading = false;
    document.getElementById('loading-indicator').classList.add('hidden');
}

function showEmptyState() {
    document.getElementById('empty-state').classList.remove('hidden');
    document.getElementById('table-container').classList.add('hidden');
    document.getElementById('pagination-container').classList.add('hidden');
}

function showTable() {
    document.getElementById('table-container').classList.remove('hidden');
    document.getElementById('empty-state').classList.add('hidden');
    document.getElementById('pagination-container').classList.remove('hidden');
}

// ===========================================
// API Functions
// ===========================================
async function loadRequests() {
    showLoading();
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) {
            params.append('search', state.filters.search);
        }
        if (state.filters.status) {
            params.append('status', state.filters.status);
        }
        if (state.filters.dateFrom) {
            params.append('date_from', state.filters.dateFrom);
        }
        if (state.filters.dateTo) {
            params.append('date_to', state.filters.dateTo);
        }
        
        const response = await fetch(`${API_BASE}/list.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.requests = data.data.material_requests || [];
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                totalPages: data.data.pagination.total_pages
            };
            
            // Update stats if available (ADV only)
            if (data.data.stats) {
                state.counts = {
                    total: data.data.stats.total || 0,
                    requested: data.data.stats.requested || 0,
                    approved: data.data.stats.approved || 0,
                    dispatched: data.data.stats.dispatched || 0,
                    received: data.data.stats.received || 0
                };
                updateCountsDisplay();
            }
            
            renderTable();
            renderPagination();
        } else {
            showToast(data.message || 'Failed to load material requests', 'error');
            showEmptyState();
        }
    } catch (error) {
        console.error('Error loading requests:', error);
        showToast('Failed to load material requests', 'error');
        showEmptyState();
    } finally {
        hideLoading();
    }
}

async function viewRequest(id) {
    try {
        const response = await fetch(`${API_BASE}/detail.php?id=${id}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const request = data.data.material_request;
            renderViewModal(request);
        } else {
            showToast(data.message || 'Failed to load request details', 'error');
        }
    } catch (error) {
        console.error('Error loading request details:', error);
        showToast('Failed to load request details', 'error');
    }
}

async function updateStatus(id, newStatus) {
    try {
        const response = await fetch(`${API_BASE}/status.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id, status: newStatus })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || 'Status updated successfully');
            closeViewModal();
            loadRequests();
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showToast('Failed to update status', 'error');
    }
}

// ===========================================
// Display Functions
// ===========================================
function updateCountsDisplay() {
    // Stats are only shown for ADV users
    const totalEl = document.getElementById('total-count');
    if (totalEl) {
        totalEl.textContent = state.counts.total;
        document.getElementById('requested-count').textContent = state.counts.requested;
        document.getElementById('approved-count').textContent = state.counts.approved;
        document.getElementById('dispatched-count').textContent = state.counts.dispatched;
        document.getElementById('received-count').textContent = state.counts.received;
    }
}

function renderTable() {
    hideLoading();
    
    if (state.requests.length === 0) {
        showEmptyState();
        return;
    }
    
    showTable();
    
    const tbody = document.getElementById('requests-tbody');
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.requests.map((request, index) => {
        const itemCount = request.item_count || (request.items ? request.items.length : 0);
        
        return `
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center">
                        <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5">
                            <i class="fas fa-map-marker-alt text-blue-500 text-xs"></i>
                        </div>
                        <div>
                            <span class="font-medium text-xs text-gray-800">${escapeHtml(request.site_name || 'Unknown Site')}</span>
                            <p class="text-[10px] text-gray-500">${escapeHtml(request.site_lho || '')}</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-2.5">
                    <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-[10px] font-medium">
                        ${escapeHtml(request.material_master_name || 'Unknown Master')}
                    </span>
                </td>
                <td class="px-4 py-2.5">${getStatusBadge(request.status)}</td>
                <td class="px-4 py-2.5 text-xs text-gray-600">${formatDate(request.requested_at)}</td>
                <td class="px-4 py-2.5">
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px]">${itemCount} items</span>
                </td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-1">
                        <button onclick="viewRequest(${request.id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="View Details">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                        ${state.userRole === 'adv' && request.status === 'requested' ? `
                        <button onclick="confirmApprove(${request.id})" class="p-1.5 text-green-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Approve">
                            <i class="fas fa-check text-xs"></i>
                        </button>
                        <button onclick="confirmReject(${request.id})" class="p-1.5 text-red-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Reject">
                            <i class="fas fa-times text-xs"></i>
                        </button>` : ''}
                        ${state.userRole === 'adv' && request.status === 'approved' ? `
                        <button onclick="createDispatchForRequest(${request.id})" class="p-1.5 text-purple-500 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors" title="Create Dispatch">
                            <i class="fas fa-truck text-xs"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination() {
    const { page, limit, total, totalPages } = state.pagination;
    const start = total > 0 ? (page - 1) * limit + 1 : 0;
    const end = Math.min(page * limit, total);
    
    document.getElementById('pagination-info').textContent = 
        total > 0 ? `Showing ${start} to ${end} of ${total} entries` : 'No entries';
    
    const controls = document.getElementById('pagination-controls');
    
    if (totalPages <= 1) {
        controls.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 border rounded-lg ${page === 1 ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
            html += `<button onclick="goToPage(${i})" 
                class="px-3 py-1 border rounded-lg ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">
                ${i}
            </button>`;
        } else if (i === page - 2 || i === page + 2) {
            html += `<span class="px-2">...</span>`;
        }
    }
    
    // Next button
    html += `<button onclick="goToPage(${page + 1})" ${page === totalPages ? 'disabled' : ''} 
        class="px-3 py-1 border rounded-lg ${page === totalPages ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.totalPages) return;
    state.pagination.page = page;
    loadRequests();
}


// ===========================================
// View Request Details Modal
// ===========================================
function renderViewModal(request) {
    const items = request.items || [];
    
    // Build action buttons based on status and user role
    let actionButtons = '';
    if (state.userRole === 'adv') {
        if (request.status === 'requested') {
            actionButtons = `
                <button onclick="confirmApprove(${request.id})" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Approve
                </button>
                <button onclick="confirmReject(${request.id})" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition ml-2">
                    <i class="fas fa-times mr-2"></i>Reject
                </button>`;
        } else if (request.status === 'approved') {
            actionButtons = `<button onclick="createDispatchForRequest(${request.id})" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                <i class="fas fa-truck mr-2"></i>Create Dispatch
            </button>`;
        }
    } else if (state.userRole === 'engineer' && request.status === 'dispatched') {
        actionButtons = `<button onclick="updateStatus(${request.id}, 'received')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-check-double mr-2"></i>Confirm Receipt
        </button>`;
    }
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-6">
            <!-- Request Info -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Request ID</p>
                    <p class="font-medium">#${request.id}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p>${getStatusBadge(request.status)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Material Master</p>
                    <p class="font-medium">${escapeHtml(request.material_master_name || 'Unknown')}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Requested By</p>
                    <p class="font-medium">${escapeHtml(request.requested_by_name || 'Unknown')}</p>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="border-t pt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Timeline</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-yellow-500 w-5"></i>
                        <span class="text-gray-600 ml-2">Requested:</span>
                        <span class="ml-2 font-medium">${formatDate(request.requested_at)}</span>
                    </div>
                    <div class="flex items-center ${request.approved_at ? '' : 'opacity-50'}">
                        <i class="fas fa-check text-blue-500 w-5"></i>
                        <span class="text-gray-600 ml-2">Approved:</span>
                        <span class="ml-2 font-medium">${formatDate(request.approved_at)}</span>
                    </div>
                    <div class="flex items-center ${request.dispatched_at ? '' : 'opacity-50'}">
                        <i class="fas fa-truck text-purple-500 w-5"></i>
                        <span class="text-gray-600 ml-2">Dispatched:</span>
                        <span class="ml-2 font-medium">${formatDate(request.dispatched_at)}</span>
                    </div>
                    <div class="flex items-center ${request.received_at ? '' : 'opacity-50'}">
                        <i class="fas fa-check-double text-green-500 w-5"></i>
                        <span class="text-gray-600 ml-2">Received:</span>
                        <span class="ml-2 font-medium">${formatDate(request.received_at)}</span>
                    </div>
                </div>
            </div>
            
            <!-- Site Information -->
            <div class="border-t pt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Site Information</h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-map-marker-alt text-blue-500"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">${escapeHtml(request.site_name || 'Unknown Site')}</p>
                            <p class="text-sm text-gray-500">${escapeHtml(request.site_lho || '')}</p>
                            <p class="text-sm text-gray-500">${escapeHtml(request.site_address || '')}</p>
                            <p class="text-sm text-gray-500">${escapeHtml(request.site_city || '')}${request.site_state ? ', ' + escapeHtml(request.site_state) : ''}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="border-t pt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Products (${items.length} items)</h4>
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
                        <tbody class="divide-y">
                            ${items.length > 0 ? items.map(item => `
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
                                    <td class="px-4 py-3 text-gray-600 text-sm">${escapeHtml(item.category_name || '-')}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-primary">${item.quantity_requested || item.quantity || 0}</td>
                                </tr>
                            `).join('') : '<tr><td colspan="3" class="px-4 py-3 text-center text-gray-500">No items</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
            
            ${actionButtons ? `
            <!-- Action Buttons -->
            <div class="border-t pt-4 flex justify-end">
                ${actionButtons}
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('view-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Redirect to dispatch page with material request data
function createDispatchForRequest(requestId) {
    // Store the request ID in session storage and redirect to dispatch page
    sessionStorage.setItem('material_request_id', requestId);
    window.location.href = 'dispatch.php?material_request_id=' + requestId;
}

// ===========================================
// Event Listeners and Initialization
// ===========================================
let searchTimeout = null;

document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadRequests();
    
    // Search with debounce
    document.getElementById('search-input').addEventListener('input', function(e) {
        state.filters.search = e.target.value;
        state.pagination.page = 1;
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadRequests();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadRequests();
    });
    
    // Date filters
    document.getElementById('date-from').addEventListener('change', function(e) {
        state.filters.dateFrom = e.target.value;
        state.pagination.page = 1;
        loadRequests();
    });
    
    document.getElementById('date-to').addEventListener('change', function(e) {
        state.filters.dateTo = e.target.value;
        state.pagination.page = 1;
        loadRequests();
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
