<?php
/**
 * Delegation Tracking Dashboard
 * 
 * Displays all delegations with status, contractor, dates
 * Includes filters for status, contractor, date range
 * 
 * Requirements: 3.1, 3.2
 * - 3.1: Display all delegations with current status, contractor name, delegation date, response date
 * - 3.2: Filter delegations by status, contractor, date range
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access - only ADV users can access delegation tracking
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Delegation Tracking';
$currentPage = 'delegations';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => '../sites/index.php'],
    ['label' => 'Delegation Tracking']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Delegation Tracking</h3>
            <p class="text-sm text-gray-500">Monitor site delegations to contractors</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="../sites/delegate.php" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center">
                <i class="fas fa-share-alt mr-2"></i>New Delegation
            </a>
            <button onclick="exportDelegations()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="p-4 border-b bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-list text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total</p>
                        <p id="total-count" class="text-xl font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-clock text-yellow-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pending</p>
                        <p id="pending-count" class="text-xl font-semibold text-yellow-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('accepted')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Accepted</p>
                        <p id="accepted-count" class="text-xl font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('rejected')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-times-circle text-red-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Rejected</p>
                        <p id="rejected-count" class="text-xl font-semibold text-red-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by site name, LHO, contractor..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div>
                <select id="contractor-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Contractors</option>
                </select>
            </div>
            <div>
                <input type="date" id="date-from" placeholder="From Date" 
                    class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <input type="date" id="date-to" placeholder="To Date" 
                    class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <button onclick="clearFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
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
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Contractor</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Delegated</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Response</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="delegations-tbody" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td>
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


<script>
// State management
const state = {
    delegations: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', contractor_id: '', date_from: '', date_to: '' },
    contractors: [],
    counts: { total: 0, pending: 0, accepted: 0, rejected: 0 }
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
        loadDelegations();
    });
    
    // Contractor filter
    document.getElementById('contractor-filter').addEventListener('change', function(e) {
        state.filters.contractor_id = e.target.value;
        state.pagination.page = 1;
        loadDelegations();
    });
    
    // Date filters
    document.getElementById('date-from').addEventListener('change', function(e) {
        state.filters.date_from = e.target.value;
        state.pagination.page = 1;
        loadDelegations();
    });
    
    document.getElementById('date-to').addEventListener('change', function(e) {
        state.filters.date_to = e.target.value;
        state.pagination.page = 1;
        loadDelegations();
    });
}

// Filter by status (from stats cards)
function filterByStatus(status) {
    state.filters.status = status;
    document.getElementById('status-filter').value = status;
    state.pagination.page = 1;
    loadDelegations();
}

// Clear all filters
function clearFilters() {
    state.filters = { search: '', status: '', contractor_id: '', date_from: '', date_to: '' };
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('contractor-filter').value = '';
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    state.pagination.page = 1;
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
        if (state.filters.contractor_id) params.append('contractor_id', state.filters.contractor_id);
        if (state.filters.date_from) params.append('date_from', state.filters.date_from);
        if (state.filters.date_to) params.append('date_to', state.filters.date_to);
        
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
            state.contractors = data.data.contractors || [];
            state.counts = data.data.counts || { total: 0, pending: 0, accepted: 0, rejected: 0 };
            
            renderTable();
            renderPagination();
            updateStats();
            updateContractorFilter();
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

// Update stats display
function updateStats() {
    document.getElementById('total-count').textContent = state.counts.total || 0;
    document.getElementById('pending-count').textContent = state.counts.pending || 0;
    document.getElementById('accepted-count').textContent = state.counts.accepted || 0;
    document.getElementById('rejected-count').textContent = state.counts.rejected || 0;
}

// Update contractor filter dropdown
function updateContractorFilter() {
    const select = document.getElementById('contractor-filter');
    const currentValue = select.value;
    
    // Keep first option
    select.innerHTML = '<option value="">All Contractors</option>';
    
    state.contractors.forEach(contractor => {
        const option = document.createElement('option');
        option.value = contractor.id;
        option.textContent = contractor.name;
        if (contractor.id == currentValue) option.selected = true;
        select.appendChild(option);
    });
}

// Render table
function renderTable() {
    const tbody = document.getElementById('delegations-tbody');
    
    if (state.delegations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-share-alt text-4xl mb-3 text-gray-300"></i>
                    <p>No delegations found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = state.delegations.map(delegation => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${delegation.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-map-marker-alt text-blue-500 text-xs"></i>
                    </div>
                    <div>
                        <span class="font-medium text-gray-800 text-xs">${escapeHtml(delegation.site_name || 'N/A')}</span>
                        <p class="text-[10px] text-gray-500">${escapeHtml(delegation.lho || '')} • ${escapeHtml(delegation.city || '')}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-building text-purple-500 text-xs"></i>
                    </div>
                    <span class="text-gray-800 text-xs">${escapeHtml(delegation.contractor_name || 'N/A')}</span>
                </div>
            </td>
            <td class="px-4 py-2.5">
                ${getStatusBadge(delegation.status)}
            </td>
            <td class="px-4 py-2.5 text-gray-600">
                <div>
                    <span class="text-xs">${formatDate(delegation.delegated_at)}</span>
                    <p class="text-[10px] text-gray-400">by ${escapeHtml(delegation.delegated_by_name || 'N/A')}</p>
                </div>
            </td>
            <td class="px-4 py-2.5 text-gray-600">
                ${delegation.responded_at ? `
                    <div>
                        <span class="text-xs">${formatDate(delegation.responded_at)}</span>
                        <p class="text-[10px] text-gray-400">by ${escapeHtml(delegation.responded_by_name || 'N/A')}</p>
                    </div>
                ` : '<span class="text-gray-400 text-xs">-</span>'}
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <button onclick="viewDelegation(${delegation.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View Details">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    <a href="history.php?delegation_id=${delegation.id}" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="View History">
                        <i class="fas fa-history text-xs"></i>
                    </a>
                </div>
            </td>
        </tr>
    `).join('');
}

// Get status badge HTML
function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-yellow-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-1"></span>Pending</span>',
        'accepted': '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Accepted</span>',
        'rejected': '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>Rejected</span>'
    };
    return badges[status] || `<span class="inline-flex items-center px-2 py-0.5 bg-gray-50 text-gray-600 rounded-full text-[10px] font-medium">${status}</span>`;
}

// Format date
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
                <h4 class="text-sm font-medium text-gray-500 mb-2">Contractor</h4>
                <p class="font-medium text-gray-800">${escapeHtml(delegation.contractor_name || 'N/A')}</p>
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

// Export delegations to Excel
// Requirements: 3.4 - Generate Excel file containing all filtered delegation records
async function exportDelegations() {
    try {
        // Show loading state
        showToast('Generating Excel export...', 'info');
        
        // Build export URL with current filters
        const params = new URLSearchParams();
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.contractor_id) params.append('contractor_id', state.filters.contractor_id);
        if (state.filters.date_from) params.append('date_from', state.filters.date_from);
        if (state.filters.date_to) params.append('date_to', state.filters.date_to);
        
        // Use the dedicated export endpoint that generates Excel files
        const exportUrl = `../api/delegations/export.php?${params}`;
        
        // Fetch the Excel file
        const response = await fetch(exportUrl, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            // Try to parse error response
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || 'Failed to export delegations');
            }
            throw new Error('Failed to export delegations');
        }
        
        // Get filename from Content-Disposition header or generate default
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = `delegations_export_${new Date().toISOString().split('T')[0]}.xlsx`;
        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename="?([^";\n]+)"?/);
            if (filenameMatch && filenameMatch[1]) {
                filename = filenameMatch[1];
            }
        }
        
        // Convert response to blob and download
        const blob = await response.blob();
        
        if (blob.size === 0) {
            showToast('No data to export', 'warning');
            return;
        }
        
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Excel export completed successfully', 'success');
    } catch (error) {
        console.error('Error exporting delegations:', error);
        showError(error.message || 'Failed to export delegations. Please try again.');
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

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
