<?php
/**
 * Router Configuration Page
 * 
 * Router selection dropdown with serial numbers
 * Auto-assigned IP display
 * Start/Complete/Cancel buttons
 * Timer showing remaining lock time
 * 
 * Requirements: 2.1, 3.1, 4.1
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
$pageTitle = 'Router Configuration';
$currentPage = 'configuration_configure';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Configuration'],
    ['label' => 'Configure Router']
];

ob_start();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Configuration Panel -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Router Configuration</h3>
                <p class="text-sm text-gray-500">Select a router and configure its IP address</p>
            </div>
            
            <!-- Step 1: Router Selection -->
            <div id="step-1" class="p-6">
                <div class="flex items-center mb-4">
                    <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center font-semibold mr-3">1</div>
                    <h4 class="text-lg font-medium text-gray-800">Select Router</h4>
                </div>
                
                <div class="mb-4">
                    <label for="router-select" class="block text-sm font-medium text-gray-700 mb-2">Available Routers</label>
                    <select id="router-select" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-lg">
                        <option value="">-- Select a router by serial number --</option>
                    </select>
                    <p id="router-error" class="mt-1 text-sm text-red-500 hidden"></p>
                </div>
                
                <div id="router-info" class="hidden p-4 bg-gray-50 rounded-lg mb-4">
                    <h5 class="font-medium text-gray-700 mb-2">Router Details</h5>
                    <div id="router-details" class="text-sm text-gray-600"></div>
                </div>
                
                <button id="start-config-btn" onclick="startConfiguration()" disabled
                    class="w-full px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-600 transition disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center justify-center">
                    <i class="fas fa-play mr-2"></i>Start Configuration
                </button>
            </div>
            
            <!-- Step 2: IP Assignment (Hidden initially) -->
            <div id="step-2" class="hidden p-6 border-t">
                <div class="flex items-center mb-4">
                    <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center font-semibold mr-3">2</div>
                    <h4 class="text-lg font-medium text-gray-800">Assigned IP Configuration</h4>
                </div>
                
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h5 class="font-semibold text-gray-800">IP Details</h5>
                        <span id="lock-status" class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm">
                            <i class="fas fa-lock mr-1"></i>Locked
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Network IP</p>
                            <p id="assigned-network-ip" class="font-mono text-lg font-semibold text-gray-800">-</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Router IP</p>
                            <p id="assigned-router-ip" class="font-mono text-lg font-semibold text-gray-800">-</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Site IP</p>
                            <p id="assigned-site-ip" class="font-mono text-lg font-semibold text-gray-800">-</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Subnet Mask</p>
                            <p id="assigned-subnet-mask" class="font-mono text-lg font-semibold text-gray-800">-</p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="config-notes" class="block text-sm font-medium text-gray-700 mb-2">Configuration Notes (Optional)</label>
                    <textarea id="config-notes" rows="3" 
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        placeholder="Add any notes about this configuration..."></textarea>
                </div>
                
                <div class="flex gap-4">
                    <button id="complete-config-btn" onclick="completeConfiguration()"
                        class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center">
                        <i class="fas fa-check mr-2"></i>Complete Configuration
                    </button>
                    <button id="cancel-config-btn" onclick="cancelConfiguration()"
                        class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Success (Hidden initially) -->
            <div id="step-3" class="hidden p-6 border-t">
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-4xl text-green-500"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-800 mb-2">Configuration Complete!</h4>
                    <p class="text-gray-600 mb-6">The router has been successfully configured with the assigned IP.</p>
                    <button onclick="resetConfiguration()" class="px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-plus mr-2"></i>Configure Another Router
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Timer & Info Panel -->
    <div class="lg:col-span-1">
        <!-- Timer Card -->
        <div id="timer-card" class="hidden bg-white rounded-xl shadow-sm mb-6">
            <div class="p-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-clock text-primary mr-2"></i>Lock Timer
                </h4>
                <div class="text-center">
                    <div id="timer-display" class="text-5xl font-bold text-primary mb-2">20:00</div>
                    <p class="text-sm text-gray-500">Time remaining to complete configuration</p>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div id="timer-progress" class="bg-primary h-3 rounded-full transition-all duration-1000" style="width: 100%"></div>
                    </div>
                </div>
                <div id="timer-warning" class="hidden mt-4 p-3 bg-red-50 rounded-lg">
                    <p class="text-sm text-red-700"><i class="fas fa-exclamation-triangle mr-1"></i>Less than 5 minutes remaining!</p>
                </div>
            </div>
        </div>
        
        <!-- Current Session Info -->
        <div id="session-info" class="hidden bg-white rounded-xl shadow-sm mb-6">
            <div class="p-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-info-circle text-primary mr-2"></i>Session Info
                </h4>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Router Serial</p>
                        <p id="session-router" class="font-mono font-medium text-gray-800">-</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Lock ID</p>
                        <p id="session-lock-id" class="font-medium text-gray-800">-</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Started At</p>
                        <p id="session-started" class="font-medium text-gray-800">-</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar text-primary mr-2"></i>Quick Stats
                </h4>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <span class="text-sm text-green-700">Available IPs</span>
                        <span id="stat-available-ips" class="font-semibold text-green-800">-</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                        <span class="text-sm text-yellow-700">Locked IPs</span>
                        <span id="stat-locked-ips" class="font-semibold text-yellow-800">-</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <span class="text-sm text-blue-700">Configured IPs</span>
                        <span id="stat-configured-ips" class="font-semibold text-blue-800">-</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>

// State management
const state = {
    routers: [],
    selectedRouter: null,
    currentSession: null,
    lockId: null,
    ipMasterId: null,
    timerInterval: null,
    expiresAt: null
};

// API base URL
const API_URL = '../api/configuration';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAvailableRouters();
    loadQuickStats();
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    document.getElementById('router-select').addEventListener('change', function(e) {
        const serialNumber = e.target.value;
        if (serialNumber) {
            state.selectedRouter = state.routers.find(r => r.serial_number === serialNumber);
            showRouterInfo();
            document.getElementById('start-config-btn').disabled = false;
        } else {
            state.selectedRouter = null;
            hideRouterInfo();
            document.getElementById('start-config-btn').disabled = true;
        }
    });
}

// Load available routers
async function loadAvailableRouters() {
    try {
        const response = await fetch(`${API_URL}/router_available.php`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.routers = data.data.routers || [];
            populateRouterDropdown();
        } else {
            showError(data.error?.message || 'Failed to load routers');
        }
    } catch (error) {
        console.error('Error loading routers:', error);
        showError('Failed to load available routers');
    }
}

// Populate router dropdown
function populateRouterDropdown() {
    const select = document.getElementById('router-select');
    select.innerHTML = '<option value="">-- Select a router by serial number --</option>';
    
    if (state.routers.length === 0) {
        select.innerHTML += '<option value="" disabled>No routers available for configuration</option>';
        return;
    }
    
    state.routers.forEach(router => {
        const option = document.createElement('option');
        option.value = router.serial_number;
        option.textContent = `${router.serial_number}${router.product_name ? ' - ' + router.product_name : ''}`;
        select.appendChild(option);
    });
}

// Show router info
function showRouterInfo() {
    if (!state.selectedRouter) return;
    
    const infoDiv = document.getElementById('router-info');
    const detailsDiv = document.getElementById('router-details');
    
    detailsDiv.innerHTML = `
        <div class="grid grid-cols-2 gap-2">
            <div><span class="text-gray-500">Serial:</span> <span class="font-mono">${escapeHtml(state.selectedRouter.serial_number)}</span></div>
            ${state.selectedRouter.product_name ? `<div><span class="text-gray-500">Product:</span> ${escapeHtml(state.selectedRouter.product_name)}</div>` : ''}
            ${state.selectedRouter.warehouse_name ? `<div><span class="text-gray-500">Warehouse:</span> ${escapeHtml(state.selectedRouter.warehouse_name)}</div>` : ''}
            ${state.selectedRouter.status ? `<div><span class="text-gray-500">Status:</span> ${escapeHtml(state.selectedRouter.status)}</div>` : ''}
        </div>
    `;
    
    infoDiv.classList.remove('hidden');
}

// Hide router info
function hideRouterInfo() {
    document.getElementById('router-info').classList.add('hidden');
}

// Load quick stats
async function loadQuickStats() {
    try {
        const response = await fetch(`${API_URL}/dashboard.php`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            const ipStats = data.data.ip_stats;
            document.getElementById('stat-available-ips').textContent = ipStats.available || 0;
            document.getElementById('stat-locked-ips').textContent = ipStats.locked || 0;
            document.getElementById('stat-configured-ips').textContent = ipStats.configured || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Start configuration
async function startConfiguration() {
    if (!state.selectedRouter) {
        showError('Please select a router first');
        return;
    }
    
    const startBtn = document.getElementById('start-config-btn');
    startBtn.disabled = true;
    startBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Starting...';
    
    try {
        const response = await fetch(`${API_URL}/configuration_start.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                router_serial_number: state.selectedRouter.serial_number
            })
        });
        
        const data = await response.json();
        console.log('Start configuration response:', data); // Debug log
        
        if (data.success) {
            state.currentSession = data.data;
            state.lockId = data.data.lock_id;
            state.ipMasterId = data.data.ip_master?.id || data.data.ip_master_id;
            
            // Parse expires_at - handle both ISO string and MySQL datetime format
            const expiresAtStr = data.data.expires_at;
            if (expiresAtStr) {
                // Try parsing as ISO string first, then as MySQL datetime
                state.expiresAt = new Date(expiresAtStr.replace(' ', 'T'));
                if (isNaN(state.expiresAt.getTime())) {
                    // Fallback: calculate from remaining_seconds
                    state.expiresAt = new Date(Date.now() + (data.data.remaining_seconds || 1200) * 1000);
                }
            } else {
                // Fallback: 20 minutes from now
                state.expiresAt = new Date(Date.now() + 20 * 60 * 1000);
            }
            
            // Update UI
            showStep2(data.data);
            startTimer();
            showSuccess('Configuration session started');
        } else {
            showError(data.error?.message || data.message || 'Failed to start configuration');
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="fas fa-play mr-2"></i>Start Configuration';
        }
    } catch (error) {
        console.error('Error starting configuration:', error);
        showError('Failed to start configuration. Please try again.');
        startBtn.disabled = false;
        startBtn.innerHTML = '<i class="fas fa-play mr-2"></i>Start Configuration';
    }
}

// Show step 2 with assigned IP
function showStep2(sessionData) {
    // Update IP display - API returns ip_master, not ip_details
    const ipData = sessionData.ip_master || sessionData.ip_details || {};
    document.getElementById('assigned-network-ip').textContent = ipData.network_ip || '-';
    document.getElementById('assigned-router-ip').textContent = ipData.router_ip || '-';
    document.getElementById('assigned-site-ip').textContent = ipData.site_ip || '-';
    document.getElementById('assigned-subnet-mask').textContent = ipData.subnet_mask || '-';
    
    // Update session info
    document.getElementById('session-router').textContent = state.selectedRouter.serial_number;
    document.getElementById('session-lock-id').textContent = sessionData.lock_id;
    document.getElementById('session-started').textContent = new Date().toLocaleTimeString();
    
    // Show/hide elements
    document.getElementById('step-1').classList.add('opacity-50', 'pointer-events-none');
    document.getElementById('step-2').classList.remove('hidden');
    document.getElementById('timer-card').classList.remove('hidden');
    document.getElementById('session-info').classList.remove('hidden');
    
    // Disable router selection
    document.getElementById('router-select').disabled = true;
}

// Start countdown timer
function startTimer() {
    const LOCK_DURATION = 20 * 60; // 20 minutes in seconds
    
    function updateTimer() {
        const now = new Date();
        const remaining = Math.max(0, Math.floor((state.expiresAt - now) / 1000));
        
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        
        document.getElementById('timer-display').textContent = 
            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Update progress bar
        const progress = (remaining / LOCK_DURATION) * 100;
        document.getElementById('timer-progress').style.width = `${progress}%`;
        
        // Change color based on time remaining
        const timerDisplay = document.getElementById('timer-display');
        const progressBar = document.getElementById('timer-progress');
        
        if (remaining <= 300) { // 5 minutes
            timerDisplay.classList.remove('text-primary', 'text-yellow-500');
            timerDisplay.classList.add('text-red-500');
            progressBar.classList.remove('bg-primary', 'bg-yellow-500');
            progressBar.classList.add('bg-red-500');
            document.getElementById('timer-warning').classList.remove('hidden');
        } else if (remaining <= 600) { // 10 minutes
            timerDisplay.classList.remove('text-primary', 'text-red-500');
            timerDisplay.classList.add('text-yellow-500');
            progressBar.classList.remove('bg-primary', 'bg-red-500');
            progressBar.classList.add('bg-yellow-500');
        }
        
        if (remaining <= 0) {
            clearInterval(state.timerInterval);
            handleTimeout();
        }
    }
    
    updateTimer();
    state.timerInterval = setInterval(updateTimer, 1000);
}

// Handle timeout
function handleTimeout() {
    showError('Configuration session has timed out. The IP lock has been released.');
    resetConfiguration();
}

// Complete configuration
async function completeConfiguration() {
    if (!state.lockId) {
        showError('No active configuration session');
        return;
    }
    
    const completeBtn = document.getElementById('complete-config-btn');
    completeBtn.disabled = true;
    completeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Completing...';
    
    try {
        const notes = document.getElementById('config-notes').value.trim();
        
        const response = await fetch(`${API_URL}/configuration_complete.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                lock_id: state.lockId,
                notes: notes || null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            clearInterval(state.timerInterval);
            showStep3();
            showSuccess('Configuration completed successfully!');
            loadQuickStats();
        } else {
            showError(data.error?.message || 'Failed to complete configuration');
            completeBtn.disabled = false;
            completeBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Complete Configuration';
        }
    } catch (error) {
        console.error('Error completing configuration:', error);
        showError('Failed to complete configuration. Please try again.');
        completeBtn.disabled = false;
        completeBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Complete Configuration';
    }
}

// Cancel configuration
async function cancelConfiguration() {
    if (!state.lockId) {
        resetConfiguration();
        return;
    }
    
    if (!confirm('Are you sure you want to cancel this configuration? The IP will be released.')) {
        return;
    }
    
    const cancelBtn = document.getElementById('cancel-config-btn');
    cancelBtn.disabled = true;
    cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Cancelling...';
    
    try {
        const response = await fetch(`${API_URL}/configuration_cancel.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                lock_id: state.lockId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            clearInterval(state.timerInterval);
            showSuccess('Configuration cancelled. IP has been released.');
            resetConfiguration();
            loadQuickStats();
        } else {
            showError(data.error?.message || 'Failed to cancel configuration');
            cancelBtn.disabled = false;
            cancelBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Cancel';
        }
    } catch (error) {
        console.error('Error cancelling configuration:', error);
        showError('Failed to cancel configuration. Please try again.');
        cancelBtn.disabled = false;
        cancelBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Cancel';
    }
}

// Show step 3 (success)
function showStep3() {
    document.getElementById('step-2').classList.add('hidden');
    document.getElementById('step-3').classList.remove('hidden');
    document.getElementById('timer-card').classList.add('hidden');
    document.getElementById('session-info').classList.add('hidden');
}

// Reset configuration
function resetConfiguration() {
    // Clear state
    state.selectedRouter = null;
    state.currentSession = null;
    state.lockId = null;
    state.ipMasterId = null;
    state.expiresAt = null;
    
    if (state.timerInterval) {
        clearInterval(state.timerInterval);
        state.timerInterval = null;
    }
    
    // Reset UI
    document.getElementById('step-1').classList.remove('opacity-50', 'pointer-events-none');
    document.getElementById('step-2').classList.add('hidden');
    document.getElementById('step-3').classList.add('hidden');
    document.getElementById('timer-card').classList.add('hidden');
    document.getElementById('session-info').classList.add('hidden');
    document.getElementById('timer-warning').classList.add('hidden');
    
    // Reset form
    document.getElementById('router-select').value = '';
    document.getElementById('router-select').disabled = false;
    document.getElementById('config-notes').value = '';
    document.getElementById('router-info').classList.add('hidden');
    
    // Reset buttons
    const startBtn = document.getElementById('start-config-btn');
    startBtn.disabled = true;
    startBtn.innerHTML = '<i class="fas fa-play mr-2"></i>Start Configuration';
    
    const completeBtn = document.getElementById('complete-config-btn');
    completeBtn.disabled = false;
    completeBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Complete Configuration';
    
    const cancelBtn = document.getElementById('cancel-config-btn');
    cancelBtn.disabled = false;
    cancelBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Cancel';
    
    // Reset timer display
    document.getElementById('timer-display').textContent = '20:00';
    document.getElementById('timer-display').classList.remove('text-yellow-500', 'text-red-500');
    document.getElementById('timer-display').classList.add('text-primary');
    document.getElementById('timer-progress').style.width = '100%';
    document.getElementById('timer-progress').classList.remove('bg-yellow-500', 'bg-red-500');
    document.getElementById('timer-progress').classList.add('bg-primary');
    
    // Reload routers
    loadAvailableRouters();
}

// Utility functions
function showError(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) {
        CRM.showAlert(message, 'error');
    } else {
        showToast(message, 'error');
    }
}

function showSuccess(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) {
        CRM.showAlert(message, 'success');
    } else {
        showToast(message, 'success');
    }
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
