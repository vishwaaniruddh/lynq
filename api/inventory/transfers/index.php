<?php
/**
 * Inventory API - List Transfers
 * GET /api/inventory/transfers/index.php
 * 
 * Returns inter-warehouse transfer history with filtering options
 * 
 * Query Parameters:
 * - from_warehouse_id: Filter by source warehouse (optional)
 * - to_warehouse_id: Filter by destination warehouse (optional)
 * - status: Filter by status (pending/in_transit/completed/cancelled)
 * - date_from: Filter by transfer date from (optional)
 * - date_to: Filter by transfer date to (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { transfers: [], pagination: {} } }
 * 
 * **Validates: Requirements 5.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../services/TransferService.php';
require_once __DIR__ . '/../../../repositories/TransferRepository.php';

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
    $fromWarehouseId = isset($_GET['from_warehouse_id']) ? (int)$_GET['from_warehouse_id'] : null;
    $toWarehouseId = isset($_GET['to_warehouse_id']) ? (int)$_GET['to_warehouse_id'] : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Validate status if provided
    if ($status !== null && $status !== '' && !TransferRepository::isValidStatus($status)) {
        ApiResponse::validationError(['status' => 'Invalid status. Must be pending, in_transit, completed, or cancelled']);
    }
    
    $inventoryAccessService = new InventoryAccessService();
    $transferService = new TransferService();
    
    // Get accessible warehouses for the user
    $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
    
    // Build filters
    $filters = [];
    
    // ADV users can see all transfers
    // Contractors can only see transfers involving their warehouses
    // Engineers cannot see transfers
    if ($user['company_type'] !== 'ADV') {
        $roleType = $inventoryAccessService->getUserRoleType($user['id']);
        
        if ($roleType === InventoryAccessService::ROLE_ENGINEER) {
            // Engineers cannot view transfers
            ApiResponse::forbidden('Engineers do not have access to transfer operations');
        }
        
        // Contractors can only see transfers involving their warehouses
        $filters['warehouse_scope'] = $accessibleWarehouseIds;
    }
    
    // Apply additional filters
    if ($fromWarehouseId !== null) {
        // Validate access to warehouse
        if ($user['company_type'] !== 'ADV' && !in_array($fromWarehouseId, $accessibleWarehouseIds)) {
            ApiResponse::forbidden('You do not have access to this warehouse');
        }
        $filters['from_warehouse_id'] = $fromWarehouseId;
    }
    
    if ($toWarehouseId !== null) {
        // Validate access to warehouse
        if ($user['company_type'] !== 'ADV' && !in_array($toWarehouseId, $accessibleWarehouseIds)) {
            ApiResponse::forbidden('You do not have access to this warehouse');
        }
        $filters['to_warehouse_id'] = $toWarehouseId;
    }
    
    if ($status !== null && $status !== '') {
        $filters['status'] = $status;
    }
    
    if ($dateFrom !== null && $dateFrom !== '') {
        $filters['date_from'] = $dateFrom;
    }
    
    if ($dateTo !== null && $dateTo !== '') {
        $filters['date_to'] = $dateTo;
    }
    
    // Get transfer history
    $transfers = $transferService->getTransferHistory($filters);
    
    // Get total count for pagination
    $totalCount = count($transfers);
    
    // Apply pagination
    $paginatedTransfers = array_slice($transfers, $offset, $limit);
    
    // Enrich with items for each transfer
    foreach ($paginatedTransfers as &$transfer) {
        $transfer['items'] = $transferService->getTransferItems($transfer['id']);
        $transfer['item_count'] = count($transfer['items']);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/transfers', 'GET', [
        'from_warehouse_id' => $fromWarehouseId,
        'to_warehouse_id' => $toWarehouseId,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'transfers' => $paginatedTransfers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Transfers retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Transfer API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve transfers');
}
