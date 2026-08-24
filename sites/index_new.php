<?php
/**
 * Comprehensive Sites Overview Page (New Design)
 * 
 * Displays full site lifecycle tracking:
 * - 1. Basic Info (Site Name, LHO, City/State, Status)
 * - 2. Delegation (Contractor, Status, Date/Time)
 * - 3. Survey / Feasibility (Surveyor, Date/Time, Status, View)
 * - 4. Material Part (Req #, Status, Manifest, Dispatch, Delivery)
 * - 5. Installation (Installer, Completed, Latest Milestone, Status, View)
 * - 6. Network & Actions (IP Details, Actions)
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
    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight">Advanced Sites Management</h2>
            <p class="text-xs text-gray-500 mt-1">Comprehensive Lifecycle Tracking across Delegation, Survey/Feasibility, Materials, and Installation</p>
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
            <?php if (isAdvUser()): ?>
            <a href="../sites/add.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition text-xs font-semibold shadow-sm flex items-center">
                <i class="fas fa-plus mr-1.5"></i>+ Add Site
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white p-3.5 rounded-xl border border-gray-100 shadow-sm flex items-center">
            <div class="w-9 h-9 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-sitemap text-sm"></i>
            </div>
            <div>
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Total Sites</p>
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
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
            <div>
                <input type="text" id="search-input" placeholder="Search ID, Location, Customer..." 
                    class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
            </div>
            <div>
                <input type="text" id="search-site" placeholder="Search Site..." 
                    class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
            </div>
            <div>
                <input type="text" id="search-city" placeholder="Search City..." 
                    class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-gray-50/50">
            </div>
            <div>
                <select id="lho-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">LHO / State (All)</option>
                </select>
            </div>
            <div>
                <select id="status-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Status (All)</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <select id="delegation-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Delegation (All)</option>
                    <option value="delegated">Delegated</option>
                    <option value="not_delegated">Not Delegated</option>
                </select>
            </div>
            <div>
                <select id="survey-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Survey / Feasibility (All)</option>
                    <option value="adv_approved">Approved</option>
                    <option value="submitted">Submitted</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div>
                <select id="material-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Material Status (All)</option>
                    <option value="generated">Generated</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="delivered">Delivered</option>
                </select>
            </div>
            <div>
                <select id="installation-filter" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50/50">
                    <option value="">Installation (All)</option>
                    <option value="done">Completed</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="flex items-center gap-1">
                <button onclick="clearFilters()" class="w-full py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition text-xs font-semibold">
                    <i class="fas fa-times mr-1"></i>Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Main Master Table with Live Site Color-Coded Section Headers -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse min-w-[1300px]">
                <thead>
                    <!-- Section Header Row -->
                    <tr class="font-bold text-[11px] uppercase tracking-wider text-center border-b">
                        <th colspan="4" class="px-3 py-2 bg-slate-800 text-white border-r border-slate-700">1. SITE BASIC INFO</th>
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
                        <!-- 1. Site Info -->
                        <th class="px-2 py-2 text-center w-8">#</th>
                        <th class="px-3 py-2">Site Name</th>
                        <th class="px-3 py-2">LHO</th>
                        <th class="px-3 py-2">Location</th>
                        <!-- 2A. Feasibility Delegation -->
                        <th class="px-3 py-2">Feasibility Vendor</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Date</th>
                        <!-- 2B. Installation Delegation -->
                        <th class="px-3 py-2">Inst Vendor</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Date</th>
                        <!-- 3. Feasibility Status -->
                        <th class="px-3 py-2">Inst Vendor</th>
                        <th class="px-3 py-2">Surveyor</th>
                        <th class="px-3 py-2">Date/Time</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-center">View</th>
                        <!-- 4. Material Part -->
                        <th class="px-3 py-2">Req #</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Manifest</th>
                        <th class="px-3 py-2">Dispatch</th>
                        <th class="px-3 py-2">Delivery</th>
                        <!-- 5. Installation Status -->
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
                        <!-- Actions -->
                        <th class="px-3 py-2 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="sites-tbody" class="divide-y divide-gray-100 bg-white">
                    <tr>
                        <td colspan="30" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin text-2xl text-primary mb-2"></i>
                            <p>Loading master site records...</p>
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

<!-- Modal: View IP & Router Details -->
<div id="ip-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeIpModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10 p-6 space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-semibold text-gray-800 text-base flex items-center">
                    <i class="fas fa-network-wired text-primary mr-2"></i>Network & Router Info
                </h3>
                <button onclick="closeIpModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div id="ip-modal-body" class="space-y-2 text-xs"></div>
            <div class="flex justify-end pt-3 border-t">
                <button onclick="closeIpModal()" class="px-4 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const state = {
    sites: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', lho: '', delegation: '', material: '', installation: '' }
};

document.addEventListener('DOMContentLoaded', function() {
    loadSites();
    setupEventListeners();
});

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

    ['lho-filter', 'delegation-filter', 'material-filter', 'installation-filter'].forEach(id => {
        document.getElementById(id).addEventListener('change', (e) => {
            const key = id.replace('-filter', '');
            state.filters[key] = e.target.value;
            state.pagination.page = 1;
            loadSites();
        });
    });
}

async function loadSites() {
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit,
            search: state.filters.search,
            lho: state.filters.lho,
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
            populateLHOs(data.data.lhos || []);
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
                    <p>No site records found</p>
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
            <!-- 1. Site Info (4 cols) -->
            <td class="px-2 py-2.5 text-center font-bold text-gray-500 whitespace-nowrap">${srNo}</td>
            <td class="px-3 py-2.5 font-semibold text-gray-800 whitespace-nowrap">${escapeHtml(site.site_name)}</td>
            <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">${escapeHtml(site.lho || '-')}</td>
            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">${escapeHtml(site.city || '-')}, ${escapeHtml(site.state || '')}</td>

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
            <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">${escapeHtml(site.contractor_name || 'ADV')}</td>
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
                <a href="../sites/delegate.php?id=${site.id}" class="text-purple-600 hover:text-purple-800" title="Delegate Site">
                    <i class="fas fa-share-alt"></i>
                </a>
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
    if (status === 'delivered') return '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-semibold">Delivered</span>';
    if (status === 'in_transit' || status === 'dispatched') return '<span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded text-[10px]">In Transit</span>';
    if (status === 'approved') return '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">Approved</span>';
    return `<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px]">${status}</span>`;
}

function getInstallationBadge(status) {
    if (!status) return '<span class="px-2 py-0.5 bg-gray-100 text-gray-400 rounded text-[10px]">Not Started</span>';
    if (status === 'adv_approved' || status === 'completed') return '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-[10px] font-semibold">Completed</span>';
    if (status === 'submitted') return '<span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-[10px]">Submitted</span>';
    if (status === 'in_progress') return '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px]">In Progress</span>';
    return `<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px]">${status}</span>`;
}

function formatMilestone(status) {
    if (!status) return 'Not Initiated';
    if (status === 'pending_assignment') return 'Pending Assignment';
    if (status === 'pending_materials') return 'Awaiting Materials';
    if (status === 'materials_received') return 'Materials Received';
    if (status === 'in_progress') return 'Form In Progress';
    if (status === 'submitted') return 'Form Submitted';
    if (status === 'adv_approved') return 'Approved & Finalized';
    return status;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
}

function showIpInfo(siteId) {
    const site = state.sites.find(s => s.id === siteId);
    if (!site) return;

    document.getElementById('ip-modal-body').innerHTML = `
        <div class="p-3 bg-gray-50 rounded-lg space-y-2">
            <div class="flex justify-between border-b pb-1">
                <span class="text-gray-500">Router Serial:</span>
                <span class="font-mono font-semibold text-gray-800">${site.router_serial_number || 'N/A'}</span>
            </div>
            <div class="flex justify-between border-b pb-1">
                <span class="text-gray-500">Router IP:</span>
                <span class="font-mono font-semibold text-blue-600">${site.router_ip || 'N/A'}</span>
            </div>
            <div class="flex justify-between border-b pb-1">
                <span class="text-gray-500">Network IP:</span>
                <span class="font-mono font-semibold text-purple-600">${site.network_ip || 'N/A'}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">ATM / Site IP:</span>
                <span class="font-mono font-semibold text-emerald-600">${site.site_ip || 'N/A'}</span>
            </div>
        </div>`;
    document.getElementById('ip-modal').classList.remove('hidden');
}

function closeIpModal() {
    document.getElementById('ip-modal').classList.add('hidden');
}

function populateLHOs(lhos) {
    const select = document.getElementById('lho-filter');
    if (select.children.length > 1) return;
    lhos.forEach(l => {
        const opt = document.createElement('option');
        opt.value = l;
        opt.textContent = l;
        select.appendChild(opt);
    });
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
    window.location.href = '../api/sites/index.php?export=1';
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
