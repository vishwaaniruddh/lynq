<?php
/**
 * Configuration Audit History Page
 * 
 * Filterable audit log table
 * Export functionality
 * 
 * Requirements: 9.2, 9.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV user access for IP configuration
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to access IP Configuration';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Audit History';
$currentPage = 'configuration_audit';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Configuration'],
    ['label' => 'Audit History']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Audit History</h2>
            <p class="text-gray-500">Complete history of all configuration activities</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="exportAuditLog()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-download mr-2"></i>Export CSV
            </button>
            <a href="dashboard.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center">
                <i class="fas fa-chart-line mr-2"></i>Dashboard
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Filters</h3>
            <button onclick="clearFilters()" class="text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-times mr-1"></i>Clear All
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                <select id="filter-action-type" onchange="loadAuditLog()" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="">All Actions</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" id="filter-date-from" onchange="loadAuditLog()"
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" id="filter-date-to" onchange="loadAuditLog()"
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <div class="relative">
                    <input type="text" id="filter-search" placeholder="Router serial, username..." 
                        class="w-full px-3 py-2 pr-10 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <button onclick="loadAuditLog()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Router Serial Number</label>
                <input type="text" id="filter-router" placeholder="Filter by router serial..." 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div class="flex items-end">
                <button onclick="loadAuditLog()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>
    
    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <div onclick="filterByAction('')" class="bg-white rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-total" class="text-2xl font-bold text-gray-800">-</p>
            <p class="text-xs text-gray-500">Total</p>
        </div>
        <div onclick="filterByAction('lock_acquired')" class="bg-yellow-50 rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-lock_acquired" class="text-2xl font-bold text-yellow-700">-</p>
            <p class="text-xs text-yellow-600">Locks</p>
        </div>
        <div onclick="filterByAction('configured')" class="bg-green-50 rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-configured" class="text-2xl font-bold text-green-700">-</p>
            <p class="text-xs text-green-600">Configured</p>
        </div>
        <div onclick="filterByAction('unbound')" class="bg-orange-50 rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-unbound" class="text-2xl font-bold text-orange-700">-</p>
            <p class="text-xs text-orange-600">Unbound</p>
        </div>
        <div onclick="filterByAction('lock_expired')" class="bg-red-50 rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-lock_expired" class="text-2xl font-bold text-red-700">-</p>
            <p class="text-xs text-red-600">Expired</p>
        </div>
        <div onclick="filterByAction('ip_created')" class="bg-blue-50 rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-ip_created" class="text-2xl font-bold text-blue-700">-</p>
            <p class="text-xs text-blue-600">IPs Created</p>
        </div>
        <div onclick="filterByAction('ip_updated')" class="bg-indigo-50 rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-ip_updated" class="text-2xl font-bold text-indigo-700">-</p>
            <p class="text-xs text-indigo-600">IPs Updated</p>
        </div>
        <div onclick="filterByAction('bulk_upload')" class="bg-purple-50 rounded-lg shadow-sm p-4 text-center hover:shadow-md transition cursor-pointer">
            <p id="stat-bulk_upload" class="text-2xl font-bold text-purple-700">-</p>
            <p class="text-xs text-purple-600">Bulk Uploads</p>
        </div>
    </div>
    
    <!-- Audit Log Table -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Activity Log</h3>
                <p id="results-info" class="text-sm text-gray-500">Loading...</p>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Per page:</label>
                <select id="per-page" onchange="loadAuditLog()" class="px-2 py-1 border rounded text-sm">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        
        <div id="audit-table-container">
            <div id="audit-loading" class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
                <p class="text-gray-500">Loading audit history...</p>
            </div>
            <div id="audit-empty" class="hidden text-center py-12">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-history text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500">No audit entries found</p>
                <p class="text-sm text-gray-400">Try adjusting your filters</p>
            </div>
            <div id="audit-table" class="hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Router Serial</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">IP Details</th>
                            <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody id="audit-tbody" class="divide-y divide-gray-100">
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <div id="pagination-container" class="hidden p-4 border-t flex items-center justify-between">
            <div id="pagination-info" class="text-sm text-gray-500"></div>
            <div id="pagination-controls" class="flex items-center gap-2"></div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detail-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeDetailModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl bg-white rounded-xl shadow-xl">
        <div class="p-6 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Audit Entry Details</h3>
            <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detail-content" class="p-6 max-h-96 overflow-y-auto">
        </div>
        <div class="p-4 border-t bg-gray-50 rounded-b-xl">
            <button onclick="closeDetailModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    auditLogs: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    actionTypes: [],
    stats: {}
};

// API base URL
const API_URL = '../api/configuration';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set default date range (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    document.getElementById('filter-date-to').value = formatDateForInput(today);
    document.getElementById('filter-date-from').value = formatDateForInput(thirtyDaysAgo);
    
    // Add enter key listener for search
    document.getElementById('filter-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadAuditLog();
    });
    document.getElementById('filter-router').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadAuditLog();
    });
    
    // Load initial data
    loadAuditLog();
});

// Load Audit Log
async function loadAuditLog() {
    showLoading();
    
    const params = new URLSearchParams();
    
    const actionType = document.getElementById('filter-action-type').value;
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;
    const search = document.getElementById('filter-search').value.trim();
    const router = document.getElementById('filter-router').value.trim();
    const perPage = document.getElementById('per-page').value;
    
    if (actionType) params.append('action_type', actionType);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (search) params.append('search', search);
    if (router) params.append('router_serial_number', router);
    params.append('limit', perPage);
    params.append('page', state.pagination.page);
    
    try {
        const response = await fetch(`${API_URL}/audit_log.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.auditLogs = data.data.audit_logs;
            state.pagination = data.data.pagination;
            state.stats = data.data.stats || {};
            
            // Populate action type filter if not already done
            if (state.actionTypes.length === 0 && data.data.filter_options?.action_types) {
                populateActionTypeFilter(data.data.filter_options.action_types);
                state.actionTypes = data.data.filter_options.action_types;
            }
            
            renderAuditLog();
            updateStats();
        } else {
            showError(data.error?.message || 'Failed to load audit log');
            showEmpty();
        }
    } catch (error) {
        console.error('Error loading audit log:', error);
        showError('Failed to load audit log');
        showEmpty();
    }
}

// Populate action type filter dropdown
function populateActionTypeFilter(actionTypes) {
    const select = document.getElementById('filter-action-type');
    select.innerHTML = '<option value="">All Actions</option>';
    
    actionTypes.forEach(type => {
        const option = document.createElement('option');
        option.value = type.value;
        option.textContent = type.label;
        select.appendChild(option);
    });
}

// Update summary stats from API response
function updateStats() {
    const stats = state.stats || {};
    
    // Update UI with actual counts from API
    document.getElementById('stat-total').textContent = stats.total || 0;
    document.getElementById('stat-lock_acquired').textContent = stats.lock_acquired || 0;
    document.getElementById('stat-configured').textContent = stats.configured || 0;
    document.getElementById('stat-unbound').textContent = stats.unbound || 0;
    document.getElementById('stat-lock_expired').textContent = stats.lock_expired || 0;
    document.getElementById('stat-ip_created').textContent = stats.ip_created || 0;
    document.getElementById('stat-ip_updated').textContent = stats.ip_updated || 0;
    document.getElementById('stat-bulk_upload').textContent = stats.bulk_upload || 0;
}

// Render Audit Log Table
function renderAuditLog() {
    if (!state.auditLogs || state.auditLogs.length === 0) {
        showEmpty();
        return;
    }
    
    const tbody = document.getElementById('audit-tbody');
    tbody.innerHTML = '';
    
    // Calculate starting serial number based on pagination
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    state.auditLogs.forEach((log, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50/50 transition-colors cursor-pointer';
        row.onclick = () => showDetailModal(log);
        
        const ipDetails = formatIPDetails(log.ip_details);
        const serialNo = startIndex + index + 1;
        
        row.innerHTML = `
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${serialNo}</td>
            <td class="px-4 py-2.5 text-xs whitespace-nowrap">${formatDateTime(log.created_at)}</td>
            <td class="px-4 py-2.5">${getActionBadge(log.action_type, log.action_label)}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(log.username || '-')}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(log.router_serial_number || '-')}</td>
            <td class="px-4 py-2.5 text-xs">${ipDetails}</td>
            <td class="px-4 py-2.5 text-center">
                <button onclick="event.stopPropagation(); showDetailModal(${JSON.stringify(log).replace(/"/g, '&quot;')})" 
                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">
                    <i class="fas fa-eye text-xs"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    showTable();
    renderPagination();
}

// Format IP details for display
function formatIPDetails(ipDetails) {
    if (!ipDetails || (!ipDetails.network_ip && !ipDetails.router_ip)) {
        return '<span class="text-gray-400">-</span>';
    }
    
    const parts = [];
    if (ipDetails.network_ip) parts.push(`<span class="font-mono text-xs">${escapeHtml(ipDetails.network_ip)}</span>`);
    if (ipDetails.router_ip) parts.push(`<span class="font-mono text-xs">${escapeHtml(ipDetails.router_ip)}</span>`);
    
    return parts.length > 0 ? parts.join(' / ') : '<span class="text-gray-400">-</span>';
}

// Get action badge HTML
function getActionBadge(actionType, actionLabel) {
    const badges = {
        'lock_acquired': 'bg-yellow-50 text-yellow-600',
        'lock_released': 'bg-blue-50 text-blue-600',
        'lock_expired': 'bg-red-50 text-red-600',
        'configured': 'bg-emerald-50 text-emerald-600',
        'unbound': 'bg-orange-50 text-orange-600',
        'ip_created': 'bg-blue-50 text-blue-600',
        'ip_updated': 'bg-indigo-50 text-indigo-600',
        'ip_deleted': 'bg-red-50 text-red-600',
        'bulk_upload': 'bg-purple-50 text-purple-600'
    };
    
    const colorClass = badges[actionType] || 'bg-gray-50 text-gray-600';
    return `<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium ${colorClass} rounded-full">${escapeHtml(actionLabel || actionType)}</span>`;
}

// Render Pagination
function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    
    if (total === 0) {
        document.getElementById('pagination-container').classList.add('hidden');
        document.getElementById('results-info').textContent = 'No entries found';
        return;
    }
    
    document.getElementById('pagination-container').classList.remove('hidden');
    
    const start = (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    
    document.getElementById('results-info').textContent = `Showing ${state.auditLogs.length} entries`;
    document.getElementById('pagination-info').textContent = `Showing ${start} to ${end} of ${total} entries`;
    
    const controls = document.getElementById('pagination-controls');
    controls.innerHTML = '';
    
    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.className = `px-3 py-1 rounded ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`;
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.disabled = page === 1;
    prevBtn.onclick = () => goToPage(page - 1);
    controls.appendChild(prevBtn);
    
    // Page numbers
    const maxPages = 5;
    let startPage = Math.max(1, page - Math.floor(maxPages / 2));
    let endPage = Math.min(total_pages, startPage + maxPages - 1);
    
    if (endPage - startPage < maxPages - 1) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }
    
    if (startPage > 1) {
        controls.appendChild(createPageButton(1));
        if (startPage > 2) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-2 text-gray-400';
            ellipsis.textContent = '...';
            controls.appendChild(ellipsis);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        controls.appendChild(createPageButton(i, i === page));
    }
    
    if (endPage < total_pages) {
        if (endPage < total_pages - 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-2 text-gray-400';
            ellipsis.textContent = '...';
            controls.appendChild(ellipsis);
        }
        controls.appendChild(createPageButton(total_pages));
    }
    
    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.className = `px-3 py-1 rounded ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`;
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.disabled = page === total_pages;
    nextBtn.onclick = () => goToPage(page + 1);
    controls.appendChild(nextBtn);
}

function createPageButton(pageNum, isActive = false) {
    const btn = document.createElement('button');
    btn.className = `px-3 py-1 rounded ${isActive ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`;
    btn.textContent = pageNum;
    btn.onclick = () => goToPage(pageNum);
    return btn;
}

function goToPage(pageNum) {
    state.pagination.page = pageNum;
    loadAuditLog();
}

// Show Detail Modal
function showDetailModal(log) {
    const content = document.getElementById('detail-content');
    
    const detailsHtml = log.details && Object.keys(log.details).length > 0 
        ? `<pre class="bg-gray-50 p-3 rounded text-sm overflow-x-auto">${escapeHtml(JSON.stringify(log.details, null, 2))}</pre>`
        : '<span class="text-gray-400">No additional details</span>';
    
    content.innerHTML = `
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Action</label>
                    <p class="mt-1">${getActionBadge(log.action_type, log.action_label)}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Timestamp</label>
                    <p class="mt-1 text-gray-800">${formatDateTime(log.created_at)}</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">User</label>
                    <p class="mt-1 text-gray-800">${escapeHtml(log.username || '-')}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Router Serial</label>
                    <p class="mt-1 font-mono text-gray-800">${escapeHtml(log.router_serial_number || '-')}</p>
                </div>
            </div>
            
            <div>
                <label class="text-sm font-medium text-gray-500">IP Details</label>
                <div class="mt-1 bg-gray-50 rounded p-3">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-gray-500">Network IP:</span> <span class="font-mono">${escapeHtml(log.ip_details?.network_ip || '-')}</span></div>
                        <div><span class="text-gray-500">Router IP:</span> <span class="font-mono">${escapeHtml(log.ip_details?.router_ip || '-')}</span></div>
                        <div><span class="text-gray-500">Site IP:</span> <span class="font-mono">${escapeHtml(log.ip_details?.site_ip || '-')}</span></div>
                        <div><span class="text-gray-500">Subnet Mask:</span> <span class="font-mono">${escapeHtml(log.ip_details?.subnet_mask || '-')}</span></div>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="text-sm font-medium text-gray-500">Additional Details</label>
                <div class="mt-1">${detailsHtml}</div>
            </div>
        </div>
    `;
    
    document.getElementById('detail-modal').classList.remove('hidden');
}

function closeDetailModal() {
    document.getElementById('detail-modal').classList.add('hidden');
}

// Export Audit Log
async function exportAuditLog() {
    const params = new URLSearchParams();
    
    const actionType = document.getElementById('filter-action-type').value;
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;
    const search = document.getElementById('filter-search').value.trim();
    const router = document.getElementById('filter-router').value.trim();
    
    if (actionType) params.append('action_type', actionType);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (search) params.append('search', search);
    if (router) params.append('router_serial_number', router);
    params.append('export', '1');
    
    try {
        const response = await fetch(`${API_URL}/audit_log.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.audit_logs);
            showToast('Export completed successfully', 'success');
        } else {
            showError(data.error?.message || 'Failed to export audit log');
        }
    } catch (error) {
        console.error('Error exporting audit log:', error);
        showError('Failed to export audit log');
    }
}

// Download CSV
function downloadCSV(auditLogs) {
    const headers = ['Timestamp', 'Action', 'User', 'Router Serial', 'Network IP', 'Router IP', 'Site IP', 'Subnet Mask', 'Details'];
    
    const rows = auditLogs.map(log => [
        log.created_at || '',
        log.action_label || log.action_type || '',
        log.username || '',
        log.router_serial_number || '',
        log.ip_details?.network_ip || '',
        log.ip_details?.router_ip || '',
        log.ip_details?.site_ip || '',
        log.ip_details?.subnet_mask || '',
        log.details ? JSON.stringify(log.details) : ''
    ]);
    
    const csvContent = [
        headers.join(','),
        ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const today = new Date().toISOString().split('T')[0];
    link.setAttribute('href', url);
    link.setAttribute('download', `audit_log_${today}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Filter by action type (from stats click)
function filterByAction(actionType) {
    document.getElementById('filter-action-type').value = actionType;
    state.pagination.page = 1;
    loadAuditLog();
    
    // Scroll to table
    document.getElementById('audit-table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Clear Filters
function clearFilters() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    document.getElementById('filter-action-type').value = '';
    document.getElementById('filter-date-from').value = formatDateForInput(thirtyDaysAgo);
    document.getElementById('filter-date-to').value = formatDateForInput(today);
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-router').value = '';
    
    state.pagination.page = 1;
    loadAuditLog();
}

// UI Helpers
function showLoading() {
    document.getElementById('audit-loading').classList.remove('hidden');
    document.getElementById('audit-empty').classList.add('hidden');
    document.getElementById('audit-table').classList.add('hidden');
}

function showEmpty() {
    document.getElementById('audit-loading').classList.add('hidden');
    document.getElementById('audit-empty').classList.remove('hidden');
    document.getElementById('audit-table').classList.add('hidden');
    document.getElementById('pagination-container').classList.add('hidden');
    document.getElementById('results-info').textContent = 'No entries found';
}

function showTable() {
    document.getElementById('audit-loading').classList.add('hidden');
    document.getElementById('audit-empty').classList.add('hidden');
    document.getElementById('audit-table').classList.remove('hidden');
}

// Utility functions
function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString();
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    showToast(message, 'error');
}

function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 5000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
