<?php
/**
 * API Endpoint: Mark Notification as Read
 * Marks a single notification as read
 * 
 * POST Parameters:
 * - notification_id: (required) Notification ID to mark as read
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
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate notification_id
    if (empty($input['notification_id'])) {
        ApiResponse::error('notification_id is required', 400);
        exit;
    }
    
    $notificationId = (int)$input['notification_id'];
    
    // Initialize service
    $notificationService = new InventoryNotificationService();
    
    // Mark as read
    $result = $notificationService->markAsRead($notificationId);
    
    if ($result['success']) {
        ApiResponse::success($result['data'], $result['message']);
    } else {
        ApiResponse::error($result['message'], 400, $result['code'] ?? null);
    }
    
} catch (Exception $e) {
    error_log("Mark read API error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}
