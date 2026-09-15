<?php
/**
 * Forms Master Management & Dynamic Form Builder Page
 * 
 * Features:
 * - Guided 2-step Creation Wizard: Step 1 asks Purpose & Target Project (e.g. XTPL), Step 2 opens the Visual Field Designer
 * - Project mapping (XTPL, Global/Default)
 * - Flexible field types: text, number, email, textarea, select, radio, checkbox, file (with custom extensions), date, datetime, heading
 * - Configurable field validations, column widths, section headers
 * - Purpose-specific starter templates (Site Add, Feasibility, Installation)
 * - Live interactive form preview renderer
 * - Form cloning/duplication and status management
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../middleware/MasterModuleMiddleware.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$masterMiddleware = new MasterModuleMiddleware();
$user = $masterMiddleware->requireViewPermission('forms');
if (!$user) {
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Forms Master';
$currentPage = 'masters_forms';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Master Data'],
    ['label' => 'Forms Master']
];

$permissions = $masterMiddleware->getUserModulePermissions('forms');

ob_start();
?>

<div class="space-y-6">
    <!-- Header Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-2.5 bg-indigo-50 text-indigo-600 rounded-lg text-lg">
                        <i class="fas fa-wpforms"></i>
                    </span>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Forms Master</h2>
                        <p class="text-xs text-gray-500">Design project-specific custom forms for Site Add, Feasibility, Installation & More</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <?php if ($permissions['create']): ?>
                <button onclick="openCreateWizard()" class="px-4 py-2.5 bg-primary text-white rounded-lg hover:bg-indigo-700 transition flex items-center shadow-sm font-medium text-sm">
                    <i class="fas fa-plus mr-2"></i>Create New Form
                </button>
                <?php endif; ?>
                <button onclick="loadForms()" class="px-3.5 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="mt-6 pt-5 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text" id="search-input" placeholder="Search by form name, code or project..." 
                        class="w-full pl-9 pr-4 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Purpose / Module</label>
                <select id="purpose-filter" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Purposes</option>
                    <option value="site_add">Site Add Form</option>
                    <option value="feasibility">Feasibility Form</option>
                    <option value="installation">Installation Form</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Target Project</label>
                <select id="project-filter" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Projects</option>
                    <option value="global">Global / Default Template</option>
                    <!-- Populated dynamically with projects like XTPL -->
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                <select id="status-filter" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div id="loading-indicator" class="hidden p-12 text-center">
            <i class="fas fa-spinner fa-spin text-3xl text-primary mb-3"></i>
            <p class="text-xs text-gray-500">Loading custom forms...</p>
        </div>

        <div id="table-container" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider w-12">#</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider">Form Name & Code</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider">Purpose</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider">Project</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider text-center">Fields</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider">Updated</th>
                        <th class="px-4 py-3 font-semibold text-gray-600 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="forms-table-body" class="divide-y divide-gray-100">
                    <!-- Populated via AJAX -->
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-50 text-indigo-500 mb-4">
                <i class="fas fa-wpforms text-2xl"></i>
            </div>
            <h3 class="text-sm font-bold text-gray-800 mb-1">No custom forms found</h3>
            <p class="text-xs text-gray-500 max-w-sm mx-auto mb-4">Create dynamic forms with custom fields tailored for each project and purpose.</p>
            <?php if ($permissions['create']): ?>
            <button onclick="openCreateWizard()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-indigo-700 transition text-xs font-medium inline-flex items-center">
                <i class="fas fa-plus mr-1.5"></i>Create Form
            </button>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="p-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600">
            <div id="pagination-info">Showing 0 to 0 of 0 entries</div>
            <div class="flex items-center space-x-1" id="pagination-buttons"></div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- STEP 1: CREATE NEW FORM WIZARD (ASKS PURPOSE & PROJECT)  -->
<!-- ======================================================== -->
<div id="wizard-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeCreateWizard()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100">
            
            <!-- Wizard Header -->
            <div class="bg-gradient-to-r from-indigo-700 via-indigo-800 to-purple-800 text-white px-6 py-5 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="p-2.5 bg-white/10 rounded-xl text-indigo-200">
                        <i class="fas fa-plus-circle text-xl"></i>
                    </span>
                    <div>
                        <h3 class="text-base font-bold">Create New Custom Form</h3>
                        <p class="text-xs text-indigo-200">Step 1: Select Purpose & Target Project</p>
                    </div>
                </div>
                <button type="button" onclick="closeCreateWizard()" class="text-white/70 hover:text-white transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <!-- Wizard Body -->
            <div class="p-6 space-y-5 text-xs">
                
                <!-- 1. Purpose Selector Cards -->
                <div>
                    <label class="block font-bold text-gray-800 mb-2">
                        1. Select Form Purpose <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="cursor-pointer border-2 border-indigo-500 bg-indigo-50/40 rounded-xl p-3.5 flex items-start space-x-3 transition-all hover:shadow-sm" id="card-purpose-site_add">
                            <input type="radio" name="wizard-purpose" value="site_add" checked onchange="onWizardPurposeChange('site_add')" class="mt-0.5 text-primary focus:ring-primary">
                            <div>
                                <div class="font-bold text-gray-800 flex items-center gap-1.5">
                                    <i class="fas fa-map-marker-alt text-blue-600"></i> Site Add Form
                                </div>
                                <p class="text-[11px] text-gray-500 mt-0.5">Fields for adding & onboarding ATM sites</p>
                            </div>
                        </label>

                        <label class="cursor-pointer border-2 border-gray-200 bg-white rounded-xl p-3.5 flex items-start space-x-3 transition-all hover:border-indigo-300 hover:shadow-sm" id="card-purpose-feasibility">
                            <input type="radio" name="wizard-purpose" value="feasibility" onchange="onWizardPurposeChange('feasibility')" class="mt-0.5 text-primary focus:ring-primary">
                            <div>
                                <div class="font-bold text-gray-800 flex items-center gap-1.5">
                                    <i class="fas fa-clipboard-check text-purple-600"></i> Feasibility Form
                                </div>
                                <p class="text-[11px] text-gray-500 mt-0.5">Earthing, UPS, operator signal & survey checks</p>
                            </div>
                        </label>

                        <label class="cursor-pointer border-2 border-gray-200 bg-white rounded-xl p-3.5 flex items-start space-x-3 transition-all hover:border-indigo-300 hover:shadow-sm" id="card-purpose-installation">
                            <input type="radio" name="wizard-purpose" value="installation" onchange="onWizardPurposeChange('installation')" class="mt-0.5 text-primary focus:ring-primary">
                            <div>
                                <div class="font-bold text-gray-800 flex items-center gap-1.5">
                                    <i class="fas fa-tools text-emerald-600"></i> Installation Form
                                </div>
                                <p class="text-[11px] text-gray-500 mt-0.5">Router mounting, SIM testing & bank signoff</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 2. Project Selector -->
                <div>
                    <label class="block font-bold text-gray-800 mb-1">
                        2. Select Target Project <span class="text-red-500">*</span>
                    </label>
                    <select id="wizard-project-id" onchange="onWizardProjectChange()" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-primary font-medium">
                        <option value="">-- Select Project or Global --</option>
                        <option value="0">Global / Default Template (Applies to all projects without a specific form)</option>
                        <!-- Populated with XTPL, etc. -->
                    </select>
                    <p class="text-[11px] text-gray-500 mt-1">
                        Select a project (e.g. <span class="font-semibold text-indigo-600">XTPL</span>) to create project-specific dynamic forms.
                    </p>
                </div>

                <!-- 3. Form Name & Starting Template -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <div>
                        <label class="block font-bold text-gray-800 mb-1">Form Name <span class="text-red-500">*</span></label>
                        <input type="text" id="wizard-form-name" placeholder="e.g. XTPL Feasibility Survey Form" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-800 mb-1">Starter Template</label>
                        <select id="wizard-template" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-primary">
                            <option value="recommended">Recommended Standard Fields (Fast Start)</option>
                            <option value="blank">Blank Form (Add all fields manually)</option>
                        </select>
                    </div>
                </div>

            </div>

            <!-- Wizard Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <button type="button" onclick="closeCreateWizard()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-200 rounded-lg transition">
                    Cancel
                </button>
                <button type="button" onclick="proceedToBuilderFromWizard()" class="px-5 py-2 text-xs font-bold text-white bg-primary hover:bg-indigo-700 rounded-lg transition shadow-md flex items-center">
                    Proceed to Form Designer <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- STEP 2: FORM BUILDER & FIELD DESIGNER MODAL -->
<!-- ======================================================== -->
<div id="builder-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeFormBuilder()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full border border-gray-100">
            
            <!-- Modal Header -->
            <div class="bg-gray-900 text-white px-6 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="p-2 bg-indigo-600/50 rounded-lg text-indigo-300">
                        <i class="fas fa-drafting-compass"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold" id="builder-modal-title">Custom Form Builder</h3>
                            <span id="builder-purpose-badge" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/30 text-indigo-300 border border-indigo-400/30">Purpose</span>
                            <span id="builder-project-badge" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-500/30 text-purple-300 border border-purple-400/30">Project</span>
                        </div>
                        <p class="text-xs text-gray-400">Configure form fields, validations, layout & options</p>
                    </div>
                </div>
                <button type="button" onclick="closeFormBuilder()" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 max-h-[75vh] overflow-y-auto space-y-6">
                
                <!-- Form Properties Summary / Editor -->
                <div class="bg-gray-50/90 rounded-xl p-4 border border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 bg-primary text-white rounded-full inline-flex items-center justify-center text-[10px]">1</span>
                            Form Header Details
                        </h4>
                        <span class="text-[11px] text-gray-500">You can adjust purpose and project anytime</span>
                    </div>
                    <input type="hidden" id="form-id" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
                        <div class="md:col-span-2">
                            <label class="block font-semibold text-gray-700 mb-1">Form Name <span class="text-red-500">*</span></label>
                            <input type="text" id="form-name" placeholder="e.g. XTPL Feasibility Check Form" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent font-medium">
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Purpose / Module <span class="text-red-500">*</span></label>
                            <select id="form-purpose" onchange="updateHeaderBadges()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="site_add">Site Add Form</option>
                                <option value="feasibility">Feasibility Form</option>
                                <option value="installation">Installation Form</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Target Project <span class="text-red-500">*</span></label>
                            <select id="form-project-id" onchange="updateHeaderBadges()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="">Global / Default (All Projects)</option>
                                <!-- Populated dynamically with projects like XTPL -->
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Form Code</label>
                            <input type="text" id="form-code" placeholder="Auto-generated" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary uppercase text-[11px] font-mono">
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Status</label>
                            <select id="form-status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-semibold text-gray-700 mb-1">Description</label>
                            <input type="text" id="form-desc" placeholder="Brief note on form usage..." 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>

                <!-- Field Designer -->
                <div class="space-y-4">
                    <!-- Sticky Section Header with + Add Field -->
                    <div class="sticky top-0 z-30 bg-white/95 backdrop-blur-md py-3 px-4 -mx-4 rounded-xl border-y border-indigo-100/80 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 transition-all">
                        <div>
                            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-5 h-5 bg-indigo-600 text-white rounded-full inline-flex items-center justify-center text-[10px] shadow-sm">2</span>
                                Configured Fields (<span id="fields-count-badge" class="font-extrabold text-indigo-600">0</span>)
                            </h4>
                            <p class="text-[11px] text-gray-500">Configure text, number, select, radio, checkbox, and file upload fields with custom constraints.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="addNewField()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition text-xs font-bold flex items-center shadow-md hover:shadow-lg transform active:scale-95">
                                <i class="fas fa-plus mr-1.5"></i>Add Field
                            </button>
                            <button type="button" onclick="previewCurrentForm()" class="px-3.5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg transition text-xs font-bold flex items-center shadow-sm hover:shadow">
                                <i class="fas fa-eye mr-1.5"></i>Live Preview
                            </button>
                        </div>
                    </div>

                    <!-- Fields Container -->
                    <div id="fields-container" class="space-y-3 min-h-[150px]">
                        <!-- Rendered dynamically -->
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <button type="button" onclick="previewCurrentForm()" class="px-4 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition flex items-center">
                    <i class="fas fa-play mr-1.5"></i>Test Preview
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeFormBuilder()" class="px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 rounded-lg transition">
                        Cancel
                    </button>
                    <button type="button" onclick="saveCustomForm()" id="btn-save-form" class="px-5 py-2 text-xs font-semibold text-white bg-primary hover:bg-indigo-700 rounded-lg transition shadow-md flex items-center">
                        <i class="fas fa-save mr-1.5"></i>Save Form
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- LIVE PREVIEW MODAL -->
<!-- ========================================== -->
<div id="preview-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closePreviewModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100">
            
            <div class="bg-gradient-to-r from-indigo-700 to-purple-700 text-white px-6 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="p-2 bg-white/20 rounded-lg">
                        <i class="fas fa-eye"></i>
                    </span>
                    <div>
                        <h3 class="text-base font-bold" id="preview-modal-title">Live Form Preview</h3>
                        <p class="text-xs text-indigo-200" id="preview-modal-subtitle">Interactive simulation</p>
                    </div>
                </div>
                <button type="button" onclick="closePreviewModal()" class="text-white/80 hover:text-white transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="p-6 max-h-[75vh] overflow-y-auto space-y-6 bg-gray-50/50">
                <form id="interactive-preview-form" onsubmit="handlePreviewSubmit(event)" class="space-y-6">
                    <div id="preview-sections-container" class="space-y-6">
                        <!-- Dynamic preview inputs rendered here -->
                    </div>

                    <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                        <button type="button" onclick="closePreviewModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Close Preview
                        </button>
                        <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-primary hover:bg-indigo-700 rounded-lg shadow-sm">
                            <i class="fas fa-check-circle mr-1.5"></i>Test Validation & Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- DUPLICATE MODAL -->
<!-- ========================================== -->
<div id="duplicate-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" onclick="closeDuplicateModal()"></div>
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 relative z-10 border border-gray-100 text-xs">
            <h3 class="text-base font-bold text-gray-800 mb-1 flex items-center gap-2">
                <i class="fas fa-clone text-indigo-600"></i>Duplicate Custom Form
            </h3>
            <p class="text-gray-500 mb-4">Create a new copy of this form tailored for another project or purpose.</p>
            <input type="hidden" id="duplicate-source-id">
            
            <div class="space-y-3">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">New Form Name <span class="text-red-500">*</span></label>
                    <input type="text" id="duplicate-form-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Target Project</label>
                    <select id="duplicate-project-id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">Global / Default</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Purpose</label>
                    <select id="duplicate-purpose" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="site_add">Site Add Form</option>
                        <option value="feasibility">Feasibility Form</option>
                        <option value="installation">Installation Form</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeDuplicateModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="submitDuplicateForm()" class="px-4 py-2 bg-primary text-white rounded-lg font-semibold hover:bg-indigo-700 shadow-sm">Clone Form</button>
            </div>
        </div>
    </div>
</div>

<script>
// State Management
let currentForms = [];
let projectOptions = [];
let masterSources = {};
let purposeMap = {
    'site_add': 'Site Add',
    'feasibility': 'Feasibility',
    'installation': 'Installation'
};
let fieldTypesMap = {
    'text': { label: 'Text', icon: 'fa-font', color: 'blue' },
    'number': { label: 'Number', icon: 'fa-hashtag', color: 'emerald' },
    'phone': { label: 'Contact / Phone Number', icon: 'fa-phone-alt', color: 'emerald' },
    'email': { label: 'Email', icon: 'fa-envelope', color: 'cyan' },
    'textarea': { label: 'Textarea', icon: 'fa-align-left', color: 'indigo' },
    'select': { label: 'Dropdown', icon: 'fa-list-ul', color: 'purple' },
    'radio': { label: 'Radio Buttons', icon: 'fa-dot-circle', color: 'amber' },
    'checkbox': { label: 'Checkboxes', icon: 'fa-check-square', color: 'pink' },
    'file': { label: 'File / Photo', icon: 'fa-cloud-upload-alt', color: 'orange' },
    'date': { label: 'Date', icon: 'fa-calendar-alt', color: 'teal' },
    'datetime': { label: 'Date & Time', icon: 'fa-clock', color: 'sky' },
    'heading': { label: 'Section Header', icon: 'fa-heading', color: 'gray' }
};

let currentFormFields = [];
let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', () => {
    loadLookups();
    loadForms();

    // Filters event listeners
    document.getElementById('search-input').addEventListener('input', debounce(() => { currentPage = 1; loadForms(); }, 400));
    document.getElementById('purpose-filter').addEventListener('change', () => { currentPage = 1; loadForms(); });
    document.getElementById('project-filter').addEventListener('change', () => { currentPage = 1; loadForms(); });
    document.getElementById('status-filter').addEventListener('change', () => { currentPage = 1; loadForms(); });
});

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Load dropdown lookups
async function loadLookups() {
    try {
        const res = await fetch('../api/forms-master/form_options.php');
        const data = await res.json();
        if (data.success && data.data) {
            projectOptions = data.data.projects || [];
            masterSources = data.data.master_sources || {};
            
            // Populate project filters & wizards
            const projFilter = document.getElementById('project-filter');
            const formProj = document.getElementById('form-project-id');
            const dupProj = document.getElementById('duplicate-project-id');
            const wizProj = document.getElementById('wizard-project-id');

            projectOptions.forEach(p => {
                const displayName = p.code ? `${p.name} (${p.code})` : p.name;
                projFilter.add(new Option(displayName, p.id));
                formProj.add(new Option(displayName, p.id));
                dupProj.add(new Option(displayName, p.id));
                wizProj.add(new Option(displayName, p.id));
            });
        }
    } catch (e) {
        console.error('Failed to load lookups', e);
    }
}

// Load Forms list
async function loadForms() {
    const loading = document.getElementById('loading-indicator');
    const tbody = document.getElementById('forms-table-body');
    const emptyState = document.getElementById('empty-state');
    const tableContainer = document.getElementById('table-container');

    loading.classList.remove('hidden');
    tbody.innerHTML = '';
    emptyState.classList.add('hidden');

    const search = document.getElementById('search-input').value;
    const purpose = document.getElementById('purpose-filter').value;
    const project_id = document.getElementById('project-filter').value;
    const status = document.getElementById('status-filter').value;

    const params = new URLSearchParams({
        page: currentPage,
        limit: 10,
        search: search || '',
        purpose: purpose || '',
        project_id: project_id || '',
        status: status || ''
    });

    try {
        const res = await fetch(`../api/forms-master/list.php?${params.toString()}`);
        const result = await res.json();

        loading.classList.add('hidden');

        if (result.success && result.data) {
            currentForms = result.data.data || [];
            const total = result.data.total || 0;
            totalPages = result.data.totalPages || 1;

            if (currentForms.length === 0) {
                emptyState.classList.remove('hidden');
                tableContainer.classList.add('hidden');
                document.getElementById('pagination-container').classList.add('hidden');
                return;
            }

            tableContainer.classList.remove('hidden');
            document.getElementById('pagination-container').classList.remove('hidden');

            renderFormsTable(currentForms);
            renderPagination(result.data);
        } else {
            showToast('Failed to load forms', 'error');
        }
    } catch (e) {
        loading.classList.add('hidden');
        console.error('Error fetching forms', e);
        showToast('Error loading forms', 'error');
    }
}

function renderFormsTable(forms) {
    const tbody = document.getElementById('forms-table-body');
    tbody.innerHTML = '';

    forms.forEach((form, idx) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50/80 transition-colors';

        const purposeBadgeClass = {
            'site_add': 'bg-blue-50 text-blue-700 border-blue-200',
            'feasibility': 'bg-purple-50 text-purple-700 border-purple-200',
            'installation': 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'other': 'bg-gray-100 text-gray-700 border-gray-200'
        }[form.purpose] || 'bg-gray-100 text-gray-700';

        const statusBadgeClass = {
            'active': 'bg-green-50 text-green-700 border-green-200',
            'inactive': 'bg-red-50 text-red-700 border-red-200',
            'draft': 'bg-amber-50 text-amber-700 border-amber-200'
        }[form.status] || 'bg-gray-100 text-gray-700';

        const isGlobal = !form.project_id || form.project_id === 0;

        tr.innerHTML = `
            <td class="px-4 py-3 text-gray-400 font-mono">${(currentPage - 1) * 10 + idx + 1}</td>
            <td class="px-4 py-3">
                <div class="font-bold text-gray-800 text-xs">${escapeHtml(form.form_name)}</div>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-[10px] font-mono text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">${escapeHtml(form.form_code)}</span>
                    <span class="text-[10px] text-gray-400">v${form.version || 1}</span>
                </div>
            </td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border ${purposeBadgeClass}">
                    ${purposeMap[form.purpose] || form.purpose}
                </span>
            </td>
            <td class="px-4 py-3">
                ${isGlobal 
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-700 border border-gray-200"><i class="fas fa-globe text-[9px] mr-1 text-gray-400"></i>Global / Default</span>`
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200"><i class="fas fa-project-diagram text-[9px] mr-1 text-indigo-500"></i>${escapeHtml(form.project_name)}</span>`
                }
            </td>
            <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50/60 text-indigo-700">
                    <i class="fas fa-layer-group text-[9px] mr-1"></i>${form.field_count || 0} fields
                </span>
            </td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border ${statusBadgeClass}">
                    ${form.status.toUpperCase()}
                </span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-[11px]">
                ${form.updated_at ? form.updated_at.substring(0, 10) : '-'}
            </td>
            <td class="px-4 py-3 text-right space-x-1 whitespace-nowrap">
                <button onclick="previewFormById(${form.id})" class="p-1.5 text-teal-600 hover:bg-teal-50 rounded transition" title="Live Preview">
                    <i class="fas fa-eye"></i>
                </button>
                <button onclick="editForm(${form.id})" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded transition" title="Edit Form Schema">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="openDuplicateModal(${form.id}, '${escapeHtml(form.form_name)}', '${form.purpose}', '${form.project_id || ''}')" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded transition" title="Clone / Duplicate Form">
                    <i class="fas fa-clone"></i>
                </button>
                <button onclick="deleteForm(${form.id})" class="p-1.5 text-red-600 hover:bg-red-50 rounded transition" title="Delete Form">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function renderPagination(meta) {
    const info = document.getElementById('pagination-info');
    const buttons = document.getElementById('pagination-buttons');
    
    const from = meta.total > 0 ? (meta.page - 1) * meta.limit + 1 : 0;
    const to = Math.min(meta.page * meta.limit, meta.total);
    info.textContent = `Showing ${from} to ${to} of ${meta.total} entries`;

    buttons.innerHTML = '';
    if (meta.totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.className = `px-2.5 py-1 rounded border ${meta.page > 1 ? 'hover:bg-gray-100 text-gray-700' : 'text-gray-300 cursor-not-allowed'}`;
    prevBtn.innerHTML = '<i class="fas fa-chevron-left text-[10px]"></i>';
    prevBtn.disabled = meta.page <= 1;
    prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; loadForms(); } };
    buttons.appendChild(prevBtn);

    for (let i = 1; i <= meta.totalPages; i++) {
        if (i === 1 || i === meta.totalPages || (i >= meta.page - 1 && i <= meta.page + 1)) {
            const btn = document.createElement('button');
            btn.className = `px-2.5 py-1 rounded text-xs font-medium border ${i === meta.page ? 'bg-primary text-white border-primary' : 'hover:bg-gray-100 text-gray-700'}`;
            btn.textContent = i;
            btn.onclick = () => { currentPage = i; loadForms(); };
            buttons.appendChild(btn);
        } else if (i === meta.page - 2 || i === meta.page + 2) {
            const span = document.createElement('span');
            span.className = 'px-1 text-gray-400';
            span.textContent = '...';
            buttons.appendChild(span);
        }
    }

    const nextBtn = document.createElement('button');
    nextBtn.className = `px-2.5 py-1 rounded border ${meta.page < meta.totalPages ? 'hover:bg-gray-100 text-gray-700' : 'text-gray-300 cursor-not-allowed'}`;
    nextBtn.innerHTML = '<i class="fas fa-chevron-right text-[10px]"></i>';
    nextBtn.disabled = meta.page >= meta.totalPages;
    nextBtn.onclick = () => { if (currentPage < meta.totalPages) { currentPage++; loadForms(); } };
    buttons.appendChild(nextBtn);
}

// ========================================================
// STEP 1 WIZARD LOGIC (PURPOSE & PROJECT SELECTION)
// ========================================================

function openCreateWizard() {
    // Reset wizard
    const purposeRadios = document.querySelectorAll('input[name="wizard-purpose"]');
    purposeRadios.forEach(r => {
        r.checked = r.value === 'site_add';
    });
    onWizardPurposeChange('site_add');
    
    document.getElementById('wizard-project-id').value = '';
    document.getElementById('wizard-form-name').value = '';
    document.getElementById('wizard-template').value = 'recommended';
    
    updateSuggestedFormName();
    document.getElementById('wizard-modal').classList.remove('hidden');
}

function closeCreateWizard() {
    document.getElementById('wizard-modal').classList.add('hidden');
}

function onWizardPurposeChange(purpose) {
    ['site_add', 'feasibility', 'installation'].forEach(p => {
        const card = document.getElementById(`card-purpose-${p}`);
        if (card) {
            if (p === purpose) {
                card.className = 'cursor-pointer border-2 border-indigo-500 bg-indigo-50/40 rounded-xl p-3.5 flex items-start space-x-3 transition-all shadow-sm';
            } else {
                card.className = 'cursor-pointer border-2 border-gray-200 bg-white rounded-xl p-3.5 flex items-start space-x-3 transition-all hover:border-indigo-300 hover:shadow-sm';
            }
        }
    });
    updateSuggestedFormName();
}

function onWizardProjectChange() {
    updateSuggestedFormName();
}

function updateSuggestedFormName() {
    const selectedRadio = document.querySelector('input[name="wizard-purpose"]:checked');
    const purpose = selectedRadio ? selectedRadio.value : 'site_add';
    const projSelect = document.getElementById('wizard-project-id');
    const projId = projSelect.value;
    
    let projName = 'Global';
    if (projId && projId !== '0') {
        projName = projSelect.options[projSelect.selectedIndex].text.split(' (')[0];
    }

    const purposeTitle = {
        'site_add': 'Site Addition Form',
        'feasibility': 'Feasibility Survey Form',
        'installation': 'Installation Checklist Form'
    }[purpose] || 'Custom Form';

    document.getElementById('wizard-form-name').value = `${projName} ${purposeTitle}`;
}

function proceedToBuilderFromWizard() {
    const selectedRadio = document.querySelector('input[name="wizard-purpose"]:checked');
    const purpose = selectedRadio ? selectedRadio.value : 'site_add';
    const projSelect = document.getElementById('wizard-project-id');
    const projectId = projSelect.value;
    const formName = document.getElementById('wizard-form-name').value.trim();
    const templateType = document.getElementById('wizard-template').value;

    if (!formName) {
        showToast('Please enter a Form Name', 'error');
        document.getElementById('wizard-form-name').focus();
        return;
    }

    closeCreateWizard();

    // Open Form Builder preloaded with the selected Purpose & Project
    openFormBuilderWithConfig({
        purpose: purpose,
        project_id: projectId === '0' || projectId === '' ? '' : projectId,
        form_name: formName,
        template: templateType
    });
}

// ========================================================
// STEP 2 FORM BUILDER LOGIC
// ========================================================

function openFormBuilderWithConfig(cfg) {
    document.getElementById('builder-modal-title').textContent = 'Create New Custom Form';
    document.getElementById('form-id').value = '';
    document.getElementById('form-name').value = cfg.form_name || '';
    document.getElementById('form-code').value = '';
    document.getElementById('form-purpose').value = cfg.purpose || 'site_add';
    document.getElementById('form-project-id').value = cfg.project_id || '';
    document.getElementById('form-status').value = 'active';
    document.getElementById('form-desc').value = '';

    updateHeaderBadges();

    currentFormFields = [];

    if (cfg.template === 'recommended') {
        currentFormFields = getRecommendedTemplateFields(cfg.purpose);
    } else {
        // Blank starter with 1 empty text field
        addNewField('General Field 1', 'field_1', 'text', 'General Information', false, 12);
    }

    renderFieldCards();
    document.getElementById('builder-modal').classList.remove('hidden');
}

function updateHeaderBadges() {
    const purpose = document.getElementById('form-purpose').value;
    const projSelect = document.getElementById('form-project-id');
    const projText = projSelect.selectedIndex > 0 ? projSelect.options[projSelect.selectedIndex].text : 'Global / Default';

    document.getElementById('builder-purpose-badge').textContent = `Purpose: ${purposeMap[purpose] || purpose}`;
    document.getElementById('builder-project-badge').textContent = `Project: ${projText}`;
}

function getRecommendedTemplateFields(purpose) {
    switch (purpose) {
        case 'site_add':
            return [
                {
                    section_title: 'Site Basic Info',
                    field_key: 'atm_id',
                    field_label: 'ATM Identifier / Site Code',
                    field_type: 'text',
                    placeholder: 'e.g. ATM_100982',
                    is_required: true,
                    grid_width: 6
                },
                {
                    section_title: 'Site Basic Info',
                    field_key: 'atm_category',
                    field_label: 'ATM Site Category',
                    field_type: 'select',
                    is_required: true,
                    grid_width: 6,
                    options: [
                        { label: 'Onsite Branch', value: 'onsite' },
                        { label: 'Offsite Kiosk', value: 'offsite' },
                        { label: 'Drive-Through', value: 'drive_through' }
                    ]
                },
                {
                    section_title: 'Contact & Timings',
                    field_key: 'site_contact_person',
                    field_label: 'Site Contact / Manager Name',
                    field_type: 'text',
                    placeholder: 'Full name',
                    is_required: false,
                    grid_width: 6
                },
                {
                    section_title: 'Contact & Timings',
                    field_key: 'site_access_hours',
                    field_label: 'Site Access Timings',
                    field_type: 'text',
                    placeholder: 'e.g. 24x7 or 9 AM - 9 PM',
                    is_required: true,
                    grid_width: 6
                },
                {
                    section_title: 'Site Photos & Documents',
                    field_key: 'site_front_photo',
                    field_label: 'ATM Entrance / Front Photo',
                    field_type: 'file',
                    is_required: true,
                    grid_width: 6,
                    validation_rules: { allowed_extensions: 'jpg,jpeg,png', max_size_mb: 5, multiple: false }
                },
                {
                    section_title: 'Site Photos & Documents',
                    field_key: 'site_permission_letter',
                    field_label: 'Site Access Permission Letter (PDF)',
                    field_type: 'file',
                    is_required: false,
                    grid_width: 6,
                    validation_rules: { allowed_extensions: 'pdf', max_size_mb: 10, multiple: false }
                }
            ];

        case 'feasibility':
            return [
                {
                    section_title: 'Power & Electrical Verification',
                    field_key: 'earthing_voltage',
                    field_label: 'Neutral to Earth Voltage (V)',
                    field_type: 'number',
                    placeholder: 'e.g. 1.2',
                    help_text: 'Must be within 0 to 2.0 Volts',
                    is_required: true,
                    grid_width: 6
                },
                {
                    section_title: 'Power & Electrical Verification',
                    field_key: 'ups_power_type',
                    field_label: 'UPS Availability',
                    field_type: 'radio',
                    is_required: true,
                    grid_width: 6,
                    options: [
                        { label: 'Yes, Dedicated UPS', value: 'dedicated' },
                        { label: 'Shared UPS', value: 'shared' },
                        { label: 'No UPS (Raw Power Only)', value: 'raw' }
                    ]
                },
                {
                    section_title: 'Network Signal Check',
                    field_key: 'strongest_operator',
                    field_label: 'Strongest Operator Detected',
                    field_type: 'select',
                    is_required: true,
                    grid_width: 6,
                    options: [
                        { label: 'Airtel (4G/5G)', value: 'airtel' },
                        { label: 'Jio (4G/5G)', value: 'jio' },
                        { label: 'Vodafone Idea (4G)', value: 'vi' },
                        { label: 'BSNL', value: 'bsnl' }
                    ]
                },
                {
                    section_title: 'Network Signal Check',
                    field_key: 'signal_strength_dbm',
                    field_label: 'Signal Strength (dBm)',
                    field_type: 'number',
                    placeholder: 'e.g. -75',
                    is_required: true,
                    grid_width: 6
                },
                {
                    section_title: 'Antenna & Cable Routing',
                    field_key: 'antenna_cable_length_mtr',
                    field_label: 'Estimated Antenna Cable (Meters)',
                    field_type: 'number',
                    placeholder: 'e.g. 15',
                    is_required: false,
                    grid_width: 6
                },
                {
                    section_title: 'Antenna & Cable Routing',
                    field_key: 'cable_routing_feasible',
                    field_label: 'Antenna Routing Feasible',
                    field_type: 'radio',
                    is_required: true,
                    grid_width: 6,
                    options: [
                        { label: 'Yes, Direct Route', value: 'direct' },
                        { label: 'Requires Wall Drilling', value: 'drilling' },
                        { label: 'Requires Roof Permission', value: 'roof_permission' }
                    ]
                },
                {
                    section_title: 'Site Photos',
                    field_key: 'earthing_meter_photo',
                    field_label: 'Multimeter Voltage Photo',
                    field_type: 'file',
                    is_required: true,
                    grid_width: 6,
                    validation_rules: { allowed_extensions: 'jpg,jpeg,png', max_size_mb: 5, multiple: false }
                },
                {
                    section_title: 'Site Photos',
                    field_key: 'router_proposed_place_photo',
                    field_label: 'Proposed Router Placement Snap',
                    field_type: 'file',
                    is_required: true,
                    grid_width: 6,
                    validation_rules: { allowed_extensions: 'jpg,jpeg,png', max_size_mb: 5, multiple: false }
                }
            ];

        case 'installation':
            return [
                {
                    section_title: 'Equipment Verification',
                    field_key: 'scanned_router_serial',
                    field_label: 'Scanned Router Serial Number',
                    field_type: 'text',
                    placeholder: 'e.g. RUT950_XXXXX',
                    is_required: true,
                    grid_width: 6
                },
                {
                    section_title: 'Equipment Verification',
                    field_key: 'sim_cards_installed',
                    field_label: 'SIM Cards Inserted',
                    field_type: 'checkbox',
                    is_required: true,
                    grid_width: 6,
                    options: [
                        { label: 'Primary SIM (Slot 1)', value: 'sim1' },
                        { label: 'Secondary SIM (Slot 2)', value: 'sim2' }
                    ]
                },
                {
                    section_title: 'Testing & Connectivity',
                    field_key: 'ping_latency_ms',
                    field_label: 'Ping Latency to Gateway (ms)',
                    field_type: 'number',
                    placeholder: 'e.g. 35',
                    is_required: true,
                    grid_width: 6
                },
                {
                    section_title: 'Testing & Connectivity',
                    field_key: 'atm_live_tested',
                    field_label: 'ATM Live Transaction Status',
                    field_type: 'radio',
                    is_required: true,
                    grid_width: 6,
                    options: [
                        { label: 'Success / Approved', value: 'success' },
                        { label: 'Pending Bank Activation', value: 'pending' },
                        { label: 'Failed', value: 'failed' }
                    ]
                },
                {
                    section_title: 'Installation Snaps & Signoff',
                    field_key: 'router_mounted_snap',
                    field_label: 'Mounted Router & LED Lights Snap',
                    field_type: 'file',
                    is_required: true,
                    grid_width: 6,
                    validation_rules: { allowed_extensions: 'jpg,jpeg,png', max_size_mb: 5, multiple: false }
                },
                {
                    section_title: 'Installation Snaps & Signoff',
                    field_key: 'bank_signoff_sheet',
                    field_label: 'Bank / Guard Signoff Document',
                    field_type: 'file',
                    is_required: true,
                    grid_width: 6,
                    validation_rules: { allowed_extensions: 'jpg,jpeg,png,pdf', max_size_mb: 5, multiple: false }
                }
            ];

        default:
            return [
                {
                    section_title: 'General Information',
                    field_key: 'remarks',
                    field_label: 'General Remarks',
                    field_type: 'textarea',
                    placeholder: 'Enter notes...',
                    is_required: false,
                    grid_width: 12
                }
            ];
    }
}

async function editForm(id) {
    try {
        const res = await fetch(`../api/forms-master/get.php?id=${id}`);
        const result = await res.json();
        if (!result.success || !result.data) {
            showToast('Form not found', 'error');
            return;
        }

        const form = result.data;
        document.getElementById('builder-modal-title').textContent = `Edit Form: ${form.form_name}`;
        document.getElementById('form-id').value = form.id;
        document.getElementById('form-name').value = form.form_name;
        document.getElementById('form-code').value = form.form_code;
        document.getElementById('form-purpose').value = form.purpose;
        document.getElementById('form-project-id').value = form.project_id || '';
        document.getElementById('form-status').value = form.status;
        document.getElementById('form-desc').value = form.description || '';

        updateHeaderBadges();

        currentFormFields = (form.fields || []).map(f => ({
            id: f.id,
            section_title: f.section_title || 'General Information',
            field_key: f.field_key,
            field_label: f.field_label,
            field_type: f.field_type,
            placeholder: f.placeholder || '',
            default_value: f.default_value || '',
            help_text: f.help_text || '',
            is_required: f.is_required == 1,
            options: f.options || [],
            validation_rules: f.validation_rules || {},
            grid_width: parseInt(f.grid_width) || 12,
            sort_order: parseInt(f.sort_order) || 0
        }));

        renderFieldCards();
        document.getElementById('builder-modal').classList.remove('hidden');
    } catch (e) {
        console.error(e);
        showToast('Failed to load form details', 'error');
    }
}

function closeFormBuilder() {
    document.getElementById('builder-modal').classList.add('hidden');
}

function addNewField(label = '', key = '', type = 'text', section = null, required = false, width = 12) {
    const idx = currentFormFields.length + 1;
    
    // Automatically inherit section header from the last configured field above
    let resolvedSection = section;
    if (!resolvedSection) {
        if (currentFormFields.length > 0) {
            const lastField = currentFormFields[currentFormFields.length - 1];
            resolvedSection = (lastField.section_title && lastField.section_title.trim() !== '') 
                ? lastField.section_title.trim() 
                : 'General Information';
        } else {
            resolvedSection = 'General Information';
        }
    }

    const newField = {
        section_title: resolvedSection,
        field_key: key || `field_${idx}_${Date.now().toString().slice(-4)}`,
        field_label: label || `Custom Field ${idx}`,
        field_type: type,
        placeholder: '',
        default_value: '',
        help_text: '',
        is_required: required,
        options: type === 'select' || type === 'radio' || type === 'checkbox' ? [{ label: 'Option 1', value: 'opt_1' }, { label: 'Option 2', value: 'opt_2' }] : [],
        validation_rules: type === 'file' ? { allowed_extensions: 'jpg,jpeg,png,pdf', max_size_mb: 5, multiple: false } : {},
        grid_width: width,
        sort_order: idx
    };

    currentFormFields.push(newField);
    renderFieldCards();

    // Auto scroll smoothly to the new field card
    setTimeout(() => {
        const cards = document.querySelectorAll('#fields-container > div');
        if (cards.length > 0) {
            cards[cards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }, 50);
}

function renderFieldCards() {
    const container = document.getElementById('fields-container');
    const countBadge = document.getElementById('fields-count-badge');
    countBadge.textContent = currentFormFields.length;

    if (currentFormFields.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                <i class="fas fa-layer-group text-2xl text-gray-300 mb-2"></i>
                <p class="text-xs text-gray-500 font-medium">No fields configured yet. Click "Add Field" to start building your form.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = '';

    currentFormFields.forEach((field, index) => {
        const typeInfo = fieldTypesMap[field.field_type] || { label: field.field_type, icon: 'fa-cube', color: 'indigo' };
        const isChoiceType = ['select', 'radio', 'checkbox'].includes(field.field_type);
        const isFileType = field.field_type === 'file';
        const isHeadingType = field.field_type === 'heading';

        const card = document.createElement('div');
        card.className = 'bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-indigo-300 transition-all space-y-3';
        card.dataset.index = index;

        card.innerHTML = `
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="cursor-move text-gray-400 hover:text-gray-600 p-1"><i class="fas fa-grip-vertical"></i></span>
                    <span class="w-6 h-6 rounded-md bg-indigo-50 text-indigo-600 font-bold flex items-center justify-center text-[11px]">${index + 1}</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700">
                        <i class="fas ${typeInfo.icon} text-indigo-500"></i> ${typeInfo.label}
                    </span>
                    <input type="text" value="${escapeHtml(field.section_title)}" onchange="updateFieldProp(${index}, 'section_title', this.value)" 
                        placeholder="Section Name" title="Section Name"
                        class="px-2 py-1 text-[11px] font-semibold text-gray-600 bg-gray-50 border border-gray-200 rounded focus:bg-white focus:ring-1 focus:ring-primary w-40">
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="moveField(${index}, -1)" ${index === 0 ? 'disabled' : ''} class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30" title="Move Up">
                        <i class="fas fa-arrow-up text-xs"></i>
                    </button>
                    <button type="button" onclick="moveField(${index}, 1)" ${index === currentFormFields.length - 1 ? 'disabled' : ''} class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30" title="Move Down">
                        <i class="fas fa-arrow-down text-xs"></i>
                    </button>
                    <button type="button" onclick="duplicateField(${index})" class="p-1 text-indigo-500 hover:text-indigo-700" title="Duplicate Field">
                        <i class="fas fa-copy text-xs"></i>
                    </button>
                    <button type="button" onclick="removeField(${index})" class="p-1 text-red-500 hover:text-red-700" title="Delete Field">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Field Core Attributes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs pt-1">
                <div>
                    <label class="block font-semibold text-gray-600 mb-1">Field Label <span class="text-red-500">*</span></label>
                    <input type="text" value="${escapeHtml(field.field_label)}" onchange="updateFieldLabel(${index}, this.value)" 
                        placeholder="e.g. Earthing Voltage"
                        class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block font-semibold text-gray-600 mb-1">Field Key (Unique Name)</label>
                    <input type="text" value="${escapeHtml(field.field_key)}" onchange="updateFieldProp(${index}, 'field_key', this.value)" 
                        placeholder="e.g. earthing_voltage"
                        class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg font-mono text-[11px] focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block font-semibold text-gray-600 mb-1">Field Type</label>
                    <select onchange="changeFieldType(${index}, this.value)" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        ${Object.keys(fieldTypesMap).map(typeKey => `
                            <option value="${typeKey}" ${field.field_type === typeKey ? 'selected' : ''}>${fieldTypesMap[typeKey].label}</option>
                        `).join('')}
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-gray-600 mb-1">Width in Grid</label>
                    <select onchange="updateFieldProp(${index}, 'grid_width', parseInt(this.value))" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="12" ${field.grid_width === 12 ? 'selected' : ''}>Full Width (12/12)</option>
                        <option value="6" ${field.grid_width === 6 ? 'selected' : ''}>Half Width (6/12)</option>
                        <option value="4" ${field.grid_width === 4 ? 'selected' : ''}>One-Third (4/12)</option>
                        <option value="3" ${field.grid_width === 3 ? 'selected' : ''}>One-Fourth (3/12)</option>
                    </select>
                </div>
            </div>

            <!-- Additional configs depending on type -->
            ${!isHeadingType ? `
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs bg-gray-50/50 p-2.5 rounded-lg border border-gray-100">
                <div>
                    <label class="block text-gray-500 font-medium mb-1">Placeholder Text</label>
                    <input type="text" value="${escapeHtml(field.placeholder || '')}" onchange="updateFieldProp(${index}, 'placeholder', this.value)" 
                        placeholder="e.g. Enter router serial..."
                        class="w-full px-2.5 py-1 border border-gray-300 rounded bg-white focus:ring-1 focus:ring-primary text-[11px]">
                </div>
                <div>
                    <label class="block text-gray-500 font-medium mb-1">Help Text / Subtitle</label>
                    <input type="text" value="${escapeHtml(field.help_text || '')}" onchange="updateFieldProp(${index}, 'help_text', this.value)" 
                        placeholder="e.g. Must be within 0-2 volts"
                        class="w-full px-2.5 py-1 border border-gray-300 rounded bg-white focus:ring-1 focus:ring-primary text-[11px]">
                </div>
                <div class="flex items-center justify-start gap-4 pt-4">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" ${field.is_required ? 'checked' : ''} onchange="updateFieldProp(${index}, 'is_required', this.checked)" class="rounded text-primary focus:ring-primary h-4 w-4">
                        <span class="ml-2 font-semibold text-gray-700 text-xs">Required Field</span>
                    </label>
                </div>
            </div>
            ` : ''}

            <!-- Choice Type Options Builder (Dropdown, Radio, Checkbox) -->
            ${isChoiceType ? `
            <div class="p-3.5 bg-gradient-to-r from-purple-50/60 via-indigo-50/40 to-purple-50/60 rounded-xl border border-purple-200/90 space-y-3 text-xs">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-purple-100 pb-2">
                    <span class="font-bold text-purple-950 flex items-center gap-1.5 text-xs">
                        <i class="fas fa-list-ul text-purple-600"></i> Choice Options Data Source
                    </span>
                    <div class="inline-flex rounded-lg border border-purple-200 bg-white p-0.5 shadow-sm">
                        <button type="button" onclick="setChoiceSourceType(${index}, 'master')" 
                            class="px-2.5 py-1 text-[11px] font-semibold rounded-md transition ${field.options && field.options.source === 'master' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'}">
                            <i class="fas fa-database mr-1"></i> From Master Data
                        </button>
                        <button type="button" onclick="setChoiceSourceType(${index}, 'custom')" 
                            class="px-2.5 py-1 text-[11px] font-semibold rounded-md transition ${!field.options || field.options.source !== 'master' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'}">
                            <i class="fas fa-edit mr-1"></i> Custom Manual Options
                        </button>
                    </div>
                </div>

                ${(field.options && field.options.source === 'master') ? `
                <!-- Master Data Source Selection -->
                <div class="bg-white p-3 rounded-lg border border-purple-100 space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1 text-[11px]">Select CRM Master Table</label>
                            <select onchange="updateChoiceMasterSource(${index}, this.value)" class="w-full px-3 py-1.5 border border-purple-200 rounded-lg text-xs font-semibold focus:ring-2 focus:ring-primary bg-purple-50/20">
                                <option value="banks" ${(field.options.master_key || 'banks') === 'banks' ? 'selected' : ''}>🏦 Bank Master (${masterSources.banks?.count || 13} Banks)</option>
                                <option value="customers" ${(field.options.master_key) === 'customers' ? 'selected' : ''}>👥 Customer Master (${masterSources.customers?.count || 0} Customers)</option>
                                <option value="projects" ${(field.options.master_key) === 'projects' ? 'selected' : ''}>📂 Project Master (${masterSources.projects?.count || 0} Projects)</option>
                                <option value="lhos" ${(field.options.master_key) === 'lhos' ? 'selected' : ''}>🏢 LHO Master (${masterSources.lhos?.count || 0} LHOs)</option>
                                <option value="countries" ${(field.options.master_key) === 'countries' ? 'selected' : ''}>🌍 Country Master (${masterSources.countries?.count || 0} Countries)</option>
                                <option value="states" ${(field.options.master_key) === 'states' ? 'selected' : ''}>🗺️ State Master (${masterSources.states?.count || 0} States)</option>
                                <option value="cities" ${(field.options.master_key) === 'cities' ? 'selected' : ''}>🏙️ City Master (${masterSources.cities?.count || 0} Cities)</option>
                                <option value="zones" ${(field.options.master_key) === 'zones' ? 'selected' : ''}>📍 Zone Master (${masterSources.zones?.count || 0} Zones)</option>
                                <option value="couriers" ${(field.options.master_key) === 'couriers' ? 'selected' : ''}>🚚 Courier Master (${masterSources.couriers?.count || 0} Couriers)</option>
                                <option value="product_categories" ${(field.options.master_key) === 'product_categories' ? 'selected' : ''}>📦 Product Categories (${masterSources.product_categories?.count || 0} Categories)</option>
                            </select>
                        </div>
                        <div class="bg-emerald-50/70 p-2.5 rounded-lg border border-emerald-200 text-[11px] text-emerald-900 flex items-center gap-2">
                            <i class="fas fa-check-circle text-emerald-600 text-base"></i>
                            <div>
                                <span class="font-bold">Live Synced with ${masterSources[field.options.master_key || 'banks']?.label || 'Master'}</span>
                                <p class="text-emerald-700 text-[10px]">Automatically loads all active entries from the master table in forms & surveys.</p>
                            </div>
                        </div>
                    </div>
                </div>
                ` : `
                <!-- Custom Manual Options List -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-gray-600">Manual Options List</span>
                        <button type="button" onclick="addOptionToField(${index})" class="px-2.5 py-1 bg-purple-600 text-white rounded text-[10px] font-semibold hover:bg-purple-700">
                            <i class="fas fa-plus mr-1"></i>Add Option
                        </button>
                    </div>
                    <div class="space-y-1.5" id="options-list-${index}">
                        ${(Array.isArray(field.options) ? field.options : (field.options && field.options.options ? field.options.options : [{ label: 'Option 1', value: 'opt_1' }])).map((opt, optIdx) => `
                            <div class="flex items-center gap-2">
                                <input type="text" value="${escapeHtml(opt.label)}" onchange="updateOption(${index}, ${optIdx}, 'label', this.value)" 
                                    placeholder="Option Label" class="flex-1 px-2 py-1 border border-gray-300 rounded text-xs bg-white">
                                <input type="text" value="${escapeHtml(opt.value)}" onchange="updateOption(${index}, ${optIdx}, 'value', this.value)" 
                                    placeholder="Option Value" class="w-32 px-2 py-1 border border-gray-300 rounded text-xs bg-white font-mono text-[11px]">
                                <button type="button" onclick="removeOptionFromField(${index}, ${optIdx})" class="text-red-500 hover:text-red-700 p-1">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                </div>
                `}
            </div>
            ` : ''}

            <!-- File Upload Specific Constraints -->
            ${isFileType ? `
            <div class="p-3 bg-orange-50/40 rounded-lg border border-orange-100 space-y-2 text-xs">
                <span class="font-bold text-orange-900 flex items-center gap-1.5">
                    <i class="fas fa-cloud-upload-alt"></i> File Upload Constraints
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-gray-600 font-medium mb-1">Allowed Extensions</label>
                        <input type="text" value="${escapeHtml((field.validation_rules && field.validation_rules.allowed_extensions) || 'jpg,jpeg,png,pdf')}" 
                            onchange="updateFileRule(${index}, 'allowed_extensions', this.value)"
                            placeholder="e.g. jpg,png,pdf,xlsx"
                            class="w-full px-2 py-1 border border-gray-300 rounded bg-white text-xs">
                    </div>
                    <div>
                        <label class="block text-gray-600 font-medium mb-1">Max File Size (MB)</label>
                        <input type="number" value="${(field.validation_rules && field.validation_rules.max_size_mb) || 5}" 
                            onchange="updateFileRule(${index}, 'max_size_mb', parseInt(this.value))"
                            min="1" max="50"
                            class="w-full px-2 py-1 border border-gray-300 rounded bg-white text-xs">
                    </div>
                    <div class="flex items-center pt-4">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" ${(field.validation_rules && field.validation_rules.multiple) ? 'checked' : ''} 
                                onchange="updateFileRule(${index}, 'multiple', this.checked)"
                                class="rounded text-primary focus:ring-primary h-4 w-4">
                            <span class="ml-2 font-medium text-gray-700 text-xs">Allow Multiple Uploads</span>
                        </label>
                    </div>
                </div>
            </div>
            ` : ''}

            <!-- Contact / Phone Number Specific Constraints -->
            ${field.field_type === 'phone' ? `
            <div class="p-3 bg-emerald-50/50 rounded-lg border border-emerald-200 space-y-2 text-xs">
                <span class="font-bold text-emerald-950 flex items-center gap-1.5">
                    <i class="fas fa-phone-alt text-emerald-600"></i> Contact / Phone Number Settings
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="flex items-center">
                        <label class="inline-flex items-center cursor-pointer bg-white px-3 py-2 rounded-lg border border-emerald-200 shadow-sm w-full">
                            <input type="checkbox" ${(field.validation_rules && field.validation_rules.multiple) ? 'checked' : ''} 
                                onchange="updatePhoneRule(${index}, 'multiple', this.checked)"
                                class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                            <span class="ml-2.5 font-semibold text-gray-800 text-xs">
                                <i class="fas fa-layer-group text-emerald-600 mr-1"></i> Allow Multiple Numbers
                                <span class="block font-normal text-[10px] text-gray-500 mt-0.5">Enables "+ Add Another Number" repeater button in form</span>
                            </span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <label class="inline-flex items-center cursor-pointer bg-white px-3 py-2 rounded-lg border border-emerald-200 shadow-sm w-full">
                            <input type="checkbox" ${(field.validation_rules && field.validation_rules.strict_10_digit) ? 'checked' : ''} 
                                onchange="updatePhoneRule(${index}, 'strict_10_digit', this.checked)"
                                class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                            <span class="ml-2.5 font-semibold text-gray-800 text-xs">
                                <i class="fas fa-check-double text-emerald-600 mr-1"></i> Strict 10-Digit Format
                                <span class="block font-normal text-[10px] text-gray-500 mt-0.5">Validates standard 10-digit mobile number</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            ` : ''}
        `;

        container.appendChild(card);
    });
}

function updateFieldLabel(index, val) {
    currentFormFields[index].field_label = val;
    if (!currentFormFields[index].field_key || currentFormFields[index].field_key.startsWith('field_')) {
        currentFormFields[index].field_key = val.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').substring(0, 50);
        renderFieldCards();
    }
}

function updateFieldProp(index, prop, val) {
    currentFormFields[index][prop] = val;
}

function changeFieldType(index, newType) {
    currentFormFields[index].field_type = newType;
    if (['select', 'radio', 'checkbox'].includes(newType) && (!currentFormFields[index].options || (Array.isArray(currentFormFields[index].options) && currentFormFields[index].options.length === 0))) {
        currentFormFields[index].options = [
            { label: 'Option 1', value: 'opt_1' },
            { label: 'Option 2', value: 'opt_2' }
        ];
    }
    if (newType === 'file' && (!currentFormFields[index].validation_rules || !currentFormFields[index].validation_rules.allowed_extensions)) {
        currentFormFields[index].validation_rules = { allowed_extensions: 'jpg,jpeg,png,pdf', max_size_mb: 5, multiple: false };
    }
    if (newType === 'phone' && (!currentFormFields[index].validation_rules || currentFormFields[index].validation_rules.multiple === undefined)) {
        currentFormFields[index].validation_rules = { multiple: true, strict_10_digit: true };
    }
    renderFieldCards();
}

function updatePhoneRule(index, ruleKey, val) {
    if (!currentFormFields[index].validation_rules) currentFormFields[index].validation_rules = {};
    currentFormFields[index].validation_rules[ruleKey] = val;
}

function setChoiceSourceType(index, type) {
    if (type === 'master') {
        currentFormFields[index].options = {
            source: 'master',
            master_key: 'banks'
        };
    } else {
        currentFormFields[index].options = [
            { label: 'Option 1', value: 'opt_1' },
            { label: 'Option 2', value: 'opt_2' }
        ];
    }
    renderFieldCards();
}

function updateChoiceMasterSource(index, masterKey) {
    if (!currentFormFields[index].options || typeof currentFormFields[index].options !== 'object' || currentFormFields[index].options.source !== 'master') {
        currentFormFields[index].options = { source: 'master', master_key: masterKey };
    } else {
        currentFormFields[index].options.master_key = masterKey;
    }
    renderFieldCards();
}

function moveField(index, direction) {
    const newIdx = index + direction;
    if (newIdx < 0 || newIdx >= currentFormFields.length) return;
    const temp = currentFormFields[index];
    currentFormFields[index] = currentFormFields[newIdx];
    currentFormFields[newIdx] = temp;
    renderFieldCards();
}

function duplicateField(index) {
    const orig = currentFormFields[index];
    const clone = JSON.parse(JSON.stringify(orig));
    clone.field_label += ' (Copy)';
    clone.field_key += '_copy_' + Date.now().toString().slice(-4);
    delete clone.id;
    currentFormFields.splice(index + 1, 0, clone);
    renderFieldCards();
}

function removeField(index) {
    currentFormFields.splice(index, 1);
    renderFieldCards();
}

function addOptionToField(index) {
    if (!currentFormFields[index].options || !Array.isArray(currentFormFields[index].options)) {
        currentFormFields[index].options = [];
    }
    const optNum = currentFormFields[index].options.length + 1;
    currentFormFields[index].options.push({ label: `Option ${optNum}`, value: `opt_${optNum}` });
    renderFieldCards();
}

function updateOption(fieldIndex, optIndex, key, val) {
    if (Array.isArray(currentFormFields[fieldIndex].options)) {
        currentFormFields[fieldIndex].options[optIndex][key] = val;
    }
}

function removeOptionFromField(fieldIndex, optIndex) {
    if (Array.isArray(currentFormFields[fieldIndex].options)) {
        currentFormFields[fieldIndex].options.splice(optIndex, 1);
        renderFieldCards();
    }
}

function updateFileRule(index, ruleKey, val) {
    if (!currentFormFields[index].validation_rules) currentFormFields[index].validation_rules = {};
    currentFormFields[index].validation_rules[ruleKey] = val;
}

function resolveFieldPreviewOptions(field) {
    if (field.resolved_options && Array.isArray(field.resolved_options) && field.resolved_options.length > 0) {
        return field.resolved_options;
    }
    if (field.options && field.options.source === 'master' && field.options.master_key) {
        const sourceData = masterSources[field.options.master_key];
        if (sourceData && sourceData.items) {
            return sourceData.items.map(item => ({
                label: item.name + (item.code ? ` (${item.code})` : ''),
                value: item.name
            }));
        }
    }
    if (Array.isArray(field.options)) {
        return field.options;
    }
    if (field.options && Array.isArray(field.options.options)) {
        return field.options.options;
    }
    return [];
}

// Save Custom Form
async function saveCustomForm() {
    const formId = document.getElementById('form-id').value;
    const formName = document.getElementById('form-name').value.trim();
    const formCode = document.getElementById('form-code').value.trim();
    const purpose = document.getElementById('form-purpose').value;
    const projectId = document.getElementById('form-project-id').value;
    const status = document.getElementById('form-status').value;
    const desc = document.getElementById('form-desc').value.trim();

    if (!formName) {
        showToast('Please enter a Form Name', 'error');
        document.getElementById('form-name').focus();
        return;
    }

    if (currentFormFields.length === 0) {
        showToast('Please add at least one field to the form', 'error');
        return;
    }

    const payload = {
        id: formId ? parseInt(formId) : null,
        form_name: formName,
        form_code: formCode || null,
        purpose: purpose,
        project_id: projectId ? parseInt(projectId) : null,
        status: status,
        description: desc || null,
        fields: currentFormFields.map((f, idx) => ({
            id: f.id || null,
            section_title: f.section_title || 'General Information',
            field_key: f.field_key,
            field_label: f.field_label,
            field_type: f.field_type,
            placeholder: f.placeholder || null,
            default_value: f.default_value || null,
            help_text: f.help_text || null,
            is_required: f.is_required ? 1 : 0,
            options: f.options || [],
            validation_rules: f.validation_rules || {},
            grid_width: parseInt(f.grid_width) || 12,
            sort_order: idx + 1
        }))
    };

    const isEdit = !!formId;
    const url = isEdit ? '../api/forms-master/update.php' : '../api/forms-master/create.php';
    const btn = document.getElementById('btn-save-form');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Saving...';

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-1.5"></i>Save Form';

        if (result.success) {
            showToast(result.message || 'Form saved successfully', 'success');
            closeFormBuilder();
            loadForms();
        } else {
            showToast(result.message || 'Failed to save form', 'error');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-1.5"></i>Save Form';
        console.error(e);
        showToast('Server error while saving form', 'error');
    }
}

// ==========================================
// LIVE PREVIEW MODAL
// ==========================================

function previewCurrentForm() {
    const formName = document.getElementById('form-name').value || 'Untitled Form';
    const purpose = document.getElementById('form-purpose').value;
    const projSelect = document.getElementById('form-project-id');
    const projText = projSelect.selectedIndex > 0 ? projSelect.options[projSelect.selectedIndex].text : 'Global / Default';

    renderPreview({
        form_name: formName,
        purpose: purpose,
        project_name: projText,
        fields: currentFormFields
    });
}

async function previewFormById(id) {
    try {
        const res = await fetch(`../api/forms-master/get.php?id=${id}`);
        const result = await res.json();
        if (result.success && result.data) {
            renderPreview(result.data);
        } else {
            showToast('Failed to load preview', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Error loading preview', 'error');
    }
}

function renderPreview(formData) {
    document.getElementById('preview-modal-title').textContent = formData.form_name;
    document.getElementById('preview-modal-subtitle').textContent = `Target: ${formData.project_name || 'Global'} | Purpose: ${purposeMap[formData.purpose] || formData.purpose}`;

    const container = document.getElementById('preview-sections-container');
    container.innerHTML = '';

    const fields = formData.fields || [];
    if (fields.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-gray-500">No fields to preview in this form.</div>';
        document.getElementById('preview-modal').classList.remove('hidden');
        return;
    }

    // Group fields by section
    const sections = {};
    fields.forEach(f => {
        const sec = f.section_title || 'General Information';
        if (!sections[sec]) sections[sec] = [];
        sections[sec].push(f);
    });

    Object.keys(sections).forEach(secTitle => {
        const secCard = document.createElement('div');
        secCard.className = 'bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4';

        secCard.innerHTML = `
            <div class="border-b border-gray-100 pb-2">
                <h4 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-2 h-4 bg-indigo-600 rounded-full inline-block"></span>
                    ${escapeHtml(secTitle)}
                </h4>
            </div>
            <div class="grid grid-cols-12 gap-4 items-start text-xs" id="sec-grid-${secTitle.replace(/[^a-zA-Z0-9]/g, '_')}">
            </div>
        `;

        container.appendChild(secCard);
        const grid = secCard.querySelector(`#sec-grid-${secTitle.replace(/[^a-zA-Z0-9]/g, '_')}`);

        sections[secTitle].forEach(field => {
            const colSpan = field.grid_width || 12;
            const fieldCol = document.createElement('div');
            fieldCol.className = `col-span-12 md:col-span-${colSpan} space-y-1.5`;

            const reqMark = field.is_required ? '<span class="text-red-500">*</span>' : '';
            const help = field.help_text ? `<p class="text-[11px] text-gray-400 mt-0.5">${escapeHtml(field.help_text)}</p>` : '';

            let inputHtml = '';

            switch (field.field_type) {
                case 'heading':
                    inputHtml = `
                        <div class="py-2 border-b border-indigo-100">
                            <h5 class="text-xs font-bold text-indigo-900 uppercase tracking-wide">${escapeHtml(field.field_label)}</h5>
                            ${help}
                        </div>
                    `;
                    break;

                case 'text':
                case 'email':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <input type="${field.field_type}" name="${field.field_key}" placeholder="${escapeHtml(field.placeholder || '')}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        ${help}
                    `;
                    break;

                case 'number':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <input type="number" step="any" name="${field.field_key}" placeholder="${escapeHtml(field.placeholder || '')}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        ${help}
                    `;
                    break;

                case 'phone':
                    const phoneRules = field.validation_rules || {};
                    if (phoneRules.multiple) {
                        inputHtml = `
                            <div class="flex items-center justify-between">
                                <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                                <span class="text-[10px] text-gray-400 font-medium">10 Digits</span>
                            </div>
                            <div id="preview-phone-list-${field.field_key}" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                            <i class="fas fa-phone-alt text-xs"></i>
                                        </span>
                                        <input type="tel" name="${field.field_key}[]" maxlength="10" placeholder="${escapeHtml(field.placeholder || 'e.g. 9876543210')}" 
                                            class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-mono" ${field.is_required ? 'required' : ''}>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="addPreviewPhoneRow('${field.field_key}')" class="mt-1.5 px-3 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded text-[11px] font-semibold flex items-center gap-1">
                                <i class="fas fa-plus"></i> Add Another Number
                            </button>
                            ${help}
                        `;
                    } else {
                        inputHtml = `
                            <div class="flex items-center justify-between">
                                <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                                <span class="text-[10px] text-gray-400 font-medium">10 Digits</span>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                    <i class="fas fa-phone-alt text-xs"></i>
                                </span>
                                <input type="tel" name="${field.field_key}" maxlength="10" placeholder="${escapeHtml(field.placeholder || 'e.g. 9876543210')}" 
                                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs font-mono" ${field.is_required ? 'required' : ''}>
                            </div>
                            ${help}
                        `;
                    }
                    break;

                case 'textarea':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <textarea rows="3" name="${field.field_key}" placeholder="${escapeHtml(field.placeholder || '')}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        ${help}
                    `;
                    break;

                case 'select':
                    const opts = resolveFieldPreviewOptions(field);
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <select name="${field.field_key}" ${field.is_required ? 'required' : ''} class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <option value="">-- Please Select --</option>
                            ${opts.map(o => `<option value="${escapeHtml(o.value)}">${escapeHtml(o.label)}</option>`).join('')}
                        </select>
                        ${help}
                    `;
                    break;

                case 'radio':
                    const rOpts = resolveFieldPreviewOptions(field);
                    inputHtml = `
                        <label class="block font-semibold text-gray-700 mb-1">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <div class="flex flex-wrap gap-4 pt-1">
                            ${rOpts.map(o => `
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="${field.field_key}" value="${escapeHtml(o.value)}" ${field.is_required ? 'required' : ''} class="text-primary focus:ring-primary h-4 w-4">
                                    <span class="ml-2 text-gray-700">${escapeHtml(o.label)}</span>
                                </label>
                            `).join('')}
                        </div>
                        ${help}
                    `;
                    break;

                case 'checkbox':
                    const cOpts = resolveFieldPreviewOptions(field);
                    if (cOpts.length > 0) {
                        inputHtml = `
                            <label class="block font-semibold text-gray-700 mb-1">${escapeHtml(field.field_label)} ${reqMark}</label>
                            <div class="flex flex-wrap gap-4 pt-1">
                                ${cOpts.map(o => `
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="${field.field_key}[]" value="${escapeHtml(o.value)}" class="rounded text-primary focus:ring-primary h-4 w-4">
                                        <span class="ml-2 text-gray-700">${escapeHtml(o.label)}</span>
                                    </label>
                                `).join('')}
                            </div>
                            ${help}
                        `;
                    } else {
                        inputHtml = `
                            <div class="pt-2">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="${field.field_key}" value="1" ${field.is_required ? 'required' : ''} class="rounded text-primary focus:ring-primary h-4 w-4">
                                    <span class="ml-2 font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</span>
                                </label>
                                ${help}
                            </div>
                        `;
                    }
                    break;

                case 'file':
                    const rules = field.validation_rules || {};
                    const acceptExt = rules.allowed_extensions ? rules.allowed_extensions.split(',').map(e => '.' + e.trim().replace('.', '')).join(',') : '*';
                    const maxMb = rules.max_size_mb || 5;
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-3 text-center bg-gray-50 hover:bg-white transition cursor-pointer relative">
                            <input type="file" name="${field.field_key}${rules.multiple ? '[]' : ''}" accept="${acceptExt}" ${rules.multiple ? 'multiple' : ''} ${field.is_required ? 'required' : ''}
                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                            <i class="fas fa-cloud-upload-alt text-indigo-500 text-xl mb-1"></i>
                            <p class="text-xs font-semibold text-gray-700">Choose file or drag here</p>
                            <p class="text-[10px] text-gray-400">Accepted: ${escapeHtml(rules.allowed_extensions || 'Any')} (Max: ${maxMb}MB)</p>
                        </div>
                        ${help}
                    `;
                    break;

                case 'date':
                case 'datetime':
                    inputHtml = `
                        <label class="block font-semibold text-gray-700">${escapeHtml(field.field_label)} ${reqMark}</label>
                        <input type="${field.field_type === 'date' ? 'date' : 'datetime-local'}" name="${field.field_key}" ${field.is_required ? 'required' : ''}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        ${help}
                    `;
                    break;
            }

            fieldCol.innerHTML = inputHtml;
            grid.appendChild(fieldCol);
        });
    });

    document.getElementById('preview-modal').classList.remove('hidden');
}

function addPreviewPhoneRow(key) {
    const list = document.getElementById(`preview-phone-list-${key}`);
    if (!list) return;
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 pt-1';
    row.innerHTML = `
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-emerald-600">
                <i class="fas fa-phone-alt text-xs"></i>
            </span>
            <input type="tel" name="${key}[]" placeholder="Additional contact number..." 
                class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary text-xs">
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="p-2 text-red-500 hover:text-red-700" title="Remove number">
            <i class="fas fa-times text-xs"></i>
        </button>
    `;
    list.appendChild(row);
    row.querySelector('input').focus();
}

function closePreviewModal() {
    document.getElementById('preview-modal').classList.add('hidden');
}

function handlePreviewSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const obj = {};
    formData.forEach((val, key) => {
        if (obj[key]) {
            if (!Array.isArray(obj[key])) obj[key] = [obj[key]];
            obj[key].push(val instanceof File ? val.name : val);
        } else {
            obj[key] = val instanceof File ? val.name : val;
        }
    });

    alert('Form Simulation Validated Successfully!\n\nSubmitted Payload Preview:\n' + JSON.stringify(obj, null, 2));
}

// ==========================================
// DUPLICATE MODAL LOGIC
// ==========================================

function openDuplicateModal(id, name, purpose, projectId) {
    document.getElementById('duplicate-source-id').value = id;
    document.getElementById('duplicate-form-name').value = `${name} (Copy)`;
    document.getElementById('duplicate-purpose').value = purpose;
    document.getElementById('duplicate-project-id').value = projectId || '';
    document.getElementById('duplicate-modal').classList.remove('hidden');
}

function closeDuplicateModal() {
    document.getElementById('duplicate-modal').classList.add('hidden');
}

async function submitDuplicateForm() {
    const id = document.getElementById('duplicate-source-id').value;
    const name = document.getElementById('duplicate-form-name').value.trim();
    const purpose = document.getElementById('duplicate-purpose').value;
    const projectId = document.getElementById('duplicate-project-id').value;

    if (!name) {
        showToast('Please enter a new form name', 'error');
        return;
    }

    try {
        const res = await fetch('../api/forms-master/duplicate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: parseInt(id),
                form_name: name,
                purpose: purpose,
                project_id: projectId ? parseInt(projectId) : null
            })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Form duplicated successfully', 'success');
            closeDuplicateModal();
            loadForms();
        } else {
            showToast(result.message || 'Failed to duplicate form', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Server error while duplicating form', 'error');
    }
}

// ==========================================
// DELETE FORM
// ==========================================

async function deleteForm(id) {
    if (!confirm('Are you sure you want to delete this custom form? This cannot be undone.')) {
        return;
    }

    try {
        const res = await fetch('../api/forms-master/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Form deleted successfully', 'success');
            loadForms();
        } else {
            showToast(result.message || 'Failed to delete form', 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Error deleting form', 'error');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
