<?php
/**
 * Material Requests API - List Material Requests
 * GET /api/material-requests/list.php
 * 
 * Lists material requests with filtering and pagination capabilities
 * Filters by user role: ADV (all), Contractor (delegated sites), Engineer (assigned sites)
 * 
 * Query Parameters:
 * - search: Search term for site name/material master name (optional)
 * - status: Filter by status (requested/approved/dispatched/received) (optional)
 * - date_from: Filter by start date (optional)
 * - date_to: Filter by end date (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { material_requests: [], pagination: {}, stats: {} } }
 * 
 * **Validates: Requirements 4.1, 4.2, 6.1, 7.1, 9.5**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialRequestService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    // Determine user role
    $companyType = strtoupper($currentUser['company_type'] ?? '');
    $role = 'engineer'; // Default to most restrictive
    
    if ($companyType === 'ADV') {
        $role = MaterialRequestService::ROLE_ADV;
    } elseif ($companyType === 'CONTRACTOR') {
        $role = MaterialRequestService::ROLE_CONTRACTOR;
    } else {
        // Check if user is an engineer
        $role = MaterialRequestService::ROLE_ENGINEER;
    }
    
    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    
    // Validate status if provided
    $validStatuses = ['requested', 'approved', 'dispatched', 'received'];
    if ($status !== null && $status !== '' && !in_array($status, $validStatuses)) {
        ApiResponse::validationError(['status' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)]);
    }
    
    // Validate date formats if provided
    if ($dateFrom !== null && $dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        ApiResponse::validationError(['date_from' => 'Invalid date format. Use YYYY-MM-DD']);
    }
    
    if ($dateTo !== null && $dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        ApiResponse::validationError(['date_to' => 'Invalid date format. Use YYYY-MM-DD']);
    }
    
    // Build filters
    $filters = [
        'page' => $page,
        'limit' => $limit
    ];
    
    if ($search !== null && $search !== '') {
        $filters['search'] = $search;
    }
    
    if ($status !== null && $status !== '') {
        $filters['status'] = $status;
    }
    
    if ($dateFrom !== null && $dateFrom !== '') {
        $filters['date_from'] = $dateFrom;
    }
    
    if ($dateTo !== null && $dateTo !== '') {
        $filters['date_to'] = $dateTo;
    }
    
    $materialRequestService = new MaterialRequestService();
    
    // Get paginated material requests based on role
    $result = $materialRequestService->getByRole(
        $currentUser['id'],
        $role,
        $filters,
        $currentUser['company_id']
    );
    
    // Get status counts for stats (ADV only)
    $stats = null;
    if ($role === MaterialRequestService::ROLE_ADV) {
        $stats = $materialRequestService->getStatusCounts($currentUser['company_id']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-requests/list', 'GET', [
        'role' => $role,
        'search' => $search,
        'status' => $status,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'page' => $page,
        'limit' => $limit
    ]);
    
    $responseData = [
        'material_requests' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ];
    
    if ($stats !== null) {
        $responseData['stats'] = $stats;
    }
    
    ApiResponse::success($responseData, 'Material Requests retrieved successfully');
    
} catch (Exception $e) {
    error_log("Material Requests API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve Material Requests');
}
