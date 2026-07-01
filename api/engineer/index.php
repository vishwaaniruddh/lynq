<?php
/**
 * Engineer Assignments API Endpoint
 * GET /api/engineer/index.php - List assignments for logged-in engineer
 * POST /api/engineer/index.php - Update assignment status
 * 
 * Query Parameters (GET):
 * - search: Search term for site name, LHO, city (optional)
 * - status: Filter by status (assigned, in_progress, completed) (optional)
 * - city: Filter by city (optional)
 * - state: Filter by state (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * POST Actions:
 * - action=update_status: Update assignment status (requires: assignment_id, status)
 * - action=get_counts: Get assignment counts by status
 * - action=get_filters: Get available filter options (cities, states)
 * 
 * **Validates: Requirements 6.1, 6.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/EngineerAssignmentService.php';
require_once __DIR__ . '/../../services/SiteAccessService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Verify user is an engineer (contractor user)
    if (!isEngineerUser($user['id'])) {
        ApiResponse::forbidden('Access denied. Engineer users only.');
    }
    
    $assignmentService = new EngineerAssignmentService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($assignmentService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($assignmentService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Engineer API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List assignments for engineer
 */
function handleGetRequest($assignmentService, $authMiddleware, $user) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $feasibilityStatus = isset($_GET['feasibility_status']) ? $_GET['feasibility_status'] : null;
    $lho = isset($_GET['lho']) ? trim($_GET['lho']) : null;
    $city = isset($_GET['city']) ? trim($_GET['city']) : null;
    $state = isset($_GET['state']) ? trim($_GET['state']) : null;
    $export = isset($_GET['export']) && $_GET['export'] === '1';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = $export ? 10000 : min(100, max(1, (int)($_GET['limit'] ?? 20)));
    
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
    
    if ($feasibilityStatus !== null && $feasibilityStatus !== '') {
        $filters['feasibility_status'] = $feasibilityStatus;
    }
    
    if ($lho !== null && $lho !== '') {
        $filters['lho'] = $lho;
    }
    
    if ($city !== null && $city !== '') {
        $filters['city'] = $city;
    }
    
    if ($state !== null && $state !== '') {
        $filters['state'] = $state;
    }
    
    // Get assignments for this engineer only (Requirement 6.1)
    $result = $assignmentService->getAssignmentsByEngineer($user['id'], $filters);
    
    // Get counts for stats (by feasibility status for stats cards)
    $feasibilityCounts = $assignmentService->getAssignmentCountsByFeasibilityStatusForEngineer($user['id']);
    
    // Get filter options
    $lhos = $assignmentService->getDistinctLHOsForEngineer($user['id']);
    $cities = $assignmentService->getDistinctCitiesForEngineer($user['id']);
    $states = $assignmentService->getDistinctStatesForEngineer($user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer', 'GET', [
        'search' => $search,
        'status' => $status,
        'feasibility_status' => $feasibilityStatus,
        'lho' => $lho,
        'city' => $city,
        'state' => $state,
        'page' => $page,
        'export' => $export
    ]);
    
    ApiResponse::success([
        'assignments' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ],
        'counts' => $feasibilityCounts,
        'filters' => [
            'lhos' => $lhos,
            'cities' => $cities,
            'states' => $states
        ]
    ], 'Assignments retrieved successfully');
}

/**
 * Handle POST request - Update status or get data
 */
function handlePostRequest($assignmentService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            handleUpdateStatus($assignmentService, $authMiddleware, $user, $input);
            break;
            
        case 'get_counts':
            handleGetCounts($assignmentService, $user);
            break;
            
        case 'get_filters':
            handleGetFilters($assignmentService, $user);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: update_status, get_counts, get_filters']],
                'Invalid action'
            );
    }
}

/**
 * Handle update status action
 */
function handleUpdateStatus($assignmentService, $authMiddleware, $user, $input) {
    if (!isset($input['assignment_id']) || (int)$input['assignment_id'] <= 0) {
        ApiResponse::validationError(
            ['assignment_id' => ['Assignment ID is required']],
            'Validation failed'
        );
    }
    
    if (!isset($input['status']) || trim($input['status']) === '') {
        ApiResponse::validationError(
            ['status' => ['Status is required']],
            'Validation failed'
        );
    }
    
    $assignmentId = (int)$input['assignment_id'];
    $status = trim($input['status']);
    
    // Validate status value
    $validStatuses = ['assigned', 'in_progress', 'completed'];
    if (!in_array($status, $validStatuses)) {
        ApiResponse::validationError(
            ['status' => ['Invalid status. Valid values: assigned, in_progress, completed']],
            'Validation failed'
        );
    }
    
    // Verify engineer has access to this assignment (Requirement 6.3)
    if (!$assignmentService->canEngineerAccess($assignmentId, $user['id'])) {
        ApiResponse::forbidden('You do not have permission to update this assignment');
    }
    
    $result = $assignmentService->updateAssignmentStatus($assignmentId, $status, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer', 'POST', [
        'action' => 'update_status',
        'assignment_id' => $assignmentId,
        'status' => $status
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        if ($result['code'] === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        } else {
            ApiResponse::serverError($result['message']);
        }
    }
}

/**
 * Handle get counts action
 */
function handleGetCounts($assignmentService, $user) {
    $counts = $assignmentService->getAssignmentCountsByStatusForEngineer($user['id']);
    ApiResponse::success(['counts' => $counts], 'Counts retrieved successfully');
}

/**
 * Handle get filters action
 */
function handleGetFilters($assignmentService, $user) {
    $cities = $assignmentService->getDistinctCitiesForEngineer($user['id']);
    $states = $assignmentService->getDistinctStatesForEngineer($user['id']);
    
    ApiResponse::success([
        'cities' => $cities,
        'states' => $states
    ], 'Filters retrieved successfully');
}
