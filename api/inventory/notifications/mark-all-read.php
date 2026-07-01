<?php
/**
 * API Endpoint: Mark All Notifications as Read
 * Marks all notifications as read for the current user
 */

require_once __DIR__ . '/../../ApiResponse.php';
require_once __DIR__ . '/../../../config/autoload.php';
require_once __DIR__ . '/../../../services/InventoryNotificationService.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    
    // Mark all as read
    $result = $notificationService->markAllAsRead($userId);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        ApiResponse::error($result['message'], 400, $result['code'] ?? null);
    }
    
} catch (Exception $e) {
    error_log("Mark all read API error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}
