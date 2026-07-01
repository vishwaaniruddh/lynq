<?php
/**
 * Inventory API - Get Asset History
 * GET /api/inventory/assets/history.php?id={id}
 * 
 * Returns complete movement history for a serializable asset
 * 
 * Query Parameters:
 * - id: Asset ID (required)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 50, max: 100)
 * 
 * Response: { success: bool, data: { asset: {}, history: [], pagination: {} } }
 * 
 * **Validates: Requirements 6.2, 12.4**
 * - 6.2: Return current status, current holder, source warehouse, and working condition
 * - 12.4: Provide answers to: current location, current holder, source warehouse, working status, repair/scrap status
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAuditService.php';
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
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $inventoryAuditService = new InventoryAuditService();
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
    
    // Get complete movement history
    $allHistory = $inventoryAuditService->getAssetHistory($assetId);
    $totalCount = count($allHistory);
    
    // Apply pagination to history
    $paginatedHistory = array_slice($allHistory, $offset, $limit);
    
    // Get repair history if asset has been under repair
    $repairHistory = [];
    if ($asset['status'] === AssetRepository::STATUS_UNDER_REPAIR || 
        $asset['status'] === AssetRepository::STATUS_SCRAPPED) {
        $repairHistory = $repairRepository->findByAsset($assetId);
    }
    
    // Build comprehensive response per Requirement 12.4
    $response = [
        'asset' => [
            'id' => $asset['id'],
            'serial_number' => $asset['serial_number'],
            'product_id' => $asset['product_id'],
            'product_name' => $asset['product_name'] ?? null,
            'is_repairable' => (bool)($asset['is_repairable'] ?? false),
            'status' => $asset['status'],
            'working_condition' => $asset['working_condition'],
            'warranty_expiry' => $asset['warranty_expiry'] ?? null,
            'notes' => $asset['notes'] ?? null,
            'created_at' => $asset['created_at'] ?? null,
            'updated_at' => $asset['updated_at'] ?? null
        ],
        'current_location' => [
            'warehouse_id' => $asset['warehouse_id'],
            'warehouse_name' => $asset['warehouse_name'] ?? null,
            'holder_type' => $asset['current_holder_type'],
            'holder_id' => $asset['current_holder_id'],
            'holder_name' => $asset['current_holder_name'] ?? null
        ],
        'source_warehouse' => [
            'id' => $asset['source_warehouse_id'] ?? null,
            'name' => $asset['source_warehouse_name'] ?? null
        ],
        'status_info' => [
            'current_status' => $asset['status'],
            'working_status' => $asset['working_condition'],
            'is_locked' => in_array($asset['status'], AssetRepository::getLockedStatuses()),
            'is_under_repair' => $asset['status'] === AssetRepository::STATUS_UNDER_REPAIR,
            'is_scrapped' => $asset['status'] === AssetRepository::STATUS_SCRAPPED,
            'is_lost' => $asset['status'] === AssetRepository::STATUS_LOST
        ],
        'repair_history' => $repairHistory,
        'movement_history' => $paginatedHistory,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ];
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/assets/history', 'GET', [
        'asset_id' => $assetId,
        'page' => $page
    ]);
    
    ApiResponse::success($response, 'Asset history retrieved successfully');
    
} catch (Exception $e) {
    error_log("Asset History API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve asset history');
}
