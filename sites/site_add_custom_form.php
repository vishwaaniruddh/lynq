<?php
/**
 * Site Add with Dynamic Project Custom Form
 * 
 * URL: https://localhost/sites/site_add_custome_form.php
 * 
 * Essentials:
 * - Site Name *
 * - Location Information: Country, State, City (master data), Zone, Address
 * - Business Information: Bank Name & Customer Name (master data)
 * - Project Selection: Dropdown to choose project (e.g. XTPL)
 * - Dynamic Custom Fields: Automatically appends the custom form schema configured in Forms Master for the selected project
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Add Site (Project Custom Form)';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Add Site with Custom Form']
];

ob_start();
?>

<!-- JQuery & Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
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
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #6366f1 !important;
    color: #ffffff !important;
}
.select2-container--default .select2-results__option {
    padding: 8px 12px !important;
    font-size: 0.875rem !important;
}
</style>

<div class="w-full space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        
        <!-- Header -->
        <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-gradient-to-r from-white via-indigo-50/20 to-white">
            <div class="flex items-center gap-3">
                <span class="p-3 bg-primary/10 text-primary rounded-xl text-xl">
                    <i class="fas fa-layer-group"></i>
                </span>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Add Site with Project Dynamic Form</h3>
                    <p class="text-xs text-gray-500">Capture standard site details and dynamic project-specific custom fields</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="add.php" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-xs font-semibold flex items-center">
                    <i class="fas fa-file-alt mr-1.5"></i>Standard Form
                </a>
                <a href="index.php" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-xs font-semibold flex items-center">
                    <i class="fas fa-arrow-left mr-1.5"></i>Back to Sites
                </a>
            </div>
        </div>

        <form id="dynamic-site-form" onsubmit="handleDynamicSiteSubmit(event)" class="p-8 space-y-8 text-xs">
            
            <!-- ========================================== -->
            <!-- 1. PROJECT & ESSENTIAL IDENTIFIERS -->
            <!-- ========================================== -->
            <div class="border-b border-gray-100 pb-8">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 bg-indigo-600 rounded-full"></span>
                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">1. Project & Basic Information</h4>
                    </div>
                    <span class="text-[11px] text-indigo-600 font-medium">
                        <i class="fas fa-info-circle mr-1"></i>Project determines additional custom fields
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Project Selector -->
                    <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 md:col-span-3">
                        <label for="project_id" class="block font-bold text-indigo-900 mb-1.5 uppercase tracking-wider text-[11px]">
                            Target Project <span class="text-red-500">*</span>
                        </label>
                        <select id="project_id" name="project_id" onchange="onProjectSelectionChange()" required
                            class="w-full px-4 py-2.5 bg-white border border-indigo-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs font-bold text-gray-800">
                            <option value="">-- Select Project (e.g. XTPL) --</option>
                            <!-- Populated dynamically -->
                        </select>
                        <p class="text-[11px] text-indigo-700 mt-1.5 flex items-center gap-1">
                            <i class="fas fa-magic"></i> Selecting a project dynamically loads its custom Site Add form fields below.
                        </p>
                    </div>

                    <!-- Site Name -->
                    <div class="md:col-span-2">
                        <label for="site_name" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">
                            Site Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="site_name" name="site_name" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs font-medium"
                            placeholder="e.g. SBI ATM - Connaught Place Branch">
                        <p id="site_name-error" class="mt-1 text-[11px] text-red-500 hidden"></p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 2. BUSINESS INFORMATION (BANK & CUSTOMER)  -->
            <!-- ========================================== -->
            <div class="border-b border-gray-100 pb-8">
                <div class="flex items-center gap-2 mb-5">
                    <span class="w-2.5 h-2.5 bg-blue-600 rounded-full"></span>
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">2. Business Information</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="bank_name" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Bank Name</label>
                        <select id="bank_name" name="bank_name">
                            <option value="">Select Bank Name</option>
                        </select>
                    </div>
                    <div>
                        <label for="customer_name" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Customer Name</label>
                        <select id="customer_name" name="customer_name">
                            <option value="">Select Customer Name</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- 3. LOCATION INFORMATION                   -->
            <!-- ========================================== -->
            <div class="border-b border-gray-100 pb-8">
                <div class="flex items-center gap-2 mb-5">
                    <span class="w-2.5 h-2.5 bg-emerald-600 rounded-full"></span>
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">3. Location Information</h4>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label for="country" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">
                            Country <span class="text-red-500">*</span>
                        </label>
                        <select id="country" name="country" required>
                            <option value="">Select Country</option>
                        </select>
                        <p id="country-error" class="mt-1 text-[11px] text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="state" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">
                            State <span class="text-red-500">*</span>
                        </label>
                        <select id="state" name="state" required disabled>
                            <option value="">Select State</option>
                        </select>
                        <p id="state-error" class="mt-1 text-[11px] text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="city" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">
                            City <span class="text-red-500">*</span>
                        </label>
                        <select id="city" name="city" required disabled>
                            <option value="">Select City</option>
                        </select>
                        <p id="city-error" class="mt-1 text-[11px] text-red-500 hidden"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="zone" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Zone</label>
                        <select id="zone" name="zone">
                            <option value="">Select Zone</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="latitude" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Latitude</label>
                            <input type="number" step="any" id="latitude" name="latitude" min="-90" max="90"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs" placeholder="e.g. 28.6139">
                        </div>
                        <div>
                            <label for="longitude" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Longitude</label>
                            <input type="number" step="any" id="longitude" name="longitude" min="-180" max="180"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs" placeholder="e.g. 77.2090">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="address" class="block font-semibold text-gray-700 mb-1.5 uppercase tracking-wider">Full Street Address</label>
                    <textarea id="address" name="address" rows="2"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs"
                        placeholder="Enter complete address, building name, landmark, and postal code"></textarea>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- 4. DYNAMIC PROJECT CUSTOM FORM FIELDS CONTAINER          -->
            <!-- ======================================================== -->
            <div id="dynamic-form-section" class="pb-4">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 bg-purple-600 rounded-full"></span>
                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">4. Project Custom Form Fields</h4>
                    </div>
                    <div id="active-custom-form-badge">
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                            Select a project above to load fields
                        </span>
                    </div>
                </div>

                <!-- Loading Custom Fields Indicator -->
                <div id="custom-form-loading" class="hidden p-8 text-center bg-gray-50 rounded-xl border border-gray-100">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary mb-2"></i>
                    <p class="text-xs text-gray-500 font-medium">Loading project-specific custom form schema...</p>
                </div>

                <!-- Container where dynamic sections & inputs are inserted -->
                <div id="dynamic-fields-render-area" class="space-y-6">
                    <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                        <i class="fas fa-wpforms text-2xl text-gray-300 mb-2"></i>
                        <p class="text-xs text-gray-600 font-semibold">No project selected yet</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">Choose a project from the top dropdown (e.g. <span class="text-indigo-600 font-bold">XTPL</span>) to view its custom site attributes.</p>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- FORM ACTIONS & SUBMISSION                 -->
            <!-- ========================================== -->
            <div class="pt-6 border-t border-gray-200 flex items-center justify-between">
                <a href="index.php" class="px-5 py-2.5 text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Cancel
                </a>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="testSimulateValidation()" class="px-4 py-2.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition">
                        <i class="fas fa-clipboard-check mr-1.5"></i>Validate Form
                    </button>
                    <button type="submit" id="submit-btn" class="px-6 py-2.5 text-xs font-bold text-white bg-primary hover:bg-indigo-700 rounded-lg transition shadow-md flex items-center">
                        <i class="fas fa-check-circle mr-1.5"></i>Create Site Record
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Master Data & State
let formOptions = {};
let activeCustomForm = null;
let customFormUploadedFiles = {};

$(document).ready(function() {
    // Initialize Select2 dropdowns
    $('#bank_name, #customer_name, #country, #state, #city, #lho, #zone').select2({
        width: '100%',
        dropdownAutoWidth: true
    });

    loadInitialData();

    // Cascading location listeners
    $('#country').on('change', function() {
        const countryId = $(this).val();
        if (countryId) loadStates(countryId);
        else resetStateAndCity();
    });

    $('#state').on('change', function() {
        const stateId = $(this).val();
        if (stateId) loadCities(stateId);
        else resetCity();
    });
});

/**
 * Load Initial Dropdown Data (Projects, Countries, LHOs, Banks, Customers)
 */
