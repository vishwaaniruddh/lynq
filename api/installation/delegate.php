<?php
/**
 * Installation Delegation API
 * 
 * POST /api/installation/delegate.php
 * Delegates an installation to a contractor
 * 
 * Request Body:
 * - site_id: (required) Site ID
 * - feasibility_id: (required) Feasibility check ID
 * - contractor_id: (required) Contractor company ID
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Installation record (on success)
 * 
 * Requirements: 1.3, 1.4
 * - 1.3: Display form to select contractor for installation
 * - 1.4: Create installation record with status "pending_assignment" linked to contractor
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationDelegationService.php';

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
    
    // Verify user is ADV (only ADV users can delegate installation)
    $companyType = strtoupper($user['company_type'] ?? '');
    $isSystemAdmin = isset($user['is_system_admin']) && $user['is_system_admin'];
    
    if ($companyType !== 'ADV' && !$isSystemAdmin) {
        ApiResponse::forbidden('Only ADV users can delegate installations');
    }
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::error('INVALID_REQUEST', 'Invalid JSON request body', 400);
    }
    
    // Validate required fields
    $errors = [];
    
    $siteId = isset($input['site_id']) ? (int)$input['site_id'] : 0;
    $feasibilityId = isset($input['feasibility_id']) ? (int)$input['feasibility_id'] : 0;
    $contractorId = isset($input['contractor_id']) ? (int)$input['contractor_id'] : 0;
    
    if (!$siteId) {
        $errors[] = ['field' => 'site_id', 'message' => 'Site ID is required'];
    }
    
    if (!$feasibilityId) {
        $errors[] = ['field' => 'feasibility_id', 'message' => 'Feasibility ID is required'];
    }
    
    if (!$contractorId) {
        $errors[] = ['field' => 'contractor_id', 'message' => 'Contractor ID is required'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    // Initialize service and delegate installation
    $delegationService = new InstallationDelegationService();
    $result = $delegationService->delegateInstallation($siteId, $feasibilityId, $contractorId, $user['id']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/delegate', 'POST', [
        'site_id' => $siteId,
        'feasibility_id' => $feasibilityId,
        'contractor_id' => $contractorId,
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
        } elseif ($result['code'] === 'INVALID_CONTRACTOR' || $result['code'] === 'NOT_A_CONTRACTOR' || $result['code'] === 'CONTRACTOR_INACTIVE') {
            $statusCode = 400;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Installation Delegation API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while delegating installation');
}
