<?php
/**
 * User Management Page
 * Features: Pagination, Search, Export, Filters
 */
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('users.read')) {
    $_SESSION['flash_error'] = 'You do not have permission to view users';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'User Management';
$currentPage = 'users';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Users']
];

$isAdv = isAdvUser();
$canCreate = can('users.create');
$canUpdate = can('users.update');
$canDelete = can('users.delete');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">User Management</h3>
            <p class="text-sm text-gray-500">Manage system users and their access</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="exportUsers()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
            <a href="locked.php" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition flex items-center">
                <i class="fas fa-lock mr-2"></i>Locked Users
            </a>
            <?php if ($canCreate): ?>
            <a href="create.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add User
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search by username or email..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <?php if ($isAdv): ?>
            <div>
                <select id="company-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Companies</option>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="users-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Company</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="users-tbody" class="divide-y divide-gray-100">
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
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
    users: [],
    companies: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', company_id: '', status: '' }
};

const permissions = {
    canUpdate: <?php echo json_encode($canUpdate); ?>,
    canDelete: <?php echo json_encode($canDelete); ?>,
    isAdv: <?php echo json_encode($isAdv); ?>
};

const currentUserId = <?php echo json_encode($currentUser['id']); ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (permissions.isAdv) loadCompanies();
    loadUsers();
    setupEventListeners();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', e => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { 
            state.filters.search = e.target.value; 
            state.pagination.page = 1; 
            loadUsers(); 
        }, 300);
    });
    
    const companyFilter = document.getElementById('company-filter');
    if (companyFilter) {
        companyFilter.addEventListener('change', e => { 
            state.filters.company_id = e.target.value; 
            state.pagination.page = 1; 
            loadUsers(); 
        });
    }
    
    document.getElementById('status-filter').addEventListener('change', e => { 
        state.filters.status = e.target.value; 
        state.pagination.page = 1; 
        loadUsers(); 
    });
}

async function loadCompanies() {
    try {
        const response = await fetch('../api/users/companies.php', { credentials: 'include' });
        const data = await response.json();
        if (data.success) {
            state.companies = data.data.companies || [];
            const select = document.getElementById('company-filter');
            if (select) {
                select.innerHTML = '<option value="">All Companies</option>' + 
                    state.companies.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            }
        }
    } catch (error) { console.error('Error loading companies:', error); }
}

async function loadUsers() {
    try {
        const params = new URLSearchParams({ 
            page: state.pagination.page, 
            limit: state.pagination.limit 
        });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.company_id) params.append('company_id', state.filters.company_id);
        
        const response = await fetch(`../api/users/index.php?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.users = data.data.users || [];
            state.pagination = data.data.pagination || state.pagination;
            
            // Client-side status filter
            if (state.filters.status !== '') {
                state.users = state.users.filter(u => String(u.status) === state.filters.status);
            }
            
            renderTable();
            renderPagination();
        }
    } catch (error) { 
        console.error('Error loading users:', error); 
        document.getElementById('users-tbody').innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-red-500">Error loading users</td></tr>';
    }
}

function renderTable() {
    const tbody = document.getElementById('users-tbody');
    
    if (state.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-users text-4xl mb-3 text-gray-300"></i><p>No users found</p></td></tr>';
        return;
    }
    
    const startNum = (state.pagination.page - 1) * state.pagination.limit;
    
    tbody.innerHTML = state.users.map((u, idx) => {
        const fullName = [u.first_name, u.last_name].filter(Boolean).join(' ') || u.username;
        const initial = (u.first_name || u.username || 'U').charAt(0).toUpperCase();
        const isCurrentUser = u.id == currentUserId;
        
        return `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${startNum + idx + 1}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-xs font-medium mr-2.5 flex-shrink-0">${initial}</div>
                    <div>
                        <div class="font-medium text-gray-800 text-xs">${escapeHtml(fullName)}</div>
                        <div class="text-[10px] text-gray-500">@${escapeHtml(u.username)}</div>
                    </div>
                </div>
            </td>
            <td class="px-4 py-2.5">
                <div>
                    <div class="text-gray-600 text-xs">${escapeHtml(u.email || '-')}</div>
                    ${u.phone ? `<div class="text-[10px] text-gray-500"><i class="fas fa-phone text-gray-400 mr-1"></i>${escapeHtml(u.phone)}</div>` : ''}
                </div>
            </td>
            <td class="px-4 py-2.5">
                <span class="inline-flex items-center text-xs text-gray-600">
                    ${escapeHtml(u.company_name || '-')}
                    ${u.company_type === 'ADV' ? '<span class="ml-1.5 px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium">ADV</span>' : ''}
                </span>
            </td>
            <td class="px-4 py-2.5 text-gray-600 text-xs">${escapeHtml(u.role_name || '-')}</td>
            <td class="px-4 py-2.5">
                ${u.status == 1 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>' 
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>Inactive</span>'}
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    <a href="view.php?id=${u.id}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="View"><i class="fas fa-eye text-xs"></i></a>
                    ${permissions.canUpdate ? `<a href="edit.php?id=${u.id}" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" title="Edit"><i class="fas fa-edit text-xs"></i></a>` : ''}
                    ${permissions.canDelete && !isCurrentUser ? `<button onclick="confirmDelete(${u.id}, '${escapeHtml(u.username)}')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete"><i class="fas fa-trash text-xs"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderPagination() {
    const { page, limit, total, total_pages } = state.pagination;
    document.getElementById('pagination-info').textContent = total > 0 
        ? `Showing ${(page-1)*limit+1} to ${Math.min(page*limit, total)} of ${total} users` 
        : 'No users';
    
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
        loadUsers(); 
    } 
}

function exportUsers() {
    const params = new URLSearchParams();
    if (state.filters.search) params.append('search', state.filters.search);
    if (state.filters.company_id) params.append('company_id', state.filters.company_id);
    if (state.filters.status) params.append('status', state.filters.status);
    params.append('export', '1');
    window.open(`../api/users/export.php?${params}`, '_blank');
}

function confirmDelete(id, username) {
    openConfirmModal(
        'Delete User',
        `Are you sure you want to delete user "${username}"? This action cannot be undone.`,
        function() { window.location.href = `delete.php?id=${id}&confirm=1`; }
    );
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
