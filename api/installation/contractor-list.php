<?php
/**
 * Contractor Installations List API
 * 
 * GET /api/installation/contractor-list.php
 * Returns list of installations delegated to a contractor
 * 
 * Query Parameters:
 * - contractor_id: (optional) Contractor company ID. If not provided, uses current user's company
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
 * 
 * Requirements: 2.1, 2.2
 * - 2.1: Display all sites delegated to contractor for installation
 * - 2.2: Display site details, delegation date, current status, and assigned engineer
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationAssignmentService.php';
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
    
    // Determine contractor ID
    $companyType = strtoupper($user['company_type'] ?? '');
    $isSystemAdmin = isset($user['is_system_admin']) && $user['is_system_admin'];
    
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
    
    // Verify access: Contractor users can only see their own installations
    // ADV and system admins can see any contractor's installations
    if ($companyType === 'CONTRACTOR' && (int)$user['company_id'] !== $contractorId) {
        ApiResponse::forbidden('You can only view installations delegated to your company');
    }
    
    // Build filters
    $filters = [
        'contractor_id' => $contractorId
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
    
    // Format response data (Requirements: 2.2)
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
            'delegated_at' => $installation['delegated_at'],
            'assigned_engineer_id' => $installation['assigned_engineer_id'] ? (int)$installation['assigned_engineer_id'] : null,
            'assigned_engineer_name' => $installation['assigned_engineer_name'] ?? null,
            'assigned_at' => $installation['assigned_at'],
            'eta_date' => $installation['eta_date'],
            'ada_date' => $installation['ada_date'],
            'initiated_by_name' => $installation['initiated_by_name'] ?? null,
            'created_at' => $installation['created_at'],
            'submitted_at' => $installation['submitted_at']
        ];
    }, $result['data']);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/contractor-list', 'GET', [
        'contractor_id' => $contractorId,
        'count' => count($installations),
        'total' => $result['total']
    ]);
    
    ApiResponse::success([
        'installations' => $installations,
        'total' => $result['total'],
        'page' => $result['page'],
        'limit' => $result['limit'],
        'totalPages' => $result['totalPages']
    ], 'Installations retrieved successfully');
    
} catch (Exception $e) {
    error_log("Contractor Installations List API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while retrieving installations');
}
