<?php
/**
 * LHO (Local Head Office) Management Page
 * 
 * Implements table with status and manager filters
 * Add create/edit modal with multi-select manager dropdown
 * Requirements: 1.1, 1.2, 2.1, 2.2, 2.3, 2.4, 5.1, 5.2, 5.3
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
$user = $masterMiddleware->requireViewPermission('lhos');

if (!$user) {
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'LHO Management';
$currentPage = 'masters_lhos';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'LHO']
];

$permissions = $masterMiddleware->getUserModulePermissions('lhos');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">LHO Management</h3>
            <p class="text-sm text-gray-500">Manage Local Head Office (LHO) records</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add LHO
            </button>
            <?php endif; ?>
            <button onclick="exportLhos()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search LHO or manager name..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <select id="status-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <select id="manager-filter" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary min-w-[180px]">
                    <option value="">All Managers</option>
                </select>
            </div>
        </div>
    </div>
    
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading LHOs...</p>
    </div>
    
    <div class="overflow-x-auto">
        <table id="lhos-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">ID <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="lho_name">LHO Name <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Managers</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">Status <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="lhos-tbody" class="divide-y divide-gray-100">
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    
    <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="lho-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeLhoModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add LHO</h3>
                <button onclick="closeLhoModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="lho-form" onsubmit="saveLho(event)">
                <input type="hidden" id="lho-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="lho-name" class="block text-sm font-medium text-gray-700 mb-1">LHO Name <span class="text-red-500">*</span></label>
                        <input type="text" id="lho-name" name="lho_name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter LHO name">
                        <p id="lho_name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="lho-managers" class="block text-sm font-medium text-gray-700 mb-1">Managers</label>
                        <div id="managers-container" class="relative">
                            <div id="managers-select" class="w-full min-h-[42px] px-3 py-2 border rounded-lg focus-within:ring-2 focus-within:ring-primary cursor-pointer bg-white flex flex-wrap gap-1 items-center" onclick="toggleManagerDropdown(event)">
                                <span id="managers-placeholder" class="text-gray-400">Select managers...</span>
                            </div>
                            <div id="managers-dropdown" class="hidden absolute z-20 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                <div class="p-2 border-b">
                                    <input type="text" id="manager-search" placeholder="Search managers..." 
                                        class="w-full px-3 py-1.5 text-sm border rounded focus:ring-1 focus:ring-primary focus:border-transparent"
                                        onclick="event.stopPropagation()" oninput="filterManagerOptions()">
                                </div>
                                <div id="manager-options" class="py-1">
                                    <div class="px-3 py-2 text-sm text-gray-500">Loading managers...</div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Select ADV users to assign as managers for this LHO</p>
                        <p id="managers-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="lho-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="lho-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeLhoModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition"><i class="fas fa-save mr-2"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const state = {
    lhos: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '', manager_id: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: <?php echo json_encode($permissions); ?>,
    advUsers: [],
    selectedManagers: []
};

const API_URL = '../api/masters/lhos.php';
const ADV_USERS_URL = '../api/users/adv-list.php';

document.addEventListener('DOMContentLoaded', function() {
    loadAdvUsers();
    loadLhos();
    setupEventListeners();
    setupClickOutside();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadLhos();
        }, 300);
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadLhos();
    });
    
    document.getElementById('manager-filter').addEventListener('change', function(e) {
        state.filters.manager_id = e.target.value;
        state.pagination.page = 1;
        loadLhos();
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
            loadLhos();
            updateSortIndicators();
        });
    });
}

function setupClickOutside() {
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('managers-dropdown');
        const container = document.getElementById('managers-container');
        if (dropdown && container && !container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

async function loadAdvUsers() {
    try {
        const response = await fetch(ADV_USERS_URL, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.advUsers = data.data.users;
            populateManagerFilter();
            renderManagerOptions();
        }
    } catch (error) {
        console.error('Error loading ADV users:', error);
    }
}

function populateManagerFilter() {
    const select = document.getElementById('manager-filter');
    select.innerHTML = '<option value="">All Managers</option>';
    
    state.advUsers.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = `${user.first_name} ${user.last_name}`;
        select.appendChild(option);
    });
}

function renderManagerOptions() {
    const container = document.getElementById('manager-options');
    if (state.advUsers.length === 0) {
        container.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">No ADV users available</div>';
        return;
    }
    
    container.innerHTML = state.advUsers.map(user => `
        <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer manager-option" data-name="${escapeHtml(user.first_name + ' ' + user.last_name).toLowerCase()}">
            <input type="checkbox" value="${user.id}" class="manager-checkbox mr-2 rounded border-gray-300 text-primary focus:ring-primary"
                onchange="toggleManager(${user.id}, '${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}')"
                ${state.selectedManagers.some(m => m.id === user.id) ? 'checked' : ''}>
            <span class="text-sm text-gray-700">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</span>
            <span class="text-xs text-gray-400 ml-1">(${escapeHtml(user.email)})</span>
        </label>
    `).join('');
}

function filterManagerOptions() {
    const searchTerm = document.getElementById('manager-search').value.toLowerCase();
    document.querySelectorAll('.manager-option').forEach(option => {
        const name = option.dataset.name;
        option.style.display = name.includes(searchTerm) ? '' : 'none';
    });
}

function toggleManagerDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('managers-dropdown');
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) {
        document.getElementById('manager-search').focus();
    }
}

function toggleManager(userId, userName) {
    const index = state.selectedManagers.findIndex(m => m.id === userId);
    if (index > -1) {
        state.selectedManagers.splice(index, 1);
    } else {
        state.selectedManagers.push({ id: userId, name: userName });
    }
    updateSelectedManagersDisplay();
}

function removeManager(userId) {
    state.selectedManagers = state.selectedManagers.filter(m => m.id !== userId);
    updateSelectedManagersDisplay();
    const checkbox = document.querySelector(`.manager-checkbox[value="${userId}"]`);
    if (checkbox) checkbox.checked = false;
}

function updateSelectedManagersDisplay() {
    const container = document.getElementById('managers-select');
    const placeholder = document.getElementById('managers-placeholder');
    container.querySelectorAll('.manager-tag').forEach(tag => tag.remove());
    
    if (state.selectedManagers.length === 0) {
        placeholder.style.display = '';
    } else {
        placeholder.style.display = 'none';
        state.selectedManagers.forEach(manager => {
            const tag = document.createElement('span');
            tag.className = 'manager-tag inline-flex items-center px-2 py-0.5 bg-primary/10 text-primary rounded text-xs';
            tag.innerHTML = `${escapeHtml(manager.name)}<button type="button" onclick="event.stopPropagation(); removeManager(${manager.id})" class="ml-1 hover:text-red-500"><i class="fas fa-times text-[10px]"></i></button>`;
            container.insertBefore(tag, placeholder);
        });
    }
}

function updateSortIndicators() {
    document.querySelectorAll('[data-sort]').forEach(th => {
        const icon = th.querySelector('i');
        icon.className = th.dataset.sort === state.sort.field 
            ? (state.sort.direction === 'asc' ? 'fas fa-sort-up ml-1' : 'fas fa-sort-down ml-1')
            : 'fas fa-sort ml-1';
    });
}

async function loadLhos() {
    showLoading(true);
    try {
        const params = new URLSearchParams({ 
            page: state.pagination.page, 
            limit: state.pagination.limit,
            include_managers: '1'
        });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.manager_id) params.append('manager_id', state.filters.manager_id);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.lhos = data.data.lhos;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load LHOs');
        }
    } catch (error) {
        console.error('Error loading LHOs:', error);
        showError('Failed to load LHOs. Please try again.');
    } finally {
        showLoading(false);
    }
}

function renderManagerBadges(managers) {
    if (!managers || managers.length === 0) {
        return '<span class="text-xs text-gray-400 italic">No managers</span>';
    }
    
    if (managers.length <= 2) {
        return managers.map(m => `<span class="inline-flex items-center px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium"><i class="fas fa-user mr-1 text-[8px]"></i>${escapeHtml(m.manager_name)}</span>`).join(' ');
    }
    
    const firstTwo = managers.slice(0, 2).map(m => `<span class="inline-flex items-center px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium"><i class="fas fa-user mr-1 text-[8px]"></i>${escapeHtml(m.manager_name)}</span>`).join(' ');
    const remaining = managers.length - 2;
    return `${firstTwo} <span class="inline-flex items-center px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px] font-medium" title="${managers.slice(2).map(m => m.manager_name).join(', ')}">+${remaining} more</span>`;
}

function renderTable() {
    const tbody = document.getElementById('lhos-tbody');
    
    if (state.lhos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">
            <i class="fas fa-building text-4xl mb-3 text-gray-300"></i><p>No LHOs found</p></td></tr>`;
        return;
    }
    
    tbody.innerHTML = state.lhos.map((lho, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">${(state.pagination.page - 1) * state.pagination.limit + index + 1}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${lho.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-building text-indigo-500 text-xs"></i>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(lho.lho_name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5">
                <div class="flex flex-wrap gap-1">${renderManagerBadges(lho.managers)}</div>
            </td>
            <td class="px-4 py-2.5">
                ${lho.status === 'active' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'}
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    ${state.permissions.edit ? `<button onclick="editLho(${lho.id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit"><i class="fas fa-edit text-xs"></i></button>` : ''}
                    ${state.permissions.delete ? `<button onclick="confirmDelete(${lho.id}, '${escapeHtml(lho.lho_name)}')" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete"><i class="fas fa-trash text-xs"></i></button>` : ''}
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
    loadLhos();
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add LHO';
    document.getElementById('lho-id').value = '';
    document.getElementById('lho-name').value = '';
    document.getElementById('lho-status').value = 'active';
    state.selectedManagers = [];
    updateSelectedManagersDisplay();
    renderManagerOptions();
    document.getElementById('manager-search').value = '';
    clearErrors();
    document.getElementById('lho-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

async function editLho(id) {
    try {
        const response = await fetch(`${API_URL}?id=${id}&include_managers=1`, { credentials: 'include' });
        const data = await response.json();
        
        if (!data.success || !data.data.lho) {
            showError('Failed to load LHO details');
            return;
        }
        
        const lho = data.data.lho;
        
        document.getElementById('modal-title').textContent = 'Edit LHO';
        document.getElementById('lho-id').value = lho.id;
        document.getElementById('lho-name').value = lho.lho_name;
        document.getElementById('lho-status').value = lho.status;
        
        state.selectedManagers = (lho.managers || []).map(m => ({
            id: m.user_id,
            name: m.manager_name
        }));
        updateSelectedManagersDisplay();
        renderManagerOptions();
        document.getElementById('manager-search').value = '';
        
        clearErrors();
        document.getElementById('lho-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    } catch (error) {
        console.error('Error loading LHO:', error);
        showError('Failed to load LHO details');
    }
}

function closeLhoModal() {
    document.getElementById('lho-modal').classList.add('hidden');
    document.getElementById('managers-dropdown').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveLho(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('lho-id').value;
    const lhoName = document.getElementById('lho-name').value.trim();
    const status = document.getElementById('lho-status').value;
    const managerIds = state.selectedManagers.map(m => m.id);
    
    if (!lhoName) { showFieldError('lho_name', 'LHO name is required'); return; }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const payload = { 
            action: id ? 'update' : 'create', 
            lho_name: lhoName, 
            status,
            manager_ids: managerIds
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
            closeLhoModal();
            showSuccess(data.message || 'LHO saved successfully');
            loadLhos();
        } else {
            if (data.errors) Object.keys(data.errors).forEach(f => showFieldError(f, data.errors[f][0]));
            else showError(data.error?.message || data.message || 'Failed to save LHO');
        }
    } catch (error) {
        console.error('Error saving LHO:', error);
        showError('Failed to save LHO. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

function confirmDelete(id, name) {
    openConfirmModal('Delete LHO', `Are you sure you want to delete "${name}"?`, () => deleteLho(id));
}

async function deleteLho(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'LHO deleted successfully');
            loadLhos();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete LHO');
        }
    } catch (error) {
        console.error('Error deleting LHO:', error);
        showError('Failed to delete LHO. Please try again.');
    }
}

async function exportLhos() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        if (state.filters.manager_id) params.append('manager_id', state.filters.manager_id);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.lhos, 'lhos_export.csv');
            showSuccess('LHOs exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export LHOs');
        }
    } catch (error) {
        console.error('Error exporting LHOs:', error);
        showError('Failed to export LHOs. Please try again.');
    }
}

function downloadCSV(data, filename) {
    if (!data || data.length === 0) { showError('No data to export'); return; }
    
    const headers = ['ID', 'LHO Name', 'Managers', 'Status', 'Created At', 'Updated At'];
    const rows = data.map(lho => [
        lho.id,
        `"${(lho.lho_name || '').replace(/"/g, '""')}"`,
        `"${(lho.managers || '').replace(/"/g, '""')}"`,
        lho.status || '',
        lho.created_at || '',
        lho.updated_at || ''
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
    document.getElementById('lhos-table').classList.toggle('hidden', show);
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
