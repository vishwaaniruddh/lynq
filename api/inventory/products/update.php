<?php
/**
 * Inventory API - Update Product
 * PUT /api/inventory/products/update.php?id={id}
 * 
 * Updates an existing product with validation
 * 
 * Query Parameters:
 * - id: Product ID (required)
 * 
 * Request Body (JSON):
 * {
 *   "name": "string (optional)",
 *   "category_id": "int (optional)",
 *   "unit_of_measure": "string (optional)",
 *   "inventory_type": "string (optional, INTERNAL/SITE)",
 *   "is_serializable": "bool (optional)",
 *   "is_repairable": "bool (optional)",
 *   "low_stock_threshold": "int (optional)",
 *   "description": "string (optional)",
 *   "status": "string (optional, active/inactive)"
 * }
 * 
 * Response: { success: bool, data: { product: {} } }
 * 
 * **Validates: Requirements 2.1**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../repositories/ProductRepository.php';
require_once __DIR__ . '/../../../repositories/ProductCategoryRepository.php';
require_once __DIR__ . '/../../../services/InventoryAuditService.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Only allow PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    ApiResponse::methodNotAllowed(['PUT']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication - only ADV users can update products
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get product ID from query
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$productId) {
        ApiResponse::validationError(['id' => 'Product ID is required']);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    $productRepository = new ProductRepository();
    
    // Get existing product
    $existingProduct = $productRepository->findWithCategory($productId);
    
    if (!$existingProduct) {
        ApiResponse::notFound('Product not found');
    }
    
    // Build update data
    $updateData = [];
    $oldValues = [];
    
    if (isset($input['name']) && trim($input['name']) !== '') {
        $oldValues['name'] = $existingProduct['name'];
        $updateData['name'] = trim($input['name']);
    }
    
    if (array_key_exists('category_id', $input)) {
        // Validate category if provided
        if ($input['category_id'] !== null) {
            $categoryRepository = new ProductCategoryRepository();
            $category = $categoryRepository->find((int)$input['category_id']);
            if (!$category) {
                ApiResponse::validationError(['category_id' => 'Category not found']);
            }
        }
        $oldValues['category_id'] = $existingProduct['category_id'];
        $updateData['category_id'] = $input['category_id'] !== null ? (int)$input['category_id'] : null;
    }
    
    if (isset($input['unit_of_measure']) && trim($input['unit_of_measure']) !== '') {
        $oldValues['unit_of_measure'] = $existingProduct['unit_of_measure'];
        $updateData['unit_of_measure'] = trim($input['unit_of_measure']);
    }
    
    if (isset($input['inventory_type'])) {
        $inventoryType = strtoupper(trim($input['inventory_type']));
        if (!ProductRepository::isValidInventoryType($inventoryType)) {
            ApiResponse::validationError(['inventory_type' => 'Invalid inventory type. Must be INTERNAL or SITE']);
        }
        $oldValues['inventory_type'] = $existingProduct['inventory_type'];
        $updateData['inventory_type'] = $inventoryType;
    }
    
    if (isset($input['is_serializable'])) {
        $oldValues['is_serializable'] = $existingProduct['is_serializable'];
        $updateData['is_serializable'] = (bool)$input['is_serializable'] ? 1 : 0;
    }
    
    if (isset($input['is_repairable'])) {
        $oldValues['is_repairable'] = $existingProduct['is_repairable'];
        $updateData['is_repairable'] = (bool)$input['is_repairable'] ? 1 : 0;
    }
    
    if (isset($input['low_stock_threshold'])) {
        $oldValues['low_stock_threshold'] = $existingProduct['low_stock_threshold'];
        $updateData['low_stock_threshold'] = (int)$input['low_stock_threshold'];
    }
    
    if (isset($input['description'])) {
        $oldValues['description'] = $existingProduct['description'] ?? null;
        $updateData['description'] = trim($input['description']);
    }
    
    if (isset($input['status'])) {
        if (!in_array($input['status'], ProductRepository::getStatuses())) {
            ApiResponse::validationError(['status' => 'Invalid status. Must be active or inactive']);
        }
        $oldValues['status'] = $existingProduct['status'];
        $updateData['status'] = $input['status'];
    }
    
    if (empty($updateData)) {
        ApiResponse::validationError(['body' => 'No valid fields to update']);
    }
    
    // Add updated_by
    $updateData['updated_by'] = $currentUser['id'];
    
    // Update product
    $productRepository->update($productId, $updateData);
    
    // Get updated product
    $product = $productRepository->findWithCategory($productId);
    
    // Log audit trail
    $auditService = new InventoryAuditService();
    $auditService->logAction(
        'product_updated',
        'product',
        $productId,
        $currentUser['id'],
        null,
        null,
        null,
        null,
        $oldValues,
        $updateData
    );
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/inventory/products/update', 'PUT', [
        'product_id' => $productId,
        'fields' => array_keys($updateData)
    ]);
    
    ApiResponse::success(['product' => $product], 'Product updated successfully');
    
} catch (Exception $e) {
    error_log("Inventory Products API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update product: ' . $e->getMessage());
}
