<?php
/**
 * ADV Clarity Management System - VAPID Key API
 * Returns the VAPID public key for push notification subscriptions
 */

require_once '../../config/autoload.php';
require_once '../../config/pwa.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../ApiResponse.php';

// Apply authentication middleware
AuthMiddleware::requireAuth();

try {
    // Check if PWA is enabled
    if (!isPWAEnabled()) {
        ApiResponse::error('PWA functionality is not enabled', 503);
    }
    
    // Get VAPID public key from configuration
    $vapidPublicKey = VAPID_PUBLIC_KEY;
    
    if (!$vapidPublicKey || $vapidPublicKey === 'your-vapid-public-key-here') {
        ApiResponse::error('VAPID keys not configured', 500);
    }
    
    ApiResponse::success([
        'vapidKey' => $vapidPublicKey,
        'pushEnabled' => PWA_PUSH_ENABLED
    ]);
    
} catch (Exception $e) {
    error_log("VAPID key API error: " . $e->getMessage());
    ApiResponse::error('Failed to get VAPID key', 500);
}
?>