async function loadInitialData() {
    try {
        const res = await fetch('../api/sites/form_options.php');
        const json = await res.json();
        
        if (json.success && json.data) {
            formOptions = json.data;

            // Populate Projects
            const projSelect = document.getElementById('project_id');
            projSelect.innerHTML = '<option value="">-- Select Project (e.g. XTPL) --</option><option value="0">Global / Default Template</option>';
            if (formOptions.projects && formOptions.projects.length > 0) {
                formOptions.projects.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.code ? `${p.name} (${p.code})` : p.name;
                    projSelect.appendChild(opt);
                });
            }

            // Auto select if only 1 project exists (like XTPL)
            if (formOptions.projects && formOptions.projects.length === 1) {
                projSelect.value = formOptions.projects[0].id;
                onProjectSelectionChange();
            }

            // Populate Countries
            populateSelect('#country', formOptions.countries || [], 'id', 'name', 'Select Country');
            // Populate LHOs
            populateSelect('#lho', formOptions.lhos || [], 'name', 'name', 'Select LHO');
            // Populate Banks
            populateSelect('#bank_name', formOptions.banks || [], 'name', 'name', 'Select Bank Name');
            // Populate Customers
            populateSelect('#customer_name', formOptions.customers || [], 'name', 'name', 'Select Customer Name');
            // Populate Zones
            populateSelect('#zone', formOptions.zones || [], 'name', 'name', 'Select Zone');
        }
    } catch (e) {
        console.error('Error loading initial form options', e);
        showToast('Failed to load master dropdowns', 'error');
    }
}

