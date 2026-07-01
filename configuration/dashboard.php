<?php
/**
 * Configuration Dashboard Page
 * 
 * Router statistics cards
 * IP statistics cards
 * Locked IPs table with countdown
 * Recent activities list
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4
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
$pageTitle = 'Configuration Dashboard';
$currentPage = 'configuration_dashboard';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Configuration'],
    ['label' => 'Dashboard']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Configuration Dashboard</h2>
            <p class="text-gray-500">Monitor IP configuration status and activities</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="refreshDashboard()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
            <a href="configure.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-cog mr-2"></i>Configure Router
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards Row 1: Router Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Routers -->
        <a href="reports.php" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-server text-xl text-blue-500"></i>
                </div>
                <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Routers</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="router-total" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">Total Routers</p>
                </div>
                <i class="fas fa-arrow-right text-gray-300"></i>
            </div>
        </a>
        
        <!-- Configured Routers -->
        <a href="reports.php?tab=configurations" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-xl text-green-500"></i>
                </div>
                <span id="router-configured-pct" class="text-xs font-medium text-green-500">-</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="router-configured" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">Configured</p>
                </div>
                <i class="fas fa-arrow-right text-gray-300"></i>
            </div>
        </a>
        
        <!-- Unconfigured Routers -->
        <a href="reports.php?tab=pending" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-minus-circle text-xl text-gray-500"></i>
                </div>
                <span id="router-unconfigured-pct" class="text-xs font-medium text-gray-500">-</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="router-unconfigured" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">Unconfigured</p>
                </div>
                <i class="fas fa-arrow-right text-gray-300"></i>
            </div>
        </a>
        
        <!-- In Progress -->
        <div onclick="scrollToLockedIPs()" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-spinner text-xl text-yellow-500"></i>
                </div>
                <span id="router-inprogress-pct" class="text-xs font-medium text-yellow-500">-</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="router-inprogress" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">In Progress</p>
                </div>
                <i class="fas fa-arrow-down text-gray-300"></i>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards Row 2: IP Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total IPs -->
        <a href="ip_master.php" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-network-wired text-xl text-indigo-500"></i>
                </div>
                <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">IP Pool</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="ip-total" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">Total IPs</p>
                </div>
                <i class="fas fa-arrow-right text-gray-300"></i>
            </div>
        </a>
        
        <!-- Available IPs -->
        <a href="ip_master.php?status=available" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-wifi text-xl text-emerald-500"></i>
                </div>
                <span id="ip-available-pct" class="text-xs font-medium text-emerald-500">-</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="ip-available" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">Available</p>
                </div>
                <i class="fas fa-arrow-right text-gray-300"></i>
            </div>
        </a>
        
        <!-- Locked IPs -->
        <div onclick="scrollToLockedIPs()" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-lock text-xl text-amber-500"></i>
                </div>
                <span id="ip-locked-pct" class="text-xs font-medium text-amber-500">-</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="ip-locked" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">Locked</p>
                </div>
                <i class="fas fa-arrow-down text-gray-300"></i>
            </div>
        </div>
        
        <!-- Configured IPs -->
        <a href="ip_master.php?status=configured" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer block">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-link text-xl text-purple-500"></i>
                </div>
                <span id="ip-configured-pct" class="text-xs font-medium text-purple-500">-</span>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <p id="ip-configured" class="text-3xl font-bold text-gray-800">-</p>
                    <p class="text-sm text-gray-500">Configured</p>
                </div>
                <i class="fas fa-arrow-right text-gray-300"></i>
            </div>
        </a>
    </div>
    
    <!-- IP Utilization Bar -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">IP Utilization</h3>
            <span id="ip-utilization-pct" class="text-lg font-bold text-primary">-</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
            <div class="h-4 rounded-full flex">
                <div id="utilization-configured" class="bg-purple-500 transition-all duration-500" style="width: 0%"></div>
                <div id="utilization-locked" class="bg-amber-500 transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>
        <div class="flex items-center justify-center gap-6 mt-4 text-sm">
            <div class="flex items-center">
                <div class="w-3 h-3 bg-purple-500 rounded-full mr-2"></div>
                <span class="text-gray-600">Configured</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-amber-500 rounded-full mr-2"></div>
                <span class="text-gray-600">Locked</span>
            </div>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-gray-200 rounded-full mr-2"></div>
                <span class="text-gray-600">Available</span>
            </div>
        </div>
    </div>

    
    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Locked IPs Table -->
        <div id="locked-ips-section" class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Currently Locked IPs</h3>
                    <p class="text-sm text-gray-500">Active configuration sessions</p>
                </div>
                <span id="locked-count-badge" class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-sm font-medium">
                    0 Active
                </span>
            </div>
            <div class="p-6">
                <div id="locked-ips-container">
                    <div id="locked-ips-loading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-gray-400 mb-2"></i>
                        <p class="text-gray-500">Loading locked IPs...</p>
                    </div>
                    <div id="locked-ips-empty" class="hidden text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-unlock text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500">No IPs currently locked</p>
                        <p class="text-sm text-gray-400">All IPs are available for configuration</p>
                    </div>
                    <div id="locked-ips-table" class="hidden overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th class="pb-3">IP Details</th>
                                    <th class="pb-3">Router</th>
                                    <th class="pb-3">User</th>
                                    <th class="pb-3 text-right">Time Left</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="locked-ips-tbody" class="divide-y divide-gray-100">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Recent Activities</h3>
                    <p class="text-sm text-gray-500">Latest configuration events</p>
                </div>
                <a href="audit.php" class="text-sm text-primary hover:underline">View All</a>
            </div>
            <div class="p-6">
                <div id="activities-container">
                    <div id="activities-loading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-gray-400 mb-2"></i>
                        <p class="text-gray-500">Loading activities...</p>
                    </div>
                    <div id="activities-empty" class="hidden text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-history text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500">No recent activities</p>
                        <p class="text-sm text-gray-400">Configuration activities will appear here</p>
                    </div>
                    <div id="activities-list" class="hidden space-y-4 max-h-96 overflow-y-auto">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="configure.php" class="flex flex-col items-center p-4 bg-blue-50 rounded-xl hover:bg-blue-100 transition">
                <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-cog text-xl text-white"></i>
                </div>
                <span class="text-sm font-medium text-gray-700">Configure Router</span>
            </a>
            <a href="ip_master.php" class="flex flex-col items-center p-4 bg-indigo-50 rounded-xl hover:bg-indigo-100 transition">
                <div class="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-network-wired text-xl text-white"></i>
                </div>
                <span class="text-sm font-medium text-gray-700">Manage IPs</span>
            </a>
            <a href="reports.php" class="flex flex-col items-center p-4 bg-green-50 rounded-xl hover:bg-green-100 transition">
                <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-chart-bar text-xl text-white"></i>
                </div>
                <span class="text-sm font-medium text-gray-700">View Reports</span>
            </a>
            <a href="audit.php" class="flex flex-col items-center p-4 bg-purple-50 rounded-xl hover:bg-purple-100 transition">
                <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center mb-3">
                    <i class="fas fa-history text-xl text-white"></i>
                </div>
                <span class="text-sm font-medium text-gray-700">Audit History</span>
            </a>
        </div>
    </div>
    
    <!-- Last Updated -->
    <div class="text-center text-sm text-gray-400">
        <span>Last updated: </span>
        <span id="last-updated">-</span>
        <span class="mx-2">|</span>
        <span>Auto-refresh: </span>
        <button id="auto-refresh-toggle" onclick="toggleAutoRefresh()" class="text-primary hover:underline">
            <span id="auto-refresh-status">Enabled</span>
        </button>
    </div>
</div>


<script>
// State management
const state = {
    autoRefresh: true,
    refreshInterval: null,
    lockedIPs: [],
    countdownIntervals: []
};

// API base URL
const API_URL = '../api/configuration';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    startAutoRefresh();
});

// Load all dashboard data
async function loadDashboardData() {
    try {
        const response = await fetch(`${API_URL}/dashboard.php?recent_activities_limit=10`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateRouterStats(data.data.router_stats);
            updateIPStats(data.data.ip_stats);
            updateIPUtilization(data.data.ip_utilization);
            updateLockedIPs(data.data.locked_ips);
            updateRecentActivities(data.data.recent_activities);
            updateLastUpdated(data.data.generated_at);
        } else {
            showError(data.error?.message || 'Failed to load dashboard data');
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError('Failed to load dashboard data');
    }
}

// Update router statistics
function updateRouterStats(stats) {
    document.getElementById('router-total').textContent = stats.total;
    document.getElementById('router-configured').textContent = stats.configured;
    document.getElementById('router-unconfigured').textContent = stats.unconfigured;
    document.getElementById('router-inprogress').textContent = stats.in_progress;
    
    // Update percentages
    document.getElementById('router-configured-pct').textContent = stats.percentages.configured + '%';
    document.getElementById('router-unconfigured-pct').textContent = stats.percentages.unconfigured + '%';
    document.getElementById('router-inprogress-pct').textContent = stats.percentages.in_progress + '%';
}

// Update IP statistics
function updateIPStats(stats) {
    document.getElementById('ip-total').textContent = stats.total;
    document.getElementById('ip-available').textContent = stats.available;
    document.getElementById('ip-locked').textContent = stats.locked;
    document.getElementById('ip-configured').textContent = stats.configured;
    
    // Update percentages
    document.getElementById('ip-available-pct').textContent = stats.percentages.available + '%';
    document.getElementById('ip-locked-pct').textContent = stats.percentages.locked + '%';
    document.getElementById('ip-configured-pct').textContent = stats.percentages.configured + '%';
}

// Update IP utilization bar
function updateIPUtilization(utilization) {
    document.getElementById('ip-utilization-pct').textContent = utilization.utilization_percentage + '%';
    
    const total = utilization.total || 1;
    const configuredPct = (utilization.utilized - (state.lockedIPs?.length || 0)) / total * 100;
    const lockedPct = (state.lockedIPs?.length || 0) / total * 100;
    
    document.getElementById('utilization-configured').style.width = configuredPct + '%';
    document.getElementById('utilization-locked').style.width = lockedPct + '%';
}

// Update locked IPs table
function updateLockedIPs(lockedIPs) {
    // Clear existing countdown intervals
    state.countdownIntervals.forEach(interval => clearInterval(interval));
    state.countdownIntervals = [];
    
    state.lockedIPs = lockedIPs;
    
    const loadingEl = document.getElementById('locked-ips-loading');
    const emptyEl = document.getElementById('locked-ips-empty');
    const tableEl = document.getElementById('locked-ips-table');
    const tbody = document.getElementById('locked-ips-tbody');
    const badge = document.getElementById('locked-count-badge');
    
    loadingEl.classList.add('hidden');
    
    if (!lockedIPs || lockedIPs.length === 0) {
        emptyEl.classList.remove('hidden');
        tableEl.classList.add('hidden');
        badge.textContent = '0 Active';
        return;
    }
    
    emptyEl.classList.add('hidden');
    tableEl.classList.remove('hidden');
    badge.textContent = lockedIPs.length + ' Active';
    
    tbody.innerHTML = '';
    
    lockedIPs.forEach((lock, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        row.innerHTML = `
            <td class="py-3">
                <div class="font-mono text-sm">
                    <div class="text-gray-800">${escapeHtml(lock.ip_details.network_ip)}</div>
                    <div class="text-gray-500 text-xs">${escapeHtml(lock.ip_details.router_ip)}</div>
                </div>
            </td>
            <td class="py-3">
                <span class="font-mono text-sm text-gray-700">${escapeHtml(lock.router_serial_number)}</span>
            </td>
            <td class="py-3">
                <span class="text-sm text-gray-600">${escapeHtml(lock.locked_by.username || 'User #' + lock.locked_by.user_id)}</span>
            </td>
            <td class="py-3 text-right">
                <span id="countdown-${index}" class="font-mono text-sm font-medium ${getCountdownColor(lock.remaining_time.seconds)}">
                    ${lock.remaining_time.formatted}
                </span>
            </td>
            <td class="py-3 text-right">
                <button onclick="unlockIP(${lock.lock_id}, '${escapeHtml(lock.router_serial_number)}')" 
                    class="px-2 py-1 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition"
                    title="Force unlock this IP">
                    <i class="fas fa-unlock-alt mr-1"></i>Unlock
                </button>
            </td>
        `;
        tbody.appendChild(row);
        
        // Start countdown for this lock
        startCountdown(index, lock.remaining_time.seconds);
    });
}

// Start countdown timer for a locked IP
function startCountdown(index, initialSeconds) {
    let seconds = initialSeconds;
    
    const interval = setInterval(() => {
        seconds--;
        
        if (seconds <= 0) {
            clearInterval(interval);
            // Refresh dashboard when a lock expires
            loadDashboardData();
            return;
        }
        
        const el = document.getElementById(`countdown-${index}`);
        if (el) {
            el.textContent = formatTime(seconds);
            el.className = `font-mono text-sm font-medium ${getCountdownColor(seconds)}`;
        }
    }, 1000);
    
    state.countdownIntervals.push(interval);
}

// Format seconds to mm:ss
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// Get countdown color based on remaining time
function getCountdownColor(seconds) {
    if (seconds <= 300) return 'text-red-500'; // 5 minutes
    if (seconds <= 600) return 'text-yellow-500'; // 10 minutes
    return 'text-green-500';
}

// Update recent activities list
function updateRecentActivities(activities) {
    const loadingEl = document.getElementById('activities-loading');
    const emptyEl = document.getElementById('activities-empty');
    const listEl = document.getElementById('activities-list');
    
    loadingEl.classList.add('hidden');
    
    if (!activities || activities.length === 0) {
        emptyEl.classList.remove('hidden');
        listEl.classList.add('hidden');
        return;
    }
    
    emptyEl.classList.add('hidden');
    listEl.classList.remove('hidden');
    
    listEl.innerHTML = '';
    
    activities.forEach(activity => {
        const item = document.createElement('div');
        item.className = 'flex items-start space-x-3 p-3 rounded-lg hover:bg-gray-50 transition';
        
        const iconInfo = getActivityIcon(activity.action_type);
        
        item.innerHTML = `
            <div class="w-10 h-10 ${iconInfo.bgColor} rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas ${iconInfo.icon} ${iconInfo.textColor}"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800">${getActivityLabel(activity.action_type)}</p>
                <p class="text-xs text-gray-500 truncate">
                    ${activity.router_serial_number ? 'Router: ' + escapeHtml(activity.router_serial_number) : ''}
                    ${activity.ip_details.network_ip ? ' | IP: ' + escapeHtml(activity.ip_details.network_ip) : ''}
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    ${activity.user.username || 'System'} • ${formatDateTime(activity.created_at)}
                </p>
            </div>
        `;
        listEl.appendChild(item);
    });
}

// Get activity icon and colors
function getActivityIcon(actionType) {
    const icons = {
        'lock_acquired': { icon: 'fa-lock', bgColor: 'bg-yellow-100', textColor: 'text-yellow-500' },
        'lock_released': { icon: 'fa-unlock', bgColor: 'bg-gray-100', textColor: 'text-gray-500' },
        'lock_expired': { icon: 'fa-clock', bgColor: 'bg-red-100', textColor: 'text-red-500' },
        'configured': { icon: 'fa-check', bgColor: 'bg-green-100', textColor: 'text-green-500' },
        'unbound': { icon: 'fa-unlink', bgColor: 'bg-orange-100', textColor: 'text-orange-500' },
        'ip_created': { icon: 'fa-plus', bgColor: 'bg-blue-100', textColor: 'text-blue-500' },
        'ip_updated': { icon: 'fa-edit', bgColor: 'bg-indigo-100', textColor: 'text-indigo-500' },
        'ip_deleted': { icon: 'fa-trash', bgColor: 'bg-red-100', textColor: 'text-red-500' },
        'bulk_upload': { icon: 'fa-upload', bgColor: 'bg-purple-100', textColor: 'text-purple-500' }
    };
    return icons[actionType] || { icon: 'fa-info', bgColor: 'bg-gray-100', textColor: 'text-gray-500' };
}

// Get activity label
function getActivityLabel(actionType) {
    const labels = {
        'lock_acquired': 'IP Lock Acquired',
        'lock_released': 'IP Lock Released',
        'lock_expired': 'IP Lock Expired',
        'configured': 'Router Configured',
        'unbound': 'IP Unbound',
        'ip_created': 'IP Created',
        'ip_updated': 'IP Updated',
        'ip_deleted': 'IP Deleted',
        'bulk_upload': 'Bulk IP Upload'
    };
    return labels[actionType] || actionType;
}

// Format date time
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Update last updated timestamp
function updateLastUpdated(timestamp) {
    document.getElementById('last-updated').textContent = new Date(timestamp).toLocaleTimeString();
}

// Scroll to locked IPs section
function scrollToLockedIPs() {
    const section = document.getElementById('locked-ips-section');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Highlight the section briefly
        section.classList.add('ring-2', 'ring-primary', 'ring-offset-2');
        setTimeout(() => {
            section.classList.remove('ring-2', 'ring-primary', 'ring-offset-2');
        }, 2000);
    }
}

// Refresh dashboard
function refreshDashboard() {
    const btn = document.querySelector('[onclick="refreshDashboard()"]');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...';
    btn.disabled = true;
    
    loadDashboardData().finally(() => {
        btn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Refresh';
        btn.disabled = false;
    });
}

// Auto refresh functionality
function startAutoRefresh() {
    if (state.refreshInterval) {
        clearInterval(state.refreshInterval);
    }
    
    if (state.autoRefresh) {
        state.refreshInterval = setInterval(loadDashboardData, 30000); // Refresh every 30 seconds
    }
}

function toggleAutoRefresh() {
    state.autoRefresh = !state.autoRefresh;
    document.getElementById('auto-refresh-status').textContent = state.autoRefresh ? 'Enabled' : 'Disabled';
    
    if (state.autoRefresh) {
        startAutoRefresh();
    } else if (state.refreshInterval) {
        clearInterval(state.refreshInterval);
        state.refreshInterval = null;
    }
}

// Utility functions
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

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Force unlock an IP
async function unlockIP(lockId, routerSerial) {
    if (!confirm(`Are you sure you want to force unlock the IP for router "${routerSerial}"?\n\nThis will cancel the active configuration session.`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/configuration_cancel.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                lock_id: lockId,
                force: true
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('IP successfully unlocked', 'success');
            loadDashboardData();
        } else {
            showError(data.error?.message || data.message || 'Failed to unlock IP');
        }
    } catch (error) {
        console.error('Error unlocking IP:', error);
        showError('Failed to unlock IP. Please try again.');
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (state.refreshInterval) {
        clearInterval(state.refreshInterval);
    }
    state.countdownIntervals.forEach(interval => clearInterval(interval));
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
