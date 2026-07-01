<?php
/**
 * Inventory API - Delete Product
 * DELETE /api/inventory/products/delete.php?id={id}
 * 
 * Deletes a product only if it has no stock
 * 
 * Query Parameters:
 * - id: Product ID (required)
 * 
 * Response: { success: bool, message: string }
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../repositories/ProductRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    ApiResponse::methodNotAllowed(['DELETE']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication and inventory.products.delete permission
    $currentUser = $authMiddleware->requirePermission('inventory.products.delete');
    
    // Get product ID from query
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$productId) {
        ApiResponse::validationError(['id' => 'Product ID is required']);
    }
    
    $productRepository = new ProductRepository();
    
    // Check if product exists
    $product = $productRepository->find($productId);
    if (!$product) {
        ApiResponse::notFound('Product not found');
    }
    
    // Check if product has stock
    $totalStock = $productRepository->getTotalStock($productId);
    if ($totalStock > 0) {
        ApiResponse::forbidden('Cannot delete product with existing stock. Current stock: ' . $totalStock);
    }
    
    // Delete the product (soft delete by setting status to inactive)
    $result = $productRepository->update($productId, ['status' => 'inactive']);
    
    if (!$result) {
        ApiResponse::serverError('Failed to delete product');
    }
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/inventory/products/delete', 'DELETE', ['product_id' => $productId]);
    
    ApiResponse::success(null, 'Product deleted successfully');
    
} catch (Exception $e) {
    error_log("Inventory Products Delete API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to delete product');
}
