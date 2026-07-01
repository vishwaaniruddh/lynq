<?php
/**
 * Inventory API - List Assets
 * GET /api/inventory/assets/index.php
 * 
 * Returns list of serializable assets with filtering options
 * 
 * Query Parameters:
 * - product_id: Filter by product (optional)
 * - warehouse_id: Filter by warehouse (optional) - also includes assets dispatched FROM this warehouse
 * - status: Filter by status (optional)
 * - working_condition: Filter by working condition (optional)
 * - serial_number: Search by serial number (optional, partial match)
 * - include_dispatched: Include dispatched assets from warehouse (optional, "true"/"false")
 * - page: Page number (default: 1)
 * - limit: Results per page (default: 20, max: 100)
 * 
 * Response: { success: bool, data: { assets: [], pagination: {} } }
 * 
 * **Validates: Requirements 6.2**
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../middleware/ApiAuthMiddleware.php';
require_once __DIR__ . '/../../../services/InventoryAccessService.php';
require_once __DIR__ . '/../../../repositories/AssetRepository.php';

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
    $status = $_GET['status'] ?? null;
    $workingCondition = $_GET['working_condition'] ?? null;
    $serialNumber = $_GET['serial_number'] ?? null;
    $includeDispatched = isset($_GET['include_dispatched']) && $_GET['include_dispatched'] === 'true';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $inventoryAccessService = new InventoryAccessService();
    $assetRepository = new AssetRepository();
    
    // Check if user is ADV - they can access all warehouses
    $isAdvUser = isAdvUser($user['id']);
    
    // Get accessible warehouses for the user
    $accessibleWarehouses = $inventoryAccessService->getAccessibleWarehouses($user['id']);
    $accessibleWarehouseIds = array_column($accessibleWarehouses, 'id');
    
    // If warehouse filter specified, validate access (ADV users can access any)
    if ($warehouseId !== null) {
        if (!$isAdvUser && !in_array($warehouseId, $accessibleWarehouseIds)) {
            ApiResponse::forbidden('You do not have access to this warehouse');
        }
        $accessibleWarehouseIds = [$warehouseId];
    }
    
    // Build filters
    $filters = [];
    if ($productId !== null) {
        $filters['product_id'] = $productId;
    }
    if ($status !== null) {
        if (!AssetRepository::isValidStatus($status)) {
            ApiResponse::validationError(['status' => 'Invalid status value']);
        }
        $filters['status'] = $status;
    }
    if ($workingCondition !== null) {
        if (!AssetRepository::isValidWorkingCondition($workingCondition)) {
            ApiResponse::validationError(['working_condition' => 'Invalid working condition value']);
        }
        $filters['working_condition'] = $workingCondition;
    }
    if ($serialNumber !== null) {
        $filters['serial_number'] = $serialNumber;
    }
    
    // Get assets with filters
    $allAssets = $assetRepository->search($filters);
    
    // ADV users can see all assets, others need filtering
    if ($isAdvUser) {
        $filteredAssets = $allAssets;
        // Apply warehouse filter if specified - include assets currently in warehouse OR dispatched from it
        if ($warehouseId !== null) {
            $filteredAssets = array_filter($allAssets, function($asset) use ($warehouseId) {
                // Asset is currently in this warehouse
                if ($asset['warehouse_id'] == $warehouseId) {
                    return true;
                }
                // Asset was dispatched from this warehouse (source_warehouse_id)
                if (isset($asset['source_warehouse_id']) && $asset['source_warehouse_id'] == $warehouseId) {
                    return true;
                }
                return false;
            });
        }
    } else {
        // Filter by accessible warehouses for non-ADV users
        $filteredAssets = array_filter($allAssets, function($asset) use ($accessibleWarehouseIds, $user) {
            // Check warehouse access
            if (in_array($asset['warehouse_id'], $accessibleWarehouseIds)) {
                return true;
            }
            
            // Check source warehouse access (for dispatched assets)
            if (isset($asset['source_warehouse_id']) && in_array($asset['source_warehouse_id'], $accessibleWarehouseIds)) {
                return true;
            }
            
            // Engineers can also see assets assigned to them
            if (($user['role_name'] === 'engineer' || $user['role_name'] === 'Engineer') &&
                $asset['current_holder_type'] === 'user' && 
                $asset['current_holder_id'] == $user['id']) {
                return true;
            }
            
            // Contractors can see assets assigned to their company
            if ($asset['current_holder_type'] === 'company' && 
                $asset['current_holder_id'] == $user['company_id']) {
                return true;
            }
            
            return false;
        });
    }
    
    // Re-index array
    $filteredAssets = array_values($filteredAssets);
    $totalCount = count($filteredAssets);
    
    // Apply pagination
    $paginatedAssets = array_slice($filteredAssets, $offset, $limit);
    
    // Get dispatch details for dispatched assets
    $db = DatabaseConfig::getInstance();
    $dispatchedAssetIds = [];
    foreach ($paginatedAssets as $asset) {
        if ($asset['status'] === 'dispatched') {
            $dispatchedAssetIds[] = $asset['id'];
        }
    }
    
    $dispatchDetails = [];
    if (!empty($dispatchedAssetIds)) {
        // Get dispatch info for these assets
        $placeholders = implode(',', array_fill(0, count($dispatchedAssetIds), '?'));
        $sql = "SELECT di.asset_id, d.id as dispatch_id, d.dispatch_number, d.dispatch_date, d.status as dispatch_status,
                       d.to_company_id, d.to_user_id, d.to_warehouse_id,
                       c.name as to_company_name, 
                       CONCAT(u.first_name, ' ', u.last_name) as to_user_name,
                       w.name as to_warehouse_name,
                       cr.name as courier_name, d.pod_number,
                       fw.name as from_warehouse_name
                FROM dispatch_items di
                JOIN dispatches d ON di.dispatch_id = d.id
                LEFT JOIN companies c ON d.to_company_id = c.id
                LEFT JOIN users u ON d.to_user_id = u.id
                LEFT JOIN warehouses w ON d.to_warehouse_id = w.id
                LEFT JOIN warehouses fw ON d.from_warehouse_id = fw.id
                LEFT JOIN couriers cr ON d.courier_id = cr.id
                WHERE di.asset_id IN ($placeholders)
                ORDER BY d.created_at DESC";
        
        $types = str_repeat('i', count($dispatchedAssetIds));
        $results = $db->getResults($sql, $dispatchedAssetIds, $types);
        
        foreach ($results as $row) {
            if (!isset($dispatchDetails[$row['asset_id']])) {
                $dispatchDetails[$row['asset_id']] = $row;
            }
        }
    }
    
    // Get source warehouse names for dispatched assets
    $sourceWarehouseNames = [];
    $sourceWarehouseIds = [];
    foreach ($paginatedAssets as $asset) {
        if (!empty($asset['source_warehouse_id']) && empty($asset['warehouse_id'])) {
            $sourceWarehouseIds[$asset['source_warehouse_id']] = true;
        }
    }
    if (!empty($sourceWarehouseIds)) {
        $swIds = array_keys($sourceWarehouseIds);
        $swPlaceholders = implode(',', array_fill(0, count($swIds), '?'));
        $swSql = "SELECT id, name FROM warehouses WHERE id IN ($swPlaceholders)";
        $swTypes = str_repeat('i', count($swIds));
        $swResults = $db->getResults($swSql, $swIds, $swTypes);
        foreach ($swResults as $row) {
            $sourceWarehouseNames[$row['id']] = $row['name'];
        }
    }
    
    // Format response
    $formattedAssets = array_map(function($asset) use ($dispatchDetails, $sourceWarehouseNames) {
        // For dispatched assets, show source warehouse as the warehouse
        $warehouseName = $asset['warehouse_name'];
        if (empty($warehouseName) && !empty($asset['source_warehouse_name'])) {
            $warehouseName = $asset['source_warehouse_name'];
        }
        if (empty($warehouseName) && !empty($asset['source_warehouse_id']) && isset($sourceWarehouseNames[$asset['source_warehouse_id']])) {
            $warehouseName = $sourceWarehouseNames[$asset['source_warehouse_id']];
        }
        
        $formatted = [
            'id' => $asset['id'],
            'serial_number' => $asset['serial_number'],
            'product_id' => $asset['product_id'],
            'product_name' => $asset['product_name'] ?? null,
            'warehouse_id' => $asset['warehouse_id'] ?? $asset['source_warehouse_id'] ?? null,
            'warehouse_name' => $warehouseName,
            'source_warehouse_id' => $asset['source_warehouse_id'] ?? null,
            'status' => $asset['status'],
            'working_condition' => $asset['working_condition'],
            'current_holder_type' => $asset['current_holder_type'],
            'current_holder_id' => $asset['current_holder_id'],
            'created_at' => $asset['created_at'] ?? null,
            'updated_at' => $asset['updated_at'] ?? null,
            'dispatched_to_name' => null,
            'dispatched_to_type' => null,
            'courier_info' => null
        ];
        
        // Add dispatch details if asset is dispatched
        if ($asset['status'] === 'dispatched' && isset($dispatchDetails[$asset['id']])) {
            $dispatch = $dispatchDetails[$asset['id']];
            
            // Determine dispatched_to_name and type
            if (!empty($dispatch['to_company_name'])) {
                $formatted['dispatched_to_name'] = $dispatch['to_company_name'];
                $formatted['dispatched_to_type'] = 'company';
            } elseif (!empty($dispatch['to_user_name'])) {
                $formatted['dispatched_to_name'] = $dispatch['to_user_name'];
                $formatted['dispatched_to_type'] = 'user';
            } elseif (!empty($dispatch['to_warehouse_name'])) {
                $formatted['dispatched_to_name'] = $dispatch['to_warehouse_name'];
                $formatted['dispatched_to_type'] = 'warehouse';
            }
            
            // Build courier_info object
            if (!empty($dispatch['courier_name']) || !empty($dispatch['pod_number'])) {
                $formatted['courier_info'] = [
                    'courier_name' => $dispatch['courier_name'] ?? null,
                    'pod_number' => $dispatch['pod_number'] ?? null
                ];
            }
            
            $formatted['dispatch_info'] = [
                'dispatch_id' => $dispatch['dispatch_id'],
                'dispatch_number' => $dispatch['dispatch_number'],
                'dispatch_date' => $dispatch['dispatch_date'],
                'dispatch_status' => $dispatch['dispatch_status'],
                'to_company_name' => $dispatch['to_company_name'],
                'to_user_name' => $dispatch['to_user_name'],
                'to_warehouse_name' => $dispatch['to_warehouse_name'],
                'from_warehouse_name' => $dispatch['from_warehouse_name'] ?? null,
                'courier_name' => $dispatch['courier_name'],
                'pod_number' => $dispatch['pod_number']
            ];
            // Use from_warehouse_name if warehouse_name is still empty
            if (empty($formatted['warehouse_name']) && !empty($dispatch['from_warehouse_name'])) {
                $formatted['warehouse_name'] = $dispatch['from_warehouse_name'];
            }
        }
        
        return $formatted;
    }, $paginatedAssets);
    
    $authMiddleware->logApiAccess($user['id'], '/api/inventory/assets', 'GET', [
        'product_id' => $productId,
        'warehouse_id' => $warehouseId,
        'status' => $status,
        'page' => $page
    ]);
    
    ApiResponse::success([
        'assets' => $formattedAssets,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ]
    ], 'Assets retrieved successfully');
    
} catch (Exception $e) {
    error_log("Assets List API Error: " . $e->getMessage());
    ApiResponse::serverError('Failed to retrieve assets');
}
