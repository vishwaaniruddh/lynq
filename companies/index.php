<?php
/**
 * Company Management Page
 * Features: Pagination, Search, Export, Filters
 */
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('companies.read')) {
    $_SESSION['flash_error'] = 'You do not have permission to view companies';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Company Management';
$currentPage = 'companies';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Companies']
];

$isAdv = isAdvUser();
$canCreate = can('companies.create') && $isAdv;
$canUpdate = can('companies.update') && $isAdv;
$canDelegate = can('permissions.delegate') && $isAdv;

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Company Management</h3>
            <p class="text-sm text-gray-500">Manage companies and their settings</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="exportCompanies()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
            <?php if ($canCreate): ?>
            <a href="create.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Company
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search companies..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <?php if ($isAdv): ?>
            <div>
                <select id="type-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Types</option>
                    <option value="ADV">ADV</option>
                    <option value="CONTRACTOR">Contractor</option>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="companies-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Company</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Users</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="companies-tbody" class="divide-y divide-gray-100">
                <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<script>
const state = {
    companies: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', type: '', status: '' }
};

const permissions = {
    canUpdate: <?php echo json_encode($canUpdate); ?>,
    canDelegate: <?php echo json_encode($canDelegate); ?>,
    isAdv: <?php echo json_encode($isAdv); ?>
};

document.addEventListener('DOMContentLoaded', function() {
    loadCompanies();
    setupEventListeners();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', e => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { 
            state.filters.search = e.target.value; 
            state.pagination.page = 1; 
            loadCompanies(); 
        }, 300);
    });
    
    const typeFilter = document.getElementById('type-filter');
    if (typeFilter) {
        typeFilter.addEventListener('change', e => { 
            state.filters.type = e.target.value; 
            state.pagination.page = 1; 
            loadCompanies(); 
        });
    }
    
    document.getElementById('status-filter').addEventListener('change', e => { 
        state.filters.status = e.target.value; 
        state.pagination.page = 1; 
        loadCompanies(); 
    });
}

async function loadCompanies() {
    try {
        const params = new URLSearchParams({ 
            page: state.pagination.page, 
            limit: state.pagination.limit 
        });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.type) params.append('type', state.filters.type);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`../api/companies/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.companies = data.data.companies || [];
            state.pagination = data.data.pagination || state.pagination;
            renderTable();
            renderPagination();
        }
    } catch (error) { 
        console.error('Error loading companies:', error); 
        document.getElementById('companies-tbody').innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-red-500">Error loading companies</td></tr>';
    }
}

function renderTable() {
    const tbody = document.getElementById('companies-tbody');
    
    if (state.companies.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500"><i class="fas fa-building text-4xl mb-3 text-gray-300"></i><p>No companies found</p></td></tr>';
        return;
    }
    
    const startNum = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.companies.map((c, idx) => {
        const isAdv = c.type === 'ADV';
        const isActive = c.status === 'ACTIVE' || c.status === 'active';
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">${startNum + idx + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 ${isAdv ? 'bg-gradient-to-br from-blue-50 to-blue-100' : 'bg-gradient-to-br from-gray-50 to-gray-100'} rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-building ${isAdv ? 'text-blue-500' : 'text-gray-500'} text-xs"></i>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(c.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5">
                ${isAdv 
                    ? '<span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium">ADV</span>'
                    : '<span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px] font-medium">CONTRACTOR</span>'}
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${c.user_count || 0} users</td>
            <td class="px-4 py-2.5">
                ${isActive 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'}
            </td>
            <td class="px-4 py-2.5 text-xs text-gray-600">${formatDate(c.created_at)}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <a href="view.php?id=${c.id}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View"><i class="fas fa-eye text-xs"></i></a>
                    ${permissions.canUpdate ? `<a href="edit.php?id=${c.id}" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit"><i class="fas fa-edit text-xs"></i></a>` : ''}
                    ${permissions.canDelegate && !isAdv ? `<a href="../permissions/delegate.php?company_id=${c.id}" class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded transition-colors" title="Manage Permissions"><i class="fas fa-key text-xs"></i></a>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    document.getElementById('pagination-info').textContent = total > 0 
        ? `Showing ${(page-1)*limit+1} to ${Math.min(page*limit, total)} of ${total} companies` 
        : 'No companies';
    
    if (total_pages <= 1) { 
        document.getElementById('pagination-controls').innerHTML = ''; 
        return; 
    }
    
    let html = `<button onclick="goToPage(${page-1})" ${page===1?'disabled':''} class="px-3 py-1 rounded border ${page===1?'bg-gray-100 text-gray-400':'hover:bg-gray-100'}"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = Math.max(1, page-2); i <= Math.min(total_pages, page+2); i++) {
        html += `<button onclick="goToPage(${i})" class="px-3 py-1 rounded border ${i===page?'bg-primary text-white':'hover:bg-gray-100'}">${i}</button>`;
    }
    html += `<button onclick="goToPage(${page+1})" ${page===total_pages?'disabled':''} class="px-3 py-1 rounded border ${page===total_pages?'bg-gray-100 text-gray-400':'hover:bg-gray-100'}"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination-controls').innerHTML = html;
}

function goToPage(page) { 
    if (page >= 1 && page <= state.pagination.total_pages) { 
        state.pagination.page = page; 
        loadCompanies(); 
    } 
}

function exportCompanies() {
    const params = new URLSearchParams();
    if (state.filters.search) params.append('search', state.filters.search);
    if (state.filters.type) params.append('type', state.filters.type);
    if (state.filters.status) params.append('status', state.filters.status);
    window.open(`../api/companies/export.php?${params}`, '_blank');
}

function formatDate(d) { 
    return d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-'; 
}

function escapeHtml(t) { 
    if (!t) return ''; 
    const d = document.createElement('div'); 
    d.textContent = t; 
    return d.innerHTML; 
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
