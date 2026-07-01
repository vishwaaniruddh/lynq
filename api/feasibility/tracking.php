<?php
/**
 * Feasibility Tracking API Endpoint
 * GET /api/feasibility/tracking.php - Get tracking data with filters
 * 
 * Query Parameters:
 * - status: Filter by feasibility status (pending_eta, eta_submitted, ada_submitted, feasibility_completed)
 * - search: Search term for site name, LHO, city
 * - contractor_id: Filter by contractor
 * - engineer_id: Filter by engineer
 * - date_from: Filter by date range start
 * - date_to: Filter by date range end
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * **Validates: Requirements 8.1, 8.3**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Verify user has permission to view feasibility tracking
    // This should be available to admin, ADV users, and contractors
    if (!canViewFeasibilityTracking($user)) {
        ApiResponse::forbidden('Access denied. You do not have permission to view feasibility tracking.');
    }
    
    $feasibilityService = new FeasibilityService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($feasibilityService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET']);
    }
    
} catch (Exception $e) {
    error_log("Feasibility Tracking API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Check if user can view feasibility tracking
 * 
 * @param array $user User data
 * @return bool True if user has permission
 */
function canViewFeasibilityTracking($user) {
    // System admin can view all
    if (isset($user['is_system_admin']) && $user['is_system_admin']) {
        return true;
    }
    
    // Check role-based permissions
    $roleId = $user['role_id'] ?? 0;
    
    // Admin roles (typically role_id 1 or 2) can view
    if ($roleId <= 2) {
        return true;
    }
    
    // Check company_type (the actual field name in user data)
    $companyType = strtoupper($user['company_type'] ?? '');
    
    // ADV users can view
    if ($companyType === 'ADV') {
        return true;
    }
    
    // Contractors can view their own engineers' data
    if ($companyType === 'CONTRACTOR') {
        return true;
    }
    
    return false;
}

/**
 * Handle GET request - Get tracking data with filters
 * Requirements: 8.1, 8.3
 */
function handleGetRequest($feasibilityService, $authMiddleware, $user) {
    // Parse filter parameters
    $filters = [];
    
    // Status filter (Requirement 8.3)
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $validStatuses = [
            'pending_eta', 'eta_submitted', 'ada_submitted', 'feasibility_completed',
            'pending_contractor_review', 'contractor_approved', 'contractor_rejected',
            'adv_approved', 'adv_rejected'
        ];
        if (in_array($_GET['status'], $validStatuses)) {
            $filters['status'] = $_GET['status'];
        }
    }
    
    // Search filter
    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $filters['search'] = trim($_GET['search']);
    }
    
    // Contractor filter
    if (isset($_GET['contractor_id']) && (int)$_GET['contractor_id'] > 0) {
        $filters['contractor_id'] = (int)$_GET['contractor_id'];
    }
    
    // Engineer filter
    if (isset($_GET['engineer_id']) && (int)$_GET['engineer_id'] > 0) {
        $filters['engineer_id'] = (int)$_GET['engineer_id'];
    }
    
    // Date range filters
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Pagination
    $filters['page'] = max(1, (int)($_GET['page'] ?? 1));
    $filters['limit'] = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    
    // Apply user-based restrictions
    // Contractors can only see their own engineers' data
    $companyType = strtoupper($user['company_type'] ?? '');
    if ($companyType === 'CONTRACTOR') {
        $filters['contractor_id'] = $user['company_id'];
    }
    
    // Get tracking data (Requirement 8.1)
    $result = $feasibilityService->getFeasibilityTracking($filters);
    
    // Get status counts for summary (pass contractor_id for contractors to see only their counts)
    $contractorIdForCounts = $filters['contractor_id'] ?? null;
    $statusCounts = $feasibilityService->getFeasibilityStatusCounts($contractorIdForCounts);
    
    $authMiddleware->logApiAccess($user['id'], '/api/feasibility/tracking', 'GET', [
        'filters' => $filters
    ]);
    
    ApiResponse::success([
        'tracking' => $result['data'] ?? [],
        'pagination' => [
            'page' => $result['page'] ?? $filters['page'],
            'limit' => $result['limit'] ?? $filters['limit'],
            'total' => $result['total'] ?? 0,
            'total_pages' => $result['totalPages'] ?? 0
        ],
        'status_counts' => $statusCounts,
        'filters_applied' => array_filter($filters, function($v) { return $v !== null && $v !== ''; })
    ], 'Tracking data retrieved successfully');
}
