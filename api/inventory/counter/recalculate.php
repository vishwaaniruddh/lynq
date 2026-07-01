<?php
/**
 * Inventory Counter Recalculate API
 * POST /api/inventory/counter/recalculate.php
 * 
 * Recalculates inventory counters from dispatch/receive history
 * Admin only operation for data integrity verification
 * 
 * Request Body (JSON):
 * - entity_type: string (required) - Entity type (warehouse, company, user)
 * - entity_id: int (required) - Entity ID
 * - product_id: int (optional) - Specific product to recalculate, or null for all products
 * 
 * Response: { success: bool, data: { recalculated: array, count: int } }
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
require_once __DIR__ . '/../../../repositories/InventoryCounterRepository.php';

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
    
    // Require ADV user access - admin only operation
    $user = $authMiddleware->requireAdvUser();
    
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON request body']);
    }
    
    // Get parameters
    $entityType = isset($input['entity_type']) ? trim($input['entity_type']) : null;
    $entityId = isset($input['entity_id']) ? (int)$input['entity_id'] : null;
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : null;
    
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
    
    $inventoryCounterService = new InventoryCounterService();
    $inventoryCounterRepository = new InventoryCounterRepository();
    
    $recalculatedCounters = [];
    $errors = [];
    
    if ($productId !== null && $productId > 0) {
        // Recalculate single product counter
        $result = $inventoryCounterService->recalculateCounter($entityType, $entityId, $productId, $user['id']);
        
        if ($result['success']) {
            $recalculatedCounters[] = [
                'product_id' => $productId,
                'old_quantity' => $result['data']['old_quantity'],
                'new_quantity' => $result['data']['new_quantity'],
                'discrepancy' => $result['data']['discrepancy'],
                'pending_out' => $result['data']['pending_out'],
                'pending_in' => $result['data']['pending_in']
            ];
        } else {
            $errors[] = [
                'product_id' => $productId,
                'error' => $result['message']
            ];
        }
    } else {
        // Recalculate all counters for entity
        // First, get all existing counters for this entity
        $existingCounters = $inventoryCounterRepository->getCountersByEntity($entityType, $entityId);
        
        if (empty($existingCounters)) {
            ApiResponse::success([
                'recalculated' => [],
                'count' => 0,
                'errors' => [],
                'message' => 'No existing counters found for this entity'
            ], 'No counters to recalculate');
        }
        
        // Recalculate each counter
        foreach ($existingCounters as $counter) {
            $result = $inventoryCounterService->recalculateCounter(
                $entityType, 
                $entityId, 
                $counter['product_id'], 
                $user['id']
            );
            
            if ($result['success']) {
                $recalculatedCounters[] = [
                    'product_id' => $counter['product_id'],
                    'product_name' => $counter['product_name'] ?? null,
                    'old_quantity' => $result['data']['old_quantity'],
                    'new_quantity' => $result['data']['new_quantity'],
                    'discrepancy' => $result['data']['discrepancy'],
                    'pending_out' => $result['data']['pending_out'],
                    'pending_in' => $result['data']['pending_in']
                ];
            } else {
                $errors[] = [
                    'product_id' => $counter['product_id'],
                    'product_name' => $counter['product_name'] ?? null,
                    'error' => $result['message']
                ];
            }
        }
    }
    
    // Calculate summary statistics
    $totalDiscrepancy = array_sum(array_column($recalculatedCounters, 'discrepancy'));
    $countersWithDiscrepancy = count(array_filter($recalculatedCounters, function($c) {
        return $c['discrepancy'] !== 0;
    }));
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/counter/recalculate', 'POST', [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'product_id' => $productId,
        'recalculated_count' => count($recalculatedCounters),
        'error_count' => count($errors),
        'total_discrepancy' => $totalDiscrepancy
    ]);
    
    $responseData = [
        'recalculated' => $recalculatedCounters,
        'count' => count($recalculatedCounters),
        'errors' => $errors,
        'error_count' => count($errors),
        'summary' => [
            'total_discrepancy' => $totalDiscrepancy,
            'counters_with_discrepancy' => $countersWithDiscrepancy,
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]
    ];
    
    if (!empty($errors)) {
        ApiResponse::success($responseData, 'Counters recalculated with some errors');
    } else {
        ApiResponse::success($responseData, 'Inventory counters recalculated successfully');
    }
    
} catch (Exception $e) {
    error_log("Inventory Counter Recalculate API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to recalculate inventory counters: ' . $e->getMessage());
}
