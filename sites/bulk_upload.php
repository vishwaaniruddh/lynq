<?php
/**
 * Bulk Site Upload Page
 * 
 * Allows ADV users to upload multiple sites via Excel file
 * Includes template download and upload results display
 * 
 * Requirements: 1.2, 1.3
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/BulkOperationService.php';

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
$pageTitle = 'Bulk Site Upload';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Bulk Upload']
];

$uploadResult = null;
$errors = [];

// Check if ZipArchive extension is available (required for xlsx files)
$zipArchiveAvailable = class_exists('ZipArchive');

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
            $errors[] = 'Invalid file type. Please upload an Excel file (.xlsx, .xls) or CSV file (.csv).';
        } elseif ($ext === 'xlsx' && !$zipArchiveAvailable) {
            $errors[] = 'Cannot process .xlsx files: PHP ZipArchive extension is not enabled. Please enable it in php.ini or use .csv format instead.';
        } else {
            try {
                // Process the file
                $siteService = new SiteService();
                $uploadResult = $siteService->importFromExcel(
                    $file['tmp_name'],
                    $currentUser['company_id'],
                    $currentUser['id'],
                    $file['name'] // Pass original filename for logging
                );
            } catch (Error $e) {
                if (strpos($e->getMessage(), 'ZipArchive') !== false) {
                    $errors[] = 'Cannot process Excel file: PHP ZipArchive extension is not enabled. Please enable it in php.ini (uncomment extension=zip) and restart Apache, or use CSV format instead.';
                } else {
                    $errors[] = 'Error processing file: ' . $e->getMessage();
                }
            } catch (Exception $e) {
                $errors[] = 'Error processing file: ' . $e->getMessage();
            }
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
                <h3 class="text-lg font-semibold text-gray-800">Bulk Site Upload</h3>
                <p class="text-sm text-gray-500">Upload multiple sites using an Excel file</p>
            </div>
            <div class="flex gap-3">
                <a href="bulk_upload_history.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-history mr-2"></i>Upload History
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
                                <th class="text-left py-2 px-2">Row</th>
                                <th class="text-left py-2 px-2">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uploadResult['errors'] as $row => $rowErrors): ?>
                            <tr class="border-b border-red-100">
                                <td class="py-2 px-2 font-medium"><?php echo $row; ?></td>
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
            
            <!-- Download Links -->
            <?php if (!empty($uploadResult['logId'])): 
                require_once __DIR__ . '/../services/BulkUploadLogService.php';
                $logService = new BulkUploadLogService();
                $log = $logService->getLog($uploadResult['logId']);
            ?>
            <div class="mt-6 pt-4 border-t">
                <h5 class="font-medium text-gray-700 mb-3"><i class="fas fa-download mr-2"></i>Download Results:</h5>
                <div class="flex flex-wrap gap-3">
                    <?php if (!empty($log['success_file']) && $logService->fileExists($log['success_file'])): ?>
                    <a href="download_bulk_log.php?id=<?php echo $log['id']; ?>&type=success" 
                       class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition inline-flex items-center">
                        <i class="fas fa-file-excel mr-2"></i>Download Success Records (<?php echo $uploadResult['successCount']; ?>)
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($log['error_file']) && $logService->fileExists($log['error_file'])): ?>
                    <a href="download_bulk_log.php?id=<?php echo $log['id']; ?>&type=error" 
                       class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition inline-flex items-center">
                        <i class="fas fa-file-excel mr-2"></i>Download Error Records (<?php echo $uploadResult['errorCount']; ?>)
                    </a>
                    <?php endif; ?>
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
                        <?php if (!$zipArchiveAvailable): ?>
                        <p class="text-sm text-yellow-600 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Note: .xlsx files require PHP ZipArchive extension. Use .csv format if upload fails.</p>
                        <?php endif; ?>
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
                        <i class="fas fa-upload mr-2"></i>Upload Sites
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Template Download -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h4 class="text-md font-semibold text-gray-800">Download Template</h4>
        </div>
        <div class="p-6">
            <p class="text-gray-600 mb-4">
                Download the Excel template below and fill in your site data. Make sure to follow the column format exactly.
            </p>
            
            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                <h5 class="font-medium text-blue-800 mb-2"><i class="fas fa-info-circle mr-2"></i>Required Columns:</h5>
                <ul class="text-sm text-blue-700 list-disc list-inside space-y-1">
                    <li><strong>site_name</strong> - Unique name for the site</li>
                    <li><strong>lho</strong> - Local Head Office - must exist in LHO Master</li>
                    <li><strong>city</strong> - City name - must exist in City Master</li>
                    <li><strong>state</strong> - State name - must exist in State Master</li>
                    <li><strong>country</strong> - Country name - must exist in Country Master</li>
                </ul>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h5 class="font-medium text-gray-700 mb-2"><i class="fas fa-list mr-2"></i>Optional Columns (validated if provided):</h5>
                <ul class="text-sm text-gray-600 list-disc list-inside space-y-1">
                    <li><strong>bank_name</strong> - Associated bank name - if provided, must exist in Bank Master</li>
                    <li><strong>customer_name</strong> - Customer name - if provided, must exist in Customer Master</li>
                    <li><strong>zone</strong> - Zone/region - if provided, must exist in Zone Master</li>
                    <li><strong>address</strong> - Full address (free text)</li>
                    <li><strong>latitude</strong> - GPS latitude (-90 to 90)</li>
                    <li><strong>longitude</strong> - GPS longitude (-180 to 180)</li>
                    <li><strong>status</strong> - active or inactive (default: active)</li>
                </ul>
            </div>
            
            <div class="bg-yellow-50 rounded-lg p-4 mb-4">
                <h5 class="font-medium text-yellow-800 mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Important Notes:</h5>
                <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                    <li>Required columns must always be filled and must exist in their respective masters</li>
                    <li>Optional columns can be left empty, but if filled, they must exist in their respective masters</li>
                    <li>City must belong to the specified State, and State must belong to the specified Country</li>
                    <li>If a master record doesn't exist, please add it first before uploading sites</li>
                    <li>Names are case-sensitive and must match exactly with master records</li>
                </ul>
            </div>
            
            <button onclick="downloadTemplate()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-download mr-2"></i>Download Template
            </button>
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
        alert('Please select an Excel file (.xlsx, .xls) or CSV file (.csv)');
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
    // Create CSV template (compatible with Excel)
    const headers = ['site_name', 'lho', 'bank_name', 'customer_name', 'city', 'state', 'country', 'zone', 'address', 'latitude', 'longitude', 'status'];
    const sampleRow = ['Sample Site 1', 'Sample LHO', 'Sample Bank', 'Sample Customer', 'Mumbai', 'Maharashtra', 'India', 'West', '123 Main Street', '19.0760', '72.8777', 'active'];
    
    // Add BOM for Excel to recognize UTF-8
    const BOM = '\uFEFF';
    const csv = BOM + [headers.join(','), sampleRow.join(',')].join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'site_upload_template.csv';
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
