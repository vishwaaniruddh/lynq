<?php
/**
 * Inventory API - List Repairs
 * GET /api/inventory/repairs/index.php
 * 
 * Returns repair history with filtering options
 * 
 * Query Parameters:
 * - asset_id: Filter by asset ID (optional)
 * - status: Filter by status (pending/in_progress/completed/cancelled)
 * - repair_vendor: Filter by vendor name (partial match)
 * - date_from: Filter by send date from (optional)
 * - date_to: Filter by send date to (optional)
 * - overdue: Filter overdue repairs only (optional, "true"/"false")
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { repairs: [], pagination: {} } }
 * 
 * **Validates: Requirements 7.2, 7.3**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/RepairService.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/RepairRepository.php';

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
    $assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $repairVendor = isset($_GET['repair_vendor']) ? trim($_GET['repair_vendor']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $overdue = isset($_GET['overdue']) ? ($_GET['overdue'] === 'true') : false;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Validate status if provided
    if ($status !== null && $status !== '' && !RepairRepository::isValidStatus($status)) {
        ApiResponse::validationError(['status' => 'Invalid status. Must be pending, in_progress, completed, or cancelled']);
    }
    
    $repairService = new RepairService();
    $inventoryAccessService = new InventoryAccessService();
    
    // Build filters
    $filters = [];
    
    if ($assetId !== null) {
        $filters['asset_id'] = $assetId;
    }
    if ($status !== null && $status !== '') {
        $filters['status'] = $status;
    }
    if ($repairVendor !== null && $repairVendor !== '') {
        $filters['repair_vendor'] = $repairVendor;
    }
    if ($dateFrom !== null && $dateFrom !== '') {
        $filters['date_from'] = $dateFrom;
    }
    if ($dateTo !== null && $dateTo !== '') {
        $filters['date_to'] = $dateTo;
    }
    
    // Get repairs based on filter
    if ($overdue) {
        $repairs = $repairService->getOverdueRepairs();
    } else {
        $repairs = $repairService->getRepairHistory($filters);
    }
    
    // Filter by user access for non-ADV users
    if ($user['company_type'] !== 'ADV') {
        $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
        $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
        
        $repairs = array_filter($repairs, function($repair) use ($accessibleWarehouseIds, $user) {
            // Check if repair's asset is in accessible warehouse
            if (isset($repair['warehouse_id']) && in_array($repair['warehouse_id'], $accessibleWarehouseIds)) {
                return true;
            }
            // Check if repair was created by user's company
            return false;
        });
        
        $repairs = array_values($repairs); // Re-index array
    }
    
    // Get total count for pagination
    $totalCount = count($repairs);
    
    // Apply pagination
    $paginatedRepairs = array_slice($repairs, $offset, $limit);
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/repairs', 'GET', [
        'asset_id' => $assetId,
        'status' => $status,
        'overdue' => $overdue,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'repairs' => $paginatedRepairs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Repairs retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Repairs API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve repairs');
}
