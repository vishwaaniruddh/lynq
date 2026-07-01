<?php
/**
 * Bulk Delegation Upload Page
 * 
 * Allows ADV users to delegate multiple sites via Excel file
 * Includes template download and upload results display
 * 
 * Requirements: 2.3
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/DelegationService.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Bulk Delegation Upload';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Bulk Delegation']
];

// Get contractors for reference
$companyRepository = new CompanyRepository();
$contractors = $companyRepository->findContractors();

$uploadResult = null;
$errors = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please try again.';
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $errors[] = 'File size exceeds 5MB limit.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $errors[] = 'Invalid file type. Please upload an Excel or CSV file.';
        } else {
            // Process the file
            $delegationService = new DelegationService();
            $uploadResult = $delegationService->importDelegationsFromExcel(
                $file['tmp_name'],
                $currentUser['id']
            );
        }
    }
}

ob_start();
?>

<div class="max-w-4xl mx-auto">
    <!-- Header Card -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Bulk Delegation Upload</h3>
                <p class="text-sm text-gray-500">Delegate multiple sites to contractors using an Excel file</p>
            </div>
            <div class="flex space-x-3">
                <a href="delegate.php" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition">
                    <i class="fas fa-share-alt mr-2"></i>Manual Delegation
                </a>
                <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sites
                </a>
            </div>
        </div>
    </div>

    <?php if ($uploadResult): ?>
    <!-- Upload Results -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b">
            <h4 class="text-md font-semibold text-gray-800">Upload Results</h4>
        </div>
        <div class="p-6">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-gray-800"><?php echo $uploadResult['totalRows']; ?></p>
                    <p class="text-sm text-gray-500">Total Rows</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-green-600"><?php echo $uploadResult['successCount']; ?></p>
                    <p class="text-sm text-gray-500">Successful</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-red-600"><?php echo $uploadResult['errorCount']; ?></p>
                    <p class="text-sm text-gray-500">Failed</p>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <p class="text-2xl font-bold text-blue-600">
                        <?php echo $uploadResult['totalRows'] > 0 ? round(($uploadResult['successCount'] / $uploadResult['totalRows']) * 100) : 0; ?>%
                    </p>
                    <p class="text-sm text-gray-500">Success Rate</p>
                </div>
            </div>
            
            <!-- Status Message -->
            <?php if ($uploadResult['success']): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($uploadResult['message']); ?>
            </div>
            <?php else: ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo htmlspecialchars($uploadResult['message']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Error Details -->
            <?php if (!empty($uploadResult['errors'])): ?>
            <div class="mt-4">
                <h5 class="font-medium text-gray-700 mb-2">Error Details:</h5>
                <div class="bg-red-50 rounded-lg p-4 max-h-64 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-red-200">
                                <th class="text-left py-2 px-2">Row/Site</th>
                                <th class="text-left py-2 px-2">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uploadResult['errors'] as $row => $rowErrors): ?>
                            <tr class="border-b border-red-100">
                                <td class="py-2 px-2 font-medium"><?php echo is_numeric($row) ? "Row $row" : $row; ?></td>
                                <td class="py-2 px-2 text-red-600">
                                    <?php 
                                    if (is_array($rowErrors)) {
                                        foreach ($rowErrors as $error) {
                                            if (is_array($error)) {
                                                echo htmlspecialchars($error['message'] ?? json_encode($error)) . '<br>';
                                            } else {
                                                echo htmlspecialchars($error) . '<br>';
                                            }
                                        }
                                    } else {
                                        echo htmlspecialchars($rowErrors);
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <!-- Validation Errors -->
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?php foreach ($errors as $error): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Upload Form -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b">
            <h4 class="text-md font-semibold text-gray-800">Upload Excel File</h4>
        </div>
        <div class="p-6">
            <form method="POST" enctype="multipart/form-data" id="upload-form">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary transition" id="drop-zone">
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" class="hidden" required>
                    <div id="upload-placeholder">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 mb-2">Drag and drop your Excel file here, or</p>
                        <button type="button" onclick="document.getElementById('excel_file').click()" 
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                            <i class="fas fa-folder-open mr-2"></i>Browse Files
                        </button>
                        <p class="text-sm text-gray-400 mt-4">Supported formats: .xlsx, .xls, .csv (Max 5MB)</p>
                    </div>
                    <div id="file-selected" class="hidden">
                        <i class="fas fa-file-excel text-4xl text-green-500 mb-4"></i>
                        <p class="text-gray-800 font-medium" id="file-name"></p>
                        <p class="text-sm text-gray-500" id="file-size"></p>
                        <button type="button" onclick="clearFile()" class="mt-2 text-red-500 hover:underline text-sm">
                            <i class="fas fa-times mr-1"></i>Remove
                        </button>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <a href="index.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </a>
                    <button type="submit" id="upload-btn" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition" disabled>
                        <i class="fas fa-upload mr-2"></i>Upload Delegations
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Template Download & Reference -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h4 class="text-md font-semibold text-gray-800">Download Template & Reference</h4>
        </div>
        <div class="p-6">
            <p class="text-gray-600 mb-4">
                Download the Excel template below and fill in your delegation data. Make sure to use valid Site IDs and Contractor IDs.
            </p>
            
            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                <h5 class="font-medium text-blue-800 mb-2"><i class="fas fa-info-circle mr-2"></i>Required Columns:</h5>
                <ul class="text-sm text-blue-700 list-disc list-inside space-y-1">
                    <li><strong>site_id</strong> - The ID of the site to delegate (required)</li>
                    <li><strong>contractor_id</strong> - The ID of the contractor company (required)</li>
                </ul>
            </div>
            
            <!-- Contractor Reference -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h5 class="font-medium text-gray-700 mb-2"><i class="fas fa-building mr-2"></i>Available Contractors:</h5>
                <?php if (empty($contractors)): ?>
                <p class="text-sm text-yellow-600">No contractors available. Please add contractors first.</p>
                <?php else: ?>
                <div class="max-h-48 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 px-2">ID</th>
                                <th class="text-left py-2 px-2">Contractor Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contractors as $contractor): ?>
                            <tr class="border-b border-gray-200">
                                <td class="py-2 px-2 font-mono"><?php echo $contractor['id']; ?></td>
                                <td class="py-2 px-2"><?php echo htmlspecialchars($contractor['name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="flex space-x-3">
                <button onclick="downloadTemplate()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-download mr-2"></i>Download Template
                </button>
                <a href="index.php?export=1" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-file-export mr-2"></i>Export Sites (for IDs)
                </a>
            </div>
        </div>
    </div>
</div>

<script>
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('excel_file');
const uploadBtn = document.getElementById('upload-btn');

// Drag and drop handlers
dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    dropZone.classList.add('border-primary', 'bg-blue-50');
});

dropZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-blue-50');
});

dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-blue-50');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect(files[0]);
    }
});

// File input change handler
fileInput.addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
        handleFileSelect(e.target.files[0]);
    }
});

function handleFileSelect(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'xls', 'csv'].includes(ext)) {
        alert('Please select an Excel or CSV file');
        clearFile();
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        alert('File size exceeds 5MB limit');
        clearFile();
        return;
    }
    
    document.getElementById('upload-placeholder').classList.add('hidden');
    document.getElementById('file-selected').classList.remove('hidden');
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = formatFileSize(file.size);
    uploadBtn.disabled = false;
}

function clearFile() {
    fileInput.value = '';
    document.getElementById('upload-placeholder').classList.remove('hidden');
    document.getElementById('file-selected').classList.add('hidden');
    uploadBtn.disabled = true;
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function downloadTemplate() {
    // Create CSV template
    const headers = ['site_id', 'contractor_id'];
    const sampleRow = ['1', '2'];
    
    const csv = [headers.join(','), sampleRow.join(',')].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'delegation_upload_template.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Form submission loading state
document.getElementById('upload-form').addEventListener('submit', function() {
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
