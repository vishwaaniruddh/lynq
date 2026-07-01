<?php
/**
 * Inventory API - Process Transfer
 * POST /api/inventory/transfers/process.php
 * 
 * Processes a pending transfer (executes the stock movement)
 * 
 * Request Body (JSON):
 * {
 *   "transfer_id": "int (required)"
 * }
 * 
 * Response: { success: bool, data: { transfer_id: int, status: string } }
 * 
 * **Validates: Requirements 5.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/TransferService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/TransferRepository.php';

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
    if (!isset($input['transfer_id']) || !is_numeric($input['transfer_id'])) {
        ApiResponse::validationError(['transfer_id' => 'Transfer ID is required']);
    }
    
    $transferId = (int)$input['transfer_id'];
    
    $transferService = new TransferService();
    $inventoryAccessService = new InventoryAccessService();
    
    // Get transfer details
    $transfer = $transferService->getTransfer($transferId);
    
    if (!$transfer) {
        ApiResponse::notFound('Transfer not found');
    }
    
    // Check user has permission to process this transfer
    $canProcess = false;
    
    if ($user['company_type'] === 'ADV') {
        // ADV users can process any transfer
        $canProcess = true;
    } else {
        // Check user role
        $roleType = $inventoryAccessService->getUserRoleType($user['id']);
        
        if ($roleType === InventoryAccessService::ROLE_ENGINEER) {
            ApiResponse::forbidden('Engineers do not have permission to process transfers');
        }
        
        // Contractors can only process transfers from their warehouses
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $canProcess = in_array($transfer['from_warehouse_id'], $accessibleWarehouseIds);
    }
    
    if (!$canProcess) {
        ApiResponse::forbidden('You do not have permission to process this transfer');
    }
    
    // Check transfer status
    if ($transfer['status'] !== TransferRepository::STATUS_PENDING) {
        ApiResponse::error('INVALID_STATUS', 'Transfer can only be processed from pending status', 400, [
            'current_status' => $transfer['status'],
            'required_status' => TransferRepository::STATUS_PENDING
        ]);
    }
    
    // Process the transfer
    $result = $transferService->processTransfer($transferId, $user['id']);
    
    if (!$result['success']) {
        ApiResponse::error($result['code'] ?? 'PROCESS_TRANSFER_ERROR', $result['message'], 400);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/transfers/process', 'POST', [
        'transfer_id' => $transferId,
        'transfer_number' => $transfer['transfer_number'],
        'from_warehouse_id' => $transfer['from_warehouse_id'],
        'to_warehouse_id' => $transfer['to_warehouse_id']
    ]);
    
    ApiResponse::success($result['data'], $result['message']);
    
} catch (Exception $e) {
    error_log("Inventory Transfer API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to process transfer: ' . $e->getMessage());
}
