<?php
/**
 * ADV User Documentation Page (API-Based)
 * Comprehensive in-app documentation for ADV CRM users
 * Uses API-based content loading with sidebar navigation
 * 
 * Requirements: 1.3, 2.1 - ADV-only access and page structure
 * **Feature: adv-user-documentation, Property 1: ADV-Only Icon Visibility**
 */

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// ADV-only access control - redirect non-ADV users
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'ADV Documentation is only available to ADV users';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'ADV User Documentation';
$currentPage = 'documentation';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'ADV Documentation']
];

ob_start();
?>

<style>
/* Documentation Layout - Fixed Sidebar + Content */
.doc-layout {
    display: flex;
    min-height: calc(100vh - 140px);
    gap: 0;
}

/* Fixed Sidebar */
.doc-sidebar {
    width: 280px;
    min-width: 280px;
    background: white;
    border-right: 1px solid #e5e7eb;
    position: sticky;
    top: 80px;
    height: calc(100vh - 100px);
    overflow-y: auto;
    padding: 1.5rem 0;
}

.doc-sidebar::-webkit-scrollbar { width: 4px; }
.doc-sidebar::-webkit-scrollbar-track { background: #f1f1f1; }
.doc-sidebar::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
.doc-sidebar::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }

.sidebar-header {
    padding: 0 1.25rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 1rem;
}

.sidebar-header h2 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.sidebar-search {
    position: relative;
}

.sidebar-search input {
    width: 100%;
    padding: 0.5rem 0.75rem 0.5rem 2rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    transition: all 0.2s;
}

.sidebar-search input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

.sidebar-search i {
    position: absolute;
    left: 0.625rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 0.75rem;
}

/* Navigation Items */
.nav-section {
    padding: 0 0.75rem;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 0.625rem 0.75rem;
    margin-bottom: 0.125rem;
    color: #4b5563;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.15s;
    border-left: 3px solid transparent;
}

.nav-item:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.nav-item.active {
    background: #eef2ff;
    color: #4f46e5;
    border-left-color: #4f46e5;
    font-weight: 500;
}

.nav-item i {
    width: 1.25rem;
    margin-right: 0.625rem;
    font-size: 0.8125rem;
    text-align: center;
}

.nav-item-badge {
    margin-left: auto;
    background: #e5e7eb;
    color: #6b7280;
    font-size: 0.6875rem;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
}

