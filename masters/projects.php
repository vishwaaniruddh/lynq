<?php
/**
 * Projects Management Page
 * 
 * Implements table with pagination, search, status filter
 * Add create/edit modal with form validation
 * Add view and delete confirmation modals (Soft Delete)
 * Include permission-based button visibility
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
$user = $masterMiddleware->requireViewPermission('projects');

if (!$user) {
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Project Management';
$currentPage = 'masters_projects';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'Projects']
];

// Get user permissions for this module
$permissions = $masterMiddleware->getUserModulePermissions('projects');

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <!-- Header -->
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2.5 bg-indigo-50 text-indigo-600 rounded-lg text-lg">
                    <i class="fas fa-project-diagram"></i>
                </span>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Project Management</h3>
                    <p class="text-xs text-gray-500">Manage project records and client initiatives for site rollouts</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateModal()" class="px-4 py-2.5 bg-primary text-white rounded-lg hover:bg-indigo-700 transition flex items-center shadow-sm font-medium text-xs">
                <i class="fas fa-plus mr-2"></i>Add Project
            </button>
            <?php endif; ?>
            <button onclick="exportProjects()" class="px-4 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition flex items-center shadow-sm font-medium text-xs">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="p-4 border-b bg-gray-50/60">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <input type="text" id="search-input" placeholder="Search projects by name, code or description..." 
                        class="w-full pl-9 pr-4 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                </div>
            </div>
            <div class="w-full md:w-48">
                <select id="status-filter" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-12 text-center">
        <i class="fas fa-spinner fa-spin text-3xl text-primary mb-3"></i>
        <p class="text-xs text-gray-500">Loading projects...</p>
    </div>
    
    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="projects-table" class="w-full text-xs">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider w-16">#</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">
                        ID <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="name">
                        Project Name <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">
                        Project Code
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">
                        Description
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="status">
                        Status <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="created_at">
                        Created <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="projects-tbody" class="divide-y divide-gray-100">
                <!-- Rows populated via AJAX -->
            </tbody>
        </table>
    </div>
    
    <!-- Empty state -->
    <div id="empty-state" class="hidden p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-50 text-indigo-500 mb-4">
            <i class="fas fa-project-diagram text-2xl"></i>
        </div>
        <h3 class="text-sm font-bold text-gray-800 mb-1">No projects found</h3>
        <p class="text-xs text-gray-500 max-w-sm mx-auto mb-4">Get started by creating your first project master record.</p>
        <?php if ($permissions['create']): ?>
        <button onclick="openCreateModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-indigo-700 transition text-xs font-medium inline-flex items-center">
            <i class="fas fa-plus mr-1.5"></i>Add Project
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <div id="pagination" class="p-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600">
        <div id="pagination-info">Showing 0 to 0 of 0 entries</div>
        <div id="pagination-controls" class="flex items-center space-x-1"></div>
    </div>
</div>

<!-- ========================================== -->
<!-- CREATE / EDIT MODAL -->
<!-- ========================================== -->
<div id="form-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeFormModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100 text-xs">
            <div class="bg-gradient-to-r from-indigo-700 to-purple-800 text-white px-6 py-4 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2" id="modal-title">
                    <i class="fas fa-project-diagram"></i> Add New Project
                </h3>
                <button type="button" onclick="closeFormModal()" class="text-white/70 hover:text-white transition">
                    <i class="fas fa-times text-base"></i>
                </button>
            </div>
            
            <form id="project-form" onsubmit="handleFormSubmit(event)">
                <input type="hidden" id="project-id" name="id">
                
                <div class="p-6 space-y-4">
                    <div>
                        <label for="project-name" class="block font-semibold text-gray-700 mb-1">
                            Project Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="project-name" name="name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs"
                            placeholder="e.g. Diebold ATM Network Rollout 2026">
                        <p id="name-error" class="mt-1 text-[11px] text-red-500 hidden"></p>
                    </div>

                    <div>
                        <label for="project-code" class="block font-semibold text-gray-700 mb-1">
                            Project Code
                        </label>
                        <input type="text" id="project-code" name="code"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs font-mono uppercase"
                            placeholder="e.g. PROJ_DB_2026">
                        <p id="code-error" class="mt-1 text-[11px] text-red-500 hidden"></p>
                    </div>

                    <div>
                        <label for="project-desc" class="block font-semibold text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea id="project-desc" name="description" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-xs"
                            placeholder="Brief details about the project scope, client, or deliverables..."></textarea>
                    </div>
                    
                    <div class="pt-1">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="project-status" name="status" value="1" checked
                                class="rounded text-primary focus:ring-primary h-4 w-4">
                            <span class="ml-2 font-semibold text-gray-700">Active Status</span>
                        </label>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-3.5 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" onclick="closeFormModal()"
                        class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-button"
                        class="px-5 py-2 text-xs font-bold text-white bg-primary hover:bg-indigo-700 rounded-lg transition shadow-sm flex items-center">
                        <i class="fas fa-save mr-1.5"></i> Save Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- VIEW DETAILS MODAL -->
<!-- ========================================== -->
<div id="view-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeViewModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100 text-xs">
            <div class="bg-gray-900 text-white px-6 py-4 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-info-circle text-indigo-400"></i> Project Details
                </h3>
                <button type="button" onclick="closeViewModal()" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-base"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-500 font-semibold">Project ID:</span>
                        <p id="view-project-id" class="font-bold text-gray-800 font-mono mt-0.5">-</p>
                    </div>
                    <div>
                        <span class="text-gray-500 font-semibold">Status:</span>
                        <div id="view-project-status" class="mt-0.5">-</div>
                    </div>
                </div>
                
                <div>
                    <span class="text-gray-500 font-semibold">Project Name:</span>
                    <p id="view-project-name" class="font-bold text-gray-800 text-sm mt-0.5">-</p>
                </div>

                <div>
                    <span class="text-gray-500 font-semibold">Project Code:</span>
                    <p id="view-project-code" class="font-mono text-gray-800 mt-0.5">-</p>
                </div>

                <div>
                    <span class="text-gray-500 font-semibold">Description:</span>
                    <p id="view-project-desc" class="text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100 mt-0.5">-</p>
                </div>
                
                <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100 text-[11px]">
                    <div>
                        <span class="text-gray-400">Created At:</span>
                        <p id="view-project-created" class="text-gray-600 mt-0.5">-</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Created By:</span>
                        <p id="view-project-author" class="text-gray-600 mt-0.5">-</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-end">
                <button type="button" onclick="closeViewModal()"
                    class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200 rounded-lg transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- DELETE CONFIRMATION MODAL (SOFT DELETE) -->
<!-- ========================================== -->
<div id="delete-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeDeleteModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100 text-xs">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-trash-alt text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-800">Delete Project</h3>
                        <p class="text-[11px] text-gray-500">Soft delete project record</p>
                    </div>
                </div>
                
                <div class="mt-4 bg-amber-50 border border-amber-100 rounded-xl p-3 text-amber-800 text-[11px] space-y-1">
                    <p class="font-semibold flex items-center gap-1.5">
                        <i class="fas fa-info-circle"></i> Soft Deletion Policy
                    </p>
                    <p>Are you sure you want to delete <span id="delete-project-name" class="font-bold text-gray-900"></span>? The project will be hidden from listings but retained in audit logs.</p>
                </div>
            </div>
            
            <div class="bg-gray-50 px-6 py-3.5 border-t border-gray-200 flex justify-end gap-2">
                <button type="button" onclick="closeDeleteModal()"
                    class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200 rounded-lg transition">
                    Cancel
                </button>
                <button type="button" id="confirm-delete-button" onclick="confirmDelete()"
                    class="px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg transition shadow-sm">
                    Delete Project
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State
let projectsData = [];
let currentPage = 1;
let currentLimit = 10;
let totalPages = 1;
let currentSort = 'id';
let currentOrder = 'DESC';
let projectToDelete = null;

// Permissions from PHP
const canEdit = <?php echo $permissions['edit'] ? 'true' : 'false'; ?>;
const canDelete = <?php echo $permissions['delete'] ? 'true' : 'false'; ?>;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadProjects();
    
    // Search input with debounce
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadProjects();
        }, 300);
    });
    
    // Status filter
    document.getElementById('status-filter').addEventListener('change', function() {
        currentPage = 1;
        loadProjects();
    });
    
    // Table sorting
    document.querySelectorAll('#projects-table th[data-sort]').forEach(th => {
        th.addEventListener('click', function() {
            const sort = this.dataset.sort;
            if (currentSort === sort) {
                currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSort = sort;
                currentOrder = 'ASC';
            }
            loadProjects();
        });
    });
});

/**
 * Load projects from API
 */
