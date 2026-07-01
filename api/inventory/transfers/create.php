<?php
/**
 * Inventory API - Create Transfer
 * POST /api/inventory/transfers/create.php
 * 
 * Creates a new inter-warehouse transfer
 * 
 * Request Body (JSON):
 * {
 *   "from_warehouse_id": "int (required)",
 *   "to_warehouse_id": "int (required)",
 *   "transfer_date": "date (optional, default: today)",
 *   "notes": "string (optional)",
 *   "items": [
 *     {
 *       "product_id": "int (required)",
 *       "quantity": "int (required for non-serializable)",
 *       "asset_id": "int (required for serializable, or use serial_number)",
 *       "serial_number": "string (alternative to asset_id for serializable)"
 *     }
 *   ],
 *   "process_immediately": "bool (optional, default: false)"
 * }
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
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['from_warehouse_id']) || !is_numeric($input['from_warehouse_id'])) {
        $errors['from_warehouse_id'] = 'Source warehouse ID is required';
    }
    
    if (!isset($input['to_warehouse_id']) || !is_numeric($input['to_warehouse_id'])) {
        $errors['to_warehouse_id'] = 'Destination warehouse ID is required';
    }
    
    if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
        $errors['items'] = 'At least one item is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $fromWarehouseId = (int)$input['from_warehouse_id'];
    $toWarehouseId = (int)$input['to_warehouse_id'];
    
    // Validate source and destination are different
    if ($fromWarehouseId === $toWarehouseId) {
        ApiResponse::validationError(['to_warehouse_id' => 'Source and destination warehouses must be different']);
    }
    
    // Validate user has access to both warehouses
    $inventoryAccessService = new InventoryAccessService();
    
    // Check user role - engineers cannot create transfers
    $roleType = $inventoryAccessService->getUserRoleType($user['id']);
    if ($roleType === InventoryAccessService::ROLE_ENGINEER) {
        ApiResponse::forbidden('Engineers do not have permission to create transfers');
    }
    
    // Check access to source warehouse
    if (!$inventoryAccessService->canDispatchFrom($user['id'], $fromWarehouseId)) {
        ApiResponse::forbidden('You do not have permission to transfer from this warehouse');
    }
    
    // Validate warehouses exist and are active
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
    
    $fromWarehouse = $warehouseRepository->find($fromWarehouseId);
    if (!$fromWarehouse) {
        ApiResponse::validationError(['from_warehouse_id' => 'Source warehouse not found']);
    }
    if ($fromWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
        ApiResponse::validationError(['from_warehouse_id' => 'Cannot transfer from inactive warehouse']);
    }
    
    $toWarehouse = $warehouseRepository->find($toWarehouseId);
    if (!$toWarehouse) {
        ApiResponse::validationError(['to_warehouse_id' => 'Destination warehouse not found']);
    }
    if ($toWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
        ApiResponse::validationError(['to_warehouse_id' => 'Cannot transfer to inactive warehouse']);
    }
    
    // For contractors, validate both warehouses belong to their company
    if ($user['company_type'] !== 'ADV') {
        if ((int)$fromWarehouse['company_id'] !== (int)$user['company_id']) {
            ApiResponse::forbidden('You can only transfer from your company\'s warehouses');
        }
        if ((int)$toWarehouse['company_id'] !== (int)$user['company_id']) {
            ApiResponse::forbidden('You can only transfer to your company\'s warehouses');
        }
    }
    
    // Prepare transfer data
    $transferData = [
        'from_warehouse_id' => $fromWarehouseId,
        'to_warehouse_id' => $toWarehouseId,
        'transfer_date' => $input['transfer_date'] ?? date('Y-m-d'),
        'notes' => $input['notes'] ?? null
    ];
    
    // Validate items
    $items = [];
    foreach ($input['items'] as $index => $item) {
        if (empty($item['product_id'])) {
            $errors["items[$index].product_id"] = 'Product ID is required';
            continue;
        }
        
        $items[] = [
            'product_id' => (int)$item['product_id'],
            'quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 1,
            'asset_id' => !empty($item['asset_id']) ? (int)$item['asset_id'] : null,
            'serial_number' => $item['serial_number'] ?? null
        ];
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    // Create transfer
    $transferService = new TransferService();
    $result = $transferService->createTransfer($transferData, $items, $user['id']);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'CREATE_TRANSFER_ERROR', $result['message'], 400, $result['data'] ?? null);
    }
    
    // Process immediately if requested
    $processImmediately = isset($input['process_immediately']) && $input['process_immediately'] === true;
    if ($processImmediately && $result['success']) {
        $processResult = $transferService->processTransfer($result['data']['transfer']['id'], $user['id']);
        if ($processResult['success']) {
            $result['data']['transfer']['status'] = 'completed';
            $result['message'] = 'Transfer created and processed successfully';
        } else {
            // Transfer created but processing failed
            $result['message'] = 'Transfer created but processing failed: ' . $processResult['message'];
            $result['data']['process_error'] = $processResult['message'];
        }
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/transfers/create', 'POST', [
        'transfer_id' => $result['data']['transfer']['id'],
        'transfer_number' => $result['data']['transfer']['transfer_number'],
        'from_warehouse_id' => $fromWarehouseId,
        'to_warehouse_id' => $toWarehouseId,
        'item_count' => count($items),
        'processed_immediately' => $processImmediately
    ]);
    
    ApiResponse::success($result['data'], $result['message'], 201);
    
} catch (Exception $e) {
    error_log("Inventory Transfer API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create transfer: ' . $e->getMessage());
}
