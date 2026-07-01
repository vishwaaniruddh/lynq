<?php
/**
 * ADV Clarity Management System - Push Notification Unsubscribe API
 * Handles push notification subscription removal
 */

require_once '../../config/autoload.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/CSRFMiddleware.php';
require_once '../ApiResponse.php';
require_once '../../services/NotificationService.php';

// Apply middleware
AuthMiddleware::requireAuth();
CSRFMiddleware::validate();

// Only accept DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    ApiResponse::error('Method not allowed', 405);
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['endpoint'])) {
        ApiResponse::error('Endpoint required', 400);
    }
    
    $endpoint = $input['endpoint'];
    $userId = $_SESSION['user_id'];
    
    // Remove subscription using NotificationService
    $notificationService = new NotificationService();
    $removed = $notificationService->removeSubscription($userId, $endpoint);
    
    if ($removed) {
        ApiResponse::success([
            'message' => 'Push notification subscription removed successfully'
        ]);
    } else {
        ApiResponse::error('Subscription not found', 404);
    }
    
} catch (Exception $e) {
    error_log("Push unsubscribe error: " . $e->getMessage());
    ApiResponse::error('Failed to remove subscription', 500);
}
?>