<?php
/**
 * Section Review API
 * 
 * POST /api/installation/review-section.php
 * Approves or rejects a section of an installation
 * 
 * Request Body:
 * - installation_id: (required) Installation ID
 * - section: (required) Section identifier
 * - action: (required) 'approve' or 'reject'
 * - reason: (required for reject) Rejection reason (min 10 characters)
 * - remarks: (optional) Approval remarks
 * 
 * Response:
 * - success: boolean
 * - message: string
 * - data: Review record and updated status (on success)
 * 
 * Requirements: 12.1-12.7, 13.1-13.6
 * - 12.1: Display review panel with approve/reject options for each section
 * - 12.2: Record approval with reviewer ID, timestamp, and optional remarks
 * - 12.3: Require rejection reason (minimum 10 characters)
 * - 12.4: Update section status to "rejected" and notify engineer
 * - 12.5: Update installation status to "contractor_approved" when all sections approved
 * - 12.6: Update installation status to "contractor_rejected" when any section rejected
 * - 12.7: Make installation available for ADV review when contractor approves all sections
 * - 13.1: Display final approval panel for ADV users
 * - 13.2: Display previous contractor review comments
 * - 13.3: Update installation status to "adv_approved" when ADV approves all sections
 * - 13.4: Require rejection reason and update status to "adv_rejected"
 * - 13.5: Notify contractor and engineer on ADV rejection
 * - 13.6: Prevent modifications to ADV-approved installations
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/InstallationReviewService.php';
require_once __DIR__ . '/../../models/InstallationCheckpoint.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::methodNotAllowed(['POST']);
    }
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::error('INVALID_REQUEST', 'Invalid JSON request body', 400);
    }
    
    // Validate required fields
    $installationId = isset($input['installation_id']) ? (int)$input['installation_id'] : 0;
    $section = isset($input['section']) ? trim($input['section']) : '';
    $action = isset($input['action']) ? strtolower(trim($input['action'])) : '';
    $reason = isset($input['reason']) ? trim($input['reason']) : '';
    $remarks = isset($input['remarks']) ? trim($input['remarks']) : null;
    
    $errors = [];
    
    if (!$installationId) {
        $errors[] = ['field' => 'installation_id', 'message' => 'Installation ID is required'];
    }
    
    if (!$section) {
        $errors[] = ['field' => 'section', 'message' => 'Section is required'];
    }
    
    if (!$action || !in_array($action, ['approve', 'reject'])) {
        $errors[] = ['field' => 'action', 'message' => 'Action must be "approve" or "reject"'];
    }
    
    if ($action === 'reject' && strlen($reason) < 10) {
        $errors[] = ['field' => 'reason', 'message' => 'Rejection reason must be at least 10 characters'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    // Initialize service
    $reviewService = new InstallationReviewService();
    
    // Check if user can review this installation
    $canReview = $reviewService->canUserReview($user['id'], $installationId);
    
    if (!$canReview['canReview']) {
        ApiResponse::forbidden($canReview['reason']);
    }
    
    $level = $canReview['level'];
    
    // Perform the review action
    if ($action === 'approve') {
        $result = $reviewService->approveSection($installationId, $section, $user['id'], $remarks, $level);
    } else {
        $result = $reviewService->rejectSection($installationId, $section, $user['id'], $reason, $level);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/installation/review-section', 'POST', [
        'installation_id' => $installationId,
        'section' => $section,
        'action' => $action,
        'level' => $level,
        'success' => $result['success']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        $statusCode = 400;
        if ($result['code'] === 'NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'INSTALLATION_LOCKED' || $result['code'] === 'INVALID_STATUS') {
            $statusCode = 403;
        } elseif ($result['code'] === 'REASON_TOO_SHORT') {
            ApiResponse::validationError([
                ['field' => 'reason', 'message' => $result['message']]
            ], $result['message']);
            return;
        }
        
        ApiResponse::error($result['code'], $result['message'], $statusCode);
    }
    
} catch (Exception $e) {
    error_log("Section Review API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    ApiResponse::serverError('An error occurred while processing the review');
}
