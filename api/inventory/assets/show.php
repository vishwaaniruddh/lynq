<?php
/**
 * Inventory API - Get Asset Details
 * GET /api/inventory/assets/show.php?id={id}
 * 
 * Returns detailed information for a single asset
 * 
 * Query Parameters:
 * - id: Asset ID (required)
 * 
 * Response: { success: bool, data: { asset: {} } }
 * 
 * **Validates: Requirements 6.2, 12.4**
 * - 6.2: Return current status, current holder, source warehouse, and working condition
 * - 12.4: Provide answers to: current location, current holder, source warehouse, working status, repair/scrap status
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';
require_once __DIR__ . '/../../../repositories/RepairRepository.php';

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
    
    // Get asset ID from query parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        ApiResponse::validationError(['id' => 'Asset ID is required']);
    }
    
    $assetId = (int)$_GET['id'];
    
    $inventoryAccessService = new InventoryAccessService();
    $assetRepository = new AssetRepository();
    $repairRepository = new RepairRepository();
    
    // Get asset with full details
    $asset = $assetRepository->findWithDetails($assetId);
    if (!$asset) {
        ApiResponse::notFound('Asset not found');
    }
    
    // Check user access to this asset
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can see all assets
        $hasAccess = true;
    } elseif ($user['role_name'] === 'engineer' || $user['role_name'] === 'Engineer') {
        // Engineers can see assets assigned to them
        $hasAccess = ($asset['current_holder_type'] === 'user' && 
                      $asset['current_holder_id'] == $user['id']);
    } else {
        // Contractors can see assets in their warehouses or assigned to their company
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $hasAccess = in_array($asset['warehouse_id'], $accessibleWarehouseIds) ||
                     ($asset['current_holder_type'] === 'company' && 
                      $asset['current_holder_id'] == $user['company_id']);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have access to view this asset');
    }
    
    // Get active repair if under repair
    $activeRepair = null;
    if ($asset['status'] === AssetRepository::STATUS_UNDER_REPAIR) {
        $repairs = $repairRepository->findByAsset($assetId);
        foreach ($repairs as $repair) {
            if ($repair['status'] === 'pending' || $repair['status'] === 'in_progress') {
                $activeRepair = $repair;
                break;
            }
        }
    }
    
    // Build comprehensive response per Requirements 6.2 and 12.4
    $response = [
        'id' => $asset['id'],
        'serial_number' => $asset['serial_number'],
        'product_id' => $asset['product_id'],
        'product_name' => $asset['product_name'] ?? null,
        'is_repairable' => (bool)($asset['is_repairable'] ?? false),
        'status' => $asset['status'],
        'working_condition' => $asset['working_condition'],
        'warranty_expiry' => $asset['warranty_expiry'] ?? null,
        'notes' => $asset['notes'] ?? null,
        'current_location' => [
            'warehouse_id' => $asset['warehouse_id'],
            'warehouse_name' => $asset['warehouse_name'] ?? null
        ],
        'current_holder' => [
            'type' => $asset['current_holder_type'],
            'id' => $asset['current_holder_id'],
            'name' => $asset['current_holder_name'] ?? null
        ],
        'source_warehouse' => [
            'id' => $asset['source_warehouse_id'] ?? null,
            'name' => $asset['source_warehouse_name'] ?? null
        ],
        'status_info' => [
            'is_locked' => in_array($asset['status'], AssetRepository::getLockedStatuses()),
            'is_under_repair' => $asset['status'] === AssetRepository::STATUS_UNDER_REPAIR,
            'is_scrapped' => $asset['status'] === AssetRepository::STATUS_SCRAPPED,
            'is_lost' => $asset['status'] === AssetRepository::STATUS_LOST,
            'can_dispatch' => $asset['status'] === AssetRepository::STATUS_IN_STOCK
        ],
        'active_repair' => $activeRepair,
        'created_at' => $asset['created_at'] ?? null,
        'updated_at' => $asset['updated_at'] ?? null,
        'created_by' => $asset['created_by'] ?? null,
        'updated_by' => $asset['updated_by'] ?? null
    ];
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/assets/show', 'GET', [
        'asset_id' => $assetId
    ]);
    
    ApiResponse::success(['asset' => $response], 'Asset retrieved successfully');
    
} catch (Exception $e) {
    error_log("Asset Show API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve asset');
}
