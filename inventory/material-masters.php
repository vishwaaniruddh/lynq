<?php
/**
 * Material Masters Management Page
 * 
 * CRUD interface for managing Material Master templates
 * Material Masters define reusable sets of products with quantities for site installations
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// ADV users only
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to access Material Masters';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Material Masters';
$currentPage = 'inventory_material_masters';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Material Masters']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Material Masters</h3>
            <p class="text-sm text-gray-500">Manage reusable product templates for site installations</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Material Master
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by name..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
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
        <p class="mt-2 text-gray-500">Loading material masters...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="masters-table" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Products</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody id="masters-tbody" class="divide-y">
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Empty State -->
    <div id="empty-state" class="hidden p-8 text-center">
        <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
        <p class="text-gray-500">No material masters found</p>
        <button onclick="openCreateModal()" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
            <i class="fas fa-plus mr-2"></i>Create First Material Master
        </button>
    </div>
    
    <!-- Pagination -->
    <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="master-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal_master()"></div>
    <div class="flex items-center justify-center min-h-screen p-4 pointer-events-none">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10 pointer-events-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Material Master</h3>
                <button onclick="closeModal_master()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="master-form" onsubmit="saveMaster(event)">
                <input type="hidden" id="master-id" value="">
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label for="master-name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" id="master-name" name="name" required maxlength="100"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter material master name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="master-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="master-description" name="description" rows="2" maxlength="500"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Optional description (max 500 characters)"></textarea>
                    </div>
                    
                    <!-- Product Selection Section -->
                    <div class="border-t pt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Products <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-500 mb-3">Select products and specify quantities for this material master</p>
                        
                        <!-- Product Search -->
                        <div class="mb-3">
                            <input type="text" id="product-search" 
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Search products to add...">
                        </div>
                        
                        <!-- Available Products List -->
                        <div id="available-products" class="border rounded-lg max-h-40 overflow-y-auto mb-4 hidden">
                            <!-- Populated by JavaScript -->
                        </div>
                        
                        <!-- Selected Products -->
                        <div id="selected-products-container">
                            <div class="text-sm text-gray-500 mb-2">Selected Products:</div>
                            <div id="selected-products" class="space-y-2">
                                <p class="text-sm text-gray-400 italic">No products selected</p>
                            </div>
                        </div>
                        <p id="products-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeModal_master()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
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
    <div class="flex items-center justify-center min-h-screen p-4 pointer-events-none">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10 pointer-events-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Material Master Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5 max-h-[70vh] overflow-y-auto">
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

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4 pointer-events-none">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10 pointer-events-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Confirm Delete</h3>
                <button onclick="closeDeleteModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                    </div>
                </div>
                <p class="text-center text-gray-600 mb-2">Are you sure you want to delete this material master?</p>
                <p id="delete-master-name" class="text-center font-semibold text-gray-800 mb-4"></p>
                <p class="text-center text-sm text-gray-500">This action cannot be undone.</p>
                <input type="hidden" id="delete-master-id" value="">
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" onclick="confirmDelete()" id="delete-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-2"></i>Delete
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
    masters: [],
    products: [],
    selectedProducts: [],
    pagination: { page: 1, limit: 10, total: 0, totalPages: 0 },
    filters: { search: '', status: '' },
    editingId: null,
    loading: false
};

// API Base URLs
const API_BASE = '../api/material-masters';
const PRODUCTS_API = '../api/inventory/products/index.php';

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

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getProductById(id) {
    return state.products.find(p => p.id === parseInt(id));
}

function getStatusBadge(status) {
    return status === 'active'
        ? '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">Active</span>'
        : '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Inactive</span>';
}

function showLoading(show) {
    state.loading = show;
    const loadingIndicator = document.getElementById('loading-indicator');
    const table = document.getElementById('masters-table');
    const emptyState = document.getElementById('empty-state');
    
    if (show) {
        loadingIndicator.classList.remove('hidden');
        table.classList.add('hidden');
        emptyState.classList.add('hidden');
    } else {
        loadingIndicator.classList.add('hidden');
    }
}

// ===========================================
// API Functions
// ===========================================
async function loadMasters() {
    showLoading(true);
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
        
        const response = await fetch(`${API_BASE}/list.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.masters = data.data.material_masters || [];
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                totalPages: data.data.pagination.total_pages
            };
            renderTable();
        } else {
            showToast(data.message || 'Failed to load material masters', 'error');
        }
    } catch (error) {
        console.error('Error loading masters:', error);
        showToast('Failed to load material masters', 'error');
    } finally {
        showLoading(false);
    }
}

async function loadProducts() {
    try {
        const response = await fetch(`${PRODUCTS_API}?limit=500`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.products = data.data.products || [];
        } else {
            console.error('Failed to load products:', data.message);
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

async function saveMaster(event) {
    event.preventDefault();
    if (!validateForm()) return;
    
    const name = document.getElementById('master-name').value.trim();
    const description = document.getElementById('master-description').value.trim();
    const items = state.selectedProducts.map(p => ({ product_id: p.product_id, quantity: p.quantity }));
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        let response;
        
        if (state.editingId) {
            // Update existing master
            response = await fetch(`${API_BASE}/update.php?id=${state.editingId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ name, description, items })
            });
        } else {
            // Create new master
            response = await fetch(`${API_BASE}/create.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ name, description, items })
            });
        }
        
        const data = await response.json();
        
        if (data.success) {
            showToast(state.editingId ? 'Material Master updated successfully' : 'Material Master created successfully');
            closeModal_master();
            loadMasters();
        } else {
            // Handle validation errors
            if (data.errors) {
                if (data.errors.name) {
                    document.getElementById('name-error').textContent = data.errors.name;
                    document.getElementById('name-error').classList.remove('hidden');
                }
                if (data.errors.items) {
                    document.getElementById('products-error').textContent = data.errors.items;
                    document.getElementById('products-error').classList.remove('hidden');
                }
            } else {
                showToast(data.message || 'Failed to save material master', 'error');
            }
        }
    } catch (error) {
        console.error('Error saving master:', error);
        showToast('Failed to save material master', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

async function viewMaster(id) {
    try {
        const response = await fetch(`${API_BASE}/detail.php?id=${id}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const master = data.data.material_master;
            
            const productsHtml = (master.items || []).map(item => {
                return `<tr class="border-b last:border-b-0">
                    <td class="py-2 text-sm">${item.product_name || 'Unknown'}</td>
                    <td class="py-2 text-sm text-right">${item.quantity}</td>
                </tr>`;
            }).join('');
            
            document.getElementById('view-content').innerHTML = `
                <div class="space-y-4">
                    <div><label class="text-xs text-gray-500 uppercase">Name</label><p class="font-medium text-gray-800">${master.name}</p></div>
                    <div><label class="text-xs text-gray-500 uppercase">Description</label><p class="text-gray-600">${master.description || 'No description'}</p></div>
                    <div class="flex gap-8">
                        <div><label class="text-xs text-gray-500 uppercase">Status</label><div class="mt-1">${getStatusBadge(master.status)}</div></div>
                        <div><label class="text-xs text-gray-500 uppercase">Created</label><p class="text-gray-600">${formatDate(master.created_at)}</p></div>
                    </div>
                    <div class="border-t pt-4">
                        <label class="text-xs text-gray-500 uppercase">Products (${(master.items || []).length})</label>
                        <table class="w-full mt-2"><thead><tr class="text-left text-xs text-gray-500 border-b">
                            <th class="py-2">Product</th><th class="py-2 text-right">Qty</th>
                        </tr></thead><tbody>${productsHtml || '<tr><td colspan="2" class="py-2 text-sm text-gray-400">No products</td></tr>'}</tbody></table>
                    </div>
                </div>`;
            document.getElementById('view-modal').classList.remove('hidden');
        } else {
            showToast(data.message || 'Failed to load material master details', 'error');
        }
    } catch (error) {
        console.error('Error loading master details:', error);
        showToast('Failed to load material master details', 'error');
    }
}

async function deleteMaster(id) {
    const deleteBtn = document.getElementById('delete-btn');
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
    
    try {
        const response = await fetch(`${API_BASE}/delete.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Material Master deleted successfully');
            closeDeleteModal();
            loadMasters();
        } else {
            showToast(data.message || 'Failed to delete material master', 'error');
        }
    } catch (error) {
        console.error('Error deleting master:', error);
        showToast('Failed to delete material master', 'error');
    } finally {
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '<i class="fas fa-trash mr-2"></i>Delete';
    }
}

// ===========================================
// Table Rendering
// ===========================================
function renderTable() {
    const tbody = document.getElementById('masters-tbody');
    const emptyState = document.getElementById('empty-state');
    const table = document.getElementById('masters-table');
    
    if (state.masters.length === 0) {
        tbody.innerHTML = '';
        table.classList.add('hidden');
        emptyState.classList.remove('hidden');
        document.getElementById('pagination-container').classList.add('hidden');
        return;
    }
    
    table.classList.remove('hidden');
    emptyState.classList.add('hidden');
    document.getElementById('pagination-container').classList.remove('hidden');
    
    tbody.innerHTML = state.masters.map(master => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 text-sm text-gray-600">${master.id}</td>
            <td class="px-6 py-4 text-sm font-medium text-gray-800">${master.name}</td>
            <td class="px-6 py-4 text-sm text-gray-600">${master.description || '<span class="text-gray-400 italic">No description</span>'}</td>
            <td class="px-6 py-4 text-sm text-gray-600">
                <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">${master.product_count || (master.items ? master.items.length : 0)} products</span>
            </td>
            <td class="px-6 py-4">${getStatusBadge(master.status)}</td>
            <td class="px-6 py-4 text-sm text-gray-600">${formatDate(master.created_at)}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <button onclick="viewMaster(${master.id})" class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editMaster(${master.id})" class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="openDeleteModal(${master.id})" class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    renderPagination();
}

function renderPagination() {
    const info = document.getElementById('pagination-info');
    const controls = document.getElementById('pagination-controls');
    const { page, limit, total, totalPages } = state.pagination;
    const start = (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    
    info.textContent = total > 0 ? `Showing ${start} to ${end} of ${total} entries` : 'No entries';
    
    if (totalPages <= 1) { controls.innerHTML = ''; return; }
    
    let html = `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 border rounded-lg ${page === 1 ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i></button>`;
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
            html += `<button onclick="goToPage(${i})" 
                class="px-3 py-1 border rounded-lg ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">${i}</button>`;
        } else if (i === page - 2 || i === page + 2) {
            html += '<span class="px-2">...</span>';
        }
    }
    
    html += `<button onclick="goToPage(${page + 1})" ${page === totalPages ? 'disabled' : ''} 
        class="px-3 py-1 border rounded-lg ${page === totalPages ? 'text-gray-300 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i></button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.totalPages) return;
    state.pagination.page = page;
    loadMasters();
}

// ===========================================
// Create/Edit Modal
// ===========================================
function openCreateModal() {
    state.editingId = null;
    state.selectedProducts = [];
    document.getElementById('modal-title').textContent = 'Add Material Master';
    document.getElementById('master-id').value = '';
    document.getElementById('master-form').reset();
    document.getElementById('name-error').classList.add('hidden');
    document.getElementById('products-error').classList.add('hidden');
    renderSelectedProducts();
    document.getElementById('master-modal').classList.remove('hidden');
}

async function editMaster(id) {
    try {
        const response = await fetch(`${API_BASE}/detail.php?id=${id}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const master = data.data.material_master;
            
            state.editingId = id;
            state.selectedProducts = (master.items || []).map(item => ({
                product_id: item.product_id,
                product_name: item.product_name || 'Unknown',
                quantity: item.quantity
            }));
            
            document.getElementById('modal-title').textContent = 'Edit Material Master';
            document.getElementById('master-id').value = id;
            document.getElementById('master-name').value = master.name;
            document.getElementById('master-description').value = master.description || '';
            document.getElementById('name-error').classList.add('hidden');
            document.getElementById('products-error').classList.add('hidden');
            renderSelectedProducts();
            document.getElementById('master-modal').classList.remove('hidden');
        } else {
            showToast(data.message || 'Failed to load material master', 'error');
        }
    } catch (error) {
        console.error('Error loading master for edit:', error);
        showToast('Failed to load material master', 'error');
    }
}

function closeModal_master() {
    document.getElementById('master-modal').classList.add('hidden');
    document.body.style.overflow = '';
    state.selectedProducts = [];
    state.editingId = null;
}

function renderAvailableProducts(searchTerm = '') {
    const container = document.getElementById('available-products');
    const selectedIds = state.selectedProducts.map(p => p.product_id);
    const filtered = state.products.filter(p => 
        !selectedIds.includes(p.id) && 
        (p.name.toLowerCase().includes(searchTerm.toLowerCase()) || (p.sku && p.sku.toLowerCase().includes(searchTerm.toLowerCase())))
    );
    
    if (filtered.length === 0 || !searchTerm) {
        container.classList.add('hidden');
        return;
    }
    
    container.innerHTML = filtered.slice(0, 20).map(p => `
        <div class="p-2 hover:bg-gray-50 cursor-pointer flex justify-between items-center border-b last:border-b-0" onclick="addProduct(${p.id})">
            <div><span class="font-medium text-sm">${p.name}</span><span class="text-xs text-gray-500 ml-2">${p.sku || ''}</span></div>
            <i class="fas fa-plus text-green-500"></i>
        </div>
    `).join('');
    container.classList.remove('hidden');
}

function addProduct(productId) {
    const product = getProductById(productId);
    if (!product || state.selectedProducts.find(p => p.product_id === productId)) return;
    
    state.selectedProducts.push({ product_id: productId, product_name: product.name, quantity: 1 });
    document.getElementById('product-search').value = '';
    document.getElementById('available-products').classList.add('hidden');
    document.getElementById('products-error').classList.add('hidden');
    renderSelectedProducts();
}

function removeProduct(productId) {
    state.selectedProducts = state.selectedProducts.filter(p => p.product_id !== productId);
    renderSelectedProducts();
}

function updateQuantity(productId, quantity) {
    const item = state.selectedProducts.find(p => p.product_id === productId);
    if (item) item.quantity = Math.max(1, parseInt(quantity) || 1);
}

function renderSelectedProducts() {
    const container = document.getElementById('selected-products');
    if (state.selectedProducts.length === 0) {
        container.innerHTML = '<p class="text-sm text-gray-400 italic">No products selected</p>';
        return;
    }
    
    container.innerHTML = state.selectedProducts.map(item => `
        <div class="flex items-center gap-3 p-2 bg-gray-50 rounded-lg">
            <div class="flex-1 text-sm font-medium text-gray-700">${item.product_name}</div>
            <input type="number" min="1" value="${item.quantity}" onchange="updateQuantity(${item.product_id}, this.value)"
                class="w-20 px-2 py-1 border rounded text-center text-sm focus:ring-2 focus:ring-primary">
            <button type="button" onclick="removeProduct(${item.product_id})" class="p-1 text-red-500 hover:bg-red-50 rounded">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

function validateForm() {
    let valid = true;
    const name = document.getElementById('master-name').value.trim();
    const nameError = document.getElementById('name-error');
    const productsError = document.getElementById('products-error');
    
    if (!name) {
        nameError.textContent = 'Name is required';
        nameError.classList.remove('hidden');
        valid = false;
    } else { nameError.classList.add('hidden'); }
    
    if (state.selectedProducts.length === 0) {
        productsError.textContent = 'At least one product must be selected';
        productsError.classList.remove('hidden');
        valid = false;
    } else { productsError.classList.add('hidden'); }
    
    return valid;
}

// ===========================================
// View Modal
// ===========================================
function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
}

// ===========================================
// Delete Modal
// ===========================================
function openDeleteModal(id) {
    const master = state.masters.find(m => m.id === id);
    if (!master) return;
    
    document.getElementById('delete-master-id').value = id;
    document.getElementById('delete-master-name').textContent = master.name;
    document.getElementById('delete-modal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
}

function confirmDelete() {
    const id = parseInt(document.getElementById('delete-master-id').value);
    deleteMaster(id);
}

// ===========================================
// Event Listeners & Initialization
// ===========================================
let searchTimeout = null;

document.addEventListener('DOMContentLoaded', async function() {
    // Load products first for selection dropdown
    await loadProducts();
    
    // Then load material masters
    loadMasters();
    
    // Search with debounce
    document.getElementById('search-input').addEventListener('input', function(e) {
        state.filters.search = e.target.value;
        state.pagination.page = 1;
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadMasters();
        }, 300);
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadMasters();
    });
    
    document.getElementById('product-search').addEventListener('input', function(e) {
        renderAvailableProducts(e.target.value);
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>