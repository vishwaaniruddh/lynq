<?php
/**
 * Inventory API - Bulk Stock Upload
 * POST /api/inventory/stock/bulk.php
 * 
 * Processes bulk stock entry from uploaded data
 * 
 * Request Body (JSON):
 * {
 *   "rows": [
 *     {
 *       "product_id": "int (required)",
 *       "warehouse_id": "int (required)",
 *       "quantity": "int (required for non-serializable)",
 *       "serial_number": "string (required for serializable)",
 *       "notes": "string (optional)"
 *     }
 *   ],
 *   "validate_only": "bool (optional, default: false)"
 * }
 * 
 * Response: { success: bool, data: { result: BulkInventoryResult } }
 * 
 * **Validates: Requirements 4.1, 4.2**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/BulkInventoryService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate rows array
    if (!isset($input['rows']) || !is_array($input['rows'])) {
        ApiResponse::validationError(['rows' => 'Rows array is required']);
    }
    
    if (empty($input['rows'])) {
        ApiResponse::validationError(['rows' => 'At least one row is required']);
    }
    
    // Limit bulk upload size
    $maxRows = 1000;
    if (count($input['rows']) > $maxRows) {
        ApiResponse::validationError(['rows' => "Maximum $maxRows rows allowed per upload"]);
    }
    
    $validateOnly = isset($input['validate_only']) && $input['validate_only'] === true;
    
    // Check user access to warehouses in the upload
    $inventoryAccessService = new InventoryAccessService();
    $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
    
    // Add row numbers for tracking
    $rows = [];
    foreach ($input['rows'] as $index => $row) {
        $row['_row_number'] = $row['_row_number'] ?? ($index + 2); // Excel-style row numbers (header is row 1)
        
        // Check warehouse access
        if (!empty($row['warehouse_id']) && !in_array((int)$row['warehouse_id'], $accessibleWarehouseIds)) {
            ApiResponse::forbidden("Row {$row['_row_number']}: You do not have access to warehouse ID {$row['warehouse_id']}");
        }
        
        $rows[] = $row;
    }
    
    $bulkService = new BulkInventoryService();
    
    // Validate all rows first (Requirement 4.1)
    $validation = $bulkService->validateBulkUpload($rows);
    
    if ($validateOnly) {
        // Return validation results only
        $authMiddleware->logApiAccess($user['id'], '/api/inventory/stock/bulk', 'POST', [
            'action' => 'validate',
            'total_rows' => count($rows),
            'valid_count' => $validation['validCount'],
            'invalid_count' => $validation['invalidCount']
        ]);
        
        ApiResponse::success([
            'validation' => [
                'success' => $validation['success'],
                'validCount' => $validation['validCount'],
                'invalidCount' => $validation['invalidCount'],
                'errors' => $validation['errors']
            ]
        ], $validation['success'] ? 'Validation passed' : 'Validation failed');
    }
    
    // If validation failed, return errors (Requirement 4.2)
    if (!$validation['success']) {
        $authMiddleware->logApiAccess($user['id'], '/api/inventory/stock/bulk', 'POST', [
            'action' => 'upload_failed_validation',
            'total_rows' => count($rows),
            'invalid_count' => $validation['invalidCount']
        ]);
        
        ApiResponse::error('VALIDATION_FAILED', 'Bulk upload validation failed', 400, [
            'validCount' => $validation['validCount'],
            'invalidCount' => $validation['invalidCount'],
            'errors' => $validation['errors']
        ]);
    }
    
    // Process valid rows with partial success handling (Requirement 4.2)
    $result = $bulkService->processBulkStockEntry($validation['validRows'], $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/stock/bulk', 'POST', [
        'action' => 'upload',
        'total_rows' => $result->totalRows,
        'success_count' => $result->successCount,
        'error_count' => $result->errorCount
    ]);
    
    // Generate error report if there were errors
    $errorReportPath = null;
    if ($result->errorCount > 0) {
        $reportResult = $bulkService->generateErrorReport($result, 'bulk_stock_errors_' . date('Ymd_His'));
        if ($reportResult['success'] && !empty($reportResult['path'])) {
            $errorReportPath = $reportResult['path'];
        }
    }
    
    $responseData = [
        'result' => [
            'success' => $result->success,
            'message' => $result->message,
            'totalRows' => $result->totalRows,
            'successCount' => $result->successCount,
            'errorCount' => $result->errorCount,
            'createdIds' => $result->createdIds,
            'errors' => $result->errors,
            'rowResults' => $result->rowResults
        ]
    ];
    
    if ($errorReportPath) {
        $responseData['errorReportPath'] = $errorReportPath;
    }
    
    $statusCode = $result->success ? 201 : ($result->successCount > 0 ? 207 : 400);
    
    if ($result->success) {
        ApiResponse::success($responseData, $result->message, $statusCode);
    } elseif ($result->successCount > 0) {
        // Partial success - 207 Multi-Status
        ApiResponse::success($responseData, $result->message, $statusCode);
    } else {
        ApiResponse::error('BULK_UPLOAD_FAILED', $result->message, $statusCode, $responseData['result']);
    }
    
} catch (Exception $e) {
    error_log("Inventory Bulk Stock API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process bulk stock upload: ' . $e->getMessage());
}
