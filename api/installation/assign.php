<?php
/**
 * Engineer Assignment API
 * 
 * POST /api/installation/assign.php
 * Assigns an engineer to an installation
 * 
 * Request Body:
 * - installation_id: (required) Installation ID
 * - engineer_id: (required) Engineer user ID
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Updated installation record (on success)
 * 
 * Requirements: 2.4
 * - 2.4: Update installation status to "pending_eta" when engineer is assigned
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationAssignmentService.php';

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
    
    // Verify user is Contractor (only contractor users can assign engineers)
    $companyType = strtoupper($user['company_type'] ?? '');
    $isSystemAdmin = isset($user['is_system_admin']) && $user['is_system_admin'];
    
    if ($companyType !== 'CONTRACTOR' && !$isSystemAdmin) {
        ApiResponse::forbidden('Only contractor users can assign engineers');
    }
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::error('INVALID_REQUEST', 'Invalid JSON request body', 400);
    }
    
    // Validate required fields
    $errors = [];
    
    $installationId = isset($input['installation_id']) ? (int)$input['installation_id'] : 0;
    $engineerId = isset($input['engineer_id']) ? (int)$input['engineer_id'] : 0;
    
    if (!$installationId) {
        $errors[] = ['field' => 'installation_id', 'message' => 'Installation ID is required'];
    }
    
    if (!$engineerId) {
        $errors[] = ['field' => 'engineer_id', 'message' => 'Engineer ID is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    // Initialize service and assign engineer
    $assignmentService = new InstallationAssignmentService();
    $result = $assignmentService->assignEngineer($installationId, $engineerId, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/assign', 'POST', [
        'installation_id' => $installationId,
        'engineer_id' => $engineerId,
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message'], 200);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'WRONG_CONTRACTOR') {
            $statusCode = 403;
        } elseif ($result['code'] === 'WRONG_STATUS') {
            $statusCode = 409;
        } elseif ($result['code'] === 'INVALID_ENGINEER' || $result['code'] === 'ENGINEER_INACTIVE' || $result['code'] === 'WRONG_COMPANY') {
            $statusCode = 400;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Engineer Assignment API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while assigning engineer');
}
