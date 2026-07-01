<?php
/**
 * Bulk Accept Pending Receives API
 * POST /api/inventory/receive/bulk-accept.php
 * 
 * Accepts multiple pending receives in a single operation
 * Returns summary of results
 * 
 * Request Body (JSON):
 * - pending_receive_ids: array (required) - Array of pending receive IDs to accept
 * 
 * Response: { success: bool, data: { processed: array, total_receives: int, total_items: int } }
 * 
 * Requirements: 12.3, 12.4
 * - Process multiple pending receives in a single operation
 * - Provide summary of all processed items when bulk operations complete
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
    $pendingReceiveIds = isset($input['pending_receive_ids']) ? $input['pending_receive_ids'] : [];
    
    if (empty($pendingReceiveIds)) {
        ApiResponse::validationError(['pending_receive_ids' => 'Pending receive IDs array is required and cannot be empty']);
    }
    
    if (!is_array($pendingReceiveIds)) {
        ApiResponse::validationError(['pending_receive_ids' => 'Pending receive IDs must be an array']);
    }
    
    // Validate all IDs are numeric
    foreach ($pendingReceiveIds as $index => $id) {
        if (!is_numeric($id)) {
            ApiResponse::validationError(["pending_receive_ids[$index]" => 'All IDs must be numeric']);
        }
    }
    
    // Convert to integers
    $pendingReceiveIds = array_map('intval', $pendingReceiveIds);
    
    // Remove duplicates
    $pendingReceiveIds = array_unique($pendingReceiveIds);
    
    // Limit bulk operation size
    $maxBulkSize = 50;
    if (count($pendingReceiveIds) > $maxBulkSize) {
        ApiResponse::validationError(['pending_receive_ids' => "Cannot process more than $maxBulkSize pending receives at once"]);
    }
    
    // Validate user has permission to accept all pending receives
    $pendingReceiveRepository = new PendingReceiveRepository();
    $unauthorizedIds = [];
    
    foreach ($pendingReceiveIds as $id) {
        $pendingReceive = $pendingReceiveRepository->find($id);
        
        if (!$pendingReceive) {
            continue; // Will be handled by service validation
        }
        
        $canAccept = false;
        $recipientType = $pendingReceive['recipient_type'];
        $recipientId = $pendingReceive['recipient_id'];
        
        switch ($recipientType) {
            case 'user':
                if ($recipientId == $user['id'] || $user['company_type'] === 'ADV') {
                    $canAccept = true;
                }
                break;
                
            case 'company':
                if ($recipientId == $user['company_id'] || $user['company_type'] === 'ADV') {
                    $canAccept = true;
                }
                break;
                
            case 'warehouse':
                if ($user['company_type'] === 'ADV') {
                    $canAccept = true;
                }
                break;
        }
        
        if (!$canAccept) {
            $unauthorizedIds[] = $id;
        }
    }
    
    if (!empty($unauthorizedIds)) {
        ApiResponse::forbidden('You do not have permission to accept some pending receives', [
            'unauthorized_ids' => $unauthorizedIds
        ]);
    }
    
    $receiveService = new ReceiveService();
    
    // Bulk accept the pending receives
    $result = $receiveService->bulkAccept($pendingReceiveIds, $user['id']);
    
    if (!$result['success']) {
        // Check if it's a validation error with details
        if (isset($result['data']['errors'])) {
            ApiResponse::error($result['code'] ?? 'BULK_ACCEPT_ERROR', $result['message'], 400, [
                'errors' => $result['data']['errors']
            ]);
        }
        ApiResponse::error($result['code'] ?? 'BULK_ACCEPT_ERROR', $result['message'], 400);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/receive/bulk-accept', 'POST', [
        'pending_receive_ids' => $pendingReceiveIds,
        'total_receives' => $result['data']['total_receives'] ?? 0,
        'total_items' => $result['data']['total_items'] ?? 0
    ]);
    
    ApiResponse::success($result['data'], 'Bulk accept completed successfully');
    
} catch (Exception $e) {
    error_log("Bulk Accept Pending Receives API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to bulk accept pending receives: ' . $e->getMessage());
}
