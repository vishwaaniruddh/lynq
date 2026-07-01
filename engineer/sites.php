<?php
/**
 * Engineer Assigned Sites List
 * 
 * Displays only sites assigned to logged-in engineer
 * Includes filters for status, location
 * 
 * Requirements: 6.1, 6.4
 * - 6.1: Display only sites assigned to specific engineer
 * - 6.4: Filter assigned sites by status or location
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check engineer access - only contractor users can access this page
if (!isEngineerUser()) {
    $_SESSION['flash_error'] = 'Access denied. Engineer users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'My Assigned Sites';
$currentPage = 'engineer_sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'My Assigned Sites']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <!-- Header -->
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-800">My Assigned Sites</h3>
            <p class="text-xs text-gray-500 mt-0.5">View and manage sites assigned to you for feasibility assessment</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="exportAssignments()" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center text-xs font-medium">
                <i class="fas fa-file-excel mr-1.5"></i>Export
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-3">
            <div onclick="filterByFeasibilityStatus('')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-blue-300 hover:shadow-md transition-all" id="card-total">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-list text-blue-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Total</p>
                        <p id="total-count" class="text-lg font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByFeasibilityStatus('pending_eta')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-yellow-300 hover:shadow-md transition-all" id="card-pending_eta">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-clock text-yellow-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Pending ETA</p>
                        <p id="pending_eta-count" class="text-lg font-semibold text-yellow-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByFeasibilityStatus('eta_submitted')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-orange-300 hover:shadow-md transition-all" id="card-eta_submitted">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-calendar-check text-orange-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">ETA Submitted</p>
                        <p id="eta_submitted-count" class="text-lg font-semibold text-orange-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByFeasibilityStatus('ada_submitted')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-purple-300 hover:shadow-md transition-all" id="card-ada_submitted">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-map-marker-alt text-purple-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">ADA Submitted</p>
                        <p id="ada_submitted-count" class="text-lg font-semibold text-purple-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByFeasibilityStatus('feasibility_completed')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-green-300 hover:shadow-md transition-all" id="card-feasibility_completed">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-green-50 to-green-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-check-circle text-green-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Completed</p>
                        <p id="feasibility_completed-count" class="text-lg font-semibold text-green-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-2">
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="search-input" placeholder="Search by site name, LHO, city..." 
                        class="w-full pl-9 pr-4 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                </div>
            </div>
            <div>
                <select id="lho-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All LHOs</option>
                </select>
            </div>
            <div>
                <select id="feasibility-status-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Status</option>
                    <option value="pending_eta">Pending ETA</option>
                    <option value="eta_submitted">ETA Submitted</option>
                    <option value="ada_submitted">ADA Submitted</option>
                    <option value="feasibility_completed">Completed</option>
                    <option value="pending_contractor_review">Pending Review</option>
                    <option value="contractor_approved">Contractor Approved</option>
                    <option value="contractor_rejected">Contractor Rejected</option>
                    <option value="adv_approved">ADV Approved</option>
                    <option value="adv_rejected">ADV Rejected</option>
                </select>
            </div>
            <div>
                <select id="city-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Cities</option>
                </select>
            </div>
            <div>
                <select id="state-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All States</option>
                </select>
            </div>
            <button onclick="clearFilters()" class="px-3 py-2 text-xs bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </button>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading assignments...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="assignments-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Site</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">LHO</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">ETA/ADA</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="assignments-tbody" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td>
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

<!-- ETA Modal - Requirements 2.1, 2.2, 2.3 -->
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
                <div id="eta-modal-site-info" class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <!-- Site info will be populated by JavaScript -->
                </div>
                
                <div class="mb-4">
                    <label for="eta-date" class="block text-sm font-medium text-gray-700 mb-2">
                        ETA Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="eta-date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div class="mb-4">
                    <label for="eta-time" class="block text-sm font-medium text-gray-700 mb-2">
                        ETA Time <span class="text-red-500">*</span>
                    </label>
                    <input type="time" id="eta-time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <p class="text-sm text-gray-500"><i class="fas fa-info-circle mr-1"></i>ETA must be a future date and time</p>
                
                <input type="hidden" id="eta-modal-assignment-id" value="">
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeETAModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button id="eta-submit-btn" onclick="submitETA()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    Submit ETA
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ADA Modal - Requirements 3.1, 3.2, 3.3, 3.4 -->
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
                <div id="ada-modal-site-info" class="mb-4 p-4 bg-gray-50 rounded-lg">
                    <!-- Site info will be populated by JavaScript -->
                </div>
                
                <div id="ada-location-status" class="mb-4 p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-spinner fa-spin text-blue-500 mr-3"></i>
                        <span class="text-blue-700">Requesting location access...</span>
                    </div>
                </div>
                
                <div id="ada-coordinates" class="hidden mb-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                            <input type="text" id="ada-latitude" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                            <input type="text" id="ada-longitude" readonly class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="ada-modal-assignment-id" value="">
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeADAModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button id="ada-submit-btn" onclick="submitADA()" disabled class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Submit ADA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    assignments: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', feasibility_status: '', city: '', state: '', lho: '' },
    counts: { total: 0, pending_eta: 0, eta_submitted: 0, ada_submitted: 0, feasibility_completed: 0 },
    filterOptions: { cities: [], states: [], lhos: [] }
};

// API base URLs
const API_URL = '../api/engineer/index.php';
const ETA_API_URL = '../api/engineer/eta.php';
const ADA_API_URL = '../api/engineer/ada.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAssignments();
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
            loadAssignments();
        }, 300);
    });
    
    // Feasibility status filter
    document.getElementById('feasibility-status-filter').addEventListener('change', function(e) {
        state.filters.feasibility_status = e.target.value;
        state.pagination.page = 1;
        updateCardHighlights();
        loadAssignments();
    });
    
    // LHO filter
    document.getElementById('lho-filter').addEventListener('change', function(e) {
        state.filters.lho = e.target.value;
        state.pagination.page = 1;
        loadAssignments();
    });
    
    // City filter
    document.getElementById('city-filter').addEventListener('change', function(e) {
        state.filters.city = e.target.value;
        state.pagination.page = 1;
        loadAssignments();
    });
    
    // State filter
    document.getElementById('state-filter').addEventListener('change', function(e) {
        state.filters.state = e.target.value;
        state.pagination.page = 1;
        loadAssignments();
    });
}

// Filter by feasibility status (from stats cards)
function filterByFeasibilityStatus(status) {
    state.filters.feasibility_status = status;
    document.getElementById('feasibility-status-filter').value = status;
    state.pagination.page = 1;
    updateCardHighlights();
    loadAssignments();
}

// Update card highlights based on active filters
function updateCardHighlights() {
    // Reset all cards
    document.querySelectorAll('[id^="card-"]').forEach(card => {
        card.classList.remove('ring-2', 'ring-blue-400', 'ring-yellow-400', 'ring-orange-400', 'ring-purple-400', 'ring-green-400');
    });
    
    // Highlight active filter card
    const status = state.filters.feasibility_status;
    if (status === 'pending_eta') {
        document.getElementById('card-pending_eta').classList.add('ring-2', 'ring-yellow-400');
    } else if (status === 'eta_submitted') {
        document.getElementById('card-eta_submitted').classList.add('ring-2', 'ring-orange-400');
    } else if (status === 'ada_submitted') {
        document.getElementById('card-ada_submitted').classList.add('ring-2', 'ring-purple-400');
    } else if (status === 'feasibility_completed') {
        document.getElementById('card-feasibility_completed').classList.add('ring-2', 'ring-green-400');
    }
}

// Clear all filters
function clearFilters() {
    state.filters = { search: '', feasibility_status: '', city: '', state: '', lho: '' };
    document.getElementById('search-input').value = '';
    document.getElementById('feasibility-status-filter').value = '';
    document.getElementById('lho-filter').value = '';
    document.getElementById('city-filter').value = '';
    document.getElementById('state-filter').value = '';
    state.pagination.page = 1;
    updateCardHighlights();
    loadAssignments();
}

// Load assignments from API
async function loadAssignments() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.feasibility_status) params.append('feasibility_status', state.filters.feasibility_status);
        if (state.filters.lho) params.append('lho', state.filters.lho);
        if (state.filters.city) params.append('city', state.filters.city);
        if (state.filters.state) params.append('state', state.filters.state);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.assignments = data.data.assignments;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            state.filterOptions = data.data.filters || { cities: [], states: [], lhos: [] };
            
            // Use counts from API (real totals from database)
            state.counts = data.data.counts || { total: 0, pending_eta: 0, eta_submitted: 0, ada_submitted: 0, feasibility_completed: 0 };
            
            renderTable();
            renderPagination();
            updateStats();
            updateFilterOptions();
        } else {
            showError(data.error?.message || 'Failed to load assignments');
        }
    } catch (error) {
        console.error('Error loading assignments:', error);
        showError('Failed to load assignments. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Update stats display
function updateStats() {
    document.getElementById('total-count').textContent = state.counts.total || 0;
    document.getElementById('pending_eta-count').textContent = state.counts.pending_eta || 0;
    document.getElementById('eta_submitted-count').textContent = state.counts.eta_submitted || 0;
    document.getElementById('ada_submitted-count').textContent = state.counts.ada_submitted || 0;
    document.getElementById('feasibility_completed-count').textContent = state.counts.feasibility_completed || 0;
}

// Update filter options
function updateFilterOptions() {
    const lhoSelect = document.getElementById('lho-filter');
    const citySelect = document.getElementById('city-filter');
    const stateSelect = document.getElementById('state-filter');
    
    // Preserve current selections
    const currentLho = lhoSelect.value;
    const currentCity = citySelect.value;
    const currentState = stateSelect.value;
    
    // Update LHO options
    lhoSelect.innerHTML = '<option value="">All LHOs</option>';
    (state.filterOptions.lhos || []).forEach(lho => {
        const option = document.createElement('option');
        option.value = lho;
        option.textContent = lho;
        if (lho === currentLho) option.selected = true;
        lhoSelect.appendChild(option);
    });
    
    // Update city options
    citySelect.innerHTML = '<option value="">All Cities</option>';
    state.filterOptions.cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city;
        option.textContent = city;
        if (city === currentCity) option.selected = true;
        citySelect.appendChild(option);
    });
    
    // Update state options
    stateSelect.innerHTML = '<option value="">All States</option>';
    state.filterOptions.states.forEach(st => {
        const option = document.createElement('option');
        option.value = st;
        option.textContent = st;
        if (st === currentState) option.selected = true;
        stateSelect.appendChild(option);
    });
}

// Render table - Requirements 1.1, 1.2, 1.3, 1.4, 1.5
function renderTable() {
    const tbody = document.getElementById('assignments-tbody');
    
    if (state.assignments.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                    <i class="fas fa-map-marker-alt text-3xl mb-2 text-gray-300"></i>
                    <p class="text-sm">No assigned sites found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.assignments.map((assignment, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">${startIndex + index + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-map-marker-alt text-blue-500 text-xs"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="font-medium text-gray-800 text-xs block truncate">${escapeHtml(assignment.site_name || 'N/A')}</span>
                        ${assignment.bank_name ? `<p class="text-[10px] text-gray-400 truncate">${escapeHtml(assignment.bank_name)}</p>` : ''}
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px] font-medium">${escapeHtml(assignment.lho || '-')}</span>
            </td>
            <td class="px-4 py-2.5">
                <div class="text-xs text-gray-600">${escapeHtml(assignment.city || '')}, ${escapeHtml(assignment.state || '')}</div>
                ${assignment.address ? `<div class="text-[10px] text-gray-400 truncate max-w-[150px]">${escapeHtml(assignment.address)}</div>` : ''}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getFeasibilityStatusBadge(assignment.feasibility_status)}
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">
                ${getETAADAInfo(assignment)}
            </td>
            <td class="px-4 py-2.5">
                ${getActionButtons(assignment)}
            </td>
        </tr>
    `).join('');
}

// Get feasibility status badge HTML - Requirement 1.5
function getFeasibilityStatusBadge(status) {
    const badges = {
        'pending_eta': '<span class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-amber-400 rounded-full mr-1"></span>Pending ETA</span>',
        'eta_submitted': '<span class="inline-flex items-center px-2 py-0.5 bg-orange-50 text-orange-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-orange-400 rounded-full mr-1"></span>ETA Submitted</span>',
        'ada_submitted': '<span class="inline-flex items-center px-2 py-0.5 bg-purple-50 text-purple-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-purple-400 rounded-full mr-1"></span>ADA Submitted</span>',
        'feasibility_completed': '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Completed</span>',
        'pending_contractor_review': '<span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-blue-400 rounded-full mr-1"></span>Pending Review</span>',
        'contractor_approved': '<span class="inline-flex items-center px-2 py-0.5 bg-teal-50 text-teal-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-teal-500 rounded-full mr-1"></span>Contractor Approved</span>',
        'contractor_rejected': '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Contractor Rejected</span>',
        'adv_approved': '<span class="inline-flex items-center px-2 py-0.5 bg-green-50 text-green-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>ADV Approved</span>',
        'adv_rejected': '<span class="inline-flex items-center px-2 py-0.5 bg-orange-50 text-orange-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-orange-400 rounded-full mr-1"></span>ADV Rejected</span>'
    };
    return badges[status] || badges['pending_eta'];
}

// Get ETA/ADA info display
function getETAADAInfo(assignment) {
    let html = '<div class="text-[10px] space-y-0.5">';
    
    if (assignment.eta_datetime) {
        html += `<div><span class="text-gray-400">ETA:</span> <span class="font-medium text-gray-600">${formatDateShort(assignment.eta_datetime)}</span></div>`;
    }
    
    if (assignment.ada_datetime) {
        html += `<div><span class="text-gray-400">ADA:</span> <span class="font-medium text-gray-600">${formatDateShort(assignment.ada_datetime)}</span></div>`;
        if (assignment.ada_latitude && assignment.ada_longitude) {
            html += `<div><a href="https://www.google.com/maps?q=${assignment.ada_latitude},${assignment.ada_longitude}" target="_blank" class="text-blue-500 hover:underline"><i class="fas fa-map-marker-alt mr-0.5"></i>View</a></div>`;
        }
    }
    
    if (!assignment.eta_datetime && !assignment.ada_datetime) {
        html += '<span class="text-gray-400">-</span>';
    }
    
    html += '</div>';
    return html;
}

// Format date (short)
function formatDateShort(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Get action buttons based on feasibility status - Requirements 1.2, 1.3, 1.4
function getActionButtons(assignment) {
    const status = assignment.feasibility_status || 'pending_eta';
    let html = '<div class="flex items-center justify-center gap-0.5">';
    
    // Check if status is in approval workflow
    const approvalStatuses = ['pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected'];
    const isInApprovalWorkflow = approvalStatuses.includes(status);
    const isRejected = status === 'contractor_rejected' || status === 'adv_rejected';
    
    // ETA Button - enabled when pending_eta or eta_submitted (for updates)
    const etaEnabled = status === 'pending_eta' || status === 'eta_submitted';
    const etaTitle = status === 'pending_eta' ? 'Submit ETA' : (status === 'eta_submitted' ? 'Update ETA' : 'ETA Submitted');
    html += `<button onclick="openETAModal(${assignment.id})" 
        class="p-1.5 rounded transition-colors ${etaEnabled ? 'text-orange-500 hover:text-orange-600 hover:bg-orange-50' : 'text-gray-300 cursor-not-allowed'}" 
        ${!etaEnabled ? 'disabled' : ''} title="${etaTitle}">
        <i class="fas fa-calendar-alt text-xs"></i>
    </button>`;
    
    // ADA Button - enabled only when eta_submitted
    const adaEnabled = status === 'eta_submitted';
    const adaTitle = status === 'pending_eta' ? 'Submit ETA first' : (status === 'eta_submitted' ? 'Submit ADA' : 'ADA Submitted');
    html += `<button onclick="openADAModal(${assignment.id})" 
        class="p-1.5 rounded transition-colors ${adaEnabled ? 'text-purple-500 hover:text-purple-600 hover:bg-purple-50' : 'text-gray-300 cursor-not-allowed'}" 
        ${!adaEnabled ? 'disabled' : ''} title="${adaTitle}">
        <i class="fas fa-map-marker-alt text-xs"></i>
    </button>`;
    
    // Check Feasibility Button - enabled only when ada_submitted
    if (status === 'ada_submitted') {
        html += `<a href="feasibility_form.php?assignment_id=${assignment.id}" 
            class="p-1.5 text-green-500 hover:text-green-600 hover:bg-green-50 rounded transition-colors" 
            title="Complete Feasibility Check">
            <i class="fas fa-clipboard-check text-xs"></i>
        </a>`;
    } else if (status === 'feasibility_completed' || isInApprovalWorkflow) {
        // Show View button for completed or in approval workflow
        html += `<a href="feasibility_form.php?assignment_id=${assignment.id}&view=1" 
            class="p-1.5 text-blue-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" 
            title="View Feasibility Check">
            <i class="fas fa-eye text-xs"></i>
        </a>`;
        
        // Show Edit & Resubmit button for rejected status
        if (isRejected) {
            html += `<a href="feasibility_form.php?assignment_id=${assignment.id}&edit=1" 
                class="p-1.5 text-amber-500 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" 
                title="Edit & Resubmit">
                <i class="fas fa-edit text-xs"></i>
            </a>`;
        }
    }
    
    // View details button
    html += `<a href="site_detail.php?id=${assignment.id}" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded transition-colors" title="View Details">
        <i class="fas fa-info-circle text-xs"></i>
    </a>`;
    
    html += '</div>';
    return html;
}

// Get status badge HTML (legacy)
function getStatusBadge(status) {
    const badges = {
        'assigned': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium"><i class="fas fa-clock mr-1"></i>Assigned</span>',
        'in_progress': '<span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium"><i class="fas fa-spinner mr-1"></i>In Progress</span>',
        'completed': '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium"><i class="fas fa-check mr-1"></i>Completed</span>'
    };
    return badges[status] || `<span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">${status}</span>`;
}

// Format date time
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

// Format date
function formatDate(dateStr) {
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

// Render pagination
function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    const start = (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    
    document.getElementById('pagination-info').textContent = 
        total > 0 ? `Showing ${start} to ${end} of ${total} entries` : 'No entries';
    
    const controls = document.getElementById('pagination-controls');
    
    if (total_pages <= 1) {
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
    let endPage = Math.min(total_pages, startPage + maxPages - 1);
    
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
    
    if (endPage < total_pages) {
        if (endPage < total_pages - 1) html += `<span class="px-2">...</span>`;
        html += `<button onclick="goToPage(${total_pages})" class="px-3 py-1 rounded border hover:bg-gray-100">${total_pages}</button>`;
    }
    
    // Next button
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

// Go to page
function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadAssignments();
}

// ============ ETA Modal Functions - Requirements 2.1, 2.2, 2.3 ============

// Open ETA modal
function openETAModal(id) {
    const assignment = state.assignments.find(a => a.id === id);
    if (!assignment) return;
    
    document.getElementById('eta-modal-site-info').innerHTML = `
        <div class="flex items-center">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-map-marker-alt text-blue-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(assignment.site_name || 'N/A')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(assignment.lho || '')} • ${escapeHtml(assignment.city || '')}</p>
            </div>
        </div>
    `;
    
    // Pre-fill with existing ETA if available
    if (assignment.eta_datetime) {
        const etaDate = new Date(assignment.eta_datetime);
        document.getElementById('eta-date').value = etaDate.toISOString().split('T')[0];
        document.getElementById('eta-time').value = etaDate.toTimeString().slice(0, 5);
    } else {
        document.getElementById('eta-date').value = '';
        document.getElementById('eta-time').value = '';
    }
    
    document.getElementById('eta-modal-assignment-id').value = id;
    
    document.getElementById('eta-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close ETA modal
function closeETAModal() {
    document.getElementById('eta-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Submit ETA - Requirements 2.2, 2.3
async function submitETA() {
    const assignmentId = document.getElementById('eta-modal-assignment-id').value;
    const etaDate = document.getElementById('eta-date').value;
    const etaTime = document.getElementById('eta-time').value;
    
    if (!etaDate || !etaTime) {
        showError('Please select both date and time');
        return;
    }
    
    const etaDateTime = `${etaDate} ${etaTime}:00`;
    
    // Client-side validation for future date (Requirement 2.3)
    const etaTimestamp = new Date(etaDateTime).getTime();
    const now = Date.now();
    if (etaTimestamp <= now) {
        showError('ETA must be a future date and time');
        return;
    }
    
    const btn = document.getElementById('eta-submit-btn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    try {
        const response = await fetch(ETA_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                assignment_id: parseInt(assignmentId),
                eta_datetime: etaDateTime
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || 'ETA submitted successfully', 'success');
            closeETAModal();
            loadAssignments();
        } else {
            showError(data.error?.message || 'Failed to submit ETA');
        }
    } catch (error) {
        console.error('Error submitting ETA:', error);
        showError('Failed to submit ETA. Please try again.');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// ============ ADA Modal Functions - Requirements 3.1, 3.2, 3.3, 3.4 ============

// Open ADA modal
function openADAModal(id) {
    const assignment = state.assignments.find(a => a.id === id);
    if (!assignment) return;
    
    document.getElementById('ada-modal-site-info').innerHTML = `
        <div class="flex items-center">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-map-marker-alt text-blue-500"></i>
            </div>
            <div>
                <p class="font-medium text-gray-800">${escapeHtml(assignment.site_name || 'N/A')}</p>
                <p class="text-sm text-gray-500">${escapeHtml(assignment.lho || '')} • ${escapeHtml(assignment.city || '')}</p>
            </div>
        </div>
    `;
    
    document.getElementById('ada-modal-assignment-id').value = id;
    
    // Reset location status
    document.getElementById('ada-location-status').innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-spinner fa-spin text-blue-500 mr-3"></i>
            <span class="text-blue-700">Requesting location access...</span>
        </div>
    `;
    document.getElementById('ada-location-status').className = 'mb-4 p-4 bg-blue-50 rounded-lg';
    document.getElementById('ada-coordinates').classList.add('hidden');
    document.getElementById('ada-submit-btn').disabled = true;
    
    document.getElementById('ada-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Request geolocation (Requirement 3.1)
    requestGeolocation();
}

// Close ADA modal
function closeADAModal() {
    document.getElementById('ada-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Request geolocation - Requirements 3.1, 3.2, 3.3
function requestGeolocation() {
    if (!navigator.geolocation) {
        showGeolocationError('Geolocation is not supported by your browser');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        // Success callback (Requirement 3.2)
        function(position) {
            const latitude = position.coords.latitude.toFixed(8);
            const longitude = position.coords.longitude.toFixed(8);
            
            document.getElementById('ada-latitude').value = latitude;
            document.getElementById('ada-longitude').value = longitude;
            
            document.getElementById('ada-location-status').innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span class="text-green-700">Location captured successfully</span>
                </div>
            `;
            document.getElementById('ada-location-status').className = 'mb-4 p-4 bg-green-50 rounded-lg';
            document.getElementById('ada-coordinates').classList.remove('hidden');
            document.getElementById('ada-submit-btn').disabled = false;
        },
        // Error callback (Requirement 3.3)
        function(error) {
            let message = 'Unable to retrieve your location';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = 'Location access denied. Please enable location permissions and try again.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = 'Location information is unavailable. Please try again.';
                    break;
                case error.TIMEOUT:
                    message = 'Location request timed out. Please try again.';
                    break;
            }
            showGeolocationError(message);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Show geolocation error
function showGeolocationError(message) {
    document.getElementById('ada-location-status').innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
            <div>
                <span class="text-red-700">${message}</span>
                <button onclick="requestGeolocation()" class="ml-2 text-blue-600 hover:underline text-sm">Retry</button>
            </div>
        </div>
    `;
    document.getElementById('ada-location-status').className = 'mb-4 p-4 bg-red-50 rounded-lg';
    document.getElementById('ada-submit-btn').disabled = true;
}

// Submit ADA - Requirement 3.4
async function submitADA() {
    const assignmentId = document.getElementById('ada-modal-assignment-id').value;
    const latitude = parseFloat(document.getElementById('ada-latitude').value);
    const longitude = parseFloat(document.getElementById('ada-longitude').value);
    
    if (isNaN(latitude) || isNaN(longitude)) {
        showError('Invalid coordinates. Please try again.');
        return;
    }
    
    const btn = document.getElementById('ada-submit-btn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    try {
        const response = await fetch(ADA_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                assignment_id: parseInt(assignmentId),
                latitude: latitude,
                longitude: longitude
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message || 'ADA submitted successfully', 'success');
            closeADAModal();
            loadAssignments();
        } else {
            showError(data.error?.message || 'Failed to submit ADA');
        }
    } catch (error) {
        console.error('Error submitting ADA:', error);
        showError('Failed to submit ADA. Please try again.');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// ============ Utility Functions ============

// Show loading indicator
function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    const table = document.getElementById('assignments-table');
    
    if (show) {
        indicator.classList.remove('hidden');
        table.classList.add('opacity-50');
    } else {
        indicator.classList.add('hidden');
        table.classList.remove('opacity-50');
    }
}

// Show error message
function showError(message) {
    showToast(message, 'error');
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export assignments to CSV
async function exportAssignments() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.feasibility_status) params.append('feasibility_status', state.filters.feasibility_status);
        if (state.filters.lho) params.append('lho', state.filters.lho);
        if (state.filters.city) params.append('city', state.filters.city);
        if (state.filters.state) params.append('state', state.filters.state);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.assignments, 'my_assigned_sites.csv');
            showToast('Sites exported successfully', 'success');
        } else {
            showError(data.error?.message || 'Failed to export sites');
        }
    } catch (error) {
        console.error('Error exporting sites:', error);
        showError('Failed to export sites. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['#', 'Site Name', 'LHO', 'City', 'State', 'Address', 'Status', 'ETA', 'ADA'];
    const rows = data.map((d, i) => [
        i + 1,
        `"${(d.site_name || '').replace(/"/g, '""')}"`,
        `"${(d.lho || '').replace(/"/g, '""')}"`,
        `"${(d.city || '').replace(/"/g, '""')}"`,
        `"${(d.state || '').replace(/"/g, '""')}"`,
        `"${(d.address || '').replace(/"/g, '""')}"`,
        d.feasibility_status || '',
        d.eta_datetime || '',
        d.ada_datetime || ''
    ]);
    
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeETAModal();
        closeADAModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
