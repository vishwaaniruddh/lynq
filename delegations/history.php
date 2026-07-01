<?php
/**
 * Delegation History View
 * 
 * Displays complete history for a delegation
 * 
 * Requirements: 3.3
 * - Display complete history for a delegation
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Get delegation ID from query parameter
$delegationId = isset($_GET['delegation_id']) ? (int)$_GET['delegation_id'] : 0;

if ($delegationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid delegation ID';
    header('Location: index.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Delegation History';
$currentPage = 'delegations';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => '../sites/index.php'],
    ['label' => 'Delegation Tracking', 'url' => 'index.php'],
    ['label' => 'History']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Delegation History</h3>
            <p class="text-sm text-gray-500">Complete audit trail for delegation #<span id="delegation-id"><?php echo $delegationId; ?></span></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>Back to Tracking
            </a>
        </div>
    </div>

    <!-- Loading indicator -->
    <div id="loading-indicator" class="p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading delegation history...</p>
    </div>

    <!-- Error message -->
    <div id="error-container" class="hidden p-8 text-center">
        <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-3"></i>
        <p id="error-message" class="text-red-600"></p>
        <a href="index.php" class="mt-4 inline-block px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            Back to Delegation Tracking
        </a>
    </div>

    <!-- Content container -->
    <div id="content-container" class="hidden">
        <!-- Delegation Summary Card -->
        <div class="p-6 border-b bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Site Info -->
                <div class="bg-white p-4 rounded-lg border">
                    <div class="flex items-center mb-2">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-map-marker-alt text-blue-500"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Site</span>
                    </div>
                    <p id="site-name" class="font-semibold text-gray-800">-</p>
                    <p id="site-location" class="text-sm text-gray-500">-</p>
                </div>
                
                <!-- Contractor Info -->
                <div class="bg-white p-4 rounded-lg border">
                    <div class="flex items-center mb-2">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-building text-purple-500"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Contractor</span>
                    </div>
                    <p id="contractor-name" class="font-semibold text-gray-800">-</p>
                </div>
                
                <!-- Current Status -->
                <div class="bg-white p-4 rounded-lg border">
                    <div class="flex items-center mb-2">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-info-circle text-green-500"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Current Status</span>
                    </div>
                    <div id="current-status">-</div>
                </div>
                
                <!-- Delegated Date -->
                <div class="bg-white p-4 rounded-lg border">
                    <div class="flex items-center mb-2">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-calendar text-yellow-500"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Delegated</span>
                    </div>
                    <p id="delegated-date" class="font-semibold text-gray-800">-</p>
                    <p id="delegated-by" class="text-sm text-gray-500">-</p>
                </div>
            </div>
        </div>
        
        <!-- Rejection Notes (if rejected) -->
        <div id="rejection-notes-container" class="hidden p-6 border-b bg-red-50">
            <div class="flex items-start">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                    <i class="fas fa-times-circle text-red-500"></i>
                </div>
                <div>
                    <h4 class="font-medium text-red-800 mb-1">Rejection Notes</h4>
                    <p id="rejection-notes" class="text-red-700"></p>
                </div>
            </div>
        </div>

        <!-- History Timeline -->
        <div class="p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Activity Timeline</h4>
            <div id="history-timeline" class="relative">
                <!-- Timeline items will be populated by JavaScript -->
            </div>
            
            <!-- Empty state -->
            <div id="empty-history" class="hidden text-center py-8">
                <i class="fas fa-history text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No history records found</p>
            </div>
        </div>
    </div>
</div>

<script>
const delegationId = <?php echo $delegationId; ?>;
const API_URL = '../api/delegations/history.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDelegationHistory();
});

// Load delegation history from API
async function loadDelegationHistory() {
    showLoading(true);
    
    try {
        const response = await fetch(`${API_URL}?delegation_id=${delegationId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderDelegationDetails(data.data.delegation);
            renderHistoryTimeline(data.data.history);
            showContent();
        } else {
            showError(data.error?.message || 'Failed to load delegation history');
        }
    } catch (error) {
        console.error('Error loading delegation history:', error);
        showError('Failed to load delegation history. Please try again.');
    }
}

// Render delegation details
function renderDelegationDetails(delegation) {
    document.getElementById('site-name').textContent = delegation.site_name || 'N/A';
    document.getElementById('site-location').textContent = 
        [delegation.lho, delegation.city, delegation.state].filter(Boolean).join(' • ') || '-';
    document.getElementById('contractor-name').textContent = delegation.contractor_name || 'N/A';
    document.getElementById('current-status').innerHTML = getStatusBadge(delegation.status);
    document.getElementById('delegated-date').textContent = formatDate(delegation.delegated_at);
    document.getElementById('delegated-by').textContent = delegation.delegated_by_name ? 
        `by ${delegation.delegated_by_name}` : '';
    
    // Show rejection notes if rejected
    if (delegation.status === 'rejected' && delegation.rejection_notes) {
        document.getElementById('rejection-notes').textContent = delegation.rejection_notes;
        document.getElementById('rejection-notes-container').classList.remove('hidden');
    }
}

// Render history timeline
function renderHistoryTimeline(history) {
    const container = document.getElementById('history-timeline');
    const emptyState = document.getElementById('empty-history');
    
    if (!history || history.length === 0) {
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    emptyState.classList.add('hidden');
    
    container.innerHTML = history.map((item, index) => {
        const isLast = index === history.length - 1;
        const actionInfo = getActionInfo(item.action);
        
        return `
            <div class="flex ${!isLast ? 'pb-8' : ''}">
                <!-- Timeline line -->
                <div class="flex flex-col items-center mr-4">
                    <div class="w-10 h-10 ${actionInfo.bgColor} rounded-full flex items-center justify-center z-10">
                        <i class="${actionInfo.icon} ${actionInfo.iconColor}"></i>
                    </div>
                    ${!isLast ? `<div class="w-0.5 h-full bg-gray-200 mt-2"></div>` : ''}
                </div>
                
                <!-- Content -->
                <div class="flex-1 pb-2">
                    <div class="bg-white border rounded-lg p-4 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium ${actionInfo.textColor}">${actionInfo.label}</span>
                            <span class="text-sm text-gray-400">${formatDateTime(item.performed_at)}</span>
                        </div>
                        <p class="text-sm text-gray-600">
                            <span class="font-medium">${escapeHtml(item.performed_by_name || 'System')}</span>
                            ${item.performed_by_email ? `<span class="text-gray-400">(${escapeHtml(item.performed_by_email)})</span>` : ''}
                        </p>
                        ${item.notes ? `
                            <div class="mt-2 p-2 bg-gray-50 rounded text-sm text-gray-600">
                                <i class="fas fa-comment-alt mr-1 text-gray-400"></i>
                                ${escapeHtml(item.notes)}
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Get action display info
function getActionInfo(action) {
    const actions = {
        'created': {
            label: 'Delegation Created',
            icon: 'fas fa-plus',
            bgColor: 'bg-blue-100',
            iconColor: 'text-blue-500',
            textColor: 'text-blue-700'
        },
        'accepted': {
            label: 'Delegation Accepted',
            icon: 'fas fa-check',
            bgColor: 'bg-green-100',
            iconColor: 'text-green-500',
            textColor: 'text-green-700'
        },
        'rejected': {
            label: 'Delegation Rejected',
            icon: 'fas fa-times',
            bgColor: 'bg-red-100',
            iconColor: 'text-red-500',
            textColor: 'text-red-700'
        },
        'reassigned': {
            label: 'Delegation Reassigned',
            icon: 'fas fa-exchange-alt',
            bgColor: 'bg-yellow-100',
            iconColor: 'text-yellow-500',
            textColor: 'text-yellow-700'
        }
    };
    
    return actions[action] || {
        label: action.charAt(0).toUpperCase() + action.slice(1),
        icon: 'fas fa-circle',
        bgColor: 'bg-gray-100',
        iconColor: 'text-gray-500',
        textColor: 'text-gray-700'
    };
}

// Get status badge HTML
function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium"><i class="fas fa-clock mr-1"></i>Pending</span>',
        'accepted': '<span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium"><i class="fas fa-check mr-1"></i>Accepted</span>',
        'rejected': '<span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium"><i class="fas fa-times mr-1"></i>Rejected</span>'
    };
    return badges[status] || `<span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">${status}</span>`;
}

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric'
    });
}

// Format date and time
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Show loading indicator
function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('content-container').classList.add('hidden');
    document.getElementById('error-container').classList.add('hidden');
}

// Show content
function showContent() {
    document.getElementById('loading-indicator').classList.add('hidden');
    document.getElementById('content-container').classList.remove('hidden');
    document.getElementById('error-container').classList.add('hidden');
}

// Show error message
function showError(message) {
    document.getElementById('loading-indicator').classList.add('hidden');
    document.getElementById('content-container').classList.add('hidden');
    document.getElementById('error-container').classList.remove('hidden');
    document.getElementById('error-message').textContent = message;
}

// Escape HTML
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