function populateSelect(selector, items, valueKey, labelKey, placeholder) {
    const $el = $(selector);
    $el.empty().append(new Option(placeholder, ''));
    items.forEach(item => {
        $el.append(new Option(item[labelKey], item[valueKey]));
    });
    $el.trigger('change.select2');
}

async function loadStates(countryId) {
    try {
        const res = await fetch(`../api/sites/form_options.php?country_id=${countryId}`);
        const json = await res.json();
        if (json.success && json.data) {
            populateSelect('#state', json.data.states || [], 'id', 'name', 'Select State');
            $('#state').prop('disabled', false);
            resetCity();
        }
    } catch (e) {
        console.error(e);
    }
}

async function loadCities(stateId) {
    try {
        const res = await fetch(`../api/sites/form_options.php?state_id=${stateId}`);
        const json = await res.json();
        if (json.success && json.data) {
            populateSelect('#city', json.data.cities || [], 'id', 'name', 'Select City');
            $('#city').prop('disabled', false);
        }
    } catch (e) {
        console.error(e);
    }
}

function resetStateAndCity() {
    $('#state').empty().append(new Option('Select State', '')).prop('disabled', true).trigger('change.select2');
    resetCity();
}

function resetCity() {
    $('#city').empty().append(new Option('Select City', '')).prop('disabled', true).trigger('change.select2');
}

/**
 * Triggered when Project dropdown changes:
 * Fetches the dynamic custom form schema for site_add and renders the fields
 */
