<?php
/**
 * Inventory API - Get Dispatch Details
 * GET /api/inventory/dispatch/show.php?id={id}
 * 
 * Returns dispatch details with items
 * 
 * Query Parameters:
 * - id: Dispatch ID (required)
 * 
 * Response: { success: bool, data: { dispatch: {}, items: [] } }
 * 
 * **Validates: Requirements 5.1, 5.3**
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
    
    // Get dispatch ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        ApiResponse::validationError(['id' => 'Dispatch ID is required']);
    }
    
    $dispatchId = (int)$_GET['id'];
    
    $dispatchService = new DispatchService();
    $inventoryAccessService = new InventoryAccessService();
    
    // Get dispatch details
    $dispatch = $dispatchService->getDispatch($dispatchId);
    
    if (!$dispatch) {
        ApiResponse::notFound('Dispatch not found');
    }
    
    // Check user access
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can see all dispatches
        $hasAccess = true;
    } elseif ($user['role_name'] === 'engineer' || $user['role_name'] === 'Engineer') {
        // Engineers can see dispatches to themselves
        $hasAccess = ($dispatch['to_user_id'] == $user['id']);
    } else {
        // Contractors can see dispatches to their company or from their warehouses
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $hasAccess = ($dispatch['to_company_id'] == $user['company_id']) ||
                     in_array($dispatch['from_warehouse_id'], $accessibleWarehouseIds);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have access to this dispatch');
    }
    
    // Get dispatch items
    $items = $dispatchService->getDispatchItems($dispatchId);
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/dispatch/show', 'GET', [
        'dispatch_id' => $dispatchId
    ]);
    
    ApiResponse::success([
        'dispatch' => $dispatch,
        'items' => $items
    ], 'Dispatch retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Dispatch API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve dispatch');
}
