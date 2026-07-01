<?php
/**
 * File Manager Module - Main Page
 * 
 * Provides file system browsing and management capabilities for ADV administrators
 * 
 * Requirements: 1.1, 1.3, 1.4, 1.5
 * - 1.1: Display contents of XAMPP_Root directory
 * - 1.3: Show file name, type, size, and last modified date for each item
 * - 1.4: Distinguish between files and folders using appropriate icons
 * - 1.5: Display Breadcrumb_Navigation showing the current path
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../middleware/FileManagerMiddleware.php';

// Initialize session and check login
$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// File Manager access check (ADV + system.manage)
$fileManagerMiddleware = new FileManagerMiddleware();
$user = $fileManagerMiddleware->requireAccess();
if (!$user) {
    exit; // Redirect already handled by middleware
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'File Manager';
$currentPage = 'file_manager';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'System'],
    ['label' => 'File Manager']
];

ob_start();
?>

<!-- Date-based Login Modal -->
<div id="dateLoginModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 animate-fadeIn">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full">
                <i class="fas fa-lock text-white text-2xl"></i>
            </div>
            <h2 class="text-xl font-semibold text-center text-gray-800">File Manager Access</h2>
            <p class="text-sm text-center text-gray-500 mt-2">Enter credentials to continue</p>
        </div>
        <form id="dateLoginForm" class="p-6 space-y-4">
            <div id="dateLoginError" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm text-center">
                <i class="fas fa-exclamation-circle mr-1"></i>Invalid access code
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Access Code</label>
                <input type="password" id="dateLoginAccessCode" required autocomplete="off"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            </div>
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-semibold rounded-lg hover:opacity-90 transition transform hover:scale-[1.02]">
                <i class="fas fa-sign-in-alt mr-2"></i>Login
            </button>
            <a href="../dashboard.php" class="block w-full py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition text-center">
                <i class="fas fa-home mr-2"></i>Go to Dashboard
            </a>
        </form>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn { animation: fadeIn 0.3s ease-out; }
#mainFileManagerContent { display: none; }
#mainFileManagerContent.unlocked { display: block; }
</style>

<div id="mainFileManagerContent">
<div class="space-y-6">
    <!-- File Manager Header -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">File Manager</h3>
                <p class="text-sm text-gray-500">Browse and manage server files within XAMPP directory</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="destroyDateSession()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-2"></i>Lock
                </button>
                <button onclick="openNewFileModal()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                    <i class="fas fa-file-plus mr-2"></i>New File
                </button>
                <button onclick="openNewFolderModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                    <i class="fas fa-folder-plus mr-2"></i>New Folder
                </button>
                <button onclick="openUploadModal()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center">
                    <i class="fas fa-upload mr-2"></i>Upload
                </button>
            </div>
        </div>
        
        <!-- Breadcrumb Navigation (Requirement 1.5) -->
        <div class="p-4 border-b bg-gray-50">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex-1">
                    <nav id="breadcrumb-nav" class="flex items-center text-sm">
                        <a href="#" onclick="navigateTo('')" class="text-primary hover:underline flex items-center">
                            <i class="fas fa-home mr-1"></i>Root
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <input type="text" id="search-input" placeholder="Search files..." 
                            class="w-64 px-4 py-2 pl-10 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <button onclick="performSearch()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading indicator -->
        <div id="loading-indicator" class="hidden p-8 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
            <p class="mt-2 text-gray-500">Loading files...</p>
        </div>
        
        <!-- Directory Contents Table (Requirements 1.3, 1.4) -->
        <div class="overflow-x-auto">
            <table id="file-table" class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Size</th>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Modified</th>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="file-tbody" class="divide-y">
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Directory Info -->
        <div id="directory-info" class="p-4 border-t flex items-center justify-between text-sm text-gray-500">
            <span id="item-count">0 items</span>
            <span id="current-path-display"></span>
        </div>
    </div>
    
    <!-- Search Results Section (hidden by default) -->
    <div id="search-results-section" class="hidden bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Search Results</h3>
                <p id="search-query-display" class="text-sm text-gray-500"></p>
            </div>
            <button onclick="closeSearchResults()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Path</th>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="search-results-tbody" class="divide-y"></tbody>
            </table>
        </div>
    </div>
</div>
</div> <!-- End mainFileManagerContent -->


<!-- File Viewer Modal (Requirements 2.1, 2.2, 2.3) -->
<div id="file-viewer-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeFileViewerModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full relative z-10 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 id="viewer-filename" class="text-lg font-semibold text-gray-800">File Viewer</h3>
                    <p id="viewer-filepath" class="text-sm text-gray-500"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button id="viewer-edit-btn" onclick="editCurrentFile()" class="px-3 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </button>
                    <button onclick="closeFileViewerModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-4 bg-gray-50 border-b flex items-center gap-4 text-sm text-gray-600">
                <span><i class="fas fa-file mr-1"></i><span id="viewer-size"></span></span>
                <span><i class="fas fa-clock mr-1"></i><span id="viewer-modified"></span></span>
                <span id="viewer-large-warning" class="hidden text-yellow-600"><i class="fas fa-exclamation-triangle mr-1"></i>Large file - content truncated</span>
            </div>
            <div class="flex-1 overflow-auto p-4">
                <pre id="viewer-content" class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm font-mono whitespace-pre-wrap"></pre>
            </div>
        </div>
    </div>
</div>

<!-- File Editor Modal (Requirements 4.1, 4.2, 4.4, 4.5) -->
<div id="file-editor-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full relative z-10 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <div>
                    <h3 id="editor-filename" class="text-lg font-semibold text-gray-800">Edit File</h3>
                    <p id="editor-filepath" class="text-sm text-gray-500"></p>
                </div>
                <button onclick="closeFileEditorModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex-1 overflow-hidden p-4">
                <textarea id="editor-content" class="w-full h-full min-h-[400px] p-4 font-mono text-sm border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none bg-gray-50"></textarea>
            </div>
            <div id="editor-message" class="hidden px-5 py-3 border-t"></div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeFileEditorModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" onclick="saveFile()" id="save-file-btn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-save mr-2"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New File Modal (Requirements 3.1, 3.2) -->
<div id="new-file-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeNewFileModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Create New File</h3>
                <button onclick="closeNewFileModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="new-file-form" onsubmit="createNewFile(event)">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="new-file-name" class="block text-sm font-medium text-gray-700 mb-1">File Name <span class="text-red-500">*</span></label>
                        <input type="text" id="new-file-name" name="name" required placeholder="example.php"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p id="new-file-name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                    <div>
                        <label for="new-file-content" class="block text-sm font-medium text-gray-700 mb-1">Initial Content</label>
                        <textarea id="new-file-content" name="content" rows="6" placeholder="Enter initial file content (optional)"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent font-mono text-sm"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeNewFileModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-plus mr-2"></i>Create File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Folder Modal (Requirements 3.3, 3.4) -->
<div id="new-folder-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeNewFolderModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Create New Folder</h3>
                <button onclick="closeNewFolderModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="new-folder-form" onsubmit="createNewFolder(event)">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="new-folder-name" class="block text-sm font-medium text-gray-700 mb-1">Folder Name <span class="text-red-500">*</span></label>
                        <input type="text" id="new-folder-name" name="name" required placeholder="new-folder"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p id="new-folder-name-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeNewFolderModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-folder-plus mr-2"></i>Create Folder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Upload Modal (Requirements 9.1, 9.4) -->
<div id="upload-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeUploadModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Upload File</h3>
                <button onclick="closeUploadModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <!-- Drop Zone -->
                <div id="upload-dropzone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary transition cursor-pointer"
                    onclick="document.getElementById('upload-file-input').click()"
                    ondragover="handleDragOver(event)" ondrop="handleDrop(event)" ondragleave="handleDragLeave(event)">
                    <input type="file" id="upload-file-input" class="hidden" onchange="handleFileSelect(event)">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600">Drag and drop your file here, or click to browse</p>
                    <p class="text-sm text-gray-400 mt-1">Maximum file size: 50MB</p>
                </div>
                
                <!-- Selected File Info -->
                <div id="upload-file-info" class="hidden p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i id="upload-file-icon" class="fas fa-file text-primary text-2xl mr-3"></i>
                            <div>
                                <p id="upload-file-name" class="font-medium text-gray-800"></p>
                                <p id="upload-file-size" class="text-sm text-gray-500"></p>
                            </div>
                        </div>
                        <button onclick="clearUploadFile()" class="text-gray-400 hover:text-red-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Upload Progress -->
                <div id="upload-progress" class="hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-600">Uploading...</span>
                        <span id="upload-progress-percent" class="text-sm font-medium text-primary">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="upload-progress-bar" class="bg-primary h-2 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                </div>
                
                <!-- Overwrite Warning -->
                <div id="upload-overwrite-warning" class="hidden p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-3 mt-0.5"></i>
                        <div>
                            <p class="font-medium text-yellow-800">File already exists</p>
                            <p class="text-sm text-yellow-600">A file with this name already exists. Do you want to overwrite it?</p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <label class="flex items-center">
                            <input type="checkbox" id="upload-overwrite-checkbox" class="mr-2">
                            <span class="text-sm text-yellow-700">Yes, overwrite the existing file</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeUploadModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </button>
                <button type="button" onclick="uploadFile()" id="upload-btn" disabled class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-upload mr-2"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rename Modal (Requirements 10.1, 10.2) -->
<div id="rename-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRenameModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">Rename</h3>
                <button onclick="closeRenameModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="rename-form" onsubmit="renameItem(event)">
                <input type="hidden" id="rename-old-path" name="oldPath">
                <div class="p-5 space-y-4">
                    <div>
                        <label for="rename-new-name" class="block text-sm font-medium text-gray-700 mb-1">New Name <span class="text-red-500">*</span></label>
                        <input type="text" id="rename-new-name" name="newName" required
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p id="rename-error" class="mt-1 text-sm text-red-500 hidden"></p>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                    <button type="button" onclick="closeRenameModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-edit mr-2"></i>Rename
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal (Requirements 5.1, 5.3) -->
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
                        <p id="delete-message" class="text-gray-700">Are you sure you want to delete this item?</p>
                        <p id="delete-warning" class="hidden mt-2 text-sm text-yellow-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            This folder contains files and subfolders. All contents will be permanently deleted.
                        </p>
                    </div>
                </div>
                <input type="hidden" id="delete-path">
                <input type="hidden" id="delete-type">
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
    currentPath: '',
    items: [],
    breadcrumbs: [],
    currentFile: null,
    uploadFile: null
};

const API_BASE = '../api/filemanager';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDirectory('');
    setupEventListeners();
});

// Setup event listeners
function setupEventListeners() {
    // Search on Enter key
    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
}

// ==================== Directory Navigation ====================

async function loadDirectory(path) {
    showLoading(true);
    state.currentPath = path;
    
    try {
        const response = await fetch(`${API_BASE}/list.php?path=${encodeURIComponent(path)}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            state.items = data.data.items || [];
            state.breadcrumbs = data.data.breadcrumbs || [];
            renderBreadcrumbs();
            renderTable();
            updateDirectoryInfo();
        } else {
            showError(data.error?.message || 'Failed to load directory');
        }
    } catch (error) {
        console.error('Error loading directory:', error);
        showError('Failed to load directory. Please try again.');
    } finally {
        showLoading(false);
    }
}

function navigateTo(path) {
    closeSearchResults();
    loadDirectory(path);
}

// ==================== Rendering ====================

function renderBreadcrumbs() {
    const nav = document.getElementById('breadcrumb-nav');
    let html = `<a href="#" onclick="navigateTo('')" class="text-primary hover:underline flex items-center">
        <i class="fas fa-home mr-1"></i>Root
    </a>`;
    
    state.breadcrumbs.forEach((crumb, index) => {
        html += `<span class="mx-2 text-gray-400">/</span>`;
        if (crumb.isLast) {
            html += `<span class="text-gray-600">${escapeHtml(crumb.label)}</span>`;
        } else {
            html += `<a href="#" onclick="navigateTo('${escapeHtml(crumb.path)}')" class="text-primary hover:underline">${escapeHtml(crumb.label)}</a>`;
        }
    });
    
    nav.innerHTML = html;
}

function renderTable() {
    const tbody = document.getElementById('file-tbody');
    
    if (state.items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-folder-open text-4xl mb-3 text-gray-300"></i>
                    <p>This directory is empty</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = state.items.map(item => {
        const isDir = item.type === 'directory';
        const isFile = !isDir;
        const iconClass = item.icon || (isDir ? 'fa-folder' : 'fa-file');
        const iconColor = isDir ? 'text-yellow-500' : 'text-blue-500';
        
        // Build action buttons based on item type (Requirements 2.1, 4.1, 5.1, 8.1, 10.1)
        let actionButtons = '';
        
        if (isFile) {
            // View button - for files only (Requirement 2.1)
            actionButtons += `
                <button onclick="viewFile('${escapeHtml(item.path)}')" class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                    <i class="fas fa-eye"></i>
                </button>`;
            
            // Edit button - for editable files only (Requirement 4.1)
            if (item.isEditable) {
                actionButtons += `
                <button onclick="editFile('${escapeHtml(item.path)}')" class="p-2 text-gray-500 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>`;
            }
            
            // Download button - for files only (Requirement 8.1)
            actionButtons += `
                <button onclick="downloadFile('${escapeHtml(item.path)}')" class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition" title="Download">
                    <i class="fas fa-download"></i>
                </button>`;
        }
        
        // Rename button - for all items (Requirement 10.1)
        actionButtons += `
            <button onclick="openRenameModal('${escapeHtml(item.path)}', '${escapeHtml(item.name)}')" class="p-2 text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="Rename">
                <i class="fas fa-pen"></i>
            </button>`;
        
        // Delete button - for all items (Requirement 5.1)
        actionButtons += `
            <button onclick="openDeleteModal('${escapeHtml(item.path)}', '${item.type}', '${escapeHtml(item.name)}')" class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                <i class="fas fa-trash-alt"></i>
            </button>`;
        
        return `
        <tr class="hover:bg-gray-50 ${isDir ? 'cursor-pointer' : ''}" ${isDir ? `onclick="navigateTo('${escapeHtml(item.path)}')"` : ''}>
            <td class="px-6 py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 ${isDir ? 'bg-yellow-100' : 'bg-blue-100'} rounded-lg flex items-center justify-center mr-3">
                        <i class="fas ${iconClass} ${iconColor}"></i>
                    </div>
                    <span class="font-medium text-gray-800">${escapeHtml(item.name)}</span>
                </div>
            </td>
            <td class="px-6 py-4 text-gray-600">
                ${isDir ? '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Folder</span>' : 
                         `<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">${escapeHtml(item.extension || 'File')}</span>`}
            </td>
            <td class="px-6 py-4 text-gray-600">${isDir ? '-' : escapeHtml(item.sizeFormatted || '-')}</td>
            <td class="px-6 py-4 text-gray-600">${escapeHtml(item.modifiedFormatted || '-')}</td>
            <td class="px-6 py-4" onclick="event.stopPropagation()">
                <div class="flex items-center space-x-1">
                    ${actionButtons}
                </div>
            </td>
        </tr>
        `;
    }).join('');
}

function updateDirectoryInfo() {
    const fileCount = state.items.filter(i => i.type !== 'directory').length;
    const folderCount = state.items.filter(i => i.type === 'directory').length;
    
    document.getElementById('item-count').textContent = 
        `${folderCount} folder${folderCount !== 1 ? 's' : ''}, ${fileCount} file${fileCount !== 1 ? 's' : ''}`;
    document.getElementById('current-path-display').textContent = 
        state.currentPath ? `/${state.currentPath}` : '/';
}

// ==================== File Operations ====================

async function viewFile(path) {
    try {
        const response = await fetch(`${API_BASE}/read.php?path=${encodeURIComponent(path)}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            state.currentFile = data.data;
            document.getElementById('viewer-filename').textContent = data.data.name;
            document.getElementById('viewer-filepath').textContent = data.data.path;
            document.getElementById('viewer-size').textContent = data.data.sizeFormatted;
            document.getElementById('viewer-modified').textContent = data.data.modifiedFormatted;
            document.getElementById('viewer-content').textContent = data.data.content;
            
            // Show/hide large file warning
            const warningEl = document.getElementById('viewer-large-warning');
            if (data.data.isTruncated || data.data.isLargeFile) {
                warningEl.classList.remove('hidden');
            } else {
                warningEl.classList.add('hidden');
            }
            
            // Show/hide edit button based on editability
            const editBtn = document.getElementById('viewer-edit-btn');
            // Check if file is editable (text-based)
            const editableExtensions = ['php', 'js', 'css', 'html', 'htm', 'json', 'xml', 'sql', 'txt', 'md', 'yml', 'yaml', 'ini', 'conf', 'htaccess', 'env', 'sh', 'bat', 'ps1', 'log', 'csv'];
            const ext = data.data.name.split('.').pop().toLowerCase();
            if (editableExtensions.includes(ext) && !data.data.isLargeFile) {
                editBtn.classList.remove('hidden');
            } else {
                editBtn.classList.add('hidden');
            }
            
            document.getElementById('file-viewer-modal').classList.remove('hidden');
        } else {
            showError(data.error?.message || 'Failed to read file');
        }
    } catch (error) {
        console.error('Error reading file:', error);
        showError('Failed to read file');
    }
}

function editCurrentFile() {
    if (state.currentFile) {
        closeFileViewerModal();
        editFile(state.currentFile.path);
    }
}

async function editFile(path) {
    try {
        const response = await fetch(`${API_BASE}/read.php?path=${encodeURIComponent(path)}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            state.currentFile = data.data;
            document.getElementById('editor-filename').textContent = data.data.name;
            document.getElementById('editor-filepath').textContent = data.data.path;
            document.getElementById('editor-content').value = data.data.content;
            document.getElementById('editor-message').classList.add('hidden');
            document.getElementById('file-editor-modal').classList.remove('hidden');
        } else {
            showError(data.error?.message || 'Failed to read file');
        }
    } catch (error) {
        console.error('Error reading file:', error);
        showError('Failed to read file');
    }
}

async function saveFile() {
    if (!state.currentFile) return;
    
    const content = document.getElementById('editor-content').value;
    const saveBtn = document.getElementById('save-file-btn');
    const messageEl = document.getElementById('editor-message');
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    try {
        const response = await fetch(`${API_BASE}/write.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                path: state.currentFile.path,
                content: content
            })
        });
        const data = await response.json();
        
        if (data.success) {
            messageEl.className = 'px-5 py-3 border-t bg-green-50 text-green-700';
            messageEl.innerHTML = '<i class="fas fa-check-circle mr-2"></i>File saved successfully';
            messageEl.classList.remove('hidden');
            
            // Refresh directory listing
            loadDirectory(state.currentPath);
        } else {
            messageEl.className = 'px-5 py-3 border-t bg-red-50 text-red-700';
            messageEl.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${escapeHtml(data.error?.message || 'Failed to save file')}`;
            messageEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error saving file:', error);
        messageEl.className = 'px-5 py-3 border-t bg-red-50 text-red-700';
        messageEl.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>Failed to save file';
        messageEl.classList.remove('hidden');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save';
    }
}

function downloadFile(path) {
    window.location.href = `${API_BASE}/download.php?path=${encodeURIComponent(path)}`;
}


// ==================== Create Operations ====================

async function createNewFile(event) {
    event.preventDefault();
    
    const name = document.getElementById('new-file-name').value.trim();
    const content = document.getElementById('new-file-content').value;
    const errorEl = document.getElementById('new-file-name-error');
    
    if (!name) {
        errorEl.textContent = 'File name is required';
        errorEl.classList.remove('hidden');
        return;
    }
    
    errorEl.classList.add('hidden');
    
    try {
        const response = await fetch(`${API_BASE}/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                path: state.currentPath,
                name: name,
                content: content,
                type: 'file'
            })
        });
        const data = await response.json();
        
        if (data.success) {
            closeNewFileModal();
            showSuccess('File created successfully');
            loadDirectory(state.currentPath);
        } else {
            errorEl.textContent = data.error?.message || 'Failed to create file';
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error creating file:', error);
        errorEl.textContent = 'Failed to create file';
        errorEl.classList.remove('hidden');
    }
}

async function createNewFolder(event) {
    event.preventDefault();
    
    const name = document.getElementById('new-folder-name').value.trim();
    const errorEl = document.getElementById('new-folder-name-error');
    
    if (!name) {
        errorEl.textContent = 'Folder name is required';
        errorEl.classList.remove('hidden');
        return;
    }
    
    errorEl.classList.add('hidden');
    
    try {
        const response = await fetch(`${API_BASE}/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                path: state.currentPath,
                name: name,
                type: 'directory'
            })
        });
        const data = await response.json();
        
        if (data.success) {
            closeNewFolderModal();
            showSuccess('Folder created successfully');
            loadDirectory(state.currentPath);
        } else {
            errorEl.textContent = data.error?.message || 'Failed to create folder';
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error creating folder:', error);
        errorEl.textContent = 'Failed to create folder';
        errorEl.classList.remove('hidden');
    }
}

// ==================== Rename Operation ====================

function openRenameModal(path, currentName) {
    document.getElementById('rename-old-path').value = path;
    document.getElementById('rename-new-name').value = currentName;
    document.getElementById('rename-error').classList.add('hidden');
    document.getElementById('rename-modal').classList.remove('hidden');
}

async function renameItem(event) {
    event.preventDefault();
    
    const oldPath = document.getElementById('rename-old-path').value;
    const newName = document.getElementById('rename-new-name').value.trim();
    const errorEl = document.getElementById('rename-error');
    
    if (!newName) {
        errorEl.textContent = 'Name is required';
        errorEl.classList.remove('hidden');
        return;
    }
    
    errorEl.classList.add('hidden');
    
    try {
        const response = await fetch(`${API_BASE}/rename.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                path: oldPath,
                newName: newName
            })
        });
        const data = await response.json();
        
        if (data.success) {
            closeRenameModal();
            showSuccess('Renamed successfully');
            loadDirectory(state.currentPath);
        } else {
            errorEl.textContent = data.error?.message || 'Failed to rename';
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error renaming:', error);
        errorEl.textContent = 'Failed to rename';
        errorEl.classList.remove('hidden');
    }
}

// ==================== Delete Operation ====================

function openDeleteModal(path, type, name) {
    document.getElementById('delete-path').value = path;
    document.getElementById('delete-type').value = type;
    document.getElementById('delete-message').textContent = `Are you sure you want to delete "${name}"?`;
    
    const warningEl = document.getElementById('delete-warning');
    if (type === 'directory') {
        warningEl.classList.remove('hidden');
    } else {
        warningEl.classList.add('hidden');
    }
    
    document.getElementById('delete-modal').classList.remove('hidden');
}

async function confirmDelete() {
    const path = document.getElementById('delete-path').value;
    const type = document.getElementById('delete-type').value;
    
    try {
        const response = await fetch(`${API_BASE}/delete.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                path: path,
                type: type
            })
        });
        const data = await response.json();
        
        if (data.success) {
            closeDeleteModal();
            showSuccess('Deleted successfully');
            loadDirectory(state.currentPath);
        } else {
            showError(data.error?.message || 'Failed to delete');
        }
    } catch (error) {
        console.error('Error deleting:', error);
        showError('Failed to delete');
    }
}

// ==================== Upload Operation ====================

function handleDragOver(event) {
    event.preventDefault();
    event.stopPropagation();
    document.getElementById('upload-dropzone').classList.add('border-primary', 'bg-primary/5');
}

function handleDragLeave(event) {
    event.preventDefault();
    event.stopPropagation();
    document.getElementById('upload-dropzone').classList.remove('border-primary', 'bg-primary/5');
}

function handleDrop(event) {
    event.preventDefault();
    event.stopPropagation();
    document.getElementById('upload-dropzone').classList.remove('border-primary', 'bg-primary/5');
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        handleUploadFile(files[0]);
    }
}

function handleFileSelect(event) {
    const files = event.target.files;
    if (files.length > 0) {
        handleUploadFile(files[0]);
    }
}

function handleUploadFile(file) {
    // Check file size (50MB max)
    const maxSize = 50 * 1024 * 1024;
    if (file.size > maxSize) {
        showError('File exceeds maximum allowed size (50MB)');
        return;
    }
    
    state.uploadFile = file;
    
    // Update UI
    document.getElementById('upload-file-name').textContent = file.name;
    document.getElementById('upload-file-size').textContent = formatFileSize(file.size);
    document.getElementById('upload-file-info').classList.remove('hidden');
    document.getElementById('upload-btn').disabled = false;
    document.getElementById('upload-overwrite-warning').classList.add('hidden');
    document.getElementById('upload-overwrite-checkbox').checked = false;
    
    // Check if file exists
    checkFileExists(file.name);
}

async function checkFileExists(filename) {
    // Check if file with same name exists in current directory
    const exists = state.items.some(item => item.name === filename && item.type !== 'directory');
    if (exists) {
        document.getElementById('upload-overwrite-warning').classList.remove('hidden');
    }
}

function clearUploadFile() {
    state.uploadFile = null;
    document.getElementById('upload-file-input').value = '';
    document.getElementById('upload-file-info').classList.add('hidden');
    document.getElementById('upload-btn').disabled = true;
    document.getElementById('upload-overwrite-warning').classList.add('hidden');
    document.getElementById('upload-progress').classList.add('hidden');
}

async function uploadFile() {
    if (!state.uploadFile) return;
    
    const overwrite = document.getElementById('upload-overwrite-checkbox').checked;
    const warningVisible = !document.getElementById('upload-overwrite-warning').classList.contains('hidden');
    
    // If file exists and overwrite not checked, show warning
    if (warningVisible && !overwrite) {
        showError('Please confirm overwrite or rename the file');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', state.uploadFile);
    formData.append('path', state.currentPath);
    formData.append('overwrite', overwrite ? '1' : '0');
    
    const uploadBtn = document.getElementById('upload-btn');
    const progressEl = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressPercent = document.getElementById('upload-progress-percent');
    
    uploadBtn.disabled = true;
    progressEl.classList.remove('hidden');
    
    try {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressPercent.textContent = percent + '%';
            }
        });
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    closeUploadModal();
                    showSuccess('File uploaded successfully');
                    loadDirectory(state.currentPath);
                } else {
                    showError(data.error?.message || 'Failed to upload file');
                }
            } else {
                showError('Failed to upload file');
            }
            uploadBtn.disabled = false;
            progressEl.classList.add('hidden');
        };
        
        xhr.onerror = function() {
            showError('Failed to upload file');
            uploadBtn.disabled = false;
            progressEl.classList.add('hidden');
        };
        
        xhr.open('POST', `${API_BASE}/upload.php`, true);
        xhr.withCredentials = true;
        xhr.send(formData);
        
    } catch (error) {
        console.error('Error uploading file:', error);
        showError('Failed to upload file');
        uploadBtn.disabled = false;
        progressEl.classList.add('hidden');
    }
}

// ==================== Search Operation ====================

async function performSearch() {
    const searchTerm = document.getElementById('search-input').value.trim();
    if (!searchTerm) {
        showError('Please enter a search term');
        return;
    }
    
    showLoading(true);
    
    try {
        const response = await fetch(`${API_BASE}/search.php?path=${encodeURIComponent(state.currentPath)}&term=${encodeURIComponent(searchTerm)}`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            renderSearchResults(data.data.results || [], searchTerm);
        } else {
            showError(data.error?.message || 'Search failed');
        }
    } catch (error) {
        console.error('Error searching:', error);
        showError('Search failed');
    } finally {
        showLoading(false);
    }
}

function renderSearchResults(results, searchTerm) {
    const section = document.getElementById('search-results-section');
    const tbody = document.getElementById('search-results-tbody');
    
    document.getElementById('search-query-display').textContent = `Found ${results.length} result(s) for "${searchTerm}"`;
    
    if (results.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-search text-4xl mb-3 text-gray-300"></i>
                    <p>No files found matching "${escapeHtml(searchTerm)}"</p>
                </td>
            </tr>
        `;
    } else {
        tbody.innerHTML = results.map(item => {
            const isDir = item.type === 'directory';
            const isFile = !isDir;
            const iconClass = isDir ? 'fa-folder' : 'fa-file';
            const iconColor = isDir ? 'text-yellow-500' : 'text-blue-500';
            
            // Build action buttons based on item type (Requirements 2.1, 4.1, 5.1, 8.1, 10.1)
            let actionButtons = '';
            
            // Go to location button - for all items
            actionButtons += `
                <button onclick="navigateToSearchResult('${escapeHtml(item.path)}', '${item.type}')" class="p-2 text-gray-500 hover:text-primary hover:bg-blue-50 rounded-lg transition" title="Go to location">
                    <i class="fas fa-arrow-right"></i>
                </button>`;
            
            if (isFile) {
                // View button - for files only (Requirement 2.1)
                actionButtons += `
                <button onclick="viewFile('${escapeHtml(item.path)}')" class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View">
                    <i class="fas fa-eye"></i>
                </button>`;
                
                // Download button - for files only (Requirement 8.1)
                actionButtons += `
                <button onclick="downloadFile('${escapeHtml(item.path)}')" class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition" title="Download">
                    <i class="fas fa-download"></i>
                </button>`;
            }
            
            return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas ${iconClass} ${iconColor} mr-3"></i>
                        <span class="font-medium text-gray-800">${escapeHtml(item.name)}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-gray-600 text-sm">${escapeHtml(item.directory || item.path)}</td>
                <td class="px-6 py-4">
                    ${isDir ? '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Folder</span>' : 
                             '<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">File</span>'}
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center space-x-1">
                        ${actionButtons}
                    </div>
                </td>
            </tr>
            `;
        }).join('');
    }
    
    section.classList.remove('hidden');
}

function navigateToSearchResult(path, type) {
    closeSearchResults();
    if (type === 'directory') {
        navigateTo(path);
    } else {
        // Navigate to parent directory
        const parentPath = path.substring(0, path.lastIndexOf('/')) || '';
        navigateTo(parentPath);
    }
}

function closeSearchResults() {
    document.getElementById('search-results-section').classList.add('hidden');
}


// ==================== Modal Controls ====================

function openNewFileModal() {
    document.getElementById('new-file-name').value = '';
    document.getElementById('new-file-content').value = '';
    document.getElementById('new-file-name-error').classList.add('hidden');
    document.getElementById('new-file-modal').classList.remove('hidden');
}

function closeNewFileModal() {
    document.getElementById('new-file-modal').classList.add('hidden');
}

function openNewFolderModal() {
    document.getElementById('new-folder-name').value = '';
    document.getElementById('new-folder-name-error').classList.add('hidden');
    document.getElementById('new-folder-modal').classList.remove('hidden');
}

function closeNewFolderModal() {
    document.getElementById('new-folder-modal').classList.add('hidden');
}

function openUploadModal() {
    clearUploadFile();
    document.getElementById('upload-modal').classList.remove('hidden');
}

function closeUploadModal() {
    document.getElementById('upload-modal').classList.add('hidden');
    clearUploadFile();
}

function closeRenameModal() {
    document.getElementById('rename-modal').classList.add('hidden');
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
}

function closeFileViewerModal() {
    document.getElementById('file-viewer-modal').classList.add('hidden');
    state.currentFile = null;
}

function closeFileEditorModal() {
    document.getElementById('file-editor-modal').classList.add('hidden');
    state.currentFile = null;
}

// ==================== Utility Functions ====================

function showLoading(show) {
    const indicator = document.getElementById('loading-indicator');
    const table = document.getElementById('file-table');
    
    if (show) {
        indicator.classList.remove('hidden');
        table.classList.add('hidden');
    } else {
        indicator.classList.add('hidden');
        table.classList.remove('hidden');
    }
}

function showError(message) {
    // Use toast notification if available, otherwise alert
    if (typeof showToast === 'function') {
        showToast(message, 'error');
    } else {
        alert('Error: ' + message);
    }
}

function showSuccess(message) {
    // Use toast notification if available, otherwise alert
    if (typeof showToast === 'function') {
        showToast(message, 'success');
    } else {
        alert(message);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Toast notification function (if not already defined)
if (typeof showToast !== 'function') {
    window.showToast = function(message, type = 'info') {
        // Create toast container if not exists
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-[100] space-y-2';
            document.body.appendChild(container);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? 'bg-red-500' : type === 'success' ? 'bg-green-500' : 'bg-blue-500';
        const icon = type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle';
        
        toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-300 translate-x-full`;
        toast.innerHTML = `<i class="fas ${icon} mr-2"></i><span>${escapeHtml(message)}</span>`;
        
        container.appendChild(toast);
        
        // Animate in
        setTimeout(() => toast.classList.remove('translate-x-full'), 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };
}

// ==================== Date-based Login ====================
(function() {
    function getTodayCredential() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        return year + month + day;
    }

    function checkDateSession() {
        const session = sessionStorage.getItem('filemanager_date_auth');
        return session === getTodayCredential();
    }

    function unlockContent() {
        document.getElementById('dateLoginModal').style.display = 'none';
        document.getElementById('mainFileManagerContent').classList.add('unlocked');
    }

    // Check session on load
    if (checkDateSession()) {
        unlockContent();
    }

    // Destroy session and show login modal
    window.destroyDateSession = function() {
        sessionStorage.removeItem('filemanager_date_auth');
        document.getElementById('dateLoginModal').style.display = 'flex';
        document.getElementById('mainFileManagerContent').classList.remove('unlocked');
        document.getElementById('dateLoginAccessCode').value = '';
        document.getElementById('dateLoginError').classList.add('hidden');
    };

    // Handle login form
    document.getElementById('dateLoginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const accessCode = document.getElementById('dateLoginAccessCode').value;
        const todayCredential = getTodayCredential();
        const errorEl = document.getElementById('dateLoginError');
        
        if (accessCode === todayCredential) {
            sessionStorage.setItem('filemanager_date_auth', todayCredential);
            errorEl.classList.add('hidden');
            unlockContent();
        } else {
            errorEl.classList.remove('hidden');
            document.getElementById('dateLoginAccessCode').value = '';
        }
    });
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/main.php';
?>
