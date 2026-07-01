<?php
/**
 * Warehouse Management Page
 * 
 * Lists warehouses with status indicators
 * Create/edit warehouse forms with validation
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission - ADV users always have access
if (!can('inventory.warehouses.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view warehouses';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Warehouse Management';
$currentPage = 'inventory_warehouses';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Warehouses']
];

// Get user permissions - ADV users have all permissions
$isAdv = isAdvUser();
$canCreate = can('inventory.warehouses.create') || $isAdv;
$canEdit = can('inventory.warehouses.update') || $isAdv;
$canDelete = can('inventory.warehouses.delete') || $isAdv;

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Warehouse Management</h3>
            <p class="text-sm text-gray-500">Manage inventory storage locations</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($canCreate): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Warehouse
            </button>
            <?php endif; ?>
            <button onclick="exportWarehouses()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search warehouses..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <?php if (isAdvUser()): ?>
            <div>
                <select id="company-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Companies</option>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading warehouses...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="warehouses-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" data-sort="name">
                        Warehouse <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Company</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" data-sort="status">
                        Status <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="warehouses-tbody" class="divide-y divide-gray-100">
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

<!-- Create/Edit Modal -->
<div id="warehouse-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeWarehouseModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Warehouse</h3>
                <button onclick="closeWarehouseModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="warehouse-form" onsubmit="saveWarehouse(event)">
                <input type="hidden" id="warehouse-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="warehouse-name" class="block text-sm font-medium text-gray-700 mb-1">Warehouse Name <span class="text-red-500">*</span></label>
                        <input type="text" id="warehouse-name" name="name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter warehouse name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="warehouse-location" class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" id="warehouse-location" name="location"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter location/address">
                    </div>
                    <div>
                        <label for="warehouse-company" class="block text-sm font-medium text-gray-700 mb-1">Company <span class="text-red-500">*</span></label>
                        <select id="warehouse-company" name="company_id" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Company</option>
                        </select>
                        <p id="company_id-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="warehouse-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="warehouse-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeWarehouseModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
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
                <h3 class="text-lg font-semibold text-gray-800">Warehouse Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5">
                <!-- Content populated by JavaScript -->
            </div>
            <div class="flex justify-end p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
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
    warehouses: [],
    companies: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', company_id: '', status: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: {
        create: <?php echo json_encode($canCreate); ?>,
        edit: <?php echo json_encode($canEdit); ?>,
        delete: <?php echo json_encode($canDelete); ?>
    }
};

const API_URL = '../api/inventory/warehouses';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCompanies();
    loadWarehouses();
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
            loadWarehouses();
        }, 300);
    });
    
    const companyFilter = document.getElementById('company-filter');
    if (companyFilter) {
        companyFilter.addEventListener('change', function(e) {
            state.filters.company_id = e.target.value;
            state.pagination.page = 1;
            loadWarehouses();
        });
    }
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadWarehouses();
    });
    
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const field = this.dataset.sort;
            if (state.sort.field === field) {
                state.sort.direction = state.sort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort.field = field;
                state.sort.direction = 'asc';
            }
            loadWarehouses();
            updateSortIndicators();
        });
    });
}

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

// Load companies for dropdown
async function loadCompanies() {
    try {
        const response = await fetch('../api/users/companies.php', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.companies = data.data.companies || [];
            populateCompanyDropdowns();
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

function populateCompanyDropdowns() {
    const filterSelect = document.getElementById('company-filter');
    const formSelect = document.getElementById('warehouse-company');
    
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">All Companies</option>' +
            state.companies.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    }
    
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Select Company</option>' +
            state.companies.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    }
}

// Load warehouses from API
async function loadWarehouses() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.company_id) params.append('company_id', state.filters.company_id);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.warehouses = data.data.warehouses;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load warehouses');
        }
    } catch (error) {
        console.error('Error loading warehouses:', error);
        showError('Failed to load warehouses. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render table
function renderTable() {
    const tbody = document.getElementById('warehouses-tbody');
    
    if (state.warehouses.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-warehouse text-4xl mb-3 text-gray-300"></i>
                    <p>No warehouses found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startNum = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.warehouses.map((warehouse, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${startNum + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br ${warehouse.status === 'active' ? 'from-blue-50 to-blue-100' : 'from-gray-50 to-gray-100'} rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-warehouse ${warehouse.status === 'active' ? 'text-blue-500' : 'text-gray-400'} text-xs"></i>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(warehouse.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5 text-gray-600 text-xs">${escapeHtml(warehouse.company_name || '-')}</td>
            <td class="px-4 py-2.5 text-gray-600 text-xs">${escapeHtml(warehouse.location || '-')}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center gap-1.5 whitespace-nowrap">
                    <a href="warehouse-stock.php?warehouse_id=${warehouse.id}&type=stock" class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] whitespace-nowrap hover:bg-blue-100 transition cursor-pointer">${warehouse.stock_count || 0} items</a>
                    <a href="warehouse-stock.php?warehouse_id=${warehouse.id}&type=assets" class="px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px] whitespace-nowrap hover:bg-purple-100 transition cursor-pointer">${warehouse.asset_count || 0} assets</a>
                </div>
            </td>
            <td class="px-4 py-2.5">
                ${warehouse.status === 'active' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>Inactive</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <button onclick="viewWarehouse(${warehouse.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${state.permissions.edit ? `
                    <button onclick="editWarehouse(${warehouse.id})" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
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
    loadWarehouses();
}

// Modal functions
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Warehouse';
    document.getElementById('warehouse-id').value = '';
    document.getElementById('warehouse-name').value = '';
    document.getElementById('warehouse-location').value = '';
    document.getElementById('warehouse-company').value = '';
    document.getElementById('warehouse-status').value = 'active';
    clearErrors();
    document.getElementById('warehouse-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editWarehouse(id) {
    const warehouse = state.warehouses.find(w => w.id === id);
    if (!warehouse) return;
    
    document.getElementById('modal-title').textContent = 'Edit Warehouse';
    document.getElementById('warehouse-id').value = warehouse.id;
    document.getElementById('warehouse-name').value = warehouse.name;
    document.getElementById('warehouse-location').value = warehouse.location || '';
    document.getElementById('warehouse-company').value = warehouse.company_id;
    document.getElementById('warehouse-status').value = warehouse.status;
    clearErrors();
    document.getElementById('warehouse-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeWarehouseModal() {
    document.getElementById('warehouse-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveWarehouse(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('warehouse-id').value;
    const name = document.getElementById('warehouse-name').value.trim();
    const location = document.getElementById('warehouse-location').value.trim();
    const companyId = document.getElementById('warehouse-company').value;
    const status = document.getElementById('warehouse-status').value;
    
    if (!name) {
        showFieldError('name', 'Warehouse name is required');
        return;
    }
    if (!companyId) {
        showFieldError('company_id', 'Company is required');
        return;
    }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const endpoint = id ? `${API_URL}/update.php` : `${API_URL}/create.php`;
        const payload = { name, location, company_id: parseInt(companyId), status };
        if (id) payload.id = parseInt(id);
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeWarehouseModal();
            showSuccess(data.message || 'Warehouse saved successfully');
            loadWarehouses();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    showFieldError(field, data.errors[field]);
                });
            } else {
                showError(data.error?.message || 'Failed to save warehouse');
            }
        }
    } catch (error) {
        console.error('Error saving warehouse:', error);
        showError('Failed to save warehouse. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

// View warehouse
function viewWarehouse(id) {
    const warehouse = state.warehouses.find(w => w.id === id);
    if (!warehouse) return;
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 ${warehouse.status === 'active' ? 'bg-blue-100' : 'bg-gray-100'} rounded-2xl flex items-center justify-center">
                    <i class="fas fa-warehouse text-3xl ${warehouse.status === 'active' ? 'text-blue-500' : 'text-gray-400'}"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">ID</p>
                    <p class="font-medium">${warehouse.id}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p>${warehouse.status === 'active' 
                        ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>'
                        : '<span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Inactive</span>'
                    }</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Warehouse Name</p>
                    <p class="font-medium">${escapeHtml(warehouse.name)}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Company</p>
                    <p class="font-medium">${escapeHtml(warehouse.company_name || '-')}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Location</p>
                    <p class="font-medium">${escapeHtml(warehouse.location || '-')}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Stock Items</p>
                    <p class="font-medium">${warehouse.stock_count || 0}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Assets</p>
                    <p class="font-medium">${warehouse.asset_count || 0}</p>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('view-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Export warehouses
async function exportWarehouses() {
    try {
        const params = new URLSearchParams({ limit: 1000 });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.company_id) params.append('company_id', state.filters.company_id);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.warehouses, 'warehouses_export.csv');
            showSuccess('Warehouses exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export warehouses');
        }
    } catch (error) {
        console.error('Error exporting warehouses:', error);
        showError('Failed to export warehouses. Please try again.');
    }
}

function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'Company', 'Location', 'Status', 'Stock Count', 'Asset Count'];
    const rows = data.map(w => [
        w.id,
        `"${(w.name || '').replace(/"/g, '""')}"`,
        `"${(w.company_name || '').replace(/"/g, '""')}"`,
        `"${(w.location || '').replace(/"/g, '""')}"`,
        w.status,
        w.stock_count || 0,
        w.asset_count || 0
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
    document.getElementById('warehouses-table').classList.toggle('hidden', show);
}

function showError(message) {
    showToast(message, 'error');
}

function showSuccess(message) {
    showToast(message, 'success');
}

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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
