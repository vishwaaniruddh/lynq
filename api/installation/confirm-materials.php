<?php
/**
 * Material Receipt Confirmation API
 * 
 * POST /api/installation/confirm-materials.php
 * Confirms material receipt for an installation
 * 
 * Request Body:
 * - installation_id: (required) Installation ID
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Receipt record and updated status (on success)
 * 
 * Requirements: 2.1, 2.2, 2.3
 * - 2.1: Display "Confirm Materials Received" button for pending_materials status
 * - 2.2: Record confirmation with timestamp and engineer ID
 * - 2.3: Update installation status to "materials_received"
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialReceiptService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::methodNotAllowed(['POST']);
    }
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::error('INVALID_REQUEST', 'Invalid JSON request body', 400);
    }
    
    // Validate required fields
    $installationId = isset($input['installation_id']) ? (int)$input['installation_id'] : 0;
    
    if (!$installationId) {
        ApiResponse::validationError([
            ['field' => 'installation_id', 'message' => 'Installation ID is required']
        ], 'Validation failed');
    }
    
    // Initialize service and confirm material receipt
    $materialReceiptService = new MaterialReceiptService();
    $result = $materialReceiptService->confirmMaterialReceipt($installationId, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/confirm-materials', 'POST', [
        'installation_id' => $installationId,
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'NOT_FOUND' || $result['code'] === 'ENGINEER_NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'INVALID_STATUS' || $result['code'] === 'ALREADY_CONFIRMED') {
            $statusCode = 409;
        } elseif ($result['code'] === 'ENGINEER_INACTIVE') {
            $statusCode = 403;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Material Receipt Confirmation API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while confirming material receipt');
}
