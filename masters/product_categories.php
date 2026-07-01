<?php
/**
 * Product Categories Management Page
 * 
 * Implements table with pagination, search, status filter
 * Add create/edit modal with form validation
 * Add view and delete confirmation modals
 * Include permission-based button visibility
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
$user = $masterMiddleware->requireViewPermission('product_categories');

if (!$user) {
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Product Category Management';
$currentPage = 'masters_product_categories';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'Product Categories']
];

$permissions = $masterMiddleware->getUserModulePermissions('product_categories');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Product Category Management</h3>
            <p class="text-sm text-gray-500">Manage product categories for inventory items</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Category
            </button>
            <?php endif; ?>
            <button onclick="exportCategories()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search categories..." 
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
        <p class="mt-2 text-gray-500">Loading categories...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="categories-table" class="w-full">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Category Name</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Parent</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Products</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="categories-tbody" class="divide-y">
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading...</td>
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
<div id="category-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeCategoryModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Category</h3>
                <button onclick="closeCategoryModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="category-form" onsubmit="saveCategory(event)">
                <input type="hidden" id="category-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="category-name" class="block text-sm font-medium text-gray-700 mb-1">Category Name <span class="text-red-500">*</span></label>
                        <input type="text" id="category-name" name="name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter category name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="category-parent" class="block text-sm font-medium text-gray-700 mb-1">Parent Category</label>
                        <select id="category-parent" name="parent_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">None (Root Category)</option>
                        </select>
                    </div>
                    <div>
                        <label for="category-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="category-description" name="description" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Optional description"></textarea>
                    </div>
                    <div>
                        <label for="category-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="category-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeCategoryModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
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
                <h3 class="text-lg font-semibold text-gray-800">Category Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5"></div>
            <div class="flex justify-end p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Products Modal -->
<div id="products-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeProductsModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full relative z-10 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 id="products-modal-title" class="text-lg font-semibold text-gray-800">Products</h3>
                    <p id="products-modal-subtitle" class="text-sm text-gray-500"></p>
                </div>
                <button onclick="closeProductsModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="products-modal-content" class="p-5 overflow-y-auto flex-1">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                    <p class="mt-2 text-gray-500">Loading products...</p>
                </div>
            </div>
            <div class="flex justify-end p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeProductsModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const state = {
    categories: [],
    allCategories: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '' },
    permissions: <?php echo json_encode($permissions); ?>
};

const API_URL = '../api/masters/product_categories.php';

document.addEventListener('DOMContentLoaded', function() {
    loadAllCategories();
    loadCategories();
    setupEventListeners();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadCategories();
        }, 300);
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadCategories();
    });
}

async function loadAllCategories() {
    try {
        const response = await fetch(`${API_URL}?limit=1000&status=active`, { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.allCategories = data.data.categories || [];
            populateParentDropdown();
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function populateParentDropdown(excludeId = null) {
    const select = document.getElementById('category-parent');
    let options = '<option value="">None (Root Category)</option>';
    state.allCategories.forEach(c => {
        if (excludeId === null || c.id !== excludeId) {
            options += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
        }
    });
    select.innerHTML = options;
}

async function loadCategories() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.categories = data.data.categories;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load categories');
        }
    } catch (error) {
        console.error('Error loading categories:', error);
        showError('Failed to load categories. Please try again.');
    } finally {
        showLoading(false);
    }
}

function renderTable() {
    const tbody = document.getElementById('categories-tbody');
    
    if (state.categories.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-folder-open text-4xl mb-3 text-gray-300"></i>
                    <p>No categories found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.categories.map((cat, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-folder text-purple-500 text-xs"></i>
                    </div>
                    <span class="font-medium text-xs text-gray-800">${escapeHtml(cat.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${cat.parent_name ? escapeHtml(cat.parent_name) : '-'}</td>
            <td class="px-4 py-2.5">
                ${(cat.product_count || 0) > 0 ? `<button onclick="showCategoryProducts(${cat.id}, '${escapeHtml(cat.name).replace(/'/g, "\\'")}')" class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px] hover:bg-blue-200 transition cursor-pointer">${cat.product_count} products</button>` : '<span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-[10px]">0 products</span>'}
            </td>
            <td class="px-4 py-2.5">
                ${cat.status === 'active' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1.5"></span>Inactive</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center space-x-1">
                    <button onclick="viewCategory(${cat.id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${state.permissions.edit ? `
                    <button onclick="editCategory(${cat.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                    ${state.permissions.delete ? `
                    <button onclick="confirmDelete(${cat.id}, '${escapeHtml(cat.name)}')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}


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
    loadCategories();
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Category';
    document.getElementById('category-id').value = '';
    document.getElementById('category-name').value = '';
    document.getElementById('category-parent').value = '';
    document.getElementById('category-description').value = '';
    document.getElementById('category-status').value = 'active';
    populateParentDropdown();
    clearErrors();
    document.getElementById('category-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editCategory(id) {
    const cat = state.categories.find(c => c.id === id);
    if (!cat) return;
    
    document.getElementById('modal-title').textContent = 'Edit Category';
    document.getElementById('category-id').value = cat.id;
    document.getElementById('category-name').value = cat.name;
    document.getElementById('category-description').value = cat.description || '';
    document.getElementById('category-status').value = cat.status;
    populateParentDropdown(cat.id);
    document.getElementById('category-parent').value = cat.parent_id || '';
    clearErrors();
    document.getElementById('category-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCategoryModal() {
    document.getElementById('category-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveCategory(event) {
    event.preventDefault();
    event.stopPropagation();
    
    const saveBtn = document.getElementById('save-btn');
    
    // Prevent double submission
    if (saveBtn.disabled) {
        return;
    }
    
    clearErrors();
    
    const id = document.getElementById('category-id').value;
    const name = document.getElementById('category-name').value.trim();
    const parentId = document.getElementById('category-parent').value;
    const description = document.getElementById('category-description').value.trim();
    const status = document.getElementById('category-status').value;
    
    if (!name) {
        showFieldError('name', 'Category name is required');
        return;
    }
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const payload = {
            action: id ? 'update' : 'create',
            name: name,
            description: description,
            parent_id: parentId || null,
            status: status
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
            closeCategoryModal();
            showSuccess(data.message || 'Category saved successfully');
            loadAllCategories();
            loadCategories();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    showFieldError(field, data.errors[field][0]);
                });
            } else {
                showError(data.error?.message || data.message || 'Failed to save category');
            }
        }
    } catch (error) {
        console.error('Error saving category:', error);
        showError('Failed to save category. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

function viewCategory(id) {
    const cat = state.categories.find(c => c.id === id);
    if (!cat) return;
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-folder text-3xl text-purple-500"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">ID</p>
                    <p class="font-medium">${cat.id}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p>${cat.status === 'active' 
                        ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>'
                        : '<span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Inactive</span>'
                    }</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Category Name</p>
                    <p class="font-medium">${escapeHtml(cat.name)}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Parent Category</p>
                    <p class="font-medium">${cat.parent_name ? escapeHtml(cat.parent_name) : 'None (Root)'}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Description</p>
                    <p class="font-medium">${cat.description ? escapeHtml(cat.description) : '-'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Products</p>
                    <p class="font-medium">${cat.product_count || 0}</p>
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

async function showCategoryProducts(categoryId, categoryName) {
    document.getElementById('products-modal-title').textContent = categoryName;
    document.getElementById('products-modal-subtitle').textContent = 'Products with warehouse-wise stock';
    document.getElementById('products-modal-content').innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
            <p class="mt-2 text-gray-500">Loading products...</p>
        </div>
    `;
    document.getElementById('products-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    try {
        const response = await fetch(`../api/inventory/products/index.php?category_id=${categoryId}&limit=100`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success && data.data.products.length > 0) {
            const products = data.data.products;
            
            // Get stock for each product
            const stockResponse = await fetch(`../api/inventory/stock/index.php?category_id=${categoryId}&limit=500`, { credentials: 'include' });
            const stockData = await stockResponse.json();
            const stockItems = stockData.success ? stockData.data.stock : [];
            
            // Group stock by product
            const stockByProduct = {};
            stockItems.forEach(s => {
                if (!stockByProduct[s.product_id]) stockByProduct[s.product_id] = [];
                stockByProduct[s.product_id].push(s);
            });
            
            let html = '<div class="space-y-4">';
            products.forEach(product => {
                const productStock = stockByProduct[product.id] || [];
                const totalStock = productStock.reduce((sum, s) => sum + (s.quantity || 0), 0);
                
                html += `
                    <div class="border rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 ${product.is_serializable ? 'bg-purple-100' : 'bg-blue-100'} rounded-lg flex items-center justify-center">
                                    <i class="fas ${product.is_serializable ? 'fa-barcode text-purple-500' : 'fa-box text-blue-500'} text-sm"></i>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-800">${escapeHtml(product.name)}</span>
                                    <span class="text-xs text-gray-500 ml-2">${escapeHtml(product.unit_of_measure || '')}</span>
                                </div>
                            </div>
                            <span class="px-2 py-1 ${totalStock > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'} rounded text-xs font-medium">
                                Total: ${totalStock}
                            </span>
                        </div>
                        ${productStock.length > 0 ? `
                        <div class="px-4 py-2 divide-y">
                            ${productStock.map(s => `
                                <div class="py-2 flex items-center justify-between text-sm">
                                    <span class="text-gray-600"><i class="fas fa-warehouse text-gray-400 mr-2"></i>${escapeHtml(s.warehouse_name || 'Unknown')}</span>
                                    <span class="font-medium ${s.quantity > 0 ? 'text-green-600' : 'text-gray-400'}">${s.quantity || 0}</span>
                                </div>
                            `).join('')}
                        </div>
                        ` : `
                        <div class="px-4 py-3 text-sm text-gray-500 text-center">No stock in any warehouse</div>
                        `}
                    </div>
                `;
            });
            html += '</div>';
            
            document.getElementById('products-modal-content').innerHTML = html;
        } else {
            document.getElementById('products-modal-content').innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-box-open text-4xl mb-3 text-gray-300"></i>
                    <p>No products found in this category</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading products:', error);
        document.getElementById('products-modal-content').innerHTML = `
            <div class="text-center py-8 text-red-500">
                <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                <p>Failed to load products</p>
            </div>
        `;
    }
}

function closeProductsModal() {
    document.getElementById('products-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    openConfirmModal(
        'Delete Category',
        `Are you sure you want to delete "${name}"? This will set the category status to inactive.`,
        function() {
            deleteCategory(id);
        }
    );
}

async function deleteCategory(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'Category deleted successfully');
            loadAllCategories();
            loadCategories();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete category');
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        showError('Failed to delete category. Please try again.');
    }
}

async function exportCategories() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.categories, 'product_categories_export.csv');
            showSuccess('Categories exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export categories');
        }
    } catch (error) {
        console.error('Error exporting categories:', error);
        showError('Failed to export categories. Please try again.');
    }
}

function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'Parent', 'Description', 'Status', 'Products'];
    const rows = data.map(cat => [
        cat.id,
        `"${(cat.name || '').replace(/"/g, '""')}"`,
        `"${(cat.parent_name || '').replace(/"/g, '""')}"`,
        `"${(cat.description || '').replace(/"/g, '""')}"`,
        cat.status,
        cat.product_count || 0
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

function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('categories-table').classList.toggle('hidden', show);
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
