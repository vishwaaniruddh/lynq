<?php
/**
 * Update Asset Working Condition API
 * POST /api/inventory/assets/update-condition.php
 */

require_once __DIR__ . '/../../../config/autoload.php';

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
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $assetId = $input['asset_id'] ?? null;
    $workingCondition = $input['working_condition'] ?? null;
    $remarks = $input['remarks'] ?? '';
    
    if (!$assetId || !$workingCondition) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Asset ID and working condition are required']);
        exit;
    }
    
    if (!in_array($workingCondition, ['working', 'not_working'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid working condition']);
        exit;
    }
    
    // Verify asset exists
    $assetSql = "SELECT id, serial_number, working_condition FROM assets WHERE id = ?";
    $asset = $db->getResults($assetSql, [$assetId], 'i');
    
    if (empty($asset)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit;
    }
    
    $oldCondition = $asset[0]['working_condition'];
    
    // Update asset condition
    $updateSql = "UPDATE assets SET working_condition = ?, updated_at = NOW() WHERE id = ?";
    $db->executeQuery($updateSql, [$workingCondition, $assetId], 'si');
    
    // Log the change in asset_history if table exists
    try {
        $historySql = "INSERT INTO asset_history (asset_id, action, old_value, new_value, remarks, performed_by, created_at) 
                       VALUES (?, 'condition_change', ?, ?, ?, ?, NOW())";
        $db->executeQuery($historySql, [$assetId, $oldCondition, $workingCondition, $remarks, $userId], 'isssi');
    } catch (Exception $e) {
        // History table might not exist, continue anyway
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Working condition updated successfully',
        'data' => [
            'asset_id' => $assetId,
            'old_condition' => $oldCondition,
            'new_condition' => $workingCondition
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update condition: ' . $e->getMessage()]);
}
