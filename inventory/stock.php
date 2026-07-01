<?php
/**
 * Stock Entry Management Page
 * 
 * Single stock entry form
 * Bulk upload interface with validation feedback
 * 
 * Requirements: 3.1, 3.2, 4.1, 4.2
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission - stock entry requires create permission, ADV users always have access
if (!can('inventory.stock.create') && !can('inventory.stock.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view stock';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Stock Management';
$currentPage = 'inventory_stock';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Stock']
];

// Get user permissions - ADV users have all permissions
$isAdv = isAdvUser();
$canCreate = can('inventory.stock.create') || $isAdv;
$canBulkUpload = can('inventory.stock.bulk') || $isAdv;

ob_start();
?>

<div class="space-y-6">
    <!-- Stock Levels Section -->
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Stock Levels</h3>
                <p class="text-sm text-gray-500">View and manage inventory stock across warehouses</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($canCreate): ?>
                <button onclick="openStockEntryModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                    <i class="fas fa-plus mr-2"></i>Add Stock
                </button>
                <?php endif; ?>
                <?php if ($canBulkUpload): ?>
                <button onclick="openBulkUploadModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center">
                    <i class="fas fa-file-upload mr-2"></i>Bulk Upload
                </button>
                <?php endif; ?>
                <button onclick="exportStock()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
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
                    <select id="warehouse-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Warehouses</option>
                    </select>
                </div>
                <div>
                    <select id="category-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div>
                    <select id="stock-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Stock</option>
                        <option value="low">Low Stock Only</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading indicator -->
        <div id="loading-indicator" class="hidden p-8 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
            <p class="mt-2 text-gray-500">Loading stock...</p>
        </div>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table id="stock-table" class="w-full">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Warehouse</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Available</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Reserved</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="stock-tbody" class="divide-y">
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
</div>

<!-- Stock Entry Modal -->
<div id="stock-entry-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeStockEntryModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Add Stock Entry</h3>
                <button onclick="closeStockEntryModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="stock-entry-form" onsubmit="saveStockEntry(event)">
                <div class="p-5 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div>
                        <label for="entry-product" class="block text-sm font-medium text-gray-700 mb-1">Product <span class="text-red-500">*</span></label>
                        <select id="entry-product" name="product_id" required onchange="onProductChange()"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Product</option>
                        </select>
                        <p id="product_id-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="entry-warehouse" class="block text-sm font-medium text-gray-700 mb-1">Warehouse <span class="text-red-500">*</span></label>
                        <select id="entry-warehouse" name="warehouse_id" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Warehouse</option>
                        </select>
                        <p id="warehouse_id-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <!-- Quantity field (for non-serializable) -->
                    <div id="quantity-field">
                        <label for="entry-quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="text-red-500">*</span></label>
                        <input type="number" id="entry-quantity" name="quantity" min="1" value="1"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p id="quantity-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <!-- Serial number fields (for serializable) -->
                    <div id="serial-fields" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Serial Numbers <span class="text-red-500">*</span></label>
                        <div id="serial-numbers-container" class="space-y-2">
                            <div class="flex gap-2">
                                <input type="text" name="serial_numbers[]" placeholder="Enter serial number"
                                    class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <button type="button" onclick="addSerialNumberField()" class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Add multiple serial numbers for batch entry</p>
                        <p id="serial_number-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <!-- Warranty expiry (for serializable) -->
                    <div id="warranty-field" class="hidden">
                        <label for="entry-warranty" class="block text-sm font-medium text-gray-700 mb-1">Warranty Expiry</label>
                        <input type="date" id="entry-warranty" name="warranty_expiry"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="entry-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="entry-notes" name="notes" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeStockEntryModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-entry-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-save mr-2"></i>Add Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Bulk Upload Modal -->
<div id="bulk-upload-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeBulkUploadModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 sticky top-0 bg-white z-10">
                <h3 class="text-lg font-semibold text-gray-800">Bulk Stock Upload</h3>
                <button onclick="closeBulkUploadModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <!-- Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-medium text-blue-800 mb-2"><i class="fas fa-info-circle mr-2"></i>Instructions</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Download the template file and fill in your stock data</li>
                        <li>• For serializable products, enter one serial number per row</li>
                        <li>• For non-serializable products, enter the quantity</li>
                        <li>• All rows will be validated before processing</li>
                    </ul>
                </div>
                
                <!-- Template Columns Info -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-2"><i class="fas fa-columns mr-2"></i>Template Columns</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">warehouse_id <span class="text-red-500">*</span></span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">product_id <span class="text-red-500">*</span></span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">serial_number</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">quantity</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">is_repairable (0/1)</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                            <span class="text-gray-700">notes</span>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-500 mt-2"><span class="text-red-500">*</span> Required fields. Serial number required for serializable products, quantity for non-serializable.</p>
                </div>
                
                <!-- Download Template -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-800">Download Template</p>
                        <p class="text-sm text-gray-500">CSV template with required columns</p>
                    </div>
                    <button onclick="downloadBulkTemplate()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-download mr-2"></i>Download
                    </button>
                </div>
                
                <!-- File Upload -->
                <div id="upload-area" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary transition cursor-pointer"
                    onclick="document.getElementById('bulk-file-input').click()"
                    ondragover="handleDragOver(event)" ondrop="handleDrop(event)">
                    <input type="file" id="bulk-file-input" accept=".xlsx,.xls,.csv" class="hidden" onchange="handleFileSelect(event)">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600">Drag and drop your file here, or click to browse</p>
                    <p class="text-sm text-gray-400 mt-1">Supports: .xlsx, .xls, .csv</p>
                </div>
                
                <!-- Selected File Info -->
                <div id="selected-file-info" class="hidden p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-file-excel text-green-500 text-2xl mr-3"></i>
                            <div>
                                <p id="selected-file-name" class="font-medium text-gray-800"></p>
                                <p id="selected-file-size" class="text-sm text-gray-500"></p>
                            </div>
                        </div>
                        <button onclick="clearSelectedFile()" class="text-gray-400 hover:text-red-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Validation Results -->
                <div id="validation-results" class="hidden">
                    <div id="validation-success" class="hidden p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                            <div>
                                <p class="font-medium text-green-800">Validation Passed</p>
                                <p id="validation-success-msg" class="text-sm text-green-600"></p>
                            </div>
                        </div>
                    </div>
                    <div id="validation-errors" class="hidden p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3 mt-0.5"></i>
                            <div class="flex-1">
                                <p class="font-medium text-red-800">Validation Errors</p>
                                <p id="validation-error-summary" class="text-sm text-red-600 mb-2"></p>
                                <div id="validation-error-list" class="max-h-40 overflow-y-auto text-sm text-red-700 space-y-1"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Progress -->
                <div id="upload-progress" class="hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-600">Processing...</span>
                        <span id="progress-percent" class="text-sm font-medium text-primary">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="progress-bar" class="bg-primary h-2 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                </div>
                
                <!-- Upload Results -->
                <div id="upload-results" class="hidden p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-800 mb-3">Upload Results</h4>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="p-3 bg-white rounded-lg">
                            <p id="result-total" class="text-2xl font-bold text-gray-800">0</p>
                            <p class="text-sm text-gray-500">Total Rows</p>
                        </div>
                        <div class="p-3 bg-white rounded-lg">
                            <p id="result-success" class="text-2xl font-bold text-green-600">0</p>
                            <p class="text-sm text-gray-500">Successful</p>
                        </div>
                        <div class="p-3 bg-white rounded-lg">
                            <p id="result-errors" class="text-2xl font-bold text-red-600">0</p>
                            <p class="text-sm text-gray-500">Errors</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl sticky bottom-0">
                <button type="button" onclick="closeBulkUploadModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
                <button type="button" id="validate-btn" onclick="validateBulkUpload()" disabled class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check mr-2"></i>Validate
                </button>
                <button type="button" id="upload-btn" onclick="processBulkUpload()" disabled class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-upload mr-2"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    stock: [],
    warehouses: [],
    products: [],
    categories: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', warehouse_id: '', category_id: '', low_stock: '' },
    permissions: {
        create: <?php echo json_encode($canCreate); ?>,
        bulkUpload: <?php echo json_encode($canBulkUpload); ?>
    },
    bulkUpload: {
        file: null,
        parsedData: null,
        validated: false
    }
};

const API_URL = '../api/inventory/stock';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadWarehouses();
    loadProducts();
    loadCategories();
    loadStock();
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
            loadStock();
        }, 300);
    });
    
    document.getElementById('warehouse-filter').addEventListener('change', function(e) {
        state.filters.warehouse_id = e.target.value;
        state.pagination.page = 1;
        loadStock();
    });
    
    document.getElementById('category-filter').addEventListener('change', function(e) {
        state.filters.category_id = e.target.value;
        state.pagination.page = 1;
        loadStock();
    });
    
    document.getElementById('stock-filter').addEventListener('change', function(e) {
        state.filters.low_stock = e.target.value === 'low' ? 'true' : '';
        state.pagination.page = 1;
        loadStock();
    });
}

// Load warehouses for dropdown
async function loadWarehouses() {
    try {
        const response = await fetch('../api/inventory/warehouses/index.php?limit=100', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.warehouses = data.data.warehouses || [];
            populateWarehouseDropdowns();
        }
    } catch (error) {
        console.error('Error loading warehouses:', error);
    }
}

function populateWarehouseDropdowns() {
    const filterSelect = document.getElementById('warehouse-filter');
    const formSelect = document.getElementById('entry-warehouse');
    
    const options = state.warehouses
        .filter(w => w.status === 'active')
        .map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
    
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">All Warehouses</option>' + options;
    }
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Select Warehouse</option>' + options;
    }
}

// Load products for dropdown
async function loadProducts() {
    try {
        const response = await fetch('../api/inventory/products/index.php?limit=500', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.products = data.data.products || [];
            populateProductDropdown();
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function populateProductDropdown() {
    const formSelect = document.getElementById('entry-product');
    if (formSelect) {
        formSelect.innerHTML = '<option value="">Select Product</option>' +
            state.products.map(p => {
                const badge = p.is_serializable ? ' [Serializable]' : '';
                return `<option value="${p.id}" data-serializable="${p.is_serializable}">${escapeHtml(p.name)}${badge}</option>`;
            }).join('');
    }
}

// Load categories for dropdown
async function loadCategories() {
    try {
        const response = await fetch('../api/inventory/products/categories.php', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.categories = data.data.categories || [];
            populateCategoryDropdown();
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function populateCategoryDropdown() {
    const filterSelect = document.getElementById('category-filter');
    if (filterSelect) {
        filterSelect.innerHTML = '<option value="">All Categories</option>' +
            state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    }
}

// Load stock from API
async function loadStock() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.warehouse_id) params.append('warehouse_id', state.filters.warehouse_id);
        if (state.filters.category_id) params.append('category_id', state.filters.category_id);
        if (state.filters.low_stock) params.append('low_stock', state.filters.low_stock);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.stock = data.data.stock;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            
            // Apply client-side search filter
            if (state.filters.search) {
                const search = state.filters.search.toLowerCase();
                state.stock = state.stock.filter(s => 
                    (s.product_name || '').toLowerCase().includes(search) ||
                    (s.warehouse_name || '').toLowerCase().includes(search)
                );
            }
            
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load stock');
        }
    } catch (error) {
        console.error('Error loading stock:', error);
        showError('Failed to load stock. Please try again.');
    } finally {
        showLoading(false);
    }
}


// Render table
function renderTable() {
    const tbody = document.getElementById('stock-tbody');
    
    if (state.stock.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-boxes text-4xl mb-3 text-gray-300"></i>
                    <p>No stock found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.stock.map((stock, index) => {
        const isSerializable = stock.type === 'serializable';
        const isLowStock = stock.is_low_stock || (stock.quantity <= (stock.low_stock_threshold || 0));
        const available = isSerializable ? stock.quantity : (stock.available_quantity || stock.quantity);
        const reserved = isSerializable ? 0 : (stock.reserved_quantity || 0);
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 ${isSerializable ? 'bg-gradient-to-br from-purple-50 to-purple-100' : 'bg-gradient-to-br from-blue-50 to-blue-100'} rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas ${isSerializable ? 'fa-barcode' : 'fa-box'} ${isSerializable ? 'text-purple-500' : 'text-blue-500'} text-xs"></i>
                    </div>
                    <span class="font-medium text-xs text-gray-800">${escapeHtml(stock.product_name || '-')}</span>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(stock.warehouse_name || '-')}</td>
            <td class="px-4 py-2.5">
                ${isSerializable 
                    ? '<span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-[10px]">Serializable</span>'
                    : '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">Quantity</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <span class="font-semibold text-xs ${isLowStock ? 'text-red-600' : 'text-green-600'}">${available}</span>
                ${isSerializable && stock.total_assets ? `<span class="text-gray-400 text-[10px]">/ ${stock.total_assets}</span>` : ''}
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${reserved}</td>
            <td class="px-4 py-2.5">
                ${isLowStock 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[10px]"><i class="fas fa-exclamation-triangle mr-1"></i>Low Stock</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px]"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>In Stock</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center space-x-1">
                    ${state.permissions.create ? `
                    <button onclick="addStockForProduct(${stock.product_id}, ${stock.warehouse_id})" class="p-1.5 text-gray-400 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors" title="Add Stock">
                        <i class="fas fa-plus-circle text-xs"></i>
                    </button>
                    ` : ''}
                    ${isSerializable ? `
                    <button onclick="viewAssets(${stock.product_id}, ${stock.warehouse_id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Assets">
                        <i class="fas fa-list text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `}).join('');
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
    loadStock();
}

// Stock Entry Modal functions
function openStockEntryModal() {
    document.getElementById('stock-entry-form').reset();
    document.getElementById('serial-numbers-container').innerHTML = `
        <div class="flex gap-2">
            <input type="text" name="serial_numbers[]" placeholder="Enter serial number"
                class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            <button type="button" onclick="addSerialNumberField()" class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    `;
    showQuantityFields();
    clearErrors();
    document.getElementById('stock-entry-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeStockEntryModal() {
    document.getElementById('stock-entry-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function addStockForProduct(productId, warehouseId) {
    openStockEntryModal();
    document.getElementById('entry-product').value = productId;
    document.getElementById('entry-warehouse').value = warehouseId;
    onProductChange();
}

function onProductChange() {
    const select = document.getElementById('entry-product');
    const option = select.options[select.selectedIndex];
    const isSerializable = option && option.dataset.serializable === '1';
    
    if (isSerializable) {
        showSerialFields();
    } else {
        showQuantityFields();
    }
}

function showQuantityFields() {
    document.getElementById('quantity-field').classList.remove('hidden');
    document.getElementById('serial-fields').classList.add('hidden');
    document.getElementById('warranty-field').classList.add('hidden');
}

function showSerialFields() {
    document.getElementById('quantity-field').classList.add('hidden');
    document.getElementById('serial-fields').classList.remove('hidden');
    document.getElementById('warranty-field').classList.remove('hidden');
}

function addSerialNumberField() {
    const container = document.getElementById('serial-numbers-container');
    const div = document.createElement('div');
    div.className = 'flex gap-2';
    div.innerHTML = `
        <input type="text" name="serial_numbers[]" placeholder="Enter serial number"
            class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
        <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200">
            <i class="fas fa-minus"></i>
        </button>
    `;
    container.appendChild(div);
}

async function saveStockEntry(event) {
    event.preventDefault();
    clearErrors();
    
    const productId = document.getElementById('entry-product').value;
    const warehouseId = document.getElementById('entry-warehouse').value;
    const notes = document.getElementById('entry-notes').value.trim();
    
    if (!productId) { showFieldError('product_id', 'Product is required'); return; }
    if (!warehouseId) { showFieldError('warehouse_id', 'Warehouse is required'); return; }
    
    const product = state.products.find(p => p.id == productId);
    const isSerializable = product && product.is_serializable == 1;
    
    const payload = {
        product_id: parseInt(productId),
        warehouse_id: parseInt(warehouseId),
        notes: notes || undefined
    };
    
    if (isSerializable) {
        const serialInputs = document.querySelectorAll('input[name="serial_numbers[]"]');
        const serialNumbers = Array.from(serialInputs).map(i => i.value.trim()).filter(v => v);
        
        if (serialNumbers.length === 0) {
            showFieldError('serial_number', 'At least one serial number is required');
            return;
        }
        
        payload.serial_numbers = serialNumbers;
        
        const warranty = document.getElementById('entry-warranty').value;
        if (warranty) payload.warranty_expiry = warranty;
    } else {
        const quantity = parseInt(document.getElementById('entry-quantity').value);
        if (!quantity || quantity < 1) {
            showFieldError('quantity', 'Quantity must be at least 1');
            return;
        }
        payload.quantity = quantity;
    }
    
    const saveBtn = document.getElementById('save-entry-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const response = await fetch(`${API_URL}/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeStockEntryModal();
            showSuccess(data.message || 'Stock added successfully');
            loadStock();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => showFieldError(field, data.errors[field]));
            } else {
                showError(data.error?.message || 'Failed to add stock');
            }
        }
    } catch (error) {
        console.error('Error saving stock:', error);
        showError('Failed to add stock. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Add Stock';
    }
}


// Bulk Upload Modal functions
function openBulkUploadModal() {
    resetBulkUploadState();
    document.getElementById('bulk-upload-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeBulkUploadModal() {
    document.getElementById('bulk-upload-modal').classList.add('hidden');
    document.body.style.overflow = '';
    resetBulkUploadState();
}

function resetBulkUploadState() {
    state.bulkUpload = { file: null, parsedData: null, validated: false };
    document.getElementById('bulk-file-input').value = '';
    document.getElementById('selected-file-info').classList.add('hidden');
    document.getElementById('validation-results').classList.add('hidden');
    document.getElementById('validation-success').classList.add('hidden');
    document.getElementById('validation-errors').classList.add('hidden');
    document.getElementById('upload-progress').classList.add('hidden');
    document.getElementById('upload-results').classList.add('hidden');
    document.getElementById('validate-btn').disabled = true;
    document.getElementById('upload-btn').disabled = true;
}

function downloadBulkTemplate() {
    const headers = ['warehouse_id', 'product_id', 'serial_number', 'quantity', 'is_repairable', 'notes'];
    const sampleData = [
        ['1', '1', '', '10', '0', 'Sample non-serializable entry (quantity based)'],
        ['1', '2', 'SN-001', '1', '1', 'Sample serializable entry (repairable)'],
        ['1', '2', 'SN-002', '1', '1', 'Another serializable entry'],
        ['1', '3', 'ASSET-001', '1', '0', 'Non-repairable serializable item']
    ];
    
    const csv = [headers.join(','), ...sampleData.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'bulk_stock_template.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function handleDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('border-primary', 'bg-blue-50');
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('border-primary', 'bg-blue-50');
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
}

function handleFileSelect(event) {
    const files = event.target.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
}

function handleFile(file) {
    const validTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    const validExtensions = ['.csv', '.xls', '.xlsx'];
    
    const extension = '.' + file.name.split('.').pop().toLowerCase();
    if (!validExtensions.includes(extension)) {
        showError('Invalid file type. Please upload a CSV or Excel file.');
        return;
    }
    
    state.bulkUpload.file = file;
    state.bulkUpload.validated = false;
    
    document.getElementById('selected-file-name').textContent = file.name;
    document.getElementById('selected-file-size').textContent = formatFileSize(file.size);
    document.getElementById('selected-file-info').classList.remove('hidden');
    document.getElementById('validate-btn').disabled = false;
    document.getElementById('upload-btn').disabled = true;
    
    // Hide previous results
    document.getElementById('validation-results').classList.add('hidden');
    document.getElementById('upload-results').classList.add('hidden');
    
    // Parse file
    parseFile(file);
}

function clearSelectedFile() {
    resetBulkUploadState();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

async function parseFile(file) {
    const extension = file.name.split('.').pop().toLowerCase();
    
    if (extension === 'csv') {
        const text = await file.text();
        state.bulkUpload.parsedData = parseCSV(text);
    } else {
        // For Excel files, we'll send to server for parsing
        state.bulkUpload.parsedData = null;
    }
}

function parseCSV(text) {
    const lines = text.split('\n').filter(line => line.trim());
    if (lines.length < 2) return [];
    
    const headers = lines[0].split(',').map(h => h.trim().toLowerCase());
    const rows = [];
    
    for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',').map(v => v.trim());
        const row = { _row_number: i + 1 };
        
        headers.forEach((header, index) => {
            row[header] = values[index] || '';
        });
        
        rows.push(row);
    }
    
    return rows;
}

async function validateBulkUpload() {
    if (!state.bulkUpload.file) {
        showError('Please select a file first');
        return;
    }
    
    const validateBtn = document.getElementById('validate-btn');
    validateBtn.disabled = true;
    validateBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Validating...';
    
    try {
        let rows = state.bulkUpload.parsedData;
        
        // If no parsed data (Excel file), send file to server
        if (!rows) {
            // For now, show error - Excel parsing would need server-side support
            showError('Excel file parsing requires server-side processing. Please use CSV format.');
            return;
        }
        
        const response = await fetch(`${API_URL}/bulk.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ rows, validate_only: true })
        });
        
        const data = await response.json();
        
        document.getElementById('validation-results').classList.remove('hidden');
        
        if (data.success && data.data.validation.success) {
            document.getElementById('validation-success').classList.remove('hidden');
            document.getElementById('validation-errors').classList.add('hidden');
            document.getElementById('validation-success-msg').textContent = 
                `${data.data.validation.validCount} rows validated successfully`;
            
            state.bulkUpload.validated = true;
            document.getElementById('upload-btn').disabled = false;
        } else {
            document.getElementById('validation-success').classList.add('hidden');
            document.getElementById('validation-errors').classList.remove('hidden');
            
            const validation = data.data?.validation || data;
            document.getElementById('validation-error-summary').textContent = 
                `${validation.invalidCount || 0} rows have errors`;
            
            const errorList = document.getElementById('validation-error-list');
            const errors = validation.errors || [];
            errorList.innerHTML = errors.slice(0, 20).map(e => 
                `<p>Row ${e.row}: ${e.message}</p>`
            ).join('');
            
            if (errors.length > 20) {
                errorList.innerHTML += `<p class="font-medium">... and ${errors.length - 20} more errors</p>`;
            }
            
            state.bulkUpload.validated = false;
            document.getElementById('upload-btn').disabled = true;
        }
    } catch (error) {
        console.error('Error validating:', error);
        showError('Failed to validate file. Please try again.');
    } finally {
        validateBtn.disabled = false;
        validateBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Validate';
    }
}

async function processBulkUpload() {
    if (!state.bulkUpload.validated || !state.bulkUpload.parsedData) {
        showError('Please validate the file first');
        return;
    }
    
    const uploadBtn = document.getElementById('upload-btn');
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
    
    document.getElementById('upload-progress').classList.remove('hidden');
    updateProgress(10);
    
    try {
        updateProgress(30);
        
        const response = await fetch(`${API_URL}/bulk.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ rows: state.bulkUpload.parsedData })
        });
        
        updateProgress(70);
        
        const data = await response.json();
        
        updateProgress(100);
        
        document.getElementById('upload-progress').classList.add('hidden');
        document.getElementById('upload-results').classList.remove('hidden');
        
        const result = data.data?.result || {};
        document.getElementById('result-total').textContent = result.totalRows || 0;
        document.getElementById('result-success').textContent = result.successCount || 0;
        document.getElementById('result-errors').textContent = result.errorCount || 0;
        
        if (data.success) {
            showSuccess(result.message || 'Bulk upload completed successfully');
            loadStock();
        } else {
            showError(result.message || 'Bulk upload completed with errors');
        }
    } catch (error) {
        console.error('Error uploading:', error);
        showError('Failed to process upload. Please try again.');
        document.getElementById('upload-progress').classList.add('hidden');
    } finally {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
    }
}

function updateProgress(percent) {
    document.getElementById('progress-percent').textContent = percent + '%';
    document.getElementById('progress-bar').style.width = percent + '%';
}

// View assets for serializable products
function viewAssets(productId, warehouseId) {
    window.location.href = `assets.php?product_id=${productId}&warehouse_id=${warehouseId}`;
}

// Export stock
async function exportStock() {
    try {
        const params = new URLSearchParams({ limit: 1000 });
        if (state.filters.warehouse_id) params.append('warehouse_id', state.filters.warehouse_id);
        if (state.filters.category_id) params.append('category_id', state.filters.category_id);
        if (state.filters.low_stock) params.append('low_stock', state.filters.low_stock);
        
        const response = await fetch(`${API_URL}/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            downloadStockCSV(data.data.stock, 'stock_export.csv');
            showSuccess('Stock exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export stock');
        }
    } catch (error) {
        console.error('Error exporting stock:', error);
        showError('Failed to export stock. Please try again.');
    }
}

function downloadStockCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['Product', 'Warehouse', 'Type', 'Available', 'Reserved', 'Total', 'Status'];
    const rows = data.map(s => [
        `"${(s.product_name || '').replace(/"/g, '""')}"`,
        `"${(s.warehouse_name || '').replace(/"/g, '""')}"`,
        s.type === 'serializable' ? 'Serializable' : 'Quantity',
        s.type === 'serializable' ? s.quantity : (s.available_quantity || s.quantity),
        s.type === 'serializable' ? 0 : (s.reserved_quantity || 0),
        s.type === 'serializable' ? (s.total_assets || s.quantity) : s.quantity,
        s.is_low_stock ? 'Low Stock' : 'In Stock'
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
    document.getElementById('stock-table').classList.toggle('hidden', show);
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

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.classList.add('hidden');
        el.textContent = '';
    });
}

function showFieldError(field, message) {
    const errorEl = document.getElementById(`${field}-error`);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
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
include __DIR__ . '/../views/layouts/main.php';
?>
