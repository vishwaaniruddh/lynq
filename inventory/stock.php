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
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

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
        <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
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
                        <li>• Maximum <strong>1,000 rows</strong> allowed per upload</li>
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
                    <p class="text-[11px] text-indigo-600 font-semibold mt-1.5"><i class="fas fa-info-circle mr-1"></i>Active Warehouse ID: <span class="font-bold underline">27260</span> (Mumbai Main HUB) | Product ID: <span class="font-bold underline">1</span> (Router ADV 50)</p>
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
                <div id="validation-results" class="hidden space-y-4">
                    <!-- Status Alerts -->
                    <div id="validation-success" class="hidden p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-emerald-500 text-xl mr-3"></i>
                                <div>
                                    <p class="font-semibold text-emerald-800">Validation Passed</p>
                                    <p id="validation-success-msg" class="text-sm text-emerald-600"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="validation-errors" class="hidden p-4 bg-red-50 border border-red-200 rounded-xl">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3 mt-0.5"></i>
                                <div>
                                    <p class="font-semibold text-red-800">Validation Failed</p>
                                    <p id="validation-error-summary" class="text-sm text-red-600"></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="removeFailedBulkRows()" class="px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs font-semibold shadow-sm transition flex items-center gap-1.5 whitespace-nowrap">
                                    <i class="fas fa-trash-alt"></i> Remove Failed Rows
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table Toolbar (Counts & Tabs) -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-gray-100">
                        <div class="flex items-center gap-2" id="bulk-filter-tabs">
                            <button type="button" onclick="setBulkFilterTab('all')" id="tab-btn-all" class="px-3 py-1 rounded-lg text-xs font-medium bg-gray-900 text-white shadow-sm transition">
                                All Rows (<span id="count-all">0</span>)
                            </button>
                            <button type="button" onclick="setBulkFilterTab('failed')" id="tab-btn-failed" class="px-3 py-1 rounded-lg text-xs font-medium bg-gray-100 text-red-600 hover:bg-red-50 transition">
                                Failed (<span id="count-failed">0</span>)
                            </button>
                            <button type="button" onclick="setBulkFilterTab('passed')" id="tab-btn-passed" class="px-3 py-1 rounded-lg text-xs font-medium bg-gray-100 text-emerald-600 hover:bg-emerald-50 transition">
                                Passed (<span id="count-passed">0</span>)
                            </button>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span id="bulk-rows-subtext">Delete invalid row(s) to enable upload for remaining valid rows</span>
                        </div>
                    </div>
                    
                    <!-- Rows Table Container -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="max-h-[340px] overflow-y-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-gray-50/90 sticky top-0 z-10 border-b border-gray-200 text-gray-600 font-semibold uppercase tracking-wider text-[10px]">
                                    <tr>
                                        <th class="px-3 py-2 w-14 text-center">Row</th>
                                        <th class="px-3 py-2">Warehouse</th>
                                        <th class="px-3 py-2">Product</th>
                                        <th class="px-3 py-2">Serial / Qty</th>
                                        <th class="px-3 py-2 w-20 text-center">Status</th>
                                        <th class="px-3 py-2">Validation Note / Error</th>
                                        <th class="px-3 py-2 w-20 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="bulk-rows-tbody" class="divide-y divide-gray-100 bg-white">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
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
                    <div id="upload-result-message" class="hidden mb-3 p-3 text-xs rounded-lg font-medium"></div>
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
        parsedData: [],
        validationErrors: {},
        activeTab: 'all',
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
let serialCheckDebounceTimer = null;

