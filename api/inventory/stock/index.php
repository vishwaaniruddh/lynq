<?php
/**
 * Inventory API - Get Stock Levels
 * GET /api/inventory/stock/index.php
 * 
 * Returns stock levels with filtering options
 * 
 * Query Parameters:
 * - product_id: Filter by product (optional)
 * - warehouse_id: Filter by warehouse (optional)
 * - category_id: Filter by product category (optional)
 * - low_stock: Show only low stock items (optional, "true"/"false")
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { stock: [], pagination: {} } }
 * 
 * **Validates: Requirements 3.1, 3.2**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../services/StockService.php';
require_once __DIR__ . '/../../../repositories/StockRepository.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';
require_once __DIR__ . '/../../../repositories/ProductRepository.php';

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
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $lowStockOnly = isset($_GET['low_stock']) && $_GET['low_stock'] === 'true';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $inventoryAccessService = new InventoryAccessService();
    $stockRepository = new StockRepository();
    $assetRepository = new AssetRepository();
    $productRepository = new ProductRepository();
    
    // Get accessible warehouses for the user
    $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
    
    // If warehouse filter specified, validate access
    if ($warehouseId !== null) {
        if (!in_array($warehouseId, $accessibleWarehouseIds)) {
            ApiResponse::forbidden('You do not have access to this warehouse');
        }
        $accessibleWarehouseIds = [$warehouseId];
    }
    
    $stockData = [];
    
    // Get non-serializable stock
    $filters = [];
    if ($productId !== null) {
        $filters['product_id'] = $productId;
    }
    
    $nonSerializableStock = $stockRepository->findAllWithDetails($filters);
    
    // Filter by accessible warehouses
    $nonSerializableStock = array_filter($nonSerializableStock, function($s) use ($accessibleWarehouseIds) {
        return in_array($s['warehouse_id'], $accessibleWarehouseIds);
    });
    
    // Filter by category if specified
    if ($categoryId !== null) {
        $nonSerializableStock = array_filter($nonSerializableStock, function($s) use ($categoryId) {
            return isset($s['category_id']) && (int)$s['category_id'] === $categoryId;
        });
    }
    
    // Filter low stock if requested
    if ($lowStockOnly) {
        $nonSerializableStock = array_filter($nonSerializableStock, function($s) {
            $threshold = $s['low_stock_threshold'] ?? 0;
            return $s['quantity'] <= $threshold;
        });
    }
    
    foreach ($nonSerializableStock as $stock) {
        // Get product details for category and unit
        $product = $productRepository->findWithCategory($stock['product_id']);
        $stockData[] = [
            'type' => 'quantity',
            'product_id' => $stock['product_id'],
            'product_name' => $stock['product_name'] ?? null,
            'category_name' => $product['category_name'] ?? null,
            'unit_of_measure' => $stock['unit_of_measure'] ?? ($product['unit_of_measure'] ?? null),
            'warehouse_id' => $stock['warehouse_id'],
            'warehouse_name' => $stock['warehouse_name'] ?? null,
            'quantity' => (int)$stock['quantity'],
            'reserved_quantity' => (int)($stock['reserved_quantity'] ?? 0),
            'available_quantity' => (int)$stock['quantity'] - (int)($stock['reserved_quantity'] ?? 0),
            'low_stock_threshold' => (int)($stock['low_stock_threshold'] ?? 0),
            'is_low_stock' => (int)$stock['quantity'] <= (int)($stock['low_stock_threshold'] ?? 0)
        ];
    }
    
    // Get serializable assets count by product/warehouse
    $assetCounts = $assetRepository->getCountsByProductAndWarehouse($accessibleWarehouseIds, $productId);
    
    foreach ($assetCounts as $count) {
        $product = $productRepository->findWithCategory($count['product_id']);
        
        if ($categoryId !== null) {
            if (!$product || (int)$product['category_id'] !== $categoryId) {
                continue;
            }
        }
        
        $stockData[] = [
            'type' => 'serializable',
            'product_id' => $count['product_id'],
            'product_name' => $count['product_name'] ?? null,
            'category_name' => $product['category_name'] ?? null,
            'unit_of_measure' => $product['unit_of_measure'] ?? null,
            'warehouse_id' => $count['warehouse_id'],
            'warehouse_name' => $count['warehouse_name'] ?? null,
            'quantity' => (int)$count['in_stock_count'],
            'total_assets' => (int)$count['total_count'],
            'dispatched_count' => (int)($count['dispatched_count'] ?? 0),
            'under_repair_count' => (int)($count['under_repair_count'] ?? 0)
        ];
    }
    
    // Sort by product name
    usort($stockData, function($a, $b) {
        return strcmp($a['product_name'] ?? '', $b['product_name'] ?? '');
    });
    
    // Get total count for pagination
    $totalCount = count($stockData);
    
    // Apply pagination
    $paginatedStock = array_slice($stockData, $offset, $limit);
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/stock', 'GET', [
        'product_id' => $productId,
        'warehouse_id' => $warehouseId,
        'category_id' => $categoryId,
        'low_stock' => $lowStockOnly,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'stock' => $paginatedStock,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Stock levels retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Stock API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve stock levels');
}
