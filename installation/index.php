<?php
/**
 * Installation List Page
 * 
 * Displays list of installations with status, site info, dates
 * Includes filters for status
 * Includes "Export" button
 * 
 * Requirements: 16.1, 16.2, 16.3
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Installation Management';
$currentPage = 'installation';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Installation']
];

// Check if user can view installations
$companyType = strtoupper($currentUser['company_type'] ?? '');
$canViewInstallations = isAdvUser() || $companyType === 'CONTRACTOR';

if (!$canViewInstallations) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to view installations.';
    header('Location: ../dashboard.php');
    exit;
}

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Installation Management</h3>
            <p class="text-sm text-gray-500">Track and manage site installations</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="exportInstallations()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
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
                        <p class="text-sm text-gray-500">Pending</p>
                        <p id="pending-count" class="text-xl font-semibold text-yellow-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-paper-plane text-indigo-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Submitted</p>
                        <p id="submitted-count" class="text-xl font-semibold text-indigo-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Approved</p>
                        <p id="approved-count" class="text-xl font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border">
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
                <input type="text" id="search-input" placeholder="Search by ATM ID, site name, city..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="pending_materials">Pending Materials</option>
                    <option value="materials_received">Materials Received</option>
                    <option value="in_progress">In Progress</option>
                    <option value="submitted">Submitted</option>
                    <option value="pending_contractor_review">Pending Contractor Review</option>
                    <option value="contractor_approved">Contractor Approved</option>
                    <option value="contractor_rejected">Contractor Rejected</option>
                    <option value="adv_approved">ADV Approved</option>
                    <option value="adv_rejected">ADV Rejected</option>
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
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading installations...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="installations-table" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase cursor-pointer hover:bg-gray-100" data-sort="id">
                        ID <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase cursor-pointer hover:bg-gray-100" data-sort="atm_id">
                        ATM ID / Site <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase cursor-pointer hover:bg-gray-100" data-sort="city">
                        Location <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase cursor-pointer hover:bg-gray-100" data-sort="status">
                        Status <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Material Receipt</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase cursor-pointer hover:bg-gray-100" data-sort="created_at">
                        Created <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody id="installations-tbody" class="divide-y">
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">Loading...</td>
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

<script>

// State management
const state = {
    installations: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', date_from: '', date_to: '' },
    sort: { field: 'created_at', direction: 'desc' },
    statusCounts: {}
};

// API base URL
const API_URL = '../api/installation/tracking.php';
const EXPORT_URL = '../api/installation/export.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadInstallations();
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
            loadInstallations();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadInstallations();
    });
    
    // Date filters
    document.getElementById('date-from').addEventListener('change', function(e) {
        state.filters.date_from = e.target.value;
        state.pagination.page = 1;
        loadInstallations();
    });
    
    document.getElementById('date-to').addEventListener('change', function(e) {
        state.filters.date_to = e.target.value;
        state.pagination.page = 1;
        loadInstallations();
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
            loadInstallations();
            updateSortIndicators();
        });
    });
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

// Load installations from API
async function loadInstallations() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.date_from) params.append('date_from', state.filters.date_from);
        if (state.filters.date_to) params.append('date_to', state.filters.date_to);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.installations = data.data.tracking || [];
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            state.statusCounts = data.data.status_counts || {};
            
            renderTable();
            renderPagination();
            updateStats();
        } else {
            showError(data.message || 'Failed to load installations');
        }
    } catch (error) {
        console.error('Error loading installations:', error);
        showError('Failed to load installations. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Update stats display
function updateStats() {
    const counts = state.statusCounts;
    
    // Calculate totals
    let total = 0;
    let pending = 0;
    let submitted = 0;
    let approved = 0;
    let rejected = 0;
    
    Object.entries(counts).forEach(([status, count]) => {
        total += count;
        if (['pending_materials', 'materials_received', 'in_progress'].includes(status)) {
            pending += count;
        } else if (['submitted', 'pending_contractor_review'].includes(status)) {
            submitted += count;
        } else if (['contractor_approved', 'adv_approved'].includes(status)) {
            approved += count;
        } else if (['contractor_rejected', 'adv_rejected'].includes(status)) {
            rejected += count;
        }
    });
    
    document.getElementById('total-count').textContent = total;
    document.getElementById('pending-count').textContent = pending;
    document.getElementById('submitted-count').textContent = submitted;
    document.getElementById('approved-count').textContent = approved;
    document.getElementById('rejected-count').textContent = rejected;
}

// Render table
function renderTable() {
    const tbody = document.getElementById('installations-tbody');
    
    if (state.installations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
                    <p>No installations found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = state.installations.map(inst => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 text-gray-600">${inst.id}</td>
            <td class="px-6 py-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-tools text-indigo-500"></i>
                    </div>
                    <div>
                        <span class="font-medium text-gray-800">${escapeHtml(inst.atm_id || '-')}</span>
                        ${inst.lho ? `<p class="text-xs text-gray-500">${escapeHtml(inst.lho)}</p>` : ''}
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-gray-600">
                <div>
                    <span>${escapeHtml(inst.city || '-')}</span>
                    ${inst.state ? `<p class="text-xs text-gray-400">${escapeHtml(inst.state)}</p>` : ''}
                </div>
            </td>
            <td class="px-6 py-4">
                ${getStatusBadge(inst.status)}
            </td>
            <td class="px-6 py-4">
                ${getMaterialReceiptBadge(inst)}
            </td>
            <td class="px-6 py-4 text-gray-600">
                <div>
                    <span>${formatDate(inst.created_at)}</span>
                    ${inst.submitted_at ? `<p class="text-xs text-gray-400">Submitted: ${formatDate(inst.submitted_at)}</p>` : ''}
                </div>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center space-x-2">
                    <a href="view.php?id=${inst.id}" class="p-2 text-gray-500 hover:text-primary" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    ${canEditInstallation(inst) ? `
                        <a href="form.php?id=${inst.id}" class="p-2 text-gray-500 hover:text-blue-600" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                    ` : ''}
                    ${canReviewInstallation(inst) ? `
                        <a href="review.php?id=${inst.id}" class="p-2 text-gray-500 hover:text-green-600" title="Review">
                            <i class="fas fa-clipboard-check"></i>
                        </a>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

// Get status badge HTML
function getStatusBadge(status) {
    const statusConfig = {
        pending_materials: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Pending Materials' },
        materials_received: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Materials Received' },
        in_progress: { bg: 'bg-indigo-100', text: 'text-indigo-700', label: 'In Progress' },
        submitted: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Submitted' },
        pending_contractor_review: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'Pending Review' },
        contractor_approved: { bg: 'bg-teal-100', text: 'text-teal-700', label: 'Contractor Approved' },
        contractor_rejected: { bg: 'bg-red-100', text: 'text-red-700', label: 'Contractor Rejected' },
        adv_approved: { bg: 'bg-green-100', text: 'text-green-700', label: 'ADV Approved' },
        adv_rejected: { bg: 'bg-red-100', text: 'text-red-700', label: 'ADV Rejected' }
    };
    
    const config = statusConfig[status] || { bg: 'bg-gray-100', text: 'text-gray-700', label: status };
    return `<span class="px-2 py-1 ${config.bg} ${config.text} rounded-full text-xs">${config.label}</span>`;
}

// Get material receipt badge
function getMaterialReceiptBadge(inst) {
    if (inst.status === 'pending_materials') {
        return '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs"><i class="fas fa-clock mr-1"></i>Pending</span>';
    }
    return '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs"><i class="fas fa-check mr-1"></i>Received</span>';
}

// Check if user can edit installation
function canEditInstallation(inst) {
    const editableStatuses = ['materials_received', 'in_progress', 'contractor_rejected', 'adv_rejected'];
    return editableStatuses.includes(inst.status);
}

// Check if user can review installation
function canReviewInstallation(inst) {
    const reviewableStatuses = ['submitted', 'pending_contractor_review', 'contractor_approved'];
    return reviewableStatuses.includes(inst.status);
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
            class="px-3 py-1 rounded border ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">${i}</button>`;
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

// Go to specific page
function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadInstallations();
}

// Export installations
async function exportInstallations() {
    try {
        const params = new URLSearchParams();
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.date_from) params.append('date_from', state.filters.date_from);
        if (state.filters.date_to) params.append('date_to', state.filters.date_to);
        
        window.location.href = `${EXPORT_URL}?${params}`;
    } catch (error) {
        console.error('Error exporting:', error);
        showError('Failed to export installations');
    }
}

// Show/hide loading indicator
function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    const table = document.getElementById('installations-table');
    
    if (show) {
        indicator.classList.remove('hidden');
        table.classList.add('hidden');
    } else {
        indicator.classList.add('hidden');
        table.classList.remove('hidden');
    }
}

// Show error message
function showError(message) {
    const tbody = document.getElementById('installations-tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="px-6 py-8 text-center text-red-500">
                <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                <p>${escapeHtml(message)}</p>
            </td>
        </tr>
    `;
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
