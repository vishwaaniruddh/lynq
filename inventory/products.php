<?php
/**
 * Product Management Page
 * 
 * Lists products with category/type filters
 * Create/edit product forms with serializable/repairable toggles
 * 
 * Requirements: 2.1, 2.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission - ADV users always have access
if (!can('inventory.products.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view products';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Product Management';
$currentPage = 'inventory_products';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Products']
];

// Get user permissions - ADV users have all permissions
$isAdv = isAdvUser();
$canCreate = can('inventory.products.create') || $isAdv;
$canEdit = can('inventory.products.update') || $isAdv;
$canDelete = can('inventory.products.delete') || $isAdv;

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Product Management</h3>
            <p class="text-sm text-gray-500">Manage inventory products and materials</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($canCreate): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Product
            </button>
            <?php endif; ?>
            <button onclick="exportProducts()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search products..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="category-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div>
                <select id="type-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Types</option>
                    <option value="INTERNAL">Internal</option>
                    <option value="SITE">Site</option>
                </select>
            </div>
            <div>
                <select id="serializable-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Items</option>
                    <option value="1">Serializable</option>
                    <option value="0">Non-Serializable</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading products...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="products-table" class="w-full">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" data-sort="id">
                        # <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" data-sort="name">
                        Product <i class="fas fa-sort ml-1"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Attributes</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="products-tbody" class="divide-y">
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
<div id="product-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeProductModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Product</h3>
                <button onclick="closeProductModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="product-form" onsubmit="saveProduct(event)">
                <input type="hidden" id="product-id" value="">
                <div class="p-5 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div>
                        <label for="product-name" class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                        <input type="text" id="product-name" name="name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter product name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="product-category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select id="product-category" name="category_id"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div>
                            <label for="product-unit" class="block text-sm font-medium text-gray-700 mb-1">Unit of Measure <span class="text-red-500">*</span></label>
                            <input type="text" id="product-unit" name="unit_of_measure" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="e.g., pcs, kg, m">
                            <p id="unit_of_measure-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="product-type" class="block text-sm font-medium text-gray-700 mb-1">Inventory Type <span class="text-red-500">*</span></label>
                            <select id="product-type" name="inventory_type" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select Type</option>
                                <option value="INTERNAL">Internal (Office Use)</option>
                                <option value="SITE">Site (Project Use)</option>
                            </select>
                            <p id="inventory_type-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="product-threshold" class="block text-sm font-medium text-gray-700 mb-1">Low Stock Threshold</label>
                            <input type="number" id="product-threshold" name="low_stock_threshold" min="0" value="0"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" id="product-serializable" name="is_serializable" class="w-4 h-4 text-primary rounded">
                            <label for="product-serializable" class="ml-2 text-sm text-gray-700">
                                <span class="font-medium">Serializable</span>
                                <p class="text-xs text-gray-500">Track by serial number</p>
                            </label>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <input type="checkbox" id="product-repairable" name="is_repairable" class="w-4 h-4 text-primary rounded">
                            <label for="product-repairable" class="ml-2 text-sm text-gray-700">
                                <span class="font-medium">Repairable</span>
                                <p class="text-xs text-gray-500">Can be sent for repair</p>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label for="product-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="product-description" name="description" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeProductModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
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
                <h3 class="text-lg font-semibold text-gray-800">Product Details</h3>
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
    products: [],
    categories: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', category_id: '', inventory_type: '', is_serializable: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: {
        create: <?php echo json_encode($canCreate); ?>,
        edit: <?php echo json_encode($canEdit); ?>,
        delete: <?php echo json_encode($canDelete); ?>
    }
};

const API_URL = '../api/inventory/products';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
    loadProducts();
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
            loadProducts();
        }, 300);
    });
    
    document.getElementById('category-filter').addEventListener('change', function(e) {
        state.filters.category_id = e.target.value;
        state.pagination.page = 1;
        loadProducts();
    });
    
    document.getElementById('type-filter').addEventListener('change', function(e) {
        state.filters.inventory_type = e.target.value;
        state.pagination.page = 1;
        loadProducts();
    });
    
    document.getElementById('serializable-filter').addEventListener('change', function(e) {
        state.filters.is_serializable = e.target.value;
        state.pagination.page = 1;
        loadProducts();
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
            loadProducts();
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

// Load categories for dropdown
async function loadCategories() {
    try {
        const response = await fetch(`${API_URL}/categories.php`, { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.categories = data.data.flat_categories || [];
            populateCategoryDropdowns();
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function populateCategoryDropdowns() {
    const filterSelect = document.getElementById('category-filter');
    const formSelect = document.getElementById('product-category');
    
    const options = state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">All Categories</option>' + options;
    }
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Select Category</option>' + options;
    }
}

// Load products from API
async function loadProducts() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.category_id) params.append('category_id', state.filters.category_id);
        if (state.filters.inventory_type) params.append('inventory_type', state.filters.inventory_type);
        if (state.filters.is_serializable !== '') params.append('is_serializable', state.filters.is_serializable);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.products = data.data.products;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load products');
        }
    } catch (error) {
        console.error('Error loading products:', error);
        showError('Failed to load products. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render table
function renderTable() {
    const tbody = document.getElementById('products-tbody');
    
    if (state.products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-box text-4xl mb-3 text-gray-300"></i>
                    <p>No products found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.products.map((product, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 ${product.is_serializable ? 'bg-gradient-to-br from-purple-50 to-purple-100' : 'bg-gradient-to-br from-blue-50 to-blue-100'} rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas ${product.is_serializable ? 'fa-barcode' : 'fa-box'} ${product.is_serializable ? 'text-purple-500' : 'text-blue-500'} text-xs"></i>
                    </div>
                    <div>
                        <span class="font-medium text-xs text-gray-800">${escapeHtml(product.name)}</span>
                        <p class="text-[10px] text-gray-500">${escapeHtml(product.unit_of_measure)}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(product.category_name || '-')}</td>
            <td class="px-4 py-2.5">
                ${product.inventory_type === 'INTERNAL' 
                    ? '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">Internal</span>'
                    : '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-[10px]">Site</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center gap-1">
                    ${product.is_serializable ? '<span class="px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded text-[10px]" title="Serializable"><i class="fas fa-barcode"></i></span>' : ''}
                    ${product.is_repairable ? '<span class="px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded text-[10px]" title="Repairable"><i class="fas fa-tools"></i></span>' : ''}
                    ${!product.is_serializable && !product.is_repairable ? '<span class="text-gray-400 text-[10px]">-</span>' : ''}
                </div>
            </td>
            <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 ${(product.total_stock || 0) <= (product.low_stock_threshold || 0) ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'} rounded text-[10px]">
                    ${product.total_stock || 0} ${escapeHtml(product.unit_of_measure)}
                </span>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center space-x-1">
                    <button onclick="viewProduct(${product.id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${state.permissions.edit ? `
                    <button onclick="editProduct(${product.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                    ${state.permissions.delete && parseInt(product.total_stock || 0) === 0 ? `<button onclick="deleteProduct(${product.id})" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><i class="fas fa-trash text-xs"></i></button>` : ''}
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
    loadProducts();
}

// Modal functions
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Product';
    document.getElementById('product-id').value = '';
    document.getElementById('product-name').value = '';
    document.getElementById('product-category').value = '';
    document.getElementById('product-unit').value = '';
    document.getElementById('product-type').value = '';
    document.getElementById('product-threshold').value = '0';
    document.getElementById('product-serializable').checked = false;
    document.getElementById('product-repairable').checked = false;
    document.getElementById('product-description').value = '';
    clearErrors();
    document.getElementById('product-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editProduct(id) {
    const product = state.products.find(p => p.id === id);
    if (!product) return;
    
    document.getElementById('modal-title').textContent = 'Edit Product';
    document.getElementById('product-id').value = product.id;
    document.getElementById('product-name').value = product.name;
    document.getElementById('product-category').value = product.category_id || '';
    document.getElementById('product-unit').value = product.unit_of_measure;
    document.getElementById('product-type').value = product.inventory_type;
    document.getElementById('product-threshold').value = product.low_stock_threshold || 0;
    document.getElementById('product-serializable').checked = product.is_serializable == 1;
    document.getElementById('product-repairable').checked = product.is_repairable == 1;
    document.getElementById('product-description').value = product.description || '';
    clearErrors();
    document.getElementById('product-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeProductModal() {
    document.getElementById('product-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveProduct(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('product-id').value;
    const name = document.getElementById('product-name').value.trim();
    const categoryId = document.getElementById('product-category').value;
    const unitOfMeasure = document.getElementById('product-unit').value.trim();
    const inventoryType = document.getElementById('product-type').value;
    const lowStockThreshold = parseInt(document.getElementById('product-threshold').value) || 0;
    const isSerializable = document.getElementById('product-serializable').checked;
    const isRepairable = document.getElementById('product-repairable').checked;
    const description = document.getElementById('product-description').value.trim();
    
    if (!name) { showFieldError('name', 'Product name is required'); return; }
    if (!unitOfMeasure) { showFieldError('unit_of_measure', 'Unit of measure is required'); return; }
    if (!inventoryType) { showFieldError('inventory_type', 'Inventory type is required'); return; }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const endpoint = id ? `${API_URL}/update.php` : `${API_URL}/create.php`;
        const payload = {
            name, unit_of_measure: unitOfMeasure, inventory_type: inventoryType,
            low_stock_threshold: lowStockThreshold, is_serializable: isSerializable,
            is_repairable: isRepairable, description
        };
        if (categoryId) payload.category_id = parseInt(categoryId);
        if (id) payload.id = parseInt(id);
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeProductModal();
            showSuccess(data.message || 'Product saved successfully');
            loadProducts();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => showFieldError(field, data.errors[field]));
            } else {
                showError(data.error?.message || 'Failed to save product');
            }
        }
    } catch (error) {
        console.error('Error saving product:', error);
        showError('Failed to save product. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

// View product
function viewProduct(id) {
    const product = state.products.find(p => p.id === id);
    if (!product) return;
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 ${product.is_serializable ? 'bg-purple-100' : 'bg-blue-100'} rounded-2xl flex items-center justify-center">
                    <i class="fas ${product.is_serializable ? 'fa-barcode' : 'fa-box'} text-3xl ${product.is_serializable ? 'text-purple-500' : 'text-blue-500'}"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">ID</p>
                    <p class="font-medium">${product.id}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Type</p>
                    <p>${product.inventory_type === 'INTERNAL' 
                        ? '<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Internal</span>'
                        : '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Site</span>'
                    }</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Product Name</p>
                    <p class="font-medium">${escapeHtml(product.name)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Category</p>
                    <p class="font-medium">${escapeHtml(product.category_name || '-')}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Unit</p>
                    <p class="font-medium">${escapeHtml(product.unit_of_measure)}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Stock</p>
                    <p class="font-medium">${product.total_stock || 0}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Low Stock Threshold</p>
                    <p class="font-medium">${product.low_stock_threshold || 0}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Attributes</p>
                    <div class="flex gap-2 mt-1">
                        ${product.is_serializable ? '<span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs"><i class="fas fa-barcode mr-1"></i>Serializable</span>' : ''}
                        ${product.is_repairable ? '<span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs"><i class="fas fa-tools mr-1"></i>Repairable</span>' : ''}
                        ${!product.is_serializable && !product.is_repairable ? '<span class="text-gray-400 text-sm">None</span>' : ''}
                    </div>
                </div>
                ${product.description ? `
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Description</p>
                    <p class="font-medium">${escapeHtml(product.description)}</p>
                </div>
                ` : ''}
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

// Export products
async function exportProducts() {
    try {
        const params = new URLSearchParams({ limit: 1000 });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.category_id) params.append('category_id', state.filters.category_id);
        if (state.filters.inventory_type) params.append('inventory_type', state.filters.inventory_type);
        if (state.filters.is_serializable !== '') params.append('is_serializable', state.filters.is_serializable);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.products, 'products_export.csv');
            showSuccess('Products exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export products');
        }
    } catch (error) {
        console.error('Error exporting products:', error);
        showError('Failed to export products. Please try again.');
    }
}

function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'Category', 'Unit', 'Type', 'Serializable', 'Repairable', 'Stock', 'Threshold'];
    const rows = data.map(p => [
        p.id,
        `"${(p.name || '').replace(/"/g, '""')}"`,
        `"${(p.category_name || '').replace(/"/g, '""')}"`,
        `"${(p.unit_of_measure || '').replace(/"/g, '""')}"`,
        p.inventory_type,
        p.is_serializable ? 'Yes' : 'No',
        p.is_repairable ? 'Yes' : 'No',
        p.total_stock || 0,
        p.low_stock_threshold || 0
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

// Delete product
function deleteProduct(id) {
    const product = state.products.find(p => p.id === id);
    const name = product ? product.name : 'this product';
    showConfirmToast(`Delete "${name}"?`, 'This action cannot be undone.', () => confirmDeleteProduct(id));
}

async function confirmDeleteProduct(id) {
    try {
        const response = await fetch(`${API_URL}/delete.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'Product deleted successfully');
            loadProducts();
        } else {
            showError(data.error?.message || 'Failed to delete product');
        }
    } catch (error) {
        console.error('Error deleting product:', error);
        showError('Failed to delete product. Please try again.');
    }
}

function showConfirmToast(title, message, onConfirm) {
    const existing = document.getElementById('confirm-toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'confirm-toast';
    toast.className = 'fixed top-4 right-4 z-50 bg-white border border-gray-200 rounded-xl shadow-2xl p-5 max-w-sm';
    toast.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-trash text-red-500"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-semibold text-gray-800">${title}</h4>
                <p class="text-sm text-gray-500 mt-1">${message}</p>
                <div class="flex gap-2 mt-4">
                    <button onclick="document.getElementById('confirm-toast').remove()" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button id="confirm-delete-btn" class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Delete</button>
                </div>
            </div>
            <button onclick="document.getElementById('confirm-toast').remove()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
    `;
    document.body.appendChild(toast);
    document.getElementById('confirm-delete-btn').onclick = () => { toast.remove(); onConfirm(); };
}

// Utility functions
function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('products-table').classList.toggle('hidden', show);
}

function showError(message) { showToast(message, 'error'); }
function showSuccess(message) { showToast(message, 'success'); }

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
    if (errorEl) { errorEl.textContent = message; errorEl.classList.remove('hidden'); }
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
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