function openStockEntryModal() {
    document.getElementById('stock-entry-form').reset();
    document.getElementById('serial-numbers-container').innerHTML = `
        <div class="serial-input-row">
            <div class="flex gap-2">
                <input type="text" name="serial_numbers[]" placeholder="Enter serial number"
                    oninput="handleSerialInput(this)" onblur="checkSerialOnServer(this)"
                    class="serial-input flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
                <button type="button" onclick="addSerialNumberField()" class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="serial-feedback text-xs mt-1 hidden"></div>
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
    div.className = 'serial-input-row';
    div.innerHTML = `
        <div class="flex gap-2">
            <input type="text" name="serial_numbers[]" placeholder="Enter serial number"
                oninput="handleSerialInput(this)" onblur="checkSerialOnServer(this)"
                class="serial-input flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition">
            <button type="button" onclick="removeSerialNumberField(this)" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        <div class="serial-feedback text-xs mt-1 hidden"></div>
    `;
    container.appendChild(div);
    const newInput = div.querySelector('input');
    if (newInput) newInput.focus();
}

function removeSerialNumberField(btn) {
    const row = btn.closest('.serial-input-row');
    if (row) {
        row.remove();
        revalidateAllSerials();
    }
}

function handleSerialInput(inputEl) {
    clearTimeout(serialCheckDebounceTimer);
    revalidateAllSerials();
    
    serialCheckDebounceTimer = setTimeout(() => {
        checkAllSerialsOnServer();
    }, 400);
}

function revalidateAllSerials() {
    const rows = document.querySelectorAll('#serial-numbers-container .serial-input-row');
    const values = [];
    const counts = {};
    
    rows.forEach(row => {
        const input = row.querySelector('.serial-input');
        if (input) {
            const val = input.value.trim();
            if (val) {
                values.push(val);
                counts[val] = (counts[val] || 0) + 1;
            }
        }
    });
    
    let hasDuplicate = false;
    
    rows.forEach(row => {
        const input = row.querySelector('.serial-input');
        const feedback = row.querySelector('.serial-feedback');
        if (!input || !feedback) return;
        
        const val = input.value.trim();
        if (!val) {
            input.classList.remove('border-red-500', 'bg-red-50/50', 'text-red-800', 'border-emerald-500', 'bg-emerald-50/30');
            feedback.classList.add('hidden');
            feedback.innerHTML = '';
            return;
        }
        
        if (counts[val] > 1) {
            hasDuplicate = true;
            input.classList.remove('border-emerald-500', 'bg-emerald-50/30');
            input.classList.add('border-red-500', 'bg-red-50/50', 'text-red-800');
            feedback.classList.remove('hidden');
            feedback.innerHTML = '<span class="text-red-600 font-medium flex items-center"><i class="fas fa-exclamation-triangle mr-1"></i>Duplicate serial number entered in form</span>';
        } else {
            // Keep normal or existing status if not form duplicate
            if (feedback.innerHTML.includes('Duplicate serial number entered in form')) {
                input.classList.remove('border-red-500', 'bg-red-50/50', 'text-red-800');
                feedback.classList.add('hidden');
                feedback.innerHTML = '';
            }
        }
    });
    
    const generalErr = document.getElementById('serial_number-error');
    if (generalErr) {
        if (hasDuplicate) {
            generalErr.textContent = 'Duplicate serial numbers found. Each serial number must be unique.';
            generalErr.classList.remove('hidden');
        } else if (generalErr.textContent.includes('Duplicate serial numbers')) {
            generalErr.textContent = '';
            generalErr.classList.add('hidden');
        }
    }
    
    return !hasDuplicate;
}

async function checkSerialOnServer(inputEl) {
    if (!inputEl) return;
    const row = inputEl.closest('.serial-input-row');
    if (!row) return;
    const feedback = row.querySelector('.serial-feedback');
    const val = inputEl.value.trim();
    
    if (!val) return;
    
    // If it's already flagged as form duplicate, don't overwrite with server message
    if (feedback && feedback.innerHTML.includes('Duplicate serial number entered in form')) {
        return;
    }
    
    try {
        const response = await fetch(`../api/inventory/assets/check-serial.php?serial_number=${encodeURIComponent(val)}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success && data.data) {
            const existingList = (data.data.existing || []).map(s => String(s).trim().toLowerCase());
            const isExisting = existingList.includes(val.toLowerCase());
            
            if (isExisting) {
                inputEl.classList.remove('border-emerald-500', 'bg-emerald-50/30');
                inputEl.classList.add('border-red-500', 'bg-red-50/50', 'text-red-800');
                if (feedback) {
                    feedback.classList.remove('hidden');
                    feedback.innerHTML = `<span class="text-red-600 font-medium flex items-center"><i class="fas fa-times-circle mr-1"></i>Serial number already registered in inventory</span>`;
                }
            } else {
                inputEl.classList.remove('border-red-500', 'bg-red-50/50', 'text-red-800');
                inputEl.classList.add('border-emerald-500', 'bg-emerald-50/30');
                if (feedback) {
                    feedback.classList.remove('hidden');
                    feedback.innerHTML = `<span class="text-emerald-600 font-medium flex items-center"><i class="fas fa-check-circle mr-1"></i>Unique & Available</span>`;
                }
            }
        }
    } catch (e) {
        console.error('Serial check error:', e);
    }
}

