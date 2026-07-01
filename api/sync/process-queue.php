<?php
/**
 * ADV Clarity Management System - Process Offline Queue API
 * Processes queued offline actions
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
    
    if (!$input || !isset($input['actions']) || !is_array($input['actions'])) {
        ApiResponse::error('Invalid actions data', 400);
    }
    
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    $actions = $input['actions'];
    
    $syncService = new SyncService();
    $results = $syncService->processQueuedActions($userId, $companyId, $actions);
    
    ApiResponse::success([
        'processed' => count($results['successful']),
        'failed' => count($results['failed']),
        'successful' => $results['successful'],
        'failed' => $results['failed'],
        'conflicts' => $results['conflicts'] ?? []
    ]);
    
} catch (Exception $e) {
    error_log("Process queue API error: " . $e->getMessage());
    ApiResponse::error('Failed to process queue', 500);
}
?>