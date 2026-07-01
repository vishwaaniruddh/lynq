<?php
/**
 * Inventory API - Complete Repair
 * POST /api/inventory/repairs/complete.php?id={id}
 * 
 * Completes a repair and returns the asset to stock
 * 
 * Query Parameters:
 * - id: Repair ID (required)
 * 
 * Request Body (JSON):
 * {
 *   "actual_cost": "decimal (optional)",
 *   "resolution": "string (optional)",
 *   "return_warehouse_id": "int (optional, defaults to source warehouse)"
 * }
 * 
 * Response: { success: bool, data: { repair_id, asset_id, asset_status, return_warehouse_id } }
 * 
 * **Validates: Requirements 7.2, 7.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/RepairService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/RepairRepository.php';
require_once __DIR__ . '/../../../repositories/WarehouseRepository.php';

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
    
    // Get repair ID from query parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        ApiResponse::validationError(['id' => 'Repair ID is required']);
    }
    
    $repairId = (int)$_GET['id'];
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        $input = []; // Allow empty body
    }
    
    // Get repair to check access
    $repairRepository = new RepairRepository();
    $repair = $repairRepository->findWithDetails($repairId);
    
    if (!$repair) {
        ApiResponse::notFound('Repair not found');
    }
    
    // Check user has permission to complete repairs
    // Only ADV users and contractors with warehouse access can complete repairs
    $inventoryAccessService = new InventoryAccessService();
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can complete any repair
        $hasAccess = true;
    } elseif ($user['role_name'] !== 'engineer' && $user['role_name'] !== 'Engineer') {
        // Contractors can complete repairs for assets in their warehouses
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $hasAccess = isset($repair['warehouse_id']) && in_array($repair['warehouse_id'], $accessibleWarehouseIds);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have permission to complete this repair');
    }
    
    // Validate return warehouse if provided
    if (!empty($input['return_warehouse_id'])) {
        $warehouseRepository = new WarehouseRepository();
        $warehouseRepository->disableCompanyFilter();
        $returnWarehouse = $warehouseRepository->find((int)$input['return_warehouse_id']);
        
        if (!$returnWarehouse) {
            ApiResponse::validationError(['return_warehouse_id' => 'Return warehouse not found']);
        }
        
        if ($returnWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            ApiResponse::validationError(['return_warehouse_id' => 'Cannot return to inactive warehouse']);
        }
    }
    
    // Prepare completion data
    $completionData = [
        'actual_cost' => isset($input['actual_cost']) ? (float)$input['actual_cost'] : null,
        'resolution' => $input['resolution'] ?? null,
        'return_warehouse_id' => !empty($input['return_warehouse_id']) ? (int)$input['return_warehouse_id'] : null
    ];
    
    // Complete repair
    $repairService = new RepairService();
    $result = $repairService->completeRepair($repairId, $completionData, $user['id']);
    
    if (!$result['success']) {
        $statusCode = 400;
        if ($result['code'] === 'REPAIR_NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'ALREADY_COMPLETED') {
            $statusCode = 409;
        } elseif ($result['code'] === 'REPAIR_CANCELLED') {
            $statusCode = 400;
        }
        
        ApiResponse::error($result['code'] ?? 'COMPLETE_REPAIR_ERROR', $result['message'], $statusCode, $result['data'] ?? null);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/repairs/complete', 'POST', [
        'repair_id' => $repairId,
        'asset_id' => $result['data']['asset_id'],
        'actual_cost' => $completionData['actual_cost']
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("Inventory Repairs API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to complete repair: ' . $e->getMessage());
}
