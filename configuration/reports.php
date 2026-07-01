<?php
/**
 * Configuration Reports Page
 * 
 * Configuration report with export
 * IP usage report with export
 * Pending configuration report with export
 * 
 * Requirements: 8.1, 8.2, 8.3, 8.4
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV user access for IP configuration
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to access IP Configuration';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Configuration Reports';
$currentPage = 'configuration_reports';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Configuration'],
    ['label' => 'Reports']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Configuration Reports</h2>
            <p class="text-gray-500">Generate and export IP configuration reports</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center">
                <i class="fas fa-chart-line mr-2"></i>Dashboard
            </a>
        </div>
    </div>
    
    <!-- Report Type Tabs -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="border-b">
            <nav class="flex -mb-px">
                <button onclick="switchTab('configurations')" id="tab-configurations" 
                    class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-primary text-primary">
                    <i class="fas fa-link mr-2"></i>Configuration Report
                </button>
                <button onclick="switchTab('ip_usage')" id="tab-ip_usage" 
                    class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-network-wired mr-2"></i>IP Usage Report
                </button>
                <button onclick="switchTab('pending')" id="tab-pending" 
                    class="tab-btn px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    <i class="fas fa-clock mr-2"></i>Pending Configuration
                </button>
            </nav>
        </div>
        
        <!-- Configuration Report Tab -->
        <div id="panel-configurations" class="tab-panel p-6">
            <div class="flex flex-col lg:flex-row lg:items-end gap-4 mb-6">
                <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" id="config-date-from" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" id="config-date-to" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="config-search" placeholder="Router serial or IP..." 
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="flex items-end gap-2">
                        <button onclick="loadConfigurationReport()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <button onclick="clearConfigFilters()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <button onclick="exportReport('configurations')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div id="config-summary" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-sm text-blue-600">Total Configurations</p>
                    <p id="config-total" class="text-2xl font-bold text-blue-800">-</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <p class="text-sm text-green-600">Date Range</p>
                    <p id="config-date-range" class="text-lg font-medium text-green-800">All Time</p>
                </div>
                <div class="bg-purple-50 rounded-lg p-4">
                    <p class="text-sm text-purple-600">Generated At</p>
                    <p id="config-generated" class="text-lg font-medium text-purple-800">-</p>
                </div>
            </div>
            
            <!-- Data Table -->
            <div id="config-table-container">
                <div id="config-loading" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Loading configuration report...</p>
                </div>
                <div id="config-empty" class="hidden text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-alt text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500">No configurations found</p>
                    <p class="text-sm text-gray-400">Try adjusting your filters</p>
                </div>
                <div id="config-table" class="hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Router Serial</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Network IP</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Router IP</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site IP</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Subnet Mask</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Configured At</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Configured By</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Notes</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="config-tbody" class="divide-y divide-gray-100">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- IP Usage Report Tab -->
        <div id="panel-ip_usage" class="tab-panel hidden p-6">
            <div class="flex flex-col lg:flex-row lg:items-end gap-4 mb-6">
                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="ip-status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="available">Available</option>
                            <option value="locked">Locked</option>
                            <option value="configured">Configured</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="ip-search" placeholder="Search IP addresses..." 
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="flex items-end gap-2">
                        <button onclick="loadIPUsageReport()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <button onclick="clearIPFilters()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <button onclick="exportReport('ip_usage')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div id="ip-summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-sm text-blue-600">Total IPs</p>
                    <p id="ip-total" class="text-2xl font-bold text-blue-800">-</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <p class="text-sm text-green-600">Available</p>
                    <p id="ip-available" class="text-2xl font-bold text-green-800">-</p>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <p class="text-sm text-yellow-600">Locked</p>
                    <p id="ip-locked" class="text-2xl font-bold text-yellow-800">-</p>
                </div>
                <div class="bg-purple-50 rounded-lg p-4">
                    <p class="text-sm text-purple-600">Configured</p>
                    <p id="ip-configured" class="text-2xl font-bold text-purple-800">-</p>
                </div>
            </div>
            
            <!-- Data Table -->
            <div id="ip-table-container">
                <div id="ip-loading" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Loading IP usage report...</p>
                </div>
                <div id="ip-empty" class="hidden text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-network-wired text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500">No IP records found</p>
                    <p class="text-sm text-gray-400">Try adjusting your filters</p>
                </div>
                <div id="ip-table" class="hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Network IP</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Router IP</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site IP</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Subnet Mask</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Bound Router</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Configured At</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Configured By</th>
                            </tr>
                        </thead>
                        <tbody id="ip-tbody" class="divide-y divide-gray-100">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Pending Configuration Tab -->
        <div id="panel-pending" class="tab-panel hidden p-6">
            <div class="flex flex-col lg:flex-row lg:items-end gap-4 mb-6">
                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="pending-search" placeholder="Search router serial or product..." 
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="flex items-end gap-2">
                        <button onclick="loadPendingReport()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <button onclick="clearPendingFilters()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <button onclick="exportReport('pending')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div id="pending-summary" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-orange-50 rounded-lg p-4">
                    <p class="text-sm text-orange-600">Total Pending</p>
                    <p id="pending-total" class="text-2xl font-bold text-orange-800">-</p>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <p class="text-sm text-yellow-600">In Progress</p>
                    <p id="pending-inprogress" class="text-2xl font-bold text-yellow-800">-</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Waiting</p>
                    <p id="pending-waiting" class="text-2xl font-bold text-gray-800">-</p>
                </div>
            </div>
            
            <!-- Data Table -->
            <div id="pending-table-container">
                <div id="pending-loading" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-3"></i>
                    <p class="text-gray-500">Loading pending configuration report...</p>
                </div>
                <div id="pending-empty" class="hidden text-center py-12">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-2xl text-green-500"></i>
                    </div>
                    <p class="text-gray-500">All routers are configured!</p>
                    <p class="text-sm text-gray-400">No pending configurations found</p>
                </div>
                <div id="pending-table" class="hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Serial Number</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Warehouse</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Asset Status</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Config Status</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Locked By</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Lock Expires</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pending-tbody" class="divide-y divide-gray-100">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// State management
const state = {
    currentTab: 'configurations',
    configData: null,
    ipData: null,
    pendingData: null
};

// API base URL
const API_URL = '../api/configuration';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set default date range (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    document.getElementById('config-date-to').value = formatDateForInput(today);
    document.getElementById('config-date-from').value = formatDateForInput(thirtyDaysAgo);
    
    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam && ['configurations', 'ip_usage', 'pending'].includes(tabParam)) {
        switchTab(tabParam);
    } else {
        // Load initial report
        loadConfigurationReport();
    }
});

// Tab switching
function switchTab(tabName) {
    state.currentTab = tabName;
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-primary', 'text-primary');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    const activeTab = document.getElementById(`tab-${tabName}`);
    activeTab.classList.remove('border-transparent', 'text-gray-500');
    activeTab.classList.add('border-primary', 'text-primary');
    
    // Update panels
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.add('hidden');
    });
    document.getElementById(`panel-${tabName}`).classList.remove('hidden');
    
    // Load data if not already loaded
    switch (tabName) {
        case 'configurations':
            if (!state.configData) loadConfigurationReport();
            break;
        case 'ip_usage':
            if (!state.ipData) loadIPUsageReport();
            break;
        case 'pending':
            if (!state.pendingData) loadPendingReport();
            break;
    }
}

// Load Configuration Report
async function loadConfigurationReport() {
    showLoading('config');
    
    const params = new URLSearchParams();
    
    const dateFrom = document.getElementById('config-date-from').value;
    const dateTo = document.getElementById('config-date-to').value;
    const search = document.getElementById('config-search').value.trim();
    
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (search) params.append('search', search);
    
    try {
        const response = await fetch(`${API_URL}/reports/configurations.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.configData = data.data;
            renderConfigurationReport(data.data);
        } else {
            showError(data.error?.message || 'Failed to load configuration report');
            showEmpty('config');
        }
    } catch (error) {
        console.error('Error loading configuration report:', error);
        showError('Failed to load configuration report');
        showEmpty('config');
    }
}

// Render Configuration Report
function renderConfigurationReport(data) {
    // Update summary
    document.getElementById('config-total').textContent = data.total;
    document.getElementById('config-generated').textContent = formatDateTime(data.generated_at);
    
    const filters = data.filters_applied;
    if (filters.date_from && filters.date_to) {
        document.getElementById('config-date-range').textContent = 
            `${formatDate(filters.date_from)} - ${formatDate(filters.date_to)}`;
    } else {
        document.getElementById('config-date-range').textContent = 'All Time';
    }
    
    // Render table
    if (!data.data || data.data.length === 0) {
        showEmpty('config');
        return;
    }
    
    const tbody = document.getElementById('config-tbody');
    tbody.innerHTML = '';
    
    data.data.forEach((record, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50/50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${index + 1}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.router_serial_number)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.ip_details.network_ip)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.ip_details.router_ip)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.ip_details.site_ip)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.ip_details.subnet_mask)}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${formatDateTime(record.configured_at)}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(record.configured_by.username || '-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500 max-w-xs truncate" title="${escapeHtml(record.notes || '')}">${escapeHtml(record.notes || '-')}</td>
            <td class="px-4 py-2.5 text-center">
                <button onclick="unbindConfiguration(${record.binding_id}, '${escapeHtml(record.router_serial_number)}')" 
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Unbind">
                    <i class="fas fa-unlink text-xs"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    showTable('config');
}

// Load IP Usage Report
async function loadIPUsageReport() {
    showLoading('ip');
    
    const params = new URLSearchParams();
    
    const status = document.getElementById('ip-status').value;
    const search = document.getElementById('ip-search').value.trim();
    
    if (status) params.append('status', status);
    if (search) params.append('search', search);
    
    try {
        const response = await fetch(`${API_URL}/reports/ip_usage.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.ipData = data.data;
            renderIPUsageReport(data.data);
        } else {
            showError(data.error?.message || 'Failed to load IP usage report');
            showEmpty('ip');
        }
    } catch (error) {
        console.error('Error loading IP usage report:', error);
        showError('Failed to load IP usage report');
        showEmpty('ip');
    }
}

// Render IP Usage Report
function renderIPUsageReport(data) {
    // Update summary
    document.getElementById('ip-total').textContent = data.summary.total;
    document.getElementById('ip-available').textContent = data.summary.available;
    document.getElementById('ip-locked').textContent = data.summary.locked;
    document.getElementById('ip-configured').textContent = data.summary.configured;
    
    // Render table
    if (!data.data || data.data.length === 0) {
        showEmpty('ip');
        return;
    }
    
    const tbody = document.getElementById('ip-tbody');
    tbody.innerHTML = '';
    
    data.data.forEach((record, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50/50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${index + 1}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.network_ip)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.router_ip)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.site_ip)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.subnet_mask)}</td>
            <td class="px-4 py-2.5">${getStatusBadge(record.status)}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${record.binding ? escapeHtml(record.binding.router_serial_number) : '-'}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${record.binding ? formatDateTime(record.binding.configured_at) : '-'}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${record.binding ? escapeHtml(record.binding.configured_by.username || '-') : '-'}</td>
        `;
        tbody.appendChild(row);
    });
    
    showTable('ip');
}

// Load Pending Report
async function loadPendingReport() {
    showLoading('pending');
    
    const params = new URLSearchParams();
    
    const search = document.getElementById('pending-search').value.trim();
    if (search) params.append('search', search);
    
    try {
        const response = await fetch(`${API_URL}/reports/pending.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.pendingData = data.data;
            renderPendingReport(data.data);
        } else {
            showError(data.error?.message || 'Failed to load pending report');
            showEmpty('pending');
        }
    } catch (error) {
        console.error('Error loading pending report:', error);
        showError('Failed to load pending report');
        showEmpty('pending');
    }
}

// Render Pending Report
function renderPendingReport(data) {
    // Update summary
    document.getElementById('pending-total').textContent = data.summary.total_pending;
    document.getElementById('pending-inprogress').textContent = data.summary.in_progress;
    document.getElementById('pending-waiting').textContent = data.summary.waiting;
    
    // Render table
    if (!data.data || data.data.length === 0) {
        showEmpty('pending');
        return;
    }
    
    const tbody = document.getElementById('pending-tbody');
    tbody.innerHTML = '';
    
    data.data.forEach((record, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50/50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${index + 1}</td>
            <td class="px-4 py-2.5 font-mono text-xs text-gray-600">${escapeHtml(record.serial_number)}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(record.product?.name || '-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(record.warehouse?.name || '-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(record.asset_status || '-')}</td>
            <td class="px-4 py-2.5">${getConfigStatusBadge(record.configuration_status)}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${record.active_lock ? escapeHtml(record.active_lock.locked_by.username || '-') : '-'}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${record.active_lock ? formatDateTime(record.active_lock.expires_at) : '-'}</td>
            <td class="px-4 py-2.5 text-center">
                ${record.configuration_status === 'waiting' ? 
                    `<a href="configure.php?router=${encodeURIComponent(record.serial_number)}" 
                        class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors inline-flex" title="Configure">
                        <i class="fas fa-cog text-xs"></i>
                    </a>` : 
                    '<span class="text-[10px] text-gray-400">In Progress</span>'
                }
            </td>
        `;
        tbody.appendChild(row);
    });
    
    showTable('pending');
}

// Export Report
function exportReport(reportType) {
    let url = '';
    const params = new URLSearchParams();
    params.append('export', 'csv');
    
    switch (reportType) {
        case 'configurations':
            url = `${API_URL}/reports/configurations.php`;
            const dateFrom = document.getElementById('config-date-from').value;
            const dateTo = document.getElementById('config-date-to').value;
            const configSearch = document.getElementById('config-search').value.trim();
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            if (configSearch) params.append('search', configSearch);
            break;
            
        case 'ip_usage':
            url = `${API_URL}/reports/ip_usage.php`;
            const status = document.getElementById('ip-status').value;
            const ipSearch = document.getElementById('ip-search').value.trim();
            if (status) params.append('status', status);
            if (ipSearch) params.append('search', ipSearch);
            break;
            
        case 'pending':
            url = `${API_URL}/reports/pending.php`;
            const pendingSearch = document.getElementById('pending-search').value.trim();
            if (pendingSearch) params.append('search', pendingSearch);
            break;
    }
    
    // Trigger download
    window.location.href = `${url}?${params}`;
}

// Clear filters
function clearConfigFilters() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    document.getElementById('config-date-from').value = formatDateForInput(thirtyDaysAgo);
    document.getElementById('config-date-to').value = formatDateForInput(today);
    document.getElementById('config-search').value = '';
    loadConfigurationReport();
}

function clearIPFilters() {
    document.getElementById('ip-status').value = '';
    document.getElementById('ip-search').value = '';
    loadIPUsageReport();
}

function clearPendingFilters() {
    document.getElementById('pending-search').value = '';
    loadPendingReport();
}

// UI Helpers
function showLoading(prefix) {
    document.getElementById(`${prefix}-loading`).classList.remove('hidden');
    document.getElementById(`${prefix}-empty`).classList.add('hidden');
    document.getElementById(`${prefix}-table`).classList.add('hidden');
}

function showEmpty(prefix) {
    document.getElementById(`${prefix}-loading`).classList.add('hidden');
    document.getElementById(`${prefix}-empty`).classList.remove('hidden');
    document.getElementById(`${prefix}-table`).classList.add('hidden');
}

function showTable(prefix) {
    document.getElementById(`${prefix}-loading`).classList.add('hidden');
    document.getElementById(`${prefix}-empty`).classList.add('hidden');
    document.getElementById(`${prefix}-table`).classList.remove('hidden');
}

function getStatusBadge(status) {
    const badges = {
        'available': '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded-full">Available</span>',
        'locked': '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded-full">Locked</span>',
        'configured': '<span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-700 rounded-full">Configured</span>'
    };
    return badges[status] || `<span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded-full">${status}</span>`;
}

function getConfigStatusBadge(status) {
    const badges = {
        'in_progress': '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-700 rounded-full">In Progress</span>',
        'waiting': '<span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded-full">Waiting</span>'
    };
    return badges[status] || `<span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded-full">${status}</span>`;
}

// Utility functions
function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString();
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    showToast(message, 'error');
}

function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
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

// Unbind Configuration
async function unbindConfiguration(bindingId, routerSerial) {
    const reason = prompt(`Are you sure you want to unbind the IP from router "${routerSerial}"?\n\nPlease enter a reason for unbinding:`);
    
    if (reason === null) {
        return; // User cancelled
    }
    
    if (!reason.trim()) {
        showError('Please provide a reason for unbinding');
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/binding_unbind.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                binding_id: bindingId,
                reason: reason.trim(),
                confirm: true
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('IP successfully unbound from router', 'success');
            // Reload the configuration report
            state.configData = null;
            loadConfigurationReport();
        } else {
            showError(data.error?.message || data.message || 'Failed to unbind configuration');
        }
    } catch (error) {
        console.error('Error unbinding configuration:', error);
        showError('Failed to unbind configuration. Please try again.');
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
