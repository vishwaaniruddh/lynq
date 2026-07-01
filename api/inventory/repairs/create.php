<?php
/**
 * Inventory API - Create Repair Request
 * POST /api/inventory/repairs/create.php
 * 
 * Creates a new repair request for a repairable asset
 * 
 * Request Body (JSON):
 * {
 *   "asset_id": "int (required)",
 *   "repair_vendor": "string (required)",
 *   "estimated_cost": "decimal (optional)",
 *   "send_date": "date (optional, default: today)",
 *   "expected_return_date": "date (optional)",
 *   "diagnosis": "string (optional)",
 *   "notes": "string (optional)"
 * }
 * 
 * Response: { success: bool, data: { repair: {}, asset_status: string } }
 * 
 * **Validates: Requirements 7.2, 7.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/RepairService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';

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
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['asset_id']) || !is_numeric($input['asset_id'])) {
        $errors['asset_id'] = 'Asset ID is required';
    }
    
    if (empty($input['repair_vendor'])) {
        $errors['repair_vendor'] = 'Repair vendor is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $assetId = (int)$input['asset_id'];
    
    // Check user has access to the asset
    $assetRepository = new AssetRepository();
    $asset = $assetRepository->findWithDetails($assetId);
    
    if (!$asset) {
        ApiResponse::notFound('Asset not found');
    }
    
    $inventoryAccessService = new InventoryAccessService();
    
    // Validate user has access to this asset
    $hasAccess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can create repairs for any asset
        $hasAccess = true;
    } elseif ($user['role_name'] === 'engineer' || $user['role_name'] === 'Engineer') {
        // Engineers can request repairs for assets assigned to them
        $hasAccess = $asset['current_holder_type'] === 'user' && 
                     $asset['current_holder_id'] == $user['id'];
    } else {
        // Contractors can create repairs for assets in their warehouses
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $hasAccess = in_array($asset['warehouse_id'], $accessibleWarehouseIds) ||
                     ($asset['current_holder_type'] === 'company' && 
                      $asset['current_holder_id'] == $user['company_id']);
    }
    
    if (!$hasAccess) {
        ApiResponse::forbidden('You do not have permission to create a repair request for this asset');
    }
    
    // Prepare repair data
    $repairData = [
        'repair_vendor' => trim($input['repair_vendor']),
        'estimated_cost' => isset($input['estimated_cost']) ? (float)$input['estimated_cost'] : null,
        'send_date' => $input['send_date'] ?? date('Y-m-d'),
        'expected_return_date' => $input['expected_return_date'] ?? null,
        'diagnosis' => $input['diagnosis'] ?? null,
        'notes' => $input['notes'] ?? null
    ];
    
    // Validate dates if provided
    if (!empty($repairData['send_date']) && !strtotime($repairData['send_date'])) {
        ApiResponse::validationError(['send_date' => 'Invalid send date format']);
    }
    
    if (!empty($repairData['expected_return_date']) && !strtotime($repairData['expected_return_date'])) {
        ApiResponse::validationError(['expected_return_date' => 'Invalid expected return date format']);
    }
    
    // Create repair request
    $repairService = new RepairService();
    $result = $repairService->initiateRepair($assetId, $repairData, $user['id']);
    
    if (!$result['success']) {
        $statusCode = 400;
        if ($result['code'] === 'ASSET_NOT_FOUND') {
            $statusCode = 404;
        } elseif ($result['code'] === 'NOT_REPAIRABLE') {
            $statusCode = 400;
        } elseif ($result['code'] === 'ASSET_LOCKED') {
            $statusCode = 400;
        } elseif ($result['code'] === 'REPAIR_IN_PROGRESS') {
            $statusCode = 409;
        }
        
        ApiResponse::error($result['code'] ?? 'CREATE_REPAIR_ERROR', $result['message'], $statusCode, $result['data'] ?? null);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/repairs/create', 'POST', [
        'repair_id' => $result['data']['repair']['id'],
        'asset_id' => $assetId,
        'repair_vendor' => $repairData['repair_vendor']
    ]);
    
    ApiResponse::success($result['data'], $result['message'], 201);
    
} catch (Exception $e) {
    error_log("Inventory Repairs API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create repair request: ' . $e->getMessage());
}