async function checkAllSerialsOnServer() {
    const rows = document.querySelectorAll('#serial-numbers-container .serial-input-row');
    const cleanSerials = [];
    
    rows.forEach(row => {
        const input = row.querySelector('.serial-input');
        const feedback = row.querySelector('.serial-feedback');
        if (input) {
            const val = input.value.trim();
            if (val && (!feedback || !feedback.innerHTML.includes('Duplicate serial number entered in form'))) {
                cleanSerials.push(val);
            }
        }
    });
    
    if (cleanSerials.length === 0) return;
    
    try {
        const response = await fetch('../api/inventory/assets/check-serial.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ serial_numbers: cleanSerials })
        });
        const data = await response.json();
        
        if (data.success && data.data) {
            const existingList = (data.data.existing || []).map(s => String(s).trim().toLowerCase());
            
            rows.forEach(row => {
                const input = row.querySelector('.serial-input');
                const feedback = row.querySelector('.serial-feedback');
                if (!input || !feedback) return;
                
                const val = input.value.trim();
                if (!val) return;
                
                if (feedback.innerHTML.includes('Duplicate serial number entered in form')) return;
                
                if (existingList.includes(val.toLowerCase())) {
                    input.classList.remove('border-emerald-500', 'bg-emerald-50/30');
                    input.classList.add('border-red-500', 'bg-red-50/50', 'text-red-800');
                    feedback.classList.remove('hidden');
                    feedback.innerHTML = `<span class="text-red-600 font-medium flex items-center"><i class="fas fa-times-circle mr-1"></i>Serial number already registered in inventory</span>`;
                } else {
                    input.classList.remove('border-red-500', 'bg-red-50/50', 'text-red-800');
                    input.classList.add('border-emerald-500', 'bg-emerald-50/30');
                    feedback.classList.remove('hidden');
                    feedback.innerHTML = `<span class="text-emerald-600 font-medium flex items-center"><i class="fas fa-check-circle mr-1"></i>Unique & Available</span>`;
                }
            });
        }
    } catch (e) {
        console.error('Batch serial check error:', e);
    }
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
        
        // 1. Client-side form duplicate check
        const isUniqueInForm = revalidateAllSerials();
        if (!isUniqueInForm) {
            showError('Duplicate serial numbers detected. Each serial number must be unique.');
            return;
        }
        
        // 2. Pre-save database existence check
        const saveBtn = document.getElementById('save-entry-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Validating serials...';
        
        try {
            const checkRes = await fetch('../api/inventory/assets/check-serial.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ serial_numbers: serialNumbers })
            });
            const checkData = await checkRes.json();
            
            if (checkData.success && checkData.data) {
                if (checkData.data.duplicates_in_input && checkData.data.duplicates_in_input.length > 0) {
                    revalidateAllSerials();
                    showError(`Duplicate serials entered: ${checkData.data.duplicates_in_input.join(', ')}`);
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Add Stock';
                    return;
                }
                
                const existingList = (checkData.data.existing || []).map(s => String(s).trim());
                if (existingList.length > 0) {
                    const rows = document.querySelectorAll('#serial-numbers-container .serial-input-row');
                    rows.forEach(row => {
                        const input = row.querySelector('.serial-input');
                        const feedback = row.querySelector('.serial-feedback');
                        if (input && feedback) {
                            const val = input.value.trim();
                            if (existingList.some(e => e.toLowerCase() === val.toLowerCase())) {
                                input.classList.remove('border-emerald-500', 'bg-emerald-50/30');
                                input.classList.add('border-red-500', 'bg-red-50/50', 'text-red-800');
                                feedback.classList.remove('hidden');
                                feedback.innerHTML = `<span class="text-red-600 font-medium flex items-center"><i class="fas fa-times-circle mr-1"></i>Serial number already registered in inventory</span>`;
                            }
                        }
                    });
                    showError(`Serial number(s) already registered in inventory: ${existingList.join(', ')}`);
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Add Stock';
                    return;
                }
            }
        } catch (e) {
            console.warn('Pre-check error, proceeding to backend validation:', e);
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
            const fieldErrors = data.error?.details || data.errors;
            if (fieldErrors && typeof fieldErrors === 'object') {
                Object.keys(fieldErrors).forEach(field => showFieldError(field, fieldErrors[field]));
            }
            showError(data.error?.message || data.message || 'Failed to add stock');
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
    state.bulkUpload = { 
        file: null, 
        parsedData: [], 
        validationErrors: {}, 
        activeTab: 'all', 
        validated: false 
    };
    document.getElementById('bulk-file-input').value = '';
    document.getElementById('selected-file-info').classList.add('hidden');
    document.getElementById('validation-results').classList.add('hidden');
    document.getElementById('validation-success').classList.add('hidden');
    document.getElementById('validation-errors').classList.add('hidden');
    document.getElementById('upload-progress').classList.add('hidden');
    document.getElementById('upload-results').classList.add('hidden');
    const resMsg = document.getElementById('upload-result-message');
    if (resMsg) {
        resMsg.className = 'hidden mb-3 p-3 text-xs rounded-lg font-medium';
        resMsg.textContent = '';
    }
    document.getElementById('validate-btn').disabled = true;
    document.getElementById('upload-btn').disabled = true;
    document.getElementById('upload-btn').innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
    const tbody = document.getElementById('bulk-rows-tbody');
    if (tbody) tbody.innerHTML = '';
}

