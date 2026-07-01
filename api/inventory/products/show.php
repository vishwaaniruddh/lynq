<?php
/**
 * Inventory API - Get Single Product
 * GET /api/inventory/products/show.php?id={id}
 * 
 * Gets a single product with details
 * 
 * Query Parameters:
 * - id: Product ID (required)
 * 
 * Response: { success: bool, data: { product: {} } }
 * 
 * **Validates: Requirements 2.1**
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
    
    // Get product ID from query
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$productId) {
        ApiResponse::validationError(['id' => 'Product ID is required']);
    }
    
    $productRepository = new ProductRepository();
    
    // Get product with category details
    $product = $productRepository->findWithCategory($productId);
    
    if (!$product) {
        ApiResponse::notFound('Product not found');
    }
    
    // Enrich with stock information
    $product['total_stock'] = $productRepository->getTotalStock($productId);
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/products/show', 'GET', [
        'product_id' => $productId
    ]);
    
    ApiResponse::success(['product' => $product], 'Product retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Products API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve product');
}
