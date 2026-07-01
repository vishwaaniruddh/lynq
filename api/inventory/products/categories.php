<?php
/**
 * Inventory API - List Product Categories
 * GET /api/inventory/products/categories.php
 * 
 * Lists all product categories
 * 
 * Query Parameters:
 * - status: Filter by status (active/inactive) (optional)
 * 
 * Response: { success: bool, data: { categories: [] } }
 * 
 * **Validates: Requirements 2.1**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../repositories/ProductCategoryRepository.php';

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
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'active';
    
    $categoryRepository = new ProductCategoryRepository();
    
    // Get categories
    if ($status === 'all') {
        $categories = $categoryRepository->findAll([], 'name');
    } else {
        $categories = $categoryRepository->findAll(['status' => $status], 'name');
    }
    
    // Build hierarchical structure
    $categoriesById = [];
    foreach ($categories as $category) {
        $categoriesById[$category['id']] = $category;
        $categoriesById[$category['id']]['children'] = [];
    }
    
    $rootCategories = [];
    foreach ($categoriesById as &$category) {
        if ($category['parent_id'] && isset($categoriesById[$category['parent_id']])) {
            $categoriesById[$category['parent_id']]['children'][] = &$category;
        } else {
            $rootCategories[] = &$category;
        }
    }
    
    // Log API access
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/products/categories', 'GET', [
        'status' => $status
    ]);
    
    ApiResponse::success([
        'categories' => $rootCategories,
        'flat_categories' => array_values($categories)
    ], 'Categories retrieved successfully');
    
} catch (Exception $e) {
    error_log("Inventory Products API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve categories');
}
