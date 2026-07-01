<?php
/**
 * Engineer Installations List API
 * 
 * GET /api/installation/engineer-list.php
 * Returns list of installations assigned to an engineer
 * 
 * Query Parameters:
 * - engineer_id: (optional) Engineer user ID. If not provided, uses current user
 * - status: (optional) Filter by installation status
 * - page: (optional) Page number for pagination (default: 1)
 * - limit: (optional) Items per page (default: 10, max: 100)
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Object containing:
 *   - installations: Array of installation records
 *   - total: Total count
 *   - page: Current page
 *   - limit: Items per page
 *   - totalPages: Total pages
 *   - pending_eta_count: Count of installations pending ETA
 *   - pending_ada_count: Count of installations pending ADA
 * 
 * Requirements: 3.1
 * - 3.1: Display sites with status "pending_eta" or "pending_ada" assigned to engineer
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationETAService.php';
require_once __DIR__ . '/../../repositories/InstallationRepository.php';

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
    
    // Determine engineer ID
    $isSystemAdmin = isset($user['is_system_admin']) && $user['is_system_admin'];
    $companyType = strtoupper($user['company_type'] ?? '');
    
    // Get engineer_id from query params or use current user
    $engineerId = isset($_GET['engineer_id']) ? (int)$_GET['engineer_id'] : 0;
    
    if (!$engineerId) {
        // If no engineer_id provided, use current user
        $engineerId = (int)$user['id'];
    }
    
    // Verify access: Users can only see their own installations unless they are ADV/admin
    // or a contractor admin/manager viewing their engineers
    if ($engineerId !== (int)$user['id'] && !$isSystemAdmin && $companyType !== 'ADV') {
        // Check if user is contractor admin/manager viewing their own engineer
        if ($companyType === 'CONTRACTOR') {
            // Verify the engineer belongs to the same company
            require_once __DIR__ . '/../../repositories/UserRepository.php';
            $userRepo = new UserRepository();
            $engineer = $userRepo->find($engineerId);
            
            if (!$engineer || (int)$engineer['company_id'] !== (int)$user['company_id']) {
                ApiResponse::forbidden('You can only view installations for engineers in your company');
            }
        } else {
            ApiResponse::forbidden('You can only view your own installations');
        }
    }
    
    // Build filters
    $filters = [
        'engineer_id' => $engineerId
    ];
    
    // Optional status filter
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $filters['status'] = $_GET['status'];
    }
    
    // Pagination
    if (isset($_GET['page'])) {
        $filters['page'] = (int)$_GET['page'];
    }
    if (isset($_GET['limit'])) {
        $filters['limit'] = (int)$_GET['limit'];
    }
    
    // Order by
    if (isset($_GET['orderBy'])) {
        $filters['orderBy'] = $_GET['orderBy'];
    }
    if (isset($_GET['orderDir'])) {
        $filters['orderDir'] = $_GET['orderDir'];
    }
    
    // Initialize repository and get installations
    $installationRepository = new InstallationRepository();
    $result = $installationRepository->findAllWithFilters($filters);
    
    // Get ETA/ADA pending counts (Requirements: 3.1)
    $etaService = new InstallationETAService();
    $pendingETASites = $etaService->getPendingETASites($engineerId);
    $pendingADASites = $etaService->getPendingADASites($engineerId);
    
    // Format response data
    $installations = array_map(function($installation) {
        return [
            'id' => (int)$installation['id'],
            'site_id' => (int)$installation['site_id'],
            'site_name' => $installation['site_name'] ?? $installation['atm_id'],
            'atm_id' => $installation['atm_id'],
            'address' => $installation['address'],
            'city' => $installation['city'],
            'state' => $installation['state'],
            'lho' => $installation['lho'] ?? $installation['site_lho'],
            'status' => $installation['status'],
            'contractor_id' => $installation['contractor_id'] ? (int)$installation['contractor_id'] : null,
            'contractor_name' => $installation['contractor_name'] ?? null,
            'delegated_at' => $installation['delegated_at'],
            'assigned_at' => $installation['assigned_at'],
            'eta_date' => $installation['eta_date'],
            'eta_submitted_at' => $installation['eta_submitted_at'],
            'ada_date' => $installation['ada_date'],
            'ada_submitted_at' => $installation['ada_submitted_at'],
            'initiated_by_name' => $installation['initiated_by_name'] ?? null,
            'created_at' => $installation['created_at'],
            'submitted_at' => $installation['submitted_at']
        ];
    }, $result['data']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/engineer-list', 'GET', [
        'engineer_id' => $engineerId,
        'count' => count($installations),
        'total' => $result['total']
    ]);
    
    ApiResponse::success([
        'installations' => $installations,
        'total' => $result['total'],
        'page' => $result['page'],
        'limit' => $result['limit'],
        'totalPages' => $result['totalPages'],
        'pending_eta_count' => count($pendingETASites),
        'pending_ada_count' => count($pendingADASites)
    ], 'Installations retrieved successfully');
    
} catch (Exception $e) {
    error_log("Engineer Installations List API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while retrieving installations');
}