async function loadProjects() {
    const search = document.getElementById('search-input').value;
    const status = document.getElementById('status-filter').value;
    
    const params = new URLSearchParams({
        page: currentPage,
        limit: currentLimit,
        orderBy: currentSort,
        orderDir: currentOrder
    });
    
    if (search) params.append('search', search);
    if (status !== '') params.append('status', status);
    
    document.getElementById('loading-indicator').classList.remove('hidden');
    document.getElementById('projects-tbody').innerHTML = '';
    document.getElementById('empty-state').classList.add('hidden');
    
    try {
        const response = await fetch(`../api/masters/projects.php?${params}`);
        const result = await response.json();
        
        document.getElementById('loading-indicator').classList.add('hidden');
        
        if (result.success) {
            projectsData = result.data.projects || [];
            const pagination = result.data.pagination || { total: 0, page: 1, limit: 10, total_pages: 1 };
            
            totalPages = pagination.total_pages;
            renderTable(projectsData);
            renderPagination(pagination);
        } else {
            showToast(result.error?.message || 'Failed to load projects', 'error');
        }
    } catch (error) {
        document.getElementById('loading-indicator').classList.add('hidden');
        showToast('Network error while loading projects', 'error');
    }
}

/**
 * Render table rows
 */
function renderTable(projects) {
    const tbody = document.getElementById('projects-tbody');
    const emptyState = document.getElementById('empty-state');
    
    if (!projects || projects.length === 0) {
        tbody.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }
    
    emptyState.classList.add('hidden');
    
    tbody.innerHTML = projects.map((project, idx) => {
        const statusBadge = project.status == 1
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-700 border border-red-200">Inactive</span>';
        
        const codeBadge = project.code 
            ? `<span class="px-1.5 py-0.5 bg-gray-100 text-gray-700 rounded font-mono text-[10px] font-semibold">${escapeHtml(project.code)}</span>`
            : '<span class="text-gray-400">-</span>';

        return `
            <tr class="hover:bg-gray-50/80 transition-colors">
                <td class="px-4 py-3 text-gray-400 font-mono">${(currentPage - 1) * currentLimit + idx + 1}</td>
                <td class="px-4 py-3 font-mono text-gray-500 font-semibold">${project.id}</td>
                <td class="px-4 py-3">
                    <span class="font-bold text-gray-800">${escapeHtml(project.name)}</span>
                </td>
                <td class="px-4 py-3">${codeBadge}</td>
                <td class="px-4 py-3 text-gray-500 max-w-xs truncate" title="${escapeHtml(project.description || '')}">
                    ${escapeHtml(project.description || '-')}
                </td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3 text-gray-500 text-[11px]">${formatDate(project.created_at)}</td>
                <td class="px-4 py-3 text-right space-x-1 whitespace-nowrap">
                    <button onclick="viewProject(${project.id})" class="p-1.5 text-gray-600 hover:bg-gray-100 rounded transition" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${canEdit ? `
                    <button onclick="editProject(${project.id})" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded transition" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    ` : ''}
                    ${canDelete ? `
                    <button onclick="openDeleteModal(${project.id}, '${escapeHtml(project.name)}')" class="p-1.5 text-red-600 hover:bg-red-50 rounded transition" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    ` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Render pagination controls
 */
function renderPagination(pagination) {
    const info = document.getElementById('pagination-info');
    const controls = document.getElementById('pagination-controls');
    
    const start = pagination.total === 0 ? 0 : (pagination.page - 1) * pagination.limit + 1;
    const end = Math.min(pagination.page * pagination.limit, pagination.total);
    
    info.textContent = `Showing ${start} to ${end} of ${pagination.total} entries`;
    
    if (pagination.total_pages <= 1) {
        controls.innerHTML = '';
        return;
    }
    
    let html = '';
    
    html += `
        <button onclick="goToPage(${pagination.page - 1})" ${pagination.page <= 1 ? 'disabled' : ''}
            class="px-2.5 py-1 rounded border ${pagination.page <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-100'}">
            <i class="fas fa-chevron-left text-[10px]"></i>
        </button>
    `;
    
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === 1 || i === pagination.total_pages || (i >= pagination.page - 1 && i <= pagination.page + 1)) {
            html += `
                <button onclick="goToPage(${i})"
                    class="px-2.5 py-1 rounded border font-medium ${i === pagination.page ? 'bg-primary text-white border-primary' : 'text-gray-700 hover:bg-gray-100'}">
                    ${i}
                </button>
            `;
        } else if (i === pagination.page - 2 || i === pagination.page + 2) {
            html += '<span class="px-1 text-gray-400">...</span>';
        }
    }
    
    html += `
        <button onclick="goToPage(${pagination.page + 1})" ${pagination.page >= pagination.total_pages ? 'disabled' : ''}
            class="px-2.5 py-1 rounded border ${pagination.page >= pagination.total_pages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-100'}">
            <i class="fas fa-chevron-right text-[10px]"></i>
        </button>
    `;
    
    controls.innerHTML = html;
}

function goToPage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        loadProjects();
    }
}

