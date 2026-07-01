<?php
/**
 * Inventory API - Create Product
 * POST /api/inventory/products/create.php
 * 
 * Creates a new product with validation
 * 
 * Request Body (JSON):
 * {
 *   "name": "string (required)",
 *   "category_id": "int (optional)",
 *   "unit_of_measure": "string (required)",
 *   "inventory_type": "string (required, INTERNAL/SITE)",
 *   "is_serializable": "bool (optional, default: false)",
 *   "is_repairable": "bool (optional, default: false)",
 *   "low_stock_threshold": "int (optional, default: 0)",
 *   "description": "string (optional)"
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

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed(['POST']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication - only ADV users can create products
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['name']) || trim($input['name']) === '') {
        $errors['name'] = 'Product name is required';
    }
    
    if (!isset($input['unit_of_measure']) || trim($input['unit_of_measure']) === '') {
        $errors['unit_of_measure'] = 'Unit of measure is required';
    }
    
    if (!isset($input['inventory_type']) || trim($input['inventory_type']) === '') {
        $errors['inventory_type'] = 'Inventory type is required';
    } elseif (!ProductRepository::isValidInventoryType(strtoupper($input['inventory_type']))) {
        $errors['inventory_type'] = 'Invalid inventory type. Must be INTERNAL or SITE';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    // Validate category if provided
    if (isset($input['category_id']) && $input['category_id'] !== null) {
        $categoryRepository = new ProductCategoryRepository();
        $category = $categoryRepository->find((int)$input['category_id']);
        if (!$category) {
            ApiResponse::validationError(['category_id' => 'Category not found']);
        }
    }
    
    // Sanitize input
    $productData = [
        'name' => trim($input['name']),
        'category_id' => isset($input['category_id']) ? (int)$input['category_id'] : null,
        'unit_of_measure' => trim($input['unit_of_measure']),
        'inventory_type' => strtoupper(trim($input['inventory_type'])),
        'is_serializable' => isset($input['is_serializable']) ? (bool)$input['is_serializable'] : false,
        'is_repairable' => isset($input['is_repairable']) ? (bool)$input['is_repairable'] : false,
        'low_stock_threshold' => isset($input['low_stock_threshold']) ? (int)$input['low_stock_threshold'] : 0,
        'description' => isset($input['description']) ? trim($input['description']) : null,
        'status' => ProductRepository::STATUS_ACTIVE,
        'created_by' => $currentUser['id']
    ];
    
    $productRepository = new ProductRepository();
    
    // Create product
    $createdProduct = $productRepository->create($productData);
    $productId = is_array($createdProduct) ? $createdProduct['id'] : $createdProduct;
    
    // Get created product with category details
    $product = $productRepository->findWithCategory($productId);
    
    // Log audit trail
    $auditService = new InventoryAuditService();
    $auditService->logAction(
        'product_created',
        'product',
        (int)$productId,
        $currentUser['id'],
        $productData
    );
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/inventory/products/create', 'POST', [
        'product_id' => $productId,
        'name' => $productData['name'],
        'inventory_type' => $productData['inventory_type']
    ]);
    
    ApiResponse::success(['product' => $product], 'Product created successfully', 201);
    
} catch (Exception $e) {
    error_log("Inventory Products API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create product: ' . $e->getMessage());
}
