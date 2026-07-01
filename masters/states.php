<?php
/**
 * States Management Page
 * 
 * Implements table with country, zone, status filters
 * Add create/edit modal with country and zone dropdowns
 * Include cascading dropdown for country selection
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 8.3
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access and view permission
$masterMiddleware = new MasterModuleMiddleware();
$user = $masterMiddleware->requireViewPermission('locations');

if (!$user) {
    exit; // Middleware handles redirect
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'State Management';
$currentPage = 'masters_states';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'States']
];

// Get user permissions for this module
$permissions = $masterMiddleware->getUserModulePermissions('locations');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">State Management</h3>
            <p class="text-sm text-gray-500">Manage states within countries</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add State
            </button>
            <?php endif; ?>
            <button onclick="exportStates()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search states..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="country-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Countries</option>
                </select>
            </div>
            <div>
                <select id="zone-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Zones</option>
                </select>
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading states...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="states-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">
                        ID <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="name">
                        State Name <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Country</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Zone</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Cities</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">
                        Status <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="states-tbody" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="8" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td>
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

<!-- Create/Edit Modal -->
<div id="state-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeStateModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add State</h3>
                <button onclick="closeStateModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="state-form" onsubmit="saveState(event)">
                <input type="hidden" id="state-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="state-name" class="block text-sm font-medium text-gray-700 mb-1">State Name <span class="text-red-500">*</span></label>
                        <input type="text" id="state-name" name="name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter state name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="state-country" class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                        <select id="state-country" name="country_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select Country</option>
                        </select>
                        <p id="country_id-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="state-zone" class="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                        <select id="state-zone" name="zone_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">No Zone</option>
                        </select>
                    </div>
                    <div>
                        <label for="state-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="state-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeStateModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    states: [],
    countries: [],
    zones: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', country_id: '', zone_id: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: <?php echo json_encode($permissions); ?>
};

// API URLs
const API_URL = '../api/masters/states.php';
const COUNTRIES_API = '../api/masters/countries.php';
const ZONES_API = '../api/masters/zones.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDropdownData();
    loadStates();
    setupEventListeners();
});

// Load dropdown data
async function loadDropdownData() {
    try {
        // Load countries
        const countriesRes = await fetch(`${COUNTRIES_API}?active_only=1`, { credentials: 'include' });
        const countriesData = await countriesRes.json();
        if (countriesData.success) {
            state.countries = countriesData.data.countries;
            populateCountryDropdowns();
        }
        
        // Load zones
        const zonesRes = await fetch(`${ZONES_API}?active_only=1`, { credentials: 'include' });
        const zonesData = await zonesRes.json();
        if (zonesData.success) {
            state.zones = zonesData.data.zones;
            populateZoneDropdowns();
        }
    } catch (error) {
        console.error('Error loading dropdown data:', error);
    }
}

function populateCountryDropdowns() {
    const filterSelect = document.getElementById('country-filter');
    const modalSelect = document.getElementById('state-country');
    
    const options = state.countries.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    
    filterSelect.innerHTML = '<option value="">All Countries</option>' + options;
    modalSelect.innerHTML = '<option value="">Select Country</option>' + options;
}

function populateZoneDropdowns() {
    const filterSelect = document.getElementById('zone-filter');
    const modalSelect = document.getElementById('state-zone');
    
    const options = state.zones.map(z => `<option value="${z.id}">${escapeHtml(z.name)}</option>`).join('');
    
    filterSelect.innerHTML = '<option value="">All Zones</option>' + options;
    modalSelect.innerHTML = '<option value="">No Zone</option>' + options;
}

// Setup event listeners
function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadStates();
        }, 300);
    });
    
    document.getElementById('country-filter').addEventListener('change', function(e) {
        state.filters.country_id = e.target.value;
        state.pagination.page = 1;
        loadStates();
    });
    
    document.getElementById('zone-filter').addEventListener('change', function(e) {
        state.filters.zone_id = e.target.value;
        state.pagination.page = 1;
        loadStates();
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadStates();
    });
    
    document.querySelectorAll('[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const field = this.dataset.sort;
            if (state.sort.field === field) {
                state.sort.direction = state.sort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort.field = field;
                state.sort.direction = 'asc';
            }
            loadStates();
            updateSortIndicators();
        });
    });
}

function updateSortIndicators() {
    document.querySelectorAll('[data-sort]').forEach(th => {
        const icon = th.querySelector('i');
        if (th.dataset.sort === state.sort.field) {
            icon.className = state.sort.direction === 'asc' ? 'fas fa-sort-up ml-1' : 'fas fa-sort-down ml-1';
        } else {
            icon.className = 'fas fa-sort ml-1';
        }
    });
}

// Load states from API
async function loadStates() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.country_id) params.append('country_id', state.filters.country_id);
        if (state.filters.zone_id) params.append('zone_id', state.filters.zone_id);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.states = data.data.states;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load states');
        }
    } catch (error) {
        console.error('Error loading states:', error);
        showError('Failed to load states. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render table
function renderTable() {
    const tbody = document.getElementById('states-tbody');
    
    if (state.states.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-map text-4xl mb-3 text-gray-300"></i>
                    <p>No states found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = state.states.map((s, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">${(state.pagination.page - 1) * state.pagination.limit + index + 1}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${s.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-teal-50 to-teal-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-map text-teal-500 text-xs"></i>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(s.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(s.country_name || '-')}</td>
            <td class="px-4 py-2.5">
                ${s.zone_name 
                    ? `<span class="px-2 py-0.5 bg-orange-50 text-orange-600 rounded text-[10px] font-medium">${escapeHtml(s.zone_name)}</span>`
                    : '<span class="text-gray-400 text-xs">-</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px] font-medium">${s.city_count || 0} cities</span>
            </td>
            <td class="px-4 py-2.5">
                ${s.status === 'active' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    ${state.permissions.edit ? `
                    <button onclick="editState(${s.id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                    ${state.permissions.delete ? `
                    <button onclick="confirmDelete(${s.id}, '${escapeHtml(s.name)}', ${s.city_count || 0})" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
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
    
    let html = `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    const maxPages = 5;
    let startPage = Math.max(1, page - Math.floor(maxPages / 2));
    let endPage = Math.min(total_pages, startPage + maxPages - 1);
    
    if (startPage > 1) {
        html += `<button onclick="goToPage(1)" class="px-3 py-1 rounded border hover:bg-gray-100">1</button>`;
        if (startPage > 2) html += `<span class="px-2">...</span>`;
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<button onclick="goToPage(${i})" 
            class="px-3 py-1 rounded border ${i === page ? 'bg-primary text-white' : 'hover:bg-gray-100'}">${i}</button>`;
    }
    
    if (endPage < total_pages) {
        if (endPage < total_pages - 1) html += `<span class="px-2">...</span>`;
        html += `<button onclick="goToPage(${total_pages})" class="px-3 py-1 rounded border hover:bg-gray-100">${total_pages}</button>`;
    }
    
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadStates();
}

// Modal functions
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add State';
    document.getElementById('state-id').value = '';
    document.getElementById('state-name').value = '';
    document.getElementById('state-country').value = '';
    document.getElementById('state-zone').value = '';
    document.getElementById('state-status').value = 'active';
    clearErrors();
    document.getElementById('state-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editState(id) {
    const s = state.states.find(st => st.id === id);
    if (!s) return;
    
    document.getElementById('modal-title').textContent = 'Edit State';
    document.getElementById('state-id').value = s.id;
    document.getElementById('state-name').value = s.name;
    document.getElementById('state-country').value = s.country_id;
    document.getElementById('state-zone').value = s.zone_id || '';
    document.getElementById('state-status').value = s.status;
    clearErrors();
    document.getElementById('state-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeStateModal() {
    document.getElementById('state-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveState(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('state-id').value;
    const name = document.getElementById('state-name').value.trim();
    const countryId = document.getElementById('state-country').value;
    const zoneId = document.getElementById('state-zone').value;
    const status = document.getElementById('state-status').value;
    
    let hasError = false;
    if (!name) { showFieldError('name', 'State name is required'); hasError = true; }
    if (!countryId) { showFieldError('country_id', 'Country is required'); hasError = true; }
    if (hasError) return;
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const payload = {
            action: id ? 'update' : 'create',
            name: name,
            country_id: parseInt(countryId),
            zone_id: zoneId ? parseInt(zoneId) : null,
            status: status
        };
        if (id) payload.id = parseInt(id);
        
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeStateModal();
            showSuccess(data.message || 'State saved successfully');
            loadStates();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => showFieldError(field, data.errors[field][0]));
            } else {
                showError(data.error?.message || data.message || 'Failed to save state');
            }
        }
    } catch (error) {
        console.error('Error saving state:', error);
        showError('Failed to save state. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

function confirmDelete(id, name, cityCount) {
    if (cityCount > 0) {
        openConfirmModal('Cannot Delete State', 
            `"${name}" has ${cityCount} city(ies) associated with it. Please delete or reassign the cities first.`,
            null, 'warning');
        document.getElementById('confirm-btn').style.display = 'none';
        return;
    }
    
    document.getElementById('confirm-btn').style.display = '';
    openConfirmModal('Delete State', `Are you sure you want to delete "${name}"?`, () => deleteState(id));
}

async function deleteState(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'State deleted successfully');
            loadStates();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete state');
        }
    } catch (error) {
        console.error('Error deleting state:', error);
        showError('Failed to delete state. Please try again.');
    }
}

// Export states
async function exportStates() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.country_id) params.append('country_id', state.filters.country_id);
        if (state.filters.zone_id) params.append('zone_id', state.filters.zone_id);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.states, 'states_export.csv');
            showSuccess('States exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export states');
        }
    } catch (error) {
        console.error('Error exporting states:', error);
        showError('Failed to export states. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'Country', 'Zone', 'Status', 'Cities', 'Created At', 'Updated At'];
    const rows = data.map(s => [
        s.id,
        `"${(s.name || '').replace(/"/g, '""')}"`,
        `"${(s.country_name || '').replace(/"/g, '""')}"`,
        `"${(s.zone_name || '').replace(/"/g, '""')}"`,
        s.status || '',
        s.city_count || 0,
        s.created_at || '',
        s.updated_at || ''
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

// Utility functions
function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('states-table').classList.toggle('hidden', show);
}

function showError(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) CRM.showAlert(message, 'error');
    else showToast(message, 'error');
}

function showSuccess(message) {
    if (typeof CRM !== 'undefined' && CRM.showAlert) CRM.showAlert(message, 'success');
    else showToast(message, 'success');
}

function showToast(message, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i><span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75"><i class="fas fa-times"></i></button>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function showFieldError(field, message) {
    const errorEl = document.getElementById(`${field}-error`);
    if (errorEl) { errorEl.textContent = message; errorEl.classList.remove('hidden'); }
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
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
