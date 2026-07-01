<?php
/**
 * Delegations API Endpoint
 * GET /api/delegations/index.php - List delegations with pagination, search, filters
 * POST /api/delegations/index.php - Accept, reject delegation actions
 * 
 * Query Parameters (GET):
 * - search: Search term for site name, LHO, contractor (optional)
 * - status: Filter by status (pending, accepted, rejected) (optional)
 * - contractor_id: Filter by contractor ID (optional)
 * - date_from: Filter by delegation date from (optional)
 * - date_to: Filter by delegation date to (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * - export: Set to 1 for export mode (returns all filtered records)
 * 
 * POST Actions:
 * - action=accept: Accept delegation (requires: delegation_id)
 * - action=reject: Reject delegation (requires: delegation_id, notes)
 * - action=get_contractors: Get distinct contractors for filter dropdown
 * - action=get_counts: Get delegation counts by status
 * 
 * **Validates: Requirements 3.1, 3.2, 3.4, 4.2, 4.3**
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
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($delegationService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Delegations API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}

/**
 * Handle GET request - List delegations
 */
function handleGetRequest($delegationService, $authMiddleware, $user) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $contractorId = isset($_GET['contractor_id']) ? (int)$_GET['contractor_id'] : null;
    $lho = isset($_GET['lho']) ? trim($_GET['lho']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $export = isset($_GET['export']) && $_GET['export'] == '1';
    
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
    
    if ($contractorId !== null && $contractorId > 0) {
        $filters['contractor_id'] = $contractorId;
    }
    
    if ($lho !== null && $lho !== '') {
        $filters['lho'] = $lho;
    }
    
    if ($dateFrom !== null && $dateFrom !== '') {
        $filters['date_from'] = $dateFrom . ' 00:00:00';
    }
    
    if ($dateTo !== null && $dateTo !== '') {
        $filters['date_to'] = $dateTo . ' 23:59:59';
    }
    
    // Determine if user is ADV or Contractor
    $isAdv = isAdvUser();
    
    // Handle export mode
    if ($export) {
        if ($isAdv) {
            $delegations = $delegationService->exportDelegations($user['company_id'], $filters);
        } else {
            // Contractors can only export their own delegations
            $result = $delegationService->getDelegationsByContractor($user['company_id'], array_merge($filters, ['limit' => 10000]));
            $delegations = $result['data'];
        }
        
        $authMiddleware->logApiAccess($user['id'], '/api/delegations', 'GET', [
            'action' => 'export',
            'filters' => $filters
        ]);
        
        ApiResponse::success([
            'delegations' => $delegations,
            'total' => count($delegations)
        ], 'Delegations exported successfully');
        return;
    }
    
    // Get paginated list based on user type
    if ($isAdv) {
        $result = $delegationService->getDelegationsByADV($user['company_id'], $filters);
        $contractors = $delegationService->getDistinctContractors($user['company_id']);
        $counts = $delegationService->getDelegationCountsByStatus($user['company_id']);
    } else {
        $result = $delegationService->getDelegationsByContractor($user['company_id'], $filters);
        $contractors = [];
        // Get proper counts for contractors (not from paginated result)
        $counts = $delegationService->getDelegationCountsByStatusForContractor($user['company_id']);
        // Get distinct LHOs for contractor's delegated sites
        $lhos = $delegationService->getDistinctLHOsForContractor($user['company_id']);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/delegations', 'GET', [
        'search' => $search,
        'status' => $status,
        'contractor_id' => $contractorId,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'delegations' => $result['data'],
        'pagination' => [
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total' => $result['total'],
            'total_pages' => $result['totalPages']
        ],
        'contractors' => $contractors,
        'counts' => $counts,
        'lhos' => $lhos ?? []
    ], 'Delegations retrieved successfully');
}

/**
 * Handle POST request - Accept, reject, or other actions
 */
function handlePostRequest($delegationService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'accept':
            handleAccept($delegationService, $authMiddleware, $user, $input);
            break;
            
        case 'reject':
            handleReject($delegationService, $authMiddleware, $user, $input);
            break;
            
        case 'cancel':
            handleCancel($delegationService, $authMiddleware, $user, $input);
            break;
            
        case 'get_contractors':
            handleGetContractors($delegationService, $user);
            break;
            
        case 'get_counts':
            handleGetCounts($delegationService, $user);
            break;
            
        case 'get_history':
            handleGetHistory($delegationService, $user, $input);
            break;
            
        default:
            ApiResponse::validationError(
                ['action' => ['Invalid or missing action. Valid actions: accept, reject, cancel, get_contractors, get_counts, get_history']],
                'Invalid action'
            );
    }
}

/**
 * Handle accept delegation action
 */
