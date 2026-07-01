<?php
/**
 * Item History Page (ADV Only)
 * 
 * Display complete dispatch chain for serializable items
 * Track item journey from origin to current holder
 * 
 * Requirements: 9.1, 9.2, 9.3
 * - 9.1: Display complete chain of dispatches and receives from origin to current holder
 * - 9.2: Show each transfer with sender, receiver, timestamps, and acceptance status
 * - 9.3: Maintain full history without data loss for re-dispatched items
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// ADV only page
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to view item history';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Item History';
$currentPage = 'inventory_item_history';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Item History']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Search Section -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Item History Search</h3>
            <p class="text-sm text-gray-500">Search for serializable items to view their complete dispatch chain</p>
        </div>
        
        <div class="p-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="asset-search" class="block text-sm font-medium text-gray-700 mb-1">Search by Serial Number</label>
                    <div class="relative">
                        <input type="text" id="asset-search" placeholder="Enter serial number..." 
                            class="w-full px-4 py-2 pl-10 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
                <div class="md:w-64">
                    <label for="product-filter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Product</label>
                    <select id="product-filter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Products</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="searchAssets()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search Results -->
    <div id="search-results" class="bg-white rounded-xl shadow-sm hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Search Results</h3>
            <p id="results-count" class="text-sm text-gray-500"></p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Serial Number</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Current Holder</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="results-tbody" class="divide-y"></tbody>
            </table>
        </div>
    </div>
    
    <!-- Item History Timeline -->
    <div id="history-section" class="bg-white rounded-xl shadow-sm hidden">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Dispatch Chain</h3>
                <p id="history-subtitle" class="text-sm text-gray-500"></p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="exportHistory()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-download mr-2"></i>Export
                </button>
                <button onclick="closeHistory()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
        </div>
        
        <!-- Asset Info Card -->
        <div id="asset-info" class="p-6 border-b bg-gray-50"></div>
        
        <!-- Timeline -->
        <div id="history-timeline" class="p-6"></div>
    </div>
</div>

<script>
const API_URL = '../api/inventory';
let currentAssetId = null;
let products = [];

// Status configuration
const STATUS_CONFIG = {
    in_stock: { label: 'In Stock', color: 'bg-green-100 text-green-700', icon: 'fa-warehouse' },
    dispatched: { label: 'Dispatched', color: 'bg-blue-100 text-blue-700', icon: 'fa-truck' },
    assigned: { label: 'Assigned', color: 'bg-purple-100 text-purple-700', icon: 'fa-user-check' },
    in_use: { label: 'In Use', color: 'bg-indigo-100 text-indigo-700', icon: 'fa-tools' },
    returned: { label: 'Returned', color: 'bg-teal-100 text-teal-700', icon: 'fa-undo' },
    under_repair: { label: 'Under Repair', color: 'bg-yellow-100 text-yellow-700', icon: 'fa-wrench' },
    scrapped: { label: 'Scrapped', color: 'bg-red-100 text-red-700', icon: 'fa-trash' },
    lost: { label: 'Lost', color: 'bg-gray-100 text-gray-700', icon: 'fa-question-circle' }
};

const CHAIN_STATUS_CONFIG = {
    dispatched: { label: 'Dispatched', color: 'bg-blue-500', icon: 'fa-truck' },
    accepted: { label: 'Accepted', color: 'bg-green-500', icon: 'fa-check' },
    rejected: { label: 'Rejected', color: 'bg-red-500', icon: 'fa-times' }
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    
    // Handle Enter key in search
    document.getElementById('asset-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchAssets();
    });
    
    // Check URL params for asset_id
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('asset_id')) {
        viewHistory(parseInt(urlParams.get('asset_id')));
    }
});

// Load products for filter
async function loadProducts() {
    try {
        const response = await fetch(`${API_URL}/products/index.php?limit=500&is_serializable=1`, { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            products = (data.data.products || []).filter(p => p.is_serializable == 1);
            const select = document.getElementById('product-filter');
            select.innerHTML = '<option value="">All Products</option>' +
                products.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

// Search assets
async function searchAssets() {
    const serialNumber = document.getElementById('asset-search').value.trim();
    const productId = document.getElementById('product-filter').value;
    
    if (!serialNumber && !productId) {
        showToast('Please enter a serial number or select a product', 'warning');
        return;
    }
    
    try {
        const params = new URLSearchParams({ limit: 50 });
        if (serialNumber) params.append('serial_number', serialNumber);
        if (productId) params.append('product_id', productId);
        
        const response = await fetch(`${API_URL}/assets/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            renderSearchResults(data.data.assets || []);
        } else {
            showToast(data.error?.message || 'Search failed', 'error');
        }
    } catch (error) {
        console.error('Search error:', error);
        showToast('Search failed', 'error');
    }
}

// Render search results
function renderSearchResults(assets) {
    const container = document.getElementById('search-results');
    const tbody = document.getElementById('results-tbody');
    const countEl = document.getElementById('results-count');
    
    container.classList.remove('hidden');
    countEl.textContent = `Found ${assets.length} item(s)`;
    
    if (assets.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    <i class="fas fa-search text-4xl mb-3 text-gray-300"></i>
                    <p>No items found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = assets.map((asset, index) => {
        const statusConfig = STATUS_CONFIG[asset.status] || { label: asset.status, color: 'bg-gray-100 text-gray-700', icon: 'fa-circle' };
        const holder = asset.current_holder_name || asset.warehouse_name || '-';
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">#${index + 1}</td>
            <td class="px-4 py-2.5">
                <span class="font-medium text-xs text-gray-800">${escapeHtml(asset.serial_number)}</span>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(asset.product_name || '-')}</td>
            <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2 py-0.5 ${statusConfig.color} rounded-full text-[10px] font-medium">
                    <i class="fas ${statusConfig.icon} mr-1"></i>${statusConfig.label}
                </span>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(holder)}</td>
            <td class="px-4 py-2.5">
                <button onclick="viewHistory(${asset.id})" class="px-2.5 py-1 bg-primary text-white rounded-lg hover:bg-blue-600 transition text-[10px]">
                    <i class="fas fa-history mr-1"></i>View History
                </button>
            </td>
        </tr>
        `;
    }).join('');
}

// View item history
async function viewHistory(assetId) {
    currentAssetId = assetId;
    
    try {
        const response = await fetch(`${API_URL}/history/item.php?asset_id=${assetId}`, { credentials: 'include' });
        const data = await response.json();
        
        if (!data.success) {
            showToast(data.error?.message || 'Failed to load history', 'error');
            return;
        }
        
        renderHistory(data.data);
    } catch (error) {
        console.error('Error loading history:', error);
        showToast('Failed to load history', 'error');
    }
}

// Render history timeline
function renderHistory(data) {
    const section = document.getElementById('history-section');
    const subtitle = document.getElementById('history-subtitle');
    const assetInfo = document.getElementById('asset-info');
    const timeline = document.getElementById('history-timeline');
    
    section.classList.remove('hidden');
    
    const asset = data.asset;
    const history = data.history || [];
    
    subtitle.textContent = `Serial: ${asset.serial_number} | ${data.total_transfers} transfer(s)`;
    
    // Asset info card
    const statusConfig = STATUS_CONFIG[asset.status] || { label: asset.status, color: 'bg-gray-100 text-gray-700', icon: 'fa-circle' };
    assetInfo.innerHTML = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-500 mb-1">Serial Number</p>
                <p class="font-semibold text-gray-800">${escapeHtml(asset.serial_number)}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Product</p>
                <p class="font-semibold text-gray-800">${escapeHtml(asset.product_name || '-')}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Current Status</p>
                <span class="inline-flex items-center px-2.5 py-1 ${statusConfig.color} rounded-full text-xs font-medium">
                    <i class="fas ${statusConfig.icon} mr-1.5"></i>${statusConfig.label}
                </span>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Current Holder</p>
                <p class="font-semibold text-gray-800">${escapeHtml(asset.current_holder_name || asset.warehouse_name || '-')}</p>
            </div>
        </div>
    `;
    
    // Timeline
    if (history.length === 0) {
        timeline.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-history text-4xl mb-3 text-gray-300"></i>
                <p>No dispatch history found for this item</p>
            </div>
        `;
        return;
    }
    
    timeline.innerHTML = `
        <div class="relative">
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
            <div class="space-y-6">
                ${history.map((entry, index) => {
                    const chainStatus = CHAIN_STATUS_CONFIG[entry.status] || { label: entry.status, color: 'bg-gray-500', icon: 'fa-circle' };
                    const isFirst = index === 0;
                    const isLast = index === history.length - 1;
                    
                    return `
                    <div class="relative pl-10">
                        <div class="absolute left-0 w-8 h-8 rounded-full ${chainStatus.color} flex items-center justify-center text-white">
                            <i class="fas ${chainStatus.icon} text-sm"></i>
                        </div>
                        <div class="bg-white border rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-gray-800">
                                    ${isFirst ? 'Origin' : `Transfer #${entry.sequence_number}`}
                                </span>
                                <span class="text-xs text-gray-500">${formatDate(entry.dispatch_date)}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">From</p>
                                    <p class="font-medium text-gray-800">${escapeHtml(entry.from_entity_name || formatEntityType(entry.from_entity_type))}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">To</p>
                                    <p class="font-medium text-gray-800">${escapeHtml(entry.to_entity_name || formatEntityType(entry.to_entity_type))}</p>
                                </div>
                            </div>
                            ${entry.acceptance_date ? `
                            <div class="mt-2 pt-2 border-t text-xs text-gray-500">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                Accepted on ${formatDate(entry.acceptance_date)}
                            </div>
                            ` : entry.status === 'rejected' ? `
                            <div class="mt-2 pt-2 border-t text-xs text-red-500">
                                <i class="fas fa-times-circle mr-1"></i>
                                Rejected
                            </div>
                            ` : `
                            <div class="mt-2 pt-2 border-t text-xs text-yellow-600">
                                <i class="fas fa-clock mr-1"></i>
                                Pending acceptance
                            </div>
                            `}
                        </div>
                    </div>
                    `;
                }).join('')}
            </div>
        </div>
    `;
    
    // Scroll to history section
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Export history
async function exportHistory() {
    if (!currentAssetId) return;
    
    try {
        const response = await fetch(`${API_URL}/history/export.php?asset_id=${currentAssetId}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            // Create download
            const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `item-history-${currentAssetId}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            showToast('History exported successfully', 'success');
        } else {
            showToast(data.error?.message || 'Export failed', 'error');
        }
    } catch (error) {
        console.error('Export error:', error);
        showToast('Export failed', 'error');
    }
}

// Close history
function closeHistory() {
    document.getElementById('history-section').classList.add('hidden');
    currentAssetId = null;
}

// Helper functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatEntityType(type) {
    const types = {
        warehouse: 'Warehouse',
        company: 'Company',
        user: 'User'
    };
    return types[type] || type;
}

function showToast(message, type = 'info') {
    // Use existing toast system if available
    if (typeof Toastify !== 'undefined') {
        Toastify({
            text: message,
            duration: 3000,
            gravity: 'top',
            position: 'right',
            backgroundColor: type === 'error' ? '#ef4444' : type === 'success' ? '#22c55e' : '#3b82f6'
        }).showToast();
    } else {
        alert(message);
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>
