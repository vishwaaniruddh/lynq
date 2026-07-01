<?php
/**
 * IP_Master Management Page
 * 
 * List view with status filtering
 * Add/Edit/Delete forms
 * Bulk upload interface
 * 
 * Requirements: 1.1, 1.2, 1.3, 10.1
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
$pageTitle = 'IP Master Management';
$currentPage = 'configuration_ip_master';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Configuration'],
    ['label' => 'IP Master']
];

// ADV users have full permissions
$permissions = [
    'create' => true,
    'edit' => true,
    'delete' => true
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">IP Master Management</h3>
            <p class="text-sm text-gray-500">Manage IP address combinations for router configuration</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add IP
            </button>
            <button onclick="openBulkUploadModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center">
                <i class="fas fa-upload mr-2"></i>Bulk Upload
            </button>
            <button onclick="exportIPMasters()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search IP addresses..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="locked">Locked</option>
                    <option value="configured">Configured</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading IP records...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="ip-master-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" data-sort="id">
                        ID <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Network IP</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Router IP</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site IP</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Subnet Mask</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" data-sort="status">
                        Status <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="ip-master-tbody" class="divide-y divide-gray-100">
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

<!-- Create/Edit Modal -->
<div id="ip-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeIPModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add IP Master</h3>
                <button onclick="closeIPModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="ip-form" onsubmit="saveIPMaster(event)">
                <input type="hidden" id="ip-id" value="">
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="network-ip" class="block text-sm font-medium text-gray-700 mb-1">Network IP <span class="text-red-500">*</span></label>
                            <input type="text" id="network-ip" name="network_ip" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="e.g., 192.168.1.0">
                            <p id="network_ip-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="router-ip" class="block text-sm font-medium text-gray-700 mb-1">Router IP <span class="text-red-500">*</span></label>
                            <input type="text" id="router-ip" name="router_ip" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="e.g., 192.168.1.1">
                            <p id="router_ip-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="site-ip" class="block text-sm font-medium text-gray-700 mb-1">Site IP <span class="text-red-500">*</span></label>
                            <input type="text" id="site-ip" name="site_ip" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="e.g., 192.168.1.2">
                            <p id="site_ip-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="subnet-mask" class="block text-sm font-medium text-gray-700 mb-1">Subnet Mask <span class="text-red-500">*</span></label>
                            <input type="text" id="subnet-mask" name="subnet_mask" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="e.g., 255.255.255.0">
                            <p id="subnet_mask-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeIPModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">IP Master Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="flex justify-end p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div id="bulk-upload-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeBulkUploadModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Bulk Upload IP Masters</h3>
                <button onclick="closeBulkUploadModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-medium text-blue-800 mb-2"><i class="fas fa-info-circle mr-2"></i>File Format</h4>
                    <p class="text-sm text-blue-700 mb-2">Upload an Excel (.xlsx) or CSV file with the following columns:</p>
                    <ul class="text-sm text-blue-700 list-disc list-inside">
                        <li>network_ip - Network IP address</li>
                        <li>router_ip - Router IP address</li>
                        <li>site_ip - Site IP address</li>
                        <li>subnet_mask - Subnet mask</li>
                    </ul>
                    <a href="../excelformats/bulkIPupload.xlsx" download class="inline-flex items-center mt-3 text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-download mr-1"></i>Download Template
                    </a>
                </div>
                
                <form id="bulk-upload-form" onsubmit="uploadBulkIPMasters(event)">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center" id="drop-zone">
                        <input type="file" id="bulk-file" name="file" accept=".xlsx,.csv" class="hidden" onchange="handleFileSelect(this)">
                        <div id="file-placeholder">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 mb-2">Drag and drop your file here, or</p>
                            <button type="button" onclick="document.getElementById('bulk-file').click()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                                Browse Files
                            </button>
                        </div>
                        <div id="file-selected" class="hidden">
                            <i class="fas fa-file-excel text-4xl text-green-500 mb-3"></i>
                            <p id="selected-file-name" class="text-gray-800 font-medium mb-2"></p>
                            <button type="button" onclick="clearFileSelection()" class="text-sm text-red-500 hover:text-red-700">
                                <i class="fas fa-times mr-1"></i>Remove
                            </button>
                        </div>
                    </div>
                    
                    <div id="upload-progress" class="hidden mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-600">Uploading...</span>
                            <span id="progress-percent" class="text-sm font-medium text-primary">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="progress-bar" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div id="upload-result" class="hidden mt-4"></div>
                </form>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeBulkUploadModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" id="upload-btn" onclick="uploadBulkIPMasters(event)" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition" disabled>
                    <i class="fas fa-upload mr-2"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<script>

// State management
const state = {
    ipMasters: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: <?php echo json_encode($permissions); ?>
};

// API base URL
const API_URL = '../api/configuration';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const statusParam = urlParams.get('status');
    if (statusParam) {
        state.filters.status = statusParam;
        document.getElementById('status-filter').value = statusParam;
    }
    
    loadIPMasters();
    setupEventListeners();
    setupDragAndDrop();
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
            loadIPMasters();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadIPMasters();
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
            loadIPMasters();
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

// Load IP Masters from API
async function loadIPMasters() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}/ip_master.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.ipMasters = data.data.ip_masters;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load IP records');
        }
    } catch (error) {
        console.error('Error loading IP masters:', error);
        showError('Failed to load IP records. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render table
function renderTable() {
    const tbody = document.getElementById('ip-master-tbody');
    
    if (state.ipMasters.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-network-wired text-4xl mb-3 text-gray-300"></i>
                    <p>No IP records found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.ipMasters.map((ip, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${ip.id}</td>
            <td class="px-4 py-2.5">
                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">${escapeHtml(ip.network_ip)}</span>
            </td>
            <td class="px-4 py-2.5">
                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">${escapeHtml(ip.router_ip)}</span>
            </td>
            <td class="px-4 py-2.5">
                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">${escapeHtml(ip.site_ip)}</span>
            </td>
            <td class="px-4 py-2.5">
                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">${escapeHtml(ip.subnet_mask)}</span>
            </td>
            <td class="px-4 py-2.5">
                ${getStatusBadge(ip.status)}
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <button onclick="viewIPMaster(${ip.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${ip.status === 'available' && state.permissions.edit ? `
                    <button onclick="editIPMaster(${ip.id})" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                    ${ip.status === 'available' && state.permissions.delete ? `
                    <button onclick="confirmDelete(${ip.id})" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                    ` : ''}
                    ${ip.status === 'locked' ? `
                    <button onclick="unlockIP(${ip.id})" class="p-1.5 text-yellow-500 hover:text-yellow-700 hover:bg-yellow-50 rounded transition-colors" title="Force Unlock">
                        <i class="fas fa-unlock-alt text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

// Get status badge HTML
function getStatusBadge(status) {
    const badges = {
        'available': '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Available</span>',
        'locked': '<span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-yellow-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-1"></span>Locked</span>',
        'configured': '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1"></span>Configured</span>'
    };
    return badges[status] || `<span class="inline-flex items-center px-2 py-0.5 bg-gray-50 text-gray-600 rounded-full text-[10px] font-medium">${status}</span>`;
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
    loadIPMasters();
}

// Open create modal
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add IP Master';
    document.getElementById('ip-id').value = '';
    document.getElementById('network-ip').value = '';
    document.getElementById('router-ip').value = '';
    document.getElementById('site-ip').value = '';
    document.getElementById('subnet-mask').value = '';
    clearErrors();
    document.getElementById('ip-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Edit IP Master
function editIPMaster(id) {
    const ip = state.ipMasters.find(i => i.id === id);
    if (!ip) return;
    
    if (ip.status !== 'available') {
        showError('Cannot edit IP that is locked or configured');
        return;
    }
    
    document.getElementById('modal-title').textContent = 'Edit IP Master';
    document.getElementById('ip-id').value = ip.id;
    document.getElementById('network-ip').value = ip.network_ip;
    document.getElementById('router-ip').value = ip.router_ip;
    document.getElementById('site-ip').value = ip.site_ip;
    document.getElementById('subnet-mask').value = ip.subnet_mask;
    clearErrors();
    document.getElementById('ip-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close IP modal
function closeIPModal() {
    document.getElementById('ip-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Save IP Master
async function saveIPMaster(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('ip-id').value;
    const networkIp = document.getElementById('network-ip').value.trim();
    const routerIp = document.getElementById('router-ip').value.trim();
    const siteIp = document.getElementById('site-ip').value.trim();
    const subnetMask = document.getElementById('subnet-mask').value.trim();
    
    // Validate
    if (!networkIp) { showFieldError('network_ip', 'Network IP is required'); return; }
    if (!routerIp) { showFieldError('router_ip', 'Router IP is required'); return; }
    if (!siteIp) { showFieldError('site_ip', 'Site IP is required'); return; }
    if (!subnetMask) { showFieldError('subnet_mask', 'Subnet Mask is required'); return; }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const endpoint = id ? `${API_URL}/ip_master_edit.php` : `${API_URL}/ip_master.php`;
        const payload = {
            network_ip: networkIp,
            router_ip: routerIp,
            site_ip: siteIp,
            subnet_mask: subnetMask
        };
        
        if (id) {
            payload.id = parseInt(id);
            payload.action = 'update';
        }
        
        const response = await fetch(endpoint, {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeIPModal();
            showSuccess(data.message || 'IP Master saved successfully');
            loadIPMasters();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    const errorMsg = Array.isArray(data.errors[field]) ? data.errors[field][0] : data.errors[field];
                    showFieldError(field, errorMsg);
                });
            } else {
                showError(data.error?.message || data.message || 'Failed to save IP Master');
            }
        }
    } catch (error) {
        console.error('Error saving IP Master:', error);
        showError('Failed to save IP Master. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

// View IP Master
function viewIPMaster(id) {
    const ip = state.ipMasters.find(i => i.id === id);
    if (!ip) return;
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-network-wired text-3xl text-indigo-500"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">ID</p>
                    <p class="font-medium">${ip.id}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p>${getStatusBadge(ip.status)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Network IP</p>
                    <p class="font-mono font-medium">${escapeHtml(ip.network_ip)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Router IP</p>
                    <p class="font-mono font-medium">${escapeHtml(ip.router_ip)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Site IP</p>
                    <p class="font-mono font-medium">${escapeHtml(ip.site_ip)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Subnet Mask</p>
                    <p class="font-mono font-medium">${escapeHtml(ip.subnet_mask)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created</p>
                    <p class="font-medium">${formatDate(ip.created_at)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Updated</p>
                    <p class="font-medium">${formatDate(ip.updated_at)}</p>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('view-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close view modal
function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Confirm delete
function confirmDelete(id) {
    const ip = state.ipMasters.find(i => i.id === id);
    if (!ip) return;
    
    openConfirmModal(
        'Delete IP Master',
        `Are you sure you want to delete this IP combination?<br><br>
        <span class="font-mono text-sm">${escapeHtml(ip.network_ip)} / ${escapeHtml(ip.router_ip)} / ${escapeHtml(ip.site_ip)}</span>`,
        function() {
            deleteIPMaster(id);
        }
    );
}

// Delete IP Master
async function deleteIPMaster(id) {
    try {
        const response = await fetch(`${API_URL}/ip_master_edit.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'IP Master deleted successfully');
            loadIPMasters();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete IP Master');
        }
    } catch (error) {
        console.error('Error deleting IP Master:', error);
        showError('Failed to delete IP Master. Please try again.');
    }
}

// Export IP Masters
async function exportIPMasters() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}/ip_master.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.ip_masters, 'ip_masters_export.csv');
            showSuccess('IP Masters exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export IP Masters');
        }
    } catch (error) {
        console.error('Error exporting IP Masters:', error);
        showError('Failed to export IP Masters. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Network IP', 'Router IP', 'Site IP', 'Subnet Mask', 'Status', 'Created At'];
    const rows = data.map(ip => [
        ip.id,
        ip.network_ip,
        ip.router_ip,
        ip.site_ip,
        ip.subnet_mask,
        ip.status,
        ip.created_at
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

// Bulk Upload Functions
function openBulkUploadModal() {
    clearFileSelection();
    document.getElementById('upload-result').classList.add('hidden');
    document.getElementById('upload-result').innerHTML = '';
    document.getElementById('bulk-upload-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeBulkUploadModal() {
    document.getElementById('bulk-upload-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function setupDragAndDrop() {
    const dropZone = document.getElementById('drop-zone');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('border-primary', 'bg-blue-50');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('border-primary', 'bg-blue-50');
        }, false);
    });
    
    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('bulk-file').files = files;
            handleFileSelect(document.getElementById('bulk-file'));
        }
    }, false);
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        document.getElementById('file-placeholder').classList.add('hidden');
        document.getElementById('file-selected').classList.remove('hidden');
        document.getElementById('selected-file-name').textContent = file.name;
        document.getElementById('upload-btn').disabled = false;
    }
}

function clearFileSelection() {
    document.getElementById('bulk-file').value = '';
    document.getElementById('file-placeholder').classList.remove('hidden');
    document.getElementById('file-selected').classList.add('hidden');
    document.getElementById('upload-btn').disabled = true;
    document.getElementById('upload-progress').classList.add('hidden');
}

async function uploadBulkIPMasters(event) {
    event.preventDefault();
    
    const fileInput = document.getElementById('bulk-file');
    const file = fileInput.files[0];
    
    if (!file) {
        showError('Please select a file to upload');
        return;
    }
    
    const uploadBtn = document.getElementById('upload-btn');
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
    
    document.getElementById('upload-progress').classList.remove('hidden');
    document.getElementById('upload-result').classList.add('hidden');
    
    try {
        const formData = new FormData();
        formData.append('file', file);
        
        const response = await fetch(`${API_URL}/ip_master_bulk.php`, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        
        const data = await response.json();
        
        document.getElementById('progress-bar').style.width = '100%';
        document.getElementById('progress-percent').textContent = '100%';
        
        const resultDiv = document.getElementById('upload-result');
        resultDiv.classList.remove('hidden');
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="p-4 bg-green-50 rounded-lg">
                    <h4 class="font-medium text-green-800 mb-2"><i class="fas fa-check-circle mr-2"></i>Upload Successful</h4>
                    <p class="text-sm text-green-700">${data.message}</p>
                    <div class="mt-2 text-sm text-green-600">
                        <span class="font-medium">${data.data?.created || 0}</span> records created
                    </div>
                </div>
            `;
            loadIPMasters();
        } else {
            let errorHtml = `
                <div class="p-4 bg-red-50 rounded-lg">
                    <h4 class="font-medium text-red-800 mb-2"><i class="fas fa-exclamation-circle mr-2"></i>Upload Failed</h4>
                    <p class="text-sm text-red-700">${data.error?.message || data.message || 'Unknown error'}</p>
            `;
            
            if (data.data?.errors && data.data.errors.length > 0) {
                errorHtml += `
                    <div class="mt-3 max-h-40 overflow-y-auto">
                        <p class="text-sm font-medium text-red-800 mb-1">Errors:</p>
                        <ul class="text-sm text-red-700 list-disc list-inside">
                            ${data.data.errors.slice(0, 10).map(err => `<li>Row ${err.row}: ${err.error}</li>`).join('')}
                            ${data.data.errors.length > 10 ? `<li>... and ${data.data.errors.length - 10} more errors</li>` : ''}
                        </ul>
                    </div>
                `;
            }
            
            errorHtml += '</div>';
            resultDiv.innerHTML = errorHtml;
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        document.getElementById('upload-result').classList.remove('hidden');
        document.getElementById('upload-result').innerHTML = `
            <div class="p-4 bg-red-50 rounded-lg">
                <h4 class="font-medium text-red-800 mb-2"><i class="fas fa-exclamation-circle mr-2"></i>Upload Failed</h4>
                <p class="text-sm text-red-700">Failed to upload file. Please try again.</p>
            </div>
        `;
    } finally {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
    }
}

// Utility functions
function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('ip-master-table').classList.toggle('hidden', show);
}

function showError(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) {
        CRM.showAlert(message, 'error');
    } else {
        showToast(message, 'error');
    }
}

function showSuccess(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) {
        CRM.showAlert(message, 'success');
    } else {
        showToast(message, 'success');
    }
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

function showFieldError(field, message) {
    const errorEl = document.getElementById(`${field}-error`);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

// Force unlock an IP
async function unlockIP(ipMasterId) {
    // First, we need to find the lock for this IP
    if (!confirm('Are you sure you want to force unlock this IP?\n\nThis will cancel any active configuration session using this IP.')) {
        return;
    }
    
    try {
        // Get the lock ID for this IP_Master
        const dashboardResponse = await fetch(`${API_URL}/dashboard.php`, {
            credentials: 'include'
        });
        
        const dashboardData = await dashboardResponse.json();
        
        if (!dashboardData.success) {
            showError('Failed to get lock information');
            return;
        }
        
        const lockedIPs = dashboardData.data.locked_ips || [];
        const lock = lockedIPs.find(l => l.ip_master_id === ipMasterId);
        
        if (!lock) {
            showError('No active lock found for this IP. It may have already been released.');
            loadIPMasters();
            return;
        }
        
        // Force cancel the configuration
        const response = await fetch(`${API_URL}/configuration_cancel.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                lock_id: lock.lock_id,
                force: true
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('IP successfully unlocked');
            loadIPMasters();
        } else {
            showError(data.error?.message || data.message || 'Failed to unlock IP');
        }
    } catch (error) {
        console.error('Error unlocking IP:', error);
        showError('Failed to unlock IP. Please try again.');
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
