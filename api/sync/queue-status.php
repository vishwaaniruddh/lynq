<?php
/**
 * ADV Clarity Management System - Offline Queue Status API
 * Returns the status of offline sync operations
 */

require_once '../../config/autoload.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../ApiResponse.php';
require_once '../../services/SyncService.php';

// Apply authentication middleware
AuthMiddleware::requireAuth();

try {
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    
    $syncService = new SyncService();
    $status = $syncService->getQueueStatus($userId, $companyId);
    
    ApiResponse::success($status);
    
} catch (Exception $e) {
    error_log("Queue status API error: " . $e->getMessage());
    ApiResponse::error('Failed to get queue status', 500);
}
?>