<?php
/**
 * Dynamic Custom Site Edit Page
 * Allows editing standard site details and all dynamic project custom fields
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$siteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$siteRepo = new SiteRepository();
$site = $siteRepo->findById($siteId);

if (!$site) {
    header('Location: ../sites/index_new.php');
    exit;
}

$baseUrl = '..';
$pageTitle = 'Edit Site: ' . htmlspecialchars($site['site_name']);
$currentPage = 'sites_new';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites Master Tracking', 'url' => '../sites/index_new.php'],
    ['label' => htmlspecialchars($site['site_name']), 'url' => "../sites/site_view_custom_form.php?id={$siteId}"],
    ['label' => 'Edit']
];

ob_start();
?>

<!-- Include Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single {
        height: 38px !important;
        border-radius: 0.5rem !important;
        border: 1px solid #d1d5db !important;
        padding-top: 4px;
        font-size: 0.75rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
</style>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-bold text-gray-900">Edit Site: <?= htmlspecialchars($site['site_name']) ?></h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    ID: #<?= $site['id'] ?>
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Update site essentials and project-specific custom form fields</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="../sites/site_view_custom_form.php?id=<?= $site['id'] ?>" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center">
                <i class="fas fa-eye mr-1.5"></i>View Details
            </a>
            <a href="../sites/index_new.php" class="px-3.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg transition">
                Cancel
            </a>
        </div>
    </div>

    <!-- Edit Form -->
    <form id="edit-site-form" onsubmit="handleSiteUpdate(event)" class="space-y-6">
        <input type="hidden" id="site_id" value="<?= $site['id'] ?>">

        <!-- Section 1: Core Essentials -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2.5 h-4 bg-primary rounded-full"></span>
                    1. Site Essential & Master Information
                </h3>
                <span class="text-[11px] text-gray-400">Core CRM Identifiers</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <!-- Target Project -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">
                        Target Project <span class="text-red-500">*</span>
                    </label>
                    <select id="project_id" onchange="onProjectChange()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-medium" required>
                        <option value="">-- Select Project --</option>
                    </select>
                </div>

                <!-- Site Name -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">
                        Site Name / ATM ID <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="site_name" value="<?= htmlspecialchars($site['site_name']) ?>" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-medium">
                </div>

                <!-- Status -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Site Status</label>
                    <select id="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">
                        <option value="active" <?= $site['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $site['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <!-- Bank Name -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Bank Name</label>
                    <select id="bank_name" class="w-full select2-bank">
                        <option value="">-- Select Bank --</option>
                    </select>
                </div>

                <!-- Customer Name -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Customer Name</label>
                    <select id="customer_name" class="w-full select2-customer">
                        <option value="">-- Select Customer --</option>
                    </select>
                </div>

                <!-- Zone -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Zone</label>
                    <select id="zone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">
                        <option value="">-- Select Zone --</option>
                    </select>
                </div>

                <!-- Location: Country, State, City -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Country <span class="text-red-500">*</span></label>
                    <select id="country" class="w-full select2-country" required>
                        <option value="">-- Select Country --</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">State <span class="text-red-500">*</span></label>
                    <select id="state" class="w-full select2-state" required>
                        <option value="">-- Select State --</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                    <select id="city" class="w-full select2-city" required>
                        <option value="">-- Select City --</option>
                    </select>
                </div>

                <!-- Street Address -->
                <div class="md:col-span-3">
                    <label class="block font-semibold text-gray-700 mb-1">Full Street Address</label>
                    <textarea id="address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs"><?= htmlspecialchars($site['address'] ?? '') ?></textarea>
                </div>

                <!-- Coordinates -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Latitude</label>
                    <input type="number" step="any" id="latitude" value="<?= htmlspecialchars($site['latitude'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Longitude</label>
                    <input type="number" step="any" id="longitude" value="<?= htmlspecialchars($site['longitude'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono">
                </div>
            </div>
        </div>

        <!-- Section 2: Dynamic Project Custom Form Fields -->
        <div id="dynamic-form-container" class="space-y-4">
            <div id="custom-form-loading" class="p-8 text-center bg-white rounded-2xl border border-gray-200 shadow-sm text-gray-400">
                <i class="fas fa-spinner fa-spin text-2xl text-primary mb-2"></i>
                <p class="text-xs">Loading project custom form schema...</p>
            </div>
            <div id="dynamic-fields-render-area" class="space-y-4"></div>
        </div>

        <!-- Submit Bar -->
        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
            <a href="../sites/site_view_custom_form.php?id=<?= $site['id'] ?>" class="px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 rounded-lg transition">
                Cancel
            </a>
            <button type="submit" id="submit-btn" class="px-6 py-2.5 text-xs font-bold text-white bg-primary hover:bg-indigo-700 rounded-lg transition shadow-md flex items-center">
                <i class="fas fa-save mr-2"></i>Save & Update Site
            </button>
        </div>
    </form>
</div>

<!-- Select2 & Helpers -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let formOptions = {};
let activeCustomForm = null;
let customFormUploadedFiles = {};
const existingSiteData = <?= json_encode($site, JSON_UNESCAPED_UNICODE) ?>;
const existingCustomData = existingSiteData.custom_fields_json ? (typeof existingSiteData.custom_fields_json === 'string' ? JSON.parse(existingSiteData.custom_fields_json) : existingSiteData.custom_fields_json) : null;
const existingFieldsMap = existingCustomData && existingCustomData.fields ? existingCustomData.fields : {};

document.addEventListener('DOMContentLoaded', async () => {
    initSelect2();
    await loadFormOptions();
    const projId = existingSiteData.project_id || (formOptions.projects && formOptions.projects.length > 0 ? formOptions.projects[0].id : 1);
    if (projId) {
        document.getElementById('project_id').value = projId;
        await loadProjectCustomForm(projId);
    } else {
        document.getElementById('custom-form-loading').classList.add('hidden');
    }
});

function initSelect2() {
    $('.select2-bank, .select2-customer, .select2-country, .select2-state, .select2-city').select2({
        width: '100%'
    });
}

async function loadFormOptions() {
    try {
        const res = await fetch('../api/sites/form_options.php');
        const data = await res.json();
        if (data.success) {
            formOptions = data.data;
            populateMasters();
        }
    } catch (e) {
        console.error('Failed to load master options', e);
    }
}

function populateMasters() {
    // Projects
    const projSelect = document.getElementById('project_id');
    (formOptions.projects || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = `${p.name} (${p.code || 'ID: ' + p.id})`;
        if (parseInt(existingSiteData.project_id) === parseInt(p.id)) opt.selected = true;
        projSelect.appendChild(opt);
    });

    // Banks
    const bankSelect = $('#bank_name');
    (formOptions.banks || []).forEach(b => {
        const opt = new Option(b.name, b.id, false, existingSiteData.bank_name === b.name);
        bankSelect.append(opt);
    });
    bankSelect.trigger('change');

    // Customers
    const custSelect = $('#customer_name');
    (formOptions.customers || []).forEach(c => {
        const opt = new Option(c.name, c.id, false, existingSiteData.customer_name === c.name);
        custSelect.append(opt);
    });
    custSelect.trigger('change');

    // Zones
    const zoneSelect = document.getElementById('zone');
    (formOptions.zones || []).forEach(z => {
        const opt = document.createElement('option');
        opt.value = z.id;
        opt.textContent = z.name;
        if (existingSiteData.zone === z.name) opt.selected = true;
        zoneSelect.appendChild(opt);
    });

    // Countries
    const countrySelect = $('#country');
    (formOptions.countries || []).forEach(c => {
        const opt = new Option(c.name, c.id, false, existingSiteData.country === c.name || c.name === 'India');
        countrySelect.append(opt);
    });
    countrySelect.trigger('change');

    // States
    const stateSelect = $('#state');
    (formOptions.states || []).forEach(s => {
        const opt = new Option(s.name, s.id, false, existingSiteData.state === s.name);
        stateSelect.append(opt);
    });
    stateSelect.trigger('change');

    // Cities
    const citySelect = $('#city');
    (formOptions.cities || []).forEach(ct => {
        const opt = new Option(ct.name, ct.id, false, existingSiteData.city === ct.name);
        citySelect.append(opt);
    });
    citySelect.trigger('change');
}

async function onProjectChange() {
    const projId = document.getElementById('project_id').value;
    await loadProjectCustomForm(projId);
}

async function loadProjectCustomForm(projectId) {
    const loadingEl = document.getElementById('custom-form-loading');
    const renderArea = document.getElementById('dynamic-fields-render-area');
    loadingEl.classList.remove('hidden');
    renderArea.innerHTML = '';

    try {
        const res = await fetch(`../api/sites/form_options.php?fetch_custom_form=1&purpose=site_add&project_id=${projectId || 0}`);
        const json = await res.json();
        loadingEl.classList.add('hidden');

        if (json.success && json.data && json.data.form) {
            activeCustomForm = json.data.form;
            renderDynamicFormFields(activeCustomForm.fields || []);
        } else {
            activeCustomForm = null;
            renderArea.innerHTML = `
                <div class="p-6 text-center bg-gray-50 rounded-xl border border-gray-200">
                    <i class="fas fa-info-circle text-gray-400 text-xl mb-1"></i>
                    <p class="text-xs text-gray-600 font-medium">No custom form configured for this project.</p>
                </div>
            `;
        }
    } catch (e) {
        loadingEl.classList.add('hidden');
        console.error('Error fetching custom form schema', e);
    }
}

function resolveCustomFieldOptions(field) {
    if (field.resolved_options && Array.isArray(field.resolved_options) && field.resolved_options.length > 0) {
        return field.resolved_options;
    }
    if (field.options && field.options.source === 'master') {
        const mKey = field.options.master_key;
        if (mKey === 'banks' && formOptions.banks) return formOptions.banks.map(b => ({ label: b.name, value: b.name }));
        if (mKey === 'customers' && formOptions.customers) return formOptions.customers.map(c => ({ label: c.name, value: c.name }));
        if (mKey === 'projects' && formOptions.projects) return formOptions.projects.map(p => ({ label: p.name, value: p.id }));
    }
    if (Array.isArray(field.options)) return field.options;
    if (field.options && Array.isArray(field.options.options)) return field.options.options;
    return [];
}

function addSitePhoneRow(key, fieldName, initialVal = '') {
    const container = document.getElementById(`phone-repeater-${key}`);
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 pt-1 phone-input-row';
    row.innerHTML = `
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 phone-icon">
                <i class="fas fa-phone-alt text-xs"></i>
            </span>
            <input type="tel" name="${fieldName}[]" data-key="${key}" data-type="phone" maxlength="10" value="${escapeHtml(initialVal)}" placeholder="Additional contact number..." 
                class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-mono transition-colors" oninput="validatePhoneDigits(this)">
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="p-2 text-red-500 hover:text-red-700 rounded-lg hover:bg-red-50 transition" title="Remove number">
            <i class="fas fa-trash-alt text-xs"></i>
        </button>
    `;
    container.appendChild(row);
    if (!initialVal) row.querySelector('input').focus();
    validatePhoneDigits(row.querySelector('input'));
}

function validatePhoneDigits(input) {
    let cleaned = input.value.replace(/[^0-9]/g, '');
    if (cleaned.length > 10) cleaned = cleaned.slice(0, 10);
    input.value = cleaned;

    const icon = input.parentElement.querySelector('.phone-icon i');
    if (cleaned.length === 10) {
        input.classList.remove('border-amber-400', 'border-red-500', 'border-gray-300');
        input.classList.add('border-emerald-500', 'bg-emerald-50/10');
        if (icon) icon.className = 'fas fa-check-circle text-xs text-emerald-600';
    } else if (cleaned.length > 0) {
        input.classList.remove('border-emerald-500', 'bg-emerald-50/10', 'border-red-500', 'border-gray-300');
        input.classList.add('border-amber-400');
        if (icon) icon.className = 'fas fa-phone-alt text-xs text-amber-500';
    } else {
        input.classList.remove('border-emerald-500', 'bg-emerald-50/10', 'border-amber-400', 'border-red-500');
        input.classList.add('border-gray-300');
        if (icon) icon.className = 'fas fa-phone-alt text-xs text-gray-400';
    }
}

function renderDynamicFormFields(fields) {
    const container = document.getElementById('dynamic-fields-render-area');
    container.innerHTML = '';

    if (!fields || fields.length === 0) return;

    const sections = {};
    fields.forEach(f => {
        const sec = f.section_title || 'General Information';
        if (!sections[sec]) sections[sec] = [];
        sections[sec].push(f);
    });

    Object.keys(sections).forEach(secTitle => {
        const secCard = document.createElement('div');
        secCard.className = 'bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4';

        secCard.innerHTML = `
            <div class="border-b border-gray-100 pb-2 flex items-center justify-between">
                <h5 class="text-xs font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-4 bg-indigo-600 rounded-full inline-block"></span>
                    ${escapeHtml(secTitle)}
                </h5>
                <span class="text-[10px] text-gray-400 font-mono">Custom Section</span>
            </div>
            <div class="grid grid-cols-12 gap-4 items-start text-xs" id="dyn-sec-${secTitle.replace(/[^a-zA-Z0-9]/g, '_')}"></div>
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
            
            // Resolve existing saved value
            const existingVal = existingFieldsMap[field.field_key]?.value ?? (field.default_value || '');

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
                            value="${escapeHtml(existingVal)}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">
                        ${help}
                    `;
                    break;

                case 'number':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <input type="number" step="any" name="${fieldName}" data-key="${field.field_key}" placeholder="${escapeHtml(field.placeholder || '')}" 
                            value="${escapeHtml(existingVal)}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">
                        ${help}
                    `;
                    break;

                case 'phone':
                    const phoneRules = field.validation_rules || {};
                    const phoneArr = Array.isArray(existingVal) ? existingVal : (existingVal ? [existingVal] : ['']);
                    const firstPhone = phoneArr[0] || '';

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
                                        <input type="tel" name="${fieldName}[]" data-key="${field.field_key}" data-type="phone" maxlength="10" 
                                            value="${escapeHtml(firstPhone)}" placeholder="${escapeHtml(field.placeholder || 'e.g. 9876543210')}" 
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
                                <input type="tel" name="${fieldName}" data-key="${field.field_key}" data-type="phone" maxlength="10" 
                                    value="${escapeHtml(firstPhone)}" placeholder="${escapeHtml(field.placeholder || 'e.g. 9876543210')}" 
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
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">${escapeHtml(existingVal)}</textarea>
                        ${help}
                    `;
                    break;

                case 'select':
                    const opts = resolveCustomFieldOptions(field);
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <select name="${fieldName}" data-key="${field.field_key}" ${field.is_required ? 'required' : ''} class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">
                            <option value="">-- Select ${escapeHtml(field.field_label)} --</option>
                            ${opts.map(o => `<option value="${escapeHtml(o.value)}" ${String(existingVal) === String(o.value) ? 'selected' : ''}>${escapeHtml(o.label)}</option>`).join('')}
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
                                    <input type="radio" name="${fieldName}" data-key="${field.field_key}" value="${escapeHtml(o.value)}" ${String(existingVal) === String(o.value) ? 'checked' : ''} ${field.is_required ? 'required' : ''} class="text-primary focus:ring-primary h-4 w-4">
                                    <span class="ml-2 text-gray-700">${escapeHtml(o.label)}</span>
                                </label>
                            `).join('')}
                        </div>
                        ${help}
                    `;
                    break;

                case 'checkbox':
                    const cOpts = resolveCustomFieldOptions(field);
                    const selChecks = Array.isArray(existingVal) ? existingVal : (existingVal ? [existingVal] : []);
                    if (cOpts.length > 0) {
                        inputHtml = `
                            <label class="block font-semibold text-gray-700 mb-1">${escapeHtml(field.field_label)} ${reqMark}</label>
                            <div class="flex flex-wrap gap-4 pt-1">
                                ${cOpts.map(o => `
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="${fieldName}[]" data-key="${field.field_key}" value="${escapeHtml(o.value)}" ${selChecks.includes(o.value) ? 'checked' : ''} class="rounded text-primary focus:ring-primary h-4 w-4">
                                        <span class="ml-2 text-gray-700">${escapeHtml(o.label)}</span>
                                    </label>
                                `).join('')}
                            </div>
                            ${help}
                        `;
                    }
                    break;
            }

            fieldCol.innerHTML = inputHtml;
            grid.appendChild(fieldCol);

            // Populate remaining phone rows if multiple
            if (field.field_type === 'phone' && (field.validation_rules || {}).multiple) {
                const phoneArr = Array.isArray(existingVal) ? existingVal : (existingVal ? [existingVal] : []);
                for (let pi = 1; pi < phoneArr.length; pi++) {
                    addSitePhoneRow(field.field_key, fieldName, phoneArr[pi]);
                }
                const firstInp = fieldCol.querySelector('input[type="tel"]');
                if (firstInp) validatePhoneDigits(firstInp);
            }
        });
    });
}

async function handleSiteUpdate(e) {
    e.preventDefault();

    const siteId = document.getElementById('site_id').value;
    const siteName = document.getElementById('site_name').value.trim();
    const projectId = document.getElementById('project_id').value;
    const country = $('#country option:selected').text();
    const state = $('#state option:selected').text();
    const city = $('#city option:selected').text();

    if (!siteName) {
        showToast('Site Name is required', 'error');
        return;
    }
    if (!projectId) {
        showToast('Target Project is required', 'error');
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
                val = checks.length > 0 ? checks : null;
            } else if (field.field_type === 'phone') {
                const phoneRules = field.validation_rules || {};
                if (phoneRules.multiple) {
                    const inputs = Array.from(document.querySelectorAll(`input[name="${fieldName}[]"]`));
                    const validNumbers = [];
                    for (let inp of inputs) {
                        const num = inp.value.trim();
                        if (num === '') continue;
                        const digitsOnly = num.replace(/\D/g, '');
                        if (phoneRules.strict_10_digit !== false && digitsOnly.length !== 10) {
                            inp.focus();
                            inp.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                            showToast(`Contact Number "${num}" is invalid. Must be exactly 10 digits.`, 'error');
                            return;
                        }
                        validNumbers.push(num);
                    }
                    val = validNumbers.length > 0 ? validNumbers : null;
                } else {
                    const inp = document.querySelector(`[name="${fieldName}"]`);
                    val = inp ? inp.value.trim() : null;
                    if (val && phoneRules.strict_10_digit !== false) {
                        const digitsOnly = val.replace(/\D/g, '');
                        if (digitsOnly.length !== 10) {
                            inp.focus();
                            inp.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                            showToast(`Contact Number is invalid. Must be exactly 10 digits.`, 'error');
                            return;
                        }
                    }
                }
            } else {
                const inp = document.querySelector(`[name="${fieldName}"]`);
                val = inp ? inp.value.trim() : null;
            }

            if (field.is_required && (val === null || val === '' || (Array.isArray(val) && val.length === 0))) {
                showToast(`Required field missing: "${field.field_label}"`, 'error');
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
        action: 'update',
        id: parseInt(siteId),
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
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating Site...';

    try {
        const res = await fetch('../api/sites/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save & Update Site';

        if (result.success) {
            showToast('Site updated successfully!', 'success');
            setTimeout(() => {
                window.location.href = `../sites/site_view_custom_form.php?id=${siteId}`;
            }, 800);
        } else {
            showToast(result.message || 'Failed to update site', 'error');
        }
    } catch (e) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save & Update Site';
        console.error('Update error', e);
        showToast('Error communicating with server', 'error');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[m]);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
