<?php
/**
 * Installation Submit API
 * 
 * POST /api/installation/submit.php
 * Submits installation form for review
 * 
 * Request Body:
 * - installation_id: (required) Installation ID
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Updated installation record (on success)
 * 
 * Requirements: 3.5
 * - 3.5: Update status to "submitted" on successful submission
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationService.php';

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
    
    // Initialize service and submit installation
    $installationService = new InstallationService();
    $result = $installationService->submitInstallation($installationId, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/submit', 'POST', [
        'installation_id' => $installationId,
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'FORM_ACCESS_DENIED' || $result['code'] === 'INSTALLATION_LOCKED') {
            $statusCode = 403;
        } elseif ($result['code'] === 'VALIDATION_ERROR') {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
            return;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Installation Submit API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while submitting installation');
}