function handleAccept($delegationService, $authMiddleware, $user, $input) {
    if (!isset($input['delegation_id']) || (int)$input['delegation_id'] <= 0) {
        ApiResponse::validationError(
            ['delegation_id' => ['Delegation ID is required']],
            'Validation failed'
        );
    }
    
    $delegationId = (int)$input['delegation_id'];
    
    // Verify user has access to this delegation (contractor only)
    $delegation = $delegationService->getDelegation($delegationId);
    if (!$delegation) {
        ApiResponse::notFound('Delegation not found');
    }
    
    if ($delegation['contractor_id'] !== $user['company_id']) {
        ApiResponse::forbidden('You do not have permission to accept this delegation');
    }
    
    $result = $delegationService->acceptDelegation($delegationId, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/delegations', 'POST', [
        'action' => 'accept',
        'delegation_id' => $delegationId
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        if ($result['code'] === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        } elseif ($result['code'] === 'INVALID_STATUS') {
            ApiResponse::error('INVALID_STATUS', $result['message'], 400);
        } else {
            ApiResponse::serverError($result['message']);
        }
    }
}

/**
 * Handle reject delegation action
 */
function handleReject($delegationService, $authMiddleware, $user, $input) {
    if (!isset($input['delegation_id']) || (int)$input['delegation_id'] <= 0) {
        ApiResponse::validationError(
            ['delegation_id' => ['Delegation ID is required']],
            'Validation failed'
        );
    }
    
    if (!isset($input['notes']) || trim($input['notes']) === '') {
        ApiResponse::validationError(
            ['notes' => ['Rejection notes are required']],
            'Validation failed'
        );
    }
    
    $delegationId = (int)$input['delegation_id'];
    $notes = trim($input['notes']);
    
    // Verify user has access to this delegation (contractor only)
    $delegation = $delegationService->getDelegation($delegationId);
    if (!$delegation) {
        ApiResponse::notFound('Delegation not found');
    }
    
    if ($delegation['contractor_id'] !== $user['company_id']) {
        ApiResponse::forbidden('You do not have permission to reject this delegation');
    }
    
    $result = $delegationService->rejectDelegation($delegationId, $notes, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/delegations', 'POST', [
        'action' => 'reject',
        'delegation_id' => $delegationId
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        if ($result['code'] === 'NOT_FOUND') {
            ApiResponse::notFound($result['message']);
        } elseif ($result['code'] === 'INVALID_STATUS') {
            ApiResponse::error('INVALID_STATUS', $result['message'], 400);
        } elseif ($result['code'] === 'VALIDATION_ERROR') {
            ApiResponse::validationError($result['errors'] ?? [], $result['message']);
        } else {
            ApiResponse::serverError($result['message']);
        }
    }
}

/**
 * Handle cancel delegation action (ADV only)
 */
function handleCancel($delegationService, $authMiddleware, $user, $input) {
    // Only ADV users can cancel delegations
    if (!isAdvUser()) {
        ApiResponse::forbidden('Only ADV users can cancel delegations');
    }
    
    if (!isset($input['delegation_id']) || (int)$input['delegation_id'] <= 0) {
        ApiResponse::validationError(
            ['delegation_id' => ['Delegation ID is required']],
            'Validation failed'
        );
    }
    
    $delegationId = (int)$input['delegation_id'];
    
    // Verify the delegation exists and belongs to ADV's company
    $delegation = $delegationService->getDelegation($delegationId);
    if (!$delegation) {
        ApiResponse::notFound('Delegation not found');
    }
    
    $result = $delegationService->cancelDelegation($delegationId, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/delegations', 'POST', [
        'action' => 'cancel',
        'delegation_id' => $delegationId
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
 * Handle get contractors action
 */
function handleGetContractors($delegationService, $user) {
    if (!isAdvUser()) {
        ApiResponse::forbidden('Only ADV users can access contractor list');
    }
    
    $contractors = $delegationService->getDistinctContractors($user['company_id']);
    ApiResponse::success(['contractors' => $contractors], 'Contractors retrieved successfully');
}

/**
 * Handle get counts action
 */
function handleGetCounts($delegationService, $user) {
    if (isAdvUser()) {
        $counts = $delegationService->getDelegationCountsByStatus($user['company_id']);
    } else {
        // For contractors, get counts from their delegations
        $result = $delegationService->getDelegationsByContractor($user['company_id'], ['limit' => 10000]);
        $counts = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'total' => 0];
        foreach ($result['data'] as $delegation) {
            $counts[$delegation['status']]++;
            $counts['total']++;
        }
    }
    
    ApiResponse::success(['counts' => $counts], 'Counts retrieved successfully');
}

/**
 * Handle get history action
 */
function handleGetHistory($delegationService, $user, $input) {
    if (!isset($input['site_id']) || (int)$input['site_id'] <= 0) {
        ApiResponse::validationError(
            ['site_id' => ['Site ID is required']],
            'Validation failed'
        );
    }
    
    $siteId = (int)$input['site_id'];
    $history = $delegationService->getDelegationHistory($siteId);
    
    ApiResponse::success(['history' => $history], 'Delegation history retrieved successfully');
}
