<?php
/**
 * Dynamic Bulk Site Upload (Project Based)
 * 
 * URL: https://localhost/sites/bulk_upload_dynamic.php
 * 
 * Allows ADV users to upload sites for a specific project.
 * Automatically dynamically populates custom field schema columns based on project selection,
 * generates project-specific template downloads, and processes custom fields into custom_fields_json.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/SiteService.php';
require_once __DIR__ . '/../services/BulkOperationService.php';
require_once __DIR__ . '/../services/ProjectService.php';
require_once __DIR__ . '/../models/CustomForm.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Dynamic Bulk Site Upload (Project Based)';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Dynamic Bulk Upload']
];

$projectService = new ProjectService();
$projectsRes = $projectService->getAll(['status' => 1]);
$projects = $projectsRes['data'] ?? [];

$dbInstance = DatabaseConfig::getInstance();
$sampleCountryRes = $dbInstance->getResults("SELECT name FROM countries WHERE status = 'active' LIMIT 1");
$sampleCountry = !empty($sampleCountryRes) ? $sampleCountryRes[0]['name'] : 'India';

$sampleStateRes = $dbInstance->getResults("SELECT name FROM states WHERE status = 'active' LIMIT 1");
$sampleState = !empty($sampleStateRes) ? $sampleStateRes[0]['name'] : 'Maharashtra';

$sampleCityRes = $dbInstance->getResults("SELECT name FROM cities WHERE status = 'active' LIMIT 1");
$sampleCity = !empty($sampleCityRes) ? $sampleCityRes[0]['name'] : 'Mumbai';

$sampleBankRes = $dbInstance->getResults("SELECT name FROM banks WHERE status = 1 LIMIT 1");
$sampleBank = !empty($sampleBankRes) ? $sampleBankRes[0]['name'] : '';

$sampleCustomerRes = $dbInstance->getResults("SELECT name FROM customers WHERE status = 1 LIMIT 1");
$sampleCustomer = !empty($sampleCustomerRes) ? $sampleCustomerRes[0]['name'] : '';

$sampleLhoRes = $dbInstance->getResults("SELECT lho_name FROM lhos WHERE status = 'active' LIMIT 1");
$sampleLho = !empty($sampleLhoRes) ? $sampleLhoRes[0]['lho_name'] : '';

$sampleZoneRes = $dbInstance->getResults("SELECT name FROM zones WHERE status = 'active' LIMIT 1");
$sampleZone = !empty($sampleZoneRes) ? $sampleZoneRes[0]['name'] : '';

$uploadResult = null;
$errors = [];
$selectedProjectId = $_POST['project_id'] ?? ($_GET['project_id'] ?? null);

$zipArchiveAvailable = class_exists('ZipArchive');

// Handle File Upload POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $file = $_FILES['excel_file'];
    
    if (!$projectId) {
        $errors[] = 'Target Project selection is required for dynamic bulk upload.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please try again.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'File size exceeds 5MB limit.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $errors[] = 'Invalid file format. Please upload an Excel file (.xlsx, .xls) or CSV file (.csv).';
        } elseif ($ext === 'xlsx' && !$zipArchiveAvailable) {
            $errors[] = 'Cannot process .xlsx files: PHP ZipArchive extension is not enabled. Please use .csv format instead.';
        } else {
            try {
                $siteService = new SiteService();
                $customFormModel = new CustomForm();
                $customSchema = $customFormModel->findFormForProject('site_add', $projectId);
                $customFields = $customSchema['fields'] ?? [];
                
                // Parse rows from file
                $bulkService = new BulkOperationService();
                
                // Read CSV/Excel rows manually to capture dynamic columns
                $rawRows = parseDynamicUploadedFile($file['tmp_name'], $ext);
                
                if (empty($rawRows)) {
                    $errors[] = 'The uploaded file is empty or could not be parsed.';
                } else {
                    $uploadResult = processDynamicSiteImport(
                        $rawRows,
                        $projectId,
                        $customFields,
                        $currentUser['company_id'],
                        $currentUser['id'],
                        $file['name']
                    );
                }
            } catch (Exception $e) {
                $errors[] = 'Error processing file: ' . $e->getMessage();
            }
        }
    }
}

/**
 * Parse CSV / Excel rows into structured key-value maps
 */
