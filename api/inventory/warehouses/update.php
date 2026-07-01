<?php
/**
 * Inventory API - Update Warehouse
 * PUT /api/inventory/warehouses/update.php?id={id}
 * 
 * Updates an existing warehouse with validation
 * 
 * Query Parameters:
 * - id: Warehouse ID (required)
 * 
 * Request Body (JSON):
 * {
 *   "name": "string (optional)",
 *   "location": "string (optional)",
 *   "status": "string (optional)"
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
    
    // Require authentication
    $currentUser = $authMiddleware->requireAuth();
    
    // Get warehouse ID from query
    $warehouseId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$warehouseId) {
        ApiResponse::validationError(['id' => 'Warehouse ID is required']);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ApiResponse::validationError(['body' => 'Invalid JSON body']);
    }
    
    $warehouseRepository = new WarehouseRepository();
    $warehouseRepository->disableCompanyFilter();
    
    // Get existing warehouse
    $existingWarehouse = $warehouseRepository->findWithCompany($warehouseId);
    
    if (!$existingWarehouse) {
        ApiResponse::notFound('Warehouse not found');
    }
    
    // Check access - ADV users can update any warehouse, contractors only their own
    if ($currentUser['company_type'] !== 'ADV') {
        if ((int)$existingWarehouse['company_id'] !== (int)$currentUser['company_id']) {
            ApiResponse::forbidden('You can only update warehouses belonging to your company');
        }
    }
    
    // Build update data
    $updateData = [];
    $oldValues = [];
    
    if (isset($input['name']) && trim($input['name']) !== '') {
        $newName = trim($input['name']);
        
        // Check name uniqueness within company (Requirement 1.4)
        if (!$warehouseRepository->isNameUniqueInCompany($newName, $existingWarehouse['company_id'], $warehouseId)) {
            ApiResponse::validationError(['name' => "Warehouse name '$newName' already exists in this company"]);
        }
        
        $oldValues['name'] = $existingWarehouse['name'];
        $updateData['name'] = $newName;
    }
    
    if (isset($input['location'])) {
        $oldValues['location'] = $existingWarehouse['location'];
        $updateData['location'] = trim($input['location']);
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
    
    // Log audit trail
    $auditService = new InventoryAuditService();
    $auditService->logAction(
        'warehouse_updated',
        'warehouse',
        $warehouseId,
        $currentUser['id'],
        null,
        null,
        null,
        null,
        $oldValues,
        $updateData
    );
    
    // Log API access
    $authMiddleware->logApiAccess($currentUser['id'], '/api/inventory/warehouses/update', 'PUT', [
        'warehouse_id' => $warehouseId,
        'fields' => array_keys($updateData)
    ]);
    
    ApiResponse::success(['warehouse' => $warehouse], 'Warehouse updated successfully');
    
} catch (Exception $e) {
    error_log("Inventory Warehouses API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to update warehouse: ' . $e->getMessage());
}
