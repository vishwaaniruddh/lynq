<?php
/**
 * Inventory API - List Products
 * GET /api/inventory/products/index.php
 * 
 * Lists products with filtering capabilities
 * 
 * Query Parameters:
 * - category_id: Filter by category (optional)
 * - inventory_type: Filter by type (INTERNAL/SITE) (optional)
 * - is_serializable: Filter by serializable flag (0/1) (optional)
 * - is_repairable: Filter by repairable flag (0/1) (optional)
 * - search: Search term for name (optional)
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { products: [], pagination: {} } }
 * 
 * **Validates: Requirements 2.1, 2.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
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
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $user = $authMiddleware->requireAuth();
    
    // Get query parameters
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $inventoryType = isset($_GET['inventory_type']) ? strtoupper(trim($_GET['inventory_type'])) : null;
    $isSerializable = isset($_GET['is_serializable']) ? (int)$_GET['is_serializable'] : null;
    $isRepairable = isset($_GET['is_repairable']) ? (int)$_GET['is_repairable'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Validate inventory type if provided
    if ($inventoryType !== null && $inventoryType !== '' && !ProductRepository::isValidInventoryType($inventoryType)) {
        ApiResponse::validationError(['inventory_type' => 'Invalid inventory type. Must be INTERNAL or SITE']);
    }
    
    $productRepository = new ProductRepository();
    
    // Build filters
    $filters = [];
    
    if ($categoryId !== null) {
        $filters['category_id'] = $categoryId;
    }
    
    if ($inventoryType !== null && $inventoryType !== '') {
        $filters['inventory_type'] = $inventoryType;
    }
    
    if ($isSerializable !== null) {
        $filters['is_serializable'] = (bool)$isSerializable;
    }
    
    if ($isRepairable !== null) {
        $filters['is_repairable'] = (bool)$isRepairable;
    }
    
    if ($search !== null && $search !== '') {
        $filters['search'] = $search;
    }
    
    // Search products with filters
    $products = $productRepository->search($filters);
    
    // Get total count for pagination
    $totalCount = count($products);
    
    // Apply pagination
    $paginatedProducts = array_slice($products, $offset, $limit);
    
    // Enrich with stock information
    foreach ($paginatedProducts as &$product) {
        $product['total_stock'] = $productRepository->getTotalStock($product['id']);
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/products', 'GET', [
        'category_id' => $categoryId,
        'inventory_type' => $inventoryType,
        'is_serializable' => $isSerializable,
        'search' => $search,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'products' => $paginatedProducts,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Products retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Products API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve products');
}
