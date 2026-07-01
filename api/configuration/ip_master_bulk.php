<?php
/**
 * IP Master Bulk Upload API Endpoint
 * POST /api/configuration/ip_master_bulk.php - Bulk upload IP_Master records
 * 
 * Accepts CSV or JSON data with IP_Master records.
 * 
 * POST Body (JSON):
 * - data: Array of IP_Master records, each containing:
 *   - network_ip: Network IP address (required)
 *   - router_ip: Router IP address (required)
 *   - site_ip: Site IP address (required)
 *   - subnet_mask: Subnet mask (required)
 * 
 * POST Body (CSV via file upload):
 * - file: CSV file with columns: network_ip, router_ip, site_ip, subnet_mask
 * 
 * **Validates: Requirements 10.1, 10.2, 10.3, 10.4**
 * - 10.1: Validate all rows for IP format and uniqueness before committing
 * - 10.2: Generate error report listing failed rows with reasons
 * - 10.3: Create all IP_Master records with Available status
 * - 10.4: Validate against expected schema and reject malformed entries
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/IPMasterService.php';
require_once __DIR__ . '/../../repositories/IPMasterRepository.php';
require_once __DIR__ . '/../../models/IPMaster.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require ADV user access for all IP configuration operations
    $user = $authMiddleware->requireAdvUser();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::methodNotAllowed(['POST']);
    }
    
    handleBulkUpload($authMiddleware, $user);
    
} catch (Exception $e) {
    error_log("IP Master Bulk API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle bulk upload request
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4
 */
function handleBulkUpload($authMiddleware, $user) {
    $rows = [];
    
    // Check if file upload
    if (!empty($_FILES['file'])) {
        $rows = parseUploadedFile($_FILES['file']);
    } else {
        // Try JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!empty($input['data']) && is_array($input['data'])) {
            $rows = $input['data'];
        } elseif (!empty($_POST['data'])) {
            // Try form data
            $rows = is_array($_POST['data']) ? $_POST['data'] : json_decode($_POST['data'], true);
        }
    }
    
    if (empty($rows)) {
        ApiResponse::validationError(
            ['data' => ['No data provided. Upload a CSV file or provide JSON data array.']],
            'No data to process'
        );
    }
    
    // Process bulk upload
    $result = processBulkIPMasterUpload($rows, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/configuration/ip_master_bulk', 'POST', [
        'action' => 'bulk_upload',
        'total_rows' => $result['total_rows'],
        'success_count' => $result['success_count'],
        'error_count' => $result['error_count']
    ]);
    
    if ($result['success']) {
        ApiResponse::success([
            'total_rows' => $result['total_rows'],
            'success_count' => $result['success_count'],
            'error_count' => $result['error_count'],
            'created_ids' => $result['created_ids'],
            'errors' => $result['errors']
        ], $result['message'], 201);
    } else {
        // Return error report with details
        ApiResponse::error(
            'BULK_UPLOAD_FAILED',
            $result['message'],
            400,
            [
                'total_rows' => $result['total_rows'],
                'success_count' => $result['success_count'],
                'error_count' => $result['error_count'],
                'errors' => $result['errors']
            ]
        );
    }
}

/**
 * Parse uploaded CSV file
 * 
 * Requirement 10.4: Validate against expected schema
 */
function parseUploadedFile($file): array {
    $rows = [];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $message = $errorMessages[$file['error']] ?? 'Unknown upload error';
        ApiResponse::validationError(['file' => [$message]], 'File upload failed');
    }
    
    // Check file type
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'txt'])) {
        ApiResponse::validationError(
            ['file' => ['Invalid file type. Only CSV files are supported.']],
            'Invalid file type'
        );
    }
    
    // Parse CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        ApiResponse::serverError('Failed to read uploaded file');
    }
    
    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        ApiResponse::validationError(
            ['file' => ['CSV file is empty or invalid']],
            'Invalid CSV file'
        );
    }
    
    // Normalize header names
    $header = array_map(function($col) {
        return strtolower(trim(str_replace([' ', '-'], '_', $col)));
    }, $header);
    
    // Validate required columns
    $requiredColumns = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
    $missingColumns = array_diff($requiredColumns, $header);
    
    if (!empty($missingColumns)) {
        fclose($handle);
        ApiResponse::validationError(
            ['file' => ['Missing required columns: ' . implode(', ', $missingColumns)]],
            'Invalid CSV schema'
        );
    }
    
    // Get column indices
    $columnIndices = [];
    foreach ($requiredColumns as $col) {
        $columnIndices[$col] = array_search($col, $header);
    }
    
    // Read data rows
    $rowNumber = 1; // Header is row 1
    while (($data = fgetcsv($handle)) !== false) {
        $rowNumber++;
        
        // Skip empty rows
        if (empty(array_filter($data))) {
            continue;
        }
        
        $row = [
            '_row_number' => $rowNumber,
            'network_ip' => isset($data[$columnIndices['network_ip']]) ? trim($data[$columnIndices['network_ip']]) : '',
            'router_ip' => isset($data[$columnIndices['router_ip']]) ? trim($data[$columnIndices['router_ip']]) : '',
            'site_ip' => isset($data[$columnIndices['site_ip']]) ? trim($data[$columnIndices['site_ip']]) : '',
            'subnet_mask' => isset($data[$columnIndices['subnet_mask']]) ? trim($data[$columnIndices['subnet_mask']]) : ''
        ];
        
        $rows[] = $row;
    }
    
    fclose($handle);
    
    return $rows;
}

