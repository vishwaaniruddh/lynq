<?php
/**
 * API Endpoint: Get Unread Notification Count
 * Returns the count of unread notifications for the current user
 * 
 * Requirements: 11.5
 */

require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../../services/InventoryNotificationService.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed', 405);
    exit;
}

try {
    // Initialize session service
    $sessionService = new SessionService();
    
    // Check if user is logged in
    $userId = $sessionService->getCurrentUserId();
    if (!$userId) {
        ApiResponse::error('Unauthorized', 401);
        exit;
    }
    
    // Initialize service
    $notificationService = new InventoryNotificationService();
    
    // Get unread count
    $result = $notificationService->getUnreadCount($userId);
    
    if ($result['success']) {
        ApiResponse::success($result['data']);
    } else {
        ApiResponse::error($result['message'], 400, $result['code'] ?? null);
    }
    
} catch (Exception $e) {
    error_log("Unread count API error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}
