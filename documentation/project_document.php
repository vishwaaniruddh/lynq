<?php
/**
 * Project Documentation Page
 * Location: /documentation/project_document.php
 * Supported pages: -login, etc.
 * Uses AJAX to fetch section content dynamically.
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
$isLoggedIn = $sessionService->isLoggedIn();
$currentUser = $isLoggedIn ? $sessionService->getCurrentUser() : null;
$baseUrl = '..';
$pageTitle = 'Project Documentation';
$currentPage = 'project_document';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Documentation', 'url' => '#'],
    ['label' => 'Project Document']
];

ob_start();
?>

<style>
.doc-container {
    display: flex;
    gap: 1.5rem;
    min-height: calc(100vh - 140px);
}
.doc-nav {
    width: 260px;
    flex-shrink: 0;
}
.doc-main {
    flex-grow: 1;
    min-width: 0;
}
.nav-card {
    background: white;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    overflow: hidden;
    position: sticky;
    top: 80px;
}
.nav-header {
    padding: 1rem 1.25rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}
.nav-link-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    color: #4b5563;
    font-size: 0.875rem;
    font-weight: 500;
    border-left: 3px solid transparent;
    transition: all 0.2s;
    cursor: pointer;
}
.nav-link-item:hover {
    background: #f3f4f6;
    color: #1f2937;
}
.nav-link-item.active {
    background: #eef2ff;
    color: #4f46e5;
    border-left-color: #4f46e5;
}
.nav-link-item i {
    width: 1.5rem;
    font-size: 1rem;
}
.content-card {
    background: white;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
}
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1rem;
    color: #6b7280;
}
.spinner-icon {
    width: 2.5rem;
    height: 2.5rem;
    border: 4px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 1rem;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="doc-container">
    <!-- Left Sidebar Navigation -->
    <aside class="doc-nav">
        <div class="nav-card">
            <div class="nav-header">
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center">
                    <i class="fas fa-book-reader text-indigo-600 mr-2"></i>Project Modules
                </h3>
            </div>
            <div class="py-2" id="docNavList">
                <!-- Supported Pages -->
                <div class="nav-link-item active" data-page="login" onclick="loadDocPage('login')">
                    <i class="fas fa-key text-indigo-500"></i>
                    <span>-login (Authentication)</span>
                </div>
                <div class="nav-link-item" data-page="users" onclick="loadDocPage('users')">
                    <i class="fas fa-users text-blue-500"></i>
                    <span>-users (User Management)</span>
                </div>
                <div class="nav-link-item" data-page="dashboard" onclick="loadDocPage('dashboard')">
                    <i class="fas fa-chart-line text-emerald-500"></i>
                    <span>-dashboard (Overview)</span>
                </div>
                <div class="nav-link-item" data-page="masters" onclick="loadDocPage('masters')">
                    <i class="fas fa-database text-purple-500"></i>
                    <span>-masters (Master Data)</span>
                </div>
                <div class="nav-link-item" data-page="sites" onclick="loadDocPage('sites')">
                    <i class="fas fa-map-marker-alt text-amber-500"></i>
                    <span>-sites (Site Management API & Page)</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Display Area -->
    <main class="doc-main">
        <div class="content-card">
            <!-- Header Bar -->
            <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-200">
                <div>
                    <h1 id="pageHeading" class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                        <i class="fas fa-key text-indigo-600"></i>Login & Authentication API
                    </h1>
                    <p class="text-xs text-gray-500 mt-1">Loaded dynamically via AJAX content request</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="reloadCurrentPage()" class="px-3 py-1.5 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg text-xs font-medium transition flex items-center">
                        <i class="fas fa-sync-alt mr-1.5"></i>Reload AJAX
                    </button>
                    <button onclick="window.print()" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-medium transition flex items-center">
                        <i class="fas fa-print mr-1.5"></i>Print
                    </button>
                </div>
            </div>

            <!-- AJAX Container -->
            <div id="ajaxContentArea">
                <div class="loading-spinner">
                    <div class="spinner-icon"></div>
                    <span>Fetching documentation content...</span>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
let currentPageId = 'login';
const loadedCache = new Map();

document.addEventListener('DOMContentLoaded', function() {
    loadDocPage('login');
});

async function loadDocPage(pageId) {
    currentPageId = pageId;

    // Highlight active nav item
    document.querySelectorAll('.nav-link-item').forEach(item => {
        item.classList.toggle('active', item.dataset.page === pageId);
    });

    const contentArea = document.getElementById('ajaxContentArea');
    const pageHeading = document.getElementById('pageHeading');

    // Show loading spinner during AJAX fetch
    contentArea.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner-icon"></div>
            <span>Fetching content via AJAX for <strong>-${pageId}</strong>...</span>
        </div>
    `;

    try {
        let data = loadedCache.get(pageId);
        if (!data) {
            // Call AJAX endpoint to fetch section content
            const response = await fetch(`../api/documentation/sections.php?section=${pageId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Failed to load section data');
            }
            data = result.data;
            loadedCache.set(pageId, data);
        }

        // Update page header icon and title
        pageHeading.innerHTML = `<i class="${data.icon || 'fas fa-file-alt'} text-indigo-600"></i>${data.title || pageId}`;

        // Render fetched HTML content
        contentArea.innerHTML = data.content;

    } catch (error) {
        console.error('AJAX Fetch Error:', error);
        contentArea.innerHTML = `
            <div class="p-6 bg-red-50 border border-red-200 rounded-xl text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
                <h3 class="text-base font-bold text-red-800 mb-1">Failed to load content</h3>
                <p class="text-xs text-red-600 mb-4">${escapeHtml(error.message)}</p>
                <button onclick="reloadCurrentPage()" class="px-4 py-2 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-redo mr-1.5"></i>Try Again
                </button>
            </div>
        `;
    }
}

function reloadCurrentPage() {
    loadedCache.delete(currentPageId);
    loadDocPage(currentPageId);
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
