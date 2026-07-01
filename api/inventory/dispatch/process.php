<?php
/**
 * Inventory API - Process Dispatch Status
 * POST /api/inventory/dispatch/process.php
 * 
 * Updates dispatch status (pending -> in_transit -> delivered)
 * 
 * Request Body (JSON):
 * {
 *   "dispatch_id": "int (required)",
 *   "status": "string (required: in_transit, delivered, cancelled)"
 * }
 * 
 * Response: { success: bool, data: { dispatch_id: int, status: string } }
 * 
 * **Validates: Requirements 5.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/DispatchService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';

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
    
    if (!isset($input['dispatch_id']) || !is_numeric($input['dispatch_id'])) {
        $errors['dispatch_id'] = 'Dispatch ID is required';
    }
    
    if (!isset($input['status']) || trim($input['status']) === '') {
        $errors['status'] = 'Status is required';
    } elseif (!DispatchRepository::isValidStatus($input['status'])) {
        $errors['status'] = 'Invalid status. Must be in_transit, delivered, or cancelled';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    $dispatchId = (int)$input['dispatch_id'];
    $newStatus = trim($input['status']);
    
    $dispatchService = new DispatchService();
    $inventoryAccessService = new InventoryAccessService();
    
    // Get dispatch details
    $dispatch = $dispatchService->getDispatch($dispatchId);
    
    if (!$dispatch) {
        ApiResponse::notFound('Dispatch not found');
    }
    
    // Check user has permission to process this dispatch
    $canProcess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can process any dispatch
        $canProcess = true;
    } else {
        // Contractors can only process dispatches from their warehouses
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        $canProcess = in_array($dispatch['from_warehouse_id'], $accessibleWarehouseIds);
    }
    
    if (!$canProcess) {
        ApiResponse::forbidden('You do not have permission to process this dispatch');
    }
    
    // Process the dispatch
    $result = $dispatchService->processDispatch($dispatchId, $newStatus, $user['id']);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'PROCESS_DISPATCH_ERROR', $result['message'], 400);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/dispatch/process', 'POST', [
        'dispatch_id' => $dispatchId,
        'dispatch_number' => $dispatch['dispatch_number'],
        'old_status' => $dispatch['status'],
        'new_status' => $newStatus
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("Inventory Dispatch API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process dispatch: ' . $e->getMessage());
}
