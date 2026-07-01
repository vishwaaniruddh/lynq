<?php
/**
 * Push Notifications API
 * Handles push notification subscriptions and sending notifications
 */

require_once '../../config/autoload.php';
require_once '../ApiResponse.php';

use Middleware\ApiAuthMiddleware;
use Services\PushNotificationService;

// Apply authentication middleware
ApiAuthMiddleware::authenticate();

$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';

try {
    $notificationService = new PushNotificationService();
    
    switch ($method) {
        case 'POST':
            if ($pathInfo === '/subscribe') {
                handleSubscribe($notificationService);
            } elseif ($pathInfo === '/send') {
                handleSendNotification($notificationService);
            } else {
                ApiResponse::error('Invalid endpoint', 404);
            }
            break;
            
        case 'DELETE':
            if ($pathInfo === '/unsubscribe') {
                handleUnsubscribe($notificationService);
            } else {
                ApiResponse::error('Invalid endpoint', 404);
            }
            break;
            
        case 'GET':
            if ($pathInfo === '/subscription') {
                handleGetSubscription($notificationService);
            } elseif ($pathInfo === '/vapid-key') {
                handleGetVapidKey($notificationService);
            } else {
                ApiResponse::error('Invalid endpoint', 404);
            }
            break;
            
        default:
            ApiResponse::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Push Notifications API Error: " . $e->getMessage());
    ApiResponse::error('Internal server error', 500);
}

/**
 * Handle push notification subscription
 */
function handleSubscribe($notificationService) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['subscription'])) {
        ApiResponse::error('Invalid subscription data', 400);
        return;
    }
    
    $subscription = $input['subscription'];
    $userId = $_SESSION['user_id'];
    
    // Validate subscription format
    if (!isset($subscription['endpoint']) || 
        !isset($subscription['keys']['p256dh']) || 
        !isset($subscription['keys']['auth'])) {
        ApiResponse::error('Invalid subscription format', 400);
        return;
    }
    
    $result = $notificationService->subscribe($userId, $subscription);
    
    if ($result) {
        ApiResponse::success(['message' => 'Subscription saved successfully']);
    } else {
        ApiResponse::error('Failed to save subscription', 500);
    }
}

/**
 * Handle push notification unsubscription
 */
function handleUnsubscribe($notificationService) {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    
    $endpoint = $input['endpoint'] ?? null;
    
    $result = $notificationService->unsubscribe($userId, $endpoint);
    
    if ($result) {
        ApiResponse::success(['message' => 'Unsubscribed successfully']);
    } else {
        ApiResponse::error('Failed to unsubscribe', 500);
    }
}

/**
 * Handle getting user's subscription status
 */
function handleGetSubscription($notificationService) {
    $userId = $_SESSION['user_id'];
    
    $subscription = $notificationService->getSubscription($userId);
    
    ApiResponse::success([
        'subscription' => $subscription,
        'isSubscribed' => !empty($subscription)
    ]);
}

/**
 * Handle getting VAPID public key
 */
function handleGetVapidKey($notificationService) {
    $vapidKey = $notificationService->getVapidPublicKey();
    
    ApiResponse::success(['vapidKey' => $vapidKey]);
}

/**
 * Handle sending push notification (admin only)
 */
function handleSendNotification($notificationService) {
    // Check if user has permission to send notifications
    if (!isset($_SESSION['permissions']) || 
        !in_array('notifications.send', $_SESSION['permissions'])) {
        ApiResponse::error('Insufficient permissions', 403);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['title']) || !isset($input['body'])) {
        ApiResponse::error('Title and body are required', 400);
        return;
    }
    
    $notification = [
        'title' => $input['title'],
        'body' => $input['body'],
        'icon' => $input['icon'] ?? '/assets/icons/icon-192.png',
        'badge' => $input['badge'] ?? '/assets/icons/icon-72.png',
        'data' => $input['data'] ?? [],
        'actions' => $input['actions'] ?? [],
        'requireInteraction' => $input['requireInteraction'] ?? false
    ];
    
    // Determine recipients
    $recipients = $input['recipients'] ?? 'all';
    
    $result = $notificationService->sendNotification($notification, $recipients);
    
    if ($result['success']) {
        ApiResponse::success([
            'message' => 'Notifications sent successfully',
            'sent' => $result['sent'],
            'failed' => $result['failed']
        ]);
    } else {
        ApiResponse::error('Failed to send notifications', 500);
    }
}