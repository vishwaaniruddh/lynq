<?php
/**
 * ETA Submission API
 * 
 * POST /api/installation/submit-eta.php
 * Submits ETA (Estimated Time of Arrival) for an installation
 * 
 * Request Body:
 * - installation_id: (required) Installation ID
 * - eta_date: (required) ETA date in Y-m-d format
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Updated installation record (on success)
 * 
 * Requirements: 3.3
 * - 3.3: Record ETA date and update status to pending_ada
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationETAService.php';

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
    $errors = [];
    
    $installationId = isset($input['installation_id']) ? (int)$input['installation_id'] : 0;
    $etaDate = isset($input['eta_date']) ? trim($input['eta_date']) : '';
    
    if (!$installationId) {
        $errors[] = ['field' => 'installation_id', 'message' => 'Installation ID is required'];
    }
    
    if (empty($etaDate)) {
        $errors[] = ['field' => 'eta_date', 'message' => 'ETA date is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    // Initialize service and submit ETA
    $etaService = new InstallationETAService();
    $result = $etaService->submitETA($installationId, $etaDate, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/submit-eta', 'POST', [
        'installation_id' => $installationId,
        'eta_date' => $etaDate,
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message'], 200);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'NOT_ASSIGNED') {
            $statusCode = 403;
        } elseif ($result['code'] === 'WRONG_STATUS') {
            $statusCode = 409;
        } elseif ($result['code'] === 'INVALID_DATE_FORMAT' || $result['code'] === 'DATE_IN_PAST') {
            $statusCode = 400;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("ETA Submission API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while submitting ETA');
}
