<?php
/**
 * Add Site Page
 * 
 * Standalone page for adding a new site with all fields and validation
 * Includes coordinate picker/map integration
 * 
 * Requirements: 1.1, 7.1, 7.2, 7.3
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Add Site';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Add Site']
];

ob_start();
?>

<!-- JQuery & Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Select2 overrides to match Tailwind UI styling */
.select2-container--default .select2-selection--single {
    border-color: #e2e8f0 !important;
    height: 42px !important;
    border-radius: 0.5rem !important;
    padding: 6px 12px !important;
    display: flex !important;
    align-items: center !important;
    background-color: #ffffff !important;
    box-shadow: none !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
    right: 8px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #1f2937 !important;
    padding-left: 0 !important;
    font-size: 0.875rem !important;
}
.select2-container--default .select2-selection--single:focus,
.select2-container--default.select2-container--focus .select2-selection--single {
    outline: none !important;
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
}
.select2-dropdown {
    border-color: #e2e8f0 !important;
    border-radius: 0.5rem !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
    overflow: hidden !important;
    background-color: #ffffff !important;
    z-index: 9999 !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field {
    border-color: #e2e8f0 !important;
    border-radius: 0.375rem !important;
    padding: 6px 10px !important;
    outline: none !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field:focus {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #6366f1 !important;
    color: #ffffff !important;
}
.select2-container--default .select2-selection--single[aria-disabled="true"] {
    background-color: #f3f4f6 !important;
    color: #9ca3af !important;
    cursor: not-allowed !important;
    border-color: #e5e7eb !important;
}
.select2-container--default .select2-results__option {
    padding: 8px 12px !important;
    font-size: 0.875rem !important;
}
</style>

<div class="w-full">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Add New Site</h3>
                <p class="text-sm text-gray-500">Create a new site record for delegation</p>
            </div>
            <a href="index.php" class="px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-lg transition text-sm font-medium flex items-center shadow-sm">
                <i class="fas fa-arrow-left mr-2"></i>Back to Sites
            </a>
        </div>
        
        <form id="site-form" class="p-8 space-y-8">
            <!-- Basic Information -->
            <div class="border-b border-gray-100 pb-8">
                <div class="flex items-center gap-2 mb-6">
                    <span class="inline-block w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Basic Information</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="site_name" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            Site Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="site_name" name="site_name" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-4 focus:ring-indigo-50 focus:border-indigo-505 hover:border-gray-300 transition-all duration-200 text-sm text-gray-800 placeholder-gray-400"
                            placeholder="Enter site name">
                        <p id="site_name-error" class="mt-1 text-xs text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="lho" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            LHO <span class="text-red-500">*</span>
                        </label>
                        <select id="lho" name="lho" required>
                            <option value="">Select LHO</option>
                        </select>
                        <p id="lho-error" class="mt-1 text-xs text-red-500 hidden"></p>
                    </div>
                </div>
            </div>

            <!-- Business Information -->
            <div class="border-b border-gray-100 pb-8">
                <div class="flex items-center gap-2 mb-6">
                    <span class="inline-block w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Business Information</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="bank_name" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Bank Name</label>
                        <select id="bank_name" name="bank_name">
                            <option value="">Select Bank Name</option>
                        </select>
                    </div>
                    <div>
                        <label for="customer_name" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Customer Name</label>
                        <select id="customer_name" name="customer_name">
                            <option value="">Select Customer Name</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Location Information -->
            <div class="border-b border-gray-100 pb-8">
                <div class="flex items-center gap-2 mb-6">
                    <span class="inline-block w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Location Information</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label for="country" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            Country <span class="text-red-500">*</span>
                        </label>
                        <select id="country" name="country" required>
                            <option value="">Select Country</option>
                        </select>
                        <p id="country-error" class="mt-1 text-xs text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="state" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            State <span class="text-red-500">*</span>
                        </label>
                        <select id="state" name="state" required disabled>
                            <option value="">Select State</option>
                        </select>
                        <p id="state-error" class="mt-1 text-xs text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="city" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            City <span class="text-red-500">*</span>
                        </label>
                        <select id="city" name="city" required disabled>
                            <option value="">Select City</option>
                        </select>
                        <p id="city-error" class="mt-1 text-xs text-red-500 hidden"></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="zone" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Zone</label>
                        <select id="zone" name="zone">
                            <option value="">Select Zone</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Status</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6">
                    <label for="address" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Full Address</label>
                    <textarea id="address" name="address" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 hover:border-gray-300 transition-all duration-200 text-sm text-gray-800 placeholder-gray-400"
                        placeholder="Enter complete building number, street, landmark, and postal code"></textarea>
                </div>
            </div>

            <!-- Coordinates -->
            <div class="pb-8">
                <div class="flex items-center gap-2 mb-6">
                    <span class="inline-block w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                    <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Coordinates</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="latitude" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            Latitude <span class="text-gray-400 text-[10px]">(-90 to 90)</span>
                        </label>
                        <input type="number" step="any" id="latitude" name="latitude" min="-90" max="90"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 hover:border-gray-300 transition-all duration-200 text-sm text-gray-800 placeholder-gray-400"
                            placeholder="e.g., 28.6139">
                        <p id="latitude-error" class="mt-1 text-xs text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="longitude" class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">
                            Longitude <span class="text-gray-400 text-[10px]">(-180 to 180)</span>
                        </label>
                        <input type="number" step="any" id="longitude" name="longitude" min="-180" max="180"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-4 focus:ring-indigo-50 focus:border-indigo-500 hover:border-gray-300 transition-all duration-200 text-sm text-gray-800 placeholder-gray-400"
                            placeholder="e.g., 77.2090">
                        <p id="longitude-error" class="mt-1 text-xs text-red-500 hidden"></p>
                    </div>
                </div>
                
                <!-- Map Preview -->
                <div id="map-container" class="h-64 bg-gray-50 rounded-xl flex items-center justify-center border border-gray-200 relative overflow-hidden">
                    <div id="map-placeholder" class="text-center p-4">
                        <i class="fas fa-map-marked-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-xs text-gray-500">Enter coordinates to preview location</p>
                        <button type="button" onclick="getCurrentLocation()" class="mt-2 text-indigo-600 hover:text-indigo-700 hover:underline text-xs font-semibold flex items-center justify-center mx-auto gap-1">
                            <i class="fas fa-crosshairs"></i> Use current location
                        </button>
                    </div>
                    <div id="map-preview" class="hidden w-full h-full absolute inset-0"></div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-100">
                <a href="index.php" class="px-6 py-2.5 bg-gray-50 text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-100 transition text-sm font-medium">
                    Cancel
                </a>
                <button type="submit" id="save-btn" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition duration-200 font-semibold text-sm shadow-sm flex items-center">
                    <i class="fas fa-save mr-2"></i>Create Site
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const SAVE_API_URL = '../api/sites/index.php';
const OPTIONS_API_URL = '../api/sites/form_options.php';

// Helper to get selected option text (name)
function getSelectedText(id) {
    const el = document.getElementById(id);
    return el && el.selectedIndex >= 0 && el.value !== '' ? el.options[el.selectedIndex].text.trim() : '';
}

// Function to initialize select2 on an element and bridge its change event to native DOM
function initSelect2(id) {
    const $el = $('#' + id);
    $el.select2({ width: '100%' }).off('change.bridge').on('change.bridge', function() {
        this.dispatchEvent(new Event('change'));
    });
}

// Initial setup on document load
document.addEventListener('DOMContentLoaded', async function() {
    setupDropdownListeners();
    await loadInitialOptions();
    initSelect2('status');
});

// Load the initial masters lists
async function loadInitialOptions() {
    try {
        const response = await fetch(OPTIONS_API_URL, { credentials: 'include' });
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Populate LHO
            const lhoSelect = document.getElementById('lho');
            lhoSelect.innerHTML = '<option value="">Select LHO</option>' +
                data.lhos.map(x => `<option value="${x.id}">${escapeHtml(x.lho_name)}</option>`).join('');
            initSelect2('lho');
                
            // Populate Bank
            const bankSelect = document.getElementById('bank_name');
            bankSelect.innerHTML = '<option value="">Select Bank Name</option>' +
                data.banks.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
            initSelect2('bank_name');
                
            // Populate Customer
            const customerSelect = document.getElementById('customer_name');
            customerSelect.innerHTML = '<option value="">Select Customer Name</option>' +
                data.customers.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
            initSelect2('customer_name');
                
            // Populate Country
            const countrySelect = document.getElementById('country');
            countrySelect.innerHTML = '<option value="">Select Country</option>' +
                data.countries.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
            initSelect2('country');
                
            // Populate Zone
            const zoneSelect = document.getElementById('zone');
            zoneSelect.innerHTML = '<option value="">Select Zone</option>' +
                data.zones.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
            initSelect2('zone');
                
            // Default to India
            const indiaOption = Array.from(countrySelect.options).find(opt => opt.text.trim().toLowerCase() === 'india');
            if (indiaOption) {
                $(countrySelect).val(indiaOption.value).trigger('change');
            }
        } else {
            showError('Failed to load form options');
        }
    } catch (e) {
        console.error('Error loading initial options:', e);
        showError('Network error loading form options');
    }
}

// Cascading drop-down listeners
function setupDropdownListeners() {
    const countrySelect = document.getElementById('country');
    const stateSelect = document.getElementById('state');
    const citySelect = document.getElementById('city');
    
    // Country change triggers State loading
    countrySelect.addEventListener('change', async function() {
        const countryId = this.value;
        
        // Reset and disable dependants
        stateSelect.innerHTML = '<option value="">Select State</option>';
        stateSelect.disabled = true;
        initSelect2('state');
        
        citySelect.innerHTML = '<option value="">Select City</option>';
        citySelect.disabled = true;
        initSelect2('city');
        
        if (!countryId) return;
        
        try {
            stateSelect.innerHTML = '<option value="">Loading...</option>';
            initSelect2('state');
            
            const response = await fetch(`${OPTIONS_API_URL}?country_id=${countryId}`, { credentials: 'include' });
            const result = await response.json();
            
            if (result.success && result.data.states && result.data.states.length > 0) {
                stateSelect.innerHTML = '<option value="">Select State</option>' +
                    result.data.states.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
                stateSelect.disabled = false;
            } else {
                stateSelect.innerHTML = '<option value="">No states found</option>';
            }
            initSelect2('state');
        } catch (e) {
            console.error('Error loading states:', e);
            stateSelect.innerHTML = '<option value="">Error loading states</option>';
            initSelect2('state');
        }
    });
    
    // State change triggers City loading
    stateSelect.addEventListener('change', async function() {
        const stateId = this.value;
        
        // Reset and disable dependants
        citySelect.innerHTML = '<option value="">Select City</option>';
        citySelect.disabled = true;
        initSelect2('city');
        
        if (!stateId) return;
        
        try {
            citySelect.innerHTML = '<option value="">Loading...</option>';
            initSelect2('city');
            
            const response = await fetch(`${OPTIONS_API_URL}?state_id=${stateId}`, { credentials: 'include' });
            const result = await response.json();
            
            if (result.success && result.data.cities && result.data.cities.length > 0) {
                citySelect.innerHTML = '<option value="">Select City</option>' +
                    result.data.cities.map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
                citySelect.disabled = false;
            } else {
                citySelect.innerHTML = '<option value="">No cities found</option>';
            }
            initSelect2('city');
        } catch (e) {
            console.error('Error loading cities:', e);
            citySelect.innerHTML = '<option value="">Error loading cities</option>';
            initSelect2('city');
        }
    });
}

// Form submit handler
document.getElementById('site-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    clearErrors();
    
    const data = {
        action: 'create',
        site_name: document.getElementById('site_name').value.trim(),
        lho: getSelectedText('lho'),
        bank_name: getSelectedText('bank_name') || null,
        customer_name: getSelectedText('customer_name') || null,
        city: getSelectedText('city'),
        state: getSelectedText('state'),
        country: getSelectedText('country'),
        zone: getSelectedText('zone') || null,
        address: document.getElementById('address').value.trim(),
        latitude: document.getElementById('latitude').value,
        longitude: document.getElementById('longitude').value,
        status: document.getElementById('status').value
    };
    
    // Client-side validation
    if (!data.site_name) { showFieldError('site_name', 'Site name is required'); return; }
    if (!data.lho) { showFieldError('lho', 'LHO is required'); return; }
    if (!data.city) { showFieldError('city', 'City is required'); return; }
    if (!data.state) { showFieldError('state', 'State is required'); return; }
    if (!data.country) { showFieldError('country', 'Country is required'); return; }
    
    // Validate coordinates
    if (data.latitude !== '' && (parseFloat(data.latitude) < -90 || parseFloat(data.latitude) > 90)) {
        showFieldError('latitude', 'Latitude must be between -90 and 90'); return;
    }
    if (data.longitude !== '' && (parseFloat(data.longitude) < -180 || parseFloat(data.longitude) > 180)) {
        showFieldError('longitude', 'Longitude must be between -180 and 180'); return;
    }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    try {
        const response = await fetch(SAVE_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Site created successfully');
            setTimeout(() => window.location.href = 'index.php', 1500);
        } else {
            if (result.errors) {
                Object.keys(result.errors).forEach(field => {
                    const error = Array.isArray(result.errors[field]) ? result.errors[field][0] : result.errors[field];
                    showFieldError(field, typeof error === 'object' ? error.message : error);
                });
            } else {
                showError(result.error?.message || result.message || 'Failed to create site');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to create site. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Create Site';
    }
});

// Get current location
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
                document.getElementById('longitude').value = position.coords.longitude.toFixed(6);
                updateMapPreview();
            },
            function(error) {
                showError('Unable to get your location: ' + error.message);
            }
        );
    } else {
        showError('Geolocation is not supported by your browser');
    }
}

// Update map preview when coordinates change
document.getElementById('latitude').addEventListener('change', updateMapPreview);
document.getElementById('longitude').addEventListener('change', updateMapPreview);

function updateMapPreview() {
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    
    if (lat && lng && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        const mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01},${lat-0.01},${parseFloat(lng)+0.01},${parseFloat(lat)+0.01}&layer=mapnik&marker=${lat},${lng}`;
        document.getElementById('map-placeholder').classList.add('hidden');
        document.getElementById('map-preview').classList.remove('hidden');
        document.getElementById('map-preview').innerHTML = `<iframe src="${mapUrl}" class="w-full h-full rounded-lg"></iframe>`;
    }
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

function showError(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg';
    toast.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Show success function
function showSuccess(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg';
    toast.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Escape HTML utility
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
