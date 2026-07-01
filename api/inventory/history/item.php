<?php
/**
 * Item History API Endpoint
 * GET /api/inventory/history/item.php
 * 
 * Returns dispatch chain for an asset (serializable item)
 * Provides complete history of all transfers from origin to current holder
 * 
 * Query Parameters:
 * - asset_id: int (required) - Asset ID to get history for
 * 
 * Response: { success: bool, data: { asset: object, history: array, total_transfers: int } }
 * 
 * Requirements: 9.1, 9.2
 * - 9.1: Display complete chain of dispatches and receives from origin to current holder
 * - 9.2: Show each transfer with sender, receiver, timestamps, and acceptance status
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/DispatchChainService.php';

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
    
    // Get query parameters
    $assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : null;
    
    // Validate required parameters
    if ($assetId === null || $assetId <= 0) {
        ApiResponse::validationError(['asset_id' => 'Asset ID is required and must be a positive integer']);
    }
    
    // Initialize service
    $dispatchChainService = new DispatchChainService();
    
    // Get item history
    $result = $dispatchChainService->getItemHistory($assetId);
    
    if (!$result['success']) {
        $statusCode = 400;
        if ($result['code'] === 'ASSET_NOT_FOUND') {
            $statusCode = 404;
        }
        ApiResponse::error($result['code'] ?? 'GET_HISTORY_ERROR', $result['message'], $statusCode);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/history/item', 'GET', [
        'asset_id' => $assetId,
        'total_transfers' => $result['data']['total_transfers'] ?? 0
    ]);
    
    ApiResponse::success($result['data'], 'Item history retrieved successfully');
    
} catch (Exception $e) {
    error_log("Item History API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve item history: ' . $e->getMessage());
}
