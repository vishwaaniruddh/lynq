<?php
/**
 * Inventory API - Create Dispatch
 * POST /api/inventory/dispatch/create.php
 * 
 * Creates a new dispatch with items
 * Supports both warehouse-based dispatch and multi-directional dispatch
 * 
 * Request Body (JSON):
 * For warehouse dispatch:
 * {
 *   "from_warehouse_id": "int (required)",
 *   "to_company_id": "int (optional)",
 *   "to_user_id": "int (optional)",
 *   "to_warehouse_id": "int (optional)",
 *   "dispatch_date": "date (optional, default: today)",
 *   "courier_id": "int (optional)",
 *   "pod_number": "string (optional)",
 *   "contact_person_name": "string (optional)",
 *   "contact_person_phone": "string (optional)",
 *   "notes": "string (optional)",
 *   "items": [...]
 * }
 * 
 * For multi-directional dispatch (contractor/engineer):
 * {
 *   "sender_type": "string (company|user)",
 *   "sender_id": "int",
 *   "to_company_id": "int (optional)",
 *   "to_user_id": "int (optional)",
 *   "to_warehouse_id": "int (optional)",
 *   "notes": "string (optional)",
 *   "items": [
 *     {
 *       "product_id": "int (required)",
 *       "quantity": "int (required)"
 *     }
 *   ]
 * }
 * 
 * Response: { success: bool, data: { dispatch: {}, items: [] } }
 * 
 * **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/DispatchService.php';
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
    
    // Determine dispatch type: multi-directional (sender_type) or warehouse-based (from_warehouse_id)
    $isMultiDirectional = !empty($input['sender_type']) && !empty($input['sender_id']);
    
    if ($isMultiDirectional) {
        // Multi-directional dispatch (contractor/engineer)
        $errors = [];
        
        $senderType = strtolower(trim($input['sender_type']));
        $senderId = (int)$input['sender_id'];
        
        // Validate sender type
        if (!in_array($senderType, ['company', 'user'])) {
            $errors['sender_type'] = 'Sender type must be "company" or "user"';
        }
        
        if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
            $errors['items'] = 'At least one item is required';
        }
        
        // Validate destination
        $hasDestination = !empty($input['to_company_id']) || 
                          !empty($input['to_user_id']) || 
                          !empty($input['to_warehouse_id']);
        
        if (!$hasDestination) {
            $errors['destination'] = 'At least one destination (company, user, or warehouse) is required';
        }
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Validate user has permission to dispatch from this entity
        $hasAccess = false;
        if ($senderType === 'company') {
            // User can dispatch from their own company
            $hasAccess = ($senderId == $user['company_id']);
        } elseif ($senderType === 'user') {
            // User can dispatch from themselves
            $hasAccess = ($senderId == $user['id']);
        }
        
        if (!$hasAccess && $user['company_type'] !== 'ADV') {
            ApiResponse::forbidden('You do not have permission to dispatch from this entity');
        }
        
        // Prepare items
        $items = [];
        foreach ($input['items'] as $index => $item) {
            if (empty($item['product_id'])) {
                $errors["items[$index].product_id"] = 'Product ID is required';
                continue;
            }
            
            $items[] = [
                'product_id' => (int)$item['product_id'],
                'quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 1
            ];
        }
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Prepare dispatch data
        $dispatchData = [
            'to_company_id' => !empty($input['to_company_id']) ? (int)$input['to_company_id'] : null,
            'to_user_id' => !empty($input['to_user_id']) ? (int)$input['to_user_id'] : null,
            'to_warehouse_id' => !empty($input['to_warehouse_id']) ? (int)$input['to_warehouse_id'] : null,
            'site_id' => !empty($input['site_id']) ? (int)$input['site_id'] : null,
            'dispatch_date' => $input['dispatch_date'] ?? date('Y-m-d'),
            'notes' => $input['notes'] ?? null
        ];
        
        // Create dispatch using appropriate method
        $dispatchService = new DispatchService();
        
        if ($senderType === 'company') {
            $result = $dispatchService->dispatchFromContractor($senderId, $dispatchData, $items, $user['id']);
        } else {
            $result = $dispatchService->dispatchFromEngineer($senderId, $dispatchData, $items, $user['id']);
        }
        
        if (!$result['success']) {
            $statusCode = 400;
            if ($result['code'] === 'INSUFFICIENT_INVENTORY') {
                $statusCode = 400;
            }
            
            ApiResponse::error($result['code'] ?? 'CREATE_DISPATCH_ERROR', $result['message'], $statusCode, $result['data'] ?? null);
        }
        
        $authMiddleware->logApiAccess($user['id'], '/api/inventory/dispatch/create', 'POST', [
            'dispatch_id' => $result['data']['dispatch']['id'] ?? null,
            'dispatch_number' => $result['data']['dispatch']['dispatch_number'] ?? null,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'item_count' => count($items)
        ]);
        
        ApiResponse::success($result['data'], $result['message'], 201);
        
    } else {
        // Traditional warehouse-based dispatch
        $errors = [];
        
        if (!isset($input['from_warehouse_id']) || !is_numeric($input['from_warehouse_id'])) {
            $errors['from_warehouse_id'] = 'Source warehouse ID is required';
        }
        
        if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
            $errors['items'] = 'At least one item is required';
        }
        
        // Validate destination - at least one must be provided
        $hasDestination = !empty($input['to_company_id']) || 
                          !empty($input['to_user_id']) || 
                          !empty($input['to_warehouse_id']);
        
        if (!$hasDestination) {
            $errors['destination'] = 'At least one destination (company, user, or warehouse) is required';
        }
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        $fromWarehouseId = (int)$input['from_warehouse_id'];
        
        // Validate user has access to source warehouse
        $inventoryAccessService = new InventoryAccessService();
        
        if (!$inventoryAccessService->canDispatchFrom($user['id'], $fromWarehouseId)) {
            ApiResponse::forbidden('You do not have permission to dispatch from this warehouse');
        }
        
        // Validate source warehouse exists and is active
        $warehouseRepository = new WarehouseRepository();
        $warehouseRepository->disableCompanyFilter();
        $fromWarehouse = $warehouseRepository->find($fromWarehouseId);
        
        if (!$fromWarehouse) {
            ApiResponse::validationError(['from_warehouse_id' => 'Source warehouse not found']);
        }
        
        if ($fromWarehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
            ApiResponse::validationError(['from_warehouse_id' => 'Cannot dispatch from inactive warehouse']);
        }
        
        // Prepare dispatch data with new shipping fields
        $dispatchData = [
            'from_warehouse_id' => $fromWarehouseId,
            'to_company_id' => !empty($input['to_company_id']) ? (int)$input['to_company_id'] : null,
            'to_user_id' => !empty($input['to_user_id']) ? (int)$input['to_user_id'] : null,
            'to_warehouse_id' => !empty($input['to_warehouse_id']) ? (int)$input['to_warehouse_id'] : null,
            'site_id' => !empty($input['site_id']) ? (int)$input['site_id'] : null,
            'material_request_id' => !empty($input['material_request_id']) ? (int)$input['material_request_id'] : null,
            'dispatch_date' => $input['dispatch_date'] ?? date('Y-m-d'),
            'courier_id' => !empty($input['courier_id']) ? (int)$input['courier_id'] : null,
            'pod_number' => !empty($input['pod_number']) ? trim($input['pod_number']) : null,
            'contact_person_name' => !empty($input['contact_person_name']) ? trim($input['contact_person_name']) : null,
            'contact_person_phone' => !empty($input['contact_person_phone']) ? trim($input['contact_person_phone']) : null,
            'notes' => $input['notes'] ?? null
        ];
        
        // Validate items - support both single asset_id and array of asset_ids
        $items = [];
        foreach ($input['items'] as $index => $item) {
            if (empty($item['product_id'])) {
                $errors["items[$index].product_id"] = 'Product ID is required';
                continue;
            }
            
            // Handle array of asset_ids for serializable items
            if (!empty($item['asset_ids']) && is_array($item['asset_ids'])) {
                // Create separate item for each asset
                foreach ($item['asset_ids'] as $assetId) {
                    $items[] = [
                        'product_id' => (int)$item['product_id'],
                        'quantity' => 1,
                        'asset_id' => (int)$assetId,
                        'serial_number' => null
                    ];
                }
            } else {
                // Single item (non-serializable or single asset)
                $items[] = [
                    'product_id' => (int)$item['product_id'],
                    'quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 1,
                    'asset_id' => !empty($item['asset_id']) ? (int)$item['asset_id'] : null,
                    'serial_number' => $item['serial_number'] ?? null
                ];
            }
        }
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Create dispatch
        $dispatchService = new DispatchService();
        $result = $dispatchService->createDispatch($dispatchData, $items, $user['id']);
        
        if (!$result['success']) {
            $statusCode = 400;
            if ($result['code'] === 'INSUFFICIENT_STOCK') {
                $statusCode = 400;
            } elseif ($result['code'] === 'WAREHOUSE_INACTIVE') {
                $statusCode = 400;
            }
            
            ApiResponse::error($result['code'] ?? 'CREATE_DISPATCH_ERROR', $result['message'], $statusCode, $result['data'] ?? null);
        }
        
        $authMiddleware->logApiAccess($user['id'], '/api/inventory/dispatch/create', 'POST', [
            'dispatch_id' => $result['data']['dispatch']['id'],
            'dispatch_number' => $result['data']['dispatch']['dispatch_number'],
            'from_warehouse_id' => $fromWarehouseId,
            'item_count' => count($items)
        ]);
        
        ApiResponse::success($result['data'], $result['message'], 201);
    }
    
} catch (Exception $e) {
    error_log("Inventory Dispatch API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create dispatch: ' . $e->getMessage());
}
