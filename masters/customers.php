<?php
/**
 * Customers Management Page
 * 
 * Implements table with pagination, search, status filter
 * Add create/edit modal with cascading Country -> State -> City dropdowns
 * Add view modal with full customer details
 * Include permission-based button visibility
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 8.3, 10.1, 10.2, 10.4
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
$user = $masterMiddleware->requireViewPermission('customers');

if (!$user) {
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Customer Management';
$currentPage = 'masters_customers';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'Customers']
];

$permissions = $masterMiddleware->getUserModulePermissions('customers');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Customer Management</h3>
            <p class="text-sm text-gray-500">Manage customer records for the CRM system</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Customer
            </button>
            <?php endif; ?>
            <button onclick="exportCustomers()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search customers by name or email..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading customers...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="customers-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">
                        ID <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="name">
                        Name <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="email">
                        Email <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">
                        Status <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="customers-tbody" class="divide-y divide-gray-100">
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

<!-- Create/Edit Modal -->
<div id="customer-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeCustomerModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 sticky top-0 bg-white">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Customer</h3>
                <button onclick="closeCustomerModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="customer-form" onsubmit="saveCustomer(event)">
                <input type="hidden" id="customer-id" value="">
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="customer-name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" id="customer-name" name="name" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter customer name">
                            <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="customer-email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="customer-email" name="email" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter email address">
                            <p id="email-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="customer-phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" id="customer-phone" name="phone"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter phone number">
                        </div>
                        <div>
                            <label for="customer-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="customer-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="customer-address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea id="customer-address" name="address" rows="2"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter street address"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="customer-country" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <select id="customer-country" name="country_id" onchange="onCountryChange()"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Select Country</option>
                            </select>
                        </div>
                        <div>
                            <label for="customer-state" class="block text-sm font-medium text-gray-700 mb-1">State</label>
                            <select id="customer-state" name="state_id" onchange="onStateChange()"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" disabled>
                                <option value="">Select State</option>
                            </select>
                        </div>
                        <div>
                            <label for="customer-city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <select id="customer-city" name="city_id"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" disabled>
                                <option value="">Select City</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="customer-postal-code" class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                        <input type="text" id="customer-postal-code" name="postal_code"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter postal code">
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl sticky bottom-0">
                    <button type="button" onclick="closeCustomerModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
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

<!-- View Modal -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Customer Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5 max-h-[60vh] overflow-y-auto"></div>
            <div class="flex justify-end p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
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
    customers: [],
    countries: [],
    states: [],
    cities: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: <?php echo json_encode($permissions); ?>
};

const API_URL = '../api/masters/customers.php';
const COUNTRIES_API = '../api/masters/countries.php';
const STATES_API = '../api/masters/states.php';
const CITIES_API = '../api/masters/cities.php';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCountries();
    loadCustomers();
    setupEventListeners();
});

// Load countries for dropdown
async function loadCountries() {
    try {
        const response = await fetch(`${COUNTRIES_API}?active_only=1&limit=500`, { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.countries = data.data.countries || [];
            populateCountryDropdown();
        }
    } catch (error) {
        console.error('Error loading countries:', error);
    }
}

function populateCountryDropdown() {
    const select = document.getElementById('customer-country');
    select.innerHTML = '<option value="">Select Country</option>' + 
        state.countries.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

// Load states based on country
async function loadStates(countryId) {
    const stateSelect = document.getElementById('customer-state');
    const citySelect = document.getElementById('customer-city');
    
    stateSelect.innerHTML = '<option value="">Loading...</option>';
    stateSelect.disabled = true;
    citySelect.innerHTML = '<option value="">Select City</option>';
    citySelect.disabled = true;
    
    if (!countryId) {
        stateSelect.innerHTML = '<option value="">Select State</option>';
        return;
    }
    
    try {
        const response = await fetch(`${STATES_API}?country_id=${countryId}&active_only=1&limit=500`, { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.states = data.data.states || [];
            stateSelect.innerHTML = '<option value="">Select State</option>' + 
                state.states.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
            stateSelect.disabled = false;
        }
    } catch (error) {
        console.error('Error loading states:', error);
        stateSelect.innerHTML = '<option value="">Error loading states</option>';
    }
}

// Load cities based on state
async function loadCities(stateId) {
    const citySelect = document.getElementById('customer-city');
    
    citySelect.innerHTML = '<option value="">Loading...</option>';
    citySelect.disabled = true;
    
    if (!stateId) {
        citySelect.innerHTML = '<option value="">Select City</option>';
        return;
    }
    
    try {
        const response = await fetch(`${CITIES_API}?state_id=${stateId}&active_only=1&limit=500`, { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.cities = data.data.cities || [];
            citySelect.innerHTML = '<option value="">Select City</option>' + 
                state.cities.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            citySelect.disabled = false;
        }
    } catch (error) {
        console.error('Error loading cities:', error);
        citySelect.innerHTML = '<option value="">Error loading cities</option>';
    }
}

function onCountryChange() {
    const countryId = document.getElementById('customer-country').value;
    loadStates(countryId);
}

function onStateChange() {
    const stateId = document.getElementById('customer-state').value;
    loadCities(stateId);
}

// Setup event listeners
function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadCustomers();
        }, 300);
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadCustomers();
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
            loadCustomers();
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

async function loadCustomers() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit
        });
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status !== '') params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.customers = data.data.customers;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load customers');
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        showError('Failed to load customers. Please try again.');
    } finally {
        showLoading(false);
    }
}

function renderTable() {
    const tbody = document.getElementById('customers-tbody');
    
    if (state.customers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                    <p>No customers found</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const startIndex = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.customers.map((customer, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">${startIndex + index + 1}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${customer.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-green-50 to-green-100 rounded-full flex items-center justify-center mr-2.5 flex-shrink-0">
                        <span class="text-green-600 font-medium text-xs">${escapeHtml(customer.name.charAt(0).toUpperCase())}</span>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(customer.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(customer.email)}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${escapeHtml(customer.phone || '-')}</td>
            <td class="px-4 py-2.5">
                ${customer.status == 1 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'
                }
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <button onclick="viewCustomer(${customer.id})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ${state.permissions.edit ? `
                    <button onclick="editCustomer(${customer.id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    ` : ''}
                    ${state.permissions.delete ? `
                    <button onclick="confirmDelete(${customer.id}, '${escapeHtml(customer.name)}')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}


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
    html += `<button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
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
    
    html += `<button onclick="goToPage(${page + 1})" ${page === total_pages ? 'disabled' : ''} 
        class="px-3 py-1 rounded border ${page === total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100'}">
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadCustomers();
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Customer';
    document.getElementById('customer-id').value = '';
    document.getElementById('customer-form').reset();
    document.getElementById('customer-state').innerHTML = '<option value="">Select State</option>';
    document.getElementById('customer-state').disabled = true;
    document.getElementById('customer-city').innerHTML = '<option value="">Select City</option>';
    document.getElementById('customer-city').disabled = true;
    clearErrors();
    document.getElementById('customer-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

async function editCustomer(id) {
    const customer = state.customers.find(c => c.id === id);
    if (!customer) return;
    
    document.getElementById('modal-title').textContent = 'Edit Customer';
    document.getElementById('customer-id').value = customer.id;
    document.getElementById('customer-name').value = customer.name;
    document.getElementById('customer-email').value = customer.email;
    document.getElementById('customer-phone').value = customer.phone || '';
    document.getElementById('customer-address').value = customer.address || '';
    document.getElementById('customer-postal-code').value = customer.postal_code || '';
    document.getElementById('customer-status').value = customer.status;
    
    // Reset dropdowns first
    document.getElementById('customer-country').value = '';
    document.getElementById('customer-state').innerHTML = '<option value="">Select State</option>';
    document.getElementById('customer-state').disabled = true;
    document.getElementById('customer-city').innerHTML = '<option value="">Select City</option>';
    document.getElementById('customer-city').disabled = true;
    
    // Set country and load states/cities
    // First try to use IDs, then fall back to finding by name
    let countryId = customer.country_id;
    let stateId = customer.state_id;
    let cityId = customer.city_id;
    
    // If no country_id but has country name, try to find it
    if (!countryId && (customer.country_name || customer.country)) {
        const countryName = customer.country_name || customer.country;
        const countryOption = Array.from(document.getElementById('customer-country').options)
            .find(opt => opt.text.toLowerCase() === countryName.toLowerCase());
        if (countryOption) {
            countryId = countryOption.value;
        }
    }
    
    if (countryId) {
        document.getElementById('customer-country').value = countryId;
        await loadStates(countryId);
        
        // If no state_id but has state name, try to find it
        if (!stateId && (customer.state_name || customer.state)) {
            const stateName = customer.state_name || customer.state;
            const stateOption = Array.from(document.getElementById('customer-state').options)
                .find(opt => opt.text.toLowerCase() === stateName.toLowerCase());
            if (stateOption) {
                stateId = stateOption.value;
            }
        }
        
        if (stateId) {
            document.getElementById('customer-state').value = stateId;
            await loadCities(stateId);
            
            // If no city_id but has city name, try to find it
            if (!cityId && (customer.city_name || customer.city)) {
                const cityName = customer.city_name || customer.city;
                const cityOption = Array.from(document.getElementById('customer-city').options)
                    .find(opt => opt.text.toLowerCase() === cityName.toLowerCase());
                if (cityOption) {
                    cityId = cityOption.value;
                }
            }
            
            if (cityId) {
                document.getElementById('customer-city').value = cityId;
            }
        }
    }
    
    clearErrors();
    document.getElementById('customer-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCustomerModal() {
    document.getElementById('customer-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveCustomer(event) {
    event.preventDefault();
    event.stopPropagation();
    
    const saveBtn = document.getElementById('save-btn');
    if (saveBtn.disabled) return;
    
    clearErrors();
    
    const id = document.getElementById('customer-id').value;
    const name = document.getElementById('customer-name').value.trim();
    const email = document.getElementById('customer-email').value.trim();
    const phone = document.getElementById('customer-phone').value.trim();
    const address = document.getElementById('customer-address').value.trim();
    const countryId = document.getElementById('customer-country').value;
    const stateId = document.getElementById('customer-state').value;
    const cityId = document.getElementById('customer-city').value;
    const postalCode = document.getElementById('customer-postal-code').value.trim();
    const status = document.getElementById('customer-status').value;
    
    // Get names for backward compatibility
    const countryName = countryId ? document.getElementById('customer-country').options[document.getElementById('customer-country').selectedIndex].text : '';
    const stateName = stateId ? document.getElementById('customer-state').options[document.getElementById('customer-state').selectedIndex].text : '';
    const cityName = cityId ? document.getElementById('customer-city').options[document.getElementById('customer-city').selectedIndex].text : '';
    
    let hasError = false;
    if (!name) {
        showFieldError('name', 'Customer name is required');
        hasError = true;
    }
    if (!email) {
        showFieldError('email', 'Email is required');
        hasError = true;
    } else if (!isValidEmail(email)) {
        showFieldError('email', 'Please enter a valid email address');
        hasError = true;
    }
    
    if (hasError) return;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const payload = {
            action: id ? 'update' : 'create',
            name: name,
            email: email,
            phone: phone,
            address: address,
            country_id: countryId ? parseInt(countryId) : null,
            state_id: stateId ? parseInt(stateId) : null,
            city_id: cityId ? parseInt(cityId) : null,
            country: countryName,
            state: stateName,
            city: cityName,
            postal_code: postalCode,
            status: parseInt(status)
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
            closeCustomerModal();
            showSuccess(data.message || 'Customer saved successfully');
            loadCustomers();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    showFieldError(field, data.errors[field][0]);
                });
            } else {
                showError(data.error?.message || data.message || 'Failed to save customer');
            }
        }
    } catch (error) {
        console.error('Error saving customer:', error);
        showError('Failed to save customer. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

function viewCustomer(id) {
    const customer = state.customers.find(c => c.id === id);
    if (!customer) return;
    
    const locationParts = [customer.city_name || customer.city, customer.state_name || customer.state, customer.country_name || customer.country].filter(Boolean);
    const location = locationParts.join(', ') || '-';
    
    document.getElementById('view-content').innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                    <span class="text-2xl text-green-600 font-bold">${escapeHtml(customer.name.charAt(0).toUpperCase())}</span>
                </div>
            </div>
            <div class="text-center mb-4">
                <h4 class="text-xl font-semibold">${escapeHtml(customer.name)}</h4>
                <p class="text-gray-500">${escapeHtml(customer.email)}</p>
            </div>
            <div class="border-t pt-4 space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-500">ID</span>
                    <span class="font-medium">${customer.id}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Status</span>
                    <span>${customer.status == 1 
                        ? '<span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">Active</span>'
                        : '<span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Inactive</span>'
                    }</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Phone</span>
                    <span class="font-medium">${escapeHtml(customer.phone || '-')}</span>
                </div>
                ${customer.address ? `
                <div>
                    <span class="text-gray-500 block mb-1">Address</span>
                    <p class="font-medium">${escapeHtml(customer.address)}</p>
                </div>
                ` : ''}
                <div class="flex justify-between">
                    <span class="text-gray-500">Location</span>
                    <span class="font-medium">${escapeHtml(location)}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Postal Code</span>
                    <span class="font-medium">${escapeHtml(customer.postal_code || '-')}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Created</span>
                    <span class="font-medium">${formatDate(customer.created_at)}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Updated</span>
                    <span class="font-medium">${formatDate(customer.updated_at)}</span>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('view-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function confirmDelete(id, name) {
    openConfirmModal(
        'Delete Customer',
        `Are you sure you want to delete "${name}"? This will set the customer status to inactive.`,
        function() { deleteCustomer(id); }
    );
}

async function deleteCustomer(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'Customer deleted successfully');
            loadCustomers();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete customer');
        }
    } catch (error) {
        console.error('Error deleting customer:', error);
        showError('Failed to delete customer. Please try again.');
    }
}

async function exportCustomers() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status !== '') params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.customers, 'customers_export.csv');
            showSuccess('Customers exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export customers');
        }
    } catch (error) {
        console.error('Error exporting customers:', error);
        showError('Failed to export customers. Please try again.');
    }
}

function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Country', 'Postal Code', 'Status', 'Created At'];
    const rows = data.map(customer => [
        customer.id,
        `"${(customer.name || '').replace(/"/g, '""')}"`,
        `"${(customer.email || '').replace(/"/g, '""')}"`,
        `"${(customer.phone || '').replace(/"/g, '""')}"`,
        `"${(customer.address || '').replace(/"/g, '""')}"`,
        `"${(customer.city_name || customer.city || '').replace(/"/g, '""')}"`,
        `"${(customer.state_name || customer.state || '').replace(/"/g, '""')}"`,
        `"${(customer.country_name || customer.country || '').replace(/"/g, '""')}"`,
        `"${(customer.postal_code || '').replace(/"/g, '""')}"`,
        customer.status == 1 ? 'Active' : 'Inactive',
        customer.created_at
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
    document.getElementById('customers-table').classList.toggle('hidden', show);
}

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
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75"><i class="fas fa-times"></i></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function showFieldError(field, message) {
    const errorEl = document.getElementById(`${field}-error`);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
