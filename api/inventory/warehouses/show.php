<?php
/**
 * Inventory API - Get Single Warehouse
 * GET /api/inventory/warehouses/show.php?id={id}
 * 
 * Gets a single warehouse with details
 * 
 * Query Parameters:
 * - id: Warehouse ID (required)
 * 
 * Response: { success: bool, data: { warehouse: {} } }
 * 
 * **Validates: Requirements 1.1, 1.2**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/WarehouseRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $user = $authMiddleware->requireAuth();
    
    // Get warehouse ID from query
    $warehouseId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$warehouseId) {
        ApiResponse::validationError(['id' => 'Warehouse ID is required']);
    }
    
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
    
    // Get warehouse with company details
    $warehouse = $warehouseRepository->findWithCompany($warehouseId);
    
    if (!$warehouse) {
        ApiResponse::notFound('Warehouse not found');
    }
    
    // Check access based on user role
    $inventoryAccessService = new InventoryAccessService();
    $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    
    $hasAccess = false;
    foreach ($accessibleWarehouses as $w) {
        if ((int)$w['id'] === $warehouseId) {
            $hasAccess = true;
            break;
        }
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have access to this warehouse');
    }
    
    // Enrich with stock counts
    $warehouse['stock_count'] = $warehouseRepository->getStockCount($warehouseId);
    $warehouse['asset_count'] = $warehouseRepository->getAssetCount($warehouseId);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/warehouses/show', 'GET', [
        'warehouse_id' => $warehouseId
    ]);
    
    ApiResponse::success(['warehouse' => $warehouse], 'Warehouse retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Warehouses API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve warehouse');
}
