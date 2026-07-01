<?php
/**
 * Accept Pending Receive API
 * POST /api/inventory/receive/accept.php
 * 
 * Accepts a pending receive by ID
 * Validates user has permission to accept
 * 
 * Request Body (JSON):
 * - pending_receive_id: int (required) - ID of the pending receive to accept
 * 
 * Response: { success: bool, data: { pending_receive_id: int, dispatch_id: int, status: string, accepted_at: string } }
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4
 * - Increment contractor's inventory counter when materials are accepted
 * - Increment engineer's inventory counter when materials are accepted
 * - Update dispatch status to "delivered" and acknowledgment status to "acknowledged"
 * - Record acceptance timestamp and accepting user
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
    
    if (!$pendingReceiveId) {
        ApiResponse::validationError(['pending_receive_id' => 'Pending receive ID is required']);
    }
    
    // Get pending receive to validate permission
    $pendingReceiveRepository = new PendingReceiveRepository();
    $pendingReceive = $pendingReceiveRepository->findWithItems($pendingReceiveId);
    
    if (!$pendingReceive) {
        ApiResponse::notFound('Pending receive not found');
    }
    
    // Validate user has permission to accept this pending receive
    $canAccept = false;
    $recipientType = $pendingReceive['recipient_type'];
    $recipientId = $pendingReceive['recipient_id'];
    
    switch ($recipientType) {
        case 'user':
            // User can accept their own pending receives
            if ($recipientId == $user['id']) {
                $canAccept = true;
            }
            // ADV users can accept on behalf of any user
            if ($user['company_type'] === 'ADV') {
                $canAccept = true;
            }
            break;
            
        case 'company':
            // Users from the same company can accept
            if ($recipientId == $user['company_id']) {
                $canAccept = true;
            }
            // ADV users can accept on behalf of any company
            if ($user['company_type'] === 'ADV') {
                $canAccept = true;
            }
            break;
            
        case 'warehouse':
            // ADV users can accept warehouse receives
            if ($user['company_type'] === 'ADV') {
                $canAccept = true;
            }
            break;
    }
    
    if (!$canAccept) {
        ApiResponse::forbidden('You do not have permission to accept this pending receive');
    }
    
    // Check if already processed
    if ($pendingReceive['status'] !== 'pending') {
        ApiResponse::error('ALREADY_PROCESSED', 'This pending receive has already been processed', 400, [
            'current_status' => $pendingReceive['status']
        ]);
    }
    
    $receiveService = new ReceiveService();
    
    // Accept the pending receive
    $result = $receiveService->acceptReceive($pendingReceiveId, $user['id']);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'ACCEPT_ERROR', $result['message'], 400);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/receive/accept', 'POST', [
        'pending_receive_id' => $pendingReceiveId,
        'dispatch_id' => $result['data']['dispatch_id'] ?? null
    ]);
    
    ApiResponse::success($result['data'], 'Pending receive accepted successfully');
    
} catch (Exception $e) {
    error_log("Accept Pending Receive API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to accept pending receive: ' . $e->getMessage());
}
