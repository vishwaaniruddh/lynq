<?php
/**
 * Engineer Installation Sites Page
 * 
 * Displays installations assigned to the logged-in engineer with:
 * - Status, ETA/ADA dates
 * - ETA/ADA submission forms based on status
 * - Installation button after ADA submission
 * 
 * Requirements: 3.1, 3.2, 3.4, 3.6
 * - 3.1: Display sites with status "pending_eta" or "pending_ada" assigned to engineer
 * - 3.2: Display ETA input form for pending_eta sites
 * - 3.4: Display ADA input form for pending_ada sites
 * - 3.6: Display Installation button/link after ADA submission
 */

require_once __DIR__ . '/../../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../../views/auth/login.php');
    exit;
}

// Check engineer access - only contractor users can access this page
if (!isEngineerUser()) {
    $_SESSION['flash_error'] = 'Access denied. Engineer users only.';
    header('Location: ../../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '../..';
$pageTitle = 'My Installation Sites';
$currentPage = 'engineer_installations';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../../dashboard.php'],
    ['label' => 'My Installation Sites']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">My Installation Sites</h3>
            <p class="text-sm text-gray-500">View and manage installation sites assigned to you</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="refreshData()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="p-4 border-b bg-gray-50">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-4">
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-clipboard-list text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total</p>
                        <p id="total-count" class="text-xl font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending_eta')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-calendar-alt text-yellow-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pending ETA</p>
                        <p id="pending-eta-count" class="text-xl font-semibold text-yellow-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending_ada')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-calendar-check text-orange-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pending ADA</p>
                        <p id="pending-ada-count" class="text-xl font-semibold text-orange-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending_materials')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-box text-amber-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pending Materials</p>
                        <p id="pending-materials-count" class="text-xl font-semibold text-amber-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('in_progress')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-tools text-purple-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">In Progress</p>
                        <p id="in-progress-count" class="text-xl font-semibold text-purple-600">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border cursor-pointer hover:shadow-md transition" onclick="filterByStatus('submitted')">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-paper-plane text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Submitted</p>
                        <p id="submitted-count" class="text-xl font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by ATM ID, site name, city..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="pending_eta">Pending ETA</option>
                    <option value="pending_ada">Pending ADA</option>
                    <option value="pending_materials">Pending Materials</option>
                    <option value="materials_received">Materials Received</option>
                    <option value="in_progress">In Progress</option>
                    <option value="submitted">Submitted</option>
                    <option value="contractor_approved">Contractor Approved</option>
                    <option value="contractor_rejected">Contractor Rejected</option>
                    <option value="adv_approved">ADV Approved</option>
                    <option value="adv_rejected">ADV Rejected</option>
                </select>
            </div>
            <button onclick="clearFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </button>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading installations...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="installations-table" class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Site / ATM ID</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Location</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">ETA / ADA</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Assigned</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody id="installations-tbody" class="divide-y">
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">Loading...</td>
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


<!-- ETA Modal - Requirements 3.2, 3.3 -->
<div id="eta-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeETAModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Submit ETA (Estimated Time of Arrival)</h3>
                <button onclick="closeETAModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <!-- Site Info -->
                <div id="eta-site-info" class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <!-- Populated by JavaScript -->
                </div>
                
                <!-- ETA Date Input -->
                <div class="mb-4">
                    <label for="eta-date" class="block text-sm font-medium text-gray-700 mb-2">
                        ETA Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="eta-date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <p id="eta-error" class="hidden mt-1 text-sm text-red-500"></p>
                </div>
                
                <p class="text-sm text-gray-500"><i class="fas fa-info-circle mr-1"></i>ETA must be today or a future date</p>
                
                <input type="hidden" id="eta-installation-id" value="">
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeETAModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button id="eta-submit-btn" onclick="submitETA()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-calendar-alt mr-2"></i>Submit ETA
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ADA Modal - Requirements 3.4, 3.5 -->
<div id="ada-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeADAModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Submit ADA (Actual Date of Arrival)</h3>
                <button onclick="closeADAModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <!-- Site Info -->
                <div id="ada-site-info" class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <!-- Populated by JavaScript -->
                </div>
                
                <!-- ETA Info -->
                <div id="ada-eta-info" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                        <span class="text-sm text-blue-700">ETA Date: <strong id="ada-eta-date"></strong></span>
                    </div>
                </div>
                
                <!-- ADA Date Input -->
                <div class="mb-4">
                    <label for="ada-date" class="block text-sm font-medium text-gray-700 mb-2">
                        ADA Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="ada-date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <p id="ada-error" class="hidden mt-1 text-sm text-red-500"></p>
                </div>
                
                <p class="text-sm text-gray-500"><i class="fas fa-info-circle mr-1"></i>ADA date cannot be before ETA date</p>
                
                <input type="hidden" id="ada-installation-id" value="">
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeADAModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button id="ada-submit-btn" onclick="submitADA()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-calendar-check mr-2"></i>Submit ADA
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Installation Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5 max-h-[60vh] overflow-y-auto">
                <!-- Content populated by JavaScript -->
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>


<script>
// State management
const state = {
    installations: [],
    pagination: { page: 1, limit: 20, total: 0, totalPages: 0 },
    filters: { search: '', status: '' },
    counts: {}
};

// API URLs
const API_URL = '../../api/installation/engineer-list.php';
const ETA_API_URL = '../../api/installation/submit-eta.php';
const ADA_API_URL = '../../api/installation/submit-ada.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadInstallations();
    setupEventListeners();
    setMinDate();
});