/**
 * Open create modal
 */
function openCreateModal() {
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Add New Project';
    document.getElementById('project-form').reset();
    document.getElementById('project-id').value = '';
    document.getElementById('project-status').checked = true;
    clearErrors();
    document.getElementById('form-modal').classList.remove('hidden');
}

/**
 * Open edit modal
 */
function editProject(id) {
    const project = projectsData.find(p => p.id == id);
    if (!project) return;
    
    document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit mr-1"></i> Edit Project';
    document.getElementById('project-id').value = project.id;
    document.getElementById('project-name').value = project.name;
    document.getElementById('project-code').value = project.code || '';
    document.getElementById('project-desc').value = project.description || '';
    document.getElementById('project-status').checked = project.status == 1;
    
    clearErrors();
    document.getElementById('form-modal').classList.remove('hidden');
}

function closeFormModal() {
    document.getElementById('form-modal').classList.add('hidden');
}

/**
 * Handle create/update form submission
 */
async function handleFormSubmit(event) {
    event.preventDefault();
    clearErrors();
    
    const id = document.getElementById('project-id').value;
    const name = document.getElementById('project-name').value.trim();
    const code = document.getElementById('project-code').value.trim();
    const description = document.getElementById('project-desc').value.trim();
    const status = document.getElementById('project-status').checked ? 1 : 0;
    
    if (!name) {
        showError('name', 'Project Name is required');
        return;
    }
    
    const saveButton = document.getElementById('save-button');
    saveButton.disabled = true;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i> Saving...';
    
    const isEdit = id !== '';
    const payload = {
        action: isEdit ? 'update' : 'create',
        name: name,
        code: code || null,
        description: description || null,
        status: status
    };
    
    if (isEdit) payload.id = parseInt(id);
    
    try {
        const response = await fetch('../api/masters/projects.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        saveButton.disabled = false;
        saveButton.innerHTML = '<i class="fas fa-save mr-1.5"></i> Save Project';
        
        if (result.success) {
            showToast(result.message || (isEdit ? 'Project updated' : 'Project created'), 'success');
            closeFormModal();
            loadProjects();
        } else {
            if (result.error?.details) {
                Object.keys(result.error.details).forEach(field => {
                    const msg = Array.isArray(result.error.details[field]) ? result.error.details[field][0] : result.error.details[field];
                    showError(field, msg);
                });
            } else {
                showToast(result.error?.message || 'Operation failed', 'error');
            }
        }
    } catch (error) {
        saveButton.disabled = false;
        saveButton.innerHTML = '<i class="fas fa-save mr-1.5"></i> Save Project';
        showToast('Server error while saving project', 'error');
    }
}

/**
 * View details modal
 */
function viewProject(id) {
    const project = projectsData.find(p => p.id == id);
    if (!project) return;
    
    document.getElementById('view-project-id').textContent = project.id;
    document.getElementById('view-project-name').textContent = project.name;
    document.getElementById('view-project-code').textContent = project.code || '-';
    document.getElementById('view-project-desc').textContent = project.description || 'No description provided';
    document.getElementById('view-project-status').innerHTML = project.status == 1
        ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>'
        : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-700 border border-red-200">Inactive</span>';
    document.getElementById('view-project-created').textContent = formatDate(project.created_at);
    document.getElementById('view-project-author').textContent = project.created_by_name || 'System';
    
    document.getElementById('view-modal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
}

/**
 * Soft delete modal
 */
function openDeleteModal(id, name) {
    projectToDelete = id;
    document.getElementById('delete-project-name').textContent = name;
    document.getElementById('delete-modal').classList.remove('hidden');
}

function closeDeleteModal() {
    projectToDelete = null;
    document.getElementById('delete-modal').classList.add('hidden');
}

async function confirmDelete() {
    if (!projectToDelete) return;
    
    const deleteButton = document.getElementById('confirm-delete-button');
    deleteButton.disabled = true;
    deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...';
    
    try {
        const response = await fetch('../api/masters/projects.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: projectToDelete
            })
        });
        
        const result = await response.json();
        deleteButton.disabled = false;
        deleteButton.textContent = 'Delete Project';
        
        if (result.success) {
            showToast('Project soft deleted successfully', 'success');
            closeDeleteModal();
            loadProjects();
        } else {
            showToast(result.error?.message || 'Failed to delete project', 'error');
        }
    } catch (error) {
        deleteButton.disabled = false;
        deleteButton.textContent = 'Delete Project';
        showToast('Server error while deleting project', 'error');
    }
}