function parseDynamicUploadedFile(string $filePath, string $ext): array {
    $rows = [];
    if ($ext === 'csv') {
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Check for BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            
            $headers = null;
            $rowNum = 1;
            while (($data = fgetcsv($handle, 2048, ',')) !== false) {
                if ($rowNum === 1) {
                    $headers = array_map(function($h) {
                        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $h))));
                    }, $data);
                } else {
                    if (empty($data) || (count($data) === 1 && trim($data[0]) === '')) {
                        $rowNum++;
                        continue;
                    }
                    $rowMap = ['_row_number' => $rowNum];
                    foreach ($headers as $idx => $colName) {
                        if (empty($colName)) continue;
                        $rowMap[$colName] = isset($data[$idx]) ? trim($data[$idx]) : '';
                    }
                    $rows[] = $rowMap;
                }
                $rowNum++;
            }
            fclose($handle);
        }
    } else {
        require_once __DIR__ . '/../utils/SimpleXLSX.php';
        $xlsx = SimpleXLSX::parse($filePath);
        if ($xlsx) {
            $rawRows = $xlsx->rows();
            if (!empty($rawRows)) {
                $headers = array_map(function($h) {
                    return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $h))));
                }, $rawRows[0]);

                for ($i = 1; $i < count($rawRows); $i++) {
                    $data = $rawRows[$i];
                    $hasData = false;
                    foreach ($data as $cellVal) {
                        if ($cellVal !== '' && $cellVal !== null) {
                            $hasData = true;
                            break;
                        }
                    }
                    if (!$hasData) continue;

                    $rowMap = ['_row_number' => $i + 1];
                    foreach ($headers as $idx => $colName) {
                        if (empty($colName)) continue;
                        $rowMap[$colName] = isset($data[$idx]) ? trim((string)$data[$idx]) : '';
                    }
                    $rows[] = $rowMap;
                }
            }
        }
    }
    return $rows;
}

/**
 * Process Dynamic Site Import with custom_fields_json support
 */
function processDynamicSiteImport(array $rawRows, int $projectId, array $customFields, int $companyId, int $createdBy, string $originalFilename): array {
    $siteService = new SiteService();
    $db = DatabaseConfig::getInstance();
    
    $totalRows = count($rawRows);
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $createdIds = [];
    $successRecords = [];
    $errorRecords = [];
    
    // Valid standard site attributes
    $standardKeys = ['site_name', 'lho', 'bank_name', 'customer_name', 'city', 'state', 'country', 'zone', 'address', 'latitude', 'longitude', 'status'];
    
    // Map of custom field keys
    $customFieldKeys = array_map(function($f) { return $f['field_key']; }, $customFields);
    
    foreach ($rawRows as $idx => $row) {
        $rowNum = $row['_row_number'] ?? ($idx + 2);
        unset($row['_row_number']);
        
        $siteData = [
            'project_id' => $projectId,
            'company_id' => $companyId,
            'status' => !empty($row['status']) ? strtolower(trim($row['status'])) : 'active'
        ];
        
        $customData = [];
        
        foreach ($row as $k => $v) {
            $cleanKey = strtolower(trim($k));
            if (in_array($cleanKey, $standardKeys)) {
                $siteData[$cleanKey] = $v;
            } else {
                // Check if key starts with custom_ or matches a custom field key
                $actualKey = strpos($cleanKey, 'custom_') === 0 ? substr($cleanKey, 7) : $cleanKey;
                if ($v !== '' && $v !== null) {
                    $customData[$actualKey] = $v;
                }
            }
        }
        
        if (!empty($customData)) {
            $siteData['custom_fields_json'] = json_encode($customData, JSON_UNESCAPED_UNICODE);
        }
        
        // Master Data & Required Field Validation
        $valResult = $siteService->validateSiteRowForImport($siteData, $companyId);
        if (!$valResult['isValid']) {
            $errorCount++;
            $errors[$rowNum] = $valResult['errors'];
            $errorRecords[] = array_merge(['row_number' => $rowNum, '_errors' => $valResult['errors']], $row);
            continue;
        }

        // Attempt creation
        $res = $siteService->createSite($siteData, $createdBy);
        
        if ($res['success']) {
            $successCount++;
            $createdIds[] = $res['data']['id'];
            $successRecords[] = array_merge(['row_number' => $rowNum], $siteData);
        } else {
            $errorCount++;
            $rowErr = isset($res['errors']) ? $res['errors'] : [$res['message']];
            $errors[$rowNum] = $rowErr;
            $errorRecords[] = array_merge(['row_number' => $rowNum, '_errors' => $rowErr], $row);
        }
    }
    
    // Log Bulk Upload
    $logId = null;
    try {
        require_once __DIR__ . '/../services/BulkUploadLogService.php';
        $logService = new BulkUploadLogService();
        
        $headers = array_merge($standardKeys, array_map(function($k) { return "custom_$k"; }, $customFieldKeys));
        
        $log = $logService->logUpload([
            'upload_type' => 'sites_dynamic',
            'original_filename' => $originalFilename,
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'success_records' => $successRecords,
            'error_records' => $errorRecords,
            'uploaded_by' => $createdBy,
            'company_id' => $companyId,
            'column_headers' => $headers
        ]);
        $logId = $log['id'] ?? null;
    } catch (Exception $e) {
        error_log("Failed logging dynamic bulk upload: " . $e->getMessage());
    }
    
    return [
        'success' => $errorCount === 0,
        'message' => "Imported {$successCount} of {$totalRows} sites for project",
        'totalRows' => $totalRows,
        'successCount' => $successCount,
        'errorCount' => $errorCount,
        'errors' => $errors,
        'createdIds' => $createdIds,
        'logId' => $logId
    ];
}

