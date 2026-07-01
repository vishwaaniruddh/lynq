<?php
/**
 * Inventory Counter GET API
 * GET /api/inventory/counter/get.php
 * 
 * Returns inventory counters for an entity
 * Supports entity_type and entity_id parameters
 * 
 * Query Parameters:
 * - entity_type: string (required) - Entity type (warehouse, company, user)
 * - entity_id: int (required) - Entity ID
 * - product_id: int (optional) - Filter by specific product
 * 
 * Response: { success: bool, data: { counters: array, count: int } }
 * 
 * Requirements: 7.1, 7.2, 7.3
 * - Display current stock minus pending outgoing dispatches for ADV inventory
 * - Display accepted items minus dispatched items for contractor inventory
 * - Display accepted items minus dispatched items for engineer inventory
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryCounterService.php';

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
    
    // Get query parameters
    $entityType = isset($_GET['entity_type']) ? trim($_GET['entity_type']) : null;
    $entityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : null;
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    
    // Validate required parameters
    if ($entityType === null || $entityType === '') {
        ApiResponse::validationError(['entity_type' => 'Entity type is required']);
    }
    
    if ($entityId === null || $entityId <= 0) {
        ApiResponse::validationError(['entity_id' => 'Entity ID is required and must be a positive integer']);
    }
    
    // Validate entity type
    $validEntityTypes = ['warehouse', 'company', 'user'];
    if (!in_array($entityType, $validEntityTypes)) {
        ApiResponse::validationError(['entity_type' => 'Invalid entity type. Must be one of: ' . implode(', ', $validEntityTypes)]);
    }
    
    // Check access permissions
    // ADV users can view any entity's counters
    // Contractor users can view their company's and their own counters
    // Engineer users can only view their own counters
    if ($user['company_type'] !== 'ADV') {
        if ($entityType === 'warehouse') {
            ApiResponse::forbidden('Only ADV users can view warehouse inventory counters');
        }
        
        if ($entityType === 'company' && (int)$entityId !== (int)$user['company_id']) {
            ApiResponse::forbidden('You can only view your own company\'s inventory counters');
        }
        
        if ($entityType === 'user' && (int)$entityId !== (int)$user['id']) {
            // Contractor users can view their engineers' counters
            if (strtoupper($user['company_type'] ?? '') === 'CONTRACTOR') {
                // Check if the user belongs to this contractor's company
                $userModel = new User();
                $targetUser = $userModel->find($entityId);
                if (!$targetUser || (int)$targetUser['company_id'] !== (int)$user['company_id']) {
                    ApiResponse::forbidden('You can only view inventory counters for users in your company');
                }
            } else {
                ApiResponse::forbidden('You can only view your own inventory counters');
            }
        }
    }
    
    $inventoryCounterService = new InventoryCounterService();
    
    // Get counters
    if ($productId !== null && $productId > 0) {
        // Get single product counter
        $quantity = $inventoryCounterService->getCounter($entityType, $entityId, $productId);
        $availableQuantity = $inventoryCounterService->getAvailableQuantity($entityType, $entityId, $productId);
        
        $counters = [[
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'available_quantity' => $availableQuantity
        ]];
        
        $responseData = [
            'counters' => $counters,
            'count' => 1,
            'filters' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'product_id' => $productId
            ]
        ];
    } else {
        // Get all counters for entity
        $result = $inventoryCounterService->getAllCounters($entityType, $entityId);
        
        if (!$result['success']) {
            ApiResponse::error($result['code'] ?? 'GET_COUNTERS_ERROR', $result['message'], 400);
        }
        
        $responseData = [
            'counters' => $result['data'],
            'count' => count($result['data']),
            'filters' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'product_id' => null
            ]
        ];
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/counter/get', 'GET', [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'product_id' => $productId,
        'result_count' => $responseData['count']
    ]);
    
    ApiResponse::success($responseData, 'Inventory counters retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Counter GET API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve inventory counters: ' . $e->getMessage());
}