/**
 * Process bulk IP_Master upload
 * 
 * Requirements: 10.1, 10.2, 10.3
 * - 10.1: Validate all rows before committing
 * - 10.2: Generate error report for failed rows
 * - 10.3: Create records with Available status
 */
function processBulkIPMasterUpload(array $rows, int $userId): array {
    $db = DatabaseConfig::getInstance();
    $repository = new IPMasterRepository();
    
    $result = [
        'success' => false,
        'message' => '',
        'total_rows' => count($rows),
        'success_count' => 0,
        'error_count' => 0,
        'created_ids' => [],
        'errors' => []
    ];
    
    if (empty($rows)) {
        $result['message'] = 'No rows to process';
        return $result;
    }
    
    // Phase 1: Validate ALL rows before committing any
    // Requirement 10.1: Validate all rows for IP format and uniqueness before committing
    $validationErrors = [];
    $seenCombinations = []; // Track combinations within this batch
    
    foreach ($rows as $index => $row) {
        $rowNumber = $row['_row_number'] ?? ($index + 2);
        $rowErrors = [];
        
        // Validate required fields
        $requiredFields = ['network_ip', 'router_ip', 'site_ip', 'subnet_mask'];
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                $rowErrors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // If required fields are missing, skip further validation
        if (!empty($rowErrors)) {
            $validationErrors[$rowNumber] = $rowErrors;
            continue;
        }
        
        // Validate IP format for all fields
        $ipErrors = IPMaster::validateAllIPs($row);
        if (!empty($ipErrors)) {
            foreach ($ipErrors as $field => $error) {
                $rowErrors[] = $error;
            }
        }
        
        // Check for duplicate within batch
        $combination = $row['network_ip'] . '|' . $row['router_ip'] . '|' . $row['site_ip'] . '|' . $row['subnet_mask'];
        if (isset($seenCombinations[$combination])) {
            $rowErrors[] = "Duplicate IP combination found in row {$seenCombinations[$combination]}";
        } else {
            $seenCombinations[$combination] = $rowNumber;
        }
        
        // Check for duplicate in database
        if (empty($rowErrors) && $repository->checkDuplicateFromArray($row)) {
            $rowErrors[] = 'This IP combination already exists in the database';
        }
        
        if (!empty($rowErrors)) {
            $validationErrors[$rowNumber] = $rowErrors;
        }
    }
    
    // If any validation errors, return without committing
    // Requirement 10.1: All rows must be valid before committing
    if (!empty($validationErrors)) {
        $result['error_count'] = count($validationErrors);
        $result['errors'] = $validationErrors;
        $result['message'] = "Validation failed for {$result['error_count']} rows. No records were created.";
        return $result;
    }
    
    // Phase 2: All validation passed, now commit all records in a transaction
    try {
        $conn = $db->getConnection();
        $conn->begin_transaction();
        
        foreach ($rows as $index => $row) {
            $rowNumber = $row['_row_number'] ?? ($index + 2);
            
            // Prepare data
            $ipMasterData = [
                'network_ip' => trim($row['network_ip']),
                'router_ip' => trim($row['router_ip']),
                'site_ip' => trim($row['site_ip']),
                'subnet_mask' => trim($row['subnet_mask']),
                'status' => IPMaster::STATUS_AVAILABLE, // Requirement 10.3
                'created_by' => $userId
            ];
            
            // Create IP_Master
            $ipMasterId = $repository->createIPMaster($ipMasterData);
            $result['created_ids'][] = $ipMasterId;
            $result['success_count']++;
            
            // Log audit
            logBulkAction($db, $userId, $ipMasterId, 'bulk_upload', [
                'network_ip' => $ipMasterData['network_ip'],
                'router_ip' => $ipMasterData['router_ip'],
                'site_ip' => $ipMasterData['site_ip'],
                'subnet_mask' => $ipMasterData['subnet_mask'],
                'row_number' => $rowNumber
            ]);
        }
        
        $conn->commit();
        
        $result['success'] = true;
        $result['message'] = "Successfully created {$result['success_count']} IP_Master records";
        
    } catch (Exception $e) {
        // Rollback on any error
        $conn->rollback();
        
        $result['success'] = false;
        $result['success_count'] = 0;
        $result['created_ids'] = [];
        $result['error_count'] = $result['total_rows'];
        $result['message'] = 'Bulk upload failed: ' . $e->getMessage();
        $result['errors'][0] = ['Transaction failed: ' . $e->getMessage()];
    }
    
    return $result;
}

/**
 * Log bulk action for audit trail
 */
function logBulkAction($db, ?int $userId, int $ipMasterId, string $action, array $details): void {
    try {
        $sql = "INSERT INTO configuration_audit_log (action_type, user_id, ip_master_id, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $db->executeQuery($sql, [
            $action,
            $userId ?? 0,
            $ipMasterId,
            json_encode($details)
        ], 'siis');
        $stmt->close();
    } catch (Exception $e) {
        error_log("Failed to log bulk IP_Master action: " . $e->getMessage());
    }
}
