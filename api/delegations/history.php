<?php
/**
 * Delegation History API Endpoint
 * GET /api/delegations/history.php - Get history for a specific delegation
 * 
 * Query Parameters (GET):
 * - delegation_id: Required - The delegation ID to get history for
 * 
 * **Validates: Requirements 3.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/DelegationService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    $delegationService = new DelegationService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($delegationService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
} catch (Exception $e) {
    error_log("Delegation History API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - Get delegation history
 */
function handleGetRequest($delegationService, $authMiddleware, $user) {
    // Validate delegation_id parameter
    if (!isset($_GET['delegation_id']) || (int)$_GET['delegation_id'] <= 0) {
        ApiResponse::validationError(
            ['delegation_id' => ['Delegation ID is required']],
            'Validation failed'
        );
    }
    
    $delegationId = (int)$_GET['delegation_id'];
    
    // Get the delegation to verify access
    $delegation = $delegationService->getDelegation($delegationId);
    
    if (!$delegation) {
        ApiResponse::notFound('Delegation not found');
    }
    
    // Verify user has access to this delegation
    // ADV users can see delegations for their sites
    // Contractors can see delegations assigned to them
    $isAdv = isAdvUser();
    
    if ($isAdv) {
        // ADV users can access if the site belongs to their company
        if ($delegation['adv_company_id'] !== $user['company_id']) {
            ApiResponse::forbidden('You do not have permission to view this delegation history');
        }
    } else {
        // Contractors can access if the delegation is assigned to their company
        if ($delegation['contractor_id'] !== $user['company_id']) {
            ApiResponse::forbidden('You do not have permission to view this delegation history');
        }
    }
    
    // Get delegation history
    $history = $delegationService->getDelegationHistoryById($delegationId);
    
    $authMiddleware->logApiAccess($user['id'], '/api/delegations/history', 'GET', [
        'delegation_id' => $delegationId
    ]);
    
    ApiResponse::success([
        'delegation' => $delegation,
        'history' => $history
    ], 'Delegation history retrieved successfully');
}