async function onProjectSelectionChange() {
    const projectId = document.getElementById('project_id').value;
    const badgeEl = document.getElementById('active-custom-form-badge');
    const loadingEl = document.getElementById('custom-form-loading');
    const renderArea = document.getElementById('dynamic-fields-render-area');

    if (!projectId) {
        badgeEl.innerHTML = '<span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">Select a project above to load fields</span>';
        renderArea.innerHTML = `
            <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                <i class="fas fa-wpforms text-2xl text-gray-300 mb-2"></i>
                <p class="text-xs text-gray-600 font-semibold">No project selected yet</p>
                <p class="text-[11px] text-gray-400 mt-0.5">Choose a project from the top dropdown to view its custom site attributes.</p>
            </div>
        `;
        activeCustomForm = null;
        return;
    }

    loadingEl.classList.remove('hidden');
    renderArea.innerHTML = '';

    try {
        const url = `../api/sites/form_options.php?fetch_custom_form=1&purpose=site_add&project_id=${projectId}`;
        const res = await fetch(url);
        const json = await res.json();
        
        loadingEl.classList.add('hidden');

        if (json.success && json.data && json.data.form) {
            activeCustomForm = json.data.form;
            const form = activeCustomForm;
            const fieldsCount = form.fields ? form.fields.length : 0;

            badgeEl.innerHTML = `
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <i class="fas fa-check-circle text-indigo-600 mr-1"></i>${escapeHtml(form.form_name)} (v${form.version || 1})
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700">
                        ${fieldsCount} custom fields
                    </span>
                </div>
            `;

            renderDynamicFormFields(form.fields || []);
        } else {
            activeCustomForm = null;
            badgeEl.innerHTML = '<span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">No project-specific custom form configured</span>';
            renderArea.innerHTML = `
                <div class="p-6 text-center border border-amber-200 rounded-xl bg-amber-50/40">
                    <i class="fas fa-info-circle text-amber-600 text-xl mb-1.5"></i>
                    <p class="text-xs text-amber-900 font-bold">No custom fields found for this project</p>
                    <p class="text-[11px] text-amber-700 mt-0.5">You can design a dynamic Site Add form for this project anytime inside Forms Master.</p>
                    <a href="../masters/forms.php" target="_blank" class="mt-3 inline-flex items-center px-3 py-1.5 bg-primary text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition shadow-sm">
                        <i class="fas fa-plus mr-1"></i>Open Forms Master
                    </a>
                </div>
            `;
        }
    } catch (e) {
        loadingEl.classList.add('hidden');
        console.error('Error fetching custom form schema', e);
        showToast('Error loading project custom form', 'error');
    }
}

function resolveCustomFieldOptions(field) {
    if (field.resolved_options && Array.isArray(field.resolved_options) && field.resolved_options.length > 0) {
        return field.resolved_options;
    }
    if (field.options && field.options.source === 'master') {
        const mKey = field.options.master_key;
        if (mKey === 'banks' && formOptions.banks) {
            return formOptions.banks.map(b => ({ label: b.name, value: b.name }));
        }
        if (mKey === 'customers' && formOptions.customers) {
            return formOptions.customers.map(c => ({ label: c.name, value: c.name }));
        }
        if (mKey === 'projects' && formOptions.projects) {
            return formOptions.projects.map(p => ({ label: p.name, value: p.id }));
        }
    }
    if (Array.isArray(field.options)) {
        return field.options;
    }
    if (field.options && Array.isArray(field.options.options)) {
        return field.options.options;
    }
    return [];
}

function addSitePhoneRow(key, fieldName) {
    const container = document.getElementById(`phone-repeater-${key}`);
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 pt-1 phone-input-row';
    row.innerHTML = `
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 phone-icon">
                <i class="fas fa-phone-alt text-xs"></i>
            </span>
            <input type="tel" name="${fieldName}[]" data-key="${key}" data-type="phone" maxlength="10" placeholder="e.g. 9876543210" 
                class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-mono transition-colors" 
                oninput="validatePhoneDigits(this)">
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="p-2 text-red-500 hover:text-red-700 rounded-lg hover:bg-red-50 transition" title="Remove number">
            <i class="fas fa-trash-alt text-xs"></i>
        </button>
    `;
    container.appendChild(row);
    row.querySelector('input').focus();
}

function validatePhoneDigits(input) {
    // Keep only numbers
    let cleaned = input.value.replace(/[^0-9]/g, '');
    if (cleaned.length > 10) {
        cleaned = cleaned.slice(0, 10);
    }
    input.value = cleaned;

    const icon = input.parentElement.querySelector('.phone-icon i');
    if (cleaned.length === 10) {
        input.classList.remove('border-amber-400', 'border-red-500', 'border-gray-300');
        input.classList.add('border-emerald-500', 'bg-emerald-50/10');
        if (icon) {
            icon.className = 'fas fa-check-circle text-xs text-emerald-600';
        }
    } else if (cleaned.length > 0) {
        input.classList.remove('border-emerald-500', 'bg-emerald-50/10', 'border-red-500', 'border-gray-300');
        input.classList.add('border-amber-400');
        if (icon) {
            icon.className = 'fas fa-phone-alt text-xs text-amber-500';
        }
    } else {
        input.classList.remove('border-emerald-500', 'bg-emerald-50/10', 'border-amber-400', 'border-red-500');
        input.classList.add('border-gray-300');
        if (icon) {
            icon.className = 'fas fa-phone-alt text-xs text-gray-400';
        }
    }
}

/**
 * Render dynamic fields grouped by section
 */
