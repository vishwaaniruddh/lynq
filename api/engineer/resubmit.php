<?php
/**
 * Engineer Feasibility Resubmission API Endpoint
 * PUT /api/engineer/resubmit.php?id={feasibilityId} - Resubmit corrected feasibility
 * GET /api/engineer/resubmit.php?id={feasibilityId}&action=editable-sections - Get editable sections
 * 
 * PUT Parameters:
 * - id: Feasibility check ID (required, in query string)
 * - [field_name]: Updated field values (only editable fields will be accepted)
 * 
 * **Validates: Requirements 12.3, 12.4**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/FeasibilityReviewService.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';

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
    
    $reviewService = new FeasibilityReviewService();
    $feasibilityService = new FeasibilityService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($reviewService, $feasibilityService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        handlePutRequest($reviewService, $feasibilityService, $authMiddleware, $user);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Support POST with _method=PUT for clients that don't support PUT
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['_method']) && strtoupper($input['_method']) === 'PUT') {
            handlePutRequest($reviewService, $feasibilityService, $authMiddleware, $user);
        } else {
            ApiResponse::methodNotAllowed(['GET', 'PUT']);
        }
    } else {
        ApiResponse::methodNotAllowed(['GET', 'PUT']);
    }
    
} catch (Exception $e) {
    error_log("Feasibility Resubmission API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process request');
}


/**
 * Handle GET request - Get editable sections for a rejected feasibility check
 * Requirements: 12.3
 */
function handleGetRequest($reviewService, $feasibilityService, $authMiddleware, $user) {
    // Validate feasibility ID
    if (!isset($_GET['id']) || (int)$_GET['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Feasibility ID is required']],
            'Validation failed'
        );
    }
    
    $feasibilityId = (int)$_GET['id'];
    $action = $_GET['action'] ?? '';
    
    // Get feasibility check to verify ownership
    $feasibility = $feasibilityService->getFeasibilityCheck($feasibilityId);
    if (!$feasibility) {
        ApiResponse::notFound('Feasibility check not found');
    }
    
    // Verify engineer owns this feasibility check
    if ((int)$feasibility['created_by'] !== (int)$user['id']) {
        // Also check if user is the assigned engineer
        $assignmentAccess = verifyEngineerAssignmentAccess($user['id'], $feasibility['assignment_id']);
        if (!$assignmentAccess) {
            ApiResponse::forbidden('You can only view your own feasibility checks');
        }
    }
    
    if ($action === 'editable-sections') {
        // Get editable sections (Requirement 12.3)
        $result = $reviewService->getEditableSections($feasibilityId);
        
        $authMiddleware->logApiAccess($user['id'], '/api/engineer/resubmit', 'GET', [
            'feasibility_id' => $feasibilityId,
            'action' => 'editable-sections'
        ]);
        
        if ($result['success']) {
            ApiResponse::success([
                'feasibility_id' => $feasibilityId,
                'editable_sections' => $result['editableSections'],
                'editable_fields' => $result['editableFields'],
                'rejection_type' => $result['rejectionType'] ?? null,
                'rejection_reason' => $result['rejectionReason'] ?? null,
                'rejected_by' => $result['rejectedBy'] ?? null,
                'rejected_at' => $result['rejectedAt'] ?? null
            ], 'Editable sections retrieved successfully');
        } else {
            switch ($result['code'] ?? 'ERROR') {
                case 'NOT_FOUND':
                    ApiResponse::notFound($result['message']);
                    break;
                case 'INVALID_STATUS':
                    ApiResponse::error('INVALID_STATUS', $result['message'], 400, [
                        'current_status' => $feasibility['approval_status'] ?? 'unknown'
                    ]);
                    break;
                default:
                    ApiResponse::error($result['code'] ?? 'ERROR', $result['message'], 400);
            }
        }
    } else {
        // Default: return feasibility check with rejection info
        $latestRejection = null;
        $editableSections = [];
        
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        if (in_array($currentStatus, ['contractor_rejected', 'adv_rejected'])) {
            $editableResult = $reviewService->getEditableSections($feasibilityId);
            if ($editableResult['success']) {
                $editableSections = $editableResult['editableSections'];
                $latestRejection = [
                    'rejection_type' => $editableResult['rejectionType'],
                    'rejection_reason' => $editableResult['rejectionReason'],
                    'rejected_by' => $editableResult['rejectedBy'],
                    'rejected_at' => $editableResult['rejectedAt']
                ];
            }
        }
        
        $authMiddleware->logApiAccess($user['id'], '/api/engineer/resubmit', 'GET', [
            'feasibility_id' => $feasibilityId
        ]);
        
        ApiResponse::success([
            'feasibility_id' => $feasibilityId,
            'feasibility' => $feasibility,
            'approval_status' => $currentStatus,
            'can_resubmit' => in_array($currentStatus, ['contractor_rejected', 'adv_rejected']),
            'editable_sections' => $editableSections,
            'latest_rejection' => $latestRejection
        ], 'Feasibility check retrieved successfully');
    }
}

