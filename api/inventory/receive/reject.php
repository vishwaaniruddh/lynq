<?php
/**
 * Reject Pending Receive API
 * POST /api/inventory/receive/reject.php
 * 
 * Rejects a pending receive with a reason
 * Validates rejection reason is provided
 * 
 * Request Body (JSON):
 * - pending_receive_id: int (required) - ID of the pending receive to reject
 * - reason: string (required) - Rejection reason
 * 
 * Response: { success: bool, data: { pending_receive_id: int, dispatch_id: int, status: string, rejection_reason: string, rejected_at: string } }
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4
 * - Restore items to sender's inventory counter when contractor rejects
 * - Restore items to sender's inventory counter when engineer rejects
 * - Require rejection reason when materials are rejected
 * - Update dispatch status to "rejected" and record the reason
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/ReceiveService.php';
require_once __DIR__ . '/../../../repositories/PendingReceiveRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON request body']);
    }
    
    // Validate required fields
    $pendingReceiveId = isset($input['pending_receive_id']) ? (int)$input['pending_receive_id'] : 0;
    $reason = isset($input['reason']) ? trim($input['reason']) : '';
    
    $errors = [];
    
    if (!$pendingReceiveId) {
        $errors['pending_receive_id'] = 'Pending receive ID is required';
    }
    
    if (empty($reason)) {
        $errors['reason'] = 'Rejection reason is required';
    } elseif (strlen($reason) < 5) {
        $errors['reason'] = 'Rejection reason must be at least 5 characters';
    } elseif (strlen($reason) > 1000) {
        $errors['reason'] = 'Rejection reason must not exceed 1000 characters';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    // Get pending receive to validate permission
    $pendingReceiveRepository = new PendingReceiveRepository();
    $pendingReceive = $pendingReceiveRepository->findWithItems($pendingReceiveId);
    
    if (!$pendingReceive) {
        ApiResponse::notFound('Pending receive not found');
    }
    
    // Validate user has permission to reject this pending receive
    $canReject = false;
    $recipientType = $pendingReceive['recipient_type'];
    $recipientId = $pendingReceive['recipient_id'];
    
    switch ($recipientType) {
        case 'user':
            // User can reject their own pending receives
            if ($recipientId == $user['id']) {
                $canReject = true;
            }
            // ADV users can reject on behalf of any user
            if ($user['company_type'] === 'ADV') {
                $canReject = true;
            }
            break;
            
        case 'company':
            // Users from the same company can reject
            if ($recipientId == $user['company_id']) {
                $canReject = true;
            }
            // ADV users can reject on behalf of any company
            if ($user['company_type'] === 'ADV') {
                $canReject = true;
            }
            break;
            
        case 'warehouse':
            // ADV users can reject warehouse receives
            if ($user['company_type'] === 'ADV') {
                $canReject = true;
            }
            break;
    }
    
    if (!$canReject) {
        ApiResponse::forbidden('You do not have permission to reject this pending receive');
    }
    
    // Check if already processed
    if ($pendingReceive['status'] !== 'pending') {
        ApiResponse::error('ALREADY_PROCESSED', 'This pending receive has already been processed', 400, [
            'current_status' => $pendingReceive['status']
        ]);
    }
    
    $receiveService = new ReceiveService();
    
    // Reject the pending receive
    $result = $receiveService->rejectReceive($pendingReceiveId, $user['id'], $reason);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'REJECT_ERROR', $result['message'], 400);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/receive/reject', 'POST', [
        'pending_receive_id' => $pendingReceiveId,
        'dispatch_id' => $result['data']['dispatch_id'] ?? null,
        'reason_length' => strlen($reason)
    ]);
    
    ApiResponse::success($result['data'], 'Pending receive rejected successfully');
    
} catch (Exception $e) {
    error_log("Reject Pending Receive API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to reject pending receive: ' . $e->getMessage());
}
