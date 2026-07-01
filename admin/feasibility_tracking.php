<?php
/**
 * Feasibility Tracking Page
 * Displays all sites with feasibility status, ETA, ADA datetime, and ADA location
 * 
 * Requirements: 8.1, 8.2, 8.3
 * - 8.1: Display all sites with their current feasibility status
 * - 8.2: Display ETA date/time, ADA date/time, and ADA location for each site
 * - 8.3: Filter feasibility records by status
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check permission - ADV users, admins, and contractors can access
$currentUser = $sessionService->getCurrentUser();
$userType = $currentUser['user_type'] ?? '';
$roleId = $currentUser['role_id'] ?? 0;
$isSystemAdmin = $currentUser['is_system_admin'] ?? false;

$canAccess = $isSystemAdmin || $roleId <= 2 || $userType === 'adv' || $userType === 'contractor';

if (!$canAccess) {
    $_SESSION['flash_error'] = 'You do not have permission to access feasibility tracking';
    header('Location: ../dashboard.php');
    exit;
}

$baseUrl = '..';
$pageTitle = 'Feasibility Tracking';
$currentPage = 'feasibility_tracking';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Feasibility Tracking']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Feasibility Tracking</h1>
            <p class="text-gray-500 mt-1">Monitor engineer progress and site assessment status</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="refreshData()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
            <button onclick="exportData()" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export Excel
            </button>
        </div>
    </div>

    <!-- Status Summary Cards - Row 1: Feasibility Progress -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Total -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total</p>
                    <p id="count-total" class="text-2xl font-bold text-gray-800 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                    <i class="fas fa-list text-gray-500"></i>
                </div>
            </div>
        </div>
        
        <!-- Pending ETA -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending_eta')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Pending ETA</p>
                    <p id="count-pending-eta" class="text-2xl font-bold text-red-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <i class="fas fa-clock text-red-500"></i>
                </div>
            </div>
        </div>
        
        <!-- ETA Submitted -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('eta_submitted')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">ETA Submitted</p>
                    <p id="count-eta-submitted" class="text-2xl font-bold text-yellow-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-calendar-check text-yellow-500"></i>
                </div>
            </div>
        </div>
        
        <!-- ADA Submitted -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('ada_submitted')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">ADA Submitted</p>
                    <p id="count-ada-submitted" class="text-2xl font-bold text-blue-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-map-marker-alt text-blue-500"></i>
                </div>
            </div>
        </div>
        
        <!-- Feasibility Completed -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('feasibility_completed')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Feasibility Done</p>
                    <p id="count-completed" class="text-2xl font-bold text-purple-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-clipboard-check text-purple-500"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Summary Cards - Row 2: Approval Workflow -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
        <!-- Pending Contractor Review -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('pending_contractor_review')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Pending Contractor</p>
                    <p id="count-pending-contractor" class="text-2xl font-bold text-yellow-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-yellow-500"></i>
                </div>
            </div>
        </div>
        
        <!-- Contractor Approved -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('contractor_approved')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Contractor Approved</p>
                    <p id="count-contractor-approved" class="text-2xl font-bold text-blue-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-user-check text-blue-500"></i>
                </div>
            </div>
        </div>
        
        <!-- Contractor Rejected -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('contractor_rejected')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Contractor Rejected</p>
                    <p id="count-contractor-rejected" class="text-2xl font-bold text-red-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <i class="fas fa-user-times text-red-500"></i>
                </div>
            </div>
        </div>
        
        <!-- ADV Approved -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('adv_approved')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">ADV Approved</p>
                    <p id="count-adv-approved" class="text-2xl font-bold text-green-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <i class="fas fa-check-double text-green-500"></i>
                </div>
            </div>
        </div>
        
        <!-- ADV Rejected -->
        <div class="bg-white rounded-xl shadow-sm p-4 cursor-pointer hover:shadow-md transition" onclick="filterByStatus('adv_rejected')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">ADV Rejected</p>
                    <p id="count-adv-rejected" class="text-2xl font-bold text-orange-600 mt-1">-</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-times-circle text-orange-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="filter-status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary" onchange="applyFilters()">
                    <option value="">All Statuses</option>
                    <optgroup label="Feasibility Progress">
                        <option value="pending_eta">Pending ETA</option>
                        <option value="eta_submitted">ETA Submitted</option>
                        <option value="ada_submitted">ADA Submitted</option>
                        <option value="feasibility_completed">Feasibility Completed</option>
                    </optgroup>
                    <optgroup label="Approval Workflow">
                        <option value="pending_contractor_review">Pending Contractor Review</option>
                        <option value="contractor_approved">Contractor Approved</option>
                        <option value="contractor_rejected">Contractor Rejected</option>
                        <option value="adv_approved">ADV Approved</option>
                        <option value="adv_rejected">ADV Rejected</option>
                    </optgroup>
                </select>
            </div>
            
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" id="filter-search" placeholder="Site name, LHO, city..." 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    onkeyup="debounceSearch()">
            </div>
            
            <!-- Date From -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                <input type="date" id="filter-date-from" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    onchange="applyFilters()">
            </div>
            
            <!-- Date To -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                <input type="date" id="filter-date-to" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                    onchange="applyFilters()">
            </div>
        </div>
        
        <div class="mt-4 flex justify-end">
            <button onclick="clearFilters()" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition">
                <i class="fas fa-times mr-2"></i>Clear Filters
            </button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Customer/Bank</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Contractor</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Progress</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Approval</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">ETA/ADA</th>
                        <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="tracking-tbody" class="bg-white divide-y divide-gray-100">
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-500">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                            <p>Loading data...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
            <div class="text-sm text-gray-500">
                Showing <span id="showing-from">0</span> to <span id="showing-to">0</span> of <span id="total-records">0</span> results
            </div>
            <div class="flex items-center gap-2">
                <button id="btn-prev" onclick="prevPage()" disabled class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span id="page-info" class="text-sm text-gray-600">Page 1 of 1</span>
                <button id="btn-next" onclick="nextPage()" disabled class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div id="details-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Feasibility Details</h3>
            <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="details-content" class="p-6">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<script>
// State
let currentPage = 1;
let totalPages = 1;
let searchTimeout = null;

// API URL
const API_URL = '../api/feasibility';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadData();
});

// Load tracking data
async function loadData() {
    try {
        const filters = getFilters();
        const params = new URLSearchParams(filters);
        
        const response = await fetch(`${API_URL}/tracking.php?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateStatusCounts(data.data.status_counts);
            renderTable(data.data.tracking);
            updatePagination(data.data.pagination);
        } else {
            showError(data.error?.message || 'Failed to load data');
        }
    } catch (error) {
        console.error('Error loading data:', error);
        showError('Failed to load tracking data');
    }
}

// Get current filters
function getFilters() {
    return {
        status: document.getElementById('filter-status').value,
        search: document.getElementById('filter-search').value,
        date_from: document.getElementById('filter-date-from').value,
        date_to: document.getElementById('filter-date-to').value,
        page: currentPage,
        limit: 20
    };
}

// Update status counts
function updateStatusCounts(counts) {
    document.getElementById('count-total').textContent = counts.total || 0;
    document.getElementById('count-pending-eta').textContent = counts.pending_eta || 0;
    document.getElementById('count-eta-submitted').textContent = counts.eta_submitted || 0;
    document.getElementById('count-ada-submitted').textContent = counts.ada_submitted || 0;
    document.getElementById('count-completed').textContent = counts.feasibility_completed || 0;
    // Approval workflow counts
    document.getElementById('count-pending-contractor').textContent = counts.pending_contractor_review || 0;
    document.getElementById('count-contractor-approved').textContent = counts.contractor_approved || 0;
    document.getElementById('count-contractor-rejected').textContent = counts.contractor_rejected || 0;
    document.getElementById('count-adv-approved').textContent = counts.adv_approved || 0;
    document.getElementById('count-adv-rejected').textContent = counts.adv_rejected || 0;
}

// Render table
function renderTable(data) {
    const tbody = document.getElementById('tracking-tbody');
    
    if (!data || data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="px-4 py-12 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                    <p>No records found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (currentPage - 1) * 20;
    
    tbody.innerHTML = data.map((row, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="text-xs font-medium text-gray-900">${escapeHtml(row.site_name || '-')}</div>
                <div class="text-[10px] text-gray-500">${escapeHtml(row.lho || '')}</div>
                <div class="text-[10px] text-gray-400 truncate max-w-[150px]" title="${escapeHtml(row.address || '')}">${escapeHtml(row.address || '-')}</div>
            </td>
            <td class="px-4 py-2.5">
                <div class="text-xs text-gray-900">${escapeHtml(row.customer_name || '-')}</div>
                <div class="text-[10px] text-gray-500">${escapeHtml(row.bank_name || '-')}</div>
            </td>
            <td class="px-4 py-2.5">
                <div class="text-xs text-gray-900">${escapeHtml(row.contractor_name || '-')}</div>
                <div class="text-[10px] text-gray-500">${escapeHtml(row.engineer_name || '-')}</div>
            </td>
            <td class="px-4 py-2.5">
                <div class="text-xs text-gray-900">${escapeHtml(row.city || '-')}</div>
                <div class="text-[10px] text-gray-500">${escapeHtml(row.state || '')}${row.zone ? ' | ' + escapeHtml(row.zone) : ''}</div>
            </td>
            <td class="px-4 py-2.5">
                ${getStatusBadge(row.feasibility_status)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getApprovalStatusBadge(row.approval_status)}
            </td>
            <td class="px-4 py-2.5">
                <div class="text-[10px] text-gray-600">
                    <div><span class="text-gray-400">ETA:</span> ${formatDateShort(row.eta_datetime)}</div>
                    <div><span class="text-gray-400">ADA:</span> ${formatDateShort(row.ada_datetime)}</div>
                </div>
            </td>
            <td class="px-4 py-2.5">
                ${getActionButtons(row)}
            </td>
        </tr>
    `).join('');
}

// Get action buttons based on status
function getActionButtons(row) {
    let buttons = [];
    
    // Site History button - always show
    buttons.push(`
        <button onclick="viewSiteHistory(${row.site_id}, ${row.assignment_id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Site Timeline">
            <i class="fas fa-stream text-xs"></i>
        </button>
    `);
    
    // View button - redirect to shared view page
    if (row.feasibility_check_id) {
        buttons.push(`
            <a href="../shared/feasibility_view.php?id=${row.feasibility_check_id}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View Details">
                <i class="fas fa-eye text-xs"></i>
            </a>
        `);
        
        // Review button - show for any status that's not already ADV approved/rejected
        // ADV can do final approval directly (bypassing contractor if needed)
        if (!['adv_approved', 'adv_rejected'].includes(row.approval_status)) {
            buttons.push(`
                <a href="../adv/feasibility_review.php?id=${row.feasibility_check_id}" class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Final Approval">
                    <i class="fas fa-check-double text-xs"></i>
                </a>
            `);
        }
        
        // Initiate Installation button - show when ADV approved and no installation exists
        if (row.approval_status === 'adv_approved') {
            if (row.installation_id) {
                // Installation exists - show link to view it
                buttons.push(`
                    <a href="../installation/view.php?id=${row.installation_id}" class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors" title="View Installation">
                        <i class="fas fa-tools text-xs"></i>
                    </a>
                `);
            } else {
                // No installation - show initiate button
                buttons.push(`
                    <button onclick="initiateInstallation(${row.site_id}, ${row.feasibility_check_id})" class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors" title="Initiate Installation">
                        <i class="fas fa-play-circle text-xs"></i>
                    </button>
                `);
            }
        }
    }
    
    return `<div class="flex items-center justify-center gap-0.5">${buttons.join('')}</div>`;
}

// Get status badge HTML
function getStatusBadge(status) {
    if (!status) return '<span class="inline-flex items-center px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full text-[10px] font-medium">Not Started</span>';
    
    const badges = {
        'pending_eta': '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>Pending ETA</span>',
        'eta_submitted': '<span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-yellow-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-1"></span>ETA Submitted</span>',
        'ada_submitted': '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1"></span>ADA Submitted</span>',
        'feasibility_completed': '<span class="inline-flex items-center px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-1"></span>Completed</span>'
    };
    return badges[status] || '<span class="inline-flex items-center px-2 py-0.5 bg-gray-50 text-gray-500 rounded-full text-[10px] font-medium">' + status + '</span>';
}

// Get approval status badge HTML
function getApprovalStatusBadge(status) {
    if (!status) return '<span class="text-gray-400 text-[10px]">-</span>';
    
    const badges = {
        'pending_contractor_review': '<span class="inline-flex items-center px-2 py-0.5 bg-yellow-50 text-yellow-600 rounded-full text-[10px] font-medium whitespace-nowrap"><span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-1"></span>Pending Contractor</span>',
        'contractor_approved': '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium whitespace-nowrap"><span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1"></span>Contractor OK</span>',
        'contractor_rejected': '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium whitespace-nowrap"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>Contractor Rejected</span>',
        'adv_approved': '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium whitespace-nowrap"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>ADV Approved</span>',
        'adv_rejected': '<span class="inline-flex items-center px-2 py-0.5 bg-orange-50 text-orange-600 rounded-full text-[10px] font-medium whitespace-nowrap"><span class="w-1.5 h-1.5 bg-orange-500 rounded-full mr-1"></span>ADV Rejected</span>'
    };
    return badges[status] || '<span class="inline-flex items-center px-2 py-0.5 bg-gray-50 text-gray-600 rounded-full text-[10px] font-medium">' + status + '</span>';
}

// Format date time
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Format date short
function formatDateShort(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Update pagination
function updatePagination(pagination) {
    currentPage = pagination.page;
    totalPages = pagination.total_pages;
    
    const from = pagination.total > 0 ? (pagination.page - 1) * pagination.limit + 1 : 0;
    const to = Math.min(pagination.page * pagination.limit, pagination.total);
    
    document.getElementById('showing-from').textContent = from;
    document.getElementById('showing-to').textContent = to;
    document.getElementById('total-records').textContent = pagination.total;
    document.getElementById('page-info').textContent = `Page ${pagination.page} of ${pagination.total_pages || 1}`;
    
    document.getElementById('btn-prev').disabled = pagination.page <= 1;
    document.getElementById('btn-next').disabled = pagination.page >= pagination.total_pages;
}

// Filter by status (from cards)
function filterByStatus(status) {
    document.getElementById('filter-status').value = status;
    currentPage = 1;
    loadData();
}

// Apply filters
function applyFilters() {
    currentPage = 1;
    loadData();
}

// Debounce search
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage = 1;
        loadData();
    }, 300);
}

// Clear filters
function clearFilters() {
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    currentPage = 1;
    loadData();
}

// Pagination
function prevPage() {
    if (currentPage > 1) {
        currentPage--;
        loadData();
    }
}

function nextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        loadData();
    }
}

// Refresh data
function refreshData() {
    loadData();
}

// Export data
async function exportData() {
    try {
        const filters = getFilters();
        delete filters.page;
        delete filters.limit;
        const params = new URLSearchParams(filters);
        
        window.location.href = `${API_URL}/export.php?${params}`;
    } catch (error) {
        console.error('Export error:', error);
        showError('Failed to export data');
    }
}

// View details
async function viewDetails(assignmentId) {
    try {
        const response = await fetch(`../api/feasibility/details.php?assignment_id=${assignmentId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            showDetailsModal(data.data);
        } else {
            showError(data.error?.message || 'Failed to load feasibility details');
        }
    } catch (error) {
        console.error('Error loading details:', error);
        showError('Failed to load feasibility details');
    }
}

// Show details modal
function showDetailsModal(data) {
    const content = document.getElementById('details-content');
    content.innerHTML = `
        <div class="space-y-6">
            <!-- Site Info -->
            <div>
                <h4 class="text-sm font-medium text-gray-500 uppercase mb-3">Site Information</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Site Name:</span> <span class="font-medium">${escapeHtml(data.site_name || '-')}</span></div>
                    <div><span class="text-gray-500">LHO:</span> <span class="font-medium">${escapeHtml(data.lho || '-')}</span></div>
                    <div><span class="text-gray-500">City:</span> <span class="font-medium">${escapeHtml(data.city || '-')}</span></div>
                    <div><span class="text-gray-500">State:</span> <span class="font-medium">${escapeHtml(data.state || '-')}</span></div>
                </div>
            </div>
            
            <!-- ATM Info -->
            <div>
                <h4 class="text-sm font-medium text-gray-500 uppercase mb-3">ATM Information</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">No. of ATMs:</span> <span class="font-medium">${data.no_of_atm || '-'}</span></div>
                    <div><span class="text-gray-500">ATM 1:</span> <span class="font-medium">${escapeHtml(data.atm_id_1 || '-')} (${escapeHtml(data.atm_1_status || '-')})</span></div>
                </div>
            </div>
            
            <!-- Network Info -->
            <div>
                <h4 class="text-sm font-medium text-gray-500 uppercase mb-3">Network Information</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Operator:</span> <span class="font-medium">${escapeHtml(data.operator || '-')}</span></div>
                    <div><span class="text-gray-500">Signal Status:</span> <span class="font-medium">${escapeHtml(data.signal_status || '-')}</span></div>
                </div>
            </div>
            
            <!-- Power Info -->
            <div>
                <h4 class="text-sm font-medium text-gray-500 uppercase mb-3">Power Infrastructure</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">UPS Available:</span> <span class="font-medium">${escapeHtml(data.ups_available || '-')}</span></div>
                    <div><span class="text-gray-500">No. of UPS:</span> <span class="font-medium">${data.no_of_ups || '-'}</span></div>
                    <div><span class="text-gray-500">Earthing:</span> <span class="font-medium">${escapeHtml(data.earthing || '-')}</span></div>
                    <div><span class="text-gray-500">Earthing Voltage:</span> <span class="font-medium">${escapeHtml(data.earthing_voltage || '-')}</span></div>
                </div>
            </div>
            
            <!-- Remarks -->
            ${data.remarks ? `
            <div>
                <h4 class="text-sm font-medium text-gray-500 uppercase mb-3">Remarks</h4>
                <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg">${escapeHtml(data.remarks)}</p>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('details-modal').classList.remove('hidden');
}

// Close details modal
function closeDetailsModal() {
    document.getElementById('details-modal').classList.add('hidden');
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show error
function showError(message) {
    alert(message);
}

// Show success message
function showSuccess(message) {
    alert(message);
}

// Initiate installation for a site
async function initiateInstallation(siteId, feasibilityId) {
    if (!confirm('Are you sure you want to initiate installation for this site?')) {
        return;
    }
    
    try {
        const response = await fetch('../api/installation/initiate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                site_id: siteId,
                feasibility_id: feasibilityId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Installation initiated successfully');
            // Redirect to installation form
            setTimeout(() => {
                window.location.href = `../installation/form.php?id=${data.data.installation_id}`;
            }, 1000);
        } else {
            showError(data.error?.message || 'Failed to initiate installation');
        }
    } catch (error) {
        console.error('Error initiating installation:', error);
        showError('Failed to initiate installation. Please try again.');
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailsModal();
    }
});

// Close modal on backdrop click
document.getElementById('details-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailsModal();
    }
});

// View review history
async function viewHistory(feasibilityId) {
    try {
        const response = await fetch(`../api/feasibility/review.php?feasibility_id=${feasibilityId}&action=history`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            // API returns { history: [...], feasibility_id: ..., total_reviews: ... }
            showHistoryModal(data.data.history || [], feasibilityId);
        } else {
            showError(data.error?.message || 'Failed to load review history');
        }
    } catch (error) {
        console.error('Error loading history:', error);
        showError('Failed to load review history');
    }
}

// Show history modal
function showHistoryModal(history, feasibilityId) {
    const content = document.getElementById('details-content');
    
    if (!history || history.length === 0) {
        content.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No review history found</p>
                <p class="text-sm text-gray-400 mt-2">This feasibility check has not been reviewed yet</p>
            </div>
        `;
    } else {
        content.innerHTML = `
            <div class="space-y-4">
                <h4 class="text-sm font-medium text-gray-500 uppercase mb-4">Review Audit Trail</h4>
                ${history.map((review, index) => `
                    <div class="border rounded-lg p-4 ${review.review_type === 'approval' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-1 rounded text-xs font-medium ${review.review_type === 'approval' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                                    ${review.review_type === 'approval' ? '<i class="fas fa-check mr-1"></i>Approved' : '<i class="fas fa-times mr-1"></i>Rejected'}
                                </span>
                                <span class="text-sm text-gray-600">by ${escapeHtml(review.reviewer_name || 'Unknown')}</span>
                                <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">${formatRole(review.reviewer_role)}</span>
                            </div>
                            <span class="text-xs text-gray-500">${formatDateTime(review.reviewed_at)}</span>
                        </div>
                        ${review.review_type === 'rejection' ? `
                            <div class="mt-2 text-sm">
                                <p class="text-gray-700"><strong>Type:</strong> ${review.rejection_type === 'section_specific' ? 'Section-Specific' : 'Overall'}</p>
                                ${review.rejection_type === 'section_specific' && review.rejected_sections ? `
                                    <p class="text-gray-700"><strong>Sections:</strong> ${formatSections(review.rejected_sections)}</p>
                                ` : ''}
                                <p class="text-gray-700 mt-1"><strong>Reason:</strong> ${escapeHtml(review.reason || 'N/A')}</p>
                            </div>
                        ` : review.comments ? `
                            <p class="text-sm text-gray-700 mt-2"><strong>Comments:</strong> ${escapeHtml(review.comments)}</p>
                        ` : ''}
                    </div>
                `).join('')}
            </div>
            <div class="mt-6 pt-4 border-t">
                <a href="../adv/feasibility_review.php?id=${feasibilityId}" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition inline-flex items-center">
                    <i class="fas fa-external-link-alt mr-2"></i>View Full Details
                </a>
            </div>
        `;
    }
    
    document.getElementById('details-modal').classList.remove('hidden');
}

// Format role for display
function formatRole(role) {
    if (!role) return 'Unknown';
    return role.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

// Format sections for display
function formatSections(sections) {
    if (!sections) return 'N/A';
    const sectionLabels = {
        'atm_information': 'ATM Information',
        'network_information': 'Network Information',
        'power_infrastructure': 'Power Infrastructure',
        'electrical_measurements': 'Electrical Measurements',
        'site_access': 'Site Access',
        'environmental_factors': 'Environmental Factors',
        'remarks': 'Remarks'
    };
    
    let sectionArray = sections;
    if (typeof sections === 'string') {
        try {
            sectionArray = JSON.parse(sections);
        } catch (e) {
            return sections;
        }
    }
    
    return sectionArray.map(s => sectionLabels[s] || s).join(', ');
}

// View site history timeline
async function viewSiteHistory(siteId, assignmentId) {
    try {
        const response = await fetch(`../api/feasibility/site-history.php?site_id=${siteId}&assignment_id=${assignmentId}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            showSiteHistoryModal(data.data);
        } else {
            showError(data.error?.message || 'Failed to load site history');
        }
    } catch (error) {
        console.error('Error loading site history:', error);
        showError('Failed to load site history');
    }
}

// Show site history modal
function showSiteHistoryModal(data) {
    const content = document.getElementById('details-content');
    const timeline = data.timeline || [];
    
    let timelineHtml = '';
    if (timeline.length === 0) {
        timelineHtml = `
            <div class="text-center py-8">
                <i class="fas fa-stream text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No history found for this site</p>
            </div>
        `;
    } else {
        timelineHtml = `
            <div class="relative">
                <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                <div class="space-y-6">
                    ${timeline.map((event, index) => `
                        <div class="relative flex items-start ml-4">
                            <div class="absolute -left-4 w-8 h-8 rounded-full ${getEventColor(event.event_type)} flex items-center justify-center">
                                <i class="fas ${getEventIcon(event.event_type)} text-white text-xs"></i>
                            </div>
                            <div class="ml-8 bg-white border rounded-lg p-4 flex-1 shadow-sm">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium text-gray-800">${escapeHtml(event.event_title)}</span>
                                    <span class="text-xs text-gray-500">${formatDateTime(event.event_date)}</span>
                                </div>
                                ${event.event_description ? `<p class="text-sm text-gray-600">${escapeHtml(event.event_description)}</p>` : ''}
                                ${event.performed_by ? `<p class="text-xs text-gray-400 mt-2">By: ${escapeHtml(event.performed_by)}</p>` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    content.innerHTML = `
        <div class="mb-4">
            <h4 class="text-lg font-semibold text-gray-800">${escapeHtml(data.site_name || 'Site')} - Timeline</h4>
            <p class="text-sm text-gray-500">${escapeHtml(data.lho || '')} | ${escapeHtml(data.city || '')}</p>
        </div>
        ${timelineHtml}
    `;
    
    document.getElementById('details-modal').classList.remove('hidden');
}

// Get event color based on type
function getEventColor(eventType) {
    const colors = {
        'site_added': 'bg-blue-500',
        'delegated': 'bg-purple-500',
        'accepted': 'bg-green-500',
        'assigned': 'bg-indigo-500',
        'eta_submitted': 'bg-yellow-500',
        'ada_submitted': 'bg-cyan-500',
        'feasibility_completed': 'bg-teal-500',
        'contractor_approved': 'bg-green-600',
        'contractor_rejected': 'bg-red-500',
        'adv_approved': 'bg-green-700',
        'adv_rejected': 'bg-orange-500',
        'resubmitted': 'bg-blue-600'
    };
    return colors[eventType] || 'bg-gray-500';
}

// Get event icon based on type
function getEventIcon(eventType) {
    const icons = {
        'site_added': 'fa-plus',
        'delegated': 'fa-share',
        'accepted': 'fa-handshake',
        'assigned': 'fa-user-plus',
        'eta_submitted': 'fa-calendar-check',
        'ada_submitted': 'fa-map-marker-alt',
        'feasibility_completed': 'fa-clipboard-check',
        'contractor_approved': 'fa-user-check',
        'contractor_rejected': 'fa-user-times',
        'adv_approved': 'fa-check-double',
        'adv_rejected': 'fa-times-circle',
        'resubmitted': 'fa-redo'
    };
    return icons[eventType] || 'fa-circle';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
