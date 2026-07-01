<?php
/**
 * Material Masters API - List Material Masters
 * GET /api/material-masters/list.php
 * 
 * Lists material masters with filtering and pagination capabilities
 * ADV users only
 * 
 * Query Parameters:
 * - search: Search term for name/description (optional)
 * - status: Filter by status (active/inactive) (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { material_masters: [], pagination: {} } }
 * 
 * **Validates: Requirements 1.1, 9.1**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialMasterService.php';

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
    
    // Require ADV user access - Material Masters are ADV only
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get query parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    
    // Validate status if provided
    if ($status !== null && $status !== '' && !in_array($status, ['active', 'inactive'])) {
        ApiResponse::validationError(['status' => 'Invalid status. Must be "active" or "inactive"']);
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
    
    $materialMasterService = new MaterialMasterService();
    
    // Get paginated material masters
    $result = $materialMasterService->getAll($filters, $currentUser['company_id']);
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-masters/list', 'GET', [
        'search' => $search,
        'status' => $status,
        'page' => $page,
        'limit' => $limit
    ]);
    
    ApiResponse::success([
        'material_masters' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ]
    ], 'Material Masters retrieved successfully');
    
} catch (Exception $e) {
    error_log("Material Masters API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve Material Masters');
}
