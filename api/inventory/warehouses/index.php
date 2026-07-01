<?php
/**
 * Inventory API - List Warehouses
 * GET /api/inventory/warehouses/index.php
 * 
 * Lists warehouses with access filtering based on user role
 * - ADV users see all warehouses
 * - Contractor users see only their company's warehouses
 * - Engineers see only warehouses where they have assigned items
 * 
 * Query Parameters:
 * - company_id: Filter by company (optional, ADV only)
 * - status: Filter by status (active/inactive)
 * - search: Search term for name/location (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { warehouses: [], pagination: {} } }
 * 
 * **Validates: Requirements 1.1, 1.2**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/WarehouseRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::methodNotAllowed(['GET']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $user = $authMiddleware->requireAuth();
    
    // Get query parameters
    $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $inventoryAccessService = new InventoryAccessService();
    $warehouseRepository = new WarehouseRepository();
    
    // Get accessible warehouses based on user role
    $warehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    
    // Apply company filter if specified (ADV only)
    if ($companyId !== null) {
        if ($user['company_type'] !== 'ADV') {
            ApiResponse::forbidden('Only ADV users can filter by company');
        }
        $warehouses = array_filter($warehouses, function($w) use ($companyId) {
            return (int)$w['company_id'] === $companyId;
        });
    }
    
    // Apply status filter
    if ($status !== null && $status !== '') {
        if (!WarehouseRepository::isValidStatus($status)) {
            ApiResponse::validationError(['status' => 'Invalid status. Must be active or inactive']);
        }
        $warehouses = array_filter($warehouses, function($w) use ($status) {
            return $w['status'] === $status;
        });
    }
    
    // Apply search filter
    if ($search !== null && $search !== '') {
        $searchLower = strtolower($search);
        $warehouses = array_filter($warehouses, function($w) use ($searchLower) {
            return strpos(strtolower($w['name']), $searchLower) !== false ||
                   strpos(strtolower($w['location'] ?? ''), $searchLower) !== false;
        });
    }
    
    // Re-index array after filtering
    $warehouses = array_values($warehouses);
    
    // Get total count for pagination
    $totalCount = count($warehouses);
    
    // Apply pagination
    $paginatedWarehouses = array_slice($warehouses, $offset, $limit);
    
    // Enrich with stock counts
    foreach ($paginatedWarehouses as &$warehouse) {
        $warehouse['stock_count'] = $warehouseRepository->getStockCount($warehouse['id']);
        $warehouse['asset_count'] = $warehouseRepository->getAssetCount($warehouse['id']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/warehouses', 'GET', [
        'company_id' => $companyId,
        'status' => $status,
        'search' => $search,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'warehouses' => $paginatedWarehouses,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Warehouses retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Warehouses API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve warehouses');
}