// Set minimum date for ETA to today
function setMinDate() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('eta-date').setAttribute('min', today);
}

// Setup event listeners
function setupEventListeners() {
    // Search with debounce
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadInstallations();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadInstallations();
    });
}

// Filter by status (from stats cards)
function filterByStatus(status) {
    state.filters.status = status;
    document.getElementById('status-filter').value = status;
    state.pagination.page = 1;
    loadInstallations();
}

// Clear all filters
function clearFilters() {
    state.filters = { search: '', status: '' };
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    state.pagination.page = 1;
    loadInstallations();
}

// Refresh data
function refreshData() {
    loadInstallations();
}

// Load installations from API
async function loadInstallations() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.installations = data.data.installations || [];
            state.pagination = {
                page: data.data.page || 1,
                limit: data.data.limit || 20,
                total: data.data.total || 0,
                totalPages: data.data.totalPages || 0
            };
            
            // Store pending counts from API
            state.counts.pending_eta = data.data.pending_eta_count || 0;
            state.counts.pending_ada = data.data.pending_ada_count || 0;
            
            renderTable();
            renderPagination();
            updateStats();
        } else {
            showError(data.error?.message || data.message || 'Failed to load installations');
        }
    } catch (error) {
        console.error('Error loading installations:', error);
        showError('Failed to load installations. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Update stats display
function updateStats() {
    // Calculate counts from current page installations
    const counts = {
        total: state.pagination.total,
        pending_eta: 0,
        pending_ada: 0,
        pending_materials: 0,
        in_progress: 0,
        submitted: 0
    };
    
    state.installations.forEach(inst => {
        if (inst.status === 'pending_eta') counts.pending_eta++;
        else if (inst.status === 'pending_ada') counts.pending_ada++;
        else if (inst.status === 'pending_materials') counts.pending_materials++;
        else if (inst.status === 'in_progress' || inst.status === 'materials_received') counts.in_progress++;
        else if (['submitted', 'pending_contractor_review', 'contractor_approved', 'adv_approved'].includes(inst.status)) counts.submitted++;
    });
    
    // Use API counts for pending_eta and pending_ada if available
    if (state.counts.pending_eta !== undefined) counts.pending_eta = state.counts.pending_eta;
    if (state.counts.pending_ada !== undefined) counts.pending_ada = state.counts.pending_ada;
    
    document.getElementById('total-count').textContent = counts.total;
    document.getElementById('pending-eta-count').textContent = counts.pending_eta;
    document.getElementById('pending-ada-count').textContent = counts.pending_ada;
    document.getElementById('pending-materials-count').textContent = counts.pending_materials;
    document.getElementById('in-progress-count').textContent = counts.in_progress;
    document.getElementById('submitted-count').textContent = counts.submitted;
}

// Render table
function renderTable() {
    const tbody = document.getElementById('installations-tbody');
    
    if (state.installations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
                    <p>No installations found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = state.installations.map(inst => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 text-gray-600">${inst.id}</td>
            <td class="px-6 py-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-tools text-indigo-500"></i>
                    </div>
                    <div>
                        <span class="font-medium text-gray-800">${escapeHtml(inst.atm_id || '-')}</span>
                        ${inst.lho ? `<p class="text-xs text-gray-500">${escapeHtml(inst.lho)}</p>` : ''}
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-gray-600">
                <div>
                    <span>${escapeHtml(inst.city || '-')}</span>
                    ${inst.state ? `<p class="text-xs text-gray-400">${escapeHtml(inst.state)}</p>` : ''}
                </div>
            </td>
            <td class="px-6 py-4">
                ${getStatusBadge(inst.status)}
            </td>
            <td class="px-6 py-4">
                ${getETAADAInfo(inst)}
            </td>
            <td class="px-6 py-4 text-gray-600">
                <div>
                    <span class="text-sm">${formatDate(inst.assigned_at)}</span>
                </div>
            </td>
            <td class="px-6 py-4">
                ${getActionButtons(inst)}
            </td>
        </tr>
    `).join('');
}

// Get status badge HTML
function getStatusBadge(status) {
    const statusConfig = {
        pending_eta: { bg: 'bg-yellow-100', text: 'text-yellow-700', icon: 'fa-calendar-alt', label: 'Pending ETA' },
        pending_ada: { bg: 'bg-orange-100', text: 'text-orange-700', icon: 'fa-calendar-check', label: 'Pending ADA' },
        pending_materials: { bg: 'bg-amber-100', text: 'text-amber-700', icon: 'fa-box', label: 'Pending Materials' },
        materials_received: { bg: 'bg-blue-100', text: 'text-blue-700', icon: 'fa-box-open', label: 'Materials Received' },
        in_progress: { bg: 'bg-purple-100', text: 'text-purple-700', icon: 'fa-tools', label: 'In Progress' },
        submitted: { bg: 'bg-cyan-100', text: 'text-cyan-700', icon: 'fa-paper-plane', label: 'Submitted' },
        pending_contractor_review: { bg: 'bg-orange-100', text: 'text-orange-700', icon: 'fa-clipboard-check', label: 'Pending Review' },
        contractor_approved: { bg: 'bg-teal-100', text: 'text-teal-700', icon: 'fa-check-circle', label: 'Contractor Approved' },
        contractor_rejected: { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-times-circle', label: 'Rejected' },
        adv_approved: { bg: 'bg-green-100', text: 'text-green-700', icon: 'fa-check-double', label: 'ADV Approved' },
        adv_rejected: { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-ban', label: 'ADV Rejected' }
    };
    
    const config = statusConfig[status] || { bg: 'bg-gray-100', text: 'text-gray-700', icon: 'fa-question', label: status };
    return `<span class="px-2 py-1 ${config.bg} ${config.text} rounded-full text-xs font-medium">
        <i class="fas ${config.icon} mr-1"></i>${config.label}
    </span>`;
}

// Get ETA/ADA info
function getETAADAInfo(inst) {
    let html = '<div class="text-sm">';
    
    if (inst.eta_date) {
        html += `<div class="flex items-center text-gray-600">
            <span class="text-xs text-gray-400 w-10">ETA:</span>
            <span>${formatDate(inst.eta_date)}</span>
        </div>`;
    }
    
    if (inst.ada_date) {
        html += `<div class="flex items-center text-gray-600">
            <span class="text-xs text-gray-400 w-10">ADA:</span>
            <span>${formatDate(inst.ada_date)}</span>
        </div>`;
    }
    
    if (!inst.eta_date && !inst.ada_date) {
        html += '<span class="text-gray-400 text-xs">-</span>';
    }
    
    html += '</div>';
    return html;
}


// Get action buttons based on status - Requirements 3.2, 3.4, 3.6
function getActionButtons(inst) {
    let buttons = [];
    
    // View button - always available
    buttons.push(`<button onclick="viewInstallation(${inst.id})" class="p-2 text-gray-500 hover:text-primary" title="View Details">
        <i class="fas fa-eye"></i>
    </button>`);
    
    // ETA button - for pending_eta status (Requirement 3.2)
    if (inst.status === 'pending_eta') {
        buttons.push(`<button onclick="openETAModal(${inst.id})" class="px-3 py-1.5 text-xs rounded-lg bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition" title="Submit ETA">
            <i class="fas fa-calendar-alt mr-1"></i>Submit ETA
        </button>`);
    }
    
    // ADA button - for pending_ada status (Requirement 3.4)
    if (inst.status === 'pending_ada') {
        buttons.push(`<button onclick="openADAModal(${inst.id})" class="px-3 py-1.5 text-xs rounded-lg bg-orange-100 text-orange-700 hover:bg-orange-200 transition" title="Submit ADA">
            <i class="fas fa-calendar-check mr-1"></i>Submit ADA
        </button>`);
    }
    
    // Confirm Materials button - for pending_materials status
    if (inst.status === 'pending_materials') {
        buttons.push(`<a href="../form.php?id=${inst.id}&confirm_materials=1" class="px-3 py-1.5 text-xs rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition" title="Confirm Materials">
            <i class="fas fa-box-open mr-1"></i>Confirm Materials
        </a>`);
    }
    
    // Installation button - for materials_received or in_progress status (Requirement 3.6)
    if (['materials_received', 'in_progress'].includes(inst.status)) {
        buttons.push(`<a href="../form.php?id=${inst.id}" class="px-3 py-1.5 text-xs rounded-lg bg-purple-100 text-purple-700 hover:bg-purple-200 transition" title="Installation Form">
            <i class="fas fa-tools mr-1"></i>Installation
        </a>`);
    }
    
    // View Installation button - for submitted and later statuses
    if (['submitted', 'pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected'].includes(inst.status)) {
        buttons.push(`<a href="../view.php?id=${inst.id}" class="px-3 py-1.5 text-xs rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="View Installation">
            <i class="fas fa-file-alt mr-1"></i>View
        </a>`);
    }
    
    // Edit button - for rejected status
    if (['contractor_rejected', 'adv_rejected'].includes(inst.status)) {
        buttons.push(`<a href="../form.php?id=${inst.id}&edit=1" class="px-3 py-1.5 text-xs rounded-lg bg-orange-100 text-orange-700 hover:bg-orange-200 transition" title="Edit & Resubmit">
            <i class="fas fa-edit mr-1"></i>Edit
        </a>`);
    }
    
    return `<div class="flex items-center space-x-1 flex-wrap gap-1">${buttons.join('')}</div>`;
}

// View installation details
function viewInstallation(id) {
    const inst = state.installations.find(i => i.id === id);
    if (!inst) return;
    
    const content = document.getElementById('view-content');
    content.innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b">
                <span class="text-gray-500">Status</span>
                ${getStatusBadge(inst.status)}
            </div>
            
            <div class="pb-3 border-b">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Site Information</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-400">ATM ID:</span>
                        <p class="font-medium text-gray-800">${escapeHtml(inst.atm_id || '-')}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">LHO:</span>
                        <p class="font-medium text-gray-800">${escapeHtml(inst.lho || '-')}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">City:</span>
                        <p class="font-medium text-gray-800">${escapeHtml(inst.city || '-')}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">State:</span>
                        <p class="font-medium text-gray-800">${escapeHtml(inst.state || '-')}</p>
                    </div>
                </div>
                ${inst.address ? `<div class="mt-2"><span class="text-gray-400">Address:</span><p class="text-gray-800">${escapeHtml(inst.address)}</p></div>` : ''}
            </div>
            
            <div class="pb-3 border-b">
                <h4 class="text-sm font-medium text-gray-500 mb-2">ETA / ADA</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-400">ETA Date:</span>
                        <p class="font-medium text-gray-800">${inst.eta_date ? formatDate(inst.eta_date) : '-'}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">ADA Date:</span>
                        <p class="font-medium text-gray-800">${inst.ada_date ? formatDate(inst.ada_date) : '-'}</p>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 class="text-sm font-medium text-gray-500 mb-2">Timeline</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-400">Assigned At:</span>
                        <p class="font-medium text-gray-800">${inst.assigned_at ? formatDate(inst.assigned_at) : '-'}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Delegated At:</span>
                        <p class="font-medium text-gray-800">${inst.delegated_at ? formatDate(inst.delegated_at) : '-'}</p>
                    </div>
                    ${inst.submitted_at ? `
                    <div>
                        <span class="text-gray-400">Submitted At:</span>
                        <p class="font-medium text-gray-800">${formatDate(inst.submitted_at)}</p>
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('view-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close view modal
function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Open ETA modal - Requirement 3.2
function openETAModal(id) {
    const inst = state.installations.find(i => i.id === id);
    if (!inst) return;
    
    document.getElementById('eta-site-info').innerHTML = `
        <div class="flex items-center">
            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-tools text-indigo-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(inst.atm_id || 'N/A')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(inst.lho || '')} • ${escapeHtml(inst.city || '')}, ${escapeHtml(inst.state || '')}</p>
            </div>
        </div>
    `;
    
    document.getElementById('eta-installation-id').value = id;
    document.getElementById('eta-date').value = '';
    document.getElementById('eta-error').classList.add('hidden');
    
    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('eta-date').setAttribute('min', today);
    
    document.getElementById('eta-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close ETA modal
function closeETAModal() {
    document.getElementById('eta-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Submit ETA - Requirement 3.3
async function submitETA() {
    const installationId = document.getElementById('eta-installation-id').value;
    const etaDate = document.getElementById('eta-date').value;
    const errorEl = document.getElementById('eta-error');
    
    // Validate
    if (!etaDate) {
        errorEl.textContent = 'Please select an ETA date';
        errorEl.classList.remove('hidden');
        return;
    }
    
    errorEl.classList.add('hidden');
    
    const submitBtn = document.getElementById('eta-submit-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch(ETA_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                installation_id: parseInt(installationId),
                eta_date: etaDate
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeETAModal();
            showToast('ETA submitted successfully', 'success');
            loadInstallations();
        } else {
            errorEl.textContent = data.error?.message || data.message || 'Failed to submit ETA';
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error submitting ETA:', error);
        errorEl.textContent = 'An error occurred. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}


// Open ADA modal - Requirement 3.4
function openADAModal(id) {
    const inst = state.installations.find(i => i.id === id);
    if (!inst) return;
    
    document.getElementById('ada-site-info').innerHTML = `
        <div class="flex items-center">
            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-tools text-indigo-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(inst.atm_id || 'N/A')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(inst.lho || '')} • ${escapeHtml(inst.city || '')}, ${escapeHtml(inst.state || '')}</p>
            </div>
        </div>
    `;
    
    // Show ETA date
    document.getElementById('ada-eta-date').textContent = inst.eta_date ? formatDate(inst.eta_date) : 'Not set';
    
    document.getElementById('ada-installation-id').value = id;
    document.getElementById('ada-date').value = '';
    document.getElementById('ada-error').classList.add('hidden');
    
    // Set min date to ETA date or today
    const minDate = inst.eta_date || new Date().toISOString().split('T')[0];
    document.getElementById('ada-date').setAttribute('min', minDate);
    
    document.getElementById('ada-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close ADA modal
function closeADAModal() {
    document.getElementById('ada-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Submit ADA - Requirement 3.5
async function submitADA() {
    const installationId = document.getElementById('ada-installation-id').value;
    const adaDate = document.getElementById('ada-date').value;
    const errorEl = document.getElementById('ada-error');
    
    // Validate
    if (!adaDate) {
        errorEl.textContent = 'Please select an ADA date';
        errorEl.classList.remove('hidden');
        return;
    }
    
    errorEl.classList.add('hidden');
    
    const submitBtn = document.getElementById('ada-submit-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch(ADA_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                installation_id: parseInt(installationId),
                ada_date: adaDate
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeADAModal();
            showToast('ADA submitted successfully', 'success');
            loadInstallations();
        } else {
            errorEl.textContent = data.error?.message || data.message || 'Failed to submit ADA';
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error submitting ADA:', error);
        errorEl.textContent = 'An error occurred. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Render pagination
function renderPagination() {
    const { page, limit, total, totalPages } = state.pagination;
    const start = total > 0 ? (page - 1) * limit + 1 : 0;
    const end = Math.min(page * limit, total);
    
    document.getElementById('pagination-info').textContent = 
        total > 0 ? `Showing ${start} to ${end} of ${total} entries` : 'No entries';
    
    const controls = document.getElementById('pagination-controls');
    
    if (totalPages <= 1) {
        controls.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    const maxPages = 5;
    let startPage = Math.max(1, page - Math.floor(maxPages / 2));
    let endPage = Math.min(totalPages, startPage + maxPages - 1);
    
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
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span class="px-2">...</span>`;
        html += `<button onclick="goToPage(${totalPages})" class="px-3 py-1 rounded border hover:bg-gray-100">${totalPages}</button>`;
    }
    
    // Next button
    html += `<button onclick="goToPage(${page + 1})" ${page === totalPages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === totalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

// Go to page
function goToPage(page) {
    if (page < 1 || page > state.pagination.totalPages) return;
    state.pagination.page = page;
    loadInstallations();
}

// Show/hide loading indicator
function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    const table = document.getElementById('installations-table');
    
    if (show) {
        indicator.classList.remove('hidden');
        table.classList.add('hidden');
    } else {
        indicator.classList.add('hidden');
        table.classList.remove('hidden');
    }
}

// Show error message
function showError(message) {
    const tbody = document.getElementById('installations-tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="px-6 py-8 text-center text-red-500">
                <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                <p>${escapeHtml(message)}</p>
            </td>
        </tr>
    `;
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>${escapeHtml(message)}`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layouts/base.php';
?>
