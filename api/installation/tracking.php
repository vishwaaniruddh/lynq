<?php
/**
 * Installation Tracking API
 * 
 * GET /api/installation/tracking.php
 * Returns paginated list of installations with filters
 * 
 * Query Parameters:
 * - status: Filter by installation status
 * - date_from: Filter by date range start (YYYY-MM-DD)
 * - date_to: Filter by date range end (YYYY-MM-DD)
 * - search: Search term for site name, ATM ID
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Paginated installation list with statuses
 * 
 * Requirements: 16.1, 16.2, 16.3
 * - 16.1: Display all installations with their current status
 * - 16.2: Display material receipt status, submission date, and approval status
 * - 16.3: Filter installations by status
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationService.php';
require_once __DIR__ . '/../../models/Installation.php';

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
    
    // Verify user has permission to view installation tracking
    if (!canViewInstallationTracking($user)) {
        ApiResponse::forbidden('Access denied. You do not have permission to view installation tracking.');
    }
    
    // Parse filter parameters
    $filters = [];
    
    // Status filter (Requirement 16.3)
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $validStatuses = Installation::getValidStatuses();
        if (in_array($_GET['status'], $validStatuses)) {
            $filters['status'] = $_GET['status'];
        }
    }
    
    // Search filter
    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $filters['search'] = trim($_GET['search']);
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
    // Contractors can only see their own company's installations
    $companyType = strtoupper($user['company_type'] ?? '');
    if ($companyType === 'CONTRACTOR') {
        $filters['company_id'] = $user['company_id'];
    }
    
    // Initialize service and get tracking data
    $installationService = new InstallationService();
    $result = $installationService->getInstallationTracking($filters);
    
    // Get status counts for summary
    $companyIdForCounts = $filters['company_id'] ?? null;
    $statusCounts = $installationService->getInstallationStatusCounts($companyIdForCounts);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/tracking', 'GET', [
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
    
} catch (Exception $e) {
    error_log("Installation Tracking API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while retrieving tracking data');
}

/**
 * Check if user can view installation tracking
 * 
 * @param array $user User data
 * @return bool True if user has permission
 */
function canViewInstallationTracking($user) {
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
    
    // Check company_type
    $companyType = strtoupper($user['company_type'] ?? '');
    
    // ADV users can view
    if ($companyType === 'ADV') {
        return true;
    }
    
    // Contractors can view their own data
    if ($companyType === 'CONTRACTOR') {
        return true;
    }
    
    return false;
}
