<?php
/**
 * Contractor Dispatch API
 * POST /api/inventory/contractor/dispatch.php
 * 
 * Allows contractor to dispatch assets to engineers or return to ADV
 */

require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../../repositories/DispatchRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $sessionService = new SessionService();
    $db = DatabaseConfig::getInstance();
    
    $userId = $sessionService->getCurrentUserId();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Verify contractor access
    if (!isContractorUser()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Contractor access required']);
        exit;
    }
    
    // Get user's company
    $userSql = "SELECT company_id FROM users WHERE id = ?";
    $userResult = $db->getResults($userSql, [$userId], 'i');
    $companyId = $userResult[0]['company_id'] ?? null;
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $assetId = $input['asset_id'] ?? null;
    $dispatchType = $input['dispatch_type'] ?? null;
    $toUserId = $input['to_user_id'] ?? null;
    $notes = $input['notes'] ?? '';
    
    if (!$assetId || !$dispatchType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Asset ID and dispatch type are required']);
        exit;
    }
    
    // Verify asset exists and belongs to contractor's inventory
    $assetSql = "SELECT a.id, a.serial_number, a.status, a.product_id, p.name as product_name
                 FROM assets a
                 LEFT JOIN products p ON a.product_id = p.id
                 WHERE a.id = ?";
    $asset = $db->getResults($assetSql, [$assetId], 'i');
    
    if (empty($asset)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit;
    }
    
    $asset = $asset[0];
    
    // Generate dispatch number
    $dispatchNumber = 'DSP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    if ($dispatchType === 'to_engineer') {
        if (!$toUserId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Engineer selection is required']);
            exit;
        }
        
        // Verify engineer belongs to same company
        $engSql = "SELECT id, first_name, last_name FROM users WHERE id = ? AND company_id = ?";
        $engineer = $db->getResults($engSql, [$toUserId, $companyId], 'ii');
        
        if (empty($engineer)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid engineer selection']);
            exit;
        }
        
        // Get or create contractor's warehouse
        $contWhSql = "SELECT id FROM warehouses WHERE company_id = ? LIMIT 1";
        $contWarehouse = $db->getResults($contWhSql, [$companyId], 'i');
        
        if (empty($contWarehouse)) {
            // Get company name for warehouse name
            $compNameSql = "SELECT name FROM companies WHERE id = ?";
            $compNameResult = $db->getResults($compNameSql, [$companyId], 'i');
            $companyName = $compNameResult[0]['name'] ?? 'Contractor';
            
            // Create a default warehouse for the contractor
            $createWhSql = "INSERT INTO warehouses (name, company_id, location, status, created_at) VALUES (?, ?, 'Default Location', 'active', NOW())";
            $db->executeQuery($createWhSql, [$companyName . ' Warehouse', $companyId], 'si');
            $fromWarehouseId = $db->getConnection()->insert_id;
        } else {
            $fromWarehouseId = $contWarehouse[0]['id'];
        }
        
        // Create dispatch record
        $dispatchSql = "INSERT INTO dispatches (dispatch_number, from_company_id, from_warehouse_id, to_user_id, dispatch_date, status, acknowledgment_status, notes, created_by, created_at) 
                        VALUES (?, ?, ?, ?, NOW(), 'delivered', 'acknowledged', ?, ?, NOW())";
        $db->executeQuery($dispatchSql, [$dispatchNumber, $companyId, $fromWarehouseId, $toUserId, $notes, $userId], 'siiisi');
        $dispatchId = $db->getConnection()->insert_id;
        
        // Create dispatch item
        $itemSql = "INSERT INTO dispatch_items (dispatch_id, asset_id, product_id, quantity) VALUES (?, ?, ?, 1)";
        $db->executeQuery($itemSql, [$dispatchId, $assetId, $asset['product_id']], 'iii');
        
        // Update asset holder
        $updateSql = "UPDATE assets SET current_holder_type = 'user', current_holder_id = ?, status = 'in_use', updated_at = NOW() WHERE id = ?";
        $db->executeQuery($updateSql, [$toUserId, $assetId], 'ii');
        
        $engineerName = $engineer[0]['first_name'] . ' ' . $engineer[0]['last_name'];
        
        echo json_encode([
            'success' => true,
            'message' => "Asset dispatched to {$engineerName}",
            'data' => [
                'dispatch_id' => $dispatchId,
                'dispatch_number' => $dispatchNumber,
                'to_engineer' => $engineerName
            ]
        ]);
        
    } elseif ($dispatchType === 'return_to_adv') {
        // Get ADV company (main company, usually id=1 or type='adv')
        $advSql = "SELECT id, name FROM companies WHERE type = 'adv' OR id = 1 LIMIT 1";
        $advCompany = $db->getResults($advSql, [], '');
        
        if (empty($advCompany)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'ADV company not found']);
            exit;
        }
        
        $advCompanyId = $advCompany[0]['id'];
        
        // Get ADV warehouse (destination)
        $advWhSql = "SELECT id, name FROM warehouses WHERE company_id = ? LIMIT 1";
        $advWarehouse = $db->getResults($advWhSql, [$advCompanyId], 'i');
        $toWarehouseId = !empty($advWarehouse) ? $advWarehouse[0]['id'] : null;
        
        // Get contractor's warehouse (source) - create if doesn't exist
        $contWhSql = "SELECT id FROM warehouses WHERE company_id = ? LIMIT 1";
        $contWarehouse = $db->getResults($contWhSql, [$companyId], 'i');
        
        if (empty($contWarehouse)) {
            // Get company name for warehouse name
            $compNameSql = "SELECT name FROM companies WHERE id = ?";
            $compNameResult = $db->getResults($compNameSql, [$companyId], 'i');
            $companyName = $compNameResult[0]['name'] ?? 'Contractor';
            
            // Create a default warehouse for the contractor
            $createWhSql = "INSERT INTO warehouses (name, company_id, location, status, created_at) VALUES (?, ?, 'Default Location', 'active', NOW())";
            $db->executeQuery($createWhSql, [$companyName . ' Warehouse', $companyId], 'si');
            $fromWarehouseId = $db->getConnection()->insert_id;
        } else {
            $fromWarehouseId = $contWarehouse[0]['id'];
        }
        
        // Create dispatch record (return)
        $dispatchSql = "INSERT INTO dispatches (dispatch_number, from_company_id, from_warehouse_id, to_company_id, to_warehouse_id, dispatch_date, status, acknowledgment_status, notes, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), 'in_transit', 'pending', ?, ?, NOW())";
        $db->executeQuery($dispatchSql, [$dispatchNumber, $companyId, $fromWarehouseId, $advCompanyId, $toWarehouseId, $notes, $userId], 'siiissi');
        $dispatchId = $db->getConnection()->insert_id;
        
        // Create dispatch item
        $itemSql = "INSERT INTO dispatch_items (dispatch_id, asset_id, product_id, quantity) VALUES (?, ?, ?, 1)";
        $db->executeQuery($itemSql, [$dispatchId, $assetId, $asset['product_id']], 'iii');
        
        // Update asset status to returned/in_transit
        $updateSql = "UPDATE assets SET status = 'returned', updated_at = NOW() WHERE id = ?";
        $db->executeQuery($updateSql, [$assetId], 'i');
        
        echo json_encode([
            'success' => true,
            'message' => 'Asset return initiated to ADV',
            'data' => [
                'dispatch_id' => $dispatchId,
                'dispatch_number' => $dispatchNumber,
                'status' => 'in_transit'
            ]
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid dispatch type']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to dispatch: ' . $e->getMessage()]);
}
