<?php
/**
 * Comprehensive Sites Overview Page (New Design)
 * 
 * Displays full site lifecycle tracking:
 * - Top Active Project Switcher & Context Bar
 * - 1. Basic Info (Site Name, Project, Bank Name, Location, Status)
 * - 2A. Feasibility Delegation (Contractor, Status, Date/Time)
 * - 2B. Installation Delegation (Contractor, Status, Date/Time)
 * - 3. Survey / Feasibility (Surveyor, Date/Time, Status, View)
 * - 4. Material Part (Req #, Status, Manifest, Dispatch, Delivery)
 * - 5. Installation (Installer, Completed, Latest Milestone, Status, View)
 * - 6. Network & Actions (IP Details, Actions: View, Edit, Delegate)
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Sites Master Tracking';
$currentPage = 'sites_new';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites Master Tracking']
];

ob_start();
?>

<div class="space-y-6">
    <!-- Top Active Project Switcher & Context Bar -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-5 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center md:justify-between gap-4 border border-indigo-800/40">
        <div class="flex items-center space-x-3.5">
            <span class="w-11 h-11 rounded-xl bg-indigo-500/20 text-indigo-400 border border-indigo-400/30 flex items-center justify-center text-xl shadow-inner">
                <i class="fas fa-layer-group"></i>
            </span>
            <div>
                <span class="text-[10px] font-bold tracking-wider text-indigo-300 uppercase block">Active Project Scope</span>
                <div class="flex items-center gap-2 mt-0.5">
                    <h3 id="current-project-label" class="text-lg font-extrabold text-white tracking-tight">XTPL Project</h3>
                    <span id="current-project-badge" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/30 text-indigo-300 border border-indigo-400/40">24 Sites</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center bg-white/10 backdrop-blur-md rounded-xl p-1 border border-white/15 shadow-sm">
                <span class="px-2.5 text-xs text-indigo-200 font-semibold flex items-center gap-1.5 whitespace-nowrap">
                    <i class="fas fa-exchange-alt text-indigo-400"></i> Switch Project:
                </span>
                <select id="top-project-select" onchange="onTopProjectChange(this.value)" 
                    class="bg-slate-800 text-white border-0 rounded-lg px-3 py-1.5 text-xs font-bold focus:ring-2 focus:ring-indigo-400 cursor-pointer shadow-sm min-w-[180px]">
                    <!-- Populated dynamically -->
                </select>
            </div>
            
            <?php if (isAdvUser()): ?>
            <a href="../sites/site_add_custome_form.php" id="btn-add-site-project" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center whitespace-nowrap">
                <i class="fas fa-plus mr-1.5"></i>Add Site to Project
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Header & Secondary Actions -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800 tracking-tight">Sites Master Lifecycle Tracker</h2>
            <p class="text-xs text-gray-500 mt-0.5">Track survey feasibility, material dispatches, installations, and router network status</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="refreshData()" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-xs font-semibold shadow-sm flex items-center">
                <i class="fas fa-sync-alt mr-1.5"></i>Refresh
            </button>
            <button onclick="exportData()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition text-xs font-semibold shadow-sm flex items-center">
                <i class="fas fa-file-excel mr-1.5"></i>Export
            </button>
            <a href="../sites/bulk_upload.php" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition text-xs font-semibold shadow-sm flex items-center">
                <i class="fas fa-upload mr-1.5"></i>Bulk Import
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm flex items-center">
            <div class="w-9 h-9 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-sitemap text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Total Sites in Project</p>
                <p id="kpi-total" class="text-lg font-bold text-gray-800">0</p>
            </div>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm flex items-center">
            <div class="w-9 h-9 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-share-alt text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Delegated</p>
                <p id="kpi-delegated" class="text-lg font-bold text-purple-600">0</p>
            </div>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm flex items-center">
            <div class="w-9 h-9 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-clipboard-check text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Feasibility Approved</p>
                <p id="kpi-feasibility" class="text-lg font-bold text-amber-600">0</p>
            </div>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm flex items-center">
            <div class="w-9 h-9 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-truck text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Dispatched</p>
                <p id="kpi-material" class="text-lg font-bold text-indigo-600">0</p>
            </div>
        </div>
        <div class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm flex items-center">
            <div class="w-9 h-9 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-check-circle text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Installed</p>
                <p id="kpi-installed" class="text-lg font-bold text-emerald-600">0</p>
            </div>
        </div>
    </div>

    <!-- Multi-field Search & Filter Grid -->
    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm space-y-2">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
            <!-- 1. Search Query -->
            <div class="lg:col-span-2">
                <input type="text" id="search-input" placeholder="Search Site ID, Bank, Location..." 
                    class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
            </div>

            <!-- 2. Bank Filter -->
            <div>
                <select id="bank-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50 font-medium text-gray-700">
                    <option value="">Bank (All Banks)</option>
                </select>
            </div>

            <!-- 3. Status Filter -->
            <div>
                <select id="status-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Status (All)</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <!-- 4. Delegation Filter -->
            <div>
                <select id="delegation-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Delegation (All)</option>
                    <option value="delegated">Delegated</option>
                    <option value="not_delegated">Not Delegated</option>
                </select>
            </div>

            <!-- 5. Survey / Feasibility Filter -->
            <div>
                <select id="survey-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Survey / Feasibility (All)</option>
                    <option value="adv_approved">Approved</option>
                    <option value="submitted">Submitted</option>
                    <option value="pending">Pending</option>
                </select>
            </div>

            <!-- 6. Material Filter -->
            <div>
                <select id="material-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Material Status (All)</option>
                    <option value="generated">Generated</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="delivered">Delivered</option>
                </select>
            </div>

            <!-- 7. Installation Filter -->
            <div>
                <select id="installation-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Installation (All)</option>
                    <option value="done">Completed</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending">Pending</option>
                </select>
            </div>

            <!-- Clear Action -->
            <div>
                <button onclick="clearFilters()" class="w-full py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition text-xs font-semibold">
                    <i class="fas fa-times mr-1"></i>Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Main Master Table with Color-Coded Section Headers -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse min-w-[1400px]">
                <thead>
                    <!-- Section Header Row -->
                    <tr class="font-bold text-[11px] uppercase tracking-wider text-center border-b">
                        <th colspan="5" class="px-3 py-2 bg-slate-800 text-white border-r border-slate-700">1. SITE BASIC INFO</th>
                        <th colspan="3" class="px-3 py-2 bg-purple-100 text-purple-900 border-r border-purple-200">2A. FEASIBILITY DELEGATION</th>
                        <th colspan="3" class="px-3 py-2 bg-indigo-100 text-indigo-900 border-r border-indigo-200">2B. INSTALLATION DELEGATION</th>
                        <th colspan="5" class="px-3 py-2 bg-teal-100 text-teal-900 border-r border-teal-200">3. SURVEY / FEASIBILITY STATUS</th>
                        <th colspan="5" class="px-3 py-2 bg-amber-100 text-amber-900 border-r border-amber-200">4. MATERIAL PART</th>
                        <th colspan="5" class="px-3 py-2 bg-blue-100 text-blue-900 border-r border-blue-200">5. INSTALLATION STATUS</th>
                        <th colspan="4" class="px-3 py-2 bg-cyan-900 text-white border-r border-cyan-800">6. NETWORK & IP INFO</th>
                        <th colspan="1" class="px-3 py-2 bg-slate-800 text-white">ACTIONS</th>
                    </tr>
                    <!-- Column Header Row -->
                    <tr class="bg-gray-50 text-gray-600 font-bold text-[10px] uppercase tracking-wider border-b divide-x">
                        <!-- 1. Site Info (5 cols) -->
                        <th class="px-2 py-2 text-center w-8">#</th>
                        <th class="px-3 py-2">Site / Xtranet ID</th>
                        <th class="px-3 py-2">Project</th>
                        <th class="px-3 py-2">Bank Name</th>
                        <th class="px-3 py-2">Location</th>
                        <!-- 2A. Feasibility Delegation -->
                        <th class="px-3 py-2">Feasibility Vendor</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Date</th>
                        <!-- 2B. Installation Delegation -->
                        <th class="px-3 py-2">Inst Vendor</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Date</th>
                        <!-- 3. Feasibility Status (5 cols) -->
                        <th class="px-3 py-2">Survey Vendor</th>
                        <th class="px-3 py-2">Surveyor</th>
                        <th class="px-3 py-2">Date/Time</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-center">View</th>
                        <!-- 4. Material Part (5 cols) -->
                        <th class="px-3 py-2">Req #</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Manifest</th>
                        <th class="px-3 py-2">Dispatch</th>
                        <th class="px-3 py-2">Delivery</th>
                        <!-- 5. Installation Status (5 cols) -->
                        <th class="px-3 py-2">Installer</th>
                        <th class="px-3 py-2">Completed</th>
                        <th class="px-3 py-2">Latest Milestone</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-center">View</th>
                        <!-- 6. IP Details (4 dedicated cols) -->
                        <th class="px-3 py-2">Router Serial</th>
                        <th class="px-3 py-2">Router IP</th>
                        <th class="px-3 py-2">Network IP</th>
                        <th class="px-3 py-2">Site/ATM IP</th>
                        <!-- Actions (1 col) -->
                        <th class="px-3 py-2 text-center w-24">Actions</th>
                    </tr>
                </thead>
                <tbody id="sites-tbody" class="divide-y divide-gray-100 bg-white">
                    <tr>
                        <td colspan="30" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin text-2xl text-primary mb-2"></i>
                            <p>Loading project site records...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gray-50/50">
            <div id="pagination-info" class="text-xs text-gray-500"></div>
            <div id="pagination-controls" class="flex items-center gap-1"></div>
        </div>
    </div>
</div>

<script>
let projectsList = [];
let activeProjectId = null;

const state = {
    sites: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', project_id: '', bank_name: '', status: '', delegation: '', material: '', installation: '' }
};

document.addEventListener('DOMContentLoaded', function() {
    loadLookups();
    setupEventListeners();
});

async function loadLookups() {
    try {
        const res = await fetch('../api/sites/form_options.php');
        const json = await res.json();
        if (json.success && json.data) {
            projectsList = json.data.projects || [];
            
            // Resolve active project: from URL or sessionStorage or default to first project
            const urlProj = new URLSearchParams(window.location.search).get('project_id');
            const savedProj = sessionStorage.getItem('selected_project_id');
            
            if (urlProj && projectsList.some(p => String(p.id) === String(urlProj))) {
                activeProjectId = urlProj;
            } else if (savedProj && projectsList.some(p => String(p.id) === String(savedProj))) {
                activeProjectId = savedProj;
            } else if (projectsList.length > 0) {
                activeProjectId = String(projectsList[0].id);
            }

            // Populate Top Project Switcher
            const topSelect = document.getElementById('top-project-select');
            topSelect.innerHTML = '';
            projectsList.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.name} (${p.site_count || 0} Sites)`;
                if (String(p.id) === String(activeProjectId)) opt.selected = true;
                topSelect.appendChild(opt);
            });

            updateTopProjectBanner();

            // Populate Bank Filter
            const bankSelect = document.getElementById('bank-filter');
            if (json.data.banks) {
                json.data.banks.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.name;
                    opt.textContent = b.name;
                    bankSelect.appendChild(opt);
                });
            }

            // Lock project in state filter
            state.filters.project_id = activeProjectId;
            loadSites();
        }
    } catch (e) {
        console.error('Failed to load filter lookups', e);
        loadSites();
    }
}

function onTopProjectChange(val) {
    activeProjectId = val;
    sessionStorage.setItem('selected_project_id', val);
    
    // Update URL param without full reload
    const url = new URL(window.location);
    url.searchParams.set('project_id', val);
    window.history.replaceState({}, '', url);

    updateTopProjectBanner();
    
    state.filters.project_id = val;
    state.pagination.page = 1;
    loadSites();
}

function updateTopProjectBanner() {
    const projObj = projectsList.find(p => String(p.id) === String(activeProjectId));
    const labelEl = document.getElementById('current-project-label');
    const badgeEl = document.getElementById('current-project-badge');
    const addSiteBtn = document.getElementById('btn-add-site-project');

    if (projObj) {
        if (labelEl) labelEl.textContent = `${projObj.name} Project`;
        if (badgeEl) badgeEl.textContent = `${projObj.site_count || 0} Sites`;
        if (addSiteBtn) addSiteBtn.href = `../sites/site_add_custome_form.php?project_id=${projObj.id}`;
    }
}

function setupEventListeners() {
    let timeout;
    document.getElementById('search-input').addEventListener('input', (e) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadSites();
        }, 300);
    });

    ['bank-filter', 'status-filter', 'delegation-filter', 'material-filter', 'installation-filter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', (e) => {
                const key = id.replace('-filter', '').replace('-', '_');
                state.filters[key] = e.target.value;
                state.pagination.page = 1;
                loadSites();
            });
        }
    });
}

function clearFilters() {
    state.filters = { search: '', project_id: activeProjectId, bank_name: '', status: '', delegation: '', material: '', installation: '' };
    document.getElementById('search-input').value = '';
    ['bank-filter', 'status-filter', 'delegation-filter', 'survey-filter', 'material-filter', 'installation-filter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    state.pagination.page = 1;
    loadSites();
}

async function loadSites() {
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit,
            search: state.filters.search,
            project_id: state.filters.project_id || activeProjectId || '',
            bank_name: state.filters.bank_name,
            status: state.filters.status,
            delegation: state.filters.delegation,
            material: state.filters.material,
            installation: state.filters.installation
        });

        const res = await fetch(`../api/sites/index.php?${params.toString()}`, { credentials: 'include' });
        const data = await res.json();

        if (data.success) {
            state.sites = data.data.sites || [];
            const pag = data.data.pagination || {};
            state.pagination.total = pag.total ?? (data.data.total || 0);
            state.pagination.total_pages = pag.total_pages ?? Math.ceil(state.pagination.total / state.pagination.limit);
            
            updateKPIs();
            renderTable();
            renderPagination();
        }
    } catch (e) {
        console.error('Error loading sites:', e);
    }
}

function updateKPIs() {
    const elTotal = document.getElementById('kpi-total');
    if (elTotal) elTotal.textContent = state.pagination.total;

    const elDelegated = document.getElementById('kpi-delegated');
    if (elDelegated) elDelegated.textContent = state.sites.filter(s => s.delegation_status === 'accepted').length;

    const elFeasibility = document.getElementById('kpi-feasibility');
    if (elFeasibility) elFeasibility.textContent = state.sites.filter(s => s.feasibility_approval_status === 'adv_approved').length;

    const elMaterial = document.getElementById('kpi-material');
    if (elMaterial) elMaterial.textContent = state.sites.filter(s => s.dispatch_status).length;

    const elInstalled = document.getElementById('kpi-installed');
    if (elInstalled) elInstalled.textContent = state.sites.filter(s => s.installation_status === 'adv_approved' || s.installation_status === 'completed').length;
}

function renderTable() {
    const tbody = document.getElementById('sites-tbody');
    if (!state.sites.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="30" class="px-4 py-10 text-center text-gray-400 text-xs">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p>No site records found for this project.</p>
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = state.sites.map((site, idx) => {
        const srNo = (state.pagination.page - 1) * state.pagination.limit + idx + 1;
        
        // 2A. Feasibility Delegation Badge/Action
        const feasDelBadge = site.contractor_name 
            ? (site.delegation_status === 'accepted' 
                ? '<span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-[10px] font-semibold">Accepted</span>'
                : '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px]">Pending</span>')
            : `<a href="../sites/delegate.php?id=${site.id}" class="text-[10px] text-purple-600 font-semibold hover:underline flex items-center gap-1"><i class="fas fa-share-alt"></i>Delegate</a>`;

        // 2B. Installation Delegation Badge/Action
        const instDelBadge = site.inst_contractor_name
            ? getInstallationBadge(site.installation_status)
            : `<a href="../installation/delegate.php?site_id=${site.id}&feasibility_id=${site.feasibility_check_id || ''}" class="text-[10px] text-indigo-600 font-semibold hover:underline flex items-center gap-1"><i class="fas fa-play-circle"></i>Delegate Inst</a>`;

        // Feasibility badge
        const feasBadge = getFeasibilityBadge(site.feasibility_approval_status || site.feasibility_status);
        // Material badge
        const matBadge = getMaterialBadge(site.material_req_status || site.dispatch_status);
        // Installation badge
        const instBadge = getInstallationBadge(site.installation_status);

        return `
        <tr class="hover:bg-gray-50/80 transition-colors border-b text-[11px] divide-x">
            <!-- 1. Site Info (5 cols) -->
            <td class="px-2 py-2.5 text-center font-bold text-gray-500 whitespace-nowrap">${srNo}</td>
            <td class="px-3 py-2.5 font-bold text-gray-900 whitespace-nowrap">
                <a href="../sites/site_view_custom_form.php?id=${site.id}" class="hover:text-primary hover:underline flex items-center gap-1">
                    <i class="fas fa-map-marker-alt text-primary/70 text-[10px]"></i>
                    ${escapeHtml(site.site_name)}
                </a>
            </td>
            <td class="px-3 py-2.5 whitespace-nowrap">
                <span class="px-2 py-0.5 rounded-full font-bold text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200">
                    ${escapeHtml(site.project_name || 'XTPL')}
                </span>
            </td>
            <td class="px-3 py-2.5 font-semibold text-gray-800 whitespace-nowrap max-w-[220px] truncate" title="${escapeHtml(site.bank_name || '')}">
                ${escapeHtml(site.bank_name || '-')}
            </td>
            <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">${escapeHtml(site.city || '-')}, ${escapeHtml(site.state || '')}</td>

            <!-- 2A. Feasibility Delegation (3 cols) -->
            <td class="px-3 py-2.5 font-medium ${site.contractor_name ? 'text-purple-700' : 'text-gray-400'} whitespace-nowrap">
                ${escapeHtml(site.contractor_name || 'Not Delegated')}
            </td>
            <td class="px-3 py-2.5 whitespace-nowrap">${feasDelBadge}</td>
            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${formatDate(site.delegated_at)}</td>

            <!-- 2B. Installation Delegation (3 cols) -->
            <td class="px-3 py-2.5 font-medium ${site.inst_contractor_name ? 'text-indigo-700' : 'text-gray-400'} whitespace-nowrap">
                ${escapeHtml(site.inst_contractor_name || 'Not Delegated')}
            </td>
            <td class="px-3 py-2.5 whitespace-nowrap">${instDelBadge}</td>
            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${formatDate(site.inst_delegated_at)}</td>

            <!-- 3. Survey / Feasibility Status (5 cols) -->
            <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap font-medium">${escapeHtml(site.contractor_name || 'ADV')}</td>
            <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap">${escapeHtml(site.surveyor_name || '-')}</td>
            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${formatDate(site.feasibility_created_at)}</td>
            <td class="px-3 py-2.5 whitespace-nowrap">${feasBadge}</td>
            <td class="px-3 py-2.5 text-center">
                ${site.feasibility_check_id ? `
                    <a href="../feasibility/view.php?id=${site.feasibility_check_id}" class="text-blue-600 hover:text-blue-800" title="View Feasibility">
                        <i class="fas fa-eye"></i>
                    </a>` : '<span class="text-gray-300">-</span>'
                }
            </td>

            <!-- 4. Material Part (5 cols) -->
            <td class="px-3 py-2.5 font-mono text-gray-700 whitespace-nowrap">${escapeHtml(site.material_req_number || '-')}</td>
            <td class="px-3 py-2.5 whitespace-nowrap">${matBadge}</td>
            <td class="px-3 py-2.5 font-mono text-gray-600 whitespace-nowrap">${escapeHtml(site.manifest_number || '-')}</td>
            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${formatDate(site.dispatch_date)}</td>
            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${formatDate(site.delivery_date)}</td>

            <!-- 5. Installation Status (5 cols) -->
            <td class="px-3 py-2.5 text-gray-700 whitespace-nowrap">${escapeHtml(site.installer_name || '-')}</td>
            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${formatDate(site.installation_completed_at)}</td>
            <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">${formatMilestone(site.installation_status)}</td>
            <td class="px-3 py-2.5 whitespace-nowrap">${instBadge}</td>
            <td class="px-3 py-2.5 text-center">
                ${site.installation_id ? `
                    <a href="../installation/view.php?id=${site.installation_id}" class="text-emerald-600 hover:text-emerald-800" title="View Installation">
                        <i class="fas fa-eye"></i>
                    </a>` : (site.feasibility_approval_status === 'adv_approved' ? `
                    <a href="../installation/delegate.php?site_id=${site.id}&feasibility_id=${site.feasibility_check_id}" class="text-purple-600 hover:text-purple-800" title="Initiate Installation">
                        <i class="fas fa-play-circle"></i>
                    </a>` : '<span class="text-gray-300">-</span>')
                }
            </td>

            <!-- 6. IP Details (4 dedicated cols) -->
            <td class="px-3 py-2.5 font-mono text-gray-700 whitespace-nowrap">${escapeHtml(site.router_serial_number || '-')}</td>
            <td class="px-3 py-2.5 font-mono font-semibold text-blue-600 whitespace-nowrap">${escapeHtml(site.router_ip || '-')}</td>
            <td class="px-3 py-2.5 font-mono font-semibold text-purple-600 whitespace-nowrap">${escapeHtml(site.network_ip || '-')}</td>
            <td class="px-3 py-2.5 font-mono font-semibold text-emerald-600 whitespace-nowrap">${escapeHtml(site.site_ip || site.ip_address || '-')}</td>

            <!-- Actions (1 col) -->
            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                <div class="flex items-center justify-center gap-1.5">
                    <a href="../sites/site_view_custom_form.php?id=${site.id}" class="p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded" title="View Site Details">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="../sites/site_edit_custom_form.php?id=${site.id}" class="p-1 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded" title="Edit Site">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="../sites/delegate.php?id=${site.id}" class="p-1 text-purple-600 hover:text-purple-800 hover:bg-purple-50 rounded" title="Delegate Site">
                        <i class="fas fa-share-alt"></i>
                    </a>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function getFeasibilityBadge(status) {
    if (!status) return '<span class="px-2 py-0.5 bg-gray-100 text-gray-400 rounded text-[10px]">Not Started</span>';
    if (status === 'adv_approved') return '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-[10px] font-semibold">ADV Approved</span>';
    if (status === 'submitted') return '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">Submitted</span>';
    if (status === 'rejected') return '<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-[10px]">Rejected</span>';
    return `<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px]">${status}</span>`;
}

function getMaterialBadge(status) {
    if (!status) return '<span class="px-2 py-0.5 bg-gray-100 text-gray-400 rounded text-[10px]">None</span>';
    if (status === 'delivered') return '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-[10px]">Delivered</span>';
    if (status === 'dispatched') return '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">Dispatched</span>';
    return '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px]">Generated</span>';
}

function getInstallationBadge(status) {
    if (!status || status === 'pending') return '<span class="px-2 py-0.5 bg-gray-100 text-gray-400 rounded text-[10px]">Pending</span>';
    if (status === 'completed' || status === 'adv_approved') return '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-[10px] font-semibold">Completed</span>';
    if (status === 'in_progress') return '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">In Progress</span>';
    return `<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px]">${status}</span>`;
}

function formatMilestone(status) {
    if (!status) return '-';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatDate(d) {
    if (!d) return '-';
    try {
        const dt = new Date(d);
        if (isNaN(dt.getTime())) return '-';
        return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch(e) {
        return '-';
    }
}

function renderPagination() {
    const info = document.getElementById('pagination-info');
    const controls = document.getElementById('pagination-controls');
    const total = state.pagination.total;
    const page = state.pagination.page;
    const limit = state.pagination.limit;

    info.textContent = total > 0 ? `Showing ${(page - 1) * limit + 1} to ${Math.min(page * limit, total)} of ${total} entries` : 'No entries';
    
    if (state.pagination.total_pages <= 1) {
        controls.innerHTML = '';
        return;
    }

    let html = `<button onclick="goToPage(${page - 1})" class="px-2.5 py-1 text-xs rounded border ${page === 1 ? 'bg-gray-100 text-gray-400' : 'hover:bg-gray-100'}" ${page === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= state.pagination.total_pages; i++) {
        html += `<button onclick="goToPage(${i})" class="px-2.5 py-1 text-xs rounded border ${i === page ? 'bg-primary text-white font-semibold' : 'hover:bg-gray-100'}">${i}</button>`;
    }
    html += `<button onclick="goToPage(${page + 1})" class="px-2.5 py-1 text-xs rounded border ${page === state.pagination.total_pages ? 'bg-gray-100 text-gray-400' : 'hover:bg-gray-100'}" ${page === state.pagination.total_pages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > state.pagination.total_pages) return;
    state.pagination.page = page;
    loadSites();
}

function refreshData() {
    loadSites();
}

function exportData() {
    const projId = activeProjectId || '';
    window.location.href = `../api/sites/index.php?export=1&project_id=${projId}`;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
