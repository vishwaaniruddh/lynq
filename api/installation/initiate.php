<?php
/**
 * Installation Initiation API
 * 
 * POST /api/installation/initiate.php
 * Initiates installation for a site with ADV-approved feasibility
 * 
 * Request Body:
 * - site_id: (required) Site ID
 * - feasibility_id: (required) Feasibility check ID
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Installation record (on success)
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.5
 * - 1.1: Display "Initiate Installation" button for ADV-approved feasibility
 * - 1.2: Create installation record linked to site and feasibility check
 * - 1.3: Set installation status to "pending_materials"
 * - 1.5: Hide button when feasibility is not ADV-approved
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
    
    // Verify user is ADV (only ADV users can initiate installation)
    $companyType = strtoupper($user['company_type'] ?? '');
    $isSystemAdmin = isset($user['is_system_admin']) && $user['is_system_admin'];
    
    if ($companyType !== 'ADV' && !$isSystemAdmin) {
        ApiResponse::forbidden('Only ADV users can initiate installations');
    }
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::error('INVALID_REQUEST', 'Invalid JSON request body', 400);
    }
    
    // Validate required fields
    $siteId = isset($input['site_id']) ? (int)$input['site_id'] : 0;
    $feasibilityId = isset($input['feasibility_id']) ? (int)$input['feasibility_id'] : 0;
    
    if (!$siteId) {
        ApiResponse::validationError([
            ['field' => 'site_id', 'message' => 'Site ID is required']
        ], 'Validation failed');
    }
    
    if (!$feasibilityId) {
        ApiResponse::validationError([
            ['field' => 'feasibility_id', 'message' => 'Feasibility ID is required']
        ], 'Validation failed');
    }
    
    // Initialize service and initiate installation
    $installationService = new InstallationService();
    $result = $installationService->initiateInstallation($siteId, $feasibilityId, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/initiate', 'POST', [
        'site_id' => $siteId,
        'feasibility_id' => $feasibilityId,
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message'], 201);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'SITE_NOT_FOUND' || $result['code'] === 'FEASIBILITY_NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'FEASIBILITY_NOT_APPROVED') {
            $statusCode = 403;
        } elseif ($result['code'] === 'INSTALLATION_EXISTS') {
            $statusCode = 409;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Installation Initiation API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while initiating installation');
}
