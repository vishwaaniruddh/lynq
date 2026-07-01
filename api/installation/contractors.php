<?php
/**
 * Get Available Contractors API
 * 
 * GET /api/installation/contractors.php
 * Returns list of active contractors available for installation delegation
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Array of contractor companies
 *   - id: Contractor company ID
 *   - name: Company name
 *   - email: Company email
 *   - phone: Company phone
 *   - address: Company address
 *   - status: Company status
 * 
 * Requirements: 1.3
 * - 1.3: Display form to select contractor for installation
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
    
    // Only allow GET method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
    // Verify user is ADV (only ADV users can view contractors for delegation)
    $companyType = strtoupper($user['company_type'] ?? '');
    $isSystemAdmin = isset($user['is_system_admin']) && $user['is_system_admin'];
    
    if ($companyType !== 'ADV' && !$isSystemAdmin) {
        ApiResponse::forbidden('Only ADV users can view available contractors');
    }
    
    // Initialize service and get available contractors
    $delegationService = new InstallationDelegationService();
    $contractors = $delegationService->getAvailableContractors();
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/contractors', 'GET', [
        'count' => count($contractors)
    ]);
    
    ApiResponse::success($contractors, 'Contractors retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get Contractors API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while retrieving contractors');
}
