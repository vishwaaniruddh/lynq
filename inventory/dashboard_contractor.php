<?php
/**
 * Contractor Inventory Dashboard
 * 
 * Received inventory list
 * Engineer assignment summary
 * Pending returns panel
 * Non-working items highlight
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check contractor role
if (!isContractorUser()) {
    $_SESSION['flash_error'] = 'Access denied. Contractor role required.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Contractor Inventory Dashboard';
$currentPage = 'inventory_dashboard_contractor';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'Contractor Dashboard']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Assets</p>
                    <p id="total-assets" class="text-2xl font-bold text-gray-800">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-boxes text-blue-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Received from ADV</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending Receives</p>
                    <p id="pending-receives-count" class="text-2xl font-bold text-blue-600">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-inbox text-blue-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Awaiting acceptance</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Engineers</p>
                    <p id="engineer-count" class="text-2xl font-bold text-gray-800">-</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-purple-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">With assigned items</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending Returns</p>
                    <p id="pending-returns" class="text-2xl font-bold text-orange-600">-</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-undo text-orange-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Awaiting processing</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Not Working</p>
                    <p id="not-working" class="text-2xl font-bold text-red-600">-</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Requiring attention</p>
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

    <!-- Inventory Counters -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Current Inventory</h3>
            <p class="text-sm text-gray-500">Stock levels by product</p>
        </div>
        <div id="inventory-counters" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Pending Receives Section -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Pending Receives</h3>
                    <p class="text-sm text-gray-500">Dispatches awaiting your acceptance</p>
                </div>
                <span id="pending-receives-badge" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">0</span>
            </div>
        </div>
        <div id="pending-receives-list" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Status Breakdown & Engineer Assignments -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Status Breakdown Chart -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Asset Status Breakdown</h3>
            <div id="status-chart" class="h-64 flex items-center justify-center">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
        
        <!-- Condition Breakdown -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Working Condition</h3>
            <div id="condition-chart" class="h-64 flex items-center justify-center">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Engineer Assignments -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Engineer Assignments</h3>
            <p class="text-sm text-gray-500">Items assigned to each engineer</p>
        </div>
        <div id="engineer-assignments" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Non-Working Items & Pending Returns -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Non-Working Items -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Non-Working Items</h3>
                        <p class="text-sm text-gray-500">Items requiring repair or attention</p>
                    </div>
                    <span id="non-working-badge" class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">0</span>
                </div>
            </div>
            <div id="non-working-items" class="p-4 max-h-96 overflow-y-auto">
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
                </div>
            </div>
        </div>
        
        <!-- Pending Returns -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Pending Returns</h3>
                        <p class="text-sm text-gray-500">Items marked for return</p>
                    </div>
                    <span id="pending-returns-badge" class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-sm font-medium">0</span>
                </div>
            </div>
            <div id="pending-returns-list" class="p-4 max-h-96 overflow-y-auto">
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Received Inventory & Recent Dispatches -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Received Inventory -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Received Inventory</h3>
                        <p class="text-sm text-gray-500">Items received from ADV</p>
                    </div>
                    <input type="text" id="inventory-search" placeholder="Search..." 
                        class="px-3 py-1 border rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="w-full">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Serial #</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Condition</th>
                        </tr>
                    </thead>
                    <tbody id="received-inventory-tbody" class="divide-y">
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Material Received -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Recent Material Received</h3>
                        <p class="text-sm text-gray-500">Material received from ADV</p>
                    </div>
                </div>
            </div>
            <div id="recent-dispatches" class="p-4 max-h-96 overflow-y-auto">
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
                    <p class="text-sm text-gray-500">Dispatches awaiting your confirmation</p>
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

    <!-- Dispatched to Engineers -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Dispatched to Engineers</h3>
                    <p class="text-sm text-gray-500">Items currently with engineers</p>
                </div>
                <span id="dispatched-engineers-count" class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium">0</span>
            </div>
        </div>
        <div id="dispatched-to-engineers" class="p-4 max-h-96 overflow-y-auto">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Inventory by Category -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Inventory by Category</h3>
            <p class="text-sm text-gray-500">Stock breakdown by product category</p>
        </div>
        <div id="inventory-by-category" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
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
    filteredInventory: []
};

const API_URL = '../api/inventory/dashboard/contractor.php';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('inventory-search').addEventListener('input', function(e) {
        filterInventory(e.target.value);
    });
}

async function loadDashboard() {
    try {
        const response = await fetch(API_URL, { credentials: 'include' });
        const result = await response.json();
        
        if (result.success) {
            state.data = result.data;
            state.filteredInventory = result.data.received_inventory?.items || [];
            renderSummaryCards();
            renderStatusChart();
            renderConditionChart();
            renderEngineerAssignments();
            renderNonWorkingItems();
            renderPendingReturns();
            renderReceivedInventory();
            renderRecentDispatches();
            renderPendingAcknowledgments();
            renderInventoryCounters();
            renderPendingReceivesList();
            renderDispatchedToEngineers();
            renderInventoryByCategory();
            renderPendingActions();
        } else {
            showError(result.message || 'Failed to load dashboard');
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError('Failed to load dashboard data');
    }
}

function renderSummaryCards() {
    const { summary, pending_returns, non_working_items, pending_receives } = state.data;
    
    document.getElementById('total-assets').textContent = summary.total_assets || 0;
    document.getElementById('engineer-count').textContent = summary.engineer_count || 0;
    document.getElementById('pending-returns').textContent = pending_returns?.count || 0;
    document.getElementById('not-working').textContent = non_working_items?.count || 0;
    document.getElementById('pending-receives-count').textContent = pending_receives?.count || summary.pending_receives_count || 0;
}

function renderStatusChart() {
    const container = document.getElementById('status-chart');
    const statusData = state.data.summary?.by_status || {};
    
    const labels = Object.keys(statusData).map(s => s.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()));
    const data = Object.values(statusData);
    const colors = ['#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#6B7280'];
    
    if (data.length === 0 || data.every(v => v === 0)) {
        container.innerHTML = '<p class="text-gray-400">No data available</p>';
        return;
    }
    
    container.innerHTML = '<canvas id="status-canvas"></canvas>';
    const ctx = document.getElementById('status-canvas').getContext('2d');
    
    state.charts.status = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{ data: data, backgroundColor: colors.slice(0, labels.length), borderWidth: 0 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } } }
        }
    });
}

function renderConditionChart() {
    const container = document.getElementById('condition-chart');
    const conditionData = state.data.summary?.by_condition || {};
    
    const working = conditionData.working || 0;
    const notWorking = conditionData.not_working || 0;
    
    if (working === 0 && notWorking === 0) {
        container.innerHTML = '<p class="text-gray-400">No data available</p>';
        return;
    }
    
    container.innerHTML = '<canvas id="condition-canvas"></canvas>';
    const ctx = document.getElementById('condition-canvas').getContext('2d');
    
    state.charts.condition = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Working', 'Not Working'],
            datasets: [{ data: [working, notWorking], backgroundColor: ['#10B981', '#EF4444'], borderWidth: 0 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } } }
        }
    });
}

function renderEngineerAssignments() {
    const container = document.getElementById('engineer-assignments');
    const engineers = state.data.engineer_assignments || [];
    
    if (engineers.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No engineers with assigned items</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${engineers.map(eng => `
                <div class="border rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-user text-purple-500"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">${escapeHtml(eng.engineer_name)}</p>
                            <p class="text-xs text-gray-500">${escapeHtml(eng.engineer_email || '')}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="bg-blue-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-blue-600">${eng.total_items}</p>
                            <p class="text-xs text-gray-500">Total</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-green-600">${eng.in_use_count}</p>
                            <p class="text-xs text-gray-500">In Use</p>
                        </div>
                        <div class="bg-red-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-red-600">${eng.not_working_count}</p>
                            <p class="text-xs text-gray-500">Not Working</p>
                        </div>
                    </div>
                    ${eng.items && eng.items.length > 0 ? `
                        <button onclick="toggleEngineerItems(this, ${eng.engineer_id})" class="mt-3 w-full text-sm text-primary hover:underline">
                            <i class="fas fa-chevron-down mr-1"></i>View Items (${eng.items.length})
                        </button>
                        <div id="engineer-items-${eng.engineer_id}" class="hidden mt-2 max-h-40 overflow-y-auto">
                            ${eng.items.map(item => `
                                <div class="flex items-center justify-between py-1 text-sm border-t">
                                    <span class="text-gray-600">${escapeHtml(item.serial_number)}</span>
                                    <span class="px-2 py-0.5 ${item.working_condition === 'working' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'} rounded text-xs">
                                        ${item.working_condition === 'working' ? 'Working' : 'Not Working'}
                                    </span>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `).join('')}
        </div>
    `;
}

function toggleEngineerItems(btn, engineerId) {
    const container = document.getElementById(`engineer-items-${engineerId}`);
    const isHidden = container.classList.contains('hidden');
    container.classList.toggle('hidden');
    btn.innerHTML = isHidden 
        ? '<i class="fas fa-chevron-up mr-1"></i>Hide Items'
        : `<i class="fas fa-chevron-down mr-1"></i>View Items`;
}

function renderNonWorkingItems() {
    const container = document.getElementById('non-working-items');
    const items = state.data.non_working_items?.items || [];
    
    document.getElementById('non-working-badge').textContent = items.length;
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4"><i class="fas fa-check-circle text-green-500 mr-2"></i>All items working</p>';
        return;
    }
    
    container.innerHTML = items.map(item => `
        <div class="flex items-center justify-between p-3 hover:bg-red-50 rounded-lg border-l-4 border-red-500 mb-2">
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(item.serial_number)}</p>
                <p class="text-xs text-gray-500">${escapeHtml(item.product_name || '')}</p>
                ${item.current_holder_name ? `<p class="text-xs text-gray-400">Held by: ${escapeHtml(item.current_holder_name)}</p>` : ''}
            </div>
            <div class="text-right">
                ${item.repair_id 
                    ? `<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">${item.repair_status || 'Under Repair'}</span>`
                    : item.is_repairable 
                        ? '<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Repairable</span>'
                        : '<span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs">Non-Repairable</span>'
                }
            </div>
        </div>
    `).join('');
}

function renderPendingReturns() {
    const container = document.getElementById('pending-returns-list');
    const items = state.data.pending_returns?.items || [];
    
    document.getElementById('pending-returns-badge').textContent = items.length;
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4"><i class="fas fa-check-circle text-green-500 mr-2"></i>No pending returns</p>';
        return;
    }
    
    container.innerHTML = items.map(item => `
        <div class="flex items-center justify-between p-3 hover:bg-orange-50 rounded-lg border-l-4 border-orange-500 mb-2">
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(item.serial_number)}</p>
                <p class="text-xs text-gray-500">${escapeHtml(item.product_name || '')}</p>
                ${item.assigned_to ? `<p class="text-xs text-gray-400">From: ${escapeHtml(item.assigned_to)}</p>` : ''}
            </div>
            <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs">Returned</span>
        </div>
    `).join('');
}

function renderReceivedInventory() {
    renderInventoryTable(state.filteredInventory);
}

function filterInventory(search) {
    const items = state.data.received_inventory?.items || [];
    if (!search) {
        state.filteredInventory = items;
    } else {
        const s = search.toLowerCase();
        state.filteredInventory = items.filter(item => 
            (item.serial_number || '').toLowerCase().includes(s) ||
            (item.product_name || '').toLowerCase().includes(s)
        );
    }
    renderInventoryTable(state.filteredInventory);
}

function renderInventoryTable(items) {
    const tbody = document.getElementById('received-inventory-tbody');
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No items found</td></tr>';
        return;
    }
    
    tbody.innerHTML = items.slice(0, 50).map(item => {
        const statusColors = {
            in_stock: 'bg-green-100 text-green-700',
            dispatched: 'bg-blue-100 text-blue-700',
            assigned: 'bg-purple-100 text-purple-700',
            in_use: 'bg-indigo-100 text-indigo-700',
            returned: 'bg-orange-100 text-orange-700',
            under_repair: 'bg-yellow-100 text-yellow-700'
        };
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-primary">${escapeHtml(item.serial_number)}</td>
                <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(item.product_name || '-')}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 ${statusColors[item.status] || 'bg-gray-100 text-gray-700'} rounded text-xs capitalize">${(item.status || '').replace('_', ' ')}</span>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 ${item.working_condition === 'working' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'} rounded text-xs">
                        ${item.working_condition === 'working' ? 'Working' : 'Not Working'}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

function renderRecentDispatches() {
    const container = document.getElementById('recent-dispatches');
    const dispatches = state.data.recent_activity?.recent_dispatches || [];
    
    if (dispatches.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No material received yet</p>';
        return;
    }
    
    container.innerHTML = dispatches.slice(0, 5).map(d => {
        const statusColors = {
            pending: 'bg-yellow-100 text-yellow-700',
            in_transit: 'bg-blue-100 text-blue-700',
            delivered: 'bg-green-100 text-green-700',
            cancelled: 'bg-red-100 text-red-700'
        };
        
        return `
            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg mb-2">
                <div>
                    <p class="font-medium text-primary text-sm">${escapeHtml(d.dispatch_number)}</p>
                    <p class="text-xs text-gray-500">From: ${escapeHtml(d.from_warehouse_name || d.from_company_name || '-')}</p>
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
                <div class="p-4 border rounded-lg hover:shadow-md transition border-orange-200 bg-orange-50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-primary">${escapeHtml(d.dispatch_number)}</span>
                        <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs">Pending</span>
                    </div>
                    <p class="text-sm text-gray-600">From: ${escapeHtml(d.from_warehouse_name || d.from_company_name || '-')}</p>
                    <p class="text-xs text-gray-400 mt-1">${formatDate(d.dispatch_date)}</p>
                    <button onclick="acknowledgeDispatch(${d.id})" class="mt-3 w-full px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                        <i class="fas fa-check mr-1"></i>Acknowledge
                    </button>
                </div>
            `).join('')}
        </div>
    `;
}

function renderInventoryCounters() {
    const container = document.getElementById('inventory-counters');
    const counters = state.data.inventory_counters || [];
    
    if (counters.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No inventory data available</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${counters.map(counter => `
                <div class="p-4 border rounded-lg hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-800">${escapeHtml(counter.product_name || 'Unknown Product')}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="bg-green-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-green-600">${counter.quantity || 0}</p>
                            <p class="text-xs text-gray-500">Available</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-blue-600">${counter.pending_out || 0}</p>
                            <p class="text-xs text-gray-500">Pending Out</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-purple-600">${counter.pending_in || 0}</p>
                            <p class="text-xs text-gray-500">Pending In</p>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderPendingReceivesList() {
    const container = document.getElementById('pending-receives-list');
    const pending = state.data.pending_receives || {};
    const items = pending.items || [];
    
    document.getElementById('pending-receives-badge').textContent = pending.count || 0;
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4"><i class="fas fa-check-circle text-green-500 mr-2"></i>No pending receives</p>';
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
                    <p class="text-sm text-gray-600">From: ${escapeHtml(pr.sender_name || pr.from_warehouse_name || '-')}</p>
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

function renderDispatchedToEngineers() {
    const container = document.getElementById('dispatched-to-engineers');
    const dispatched = state.data.dispatched_to_engineers || {};
    const items = dispatched.items || [];
    
    document.getElementById('dispatched-engineers-count').textContent = dispatched.count || 0;
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No items dispatched to engineers</p>';
        return;
    }
    
    // Group by engineer
    const byEngineer = {};
    items.forEach(item => {
        const key = item.engineer_id || 'unknown';
        if (!byEngineer[key]) {
            byEngineer[key] = {
                name: item.engineer_name || 'Unknown',
                items: []
            };
        }
        byEngineer[key].items.push(item);
    });
    
    container.innerHTML = Object.values(byEngineer).map(eng => `
        <div class="mb-4 p-4 border rounded-lg">
            <div class="flex items-center mb-3">
                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-2">
                    <i class="fas fa-user text-purple-500 text-sm"></i>
                </div>
                <div>
                    <p class="font-medium text-gray-800">${escapeHtml(eng.name)}</p>
                    <p class="text-xs text-gray-500">${eng.items.length} item(s)</p>
                </div>
            </div>
            <div class="space-y-2">
                ${eng.items.slice(0, 5).map(item => `
                    <div class="flex items-center justify-between py-1 text-sm border-t">
                        <span class="text-gray-600">${escapeHtml(item.serial_number)}</span>
                        <span class="px-2 py-0.5 ${item.working_condition === 'working' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'} rounded text-xs">
                            ${item.working_condition === 'working' ? 'Working' : 'Not Working'}
                        </span>
                    </div>
                `).join('')}
                ${eng.items.length > 5 ? `<p class="text-xs text-gray-400 text-center">+${eng.items.length - 5} more</p>` : ''}
            </div>
        </div>
    `).join('');
}

function renderInventoryByCategory() {
    const container = document.getElementById('inventory-by-category');
    const categories = state.data.inventory_by_category || [];
    
    if (categories.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No category data available</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${categories.map(cat => `
                <div class="p-4 border rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-medium text-gray-800">${escapeHtml(cat.category_name || 'Uncategorized')}</span>
                        <span class="text-lg font-bold text-gray-600">${cat.asset_count || 0}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="bg-green-50 rounded p-1">
                            <p class="font-bold text-green-600">${cat.in_stock_count || 0}</p>
                            <p class="text-gray-500">In Stock</p>
                        </div>
                        <div class="bg-blue-50 rounded p-1">
                            <p class="font-bold text-blue-600">${cat.in_use_count || 0}</p>
                            <p class="text-gray-500">In Use</p>
                        </div>
                        <div class="bg-red-50 rounded p-1">
                            <p class="font-bold text-red-600">${cat.not_working_count || 0}</p>
                            <p class="text-gray-500">Not Working</p>
                        </div>
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

async function acceptPendingReceive(pendingReceiveId) {
    if (!confirm('Are you sure you want to accept this dispatch?')) return;
    
    try {
        const response = await fetch('../api/inventory/receive/accept.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ pending_receive_id: pendingReceiveId })
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccess('Dispatch accepted successfully');
            loadDashboard();
        } else {
            showError(result.message || 'Failed to accept dispatch');
        }
    } catch (error) {
        console.error('Error accepting dispatch:', error);
        showError('Failed to accept dispatch');
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
            showSuccess('Dispatch rejected successfully');
            loadDashboard();
        } else {
            showError(result.message || 'Failed to reject dispatch');
        }
    } catch (error) {
        console.error('Error rejecting dispatch:', error);
        showError('Failed to reject dispatch');
    }
}

async function acknowledgeDispatch(dispatchId) {
    try {
        const response = await fetch(`../api/inventory/dispatch/acknowledge.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ dispatch_id: dispatchId })
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccess('Dispatch acknowledged successfully');
            loadDashboard();
        } else {
            showError(result.message || 'Failed to acknowledge dispatch');
        }
    } catch (error) {
        console.error('Error acknowledging dispatch:', error);
        showError('Failed to acknowledge dispatch');
    }
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
    showToast(message, 'error');
}

function showSuccess(message) {
    showToast(message, 'success');
}

function showToast(message, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>
