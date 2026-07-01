<?php
/**
 * ADV Inventory Dashboard
 * 
 * Stock summary cards with totals
 * Dispatched vs available charts
 * Contractor allocation breakdown
 * Alert panels for low stock and overdue repairs
 * 
 * Requirements: 9.1, 9.2, 9.3, 9.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV role
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV role required.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'ADV Inventory Dashboard';
$currentPage = 'inventory_dashboard_adv';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'ADV Dashboard']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Products</p>
                    <p id="total-products" class="text-2xl font-bold text-gray-800">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-boxes text-blue-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Across all warehouses</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Assets</p>
                    <p id="total-assets" class="text-2xl font-bold text-gray-800">-</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-barcode text-purple-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Serializable items</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Warehouses</p>
                    <p id="active-warehouses" class="text-2xl font-bold text-gray-800">-</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-warehouse text-green-500 text-xl"></i>
                </div>
            </div>
            <p id="total-warehouses-text" class="text-xs text-gray-400 mt-2">of - total</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Alerts</p>
                    <p id="total-alerts" class="text-2xl font-bold text-red-600">-</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Requiring attention</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Asset Status Breakdown -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Asset Status Breakdown</h3>
            <div id="asset-status-chart" class="h-64 flex items-center justify-center">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
        
        <!-- Dispatched vs Available -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Dispatched vs Available</h3>
            <div id="dispatch-chart" class="h-64 flex items-center justify-center">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Contractor Allocations & Alerts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Contractor Allocations -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Contractor Allocations</h3>
                <p class="text-sm text-gray-500">Assets distributed to contractors</p>
            </div>
            <div id="contractor-allocations" class="p-4 max-h-80 overflow-y-auto">
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
                </div>
            </div>
        </div>
        
        <!-- Alerts Panel -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Alerts</h3>
                        <p class="text-sm text-gray-500">Low stock & overdue repairs</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="showAlertTab('low-stock')" id="tab-low-stock" class="px-3 py-1 text-sm rounded-lg bg-red-100 text-red-700">
                            Low Stock <span id="low-stock-count" class="ml-1 font-bold">0</span>
                        </button>
                        <button onclick="showAlertTab('overdue')" id="tab-overdue" class="px-3 py-1 text-sm rounded-lg bg-gray-100 text-gray-600">
                            Overdue <span id="overdue-count" class="ml-1 font-bold">0</span>
                        </button>
                    </div>
                </div>
            </div>
            <div id="alerts-content" class="p-4 max-h-80 overflow-y-auto">
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Warehouse Summary & Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Warehouse Summary -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Warehouse Summary</h3>
                        <p class="text-sm text-gray-500">Stock levels by warehouse</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" id="warehouse-search" placeholder="Search..." 
                            class="px-3 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent w-40"
                            oninput="filterWarehouses()">
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Warehouse</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Assets</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Stock</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody id="warehouse-summary-tbody" class="divide-y">
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Warehouse Pagination -->
            <div class="p-3 border-t flex items-center justify-between">
                <div id="warehouse-pagination-info" class="text-xs text-gray-500"></div>
                <div id="warehouse-pagination-controls" class="flex items-center gap-1"></div>
            </div>
        </div>
        
        <!-- Recent Dispatches -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Recent Dispatches</h3>
                        <p class="text-sm text-gray-500">Latest dispatch activity</p>
                    </div>
                    <a href="dispatch.php" class="text-sm text-primary hover:underline">View All</a>
                </div>
            </div>
            <div id="recent-dispatches" class="p-4 max-h-80 overflow-y-auto">
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Acknowledgments -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Pending Acknowledgments</h3>
                    <p class="text-sm text-gray-500">Dispatches awaiting recipient confirmation</p>
                </div>
                <span id="pending-ack-count" class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-sm font-medium">0</span>
            </div>
        </div>
        <div id="pending-acknowledgments" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Pending Receives (Returns from Contractors/Engineers) -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Pending Returns</h3>
                    <p class="text-sm text-gray-500">Returns from contractors/engineers awaiting acceptance</p>
                </div>
                <span id="pending-receives-count" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">0</span>
            </div>
        </div>
        <div id="pending-receives" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Pending Action Highlights -->
    <div id="pending-actions-section" class="hidden">
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl shadow-sm p-6 border border-amber-200">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-bell text-amber-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Action Required</h3>
                    <p class="text-sm text-gray-500">Items requiring your attention</p>
                </div>
            </div>
            <div id="pending-actions-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Action items will be rendered here -->
            </div>
        </div>
    </div>

    <!-- Inventory by Category -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Inventory by Category</h3>
            <p class="text-sm text-gray-500">Stock breakdown by product category</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
            <div>
                <h4 class="text-sm font-medium text-gray-600 mb-3">Serializable Items (Assets)</h4>
                <div id="category-assets" class="space-y-2">
                    <div class="flex items-center justify-center py-4">
                        <i class="fas fa-spinner fa-spin text-xl text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-600 mb-3">Non-Serializable Items (Stock)</h4>
                <div id="category-stock" class="space-y-2">
                    <div class="flex items-center justify-center py-4">
                        <i class="fas fa-spinner fa-spin text-xl text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// State
const state = {
    data: null,
    charts: {},
    currentAlertTab: 'low-stock',
    warehousePagination: { page: 1, limit: 5, search: '' }
};

const API_URL = '../api/inventory/dashboard/adv.php';

// Initialize
document.addEventListener('DOMContentLoaded', loadDashboard);

async function loadDashboard() {
    try {
        const response = await fetch(API_URL, { credentials: 'include' });
        const result = await response.json();
        
        if (result.success) {
            state.data = result.data;
            renderSummaryCards();
            renderAssetStatusChart();
            renderDispatchChart();
            renderContractorAllocations();
            renderAlerts();
            renderWarehouseSummary();
            renderRecentDispatches();
            renderPendingAcknowledgments();
            renderPendingReceives();
            renderPendingActions();
            renderInventoryByCategory();
        } else {
            showError(result.message || 'Failed to load dashboard');
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError('Failed to load dashboard data');
    }
}

function renderSummaryCards() {
    const { summary, alerts } = state.data;
    
    document.getElementById('total-products').textContent = summary.total_products || 0;
    document.getElementById('total-assets').textContent = summary.total_assets || 0;
    document.getElementById('active-warehouses').textContent = summary.active_warehouses || 0;
    document.getElementById('total-warehouses-text').textContent = `of ${summary.total_warehouses || 0} total`;
    
    const totalAlerts = (alerts.low_stock?.count || 0) + (alerts.overdue_repairs?.count || 0);
    document.getElementById('total-alerts').textContent = totalAlerts;
}

function renderAssetStatusChart() {
    const container = document.getElementById('asset-status-chart');
    const statusData = state.data.stock_by_status || {};
    
    const labels = Object.keys(statusData).map(s => s.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()));
    const data = Object.values(statusData);
    const colors = ['#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#6B7280', '#EF4444', '#374151', '#DC2626'];
    
    if (data.every(v => v === 0)) {
        container.innerHTML = '<p class="text-gray-400">No asset data available</p>';
        return;
    }
    
    container.innerHTML = '<canvas id="status-canvas"></canvas>';
    const ctx = document.getElementById('status-canvas').getContext('2d');
    
    state.charts.status = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } }
            }
        }
    });
}

function renderDispatchChart() {
    const container = document.getElementById('dispatch-chart');
    const dispatchData = state.data.dispatched_vs_available || {};
    
    const serializable = dispatchData.serializable || {};
    const nonSerializable = dispatchData.non_serializable || {};
    
    container.innerHTML = '<canvas id="dispatch-canvas"></canvas>';
    const ctx = document.getElementById('dispatch-canvas').getContext('2d');
    
    state.charts.dispatch = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Serializable', 'Non-Serializable'],
            datasets: [
                {
                    label: 'Available',
                    data: [serializable.available || 0, nonSerializable.available || 0],
                    backgroundColor: '#10B981'
                },
                {
                    label: 'Dispatched/Reserved',
                    data: [serializable.dispatched || 0, nonSerializable.reserved || 0],
                    backgroundColor: '#3B82F6'
                },
                {
                    label: 'Under Repair',
                    data: [serializable.under_repair || 0, 0],
                    backgroundColor: '#F59E0B'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
            plugins: { legend: { position: 'top' } }
        }
    });
}

function renderContractorAllocations() {
    const container = document.getElementById('contractor-allocations');
    const allocations = state.data.contractor_allocations || [];
    
    if (allocations.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No contractor allocations</p>';
        return;
    }
    
    container.innerHTML = allocations.map(c => `
        <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-building text-blue-500"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">${escapeHtml(c.company_name)}</p>
                    <p class="text-xs text-gray-500">${c.asset_count} assets</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                ${c.not_working_count > 0 ? `<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">${c.not_working_count} not working</span>` : ''}
                <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">${c.in_use_count} in use</span>
            </div>
        </div>
    `).join('');
}

function renderAlerts() {
    const { alerts } = state.data;
    
    document.getElementById('low-stock-count').textContent = alerts.low_stock?.count || 0;
    document.getElementById('overdue-count').textContent = alerts.overdue_repairs?.count || 0;
    
    showAlertTab(state.currentAlertTab);
}

function showAlertTab(tab) {
    state.currentAlertTab = tab;
    const container = document.getElementById('alerts-content');
    const { alerts } = state.data;
    
    // Update tab styles
    document.getElementById('tab-low-stock').className = tab === 'low-stock' 
        ? 'px-3 py-1 text-sm rounded-lg bg-red-100 text-red-700' 
        : 'px-3 py-1 text-sm rounded-lg bg-gray-100 text-gray-600';
    document.getElementById('tab-overdue').className = tab === 'overdue' 
        ? 'px-3 py-1 text-sm rounded-lg bg-orange-100 text-orange-700' 
        : 'px-3 py-1 text-sm rounded-lg bg-gray-100 text-gray-600';
    
    if (tab === 'low-stock') {
        const items = alerts.low_stock?.items || [];
        if (items.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-400 py-4"><i class="fas fa-check-circle text-green-500 mr-2"></i>No low stock alerts</p>';
            return;
        }
        container.innerHTML = items.map(item => `
            <div class="flex items-center justify-between p-3 hover:bg-red-50 rounded-lg border-l-4 border-red-500 mb-2">
                <div>
                    <p class="font-medium text-gray-800">${escapeHtml(item.product_name || 'Unknown Product')}</p>
                    <p class="text-xs text-gray-500">${escapeHtml(item.warehouse_name || 'Unknown Warehouse')}</p>
                </div>
                <div class="text-right">
                    <p class="text-red-600 font-bold">${item.current_value || 0}</p>
                    <p class="text-xs text-gray-400">Threshold: ${item.threshold_value || 0}</p>
                </div>
            </div>
        `).join('');
    } else {
        const items = alerts.overdue_repairs?.items || [];
        if (items.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-400 py-4"><i class="fas fa-check-circle text-green-500 mr-2"></i>No overdue repairs</p>';
            return;
        }
        container.innerHTML = items.map(item => `
            <div class="flex items-center justify-between p-3 hover:bg-orange-50 rounded-lg border-l-4 border-orange-500 mb-2">
                <div>
                    <p class="font-medium text-gray-800">${escapeHtml(item.serial_number || 'Unknown')}</p>
                    <p class="text-xs text-gray-500">${escapeHtml(item.product_name || '')} - ${escapeHtml(item.repair_vendor || 'No vendor')}</p>
                </div>
                <div class="text-right">
                    <p class="text-orange-600 font-medium">${formatDate(item.expected_return_date)}</p>
                    <p class="text-xs text-gray-400">Expected return</p>
                </div>
            </div>
        `).join('');
    }
}

function renderWarehouseSummary() {
    const tbody = document.getElementById('warehouse-summary-tbody');
    let warehouses = state.data.warehouse_summary || [];
    
    // Apply search filter
    const search = state.warehousePagination.search.toLowerCase();
    if (search) {
        warehouses = warehouses.filter(w => 
            (w.name || '').toLowerCase().includes(search) || 
            (w.company_name || '').toLowerCase().includes(search)
        );
    }
    
    const total = warehouses.length;
    const { page, limit } = state.warehousePagination;
    const totalPages = Math.ceil(total / limit);
    const start = (page - 1) * limit;
    const paginatedWarehouses = warehouses.slice(start, start + limit);
    
    if (paginatedWarehouses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No warehouses found</td></tr>';
        document.getElementById('warehouse-pagination-info').textContent = '';
        document.getElementById('warehouse-pagination-controls').innerHTML = '';
        return;
    }
    
    tbody.innerHTML = paginatedWarehouses.map(w => `
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3">
                <div class="flex items-center">
                    <div class="w-8 h-8 ${w.status === 'active' ? 'bg-blue-100' : 'bg-gray-100'} rounded-lg flex items-center justify-center mr-2">
                        <i class="fas fa-warehouse ${w.status === 'active' ? 'text-blue-500' : 'text-gray-400'} text-sm"></i>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800 text-sm">${escapeHtml(w.name)}</p>
                        <p class="text-xs text-gray-400">${escapeHtml(w.company_name || '')}</p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm">${w.asset_count || 0}</td>
            <td class="px-4 py-3 text-sm">${w.stock_quantity || 0}</td>
            <td class="px-4 py-3">
                ${w.status === 'active' 
                    ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>'
                    : '<span class="px-2 py-1 bg-gray-100 text-gray-500 rounded-full text-xs">Inactive</span>'
                }
            </td>
        </tr>
    `).join('');
    
    // Render pagination info
    document.getElementById('warehouse-pagination-info').textContent = 
        `${start + 1}-${Math.min(start + limit, total)} of ${total}`;
    
    // Render pagination controls
    if (totalPages > 1) {
        let controls = `<button onclick="warehouseGoToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
            class="px-2 py-1 text-xs rounded border ${page === 1 ? 'bg-gray-100 text-gray-400' : 'hover:bg-gray-100'}">
            <i class="fas fa-chevron-left"></i>
        </button>`;
        controls += `<span class="px-2 text-xs text-gray-500">${page}/${totalPages}</span>`;
        controls += `<button onclick="warehouseGoToPage(${page + 1})" ${page === totalPages ? 'disabled' : ''} 
            class="px-2 py-1 text-xs rounded border ${page === totalPages ? 'bg-gray-100 text-gray-400' : 'hover:bg-gray-100'}">
            <i class="fas fa-chevron-right"></i>
        </button>`;
        document.getElementById('warehouse-pagination-controls').innerHTML = controls;
    } else {
        document.getElementById('warehouse-pagination-controls').innerHTML = '';
    }
}

function filterWarehouses() {
    state.warehousePagination.search = document.getElementById('warehouse-search').value;
    state.warehousePagination.page = 1;
    renderWarehouseSummary();
}

function warehouseGoToPage(page) {
    const warehouses = state.data.warehouse_summary || [];
    const search = state.warehousePagination.search.toLowerCase();
    let filtered = warehouses;
    if (search) {
        filtered = warehouses.filter(w => 
            (w.name || '').toLowerCase().includes(search) || 
            (w.company_name || '').toLowerCase().includes(search)
        );
    }
    const totalPages = Math.ceil(filtered.length / state.warehousePagination.limit);
    if (page >= 1 && page <= totalPages) {
        state.warehousePagination.page = page;
        renderWarehouseSummary();
    }
}

function renderRecentDispatches() {
    const container = document.getElementById('recent-dispatches');
    const dispatches = state.data.recent_activity?.recent_dispatches || [];
    
    if (dispatches.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No recent dispatches</p>';
        return;
    }
    
    container.innerHTML = dispatches.slice(0, 5).map(d => {
        const statusColors = {
            pending: 'bg-yellow-100 text-yellow-700',
            in_transit: 'bg-blue-100 text-blue-700',
            delivered: 'bg-green-100 text-green-700',
            cancelled: 'bg-red-100 text-red-700'
        };
        const destination = d.to_user_name || d.to_company_name || d.to_warehouse_name || '-';
        
        return `
            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg mb-2">
                <div>
                    <p class="font-medium text-primary text-sm">${escapeHtml(d.dispatch_number)}</p>
                    <p class="text-xs text-gray-500">${escapeHtml(d.from_warehouse_name || '')} → ${escapeHtml(destination)}</p>
                </div>
                <div class="text-right">
                    <span class="px-2 py-1 ${statusColors[d.status] || 'bg-gray-100 text-gray-700'} rounded-full text-xs capitalize">${d.status}</span>
                    <p class="text-xs text-gray-400 mt-1">${formatDate(d.dispatch_date)}</p>
                </div>
            </div>
        `;
    }).join('');
}

function renderPendingAcknowledgments() {
    const container = document.getElementById('pending-acknowledgments');
    const pending = state.data.recent_activity?.pending_acknowledgments || {};
    const items = pending.items || [];
    
    document.getElementById('pending-ack-count').textContent = pending.count || 0;
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4"><i class="fas fa-check-circle text-green-500 mr-2"></i>All dispatches acknowledged</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${items.map(d => `
                <div class="p-4 border rounded-lg hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-primary">${escapeHtml(d.dispatch_number)}</span>
                        <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs">Pending</span>
                    </div>
                    <p class="text-sm text-gray-600">${escapeHtml(d.to_company_name || d.to_user_name || '-')}</p>
                    <p class="text-xs text-gray-400 mt-1">${formatDate(d.dispatch_date)}</p>
                </div>
            `).join('')}
        </div>
    `;
}

function renderPendingReceives() {
    const container = document.getElementById('pending-receives');
    const pending = state.data.pending_receives || {};
    const items = pending.items || [];
    
    document.getElementById('pending-receives-count').textContent = pending.count || 0;
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4"><i class="fas fa-check-circle text-green-500 mr-2"></i>No pending returns</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${items.map(pr => `
                <div class="p-4 border rounded-lg hover:shadow-md transition ${pr.is_overdue ? 'border-red-300 bg-red-50' : ''}">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-primary">${escapeHtml(pr.dispatch_number || 'N/A')}</span>
                        ${pr.is_overdue 
                            ? '<span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Overdue</span>'
                            : '<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs">Pending</span>'
                        }
                    </div>
                    <p class="text-sm text-gray-600">From: ${escapeHtml(pr.sender_name || pr.from_company_name || pr.from_user_name || '-')}</p>
                    <p class="text-xs text-gray-500">${pr.total_expected_quantity || 0} item(s)</p>
                    <p class="text-xs text-gray-400 mt-1">${formatDate(pr.created_at)}</p>
                    <div class="mt-3 flex gap-2">
                        <button onclick="acceptPendingReceive(${pr.id})" class="flex-1 px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                            <i class="fas fa-check mr-1"></i>Accept
                        </button>
                        <button onclick="showRejectModal(${pr.id})" class="flex-1 px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                            <i class="fas fa-times mr-1"></i>Reject
                        </button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderPendingActions() {
    const section = document.getElementById('pending-actions-section');
    const container = document.getElementById('pending-actions-list');
    const actions = state.data.pending_actions || [];
    
    if (actions.length === 0) {
        section.classList.add('hidden');
        return;
    }
    
    section.classList.remove('hidden');
    
    const severityColors = {
        danger: 'bg-red-100 text-red-700 border-red-200',
        warning: 'bg-amber-100 text-amber-700 border-amber-200',
        info: 'bg-blue-100 text-blue-700 border-blue-200'
    };
    
    const severityIcons = {
        danger: 'fa-exclamation-circle text-red-500',
        warning: 'fa-exclamation-triangle text-amber-500',
        info: 'fa-info-circle text-blue-500'
    };
    
    container.innerHTML = actions.map(action => `
        <a href="${action.action_url}" class="block p-4 rounded-lg border ${severityColors[action.severity] || 'bg-gray-100 text-gray-700 border-gray-200'} hover:shadow-md transition">
            <div class="flex items-center mb-2">
                <i class="fas ${severityIcons[action.severity] || 'fa-info-circle text-gray-500'} mr-2"></i>
                <span class="font-medium">${escapeHtml(action.title)}</span>
            </div>
            <p class="text-2xl font-bold">${action.count}</p>
            <p class="text-xs mt-1">${escapeHtml(action.message)}</p>
        </a>
    `).join('');
}

function renderInventoryByCategory() {
    const assetsContainer = document.getElementById('category-assets');
    const stockContainer = document.getElementById('category-stock');
    const categoryData = state.data.inventory_by_category || {};
    
    // Render serializable items (assets)
    const assets = categoryData.serializable || [];
    if (assets.length === 0) {
        assetsContainer.innerHTML = '<p class="text-center text-gray-400 py-2">No asset data</p>';
    } else {
        assetsContainer.innerHTML = assets.slice(0, 8).map(cat => `
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-700">${escapeHtml(cat.category_name)}</span>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">${cat.in_stock_count || 0} in stock</span>
                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">${cat.dispatched_count || 0} dispatched</span>
                    <span class="font-medium text-gray-800">${cat.asset_count}</span>
                </div>
            </div>
        `).join('');
    }
    
    // Render non-serializable items (stock)
    const stock = categoryData.non_serializable || [];
    if (stock.length === 0) {
        stockContainer.innerHTML = '<p class="text-center text-gray-400 py-2">No stock data</p>';
    } else {
        stockContainer.innerHTML = stock.slice(0, 8).map(cat => `
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-700">${escapeHtml(cat.category_name)}</span>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">${cat.available_quantity || 0} available</span>
                    <span class="font-medium text-gray-800">${cat.total_quantity}</span>
                </div>
            </div>
        `).join('');
    }
}

async function acceptPendingReceive(pendingReceiveId) {
    if (!confirm('Are you sure you want to accept this return?')) return;
    
    try {
        const response = await fetch('../api/inventory/receive/accept.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pending_receive_id: pendingReceiveId })
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccess('Return accepted successfully');
            loadDashboard();
        } else {
            showError(result.message || 'Failed to accept return');
        }
    } catch (error) {
        console.error('Error accepting return:', error);
        showError('Failed to accept return');
    }
}

function showRejectModal(pendingReceiveId) {
    const reason = prompt('Please enter a reason for rejection:');
    if (reason === null) return; // User cancelled
    if (!reason.trim()) {
        showError('Rejection reason is required');
        return;
    }
    rejectPendingReceive(pendingReceiveId, reason);
}

async function rejectPendingReceive(pendingReceiveId, reason) {
    try {
        const response = await fetch('../api/inventory/receive/reject.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pending_receive_id: pendingReceiveId, reason: reason })
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccess('Return rejected successfully');
            loadDashboard();
        } else {
            showError(result.message || 'Failed to reject return');
        }
    } catch (error) {
        console.error('Error rejecting return:', error);
        showError('Failed to reject return');
    }
}

function showSuccess(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2';
    toast.innerHTML = `<i class="fas fa-check-circle"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Utility functions
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showError(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2';
    toast.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>
