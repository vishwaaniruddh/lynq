<?php
/**
 * Engineer Inventory Dashboard
 * 
 * Assigned items list
 * Status update actions (In Use, Return, Working/Not Working)
 * Repair request button
 * 
 * Requirements: 11.1, 11.2, 11.3, 11.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check engineer role
if (!isEngineerUser()) {
    $_SESSION['flash_error'] = 'Access denied. Engineer role required.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'My Inventory';
$currentPage = 'inventory_dashboard_engineer';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Inventory'],
    ['label' => 'My Items']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Items</p>
                    <p id="total-items" class="text-2xl font-bold text-gray-800">-</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-boxes text-blue-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Assigned to you</p>
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
                    <p class="text-sm text-gray-500">In Use</p>
                    <p id="in-use-count" class="text-2xl font-bold text-green-600">-</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Currently using</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Not Working</p>
                    <p id="not-working-count" class="text-2xl font-bold text-red-600">-</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Need attention</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Under Repair</p>
                    <p id="under-repair-count" class="text-2xl font-bold text-yellow-600">-</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-tools text-yellow-500 text-xl"></i>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Being repaired</p>
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
            <div id="pending-actions-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Action items will be rendered here -->
            </div>
        </div>
    </div>

    <!-- Inventory Counters -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">My Inventory</h3>
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

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <button onclick="showItemsForAction('mark_in_use')" class="p-4 border rounded-lg hover:bg-green-50 hover:border-green-300 transition text-center">
                <i class="fas fa-play-circle text-2xl text-green-500 mb-2"></i>
                <p class="text-sm font-medium text-gray-700">Mark In Use</p>
            </button>
            <button onclick="showItemsForAction('mark_returned')" class="p-4 border rounded-lg hover:bg-orange-50 hover:border-orange-300 transition text-center">
                <i class="fas fa-undo text-2xl text-orange-500 mb-2"></i>
                <p class="text-sm font-medium text-gray-700">Return Item</p>
            </button>
            <button onclick="showItemsForAction('mark_not_working')" class="p-4 border rounded-lg hover:bg-red-50 hover:border-red-300 transition text-center">
                <i class="fas fa-times-circle text-2xl text-red-500 mb-2"></i>
                <p class="text-sm font-medium text-gray-700">Report Not Working</p>
            </button>
            <button onclick="showItemsForAction('request_repair')" class="p-4 border rounded-lg hover:bg-yellow-50 hover:border-yellow-300 transition text-center">
                <i class="fas fa-wrench text-2xl text-yellow-500 mb-2"></i>
                <p class="text-sm font-medium text-gray-700">Request Repair</p>
            </button>
        </div>
    </div>

    <!-- Items Requiring Attention -->
    <div id="attention-section" class="bg-white rounded-xl shadow-sm hidden">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Items Requiring Attention</h3>
                    <p class="text-sm text-gray-500">Non-working items that need repair</p>
                </div>
                <span id="attention-count" class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">0</span>
            </div>
        </div>
        <div id="attention-items" class="p-4">
            <!-- Items will be rendered here -->
        </div>
    </div>

    <!-- Assigned Items List -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">My Assigned Items</h3>
                    <p class="text-sm text-gray-500">All items currently assigned to you</p>
                </div>
                <div class="flex items-center gap-3">
                    <input type="text" id="search-input" placeholder="Search items..." 
                        class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Status</option>
                        <option value="assigned">Assigned</option>
                        <option value="in_use">In Use</option>
                        <option value="returned">Returned</option>
                        <option value="under_repair">Under Repair</option>
                    </select>
                    <select id="condition-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">All Condition</option>
                        <option value="working">Working</option>
                        <option value="not_working">Not Working</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                            <input type="checkbox" id="select-all" class="rounded" onchange="toggleSelectAll()">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Serial #</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Condition</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Received</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="items-tbody" class="divide-y">
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Bulk Actions -->
        <div id="bulk-actions" class="hidden p-4 border-t bg-gray-50">
            <div class="flex items-center gap-4">
                <span id="selected-count" class="text-sm text-gray-600">0 items selected</span>
                <button onclick="bulkAction('in_use')" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                    <i class="fas fa-play-circle mr-1"></i>Mark In Use
                </button>
                <button onclick="bulkAction('returned')" class="px-3 py-1 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm">
                    <i class="fas fa-undo mr-1"></i>Return
                </button>
                <button onclick="bulkAction('not_working')" class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                    <i class="fas fa-times-circle mr-1"></i>Not Working
                </button>
            </div>
        </div>
    </div>

    <!-- Pending Dispatches -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Pending Dispatches</h3>
                    <p class="text-sm text-gray-500">Items being sent to you</p>
                </div>
                <span id="pending-dispatch-count" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">0</span>
            </div>
        </div>
        <div id="pending-dispatches" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Active Repairs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Active Repairs</h3>
                    <p class="text-sm text-gray-500">Items currently being repaired</p>
                </div>
                <span id="active-repairs-count" class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">0</span>
            </div>
        </div>
        <div id="active-repairs" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>

    <!-- Inventory by Category -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Inventory by Category</h3>
            <p class="text-sm text-gray-500">Items breakdown by product category</p>
        </div>
        <div id="inventory-by-category" class="p-4">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-300"></i>
            </div>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div id="action-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeActionModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="action-modal-title" class="text-lg font-semibold text-gray-800">Update Item</h3>
                <button onclick="closeActionModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="action-modal-content" class="p-5">
                <!-- Content will be rendered here -->
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeActionModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" id="action-confirm-btn" onclick="confirmAction()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State
const state = {
    data: null,
    filteredItems: [],
    selectedItems: new Set(),
    currentAction: null,
    currentAssetId: null
};

const API_URL = '../api/inventory/dashboard/engineer.php';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('search-input').addEventListener('input', filterItems);
    document.getElementById('status-filter').addEventListener('change', filterItems);
    document.getElementById('condition-filter').addEventListener('change', filterItems);
}

async function loadDashboard() {
    try {
        const response = await fetch(API_URL, { credentials: 'include' });
        const result = await response.json();
        
        if (result.success) {
            state.data = result.data;
            state.filteredItems = result.data.assigned_items?.items || [];
            renderSummaryCards();
            renderAttentionItems();
            renderItemsTable();
            renderPendingDispatches();
            renderActiveRepairs();
            renderInventoryCounters();
            renderPendingReceivesList();
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
    const { summary, pending_receives } = state.data;
    
    document.getElementById('total-items').textContent = summary.total_items || 0;
    document.getElementById('in-use-count').textContent = summary.by_status?.in_use || 0;
    document.getElementById('not-working-count').textContent = summary.by_condition?.not_working || 0;
    document.getElementById('under-repair-count').textContent = summary.by_status?.under_repair || 0;
    document.getElementById('pending-receives-count').textContent = pending_receives?.count || summary.pending_receives_count || 0;
}

function renderAttentionItems() {
    const items = state.data.items_requiring_attention?.items || [];
    const section = document.getElementById('attention-section');
    const container = document.getElementById('attention-items');
    
    document.getElementById('attention-count').textContent = items.length;
    
    if (items.length === 0) {
        section.classList.add('hidden');
        return;
    }
    
    section.classList.remove('hidden');
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${items.map(item => `
                <div class="p-4 border rounded-lg border-red-200 bg-red-50">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-800">${escapeHtml(item.serial_number)}</span>
                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">Not Working</span>
                    </div>
                    <p class="text-sm text-gray-600">${escapeHtml(item.product_name || '')}</p>
                    ${item.is_repairable ? `
                        <button onclick="requestRepair(${item.id})" class="mt-3 w-full px-3 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 text-sm">
                            <i class="fas fa-wrench mr-1"></i>Request Repair
                        </button>
                    ` : `
                        <p class="mt-3 text-xs text-gray-500 text-center">Non-repairable item</p>
                    `}
                </div>
            `).join('')}
        </div>
    `;
}

function filterItems() {
    const search = document.getElementById('search-input').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter').value;
    const conditionFilter = document.getElementById('condition-filter').value;
    
    const items = state.data.assigned_items?.items || [];
    
    state.filteredItems = items.filter(item => {
        const matchesSearch = !search || 
            (item.serial_number || '').toLowerCase().includes(search) ||
            (item.product_name || '').toLowerCase().includes(search);
        const matchesStatus = !statusFilter || item.status === statusFilter;
        const matchesCondition = !conditionFilter || item.working_condition === conditionFilter;
        
        return matchesSearch && matchesStatus && matchesCondition;
    });
    
    renderItemsTable();
}

function renderItemsTable() {
    const tbody = document.getElementById('items-tbody');
    const items = state.filteredItems;
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No items found</td></tr>';
        return;
    }
    
    tbody.innerHTML = items.map(item => {
        const statusColors = {
            assigned: 'bg-purple-100 text-purple-700',
            in_use: 'bg-green-100 text-green-700',
            returned: 'bg-orange-100 text-orange-700',
            under_repair: 'bg-yellow-100 text-yellow-700'
        };
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <input type="checkbox" class="item-checkbox rounded" data-id="${item.id}" onchange="toggleItemSelection(${item.id})">
                </td>
                <td class="px-6 py-4">
                    <span class="font-medium text-primary">${escapeHtml(item.serial_number)}</span>
                </td>
                <td class="px-6 py-4 text-gray-600">${escapeHtml(item.product_name || '-')}</td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 ${statusColors[item.status] || 'bg-gray-100 text-gray-700'} rounded text-xs capitalize">
                        ${(item.status || '').replace('_', ' ')}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2 py-1 ${item.working_condition === 'working' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'} rounded text-xs">
                        ${item.working_condition === 'working' ? 'Working' : 'Not Working'}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">${formatDate(item.received_date)}</td>
                <td class="px-6 py-4">
                    <div class="flex items-center space-x-1">
                        ${item.status !== 'in_use' && item.status !== 'under_repair' ? `
                            <button onclick="updateItemStatus(${item.id}, 'in_use')" class="p-2 text-green-500 hover:bg-green-50 rounded" title="Mark In Use">
                                <i class="fas fa-play-circle"></i>
                            </button>
                        ` : ''}
                        ${item.status !== 'returned' && item.status !== 'under_repair' ? `
                            <button onclick="updateItemStatus(${item.id}, 'returned')" class="p-2 text-orange-500 hover:bg-orange-50 rounded" title="Return">
                                <i class="fas fa-undo"></i>
                            </button>
                        ` : ''}
                        ${item.working_condition === 'working' ? `
                            <button onclick="updateItemCondition(${item.id}, 'not_working')" class="p-2 text-red-500 hover:bg-red-50 rounded" title="Report Not Working">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        ` : item.is_repairable && item.status !== 'under_repair' ? `
                            <button onclick="requestRepair(${item.id})" class="p-2 text-yellow-500 hover:bg-yellow-50 rounded" title="Request Repair">
                                <i class="fas fa-wrench"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    updateBulkActionsVisibility();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('select-all').checked;
    const checkboxes = document.querySelectorAll('.item-checkbox');
    
    state.selectedItems.clear();
    checkboxes.forEach(cb => {
        cb.checked = selectAll;
        if (selectAll) {
            state.selectedItems.add(parseInt(cb.dataset.id));
        }
    });
    
    updateBulkActionsVisibility();
}

function toggleItemSelection(id) {
    if (state.selectedItems.has(id)) {
        state.selectedItems.delete(id);
    } else {
        state.selectedItems.add(id);
    }
    updateBulkActionsVisibility();
}

function updateBulkActionsVisibility() {
    const bulkActions = document.getElementById('bulk-actions');
    const selectedCount = document.getElementById('selected-count');
    
    if (state.selectedItems.size > 0) {
        bulkActions.classList.remove('hidden');
        selectedCount.textContent = `${state.selectedItems.size} item${state.selectedItems.size > 1 ? 's' : ''} selected`;
    } else {
        bulkActions.classList.add('hidden');
    }
}

async function updateItemStatus(assetId, newStatus) {
    try {
        const response = await fetch(`../api/inventory/assets/status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ asset_id: assetId, status: newStatus })
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccess(`Item marked as ${newStatus.replace('_', ' ')}`);
            loadDashboard();
        } else {
            showError(result.message || 'Failed to update status');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showError('Failed to update item status');
    }
}

async function updateItemCondition(assetId, condition) {
    try {
        const response = await fetch(`../api/inventory/assets/status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ asset_id: assetId, working_condition: condition })
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccess(`Item marked as ${condition.replace('_', ' ')}`);
            loadDashboard();
        } else {
            showError(result.message || 'Failed to update condition');
        }
    } catch (error) {
        console.error('Error updating condition:', error);
        showError('Failed to update item condition');
    }
}

async function requestRepair(assetId) {
    state.currentAssetId = assetId;
    state.currentAction = 'request_repair';
    
    const item = state.filteredItems.find(i => i.id === assetId);
    
    document.getElementById('action-modal-title').textContent = 'Request Repair';
    document.getElementById('action-modal-content').innerHTML = `
        <div class="space-y-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="font-medium text-gray-800">${escapeHtml(item?.serial_number || '')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(item?.product_name || '')}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Issue Description</label>
                <textarea id="repair-notes" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Describe the issue..."></textarea>
            </div>
        </div>
    `;
    
    document.getElementById('action-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

async function confirmAction() {
    if (state.currentAction === 'request_repair') {
        const notes = document.getElementById('repair-notes')?.value || '';
        
        try {
            const response = await fetch(`../api/inventory/repairs/create.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ asset_id: state.currentAssetId, notes: notes })
            });
            
            const result = await response.json();
            if (result.success) {
                showSuccess('Repair request submitted');
                closeActionModal();
                loadDashboard();
            } else {
                showError(result.message || 'Failed to submit repair request');
            }
        } catch (error) {
            console.error('Error requesting repair:', error);
            showError('Failed to submit repair request');
        }
    }
}

function closeActionModal() {
    document.getElementById('action-modal').classList.add('hidden');
    document.body.style.overflow = '';
    state.currentAction = null;
    state.currentAssetId = null;
}

async function bulkAction(action) {
    if (state.selectedItems.size === 0) return;
    
    const assetIds = Array.from(state.selectedItems);
    let endpoint, payload;
    
    if (action === 'in_use' || action === 'returned') {
        endpoint = '../api/inventory/assets/status.php';
        payload = { asset_ids: assetIds, status: action };
    } else if (action === 'not_working') {
        endpoint = '../api/inventory/assets/status.php';
        payload = { asset_ids: assetIds, working_condition: 'not_working' };
    }
    
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        if (result.success) {
            showSuccess(`${assetIds.length} item(s) updated`);
            state.selectedItems.clear();
            loadDashboard();
        } else {
            showError(result.message || 'Failed to update items');
        }
    } catch (error) {
        console.error('Error in bulk action:', error);
        showError('Failed to update items');
    }
}

function showItemsForAction(action) {
    // Filter items based on action
    let items = state.data.assigned_items?.items || [];
    
    if (action === 'mark_in_use') {
        items = items.filter(i => i.status !== 'in_use' && i.status !== 'under_repair');
    } else if (action === 'mark_returned') {
        items = items.filter(i => i.status !== 'returned' && i.status !== 'under_repair');
    } else if (action === 'mark_not_working') {
        items = items.filter(i => i.working_condition === 'working');
    } else if (action === 'request_repair') {
        items = items.filter(i => i.working_condition === 'not_working' && i.is_repairable && i.status !== 'under_repair');
    }
    
    state.filteredItems = items;
    renderItemsTable();
    
    // Scroll to table
    document.getElementById('items-tbody').scrollIntoView({ behavior: 'smooth' });
}

function renderPendingDispatches() {
    const container = document.getElementById('pending-dispatches');
    const dispatches = state.data.pending_dispatches?.items || [];
    
    document.getElementById('pending-dispatch-count').textContent = dispatches.length;
    
    if (dispatches.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No pending dispatches</p>';
        return;
    }
    
    container.innerHTML = dispatches.map(d => `
        <div class="flex items-center justify-between p-3 hover:bg-blue-50 rounded-lg border-l-4 border-blue-500 mb-2">
            <div>
                <p class="font-medium text-primary">${escapeHtml(d.dispatch_number)}</p>
                <p class="text-xs text-gray-500">From: ${escapeHtml(d.from_warehouse_name || '-')}</p>
            </div>
            <div class="text-right">
                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs capitalize">${d.status}</span>
                <p class="text-xs text-gray-400 mt-1">${formatDate(d.dispatch_date)}</p>
            </div>
        </div>
    `).join('');
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
                    <p class="text-sm text-gray-600">From: ${escapeHtml(pr.sender_name || pr.from_company_name || '-')}</p>
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
                        <span class="text-lg font-bold text-gray-600">${cat.total_count || 0}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="bg-blue-50 rounded p-1">
                            <p class="font-bold text-blue-600">${cat.in_use_count || 0}</p>
                            <p class="text-gray-500">In Use</p>
                        </div>
                        <div class="bg-green-50 rounded p-1">
                            <p class="font-bold text-green-600">${cat.working_count || 0}</p>
                            <p class="text-gray-500">Working</p>
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

function renderActiveRepairs() {
    const container = document.getElementById('active-repairs');
    const repairs = state.data.active_repairs?.items || [];
    
    document.getElementById('active-repairs-count').textContent = repairs.length;
    
    if (repairs.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 py-4">No active repairs</p>';
        return;
    }
    
    container.innerHTML = repairs.map(r => `
        <div class="flex items-center justify-between p-3 hover:bg-yellow-50 rounded-lg border-l-4 border-yellow-500 mb-2">
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(r.serial_number)}</p>
                <p class="text-xs text-gray-500">${escapeHtml(r.product_name || '')} - ${escapeHtml(r.repair_vendor || 'No vendor')}</p>
            </div>
            <div class="text-right">
                <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs capitalize">${r.repair_status}</span>
                ${r.expected_return_date ? `<p class="text-xs text-gray-400 mt-1">Expected: ${formatDate(r.expected_return_date)}</p>` : ''}
            </div>
        </div>
    `).join('');
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
