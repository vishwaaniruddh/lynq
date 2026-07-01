<?php
/**
 * ADV Clarity Management System - PWA Usage Analytics API
 * Tracks PWA usage metrics and analytics
 */

require_once '../../config/autoload.php';
require_once '../../middleware/AuthMiddleware.php';
require_once '../../middleware/CSRFMiddleware.php';
require_once '../ApiResponse.php';
require_once '../../services/AnalyticsService.php';

// Apply middleware
AuthMiddleware::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFMiddleware::validate();
}

try {
    $analyticsService = new AnalyticsService();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Track PWA usage event
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['event'])) {
            ApiResponse::error('Event data required', 400);
        }
        
        $userId = $_SESSION['user_id'];
        $companyId = $_SESSION['company_id'];
        
        $eventData = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'event_type' => $input['event'],
            'event_data' => $input['data'] ?? [],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $tracked = $analyticsService->trackPWAEvent($eventData);
        
        if ($tracked) {
            ApiResponse::success(['message' => 'Event tracked successfully']);
        } else {
            ApiResponse::error('Failed to track event', 500);
        }
        
    } else {
        // Get PWA usage analytics
        $userId = $_SESSION['user_id'];
        $companyId = $_SESSION['company_id'];
        $timeframe = $_GET['timeframe'] ?? '7d';
        
        $analytics = $analyticsService->getPWAAnalytics($userId, $companyId, $timeframe);
        
        ApiResponse::success($analytics);
    }
    
} catch (Exception $e) {
    error_log("PWA analytics API error: " . $e->getMessage());
    ApiResponse::error('Analytics operation failed', 500);
}
?>