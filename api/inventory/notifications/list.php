<?php
/**
 * API Endpoint: Get Notifications List
 * Returns notifications for the current user
 * 
 * Requirements: 11.5
 * - Display notification type, related dispatch, and timestamp
 * 
 * GET Parameters:
 * - is_read: (optional) Filter by read status (0 or 1)
 * - limit: (optional) Limit number of results
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
    
    // Get query parameters
    $isRead = isset($_GET['is_read']) ? (bool)$_GET['is_read'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
    
    // Initialize service
    $notificationService = new InventoryNotificationService();
    
    // Get notifications
    $result = $notificationService->getNotifications($userId, $isRead, $limit);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        ApiResponse::error($result['message'], 400, $result['code'] ?? null);
    }
    
} catch (Exception $e) {
    error_log("Notifications list API error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}
