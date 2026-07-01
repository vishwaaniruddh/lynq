<?php
/**
 * Notes Management Page
 * 
 * Comprehensive notes management interface with full CRUD operations
 * Complements the popup notes functionality with a dedicated page
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Notes Management';
$currentPage = 'notes';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Notes']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <!-- Header -->
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-800">Notes Management</h3>
            <p class="text-xs text-gray-500 mt-0.5">Manage notes and annotations across the system</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="openCreateModal()" class="px-3 py-1.5 bg-primary text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center text-xs font-medium">
                <i class="fas fa-plus mr-1.5"></i>Add Note
            </button>
            <button onclick="exportNotes()" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center text-xs font-medium">
                <i class="fas fa-file-excel mr-1.5"></i>Export
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
            <div onclick="filterByType('')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-blue-300 hover:shadow-md transition-all" id="card-total">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-sticky-note text-blue-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Total Notes</p>
                        <p id="total-count" class="text-lg font-semibold text-gray-800">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByType('site')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-emerald-300 hover:shadow-md transition-all" id="card-site">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-map-marker-alt text-emerald-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">Site Notes</p>
                        <p id="site-count" class="text-lg font-semibold text-emerald-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByType('user')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-purple-300 hover:shadow-md transition-all" id="card-user">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-user text-purple-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">User Notes</p>
                        <p id="user-count" class="text-lg font-semibold text-purple-600">0</p>
                    </div>
                </div>
            </div>
            <div onclick="filterByType('general')" class="bg-white px-3 py-2.5 rounded-lg border border-gray-100 shadow-sm cursor-pointer hover:border-orange-300 hover:shadow-md transition-all" id="card-general">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg flex items-center justify-center mr-2.5">
                        <i class="fas fa-clipboard text-orange-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wide">General</p>
                        <p id="general-count" class="text-lg font-semibold text-orange-600">0</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-2">
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="search-input" placeholder="Search notes by title, content..." 
                        class="w-full pl-9 pr-4 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                </div>
            </div>
            <div>
                <select id="type-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Types</option>
                    <option value="site">Site Notes</option>
                    <option value="user">User Notes</option>
                    <option value="general">General Notes</option>
                </select>
            </div>
            <div>
                <select id="priority-filter" class="px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary bg-white">
                    <option value="">All Priorities</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="loading-indicator" class="hidden p-8 text-center">
        <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
        <p class="mt-2 text-gray-500">Loading notes...</p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table id="notes-table" class="w-full text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="id">
                        ID <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="title">
                        Title <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="type">
                        Type <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="priority">
                        Priority <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="created_by">
                        Created By <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors" data-sort="created_at">
                        Created <i class="fas fa-sort ml-1 text-gray-400"></i>
                    </th>
                    <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="notes-tbody" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="pagination-container" class="p-4 border-t flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div id="pagination-info" class="text-sm text-gray-500"></div>
        <div id="pagination-controls" class="flex items-center gap-2"></div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="note-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeNoteModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-gray-100 sticky top-0 bg-white">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800">Add Note</h3>
                <button onclick="closeNoteModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="note-form" onsubmit="saveNote(event)">
                <input type="hidden" id="note-id" value="">
                <div class="p-5 space-y-4">
                    <!-- Row 1: Title & Type -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="note-title" class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                            <input type="text" id="note-title" name="title" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                placeholder="Enter note title">
                            <p id="title-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                        <div>
                            <label for="note-type" class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                            <select id="note-type" name="type" required onchange="onTypeChange()"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select type</option>
                                <option value="site">Site Note</option>
                                <option value="user">User Note</option>
                                <option value="general">General Note</option>
                            </select>
                            <p id="type-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    
                    <!-- Row 2: Priority & Reference -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="note-priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select id="note-priority" name="priority"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div id="reference-container" class="hidden">
                            <label for="note-reference" class="block text-sm font-medium text-gray-700 mb-1">Reference <span class="text-red-500">*</span></label>
                            <select id="note-reference" name="reference_id"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select reference</option>
                            </select>
                            <p id="reference-error" class="mt-1 text-sm text-red-500 hidden"></p>
                        </div>
                    </div>
                    
                    <!-- Row 3: Content -->
                    <div>
                        <label for="note-content" class="block text-sm font-medium text-gray-700 mb-1">Content <span class="text-red-500">*</span></label>
                        <textarea id="note-content" name="content" rows="6" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter note content"></textarea>
                        <p id="content-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    
                    <!-- Row 4: Tags -->
                    <div>
                        <label for="note-tags" class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                        <input type="text" id="note-tags" name="tags"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            placeholder="Enter tags separated by commas">
                        <p class="mt-1 text-xs text-gray-500">Separate multiple tags with commas</p>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl sticky bottom-0">
                    <button type="button" onclick="closeNoteModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" id="save-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="view-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Note Details</h3>
                <button onclick="closeViewModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="view-content" class="p-5 max-h-[60vh] overflow-y-auto">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Confirm Delete</h3>
                <button onclick="closeDeleteModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="flex items-start">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4">
                        <i class="fas fa-trash-alt text-red-500 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-700">Are you sure you want to delete this note?</p>
                        <p class="text-sm text-gray-500 mt-1">This action cannot be undone.</p>
                    </div>
                </div>
                <input type="hidden" id="delete-note-id">
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" onclick="confirmDelete()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-trash-alt mr-2"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// State management
const state = {
    notes: [],
    pagination: { page: 1, limit: 20, total: 0, total_pages: 0 },
    filters: { search: '', type: '', priority: '' },
    sort: { field: 'created_at', direction: 'desc' },
    counts: { total: 0, site: 0, user: 0, general: 0 },
    references: { sites: [], users: [] }
};

// API base URLs
const API_URL = '../api/notes';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotes();
    setupEventListeners();
    loadReferences();
});

// Setup event listeners
function setupEventListeners() {
    // Search with debounce
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            state.filters.search = e.target.value;
            state.pagination.page = 1;
            loadNotes();
        }, 300);
    });
    
    // Type filter
    document.getElementById('type-filter').addEventListener('change', function(e) {
        state.filters.type = e.target.value;
        state.pagination.page = 1;
        updateCardHighlights();
        loadNotes();
    });
    
    // Priority filter
    document.getElementById('priority-filter').addEventListener('change', function(e) {
        state.filters.priority = e.target.value;
        state.pagination.page = 1;
        loadNotes();
    });
    
    // Table sorting
    document.querySelectorAll('[data-sort]').forEach(header => {
        header.addEventListener('click', function() {
            const field = this.dataset.sort;
            if (state.sort.field === field) {
                state.sort.direction = state.sort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort.field = field;
                state.sort.direction = 'asc';
            }
            loadNotes();
        });
    });
}

// Load notes from API
async function loadNotes() {
    showLoading(true);
    
    try {
        const params = new URLSearchParams({
            page: state.pagination.page,
            limit: state.pagination.limit,
            search: state.filters.search,
            type: state.filters.type,
            priority: state.filters.priority,
            sort_field: state.sort.field,
            sort_direction: state.sort.direction
        });
        
        const response = await fetch(`${API_URL}/list.php?${params}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            state.notes = data.data.notes || [];
            state.pagination = data.data.pagination || state.pagination;
            state.counts = data.data.counts || state.counts;
            
            renderTable();
            renderPagination();
            updateCounts();
            updateSortIcons();
        } else {
            showError(data.error?.message || 'Failed to load notes');
        }
    } catch (error) {
        console.error('Error loading notes:', error);
        showError('Failed to load notes. Please try again.');
    } finally {
        showLoading(false);
    }
}

// Render notes table
function renderTable() {
    const tbody = document.getElementById('notes-tbody');
    
    if (state.notes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-sticky-note text-4xl mb-3 text-gray-300"></i><p>No notes found</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = state.notes.map(note => `
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm text-gray-900">${note.id}</td>
            <td class="px-4 py-3">
                <div class="text-sm font-medium text-gray-900">${escapeHtml(note.title)}</div>
                ${note.tags ? `<div class="text-xs text-gray-500 mt-1">${renderTags(note.tags)}</div>` : ''}
            </td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getTypeColor(note.type)}">
                    <i class="${getTypeIcon(note.type)} mr-1"></i>
                    ${capitalizeFirst(note.type)}
                </span>
            </td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getPriorityColor(note.priority)}">
                    ${capitalizeFirst(note.priority)}
                </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(note.created_by_name || 'Unknown')}</td>
            <td class="px-4 py-3 text-sm text-gray-500">${formatDateTime(note.created_at)}</td>
            <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center space-x-2">
                    <button onclick="viewNote(${note.id})" class="text-blue-600 hover:text-blue-800 transition-colors" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editNote(${note.id})" class="text-green-600 hover:text-green-800 transition-colors" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteNote(${note.id})" class="text-red-600 hover:text-red-800 transition-colors" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Helper functions for rendering
function getTypeColor(type) {
    const colors = {
        site: 'bg-emerald-100 text-emerald-800',
        user: 'bg-purple-100 text-purple-800',
        general: 'bg-orange-100 text-orange-800'
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
}

function getTypeIcon(type) {
    const icons = {
        site: 'fas fa-map-marker-alt',
        user: 'fas fa-user',
        general: 'fas fa-clipboard'
    };
    return icons[type] || 'fas fa-sticky-note';
}

function getPriorityColor(priority) {
    const colors = {
        high: 'bg-red-100 text-red-800',
        medium: 'bg-yellow-100 text-yellow-800',
        low: 'bg-green-100 text-green-800'
    };
    return colors[priority] || 'bg-gray-100 text-gray-800';
}

function renderTags(tags) {
    if (!tags) return '';
    return tags.split(',').map(tag => 
        `<span class="inline-block bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs mr-1">${escapeHtml(tag.trim())}</span>`
    ).join('');
}

// Load reference data for dropdowns
async function loadReferences() {
    try {
        // Load sites
        const sitesResponse = await fetch('../api/sites/list.php?limit=1000', {
            credentials: 'include'
        });
        const sitesData = await sitesResponse.json();
        if (sitesData.success) {
            state.references.sites = sitesData.data.sites || [];
        }
        
        // Load users
        const usersResponse = await fetch('../api/users/list.php?limit=1000', {
            credentials: 'include'
        });
        const usersData = await usersResponse.json();
        if (usersData.success) {
            state.references.users = usersData.data.users || [];
        }
    } catch (error) {
        console.error('Error loading references:', error);
    }
}

// Modal functions
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Note';
    document.getElementById('note-id').value = '';
    document.getElementById('note-form').reset();
    document.getElementById('reference-container').classList.add('hidden');
    clearErrors();
    document.getElementById('note-modal').classList.remove('hidden');
}

function editNote(id) {
    const note = state.notes.find(n => n.id === id);
    if (!note) return;
    
    document.getElementById('modal-title').textContent = 'Edit Note';
    document.getElementById('note-id').value = note.id;
    document.getElementById('note-title').value = note.title;
    document.getElementById('note-type').value = note.type;
    document.getElementById('note-priority').value = note.priority;
    document.getElementById('note-content').value = note.content;
    document.getElementById('note-tags').value = note.tags || '';
    
    onTypeChange(); // Show reference dropdown if needed
    if (note.reference_id) {
        document.getElementById('note-reference').value = note.reference_id;
    }
    
    clearErrors();
    document.getElementById('note-modal').classList.remove('hidden');
}

function closeNoteModal() {
    document.getElementById('note-modal').classList.add('hidden');
}

function onTypeChange() {
    const type = document.getElementById('note-type').value;
    const referenceContainer = document.getElementById('reference-container');
    const referenceSelect = document.getElementById('note-reference');
    
    if (type === 'site' || type === 'user') {
        referenceContainer.classList.remove('hidden');
        
        // Populate reference dropdown
        const references = type === 'site' ? state.references.sites : state.references.users;
        const nameField = type === 'site' ? 'site_name' : 'name';
        
        referenceSelect.innerHTML = '<option value="">Select ' + type + '</option>' +
            references.map(ref => `<option value="${ref.id}">${escapeHtml(ref[nameField])}</option>`).join('');
    } else {
        referenceContainer.classList.add('hidden');
    }
}

// Save note
async function saveNote(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const noteId = document.getElementById('note-id').value;
    const isEdit = !!noteId;
    
    try {
        const url = isEdit ? `${API_URL}/update.php` : `${API_URL}/create.php`;
        if (isEdit) {
            formData.append('id', noteId);
        }
        
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(isEdit ? 'Note updated successfully' : 'Note created successfully');
            closeNoteModal();
            loadNotes();
        } else {
            if (data.errors) {
                showValidationErrors(data.errors);
            } else {
                showError(data.error?.message || 'Failed to save note');
            }
        }
    } catch (error) {
        console.error('Error saving note:', error);
        showError('Failed to save note. Please try again.');
    }
}

// View note
async function viewNote(id) {
    try {
        const response = await fetch(`${API_URL}/detail.php?id=${id}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            const note = data.data;
            document.getElementById('view-content').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <p class="mt-1 text-sm text-gray-900">${escapeHtml(note.title)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <span class="mt-1 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getTypeColor(note.type)}">
                                <i class="${getTypeIcon(note.type)} mr-1"></i>
                                ${capitalizeFirst(note.type)}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Priority</label>
                            <span class="mt-1 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getPriorityColor(note.priority)}">
                                ${capitalizeFirst(note.priority)}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Created By</label>
                            <p class="mt-1 text-sm text-gray-900">${escapeHtml(note.created_by_name || 'Unknown')}</p>
                        </div>
                    </div>
                    ${note.reference_name ? `
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reference</label>
                        <p class="mt-1 text-sm text-gray-900">${escapeHtml(note.reference_name)}</p>
                    </div>
                    ` : ''}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Content</label>
                        <div class="mt-1 p-3 bg-gray-50 rounded-lg text-sm text-gray-900 whitespace-pre-wrap">${escapeHtml(note.content)}</div>
                    </div>
                    ${note.tags ? `
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tags</label>
                        <div class="mt-1">${renderTags(note.tags)}</div>
                    </div>
                    ` : ''}
                    <div class="grid grid-cols-2 gap-4 text-xs text-gray-500">
                        <div>
                            <label class="block font-medium">Created</label>
                            <p>${formatDateTime(note.created_at)}</p>
                        </div>
                        <div>
                            <label class="block font-medium">Updated</label>
                            <p>${formatDateTime(note.updated_at)}</p>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('view-modal').classList.remove('hidden');
        } else {
            showError(data.error?.message || 'Failed to load note details');
        }
    } catch (error) {
        console.error('Error loading note:', error);
        showError('Failed to load note details. Please try again.');
    }
}

function closeViewModal() {
    document.getElementById('view-modal').classList.add('hidden');
}

// Delete note
function deleteNote(id) {
    document.getElementById('delete-note-id').value = id;
    document.getElementById('delete-modal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
}

async function confirmDelete() {
    const id = document.getElementById('delete-note-id').value;
    
    try {
        const response = await fetch(`${API_URL}/delete.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) }),
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Note deleted successfully');
            closeDeleteModal();
            loadNotes();
        } else {
            showError(data.error?.message || 'Failed to delete note');
        }
    } catch (error) {
        console.error('Error deleting note:', error);
        showError('Failed to delete note. Please try again.');
    }
}

// Filter functions
function filterByType(type) {
    document.getElementById('type-filter').value = type;
    state.filters.type = type;
    state.pagination.page = 1;
    updateCardHighlights();
    loadNotes();
}

function updateCardHighlights() {
    // Remove active class from all cards
    document.querySelectorAll('[id^="card-"]').forEach(card => {
        card.classList.remove('border-blue-300', 'shadow-md');
        card.classList.add('border-gray-100');
    });
    
    // Add active class to current filter
    const activeCard = state.filters.type ? `card-${state.filters.type}` : 'card-total';
    const card = document.getElementById(activeCard);
    if (card) {
        card.classList.remove('border-gray-100');
        card.classList.add('border-blue-300', 'shadow-md');
    }
}

// Export notes
async function exportNotes() {
    try {
        const params = new URLSearchParams({
            search: state.filters.search,
            type: state.filters.type,
            priority: state.filters.priority,
            export: 'csv'
        });
        
        window.open(`${API_URL}/export.php?${params}`, '_blank');
    } catch (error) {
        console.error('Error exporting notes:', error);
        showError('Failed to export notes. Please try again.');
    }
}

// Utility functions
function showLoading(show) {
    document.getElementById('loading-indicator').classList.toggle('hidden', !show);
    document.getElementById('notes-table').classList.toggle('hidden', show);
}

function updateCounts() {
    document.getElementById('total-count').textContent = state.counts.total || 0;
    document.getElementById('site-count').textContent = state.counts.site || 0;
    document.getElementById('user-count').textContent = state.counts.user || 0;
    document.getElementById('general-count').textContent = state.counts.general || 0;
}

function updateSortIcons() {
    // Reset all sort icons
    document.querySelectorAll('[data-sort] i').forEach(icon => {
        icon.className = 'fas fa-sort ml-1 text-gray-400';
    });
    
    // Update active sort icon
    const activeHeader = document.querySelector(`[data-sort="${state.sort.field}"] i`);
    if (activeHeader) {
        activeHeader.className = `fas fa-sort-${state.sort.direction === 'asc' ? 'up' : 'down'} ml-1 text-primary`;
    }
}

function renderPagination() {
    const container = document.getElementById('pagination-container');
    const info = document.getElementById('pagination-info');
    const controls = document.getElementById('pagination-controls');
    
    if (state.pagination.total === 0) {
        container.classList.add('hidden');
        return;
    }
    
    container.classList.remove('hidden');
    
    const start = (state.pagination.page - 1) * state.pagination.limit + 1;
    const end = Math.min(start + state.pagination.limit - 1, state.pagination.total);
    
    info.textContent = `Showing ${start} to ${end} of ${state.pagination.total} notes`;
    
    // Generate pagination controls
    let paginationHTML = '';
    
    // Previous button
    if (state.pagination.page > 1) {
        paginationHTML += `<button onclick="changePage(${state.pagination.page - 1})" class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-50">Previous</button>`;
    }
    
    // Page numbers
    const totalPages = state.pagination.total_pages;
    const currentPage = state.pagination.page;
    
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        paginationHTML += `<button onclick="changePage(1)" class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-50">1</button>`;
        if (startPage > 2) {
            paginationHTML += `<span class="px-2 text-gray-500">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === currentPage;
        paginationHTML += `<button onclick="changePage(${i})" class="px-3 py-1 text-sm border rounded-lg ${isActive ? 'bg-primary text-white border-primary' : 'hover:bg-gray-50'}">${i}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHTML += `<span class="px-2 text-gray-500">...</span>`;
        }
        paginationHTML += `<button onclick="changePage(${totalPages})" class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-50">${totalPages}</button>`;
    }
    
    // Next button
    if (state.pagination.page < totalPages) {
        paginationHTML += `<button onclick="changePage(${state.pagination.page + 1})" class="px-3 py-1 text-sm border rounded-lg hover:bg-gray-50">Next</button>`;
    }
    
    controls.innerHTML = paginationHTML;
}

function changePage(page) {
    state.pagination.page = page;
    loadNotes();
}

function showValidationErrors(errors) {
    // Clear previous errors
    clearErrors();
    
    // Show field-specific errors
    Object.keys(errors).forEach(field => {
        const errorElement = document.getElementById(`${field}-error`);
        if (errorElement) {
            errorElement.textContent = errors[field];
            errorElement.classList.remove('hidden');
        }
    });
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(element => {
        element.classList.add('hidden');
        element.textContent = '';
    });
}

function showSuccess(message) {
    // You can implement a toast notification system here
    alert(message); // Temporary implementation
}

function showError(message) {
    // You can implement a toast notification system here
    alert('Error: ' + message); // Temporary implementation
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../views/layouts/base.php';
?>p>