/**
 * Handle PUT request - Resubmit corrected feasibility check
 * Requirements: 12.3, 12.4
 */
function handlePutRequest($reviewService, $feasibilityService, $authMiddleware, $user) {
    // Validate feasibility ID
    if (!isset($_GET['id']) || (int)$_GET['id'] <= 0) {
        ApiResponse::validationError(
            ['id' => ['Feasibility ID is required']],
            'Validation failed'
        );
    }
    
    $feasibilityId = (int)$_GET['id'];
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Remove _method if present (used for PUT simulation)
    unset($input['_method']);
    
    if (empty($input)) {
        ApiResponse::validationError(
            ['data' => ['No data provided for resubmission']],
            'Validation failed'
        );
    }
    
    // Get feasibility check to verify ownership
    $feasibility = $feasibilityService->getFeasibilityCheck($feasibilityId);
    if (!$feasibility) {
        ApiResponse::notFound('Feasibility check not found');
    }
    
    // Verify engineer owns this feasibility check
    if ((int)$feasibility['created_by'] !== (int)$user['id']) {
        // Also check if user is the assigned engineer
        $assignmentAccess = verifyEngineerAssignmentAccess($user['id'], $feasibility['assignment_id']);
        if (!$assignmentAccess) {
            ApiResponse::forbidden('You can only resubmit your own feasibility checks');
        }
    }
    
    // Resubmit feasibility check (Requirements 12.3, 12.4)
    $result = $reviewService->resubmitFeasibility($feasibilityId, $input, $user['id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/engineer/resubmit', 'PUT', [
        'feasibility_id' => $feasibilityId,
        'fields_updated' => array_keys($input)
    ]);
    
    if ($result['success']) {
        ApiResponse::success([
            'feasibility_id' => $feasibilityId,
            'feasibility' => $result['data'],
            'message' => $result['message']
        ], $result['message']);
    } else {
        switch ($result['code'] ?? 'ERROR') {
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'UNAUTHORIZED':
                ApiResponse::forbidden($result['message']);
                break;
            case 'INVALID_STATUS':
            case 'IMMUTABLE':
                ApiResponse::error($result['code'], $result['message'], 400, [
                    'current_status' => $feasibility['approval_status'] ?? 'unknown'
                ]);
                break;
            case 'NO_VALID_FIELDS':
                ApiResponse::validationError(
                    ['fields' => [$result['message']]],
                    $result['message']
                );
                break;
            default:
                ApiResponse::error($result['code'] ?? 'ERROR', $result['message'], 400);
        }
    }
}

/**
 * Verify engineer has access to an assignment
 * 
 * @param int $userId User ID
 * @param int $assignmentId Assignment ID
 * @return bool True if user has access
 */
function verifyEngineerAssignmentAccess($userId, $assignmentId) {
    $db = DatabaseConfig::getInstance();
    
    $sql = "SELECT id FROM engineer_assignments WHERE id = ? AND engineer_id = ?";
    $result = $db->getResults($sql, [$assignmentId, $userId], 'ii');
    
    return !empty($result);
}
