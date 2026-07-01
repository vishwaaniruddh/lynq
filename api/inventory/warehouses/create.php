<?php
/**
 * Inventory API - Create Warehouse
 * POST /api/inventory/warehouses/create.php
 * 
 * Creates a new warehouse with validation
 * 
 * Request Body (JSON):
 * {
 *   "name": "string (required)",
 *   "location": "string (optional)",
 *   "company_id": "int (required)",
 *   "status": "string (optional, default: active)"
 * }
 * 
 * Response: { success: bool, data: { warehouse: {} } }
 * 
 * **Validates: Requirements 1.1, 1.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../repositories/WarehouseRepository.php';
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
    
    // Require authentication - only ADV users can create warehouses
    $currentUser = $authMiddleware->requireAdvUser();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    // Validate required fields
    $errors = [];
    
    if (!isset($input['name']) || trim($input['name']) === '') {
        $errors['name'] = 'Warehouse name is required';
    }
    
    if (!isset($input['company_id']) || !is_numeric($input['company_id'])) {
        $errors['company_id'] = 'Company ID is required';
    }
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    // Validate status if provided
    $status = $input['status'] ?? WarehouseRepository::STATUS_ACTIVE;
    if (!WarehouseRepository::isValidStatus($status)) {
        ApiResponse::validationError(['status' => 'Invalid status. Must be active or inactive']);
    }
    
    // Sanitize input
    $warehouseData = [
        'name' => trim($input['name']),
        'location' => isset($input['location']) ? trim($input['location']) : null,
        'company_id' => (int)$input['company_id'],
        'status' => $status,
        'created_by' => $currentUser['id']
    ];
    
    // Validate company exists
    $companyRepository = new CompanyRepository();
    $companyRepository->disableCompanyFilter();
    $company = $companyRepository->find($warehouseData['company_id']);
    
    if (!$company) {
        ApiResponse::validationError(['company_id' => 'Company not found']);
    }
    
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
    
    // Check name uniqueness within company (Requirement 1.4)
    if (!$warehouseRepository->isNameUniqueInCompany($warehouseData['name'], $warehouseData['company_id'])) {
        ApiResponse::validationError(['name' => "Warehouse name '{$warehouseData['name']}' already exists in this company"]);
    }
    
    // Create warehouse
    $warehouseId = $warehouseRepository->create($warehouseData);
    
    // Get created warehouse with company details
    $warehouse = $warehouseRepository->findWithCompany($warehouseId);
    
    // Log audit trail
    $auditService = new InventoryAuditService();
    $auditService->logAction(
        'warehouse_created',
        'warehouse',
        $warehouseId,
        $currentUser['id'],
        null,
        null,
        'warehouse',
        $warehouseId,
        null,
        $warehouseData
    );
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/inventory/warehouses/create', 'POST', [
        'warehouse_id' => $warehouseId,
        'name' => $warehouseData['name'],
        'company_id' => $warehouseData['company_id']
    ]);
    
    ApiResponse::success(['warehouse' => $warehouse], 'Warehouse created successfully', 201);
    
} catch (Exception $e) {
    error_log("Inventory Warehouses API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to create warehouse: ' . $e->getMessage());
}
