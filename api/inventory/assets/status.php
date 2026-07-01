<?php
/**
 * Inventory API - Update Asset Status
 * PUT /api/inventory/assets/status.php?id={id}
 * 
 * Updates the status of a serializable asset
 * 
 * Query Parameters:
 * - id: Asset ID (required)
 * 
 * Request Body:
 * - status: New status (optional, one of: in_stock, dispatched, assigned, in_use, returned, under_repair, scrapped, lost)
 * - working_condition: New working condition (optional, one of: working, not_working)
 * - notes: Notes about the status change (optional)
 * 
 * Response: { success: bool, data: { asset_id, old_status, new_status } }
 * 
 * **Validates: Requirements 6.1, 6.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/AssetStatusService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ApiResponse::methodNotAllowed(['PUT']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    $authMiddleware->checkRateLimit();
    $user = $authMiddleware->requireAuth();
    
    // Get asset ID from query parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        ApiResponse::validationError(['id' => 'Asset ID is required']);
    }
    
    $assetId = (int)$_GET['id'];
    
    // Parse request body
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        ApiResponse::validationError(['body' => 'Invalid JSON in request body']);
    }
    
    // Validate at least one field is provided
    if (empty($input['status']) && empty($input['working_condition'])) {
        ApiResponse::validationError([
            'status' => 'Either status or working_condition is required'
        ]);
    }
    
    $assetStatusService = new AssetStatusService();
    $inventoryAccessService = new InventoryAccessService();
    $assetRepository = new AssetRepository();
    
    // Get asset to check access
    $asset = $assetRepository->findWithDetails($assetId);
    if (!$asset) {
        ApiResponse::notFound('Asset not found');
    }
    
    // Check user access to this asset
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can update any asset
        $hasAccess = true;
    } elseif ($user['role_name'] === 'engineer' || $user['role_name'] === 'Engineer') {
        // Engineers can only update assets assigned to them
        $hasAccess = ($asset['current_holder_type'] === 'user' && 
                      $asset['current_holder_id'] == $user['id']);
    } else {
        // Contractors can update assets in their warehouses or assigned to their company
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $hasAccess = in_array($asset['warehouse_id'], $accessibleWarehouseIds) ||
                     ($asset['current_holder_type'] === 'company' && 
                      $asset['current_holder_id'] == $user['company_id']);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have access to update this asset');
    }
    
    $results = [];
    
    // Update status if provided
    if (!empty($input['status'])) {
        $newStatus = $input['status'];
        
        // Use access-controlled update for role-based restrictions
        $statusResult = $assetStatusService->updateStatusWithAccessControl(
            $assetId,
            $newStatus,
            $user['id'],
            ['notes' => $input['notes'] ?? null]
        );
        
        if (!$statusResult['success']) {
            ApiResponse::error(
                $statusResult['code'] ?? 'STATUS_UPDATE_FAILED',
                $statusResult['message'],
                400,
                $statusResult['data'] ?? null
            );
        }
        
        $results['status_update'] = $statusResult['data'];
    }
    
    // Update working condition if provided
    if (!empty($input['working_condition'])) {
        $newCondition = $input['working_condition'];
        
        // Use access-controlled update for role-based restrictions
        $conditionResult = $assetStatusService->updateWorkingConditionWithAccessControl(
            $assetId,
            $newCondition,
            $user['id']
        );
        
        if (!$conditionResult['success']) {
            ApiResponse::error(
                $conditionResult['code'] ?? 'CONDITION_UPDATE_FAILED',
                $conditionResult['message'],
                400,
                $conditionResult['data'] ?? null
            );
        }
        
        $results['condition_update'] = $conditionResult['data'];
    }
    
    // Get updated asset
    $updatedAsset = $assetRepository->findWithDetails($assetId);
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/assets/status', 'PUT', [
        'asset_id' => $assetId,
        'status' => $input['status'] ?? null,
        'working_condition' => $input['working_condition'] ?? null
    ]);
    
    ApiResponse::success([
        'asset' => $updatedAsset,
        'updates' => $results
    ], 'Asset updated successfully');
    
} catch (Exception $e) {
    error_log("Asset Status API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update asset status');
}
