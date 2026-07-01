<?php
/**
 * Material Requests API - Update Material Request Status
 * PUT /api/material-requests/status.php
 * 
 * Updates the status of a material request following valid transitions:
 * - requested → approved (ADV only)
 * - approved → dispatched (ADV only)
 * - dispatched → received (Engineer assigned to site)
 * 
 * Request Body (JSON):
 * {
 *   "id": int (required),
 *   "status": "string (required: approved|dispatched|received)",
 *   "notes": "string (optional)"
 * }
 * 
 * Response: { success: bool, data: { material_request: {} } }
 * 
 * **Validates: Requirements 5.2, 5.3, 7.3, 9.7**
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../ApiResponse.php';
require_once __DIR__ . '/../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../services/MaterialRequestService.php';
require_once __DIR__ . '/../../repositories/MaterialRequestRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ApiResponse::methodNotAllowed(['PUT']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $errors = [];
    
    if (empty($input['id'])) {
        $errors['id'] = 'Material Request ID is required';
    } elseif (!is_numeric($input['id']) || (int)$input['id'] <= 0) {
        $errors['id'] = 'Material Request ID must be a positive integer';
    }
    
    $validStatuses = [
        MaterialRequestRepository::STATUS_APPROVED,
        MaterialRequestRepository::STATUS_REJECTED,
        MaterialRequestRepository::STATUS_DISPATCHED,
        MaterialRequestRepository::STATUS_RECEIVED
    ];
    
    if (empty($input['status'])) {
        $errors['status'] = 'Status is required';
    } elseif (!in_array($input['status'], $validStatuses)) {
        $errors['status'] = 'Invalid status. Must be one of: ' . implode(', ', $validStatuses);
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $requestId = (int)$input['id'];
    $newStatus = $input['status'];
    
    // Determine user role and validate authorization
    $companyType = strtoupper($currentUser['company_type'] ?? '');
    $isAdv = $companyType === 'ADV';
    
    $materialRequestService = new MaterialRequestService();
    
    // Get current request to check authorization
    $currentRequest = $materialRequestService->getById($requestId, $isAdv ? $currentUser['company_id'] : null);
    
    if (!$currentRequest) {
        ApiResponse::notFound('Material Request not found');
    }
    
    // Authorization checks based on status transition
    if ($newStatus === MaterialRequestRepository::STATUS_APPROVED || 
        $newStatus === MaterialRequestRepository::STATUS_REJECTED ||
        $newStatus === MaterialRequestRepository::STATUS_DISPATCHED) {
        // Only ADV users can approve, reject, or dispatch
        if (!$isAdv) {
            ApiResponse::forbidden('Only ADV users can approve, reject, or dispatch material requests');
        }
    } elseif ($newStatus === MaterialRequestRepository::STATUS_RECEIVED) {
        // Engineers can confirm receipt for their assigned sites
        // ADV users can also mark as received
        if (!$isAdv) {
            // Use confirmReceipt for engineers which validates assignment
            $result = $materialRequestService->confirmReceipt($requestId, $currentUser['id']);
            
            if (!$result['success']) {
                switch ($result['code'] ?? 'UNKNOWN') {
                    case 'NOT_FOUND':
                        ApiResponse::notFound($result['message']);
                        break;
                    case 'UNAUTHORIZED':
                        ApiResponse::forbidden($result['message']);
                        break;
                    case 'INVALID_STATUS':
                        ApiResponse::error('INVALID_STATUS', $result['message'], 422);
                        break;
                    case 'INVALID_TRANSITION':
                        ApiResponse::error('INVALID_TRANSITION', $result['message'], 422);
                        break;
                    default:
                        ApiResponse::error($result['code'] ?? 'UPDATE_ERROR', $result['message'], 400);
                }
            }
            
            // Log API access
            $authMiddleware->logApiAccess($currentUser['id'], '/api/material-requests/status', 'PUT', [
                'material_request_id' => $requestId,
                'old_status' => $currentRequest['status'],
                'new_status' => $newStatus,
                'action' => 'confirm_receipt'
            ]);
            
            ApiResponse::success(
                ['material_request' => $result['data']],
                'Material Request receipt confirmed successfully'
            );
            exit;
        }
    }
    
    // ADV user status update
    $result = $materialRequestService->updateStatus(
        $requestId,
        $newStatus,
        $currentUser['id'],
        $currentUser['company_id']
    );
    
    if (!$result['success']) {
        switch ($result['code'] ?? 'UNKNOWN') {
            case 'NOT_FOUND':
                ApiResponse::notFound($result['message']);
                break;
            case 'INVALID_TRANSITION':
                ApiResponse::error('INVALID_TRANSITION', $result['message'], 422);
                break;
            default:
                ApiResponse::error($result['code'] ?? 'UPDATE_ERROR', $result['message'], 400);
        }
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/material-requests/status', 'PUT', [
        'material_request_id' => $requestId,
        'old_status' => $currentRequest['status'],
        'new_status' => $newStatus
    ]);
    
    $actionMessage = match($newStatus) {
        MaterialRequestRepository::STATUS_APPROVED => 'Material Request approved successfully',
        MaterialRequestRepository::STATUS_REJECTED => 'Material Request rejected successfully',
        MaterialRequestRepository::STATUS_DISPATCHED => 'Material Request marked as dispatched',
        MaterialRequestRepository::STATUS_RECEIVED => 'Material Request marked as received',
        default => 'Material Request status updated successfully'
    };
    
    ApiResponse::success(
        ['material_request' => $result['data']],
        $actionMessage
    );
    
} catch (Exception $e) {
    error_log("Material Requests API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update Material Request status: ' . $e->getMessage());
}