/* Main Content Area */
.doc-content {
    flex: 1;
    padding: 1.5rem 2rem;
    max-width: 900px;
    background: #f9fafb;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.content-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.content-header h1 i {
    color: #6366f1;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    padding: 0.5rem 0.75rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    color: #6b7280;
    font-size: 0.8125rem;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-icon:hover {
    background: #f3f4f6;
    color: #1f2937;
}

/* Section Content */
.section-card {
    background: white;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    margin-bottom: 1rem;
    overflow: hidden;
}

.section-card-header {
    padding: 1rem 1.25rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-card-header h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}

.section-card-body {
    padding: 1.25rem;
}

/* Feature Items */
.feature-item {
    padding: 1rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
    border-left: 3px solid #6366f1;
}

.feature-item:last-child { margin-bottom: 0; }

.feature-item h3 {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.role-badges { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-bottom: 0.5rem; }
.badge { display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; font-size: 0.6875rem; font-weight: 500; border-radius: 0.375rem; }
.badge-superadmin { background: #fef3c7; color: #92400e; }
.badge-admin { background: #dbeafe; color: #1e40af; }
.badge-manager { background: #d1fae5; color: #065f46; }
.badge-engineer { background: #e0e7ff; color: #3730a3; }

.permission-ref {
    font-size: 0.75rem;
    color: #6b7280;
    font-family: monospace;
    background: #f3f4f6;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    display: inline-block;
    margin-bottom: 0.5rem;
}

.description { font-size: 0.875rem; color: #4b5563; line-height: 1.6; }

/* Tables */
.role-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.role-table th, .role-table td { padding: 0.625rem 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
.role-table th { background: #f9fafb; font-weight: 600; color: #374151; }
.role-table td { color: #4b5563; }

/* Loading & Error States */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #6b7280;
}

.spinner {
    width: 2rem;
    height: 2rem;
    border: 3px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 0.75rem;
}

@keyframes spin { to { transform: rotate(360deg); } }

.error-state {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 0.5rem;
    padding: 1rem;
    color: #991b1b;
    text-align: center;
}

.highlight { background: #fef08a; padding: 0.125rem 0.25rem; border-radius: 0.25rem; }

/* Mobile Responsive */
@media (max-width: 768px) {
    .doc-layout { flex-direction: column; }
    .doc-sidebar {
        width: 100%;
        min-width: 100%;
        position: relative;
        top: 0;
        height: auto;
        max-height: 300px;
        border-right: none;
        border-bottom: 1px solid #e5e7eb;
    }
    .doc-content { padding: 1rem; }
}

/* Print Styles */
@media print {
    .doc-sidebar, .header-actions { display: none !important; }
    .doc-content { max-width: 100%; padding: 0; }
    .section-card { break-inside: avoid; }
}
</style>

<div class="doc-layout">
    <!-- Fixed Sidebar Navigation -->
    <aside class="doc-sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-book mr-2"></i>Documentation</h2>
            <div class="sidebar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="docSearch" placeholder="Search..." onkeyup="docManager.filterSections(this.value)">
            </div>
        </div>
        <nav class="nav-section" id="navList">
            <div class="loading-state">
                <div class="spinner"></div>
                <span>Loading...</span>
            </div>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="doc-content">
        <div class="content-header">
            <h1 id="contentTitle"><i class="fas fa-book-open"></i>Select a Section</h1>
            <div class="header-actions">
                <button class="btn-icon" onclick="docManager.expandAll()" title="Expand All">
                    <i class="fas fa-expand-alt"></i>
                </button>
                <button class="btn-icon" onclick="window.print()" title="Print">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>
        <div id="contentArea">
            <div class="section-card">
                <div class="section-card-body">
                    <p class="description" style="text-align: center; padding: 2rem;">
                        <i class="fas fa-hand-point-left" style="font-size: 2rem; color: #6366f1; display: block; margin-bottom: 1rem;"></i>
                        Select a section from the sidebar to view documentation
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
class DocumentationManager {
    constructor() {
        this.sections = [];
        this.loadedSections = new Map();
        this.activeSection = null;
        this.init();
    }

    async init() {
        try {
            await this.loadSectionsList();
            this.renderNavigation();
            // Auto-load first section
            if (this.sections.length > 0) {
                this.loadSection(this.sections[0].id);
            }
        } catch (error) {
            console.error('Failed to initialize:', error);
            document.getElementById('navList').innerHTML = '<div class="error-state">Failed to load navigation</div>';
        }
    }

    async loadSectionsList() {
        const response = await fetch('../api/documentation/sections.php');
        const result = await response.json();
        if (!result.success) throw new Error(result.error);
        this.sections = result.data;
    }

    renderNavigation() {
        const navList = document.getElementById('navList');
        navList.innerHTML = this.sections.map(section => `
            <div class="nav-item" data-section="${section.id}" onclick="docManager.loadSection('${section.id}')">
                <i class="${section.icon}"></i>
                <span>${section.title}</span>
            </div>
        `).join('');
    }

    async loadSection(sectionId) {
        // Update active state
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.section === sectionId);
        });
        this.activeSection = sectionId;

        const section = this.sections.find(s => s.id === sectionId);
        const contentArea = document.getElementById('contentArea');
        const contentTitle = document.getElementById('contentTitle');

        // Update header
        contentTitle.innerHTML = `<i class="${section.icon}"></i>${section.title}`;

        // Show loading
        contentArea.innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Loading content...</span></div>';

        try {
            let sectionData = this.loadedSections.get(sectionId);
            if (!sectionData) {
                const response = await fetch(`../api/documentation/sections.php?section=${sectionId}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.error);
                sectionData = result.data;
                this.loadedSections.set(sectionId, sectionData);
            }

            contentArea.innerHTML = `<div class="section-card"><div class="section-card-body">${sectionData.content}</div></div>`;
        } catch (error) {
            contentArea.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i> Failed to load section content</div>`;
        }
    }

    filterSections(query) {
        const searchTerm = query.toLowerCase().trim();
        document.querySelectorAll('.nav-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = !searchTerm || text.includes(searchTerm) ? '' : 'none';
        });
    }

    expandAll() {
        document.querySelectorAll('.section-card-body').forEach(body => {
            body.style.display = 'block';
        });
    }
}

let docManager;
document.addEventListener('DOMContentLoaded', () => {
    docManager = new DocumentationManager();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
