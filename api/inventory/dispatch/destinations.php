<?php
/**
 * Inventory API - Get Valid Dispatch Destinations
 * GET /api/inventory/dispatch/destinations.php
 * 
 * Returns valid destinations for a sender based on their type and ID.
 * - ADV warehouses can dispatch to any contractor or engineer
 * - Contractors can dispatch to their engineers or back to ADV
 * - Engineers can dispatch to their contractor or back to ADV
 * 
 * Query Parameters:
 * - sender_type: Type of sender ('warehouse', 'company', 'user') (required)
 * - sender_id: ID of the sender entity (required)
 * 
 * Response: { 
 *   success: bool, 
 *   data: { 
 *     warehouses: [], 
 *     companies: [], 
 *     users: [] 
 *   } 
 * }
 * 
 * **Validates: Requirements 5.5, 6.5**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/DispatchService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Validate required parameters
    $errors = [];
    
    if (!isset($_GET['sender_type']) || empty($_GET['sender_type'])) {
        $errors['sender_type'] = 'Sender type is required';
    }
    
    if (!isset($_GET['sender_id']) || !is_numeric($_GET['sender_id'])) {
        $errors['sender_id'] = 'Sender ID is required and must be numeric';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $senderType = strtolower(trim($_GET['sender_type']));
    $senderId = (int)$_GET['sender_id'];
    
    // Validate sender_type is one of the allowed values
    $validSenderTypes = ['warehouse', 'company', 'user'];
    if (!in_array($senderType, $validSenderTypes)) {
        ApiResponse::validationError([
            'sender_type' => 'Invalid sender type. Must be one of: ' . implode(', ', $validSenderTypes)
        ]);
    }
    
    // Validate user has permission to view destinations for this sender
    $inventoryAccessService = new InventoryAccessService();
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can view destinations for any sender
        $hasAccess = true;
    } elseif ($senderType === 'warehouse') {
        // Check if user has access to this warehouse
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        $hasAccess = in_array($senderId, $accessibleWarehouseIds);
    } elseif ($senderType === 'company') {
        // User can view destinations for their own company
        $hasAccess = ($senderId == $user['company_id']);
    } elseif ($senderType === 'user') {
        // User can view destinations for themselves
        $hasAccess = ($senderId == $user['id']);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have permission to view destinations for this sender');
    }
    
    // Get valid destinations
    $dispatchService = new DispatchService();
    $result = $dispatchService->getValidDestinations($senderType, $senderId);
    
    if (!$result['success']) {
        ApiResponse::error(
            $result['code'] ?? 'GET_DESTINATIONS_ERROR',
            $result['message'],
            400
        );
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/dispatch/destinations', 'GET', [
        'sender_type' => $senderType,
        'sender_id' => $senderId
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("Inventory Dispatch Destinations API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve valid destinations');
}