/**
 * Export projects to CSV
 */
async function exportProjects() {
    const search = document.getElementById('search-input').value;
    const status = document.getElementById('status-filter').value;
    
    const params = new URLSearchParams({ export: '1' });
    if (search) params.append('search', search);
    if (status !== '') params.append('status', status);
    
    try {
        const response = await fetch(`../api/masters/projects.php?${params}`);
        const result = await response.json();
        
        if (result.success && result.data.projects) {
            const csvContent = generateCSV(result.data.projects);
            downloadCSV(csvContent, `projects_export_${new Date().toISOString().slice(0, 10)}.csv`);
            showToast('Projects exported successfully', 'success');
        } else {
            showToast('Failed to export projects', 'error');
        }
    } catch (error) {
        showToast('Export error', 'error');
    }
}

function generateCSV(data) {
    const headers = ['ID', 'Project Name', 'Project Code', 'Description', 'Status', 'Created At'];
    const rows = data.map(p => [
        p.id,
        `"${(p.name || '').replace(/"/g, '""')}"`,
        `"${(p.code || '').replace(/"/g, '""')}"`,
        `"${(p.description || '').replace(/"/g, '""')}"`,
        p.status == 1 ? 'Active' : 'Inactive',
        p.created_at || ''
    ]);
    
    return [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
}

function downloadCSV(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

function showError(field, message) {
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
