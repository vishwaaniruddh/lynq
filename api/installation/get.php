<?php
/**
 * Installation Get API
 * 
 * GET /api/installation/get.php
 * Retrieves installation data by ID or site ID
 * 
 * Query Parameters:
 * - installation_id: Installation ID (optional if site_id provided)
 * - site_id: Site ID (optional if installation_id provided)
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Installation record with section statuses (on success)
 * 
 * Requirements: 3.1, 3.2
 * - 3.1: Display pre-populated site information
 * - 3.2: Display vendor and engineer information fields
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationService.php';
require_once __DIR__ . '/../../services/InstallationReviewService.php';
require_once __DIR__ . '/../../services/MaterialReceiptService.php';

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
    
    // Get query parameters - support both 'id' and 'installation_id' for convenience
    $installationId = isset($_GET['installation_id']) ? (int)$_GET['installation_id'] : 0;
    if (!$installationId && isset($_GET['id'])) {
        $installationId = (int)$_GET['id'];
    }
    $siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;
    
    if (!$installationId && !$siteId) {
        ApiResponse::validationError([
            ['field' => 'installation_id', 'message' => 'Either installation_id (or id) or site_id is required']
        ], 'Validation failed');
    }
    
    // Initialize services
    $installationService = new InstallationService();
    $reviewService = new InstallationReviewService();
    $materialReceiptService = new MaterialReceiptService();
    
    // Get installation
    $installation = null;
    if ($installationId) {
        $installation = $installationService->getInstallationWithDetails($installationId);
    } elseif ($siteId) {
        $installation = $installationService->getInstallationBySite($siteId);
        if ($installation) {
            $installation = $installationService->getInstallationWithDetails($installation['id']);
        }
    }
    
    if (!$installation) {
        ApiResponse::notFound('Installation not found');
    }
    
    // Get section statuses
    $sectionStatuses = $reviewService->getAllSectionStatuses($installation['id']);
    
    // Get material receipt status
    $materialReceipt = $materialReceiptService->getMaterialReceipt($installation['id']);
    
    // Get review history
    $reviewHistory = $reviewService->getReviewHistory($installation['id']);
    
    // Get rejected sections
    $rejectedSections = $reviewService->getRejectedSections($installation['id']);
    
    // Get editable sections
    $editableSections = $reviewService->getEditableSections($installation['id']);
    
    // Check form access
    $canAccessForm = $installationService->canAccessForm($installation['id']);
    
    // Build response data
    $responseData = [
        'installation' => $installation,
        'section_statuses' => $sectionStatuses,
        'material_receipt' => $materialReceipt,
        'review_history' => $reviewHistory,
        'rejected_sections' => $rejectedSections,
        'editable_sections' => $editableSections,
        'can_access_form' => $canAccessForm
    ];
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/get', 'GET', [
        'installation_id' => $installation['id'],
        'site_id' => $installation['site_id']
    ]);
    
    ApiResponse::success($responseData, 'Installation retrieved successfully');
    
} catch (Exception $e) {
    error_log("Installation Get API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while retrieving installation');
}