function renderDynamicFormFields(fields) {
    const container = document.getElementById('dynamic-fields-render-area');
    container.innerHTML = '';
    customFormUploadedFiles = {};

    if (!fields || fields.length === 0) {
        container.innerHTML = '<div class="p-6 text-center text-gray-500 bg-gray-50 rounded-xl">No fields in this custom form.</div>';
        return;
    }

    // Group fields by section
    const sections = {};
    fields.forEach(f => {
        const sec = f.section_title || 'General Information';
        if (!sections[sec]) sections[sec] = [];
        sections[sec].push(f);
    });

    Object.keys(sections).forEach(secTitle => {
        const secCard = document.createElement('div');
        secCard.className = 'bg-gradient-to-b from-white to-gray-50/40 p-5 rounded-xl border border-gray-200 shadow-sm space-y-4';

        secCard.innerHTML = `
            <div class="border-b border-gray-100 pb-2 flex items-center justify-between">
                <h5 class="text-xs font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-4 bg-indigo-600 rounded-full inline-block"></span>
                    ${escapeHtml(secTitle)}
                </h5>
                <span class="text-[10px] text-gray-400 font-mono">Custom Section</span>
            </div>
            <div class="grid grid-cols-12 gap-4 items-start text-xs" id="dyn-sec-${secTitle.replace(/[^a-zA-Z0-9]/g, '_')}">
            </div>
        `;

        container.appendChild(secCard);
        const grid = secCard.querySelector(`#dyn-sec-${secTitle.replace(/[^a-zA-Z0-9]/g, '_')}`);

        sections[secTitle].forEach(field => {
            const colSpan = field.grid_width || 12;
            const fieldCol = document.createElement('div');
            fieldCol.className = `col-span-12 md:col-span-${colSpan} space-y-1.5`;

            const reqMark = field.is_required ? '<span class="text-red-500 font-bold">*</span>' : '';
            const help = field.help_text ? `<p class="text-[11px] text-gray-400 mt-0.5">${escapeHtml(field.help_text)}</p>` : '';
            const fieldName = `custom_${field.field_key}`;

            let inputHtml = '';

            switch (field.field_type) {
                case 'heading':
                    inputHtml = `
                        <div class="py-2 border-b border-indigo-100">
                            <h6 class="text-xs font-bold text-indigo-900 uppercase tracking-wide">${escapeHtml(field.field_label)}</h6>
                            ${help}
                        </div>
                    `;
                    break;

                case 'text':
                case 'email':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <input type="${field.field_type}" name="${fieldName}" data-key="${field.field_key}" placeholder="${escapeHtml(field.placeholder || '')}" 
                            value="${escapeHtml(field.default_value || '')}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs">
                        ${help}
                    `;
                    break;

                case 'number':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <input type="number" step="any" name="${fieldName}" data-key="${field.field_key}" placeholder="${escapeHtml(field.placeholder || '')}" 
                            value="${escapeHtml(field.default_value || '')}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs">
                        ${help}
                    `;
                    break;

                case 'phone':
                    const phoneRules = field.validation_rules || {};
                    if (phoneRules.multiple) {
                        inputHtml = `
                            <div class="flex items-center justify-between">
                                <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                                <span class="text-[10px] text-gray-400 font-medium">10 Digits</span>
                            </div>
                            <div id="phone-repeater-${field.field_key}" class="space-y-2">
                                <div class="flex items-center gap-2 phone-input-row">
                                    <div class="relative flex-1">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 phone-icon">
                                            <i class="fas fa-phone-alt text-xs"></i>
                                        </span>
                                        <input type="tel" name="${fieldName}[]" data-key="${field.field_key}" data-type="phone" maxlength="10" placeholder="${escapeHtml(field.placeholder || 'e.g. 9876543210')}" 
                                            class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-mono transition-colors" 
                                            ${field.is_required ? 'required' : ''} oninput="validatePhoneDigits(this)">
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="addSitePhoneRow('${field.field_key}', '${fieldName}')" 
                                class="mt-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-semibold flex items-center gap-1.5 transition">
                                <i class="fas fa-plus text-xs"></i> Add Another Number
                            </button>
                            ${help}
                        `;
                    } else {
                        inputHtml = `
                            <div class="flex items-center justify-between">
                                <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                                <span class="text-[10px] text-gray-400 font-medium">10 Digits</span>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 phone-icon">
                                    <i class="fas fa-phone-alt text-xs"></i>
                                </span>
                                <input type="tel" name="${fieldName}" data-key="${field.field_key}" data-type="phone" maxlength="10" placeholder="${escapeHtml(field.placeholder || 'e.g. 9876543210')}" 
                                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-mono transition-colors" 
                                    ${field.is_required ? 'required' : ''} oninput="validatePhoneDigits(this)">
                            </div>
                            ${help}
                        `;
                    }
                    break;

                case 'textarea':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <textarea rows="3" name="${fieldName}" data-key="${field.field_key}" placeholder="${escapeHtml(field.placeholder || '')}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs">${escapeHtml(field.default_value || '')}</textarea>
                        ${help}
                    `;
                    break;

                case 'select':
                    const opts = resolveCustomFieldOptions(field);
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <select name="${fieldName}" data-key="${field.field_key}" ${field.is_required ? 'required' : ''} class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">
                            <option value="">-- Select ${escapeHtml(field.field_label)} --</option>
                            ${opts.map(o => `<option value="${escapeHtml(o.value)}" ${field.default_value === o.value ? 'selected' : ''}>${escapeHtml(o.label)}</option>`).join('')}
                        </select>
                        ${help}
                    `;
                    break;

                case 'radio':
                    const rOpts = resolveCustomFieldOptions(field);
                    inputHtml = `
                        <label class="block font-semibold text-gray-700 mb-1">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <div class="flex flex-wrap gap-4 pt-1">
                            ${rOpts.map(o => `
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="${fieldName}" data-key="${field.field_key}" value="${escapeHtml(o.value)}" ${field.default_value === o.value ? 'checked' : ''} ${field.is_required ? 'required' : ''} class="text-primary focus:ring-primary h-4 w-4">
                                    <span class="ml-2 text-gray-700">${escapeHtml(o.label)}</span>
                                </label>
                            `).join('')}
                        </div>
                        ${help}
                    `;
                    break;

                case 'checkbox':
                    const cOpts = resolveCustomFieldOptions(field);
                    if (cOpts.length > 0) {
                        inputHtml = `
                            <label class="block font-semibold text-gray-700 mb-1">${escapeHtml(field.field_label)} ${reqMark}</label>
                            <div class="flex flex-wrap gap-4 pt-1">
                                ${cOpts.map(o => `
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="${fieldName}[]" data-key="${field.field_key}" value="${escapeHtml(o.value)}" class="rounded text-primary focus:ring-primary h-4 w-4">
                                        <span class="ml-2 text-gray-700">${escapeHtml(o.label)}</span>
                                    </label>
                                `).join('')}
                            </div>
                            ${help}
                        `;
                    } else {
                        inputHtml = `
                            <div class="pt-2">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="${fieldName}" data-key="${field.field_key}" value="1" ${field.is_required ? 'required' : ''} class="rounded text-primary focus:ring-primary h-4 w-4">
                                    <span class="ml-2 font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</span>
                                </label>
                                ${help}
                            </div>
                        `;
                    }
                    break;

                case 'file':
                    const rules = field.validation_rules || {};
                    const acceptExt = rules.allowed_extensions ? rules.allowed_extensions.split(',').map(e => '.' + e.trim().replace('.', '')).join(',') : '*';
                    const maxMb = rules.max_size_mb || 5;
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-3 text-center bg-gray-50 hover:bg-white transition cursor-pointer relative" id="dropzone-${field.field_key}">
                            <input type="file" name="${fieldName}" data-key="${field.field_key}" accept="${acceptExt}" ${rules.multiple ? 'multiple' : ''} ${field.is_required ? 'required' : ''}
                                onchange="handleDynamicFileInput(this, '${field.field_key}', '${escapeHtml(rules.allowed_extensions || '')}', ${maxMb})"
                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                            <i class="fas fa-cloud-upload-alt text-indigo-500 text-lg mb-1"></i>
                            <p class="text-xs font-semibold text-gray-700" id="file-label-${field.field_key}">Choose file or drop here</p>
                            <p class="text-[10px] text-gray-400">Accepted: ${escapeHtml(rules.allowed_extensions || 'Any')} (Max: ${maxMb}MB)</p>
                        </div>
                        <p id="file-error-${field.field_key}" class="text-[11px] text-red-500 hidden"></p>
                        ${help}
                    `;
                    break;

                case 'date':
                case 'datetime':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <input type="${field.field_type === 'date' ? 'date' : 'datetime-local'}" name="${fieldName}" data-key="${field.field_key}" 
                            value="${escapeHtml(field.default_value || '')}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs">
                        ${help}
                    `;
                    break;
            }

            fieldCol.innerHTML = inputHtml;
            grid.appendChild(fieldCol);
        });
    });
}

