<?php
/**
 * Mark Asset for Repair API
 * POST /api/inventory/assets/mark-repair.php
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
    $issueType = $input['issue_type'] ?? null;
    $description = $input['description'] ?? '';
    $priority = $input['priority'] ?? 'medium';
    
    if (!$assetId || !$issueType || !$description) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Asset ID, issue type, and description are required']);
        exit;
    }
    
    // Verify asset exists
    $assetSql = "SELECT id, serial_number, status, working_condition FROM assets WHERE id = ?";
    $asset = $db->getResults($assetSql, [$assetId], 'i');
    
    if (empty($asset)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit;
    }
    
    // Update asset status and condition
    $updateSql = "UPDATE assets SET status = 'under_repair', working_condition = 'not_working', updated_at = NOW() WHERE id = ?";
    $db->executeQuery($updateSql, [$assetId], 'i');
    
    // Try to create repair record if repairs table exists
    $repairId = null;
    try {
        $repairSql = "INSERT INTO repairs (asset_id, issue_type, description, priority, status, reported_by, created_at) 
                      VALUES (?, ?, ?, ?, 'pending', ?, NOW())";
        $db->executeQuery($repairSql, [$assetId, $issueType, $description, $priority, $userId], 'isssi');
        $repairId = $db->getConnection()->insert_id;
    } catch (Exception $e) {
        // Repairs table might not exist, continue anyway
    }
    
    // Log in asset_history if table exists
    try {
        $historySql = "INSERT INTO asset_history (asset_id, action, new_value, remarks, performed_by, created_at) 
                       VALUES (?, 'marked_for_repair', 'under_repair', ?, ?, NOW())";
        $db->executeQuery($historySql, [$assetId, $description, $userId], 'isi');
    } catch (Exception $e) {
        // History table might not exist
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Asset marked for repair successfully',
        'data' => [
            'asset_id' => $assetId,
            'repair_id' => $repairId,
            'status' => 'under_repair'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark for repair: ' . $e->getMessage()]);
}
