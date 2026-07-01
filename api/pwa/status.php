<?php
/**
 * ADV Clarity Management System - PWA Status API
 * Returns the current status and configuration of PWA features
 */

require_once '../../config/autoload.php';
require_once '../../config/pwa.php';
require_once '../../middleware/ApiAuthMiddleware.php';
require_once '../ApiResponse.php';
require_once '../../services/NotificationService.php';
require_once '../../services/SyncService.php';
require_once '../../services/AnalyticsService.php';

// Apply authentication middleware
$authMiddleware = new ApiAuthMiddleware();
$authMiddleware->checkRateLimit();
$user = $authMiddleware->requireAuth();

try {
    $userId = $user['id'];
    $companyId = $user['company_id'];
    
    // Get PWA configuration
    $config = getPWAConfig();
    
    // Get service statuses
    $notificationService = new NotificationService();
    $syncService = new SyncService();
    $analyticsService = new AnalyticsService();
    
    // Check push notification subscription status
    $pushSubscriptions = $notificationService->getUserSubscriptions($userId);
    
    // Get sync queue status
    $syncStatus = $syncService->getQueueStatus($userId, $companyId);
    
    // Get recent analytics summary
    $analytics = $analyticsService->getPWAAnalytics($userId, $companyId, '7d');
    
    // Check service worker registration (client-side only)
    $status = [
        'pwaEnabled' => isPWAEnabled(),
        'httpsEnabled' => isHTTPS(),
        'features' => [
            'serviceWorker' => true, // Always true if PWA is enabled
            'pushNotifications' => $config['push']['enabled'] && count($pushSubscriptions) > 0,
            'offlineSync' => $config['sync']['maxRetries'] > 0,
            'backgroundSync' => $config['features']['backgroundSync'],
            'installPrompt' => $config['features']['installBanner'],
            'analytics' => $config['analytics']['enabled']
        ],
        'subscriptions' => [
            'push' => count($pushSubscriptions),
            'maxAllowed' => $config['push']['maxSubscriptionsPerUser']
        ],
        'sync' => [
            'queueLength' => $syncStatus['totalPending'],
            'failedActions' => $syncStatus['failedCount'],
            'conflicts' => $syncStatus['conflictCount']
        ],
        'analytics' => [
            'totalEvents' => $analytics['summary']['total_events'] ?? 0,
            'offlineActions' => $analytics['summary']['offline_actions_queued'] ?? 0,
            'cacheHitRate' => $analytics['summary']['cache_hit_rate'] ?? 0
        ],
        'manifest' => [
            'name' => $config['manifest']['name'],
            'shortName' => $config['manifest']['shortName'],
            'themeColor' => $config['manifest']['themeColor']
        ],
        'cache' => [
            'version' => $config['cache']['version'],
            'ttl' => $config['cache']['ttl']
        ],
        'debug' => $config['debug']['enabled']
    ];
    
    ApiResponse::success($status);
    
} catch (Exception $e) {
    error_log("PWA status API error: " . $e->getMessage());
    ApiResponse::error('Failed to get PWA status', 500);
}