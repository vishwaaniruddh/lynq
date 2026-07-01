<?php
/**
 * Warehouse Stock Detail Page
 * 
 * Shows all stock items or assets for a specific warehouse
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check inventory permission
if (!can('inventory.warehouses.view') && !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view warehouse stock';
    header('Location: ../dashboard.php');
    exit;
}

$warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'stock'; // 'stock' or 'assets'

if (!$warehouseId) {
    $_SESSION['flash_error'] = 'Invalid warehouse';
    header('Location: warehouses.php');
    exit;
}

// Get warehouse details
require_once __DIR__ . '/../repositories/WarehouseRepository.php';
$warehouseRepo = new WarehouseRepository();
$warehouse = $warehouseRepo->find($warehouseId);

if (!$warehouse) {
    $_SESSION['flash_error'] = 'Warehouse not found';
    header('Location: warehouses.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = $type === 'assets' ? 'Warehouse Assets' : 'Warehouse Stock';
$currentPage = 'inventory_warehouses';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Warehouses', 'url' => 'warehouses.php'],
    ['label' => htmlspecialchars($warehouse['name'])],
    ['label' => $type === 'assets' ? 'Assets' : 'Stock Items']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b">
        <div class="flex items-center gap-4 mb-4">
            <a href="warehouses.php" class="p-2 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-arrow-left text-gray-500"></i>
            </a>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-warehouse text-blue-500 text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($warehouse['name']); ?></h3>
                <p class="text-sm text-gray-500"><?php echo $type === 'assets' ? 'Assets' : 'Stock Items'; ?></p>
            </div>
        </div>
        
        <!-- Type Toggle -->
        <div class="flex gap-2">
            <a href="?warehouse_id=<?php echo $warehouseId; ?>&type=stock" 
               class="px-4 py-2 rounded-lg transition <?php echo $type === 'stock' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                <i class="fas fa-boxes mr-2"></i>Stock Items
            </a>
            <a href="?warehouse_id=<?php echo $warehouseId; ?>&type=assets" 
               class="px-4 py-2 rounded-lg transition <?php echo $type === 'assets' ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                <i class="fas fa-barcode mr-2"></i>Assets
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="data-table" class="w-full">
            <thead class="bg-gray-50">
                <?php if ($type === 'assets'): ?>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Serial Number</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Created</th>
                </tr>
                <?php else: ?>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Quantity</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Unit</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody id="data-tbody" class="divide-y">
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">Loading...</td>
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
const warehouseId = <?php echo $warehouseId; ?>;
const dataType = '<?php echo $type; ?>';

const state = {
    items: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '' }
};

document.addEventListener('DOMContentLoaded', function() {
    loadData();
    setupEventListeners();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadData();
        }, 300);
    });
}

async function loadData() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            warehouse_id: warehouseId,
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        
        const endpoint = dataType === 'assets' 
            ? `../api/inventory/assets/index.php?${params}${state.filters.search ? '&serial_number=' + encodeURIComponent(state.filters.search) : ''}`
            : `../api/inventory/stock/index.php?${params}`;
        
        const response = await fetch(endpoint, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.items = data.data[dataType === 'assets' ? 'assets' : 'stock'] || [];
            state.pagination = {
                page: data.data.pagination?.page || 1,
                limit: data.data.pagination?.limit || 20,
                total: data.data.pagination?.total || state.items.length,
                total_pages: data.data.pagination?.total_pages || 1
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load data');
        }
    } catch (error) {
        console.error('Error loading data:', error);
        showError('Failed to load data. Please try again.');
    } finally {
        showLoading(false);
    }
}

function renderTable() {
    const tbody = document.getElementById('data-tbody');
    
    if (state.items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-${dataType === 'assets' ? 'barcode' : 'boxes'} text-4xl mb-3 text-gray-300"></i>
                    <p>No ${dataType === 'assets' ? 'assets' : 'stock items'} found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startNum = (state.pagination.page - 1) * state.pagination.limit;
    
    if (dataType === 'assets') {
        tbody.innerHTML = state.items.map((item, index) => `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-4 text-gray-500">${startNum + index + 1}</td>
                <td class="px-6 py-4">
                    <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">${escapeHtml(item.serial_number)}</span>
                </td>
                <td class="px-6 py-4 font-medium text-gray-800">${escapeHtml(item.product_name || '-')}</td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 rounded text-xs ${getStatusClass(item.status)}">${escapeHtml(item.status || '-')}</span>
                </td>
                <td class="px-6 py-4 text-gray-500 text-sm">${formatDate(item.created_at)}</td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = state.items.map((item, index) => `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-4 text-gray-500">${startNum + index + 1}</td>
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-box text-blue-500 text-sm"></i>
                        </div>
                        <span class="font-medium text-gray-800">${escapeHtml(item.product_name || '-')}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-gray-600">${escapeHtml(item.category_name || '-')}</td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-sm font-medium">${item.quantity || 0}</span>
                </td>
                <td class="px-6 py-4 text-gray-600">${escapeHtml(item.unit_of_measure || '-')}</td>
            </tr>
        `).join('');
    }
}

function getStatusClass(status) {
    const classes = {
        'in_stock': 'bg-green-100 text-green-700',
        'assigned': 'bg-blue-100 text-blue-700',
        'in_repair': 'bg-yellow-100 text-yellow-700',
        'scrapped': 'bg-red-100 text-red-700',
        'lost': 'bg-gray-100 text-gray-700'
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString();
}

function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    const start = total > 0 ? (page - 1) * limit + 1 : 0;
    const end = Math.min(page * limit, total);
    
    document.getElementById('pagination-info').textContent = 
        total > 0 ? `Showing ${start} to ${end} of ${total} entries` : 'No entries';
    
    const controls = document.getElementById('pagination-controls');
    
    if (total_pages <= 1) {
        controls.innerHTML = '';
        return;
    }
    
    let html = `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i></button>`;
    
    for (let i = Math.max(1, page - 2); i <= Math.min(total_pages, page + 2); i++) {
        html += `<button onclick="goToPage(${i})" 
            class="px-3 py-1 rounded border ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">${i}</button>`;
    }
    
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i></button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadData();
}

function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('data-table').classList.toggle('hidden', show);
}

function showError(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg';
    toast.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
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
