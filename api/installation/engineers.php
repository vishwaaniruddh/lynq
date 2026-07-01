<?php
/**
 * Get Available Engineers API
 * 
 * GET /api/installation/engineers.php
 * Returns list of active engineers for a contractor company
 * 
 * Query Parameters:
 * - contractor_id: (optional) Contractor company ID. If not provided, uses current user's company
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Array of engineers
 *   - id: Engineer user ID
 *   - first_name: First name
 *   - last_name: Last name
 *   - full_name: Full name (first + last)
 *   - email: Email address
 * 
 * Requirements: 2.3
 * - 2.3: Display "Assign Engineer" option with available engineers
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../repositories/UserRepository.php';

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
    
    // Determine contractor ID
    $companyType = strtoupper($user['company_type'] ?? '');
    
    // Get contractor_id from query params or use current user's company
    $contractorId = isset($_GET['contractor_id']) ? (int)$_GET['contractor_id'] : 0;
    
    if (!$contractorId) {
        // If no contractor_id provided, use current user's company (for contractor users)
        if ($companyType === 'CONTRACTOR') {
            $contractorId = (int)$user['company_id'];
        } else {
            ApiResponse::error('MISSING_CONTRACTOR_ID', 'Contractor ID is required for non-contractor users', 400);
        }
    }
    
    // Verify access: Contractor users can only see their own engineers
    if ($companyType === 'CONTRACTOR' && (int)$user['company_id'] !== $contractorId) {
        ApiResponse::forbidden('You can only view engineers from your own company');
    }
    
    // Use UserRepository - same approach as contractor/assign.php which works
    $userRepository = new UserRepository();
    $allUsers = $userRepository->findByCompanyWithRelations($contractorId);
    
    // Filter to active users only and format response
    $engineers = [];
    foreach ($allUsers as $u) {
        if ((int)($u['status'] ?? 0) === 1) {
            $engineers[] = [
                'id' => $u['id'],
                'first_name' => $u['first_name'],
                'last_name' => $u['last_name'],
                'email' => $u['email'],
                'full_name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))
            ];
        }
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/engineers', 'GET', [
        'contractor_id' => $contractorId,
        'count' => count($engineers)
    ]);
    
    ApiResponse::success($engineers, 'Engineers retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get Engineers API Error: " . $e->getMessage());
    ApiResponse::error('SERVER_ERROR', 'An error occurred while retrieving engineers', 500);
}
