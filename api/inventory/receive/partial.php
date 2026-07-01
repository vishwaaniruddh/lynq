<?php
/**
 * Partial Accept Pending Receive API
 * POST /api/inventory/receive/partial.php
 * 
 * Partially accepts a pending receive with item quantities
 * Creates discrepancy records for differences
 * 
 * Request Body (JSON):
 * - pending_receive_id: int (required) - ID of the pending receive
 * - items: array (required) - Array of items with received quantities
 *   - dispatch_item_id: int (required) - ID of the dispatch item
 *   - received_quantity: int (required) - Quantity actually received
 *   - notes: string (optional) - Notes for this item
 * - notes: string (optional) - General notes for the partial acceptance
 * 
 * Response: { success: bool, data: { pending_receive_id: int, dispatch_id: int, status: string, total_expected: int, total_accepted: int, discrepancies: array } }
 * 
 * Requirements: 10.1, 10.2, 10.3
 * - Allow specifying quantities actually received for non-serializable items
 * - Update recipient's inventory only for accepted quantities
 * - Create discrepancy record for the difference
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
    $items = isset($input['items']) ? $input['items'] : [];
    $notes = isset($input['notes']) ? trim($input['notes']) : null;
    
    $errors = [];
    
    if (!$pendingReceiveId) {
        $errors['pending_receive_id'] = 'Pending receive ID is required';
    }
    
    if (empty($items)) {
        $errors['items'] = 'Items array is required and cannot be empty';
    } elseif (!is_array($items)) {
        $errors['items'] = 'Items must be an array';
    } else {
        // Validate each item
        foreach ($items as $index => $item) {
            if (!isset($item['dispatch_item_id']) || !is_numeric($item['dispatch_item_id'])) {
                $errors["items[$index].dispatch_item_id"] = 'Dispatch item ID is required and must be numeric';
            }
            if (!isset($item['received_quantity']) || !is_numeric($item['received_quantity'])) {
                $errors["items[$index].received_quantity"] = 'Received quantity is required and must be numeric';
            } elseif ((int)$item['received_quantity'] < 0) {
                $errors["items[$index].received_quantity"] = 'Received quantity cannot be negative';
            }
        }
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
    
    // Validate user has permission to partially accept this pending receive
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
    
    // Format items for service
    $acceptedItems = array_map(function($item) {
        return [
            'dispatch_item_id' => (int)$item['dispatch_item_id'],
            'received_quantity' => (int)$item['received_quantity'],
            'notes' => isset($item['notes']) ? trim($item['notes']) : null
        ];
    }, $items);
    
    $receiveService = new ReceiveService();
    
    // Partially accept the pending receive
    $result = $receiveService->partialAccept($pendingReceiveId, $user['id'], $acceptedItems, $notes);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'PARTIAL_ACCEPT_ERROR', $result['message'], 400);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/receive/partial', 'POST', [
        'pending_receive_id' => $pendingReceiveId,
        'dispatch_id' => $result['data']['dispatch_id'] ?? null,
        'item_count' => count($acceptedItems),
        'total_expected' => $result['data']['total_expected'] ?? 0,
        'total_accepted' => $result['data']['total_accepted'] ?? 0,
        'discrepancy_count' => count($result['data']['discrepancies'] ?? [])
    ]);
    
    ApiResponse::success($result['data'], 'Pending receive partially accepted');
    
} catch (Exception $e) {
    error_log("Partial Accept Pending Receive API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to partially accept pending receive: ' . $e->getMessage());
}
