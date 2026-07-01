<?php
/**
 * Inventory API - Add Stock Entry
 * POST /api/inventory/stock/create.php
 * 
 * Adds stock for non-serializable items or creates assets for serializable items
 * 
 * Request Body (JSON):
 * {
 *   "product_id": "int (required)",
 *   "warehouse_id": "int (required)",
 *   "quantity": "int (required for non-serializable)",
 *   "serial_number": "string (required for serializable)",
 *   "serial_numbers": "array (optional, for bulk serializable)",
 *   "notes": "string (optional)",
 *   "warranty_expiry": "date (optional, for serializable)"
 * }
 * 
 * Response: { success: bool, data: { stock/asset: {} } }
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/StockService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/ProductRepository.php';
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
    
    if (!isset($input['product_id']) || !is_numeric($input['product_id'])) {
        $errors['product_id'] = 'Product ID is required';
    }
    
    if (!isset($input['warehouse_id']) || !is_numeric($input['warehouse_id'])) {
        $errors['warehouse_id'] = 'Warehouse ID is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $productId = (int)$input['product_id'];
    $warehouseId = (int)$input['warehouse_id'];
    
    // Validate product exists
    $productRepository = new ProductRepository();
    $product = $productRepository->find($productId);
    
    if (!$product) {
        ApiResponse::validationError(['product_id' => 'Product not found']);
    }
    
    // Validate warehouse exists and user has access
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
    $warehouse = $warehouseRepository->find($warehouseId);
    
    if (!$warehouse) {
        ApiResponse::validationError(['warehouse_id' => 'Warehouse not found']);
    }
    
    // Check user access to warehouse
    $inventoryAccessService = new InventoryAccessService();
    $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
    
    if (!in_array($warehouseId, $accessibleWarehouseIds)) {
        ApiResponse::forbidden('You do not have access to this warehouse');
    }
    
    // Check warehouse is active
    if ($warehouse['status'] !== WarehouseRepository::STATUS_ACTIVE) {
        ApiResponse::validationError(['warehouse_id' => 'Cannot add stock to inactive warehouse']);
    }
    
    $stockService = new StockService();
    
    if ($product['is_serializable']) {
        // Handle serializable product - requires serial number(s)
        $serialNumbers = [];
        
        if (!empty($input['serial_numbers']) && is_array($input['serial_numbers'])) {
            $serialNumbers = $input['serial_numbers'];
        } elseif (!empty($input['serial_number'])) {
            $serialNumbers = [$input['serial_number']];
        } else {
            ApiResponse::validationError(['serial_number' => 'Serial number is required for serializable products']);
        }
        
        // Validate serial numbers are not empty
        $serialNumbers = array_filter(array_map('trim', $serialNumbers));
        if (empty($serialNumbers)) {
            ApiResponse::validationError(['serial_number' => 'At least one valid serial number is required']);
        }
        
        // Additional data for assets
        $additionalData = [];
        if (!empty($input['warranty_expiry'])) {
            $additionalData['warranty_expiry'] = $input['warranty_expiry'];
        }
        if (!empty($input['notes'])) {
            $additionalData['notes'] = $input['notes'];
        }
        
        if (count($serialNumbers) === 1) {
            // Single asset
            $result = $stockService->addAsset($productId, $warehouseId, $serialNumbers[0], $user['id'], $additionalData);
            
            if (!$result['success']) {
                ApiResponse::error($result['code'] ?? 'ADD_ASSET_ERROR', $result['message'], 400);
            }
            
            $authMiddleware->logApiAccess($user['id'], '/api/inventory/stock/create', 'POST', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'serial_number' => $serialNumbers[0],
                'asset_id' => $result['data']['id']
            ]);
            
            ApiResponse::success(['asset' => $result['data']], $result['message'], 201);
        } else {
            // Multiple assets
            $result = $stockService->addAssets($productId, $warehouseId, $serialNumbers, $user['id']);
            
            $authMiddleware->logApiAccess($user['id'], '/api/inventory/stock/create', 'POST', [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'serial_numbers_count' => count($serialNumbers),
                'success_count' => $result['successCount'],
                'error_count' => $result['errorCount']
            ]);
            
            if ($result['errorCount'] > 0 && $result['successCount'] === 0) {
                ApiResponse::error('ADD_ASSETS_ERROR', $result['message'], 400, ['errors' => $result['errors']]);
            }
            
            $statusCode = $result['success'] ? 201 : 207; // 207 Multi-Status for partial success
            ApiResponse::success([
                'total' => $result['total'],
                'successCount' => $result['successCount'],
                'errorCount' => $result['errorCount'],
                'createdIds' => $result['createdIds'],
                'errors' => $result['errors']
            ], $result['message'], $statusCode);
        }
    } else {
        // Handle non-serializable product - requires quantity
        if (!isset($input['quantity']) || !is_numeric($input['quantity']) || (int)$input['quantity'] <= 0) {
            ApiResponse::validationError(['quantity' => 'Quantity must be a positive integer for non-serializable products']);
        }
        
        $quantity = (int)$input['quantity'];
        
        $result = $stockService->addStock($productId, $warehouseId, $quantity, $user['id']);
        
        if (!$result['success']) {
            ApiResponse::error($result['code'] ?? 'ADD_STOCK_ERROR', $result['message'], 400);
        }
        
        $authMiddleware->logApiAccess($user['id'], '/api/inventory/stock/create', 'POST', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity
        ]);
        
        ApiResponse::success(['stock' => $result['data']], $result['message'], 201);
    }
    
} catch (Exception $e) {
    error_log("Inventory Stock API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to add stock: ' . $e->getMessage());
}