ob_start();
?>

<div class="max-w-5xl mx-auto space-y-6">
    
    <!-- Top Bar & Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-xl shadow-md">
                <i class="fas fa-file-csv"></i>
            </span>
            <div>
                <h3 class="text-lg font-bold text-gray-800">Dynamic Bulk Site Upload</h3>
                <p class="text-xs text-gray-500">Upload sites with project-specific custom fields format dynamically populated</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="bulk_upload.php" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-xs font-semibold flex items-center">
                <i class="fas fa-file-excel mr-1.5"></i>Standard Bulk Upload
            </a>
            <a href="bulk_upload_history.php" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-xs font-semibold flex items-center">
                <i class="fas fa-history mr-1.5"></i>Upload History
            </a>
            <a href="index.php" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-xs font-semibold flex items-center">
                <i class="fas fa-arrow-left mr-1.5"></i>Back to Sites
            </a>
        </div>
    </div>

    <!-- Upload Results Banner -->
    <?php if ($uploadResult): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b bg-gray-50/50 flex items-center justify-between">
            <h4 class="text-sm font-bold text-gray-800">Upload Processing Results</h4>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $uploadResult['success'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'; ?>">
                <?php echo $uploadResult['success'] ? 'Completed Clean' : 'Completed with Errors'; ?>
            </span>
        </div>
        <div class="p-6 space-y-6 text-xs">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 p-4 rounded-xl text-center border border-gray-100">
                    <p class="text-2xl font-black text-gray-800"><?php echo $uploadResult['totalRows']; ?></p>
                    <p class="text-[11px] text-gray-500 font-semibold uppercase tracking-wider mt-0.5">Total Rows</p>
                </div>
                <div class="bg-green-50 p-4 rounded-xl text-center border border-green-100">
                    <p class="text-2xl font-black text-green-600"><?php echo $uploadResult['successCount']; ?></p>
                    <p class="text-[11px] text-green-700 font-semibold uppercase tracking-wider mt-0.5">Successful</p>
                </div>
                <div class="bg-rose-50 p-4 rounded-xl text-center border border-rose-100">
                    <p class="text-2xl font-black text-rose-600"><?php echo $uploadResult['errorCount']; ?></p>
                    <p class="text-[11px] text-rose-700 font-semibold uppercase tracking-wider mt-0.5">Failed</p>
                </div>
                <div class="bg-indigo-50 p-4 rounded-xl text-center border border-indigo-100">
                    <p class="text-2xl font-black text-indigo-600">
                        <?php echo $uploadResult['totalRows'] > 0 ? round(($uploadResult['successCount'] / $uploadResult['totalRows']) * 100) : 0; ?>%
                    </p>
                    <p class="text-[11px] text-indigo-700 font-semibold uppercase tracking-wider mt-0.5">Success Rate</p>
                </div>
            </div>

            <?php if (!empty($uploadResult['errors'])): ?>
            <div class="space-y-2">
                <h5 class="font-bold text-gray-800">Failed Row Details:</h5>
                <div class="bg-rose-50/50 rounded-xl p-4 border border-rose-100 max-h-60 overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead>
                            <tr class="border-b border-rose-200 font-bold text-rose-900">
                                <th class="py-2 px-2 w-16">Row #</th>
                                <th class="py-2 px-2">Validation Errors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rose-100">
                            <?php foreach ($uploadResult['errors'] as $rowNo => $rowErrs): ?>
                            <tr>
                                <td class="py-2 px-2 font-bold text-rose-900"><?php echo $rowNo; ?></td>
                                <td class="py-2 px-2 text-rose-700">
                                    <?php 
                                    if (is_array($rowErrs)) {
                                        foreach ($rowErrs as $e) {
                                            echo htmlspecialchars(is_array($e) ? ($e['message'] ?? json_encode($e)) : $e) . '<br>';
                                        }
                                    } else {
                                        echo htmlspecialchars($rowErrs);
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
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl text-xs">
        <i class="fas fa-exclamation-circle mr-1.5"></i>
        <?php foreach ($errors as $err): ?>
            <span><?php echo htmlspecialchars($err); ?></span><br>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Upload & Format Builder Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <form method="POST" enctype="multipart/form-data" id="dynamic-upload-form" class="p-8 space-y-8 text-xs">
            
            <!-- Step 1: Select Target Project -->
            <div class="border-b border-gray-100 pb-6 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs">1</span>
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Select Target Project</h4>
                </div>

                <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100 space-y-2">
                    <label for="project_id" class="block font-bold text-indigo-900 uppercase tracking-wider text-[11px]">
                        Target Project Scope <span class="text-red-500">*</span>
                    </label>
                    <select id="project_id" name="project_id" onchange="onProjectChange(this.value)" required
                        class="w-full px-4 py-2.5 bg-white border border-indigo-200 rounded-lg focus:ring-2 focus:ring-primary text-xs font-bold text-gray-800">
                        <option value="">-- Choose Project (e.g. XTPL) --</option>
                        <?php foreach ($projects as $proj): ?>
                        <option value="<?php echo $proj['id']; ?>" <?php echo (string)$selectedProjectId === (string)$proj['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($proj['name'] . ($proj['code'] ? " ({$proj['code']})" : '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-indigo-700 flex items-center gap-1">
                        <i class="fas fa-magic"></i> Selecting a project dynamically populates its required custom columns and generates the project Excel/CSV template.
                    </p>
                </div>
            </div>

            <!-- Step 2: Dynamic Format & Template Download -->
            <div class="border-b border-gray-100 pb-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-purple-600 text-white font-bold flex items-center justify-center text-xs">2</span>
                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Dynamic Project File Format</h4>
                    </div>
                    <button type="button" id="btn-download-template" onclick="downloadDynamicTemplate()" disabled
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition font-bold text-xs shadow-sm flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-file-excel mr-1.5"></i>Download Project Template (.xlsx)
                    </button>
                </div>

                <!-- Format Columns Overview Box -->
                <div id="format-preview-box" class="bg-gray-50/80 p-5 rounded-xl border border-gray-200 space-y-3">
                    <p class="text-xs text-gray-500 font-medium text-center py-4" id="format-placeholder-text">
                        <i class="fas fa-info-circle text-indigo-500 mr-1"></i>Please select a target project above to inspect its dynamic column schema.
                    </p>
                    
                    <div id="format-details" class="hidden space-y-4">
                        <div>
                            <h5 class="font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-2 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span> Standard Required Columns (4)
                            </h5>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="px-2 py-1 bg-red-50 text-red-700 border border-red-200 rounded font-mono text-[10px]">site_name</span>
                                <span class="px-2 py-1 bg-red-50 text-red-700 border border-red-200 rounded font-mono text-[10px]">city</span>
                                <span class="px-2 py-1 bg-red-50 text-red-700 border border-red-200 rounded font-mono text-[10px]">state</span>
                                <span class="px-2 py-1 bg-red-50 text-red-700 border border-red-200 rounded font-mono text-[10px]">country</span>
                            </div>
                        </div>

                        <div>
                            <h5 class="font-bold text-gray-700 uppercase tracking-wider text-[10px] mb-2 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-gray-400"></span> Standard Optional Columns (8)
                            </h5>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">lho</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">bank_name</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">customer_name</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">zone</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">address</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">latitude</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">longitude</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-700 border border-gray-200 rounded font-mono text-[10px]">status</span>
                            </div>
                        </div>

                        <div>
                            <h5 class="font-bold text-indigo-900 uppercase tracking-wider text-[10px] mb-2 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-purple-600"></span> Dynamically Configured Project Custom Columns
                            </h5>
                            <div id="custom-columns-tags" class="flex flex-wrap gap-1.5">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Drag & Drop File Upload -->
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-xs">3</span>
                    <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Upload Filled Data File</h4>
                </div>

                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary transition bg-gray-50/50" id="drop-zone">
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" class="hidden" required>
                    <div id="upload-placeholder">
                        <i class="fas fa-cloud-upload-alt text-4xl text-indigo-400 mb-3"></i>
                        <p class="text-gray-700 font-semibold mb-1">Drag & drop your populated CSV or Excel file here, or</p>
                        <button type="button" onclick="document.getElementById('excel_file').click()" 
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-bold text-xs shadow-sm mt-1">
                            <i class="fas fa-folder-open mr-1.5"></i>Browse Computer
                        </button>
                        <p class="text-[11px] text-gray-400 mt-3">Supports: .csv, .xlsx, .xls (Max 5MB)</p>
                    </div>
                    <div id="file-selected" class="hidden">
                        <i class="fas fa-file-csv text-4xl text-emerald-500 mb-3"></i>
                        <p class="text-gray-800 font-bold" id="file-name"></p>
                        <p class="text-xs text-gray-500" id="file-size"></p>
                        <button type="button" onclick="clearFile()" class="mt-2 text-rose-600 hover:underline font-semibold text-xs">
                            <i class="fas fa-times mr-1"></i>Remove File
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit Button Footer -->
            <div class="pt-4 border-t border-gray-200 flex items-center justify-between">
                <a href="index.php" class="px-5 py-2.5 text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Cancel
                </a>
                <button type="submit" id="upload-btn" class="px-6 py-2.5 bg-primary hover:bg-indigo-700 text-white rounded-lg font-bold text-xs shadow-md transition flex items-center" disabled>
                    <i class="fas fa-upload mr-1.5"></i>Process Dynamic Upload
                </button>
            </div>
        </form>
    </div>
</div>

<!-- XLSX with Style JS Library -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

<script>
let activeProjectCustomFields = [];
let activeProjectName = '';

const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('excel_file');
const uploadBtn = document.getElementById('upload-btn');

document.addEventListener('DOMContentLoaded', function() {
    const pId = document.getElementById('project_id').value;
    if (pId) {
        onProjectChange(pId);
    }
});

async function onProjectChange(projectId) {
    const downloadBtn = document.getElementById('btn-download-template');
    const placeholderText = document.getElementById('format-placeholder-text');
    const formatDetails = document.getElementById('format-details');
    const tagsContainer = document.getElementById('custom-columns-tags');

    if (!projectId) {
        activeProjectCustomFields = [];
        downloadBtn.disabled = true;
        placeholderText.classList.remove('hidden');
        formatDetails.classList.add('hidden');
        return;
    }

    try {
        const url = `../api/sites/form_options.php?fetch_custom_form=1&purpose=site_add&project_id=${projectId}`;
        const res = await fetch(url);
        const json = await res.json();

        downloadBtn.disabled = false;
        placeholderText.classList.add('hidden');
        formatDetails.classList.remove('hidden');

        tagsContainer.innerHTML = '';

        if (json.success && json.data && json.data.form && json.data.form.fields) {
            activeProjectCustomFields = json.data.form.fields.filter(f => f.field_type !== 'heading');
            activeProjectName = json.data.form.form_name || 'Project';

            if (activeProjectCustomFields.length === 0) {
                tagsContainer.innerHTML = '<span class="text-xs text-gray-400 italic">No custom fields configured for this project. Standard columns will be used.</span>';
            } else {
                activeProjectCustomFields.forEach(f => {
                    const tag = document.createElement('span');
                    tag.className = 'px-2 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded font-mono text-[10px] flex items-center gap-1';
                    tag.innerHTML = `
                        <i class="fas fa-tag text-[9px] text-purple-500"></i>
                        custom_${f.field_key} <span class="text-gray-400">(${f.field_label}${f.is_required ? ' *' : ''})</span>
                    `;
                    tagsContainer.appendChild(tag);
                });
            }
        } else {
            activeProjectCustomFields = [];
            tagsContainer.innerHTML = '<span class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded">No project custom form defined. Using standard template columns.</span>';
        }
    } catch (e) {
        console.error('Error fetching project form options:', e);
    }
}

const sampleMasterData = <?php echo json_encode([
    'country' => $sampleCountry,
    'state' => $sampleState,
    'city' => $sampleCity,
    'bank_name' => $sampleBank,
    'customer_name' => $sampleCustomer,
    'lho' => $sampleLho,
    'zone' => $sampleZone
]); ?>;

function downloadDynamicTemplate() {
    const standardHeaders = ['site_name', 'city', 'state', 'country', 'lho', 'bank_name', 'customer_name', 'zone', 'address', 'latitude', 'longitude', 'status'];
    const customHeaders = activeProjectCustomFields.map(f => `custom_${f.field_key}`);
    const allHeaders = [...standardHeaders, ...customHeaders];

    const sampleStandard = [
        'Sample Site 1',
        sampleMasterData.city || 'Mumbai',
        sampleMasterData.state || 'Maharashtra',
        sampleMasterData.country || 'India',
        sampleMasterData.lho || '',
        sampleMasterData.bank_name || '',
        sampleMasterData.customer_name || '',
        sampleMasterData.zone || '',
        '123 Commercial Belt',
        '19.0760',
        '72.8777',
        'active'
    ];

    const sampleCustom = activeProjectCustomFields.map(f => {
        if (f.default_value) return f.default_value;
        if (f.field_type === 'number') return '100';
        if (f.field_type === 'phone') return '9876543210';
        return `Sample ${f.field_label}`;
    });

    const sampleRow = [...sampleStandard, ...sampleCustom];
    const projectId = document.getElementById('project_id').value || 'dynamic';
    const fileName = `site_upload_template_project_${projectId}.xlsx`;

    if (typeof XLSX !== 'undefined') {
        const ws = XLSX.utils.aoa_to_sheet([allHeaders, sampleRow]);

        // Auto full-width column size calculation with generous padding
        const colWidths = allHeaders.map((header, colIdx) => {
            const valStr = String(sampleRow[colIdx] || '');
            const maxLen = Math.max(header.length, valStr.length);
            return { wch: Math.max(maxLen + 8, 18) };
        });
        ws['!cols'] = colWidths;

        // Header Formatting: Indigo Fill, Bold White Text, Borders, Centered
        allHeaders.forEach((h, colIdx) => {
            const cellRef = XLSX.utils.encode_cell({ r: 0, c: colIdx });
            if (ws[cellRef]) {
                ws[cellRef].s = {
                    font: { name: 'Calibri', sz: 11, bold: true, color: { rgb: "FFFFFF" } },
                    fill: { fgColor: { rgb: "4F46E5" } },
                    alignment: { horizontal: "center", vertical: "center" },
                    border: {
                        top: { style: "thin", color: { rgb: "3730A3" } },
                        bottom: { style: "medium", color: { rgb: "3730A3" } },
                        left: { style: "thin", color: { rgb: "3730A3" } },
                        right: { style: "thin", color: { rgb: "3730A3" } }
                    }
                };
            }
        });

        // Sample Row Formatting: Light Fill, Clean Font, Borders
        sampleRow.forEach((val, colIdx) => {
            const cellRef = XLSX.utils.encode_cell({ r: 1, c: colIdx });
            if (ws[cellRef]) {
                ws[cellRef].s = {
                    font: { name: 'Calibri', sz: 10, color: { rgb: "1F2937" } },
                    fill: { fgColor: { rgb: "F8FAFC" } },
                    alignment: { horizontal: "left", vertical: "center" },
                    border: {
                        top: { style: "thin", color: { rgb: "E2E8F0" } },
                        bottom: { style: "thin", color: { rgb: "E2E8F0" } },
                        left: { style: "thin", color: { rgb: "E2E8F0" } },
                        right: { style: "thin", color: { rgb: "E2E8F0" } }
                    }
                };
            }
        });

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Site Template");
        XLSX.writeFile(wb, fileName);
    } else {
        // Fallback to CSV
        const BOM = '\uFEFF';
        const csvContent = BOM + [allHeaders.join(','), sampleRow.join(',')].join('\r\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `site_upload_template_project_${projectId}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    }
}

// Drag & drop handlers
dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    dropZone.classList.add('border-primary', 'bg-indigo-50/50');
});

dropZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-indigo-50/50');
});

dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-indigo-50/50');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect(files[0]);
    }
});

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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
