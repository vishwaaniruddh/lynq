<?php
/**
 * Cities Management Page
 * 
 * Implements table with country, state, zone, status filters
 * Add create/edit modal with cascading dropdowns
 * Include zone assignment functionality
 * 
 * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 8.3
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$masterMiddleware = new MasterModuleMiddleware();
$user = $masterMiddleware->requireViewPermission('locations');

if (!$user) {
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'City Management';
$currentPage = 'masters_cities';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'Cities']
];

$permissions = $masterMiddleware->getUserModulePermissions('locations');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">City Management</h3>
            <p class="text-sm text-gray-500">Manage cities within states</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add City
            </button>
            <?php endif; ?>
            <button onclick="exportCities()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4 flex-wrap">
            <div class="flex-1 min-w-[200px]">
                <input type="text" id="search-input" placeholder="Search cities..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="country-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Countries</option>
                </select>
            </div>
            <div>
                <select id="state-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All States</option>
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
    
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading cities...</p>
    </div>
    
    <div class="overflow-x-auto">
        <table id="cities-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">ID <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="name">City Name <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">State</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Country</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Zone</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">Status <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="cities-tbody" class="divide-y divide-gray-100">
                <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    
    <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="city-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeCityModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add City</h3>
                <button onclick="closeCityModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="city-form" onsubmit="saveCity(event)">
                <input type="hidden" id="city-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="city-name" class="block text-sm font-medium text-gray-700 mb-1">City Name <span class="text-red-500">*</span></label>
                        <input type="text" id="city-name" name="name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter city name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="city-country" class="block text-sm font-medium text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                        <select id="city-country" name="country_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" onchange="loadStatesForCountry()">
                            <option value="">Select Country</option>
                        </select>
                    </div>
                    <div>
                        <label for="city-state" class="block text-sm font-medium text-gray-700 mb-1">State <span class="text-red-500">*</span></label>
                        <select id="city-state" name="state_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">Select State</option>
                        </select>
                        <p id="state_id-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="city-zone" class="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                        <select id="city-zone" name="zone_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">No Zone</option>
                        </select>
                    </div>
                    <div>
                        <label for="city-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="city-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeCityModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition"><i class="fas fa-save mr-2"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const state = {
    cities: [],
    countries: [],
    states: [],
    zones: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', country_id: '', state_id: '', zone_id: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: <?php echo json_encode($permissions); ?>
};

const API_URL = '../api/masters/cities.php';
const COUNTRIES_API = '../api/masters/countries.php';
const STATES_API = '../api/masters/states.php';
const ZONES_API = '../api/masters/zones.php';

document.addEventListener('DOMContentLoaded', function() {
    loadDropdownData();
    loadCities();
    setupEventListeners();
});

async function loadDropdownData() {
    try {
        const [countriesRes, zonesRes] = await Promise.all([
            fetch(`${COUNTRIES_API}?active_only=1`, { credentials: 'include' }),
            fetch(`${ZONES_API}?active_only=1`, { credentials: 'include' })
        ]);
        
        const countriesData = await countriesRes.json();
        const zonesData = await zonesRes.json();
        
        if (countriesData.success) {
            state.countries = countriesData.data.countries;
            populateCountryDropdowns();
        }
        
        if (zonesData.success) {
            state.zones = zonesData.data.zones;
            populateZoneDropdowns();
        }
    } catch (error) {
        console.error('Error loading dropdown data:', error);
    }
}

function populateCountryDropdowns() {
    const options = state.countries.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    document.getElementById('country-filter').innerHTML = '<option value="">All Countries</option>' + options;
    document.getElementById('city-country').innerHTML = '<option value="">Select Country</option>' + options;
}

function populateZoneDropdowns() {
    const options = state.zones.map(z => `<option value="${z.id}">${escapeHtml(z.name)}</option>`).join('');
    document.getElementById('zone-filter').innerHTML = '<option value="">All Zones</option>' + options;
    document.getElementById('city-zone').innerHTML = '<option value="">No Zone</option>' + options;
}

async function loadStatesForCountry(countryId = null) {
    const cid = countryId || document.getElementById('city-country').value;
    const stateSelect = document.getElementById('city-state');
    
    if (!cid) {
        stateSelect.innerHTML = '<option value="">Select State</option>';
        return;
    }
    
    try {
        const response = await fetch(`${STATES_API}?by_country=${cid}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            const options = data.data.states.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
            stateSelect.innerHTML = '<option value="">Select State</option>' + options;
        }
    } catch (error) {
        console.error('Error loading states:', error);
    }
}

async function loadStatesForFilter() {
    const cid = document.getElementById('country-filter').value;
    const stateFilter = document.getElementById('state-filter');
    
    console.log('loadStatesForFilter called, country_id:', cid);
    
    if (!cid) {
        stateFilter.innerHTML = '<option value="">All States</option>';
        return;
    }
    
    try {
        console.log('Fetching states for country:', cid);
        const response = await fetch(`${STATES_API}?by_country=${cid}`, { credentials: 'include' });
        console.log('States API response status:', response.status);
        const data = await response.json();
        console.log('States API response data:', data);
        
        if (data.success) {
            const options = data.data.states.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
            stateFilter.innerHTML = '<option value="">All States</option>' + options;
        } else {
            console.error('States API error:', data);
        }
    } catch (error) {
        console.error('Error loading states:', error);
    }
}

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadCities();
        }, 300);
    });
    
    document.getElementById('country-filter').addEventListener('change', function(e) {
        state.filters.country_id = e.target.value;
        state.filters.state_id = '';
        document.getElementById('state-filter').value = '';
        loadStatesForFilter();
        state.pagination.page = 1;
        loadCities();
    });
    
    document.getElementById('state-filter').addEventListener('change', function(e) {
        state.filters.state_id = e.target.value;
        state.pagination.page = 1;
        loadCities();
    });
    
    document.getElementById('zone-filter').addEventListener('change', function(e) {
        state.filters.zone_id = e.target.value;
        state.pagination.page = 1;
        loadCities();
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadCities();
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
            loadCities();
            updateSortIndicators();
        });
    });
}

function updateSortIndicators() {
    document.querySelectorAll('[data-sort]').forEach(th => {
        const icon = th.querySelector('i');
        icon.className = th.dataset.sort === state.sort.field 
            ? (state.sort.direction === 'asc' ? 'fas fa-sort-up ml-1' : 'fas fa-sort-down ml-1')
            : 'fas fa-sort ml-1';
    });
}

async function loadCities() {
    showLoading(true);
    try {
        const params = new URLSearchParams({ page: state.pagination.page, limit: state.pagination.limit });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.country_id) params.append('country_id', state.filters.country_id);
        if (state.filters.state_id) params.append('state_id', state.filters.state_id);
        if (state.filters.zone_id) params.append('zone_id', state.filters.zone_id);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.cities = data.data.cities;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load cities');
        }
    } catch (error) {
        console.error('Error loading cities:', error);
        showError('Failed to load cities. Please try again.');
    } finally {
        showLoading(false);
    }
}

function renderTable() {
    const tbody = document.getElementById('cities-tbody');
    
    if (state.cities.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">
            <i class="fas fa-city text-4xl mb-3 text-gray-300"></i><p>No cities found</p></td></tr>`;
        return;
    }
    
    tbody.innerHTML = state.cities.map((c, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">${(state.pagination.page - 1) * state.pagination.limit + index + 1}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${c.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-cyan-50 to-cyan-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-city text-cyan-500 text-xs"></i>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(c.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(c.state_name || '-')}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(c.country_name || '-')}</td>
            <td class="px-4 py-2.5">
                ${c.zone_name 
                    ? `<span class="px-2 py-0.5 bg-orange-50 text-orange-600 rounded text-[10px] font-medium">${escapeHtml(c.zone_name)}</span>`
                    : '<span class="text-gray-400 text-xs">-</span>'}
            </td>
            <td class="px-4 py-2.5">
                ${c.status === 'active' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'}
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    ${state.permissions.edit ? `<button onclick="editCity(${c.id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit"><i class="fas fa-edit text-xs"></i></button>` : ''}
                    ${state.permissions.delete ? `<button onclick="confirmDelete(${c.id}, '${escapeHtml(c.name)}')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete"><i class="fas fa-trash text-xs"></i></button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    document.getElementById('pagination-info').textContent = total > 0 ? `Showing ${(page-1)*limit+1} to ${Math.min(page*limit, total)} of ${total} entries` : 'No entries';
    
    const controls = document.getElementById('pagination-controls');
    if (total_pages <= 1) { controls.innerHTML = ''; return; }
    
    let html = `<button onclick="goToPage(${page-1})" ${page===1?'disabled':''} class="px-3 py-1 rounded border ${page===1?'bg-gray-100 text-gray-400 cursor-not-allowed':'hover:bg-gray-100'}"><i class="fas fa-chevron-left"></i></button>`;
    
    for (let i = Math.max(1, page-2); i <= Math.min(total_pages, page+2); i++) {
        html += `<button onclick="goToPage(${i})" class="px-3 py-1 rounded border ${i===page?'bg-primary text-white':'hover:bg-gray-100'}">${i}</button>`;
    }
    
    html += `<button onclick="goToPage(${page+1})" ${page===total_pages?'disabled':''} class="px-3 py-1 rounded border ${page===total_pages?'bg-gray-100 text-gray-400 cursor-not-allowed':'hover:bg-gray-100'}"><i class="fas fa-chevron-right"></i></button>`;
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadCities();
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add City';
    document.getElementById('city-id').value = '';
    document.getElementById('city-name').value = '';
    document.getElementById('city-country').value = '';
    document.getElementById('city-state').innerHTML = '<option value="">Select State</option>';
    document.getElementById('city-zone').value = '';
    document.getElementById('city-status').value = 'active';
    clearErrors();
    document.getElementById('city-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

async function editCity(id) {
    const c = state.cities.find(city => city.id === id);
    if (!c) return;
    
    document.getElementById('modal-title').textContent = 'Edit City';
    document.getElementById('city-id').value = c.id;
    document.getElementById('city-name').value = c.name;
    document.getElementById('city-country').value = c.country_id || '';
    
    // Load states for the country first
    if (c.country_id) {
        await loadStatesForCountry(c.country_id);
    }
    
    document.getElementById('city-state').value = c.state_id;
    document.getElementById('city-zone').value = c.zone_id || '';
    document.getElementById('city-status').value = c.status;
    clearErrors();
    document.getElementById('city-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCityModal() {
    document.getElementById('city-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveCity(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('city-id').value;
    const name = document.getElementById('city-name').value.trim();
    const stateId = document.getElementById('city-state').value;
    const zoneId = document.getElementById('city-zone').value;
    const status = document.getElementById('city-status').value;
    
    let hasError = false;
    if (!name) { showFieldError('name', 'City name is required'); hasError = true; }
    if (!stateId) { showFieldError('state_id', 'State is required'); hasError = true; }
    if (hasError) return;
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const payload = {
            action: id ? 'update' : 'create',
            name: name,
            state_id: parseInt(stateId),
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
            closeCityModal();
            showSuccess(data.message || 'City saved successfully');
            loadCities();
        } else {
            if (data.errors) Object.keys(data.errors).forEach(f => showFieldError(f, data.errors[f][0]));
            else showError(data.error?.message || data.message || 'Failed to save city');
        }
    } catch (error) {
        console.error('Error saving city:', error);
        showError('Failed to save city. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

function confirmDelete(id, name) {
    openConfirmModal('Delete City', `Are you sure you want to delete "${name}"? This will set the city status to inactive.`, () => deleteCity(id));
}

async function deleteCity(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'City deleted successfully');
            loadCities();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete city');
        }
    } catch (error) {
        console.error('Error deleting city:', error);
        showError('Failed to delete city. Please try again.');
    }
}

// Export cities
async function exportCities() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.country_id) params.append('country_id', state.filters.country_id);
        if (state.filters.state_id) params.append('state_id', state.filters.state_id);
        if (state.filters.zone_id) params.append('zone_id', state.filters.zone_id);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.cities, 'cities_export.csv');
            showSuccess('Cities exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export cities');
        }
    } catch (error) {
        console.error('Error exporting cities:', error);
        showError('Failed to export cities. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'State', 'Country', 'Zone', 'Status', 'Created At', 'Updated At'];
    const rows = data.map(c => [
        c.id,
        `"${(c.name || '').replace(/"/g, '""')}"`,
        `"${(c.state_name || '').replace(/"/g, '""')}"`,
        `"${(c.country_name || '').replace(/"/g, '""')}"`,
        `"${(c.zone_name || '').replace(/"/g, '""')}"`,
        c.status || '',
        c.created_at || '',
        c.updated_at || ''
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

function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('cities-table').classList.toggle('hidden', show);
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
