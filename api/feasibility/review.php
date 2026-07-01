<?php
/**
 * Feasibility Review API Endpoint
 * POST /api/feasibility/review.php - Submit review (approve/reject)
 * GET /api/feasibility/review.php?feasibility_id={id} - Get reviews for feasibility
 * GET /api/feasibility/review.php?feasibility_id={id}&action=history - Get review history
 * 
 * POST Parameters:
 * - feasibility_id: Feasibility check ID (required)
 * - review_type: 'approval' or 'rejection' (required)
 * - rejection_type: 'overall' or 'section_specific' (required for rejections)
 * - rejected_sections: Array of section names (required for section_specific rejections)
 * - reason: Rejection reason (required for rejections, min 10 characters)
 * - comments: Optional comments (for approvals)
 * 
 * **Validates: Requirements 10.2, 10.3, 11.3, 11.4, 12.5**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/FeasibilityReviewService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authenticated user
    $user = $authMiddleware->requireAuth();
    
    $reviewService = new FeasibilityReviewService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($reviewService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($reviewService, $authMiddleware, $user);
    } else {
        ApiResponse::methodNotAllowed(['GET', 'POST']);
    }
    
} catch (Exception $e) {
    error_log("Feasibility Review API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}


/**
 * Handle GET request - Get reviews for a feasibility check
 * Requirements: 10.2, 12.5
 */
function handleGetRequest($reviewService, $authMiddleware, $user) {
    // Validate feasibility_id
    if (!isset($_GET['feasibility_id']) || (int)$_GET['feasibility_id'] <= 0) {
        ApiResponse::validationError(
            ['feasibility_id' => ['Feasibility ID is required']],
            'Validation failed'
        );
    }
    
    $feasibilityId = (int)$_GET['feasibility_id'];
    $action = $_GET['action'] ?? '';
    
    // Verify user can view reviews
    $canReview = $reviewService->canUserReview($user['id'], $feasibilityId);
    
    // Allow viewing if user can review OR if user is the engineer who created it
    if (!$canReview['canReview']) {
        // Check if user is the engineer who created the feasibility check
        if (!canViewFeasibilityReviews($user, $feasibilityId)) {
            ApiResponse::forbidden('Access denied. You do not have permission to view these reviews.');
        }
    }
    
    if ($action === 'history') {
        // Get review history (Requirement 12.5)
        $history = $reviewService->getReviewHistory($feasibilityId);
        
        $authMiddleware->logApiAccess($user['id'], '/api/feasibility/review', 'GET', [
            'feasibility_id' => $feasibilityId,
            'action' => 'history'
        ]);
        
        ApiResponse::success([
            'feasibility_id' => $feasibilityId,
            'history' => $history,
            'total_reviews' => count($history)
        ], 'Review history retrieved successfully');
    } else {
        // Get all reviews for feasibility (Requirement 10.2)
        $reviews = $reviewService->getReviewsByFeasibility($feasibilityId);
        $latestReview = $reviewService->getLatestReview($feasibilityId);
        
        $authMiddleware->logApiAccess($user['id'], '/api/feasibility/review', 'GET', [
            'feasibility_id' => $feasibilityId
        ]);
        
        ApiResponse::success([
            'feasibility_id' => $feasibilityId,
            'reviews' => $reviews,
            'latest_review' => $latestReview,
            'total_reviews' => count($reviews)
        ], 'Reviews retrieved successfully');
    }
}

/**
 * Handle POST request - Submit review (approve/reject)
 * Requirements: 10.2, 10.3, 11.3, 11.4
 */
function handlePostRequest($reviewService, $authMiddleware, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['feasibility_id']) || (int)$input['feasibility_id'] <= 0) {
        $errors['feasibility_id'] = ['Feasibility ID is required'];
    }
    
    if (!isset($input['review_type']) || !in_array($input['review_type'], ['approval', 'rejection'])) {
        $errors['review_type'] = ['Review type must be "approval" or "rejection"'];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors, 'Validation failed');
    }
    
    $feasibilityId = (int)$input['feasibility_id'];
    
    // Verify user can review this feasibility check
    $canReview = $reviewService->canUserReview($user['id'], $feasibilityId);
    if (!$canReview['canReview']) {
        ApiResponse::forbidden($canReview['reason']);
    }
    
    // Prepare review data
    $reviewData = [
        'review_type' => $input['review_type'],
        'comments' => $input['comments'] ?? null
    ];
    
    // For rejections, add rejection-specific fields
    if ($input['review_type'] === 'rejection') {
        $reviewData['rejection_type'] = $input['rejection_type'] ?? 'overall';
        $reviewData['rejected_sections'] = $input['rejected_sections'] ?? [];
        $reviewData['reason'] = $input['reason'] ?? '';
    }
    
    // Submit review
    $result = $reviewService->submitReview($feasibilityId, $reviewData, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/feasibility/review', 'POST', [
        'feasibility_id' => $feasibilityId,
        'review_type' => $input['review_type'],
        'reviewer_role' => $canReview['reviewerRole']
    ]);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        switch ($result['code'] ?? 'ERROR') {
            case 'VALIDATION_ERROR':
                ApiResponse::validationError($result['errors'] ?? [], $result['message']);
                break;
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'UNAUTHORIZED_REVIEWER':
            case 'INVALID_REVIEWER_ROLE':
                ApiResponse::forbidden($result['message']);
                break;
            case 'INVALID_STATUS':
                ApiResponse::error('INVALID_STATUS', $result['message'], 400);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'ERROR', $result['message'], 400);
        }
    }
}

/**
 * Check if user can view feasibility reviews
 * Engineers can view reviews for their own feasibility checks
 * 
 * @param array $user User data
 * @param int $feasibilityId Feasibility check ID
 * @return bool True if user can view reviews
 */
function canViewFeasibilityReviews($user, $feasibilityId) {
    $db = DatabaseConfig::getInstance();
    
    // Check if user is the engineer who created the feasibility check
    $sql = "SELECT fc.created_by, ea.engineer_id 
            FROM feasibility_checks fc
            JOIN engineer_assignments ea ON fc.assignment_id = ea.id
            WHERE fc.id = ?";
    
    $result = $db->getResults($sql, [$feasibilityId], 'i');
    
    if (!empty($result)) {
        $createdBy = (int)$result[0]['created_by'];
        $engineerId = (int)$result[0]['engineer_id'];
        
        // User can view if they created it or are the assigned engineer
        if ($user['id'] == $createdBy || $user['id'] == $engineerId) {
            return true;
        }
    }
    
    // ADV users can view all reviews
    if (isset($user['company_type']) && strtoupper($user['company_type']) === 'ADV') {
        return true;
    }
    
    // System admin can view all
    if (isset($user['is_system_admin']) && $user['is_system_admin']) {
        return true;
    }
    
    return false;
}