function handleDynamicFileInput(input, key, allowedExts, maxMb) {
    const errorEl = document.getElementById(`file-error-${key}`);
    const labelEl = document.getElementById(`file-label-${key}`);
    errorEl.classList.add('hidden');
    errorEl.textContent = '';

    if (!input.files || input.files.length === 0) {
        labelEl.textContent = 'Choose file or drop here';
        delete customFormUploadedFiles[key];
        return;
    }

    const files = Array.from(input.files);
    const exts = allowedExts ? allowedExts.toLowerCase().split(',').map(e => e.trim().replace('.', '')) : [];

    for (let file of files) {
        const fileExt = file.name.split('.').pop().toLowerCase();
        if (exts.length > 0 && !exts.includes(fileExt)) {
            errorEl.textContent = `Invalid file format: .${fileExt}. Allowed formats: ${allowedExts}`;
            errorEl.classList.remove('hidden');
            input.value = '';
            labelEl.textContent = 'Choose file or drop here';
            delete customFormUploadedFiles[key];
            return;
        }

        const sizeMb = file.size / (1024 * 1024);
        if (sizeMb > maxMb) {
            errorEl.textContent = `File exceeds size limit: ${(sizeMb).toFixed(1)}MB > ${maxMb}MB maximum`;
            errorEl.classList.remove('hidden');
            input.value = '';
            labelEl.textContent = 'Choose file or drop here';
            delete customFormUploadedFiles[key];
            return;
        }
    }

    labelEl.innerHTML = `<span class="text-indigo-600 font-bold"><i class="fas fa-check-circle mr-1"></i> ${files.map(f => f.name).join(', ')}</span>`;
    customFormUploadedFiles[key] = files.map(f => f.name);
}

