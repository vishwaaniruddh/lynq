<?php
/**
 * Inventory API - List Dispatches
 * GET /api/inventory/dispatch/index.php
 * 
 * Returns dispatch history with filtering options
 * 
 * Query Parameters:
 * - from_warehouse_id: Filter by source warehouse (optional)
 * - to_company_id: Filter by destination company (optional)
 * - to_user_id: Filter by destination user (optional)
 * - status: Filter by status (pending/in_transit/delivered/cancelled)
 * - acknowledgment_status: Filter by acknowledgment status (pending/acknowledged)
 * - date_from: Filter by dispatch date from (optional)
 * - date_to: Filter by dispatch date to (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { dispatches: [], pagination: {} } }
 * 
 * **Validates: Requirements 5.1, 5.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../services/DispatchService.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';

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
    $toCompanyId = isset($_GET['to_company_id']) ? (int)$_GET['to_company_id'] : null;
    $toUserId = isset($_GET['to_user_id']) ? (int)$_GET['to_user_id'] : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $acknowledgmentStatus = isset($_GET['acknowledgment_status']) ? trim($_GET['acknowledgment_status']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Validate status if provided
    if ($status !== null && $status !== '' && !DispatchRepository::isValidStatus($status)) {
        ApiResponse::validationError(['status' => 'Invalid status. Must be pending, in_transit, delivered, or cancelled']);
    }
    
    // Validate acknowledgment status if provided
    if ($acknowledgmentStatus !== null && $acknowledgmentStatus !== '' && 
        !in_array($acknowledgmentStatus, [DispatchRepository::ACK_PENDING, DispatchRepository::ACK_ACKNOWLEDGED])) {
        ApiResponse::validationError(['acknowledgment_status' => 'Invalid acknowledgment status. Must be pending or acknowledged']);
    }
    
    $inventoryAccessService = new InventoryAccessService();
    $dispatchService = new DispatchService();
    
    // Build filters based on user role
    $filters = [];
    
    // Get accessible warehouses for the user
    $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
    
    // ADV users can see all dispatches
    // Contractors can see dispatches to their company
    // Engineers can see dispatches to themselves
    if ($user['company_type'] === 'ADV') {
        // ADV can filter by any warehouse
        if ($fromWarehouseId !== null) {
            $filters['from_warehouse_id'] = $fromWarehouseId;
        }
    } else if ($user['role_name'] === 'engineer' || $user['role_name'] === 'Engineer') {
        // Engineers can only see dispatches to themselves
        $filters['to_user_id'] = $user['id'];
    } else {
        // Contractors can see dispatches to their company or from their warehouses
        $filters['company_scope'] = [
            'company_id' => $user['company_id'],
            'warehouse_ids' => $accessibleWarehouseIds
        ];
    }
    
    // Apply additional filters
    if ($toCompanyId !== null) {
        $filters['to_company_id'] = $toCompanyId;
    }
    if ($toUserId !== null) {
        $filters['to_user_id'] = $toUserId;
    }
    if ($status !== null && $status !== '') {
        $filters['status'] = $status;
    }
    if ($acknowledgmentStatus !== null && $acknowledgmentStatus !== '') {
        $filters['acknowledgment_status'] = $acknowledgmentStatus;
    }
    if ($dateFrom !== null && $dateFrom !== '') {
        $filters['date_from'] = $dateFrom;
    }
    if ($dateTo !== null && $dateTo !== '') {
        $filters['date_to'] = $dateTo;
    }
    
    // Get dispatch history
    $dispatches = $dispatchService->getDispatchHistory($filters);
    
    // Get total count for pagination
    $totalCount = count($dispatches);
    
    // Apply pagination
    $paginatedDispatches = array_slice($dispatches, $offset, $limit);
    
    // Enrich with items for each dispatch
    foreach ($paginatedDispatches as &$dispatch) {
        $dispatch['items'] = $dispatchService->getDispatchItems($dispatch['id']);
        $dispatch['item_count'] = count($dispatch['items']);
    }
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/dispatch', 'GET', [
        'from_warehouse_id' => $fromWarehouseId,
        'to_company_id' => $toCompanyId,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'dispatches' => $paginatedDispatches,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Dispatches retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Dispatch API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve dispatches');
}
