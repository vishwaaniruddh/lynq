<?php
/**
 * Inventory API - Get Repair Details
 * GET /api/inventory/repairs/show.php?id={id}
 * 
 * Returns detailed information about a specific repair
 * 
 * Query Parameters:
 * - id: Repair ID (required)
 * 
 * Response: { success: bool, data: { repair: {} } }
 * 
 * **Validates: Requirements 7.2, 7.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/RepairService.php';
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
    
    // Get repair ID from query parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        ApiResponse::validationError(['id' => 'Repair ID is required']);
    }
    
    $repairId = (int)$_GET['id'];
    
    $repairService = new RepairService();
    $inventoryAccessService = new InventoryAccessService();
    
    // Get repair with details
    $repair = $repairService->getRepair($repairId);
    
    if (!$repair) {
        ApiResponse::notFound('Repair not found');
    }
    
    // Check user has access to view this repair
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can view any repair
        $hasAccess = true;
    } elseif ($user['role_name'] === 'engineer' || $user['role_name'] === 'Engineer') {
        // Engineers can view repairs for assets assigned to them
        // (This would require checking the asset's current holder)
        $hasAccess = false; // Engineers typically don't need to view repair details
    } else {
        // Contractors can view repairs for assets in their warehouses
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $hasAccess = isset($repair['warehouse_id']) && in_array($repair['warehouse_id'], $accessibleWarehouseIds);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have permission to view this repair');
    }
    
    // Get additional repair information
    $repair['total_repair_cost'] = $repairService->getTotalRepairCost($repair['asset_id']);
    $repair['repair_history'] = $repairService->getAssetRepairs($repair['asset_id']);
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/repairs/show', 'GET', [
        'repair_id' => $repairId
    ]);
    
    ApiResponse::success([
        'repair' => $repair
    ], 'Repair details retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Repairs API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve repair details');
}
