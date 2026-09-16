<?php
/**
 * Inventory API - Update Warehouse
 * POST|PUT /api/inventory/warehouses/update.php?id={id}
 * 
 * Updates an existing warehouse with validation
 * 
 * Request Body (JSON):
 * {
 *   "id": "int (optional if provided in query)",
 *   "name": "string (optional)",
 *   "location": "string (optional)",
 *   "company_id": "int (optional)",
 *   "status": "string (optional, active/inactive)"
 * }
 * 
 * Response: { success: bool, data: { warehouse: {} } }
 * 
 * **Validates: Requirements 1.1, 1.3, 1.4**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../repositories/WarehouseRepository.php';

// Handle CORS
ApiResponse::setCorsHeaders();
ApiResponse::handlePreflight();

// Allow POST and PUT
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    ApiResponse::methodNotAllowed(['POST', 'PUT']);
}

try {
    $authMiddleware = new ApiAuthMiddleware();
    
    // Check rate limiting
    $authMiddleware->checkRateLimit();
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST ?? [];
    }
    
    // Get warehouse ID from query or body
    $warehouseId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : null);
    
    if (!$warehouseId) {
        ApiResponse::validationError(['id' => 'Warehouse ID is required']);
    }
    
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
    
    // Get existing warehouse
    $existingWarehouse = $warehouseRepository->findWithCompany($warehouseId);
    
    if (!$existingWarehouse) {
        ApiResponse::notFound('Warehouse not found');
    }
    
    // Check access - ADV users can update any warehouse, contractors only their own
    if (strtoupper($currentUser['company_type'] ?? '') !== 'ADV') {
        if ((int)$existingWarehouse['company_id'] !== (int)$currentUser['company_id']) {
            ApiResponse::forbidden('You can only update warehouses belonging to your company');
        }
    }
    
    // Build update data
    $updateData = [];
    $oldValues = [];
    
    if (isset($input['name']) && trim($input['name']) !== '') {
        $newName = trim($input['name']);
        $companyId = isset($input['company_id']) ? (int)$input['company_id'] : (int)$existingWarehouse['company_id'];
        
        // Check name uniqueness within company (Requirement 1.4)
        if (!$warehouseRepository->isNameUniqueInCompany($newName, $companyId, $warehouseId)) {
            ApiResponse::validationError(['name' => "Warehouse name '$newName' already exists in this company"]);
        }
        
        $oldValues['name'] = $existingWarehouse['name'];
        $updateData['name'] = $newName;
    }
    
    if (isset($input['location'])) {
        $oldValues['location'] = $existingWarehouse['location'];
        $updateData['location'] = trim($input['location']);
    }
    
    if (isset($input['company_id']) && is_numeric($input['company_id'])) {
        $oldValues['company_id'] = $existingWarehouse['company_id'];
        $updateData['company_id'] = (int)$input['company_id'];
    }
    
    if (isset($input['status'])) {
        if (!WarehouseRepository::isValidStatus($input['status'])) {
            ApiResponse::validationError(['status' => 'Invalid status. Must be active or inactive']);
        }
        $oldValues['status'] = $existingWarehouse['status'];
        $updateData['status'] = $input['status'];
    }
    
    if (empty($updateData)) {
        ApiResponse::validationError(['body' => 'No valid fields to update']);
    }
    
    // Add updated_by
    $updateData['updated_by'] = $currentUser['id'];
    
    // Update warehouse
    $warehouseRepository->update($warehouseId, $updateData);
    
    // Get updated warehouse
    $warehouse = $warehouseRepository->findWithCompany($warehouseId);
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/inventory/warehouses/update', $_SERVER['REQUEST_METHOD'], [
        'warehouse_id' => $warehouseId,
        'changes' => $updateData
    ]);
    
    ApiResponse::success(['warehouse' => $warehouse], 'Warehouse updated successfully');
    
} catch (Throwable $e) {
    error_log("Inventory Warehouses API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update warehouse: ' . $e->getMessage());
}