/**
 * Handle submission of the complete site form (Standard + Dynamic Custom Fields)
 */
async function handleDynamicSiteSubmit(event) {
    event.preventDefault();
    clearErrors();

    const siteName = document.getElementById('site_name').value.trim();
    const country = $('#country option:selected').text();
    const state = $('#state option:selected').text();
    const city = $('#city option:selected').text();
    const projectId = document.getElementById('project_id').value;

    if (!siteName) {
        showFieldError('site_name', 'Site Name is required');
        return;
    }
    if (!projectId) {
        showToast('Please select a Target Project', 'error');
        document.getElementById('project_id').focus();
        return;
    }
    if (!$('#country').val()) {
        showFieldError('country', 'Country is required');
        return;
    }
    if (!$('#state').val()) {
        showFieldError('state', 'State is required');
        return;
    }
    if (!$('#city').val()) {
        showFieldError('city', 'City is required');
        return;
    }

    // Collect Dynamic Custom Fields
    const customFieldsPayload = {};
    if (activeCustomForm && activeCustomForm.fields) {
        for (let field of activeCustomForm.fields) {
            if (field.field_type === 'heading') continue;

            const key = field.field_key;
            const fieldName = `custom_${key}`;
            let val = null;

            if (field.field_type === 'radio') {
                const checked = document.querySelector(`input[name="${fieldName}"]:checked`);
                val = checked ? checked.value : null;
            } else if (field.field_type === 'checkbox') {
                const checks = Array.from(document.querySelectorAll(`input[name="${fieldName}[]"]:checked`)).map(c => c.value);
                val = checks.length > 0 ? checks : (document.querySelector(`input[name="${fieldName}"]:checked`) ? 1 : null);
            } else if (field.field_type === 'file') {
            } else if (field.field_type === 'phone') {
                const phoneRules = field.validation_rules || {};
                if (phoneRules.multiple) {
                    const inputs = Array.from(document.querySelectorAll(`input[name="${fieldName}[]"]`));
                    const validNumbers = [];
                    
                    for (let i = 0; i < inputs.length; i++) {
                        const inp = inputs[i];
                        const num = inp.value.trim();
                        if (num === '') continue; // Skip blank extra rows
                        
                        const digitsOnly = num.replace(/\D/g, '');
                        if (phoneRules.strict_10_digit !== false && digitsOnly.length !== 10) {
                            inp.focus();
                            inp.classList.remove('border-gray-300', 'border-amber-400');
                            inp.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                            showToast(`Contact Number "${num}" is invalid. Must be exactly 10 digits.`, 'error');
                            return;
                        }
                        validNumbers.push(num);
                    }
                    val = validNumbers.length > 0 ? validNumbers : null;
                } else {
                    const inputEl = document.querySelector(`[name="${fieldName}"]`);
                    val = inputEl ? inputEl.value.trim() : null;
                    if (val && phoneRules.strict_10_digit !== false) {
                        const digitsOnly = val.replace(/\D/g, '');
                        if (digitsOnly.length !== 10) {
                            inputEl.focus();
                            inputEl.classList.remove('border-gray-300', 'border-amber-400');
                            inputEl.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                            showToast(`Contact Number "${val}" is invalid. Must be exactly 10 digits.`, 'error');
                            return;
                        }
                    }
                }
            } else {
                const inputEl = document.querySelector(`[name="${fieldName}"]`);
                val = inputEl ? inputEl.value.trim() : null;
            }

            if (field.is_required && (val === null || val === '' || (Array.isArray(val) && val.length === 0))) {
                showToast(`Required custom field missing: "${field.field_label}"`, 'error');
                return;
            }

            customFieldsPayload[key] = {
                label: field.field_label,
                type: field.field_type,
                value: val,
                section: field.section_title || 'General'
            };
        }
    }

    const payload = {
        action: 'create',
        site_name: siteName,
        project_id: parseInt(projectId),
        bank_name: $('#bank_name').val() ? $('#bank_name option:selected').text() : null,
        customer_name: $('#customer_name').val() ? $('#customer_name option:selected').text() : null,
        country: country,
        state: state,
        city: city,
        zone: $('#zone').val() ? $('#zone option:selected').text() : null,
        address: document.getElementById('address').value.trim() || null,
        latitude: document.getElementById('latitude').value || null,
        longitude: document.getElementById('longitude').value || null,
        status: document.getElementById('status').value || 'active',
        custom_fields_json: {
            form_id: activeCustomForm ? activeCustomForm.id : null,
            form_name: activeCustomForm ? activeCustomForm.form_name : null,
            form_code: activeCustomForm ? activeCustomForm.form_code : null,
            fields: customFieldsPayload
        }
    };

    const submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Creating Site...';

    try {
        const res = await fetch('../api/sites/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await res.json();
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check-circle mr-1.5"></i>Create Site Record';

        if (result.success) {
            showToast('Site and project dynamic fields created successfully!', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1200);
        } else {
            if (result.errors && Array.isArray(result.errors)) {
                result.errors.forEach(err => showFieldError(err.field, err.message));
            } else {
                showToast(result.message || 'Failed to create site', 'error');
            }
        }
    } catch (e) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check-circle mr-1.5"></i>Create Site Record';
        console.error(e);
        showToast('Server error while saving site', 'error');
    }
}

function testSimulateValidation() {
    const siteName = document.getElementById('site_name').value.trim();
    const country = $('#country').val();
    const projectId = document.getElementById('project_id').value;

    if (!siteName || !country || !projectId) {
        showToast('Please fill all essential required fields marked with *', 'error');
        return;
    }

    showToast('Essential validation passed! Ready to submit.', 'success');
}

function showFieldError(field, msg) {
    const el = document.getElementById(`${field}-error`);
    if (el) {
        el.textContent = msg;
        el.classList.remove('hidden');
    } else {
        showToast(msg, 'error');
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

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgClass = type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-red-600' : 'bg-gray-800');
    toast.className = `fixed bottom-4 right-4 ${bgClass} text-white px-4 py-3 rounded-xl shadow-2xl text-xs font-semibold flex items-center gap-2 z-50 transition-all duration-300 transform translate-y-4 opacity-0`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')}"></i> ${escapeHtml(message)}`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    }, 50);

    setTimeout(() => {
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
