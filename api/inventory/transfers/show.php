<?php
/**
 * Inventory API - Get Transfer Details
 * GET /api/inventory/transfers/show.php?id={id}
 * 
 * Returns transfer details with items
 * 
 * Query Parameters:
 * - id: Transfer ID (required)
 * 
 * Response: { success: bool, data: { transfer: {}, items: [] } }
 * 
 * **Validates: Requirements 5.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/TransferService.php';
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
    
    // Get transfer ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        ApiResponse::validationError(['id' => 'Transfer ID is required']);
    }
    
    $transferId = (int)$_GET['id'];
    
    $transferService = new TransferService();
    $inventoryAccessService = new InventoryAccessService();
    
    // Get transfer details
    $transfer = $transferService->getTransfer($transferId);
    
    if (!$transfer) {
        ApiResponse::notFound('Transfer not found');
    }
    
    // Check user access
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can see all transfers
        $hasAccess = true;
    } else {
        // Check user role
        $roleType = $inventoryAccessService->getUserRoleType($user['id']);
        
        if ($roleType === InventoryAccessService::ROLE_ENGINEER) {
            // Engineers cannot view transfers
            ApiResponse::forbidden('Engineers do not have access to transfer operations');
        }
        
        // Contractors can see transfers involving their warehouses
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $hasAccess = in_array($transfer['from_warehouse_id'], $accessibleWarehouseIds) ||
                     in_array($transfer['to_warehouse_id'], $accessibleWarehouseIds);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have access to this transfer');
    }
    
    // Get transfer items
    $items = $transferService->getTransferItems($transferId);
    
    // Get stock levels for context
    $stockLevels = [];
    foreach ($items as $item) {
        $stockLevels[$item['product_id']] = $transferService->getStockLevels(
            $item['product_id'],
            $transfer['from_warehouse_id'],
            $transfer['to_warehouse_id']
        );
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/transfers/show', 'GET', [
        'transfer_id' => $transferId
    ]);
    
    ApiResponse::success([
        'transfer' => $transfer,
        'items' => $items,
        'stock_levels' => $stockLevels
    ], 'Transfer retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Transfer API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve transfer');
}
