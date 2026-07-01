<?php
/**
 * ADV Clarity Management System - Push Notification Subscription API
 * Handles push notification subscription registration
 */

require_once '../../config/autoload.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/CSRFMiddleware.php';
require_once '../ApiResponse.php';
require_once '../../services/NotificationService.php';

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
    
    if (!$input || !isset($input['subscription'])) {
        ApiResponse::error('Invalid subscription data', 400);
    }
    
    $subscription = $input['subscription'];
    
    // Validate subscription structure
    if (!isset($subscription['endpoint']) || 
        !isset($subscription['keys']['p256dh']) || 
        !isset($subscription['keys']['auth'])) {
        ApiResponse::error('Invalid subscription format', 400);
    }
    
    // Get current user
    $userId = $_SESSION['user_id'];
    $companyId = $_SESSION['company_id'];
    
    // Save subscription using NotificationService
    $notificationService = new NotificationService();
    $subscriptionId = $notificationService->saveSubscription(
        $userId,
        $companyId,
        $subscription['endpoint'],
        $subscription['keys']['p256dh'],
        $subscription['keys']['auth']
    );
    
    ApiResponse::success([
        'subscriptionId' => $subscriptionId,
        'message' => 'Push notification subscription saved successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Push subscription error: " . $e->getMessage());
    ApiResponse::error('Failed to save subscription', 500);
}
?>