function downloadBulkTemplate() {
    const warehouseId = (state.warehouses && state.warehouses.length > 0) ? state.warehouses[0].id : '27260';
    const warehouseName = (state.warehouses && state.warehouses.length > 0) ? state.warehouses[0].name : 'Mumbai Main HUB';
    const headers = ['warehouse_id', 'product_id', 'serial_number', 'quantity', 'is_repairable', 'notes'];
    const sampleData = [
        [warehouseId, '1', 'ADV-ROUTER-001', '1', '1', `Router ADV 50 in ${warehouseName}`],
        [warehouseId, '1', 'ADV-ROUTER-002', '1', '1', `Router ADV 50 in ${warehouseName}`]
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
    const validExtensions = ['.csv', '.xls', '.xlsx'];
    const extension = '.' + file.name.split('.').pop().toLowerCase();
    if (!validExtensions.includes(extension)) {
        showError('Invalid file type. Please upload a CSV or Excel file.');
        return;
    }
    
    state.bulkUpload.file = file;
    state.bulkUpload.validated = false;
    state.bulkUpload.validationErrors = {};
    state.bulkUpload.activeTab = 'all';
    
    document.getElementById('selected-file-name').textContent = file.name;
    document.getElementById('selected-file-size').textContent = `${formatFileSize(file.size)} • Reading rows...`;
    document.getElementById('selected-file-info').classList.remove('hidden');
    document.getElementById('validate-btn').disabled = true;
    document.getElementById('upload-btn').disabled = true;
    document.getElementById('upload-btn').innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
    
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
    const extension = '.' + file.name.split('.').pop().toLowerCase();
    
    try {
        if (extension === '.csv') {
            const text = await file.text();
            state.bulkUpload.parsedData = parseCSV(text);
        } else if (['.xlsx', '.xls'].includes(extension) && typeof XLSX !== 'undefined') {
            const buffer = await file.arrayBuffer();
            const workbook = XLSX.read(buffer, { type: 'array' });
            const sheet = workbook.Sheets[workbook.SheetNames[0]];
            const rawJson = XLSX.utils.sheet_to_json(sheet, { defval: '' });
            state.bulkUpload.parsedData = rawJson.map((row, idx) => {
                const norm = { _row_number: idx + 2 };
                Object.keys(row).forEach(k => {
                    norm[k.trim().toLowerCase()] = String(row[k]).trim();
                });
                return norm;
            });
        } else if (['.xlsx', '.xls'].includes(extension)) {
            showError('Excel parsing library not loaded. Please upload CSV format.');
            return;
        }
        
        state.bulkUpload.validationErrors = {};
        state.bulkUpload.activeTab = 'all';
        state.bulkUpload.validated = false;
        
        const count = (state.bulkUpload.parsedData || []).length;
        const sizeText = `${formatFileSize(file.size)} • ${count} row${count === 1 ? '' : 's'} detected`;
        
        if (count > 1000) {
            document.getElementById('selected-file-size').innerHTML = 
                `${sizeText} • <span class="text-red-600 font-semibold"><i class="fas fa-exclamation-circle mr-1"></i>Exceeds limit of 1,000 rows</span>`;
            document.getElementById('validate-btn').disabled = true;
            document.getElementById('upload-btn').disabled = true;
            
            document.getElementById('validation-results').classList.remove('hidden');
            document.getElementById('validation-success').classList.add('hidden');
            document.getElementById('validation-errors').classList.remove('hidden');
            document.getElementById('validation-error-summary').innerHTML = 
                `This file contains <strong>${count}</strong> rows. Maximum <strong>1,000 rows</strong> allowed per upload (${count - 1000} rows over limit). Please reduce rows or split into smaller files.`;
            
            showError(`Maximum 1,000 rows allowed per upload. File contains ${count} rows.`);
        } else {
            document.getElementById('selected-file-size').textContent = sizeText;
            document.getElementById('validate-btn').disabled = count === 0;
            document.getElementById('upload-btn').disabled = true;
        }
    } catch (err) {
        console.error('Error parsing file:', err);
        showError('Failed to parse file: ' + err.message);
    }
}

function extractApiErrorMessage(data, fallback = 'Operation failed') {
    if (!data) return fallback;
    if (data.error) {
        if (typeof data.error.details === 'object' && data.error.details !== null) {
            const vals = Object.values(data.error.details).filter(Boolean);
            if (vals.length > 0) {
                return vals.join('; ');
            }
        } else if (typeof data.error.details === 'string' && data.error.details.trim()) {
            return data.error.details;
        }
        if (data.error.message) return data.error.message;
    }
    if (data.message) return data.message;
    return fallback;
}

function parseCSVLine(text) {
    const result = [];
    let cur = '';
    let inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (c === '"') {
            if (inQuotes && text[i + 1] === '"') {
                cur += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (c === ',' && !inQuotes) {
            result.push(cur.trim());
            cur = '';
        } else {
            cur += c;
        }
    }
    result.push(cur.trim());
    return result;
}

function parseCSV(text) {
    const lines = text.split(/\r?\n/).filter(line => line.trim());
    if (lines.length < 2) return [];
    
    const headers = parseCSVLine(lines[0]).map(h => h.replace(/^["']|["']$/g, '').trim().toLowerCase());
    const rows = [];
    
    for (let i = 1; i < lines.length; i++) {
        const values = parseCSVLine(lines[i]);
        if (values.length === 0 || (values.length === 1 && !values[0])) continue;
        
        const row = { _row_number: i + 1 };
        headers.forEach((header, index) => {
            let val = values[index] !== undefined ? values[index] : '';
            val = val.replace(/^["']|["']$/g, '').trim();
            row[header] = val;
        });
        
        rows.push(row);
    }
    
    return rows;
}

async function validateBulkUpload() {
    if (!state.bulkUpload.file || !state.bulkUpload.parsedData || state.bulkUpload.parsedData.length === 0) {
        showError('Please select a file with valid rows first');
        return;
    }
    
    const rows = state.bulkUpload.parsedData;
    if (rows.length > 1000) {
        showError(`Maximum 1,000 rows allowed per upload. Your file has ${rows.length} rows.`);
        document.getElementById('validation-results').classList.remove('hidden');
        document.getElementById('validation-success').classList.add('hidden');
        document.getElementById('validation-errors').classList.remove('hidden');
        document.getElementById('validation-error-summary').innerHTML = 
            `File has <strong>${rows.length}</strong> rows. Maximum <strong>1,000 rows</strong> allowed per upload. Please split the file into batches of 1,000 rows or fewer.`;
        return;
    }
    
    const validateBtn = document.getElementById('validate-btn');
    validateBtn.disabled = true;
    validateBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Validating...';
    
    try {
        const response = await fetch(`${API_URL}/bulk.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ rows, validate_only: true })
        });
        
        const data = await response.json();
        
        // Handle server-level validation errors (e.g. max rows exceeded, unauthorized warehouse, invalid format)
        if (!response.ok || !data.success) {
            const errorMsg = extractApiErrorMessage(data, 'Validation failed');
            showError(errorMsg);
            
            document.getElementById('validation-results').classList.remove('hidden');
            document.getElementById('validation-success').classList.add('hidden');
            document.getElementById('validation-errors').classList.remove('hidden');
            document.getElementById('validation-error-summary').innerHTML = 
                `<strong>Validation Failed:</strong> ${escapeHtml(errorMsg)}`;
            
            state.bulkUpload.validated = false;
            const uploadBtn = document.getElementById('upload-btn');
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
            return;
        }
        
        document.getElementById('validation-results').classList.remove('hidden');
        
        const validation = data.data?.validation || data.validation || {};
        let errors = validation.errors || {};
        
        if (Array.isArray(errors)) {
            const errObj = {};
            errors.forEach(e => {
                const rNum = String(e.row || e.row_number || e._row_number || '');
                if (!errObj[rNum]) errObj[rNum] = [];
                errObj[rNum].push(e);
            });
            errors = errObj;
        }
        
        state.bulkUpload.validationErrors = errors;
        const hasErrors = Object.keys(errors).length > 0;
        state.bulkUpload.activeTab = hasErrors ? 'failed' : 'all';
        state.bulkUpload.validated = !hasErrors;
        
        updateBulkValidationState();
        
    } catch (error) {
        console.error('Error validating:', error);
        showError('Failed to validate file: ' + error.message);
    } finally {
        validateBtn.disabled = false;
        validateBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Validate';
    }
}

function updateBulkValidationState() {
    const allRows = state.bulkUpload.parsedData || [];
    const total = allRows.length;
    
    // Filter active errors for existing rows only
    const validRowNums = new Set(allRows.map(r => String(r._row_number)));
    const activeErrors = {};
    Object.keys(state.bulkUpload.validationErrors || {}).forEach(k => {
        if (validRowNums.has(String(k))) {
            activeErrors[k] = state.bulkUpload.validationErrors[k];
        }
    });
    state.bulkUpload.validationErrors = activeErrors;
    
    const invalidCount = Object.keys(activeErrors).length;
    const validCount = Math.max(0, total - invalidCount);
    
    // Update badge counts in tabs
    const countAllEl = document.getElementById('count-all');
    const countFailedEl = document.getElementById('count-failed');
    const countPassedEl = document.getElementById('count-passed');
    if (countAllEl) countAllEl.textContent = total;
    if (countFailedEl) countFailedEl.textContent = invalidCount;
    if (countPassedEl) countPassedEl.textContent = validCount;
    
    const uploadBtn = document.getElementById('upload-btn');
    const validateBtn = document.getElementById('validate-btn');
    
    if (total === 0) {
        state.bulkUpload.validated = false;
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
        if (validateBtn) validateBtn.disabled = true;
        document.getElementById('validation-success').classList.add('hidden');
        document.getElementById('validation-errors').classList.add('hidden');
    } else if (total > 1000) {
        state.bulkUpload.validated = false;
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload (Limit: 1,000 Rows Max)';
        if (validateBtn) validateBtn.disabled = true;
        document.getElementById('validation-success').classList.add('hidden');
        document.getElementById('validation-errors').classList.remove('hidden');
        document.getElementById('validation-error-summary').innerHTML = 
            `File has <strong>${total}</strong> rows. Maximum <strong>1,000 rows</strong> allowed per upload (${total - 1000} rows over limit). Please delete excess rows below or split into smaller files.`;
    } else if (invalidCount === 0 && state.bulkUpload.validated) {
        // 100% valid!
        if (validateBtn) validateBtn.disabled = false;
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = `<i class="fas fa-upload mr-2"></i>Upload ${validCount} Valid Row${validCount === 1 ? '' : 's'}`;
        
        document.getElementById('validation-success').classList.remove('hidden');
        document.getElementById('validation-errors').classList.add('hidden');
        document.getElementById('validation-success-msg').innerHTML = 
            `All <strong>${validCount}</strong> row${validCount === 1 ? '' : 's'} passed validation and are ready to be uploaded!`;
            
        if (state.bulkUpload.activeTab === 'failed') {
            state.bulkUpload.activeTab = 'all';
        }
    } else {
        // Has failed rows or not yet validated
        if (validateBtn) validateBtn.disabled = false;
        state.bulkUpload.validated = false;
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
        
        document.getElementById('validation-success').classList.add('hidden');
        document.getElementById('validation-errors').classList.remove('hidden');
        document.getElementById('validation-error-summary').innerHTML = 
            `Found <strong>${invalidCount}</strong> invalid row${invalidCount === 1 ? '' : 's'} and <strong>${validCount}</strong> valid row${validCount === 1 ? '' : 's'}. Delete the failed row(s) below or click <strong>Remove Failed Rows</strong> to upload the remaining ${validCount} valid row${validCount === 1 ? '' : 's'}.`;
    }
    
    setBulkFilterTab(state.bulkUpload.activeTab || 'all');
}

function setBulkFilterTab(tab) {
    state.bulkUpload.activeTab = tab;
    
    ['all', 'failed', 'passed'].forEach(t => {
        const btn = document.getElementById(`tab-btn-${t}`);
        if (!btn) return;
        if (t === tab) {
            btn.className = 'px-3 py-1 rounded-lg text-xs font-semibold bg-gray-900 text-white shadow-sm transition';
        } else {
            const colorClass = t === 'failed' ? 'text-red-600 hover:bg-red-50' : (t === 'passed' ? 'text-emerald-600 hover:bg-emerald-50' : 'text-gray-600 hover:bg-gray-100');
            btn.className = `px-3 py-1 rounded-lg text-xs font-medium bg-gray-100 ${colorClass} transition`;
        }
    });
    
    renderBulkRowsTable();
}

function renderBulkRowsTable() {
    const tbody = document.getElementById('bulk-rows-tbody');
    if (!tbody) return;
    
    const allRows = state.bulkUpload.parsedData || [];
    const errors = state.bulkUpload.validationErrors || {};
    
    let displayRows = allRows;
    if (state.bulkUpload.activeTab === 'failed') {
        displayRows = allRows.filter(r => {
            const errs = errors[String(r._row_number)];
            return errs && (Array.isArray(errs) ? errs.length > 0 : true);
        });
    } else if (state.bulkUpload.activeTab === 'passed') {
        displayRows = allRows.filter(r => {
            const errs = errors[String(r._row_number)];
            return !errs || (Array.isArray(errs) && errs.length === 0);
        });
    }
    
    if (displayRows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    <p class="text-sm font-medium">No rows in this tab</p>
                    <p class="text-xs text-gray-400 mt-1">${state.bulkUpload.activeTab === 'failed' ? 'Great! No invalid rows remain.' : 'No rows match filter'}</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = displayRows.map((row) => {
        const rowNumStr = String(row._row_number);
        const rowErrors = errors[rowNumStr];
        const isFailed = rowErrors && (Array.isArray(rowErrors) ? rowErrors.length > 0 : true);
        
        let errorMsg = '';
        if (isFailed) {
            if (Array.isArray(rowErrors)) {
                errorMsg = rowErrors.map(e => e.message || JSON.stringify(e)).join('; ');
            } else if (typeof rowErrors === 'object') {
                errorMsg = rowErrors.message || JSON.stringify(rowErrors);
            } else {
                errorMsg = String(rowErrors);
            }
        }
        
        const serialOrQty = row.serial_number ? 
            `<span class="font-mono text-gray-800 font-medium">${escapeHtml(row.serial_number)}</span>` : 
            (row.quantity ? `<span class="text-gray-700">${escapeHtml(row.quantity)} units</span>` : '-');
            
        const product = state.products.find(p => String(p.id) === String(row.product_id));
        const productDisplay = product ? 
            `<span class="font-medium text-gray-800">${escapeHtml(product.name)}</span> <span class="text-gray-400 font-mono text-[10px]">(#${row.product_id})</span>` :
            `<span class="font-mono text-gray-700">#${escapeHtml(row.product_id)}</span>`;
            
        const warehouse = state.warehouses.find(w => String(w.id) === String(row.warehouse_id));
        const warehouseDisplay = warehouse ? 
            `<span class="font-medium text-gray-800">${escapeHtml(warehouse.name)}</span> <span class="text-gray-400 font-mono text-[10px]">(#${row.warehouse_id})</span>` :
            `<span class="font-mono text-gray-700">#${escapeHtml(row.warehouse_id)}</span>`;
            
        return `
            <tr class="${isFailed ? 'bg-red-50/50 hover:bg-red-50/80' : 'hover:bg-gray-50/70'} transition-colors">
                <td class="px-3 py-2 text-center font-mono font-medium text-gray-600">
                    #${row._row_number}
                </td>
                <td class="px-3 py-2">
                    ${warehouseDisplay}
                </td>
                <td class="px-3 py-2">
                    ${productDisplay}
                </td>
                <td class="px-3 py-2">
                    ${serialOrQty}
                </td>
                <td class="px-3 py-2 text-center whitespace-nowrap">
                    ${isFailed ? 
                        '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200"><i class="fas fa-times-circle mr-1 text-red-500"></i>Fail</span>' : 
                        '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fas fa-check-circle mr-1 text-emerald-500"></i>Pass</span>'
                    }
                </td>
                <td class="px-3 py-2">
                    ${isFailed ? 
                        `<div class="text-red-700 font-medium text-xs flex items-start gap-1"><i class="fas fa-exclamation-circle text-red-500 mt-0.5 flex-shrink-0"></i><span>${escapeHtml(errorMsg)}</span></div>` : 
                        '<span class="text-emerald-600 text-xs flex items-center gap-1"><i class="fas fa-check text-[10px]"></i>Valid</span>'
                    }
                </td>
                <td class="px-3 py-2 text-center whitespace-nowrap">
                    <button type="button" onclick="deleteBulkRow(${row._row_number})" class="px-2 py-1 bg-white hover:bg-red-50 text-red-600 border border-red-200 rounded text-[11px] font-medium transition shadow-sm flex items-center justify-center gap-1 mx-auto" title="Delete this row">
                        <i class="fas fa-trash-alt text-[10px]"></i> Delete
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function deleteBulkRow(rowNumber) {
    const targetRowNum = parseInt(rowNumber);
    const prevCount = state.bulkUpload.parsedData.length;
    state.bulkUpload.parsedData = state.bulkUpload.parsedData.filter(r => r._row_number !== targetRowNum);
    delete state.bulkUpload.validationErrors[String(targetRowNum)];
    
    const newCount = state.bulkUpload.parsedData.length;
    
    if (state.bulkUpload.file) {
        const sizeText = `${formatFileSize(state.bulkUpload.file.size)} • ${newCount} row${newCount === 1 ? '' : 's'} detected`;
        if (newCount > 1000) {
            document.getElementById('selected-file-size').innerHTML = 
                `${sizeText} • <span class="text-red-600 font-semibold"><i class="fas fa-exclamation-circle mr-1"></i>Exceeds limit of 1,000 rows</span>`;
        } else {
            document.getElementById('selected-file-size').textContent = sizeText;
        }
    }
    
    if (newCount < prevCount) {
        showSuccess(`Row #${targetRowNum} removed.`);
    }
    
    updateBulkValidationState();
}

function removeFailedBulkRows() {
    const errorRowNumbers = Object.keys(state.bulkUpload.validationErrors || {});
    if (errorRowNumbers.length === 0) return;
    
    const count = errorRowNumbers.length;
    state.bulkUpload.parsedData = state.bulkUpload.parsedData.filter(r => !errorRowNumbers.includes(String(r._row_number)));
    state.bulkUpload.validationErrors = {};
    
    const newCount = state.bulkUpload.parsedData.length;
    if (state.bulkUpload.file) {
        const sizeText = `${formatFileSize(state.bulkUpload.file.size)} • ${newCount} row${newCount === 1 ? '' : 's'} detected`;
        if (newCount > 1000) {
            document.getElementById('selected-file-size').innerHTML = 
                `${sizeText} • <span class="text-red-600 font-semibold"><i class="fas fa-exclamation-circle mr-1"></i>Exceeds limit of 1,000 rows</span>`;
        } else {
            document.getElementById('selected-file-size').textContent = sizeText;
        }
    }
    
    showSuccess(`Removed ${count} failed row(s). Remaining rows are ready for upload.`);
    updateBulkValidationState();
}

async function processBulkUpload() {
    if (!state.bulkUpload.validated || !state.bulkUpload.parsedData || state.bulkUpload.parsedData.length === 0) {
        showError('Please validate the file first before uploading');
        return;
    }
    
    if (state.bulkUpload.parsedData.length > 1000) {
        showError(`Maximum 1,000 rows allowed per upload. You currently have ${state.bulkUpload.parsedData.length} rows.`);
        return;
    }
    
    // Check if there are still any unhandled failed rows
    const failedKeys = Object.keys(state.bulkUpload.validationErrors || {}).filter(k => 
        state.bulkUpload.parsedData.some(r => String(r._row_number) === String(k))
    );
    if (failedKeys.length > 0) {
        showError('Please delete or remove all failed rows before uploading.');
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
        const totalRows = result.totalRows || state.bulkUpload.parsedData.length || 0;
        const successCount = result.successCount || 0;
        const errorCount = result.errorCount || (data.success ? 0 : totalRows);
        
        document.getElementById('result-total').textContent = totalRows;
        document.getElementById('result-success').textContent = successCount;
        document.getElementById('result-errors').textContent = errorCount;
        
        const resultMsgEl = document.getElementById('upload-result-message');
        
        if (data.success) {
            showSuccess(result.message || 'Bulk upload completed successfully');
            if (resultMsgEl) {
                resultMsgEl.className = 'mb-3 p-3 bg-emerald-100 text-emerald-800 text-xs rounded-lg font-medium';
                resultMsgEl.textContent = result.message || 'All valid rows uploaded successfully.';
                resultMsgEl.classList.remove('hidden');
            }
            loadStock();
        } else {
            const errorMsg = extractApiErrorMessage(data, result.message || 'Bulk upload failed');
            showError(errorMsg);
            if (resultMsgEl) {
                resultMsgEl.className = 'mb-3 p-3 bg-red-100 text-red-800 text-xs rounded-lg font-medium';
                resultMsgEl.textContent = `Upload Failed: ${errorMsg}`;
                resultMsgEl.classList.remove('hidden');
            }
        }
    } catch (error) {
        console.error('Error uploading:', error);
        showError('Failed to process upload: ' + error.message);
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
function exportStock() {
    try {
        const params = new URLSearchParams();
        if (state.filters.warehouse_id) params.append('warehouse_id', state.filters.warehouse_id);
        params.append('format', 'xlsx');
        params.append('download', 'true');
        
        const url = `../api/inventory/export/index.php?type=stock&${params}`;
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
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
