<?php
/**
 * Zones Management Page
 * 
 * Implements table with status filter and child counts
 * Add create/edit modal
 * Include cascade warning on delete
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 8.3
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
$user = $masterMiddleware->requireViewPermission('locations');

if (!$user) {
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Zone Management';
$currentPage = 'masters_zones';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'Zones']
];

$permissions = $masterMiddleware->getUserModulePermissions('locations');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Zone Management</h3>
            <p class="text-sm text-gray-500">Manage zones for regional grouping of states</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Add Zone
            </button>
            <?php endif; ?>
            <button onclick="exportZones()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <div class="p-4 border-b bg-gray-50">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <input type="text" id="search-input" placeholder="Search zones..." 
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
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
        <p class="mt-2 text-gray-500">Loading zones...</p>
    </div>
    
    <div class="overflow-x-auto">
        <table id="zones-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">ID <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="name">Zone Name <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">States</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Cities</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">Status <i class="fas fa-sort ml-1 text-gray-400"></i></th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="zones-tbody" class="divide-y divide-gray-100">
                <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    
    <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="zone-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeZoneModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Zone</h3>
                <button onclick="closeZoneModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="zone-form" onsubmit="saveZone(event)">
                <input type="hidden" id="zone-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="zone-name" class="block text-sm font-medium text-gray-700 mb-1">Zone Name <span class="text-red-500">*</span></label>
                        <input type="text" id="zone-name" name="name" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter zone name">
                        <p id="name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="zone-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="zone-status" name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeZoneModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition"><i class="fas fa-save mr-2"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const state = {
    zones: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', status: '' },
    sort: { field: 'id', direction: 'desc' },
    permissions: <?php echo json_encode($permissions); ?>
};

const API_URL = '../api/masters/zones.php';

document.addEventListener('DOMContentLoaded', function() {
    loadZones();
    setupEventListeners();
});

function setupEventListeners() {
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadZones();
        }, 300);
    });
    
    document.getElementById('status-filter').addEventListener('change', function(e) {
        state.filters.status = e.target.value;
        state.pagination.page = 1;
        loadZones();
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
            loadZones();
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

async function loadZones() {
    showLoading(true);
    try {
        const params = new URLSearchParams({ page: state.pagination.page, limit: state.pagination.limit });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success) {
            state.zones = data.data.zones;
            state.pagination = {
                page: data.data.pagination.page,
                limit: data.data.pagination.limit,
                total: data.data.pagination.total,
                total_pages: data.data.pagination.total_pages
            };
            renderTable();
            renderPagination();
        } else {
            showError(data.error?.message || 'Failed to load zones');
        }
    } catch (error) {
        console.error('Error loading zones:', error);
        showError('Failed to load zones. Please try again.');
    } finally {
        showLoading(false);
    }
}

function renderTable() {
    const tbody = document.getElementById('zones-tbody');
    
    if (state.zones.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">
            <i class="fas fa-layer-group text-4xl mb-3 text-gray-300"></i><p>No zones found</p></td></tr>`;
        return;
    }
    
    tbody.innerHTML = state.zones.map((z, index) => `
        <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-2.5 text-xs text-gray-500">${(state.pagination.page - 1) * state.pagination.limit + index + 1}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">#${z.id}</td>
            <td class="px-4 py-2.5">
                <div class="flex items-center">
                    <div class="w-7 h-7 bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg flex items-center justify-center mr-2.5 flex-shrink-0">
                        <i class="fas fa-layer-group text-orange-500 text-xs"></i>
                    </div>
                    <span class="font-medium text-gray-800 text-xs">${escapeHtml(z.name)}</span>
                </div>
            </td>
            <td class="px-4 py-2.5"><span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium">${z.state_count || 0} states</span></td>
            <td class="px-4 py-2.5"><span class="px-2 py-0.5 bg-purple-50 text-purple-600 rounded text-[10px] font-medium">${z.city_count || 0} cities</span></td>
            <td class="px-4 py-2.5">
                ${z.status === 'active' 
                    ? '<span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-600 rounded-full text-[10px] font-medium"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Inactive</span>'}
            </td>
            <td class="px-4 py-2.5">
                <div class="flex items-center justify-center gap-0.5">
                    ${state.permissions.edit ? `<button onclick="editZone(${z.id})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Edit"><i class="fas fa-edit text-xs"></i></button>` : ''}
                    ${state.permissions.delete ? `<button onclick="confirmDelete(${z.id}, '${escapeHtml(z.name)}', ${z.state_count || 0}, ${z.city_count || 0})" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete"><i class="fas fa-trash text-xs"></i></button>` : ''}
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
    loadZones();
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Zone';
    document.getElementById('zone-id').value = '';
    document.getElementById('zone-name').value = '';
    document.getElementById('zone-status').value = 'active';
    clearErrors();
    document.getElementById('zone-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editZone(id) {
    const z = state.zones.find(zone => zone.id === id);
    if (!z) return;
    
    document.getElementById('modal-title').textContent = 'Edit Zone';
    document.getElementById('zone-id').value = z.id;
    document.getElementById('zone-name').value = z.name;
    document.getElementById('zone-status').value = z.status;
    clearErrors();
    document.getElementById('zone-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeZoneModal() {
    document.getElementById('zone-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

async function saveZone(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('zone-id').value;
    const name = document.getElementById('zone-name').value.trim();
    const status = document.getElementById('zone-status').value;
    
    if (!name) { showFieldError('name', 'Zone name is required'); return; }
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const payload = { action: id ? 'update' : 'create', name, status };
        if (id) payload.id = parseInt(id);
        
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeZoneModal();
            showSuccess(data.message || 'Zone saved successfully');
            loadZones();
        } else {
            if (data.errors) Object.keys(data.errors).forEach(f => showFieldError(f, data.errors[f][0]));
            else showError(data.error?.message || data.message || 'Failed to save zone');
        }
    } catch (error) {
        console.error('Error saving zone:', error);
        showError('Failed to save zone. Please try again.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

function confirmDelete(id, name, stateCount, cityCount) {
    const hasChildren = stateCount > 0 || cityCount > 0;
    
    if (hasChildren) {
        openConfirmModal(
            'Delete Zone with Cascade',
            `"${name}" has ${stateCount} state(s) and ${cityCount} city(ies) associated with it. Deleting this zone will remove the zone assignment from these states and cities (they will not be deleted). Do you want to proceed?`,
            () => deleteZone(id),
            'warning'
        );
    } else {
        openConfirmModal('Delete Zone', `Are you sure you want to delete "${name}"?`, () => deleteZone(id));
    }
}

async function deleteZone(id) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'delete', id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'Zone deleted successfully');
            loadZones();
        } else {
            showError(data.error?.message || data.message || 'Failed to delete zone');
        }
    } catch (error) {
        console.error('Error deleting zone:', error);
        showError('Failed to delete zone. Please try again.');
    }
}

// Export zones
async function exportZones() {
    try {
        const params = new URLSearchParams({ export: '1' });
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.status) params.append('status', state.filters.status);
        
        const response = await fetch(`${API_URL}?${params}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            downloadCSV(data.data.zones, 'zones_export.csv');
            showSuccess('Zones exported successfully');
        } else {
            showError(data.error?.message || 'Failed to export zones');
        }
    } catch (error) {
        console.error('Error exporting zones:', error);
        showError('Failed to export zones. Please try again.');
    }
}

// Download CSV
function downloadCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('No data to export');
        return;
    }
    
    const headers = ['ID', 'Name', 'Status', 'States', 'Cities', 'Created At', 'Updated At'];
    const rows = data.map(z => [
        z.id,
        `"${(z.name || '').replace(/"/g, '""')}"`,
        z.status || '',
        z.state_count || 0,
        z.city_count || 0,
        z.created_at || '',
        z.updated_at || ''
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
    document.getElementById('zones-table').classList.toggle('hidden', show);
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
