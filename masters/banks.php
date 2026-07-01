<?php
/**
 * Banks Management Page
 * 
 * Implements table with pagination, search, status filter
 * Add create/edit modal with form validation
 * Add view and delete confirmation modals
 * Include permission-based button visibility
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 8.3, 10.1, 10.2, 10.4
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access and view permission
$masterMiddleware = new MasterModuleMiddleware();
$user = $masterMiddleware->requireViewPermission('banks');

if (!$user) {
    exit; // Middleware handles redirect
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Bank Management';
$currentPage = 'masters_banks';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'Banks']
];

// Get user permissions for this module
$permissions = $masterMiddleware->getUserModulePermissions('banks');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Bank Management</h3>
            <p class="text-sm text-gray-500">Manage bank records for the CRM system</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Bank
            </button>
            <?php endif; ?>
            <button onclick="exportBanks()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search banks..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading banks...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="banks-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">
                        ID <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="name">
                        Bank Name <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">
                        Status <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="created_at">
                        Created <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="banks-tbody" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td>
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
<div id="bank-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeBankModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Bank</h3>
                <button onclick="closeBankModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="bank-form" onsubmit="saveBank(event)">
                <input type="hidden" id="bank-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="bank-name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name <span class="text-red-500">*</span></label>
                        <input type="text" id="bank-name" name="name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter bank name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="bank-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="bank-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeBankModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
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
                <h3 class="text-lg font-semibold text-gray-800">Bank Details</h3>
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

<script>
// State management
const state = {
    banks: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: <?php echo json_encode($permissions); ?>
};

// API base URL
const API_URL = '../api/masters/banks.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadBanks();
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
            loadBanks();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadBanks();
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
            loadBanks();
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

// Load banks from API
async function loadBanks() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status !== '') params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.banks = data.data.banks;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load banks');
        }
    } catch (error) {
        console.error('Error loading banks:', error);
        showError('Failed to load banks. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render table
function renderTable() {
    const tbody = document.getElementById('banks-tbody');
    
    if (state.banks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-university text-4xl mb-3 text-gray-300"></i>
                    <p>No banks found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.banks.map((bank, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">${startIndex + index + 1}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${bank.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-university text-blue-500 text-xs"></i>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(bank.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5">
                ${bank.status == 1 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'
                }
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${formatDate(bank.created_at)}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <button onclick="viewBank(${bank.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${state.permissions.edit ? `
                    <button onclick="editBank(${bank.id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                    ${state.permissions.delete ? `
                    <button onclick="confirmDelete(${bank.id}, '${escapeHtml(bank.name)}')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
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
    loadBanks();
}

// Open create modal
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Bank';
    document.getElementById('bank-id').value = '';
    document.getElementById('bank-name').value = '';
    document.getElementById('bank-status').value = '1';
    clearErrors();
    document.getElementById('bank-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Edit bank
function editBank(id) {
    const bank = state.banks.find(b => b.id === id);
    if (!bank) return;
    
    document.getElementById('modal-title').textContent = 'Edit Bank';
    document.getElementById('bank-id').value = bank.id;
    document.getElementById('bank-name').value = bank.name;
    document.getElementById('bank-status').value = bank.status;
    clearErrors();
    document.getElementById('bank-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close bank modal
function closeBankModal() {
    document.getElementById('bank-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Save bank
async function saveBank(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('bank-id').value;
    const name = document.getElementById('bank-name').value.trim();
    const status = document.getElementById('bank-status').value;
    
    if (!name) {
        showFieldError('name', 'Bank name is required');
        return;
    }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const payload = {
            action: id ? 'update' : 'create',
            name: name,
            status: parseInt(status)
        };
        
        if (id) payload.id = parseInt(id);
        
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeBankModal();
            showSuccess(data.message || 'Bank saved successfully');
            loadBanks();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    showFieldError(field, data.errors[field][0]);
                });
            } else {
                showError(data.error?.message || data.message || 'Failed to save bank');
            }
        }
    } catch (error) {
        console.error('Error saving bank:', error);
        showError('Failed to save bank. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

// View bank
function viewBank(id) {
    const bank = state.banks.find(b => b.id === id);
    if (!bank) return;
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-university text-3xl text-blue-500"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">ID</p>
                    <p class="font-medium">${bank.id}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p>${bank.status == 1 
                        ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>'
                        : '<span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Inactive</span>'
                    }</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Bank Name</p>
                    <p class="font-medium">${escapeHtml(bank.name)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created</p>
                    <p class="font-medium">${formatDate(bank.created_at)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Updated</p>
                    <p class="font-medium">${formatDate(bank.updated_at)}</p>
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
function confirmDelete(id, name) {
    openConfirmModal(
        'Delete Bank',
        `Are you sure you want to delete "${name}"? This will set the bank status to inactive.`,
        function() {
            deleteBank(id);
        }
    );
}

// Delete bank
async function deleteBank(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'Bank deleted successfully');
            loadBanks();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete bank');
        }
    } catch (error) {
        console.error('Error deleting bank:', error);
        showError('Failed to delete bank. Please try again.');
    }
}

// Export banks
async function exportBanks() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status !== '') params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.banks, 'banks_export.csv');
            showSuccess('Banks exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export banks');
        }
    } catch (error) {
        console.error('Error exporting banks:', error);
        showError('Failed to export banks. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'Status', 'Created At', 'Updated At'];
    const rows = data.map(bank => [
        bank.id,
        `"${bank.name.replace(/"/g, '""')}"`,
        bank.status == 1 ? 'Active' : 'Inactive',
        bank.created_at,
        bank.updated_at
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
    document.getElementById('banks-table').classList.toggle('hidden', show);
}

function showError(message) {
    // Use the CRM alert system
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

// Fallback toast notification
function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2 animate-fade-in`;
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
