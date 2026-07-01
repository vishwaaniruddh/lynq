<?php
/**
 * ADV Clarity Management System - Conflict Resolution API
 * Handles data conflicts from offline sync operations
 */

require_once '../../config/autoload.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/CSRFMiddleware.php';
require_once '../ApiResponse.php';
require_once '../../services/SyncService.php';

// Apply middleware
AuthMiddleware::requireAuth();
CSRFMiddleware::validate();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['conflictId']) || !isset($input['resolution'])) {
        ApiResponse::error('Conflict ID and resolution required', 400);
    }
    
    $conflictId = $input['conflictId'];
    $resolution = $input['resolution']; // 'client', 'server', or 'merge'
    $mergeData = $input['mergeData'] ?? null;
    
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    
    $syncService = new SyncService();
    $result = $syncService->resolveConflict($userId, $companyId, $conflictId, $resolution, $mergeData);
    
    if ($result) {
        ApiResponse::success([
            'message' => 'Conflict resolved successfully',
            'conflictId' => $conflictId,
            'resolution' => $resolution
        ]);
    } else {
        ApiResponse::error('Failed to resolve conflict', 400);
    }
    
} catch (Exception $e) {
    error_log("Conflict resolution API error: " . $e->getMessage());
    ApiResponse::error('Failed to resolve conflict', 500);
}
?>