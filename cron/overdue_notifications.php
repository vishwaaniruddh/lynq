<?php
/**
 * Cron Job: Overdue Pending Receive Notifications
 * Sends reminder notifications for overdue pending receives
 * 
 * Requirements: 11.4
 * - Check for overdue pending receives daily
 * - Send reminder notifications to recipients
 * 
 * Recommended cron schedule: Run daily at 9:00 AM
 * 0 9 * * * php /path/to/clarity/new_crm/cron/overdue_notifications.php
 * 
 * For Windows Task Scheduler:
 * - Program: php.exe
 * - Arguments: C:\path\to\clarity\new_crm\cron\overdue_notifications.php
 * - Schedule: Daily at 9:00 AM
 */

// Set timezone
date_default_timezone_set("Asia/Calcutta");

// Disable time limit for cron job
set_time_limit(0);

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define base path
define('CRON_BASE_PATH', dirname(__DIR__));

// Include required files
require_once CRON_BASE_PATH . '/config/autoload.php';
require_once CRON_BASE_PATH . '/services/InventoryNotificationService.php';
require_once CRON_BASE_PATH . '/repositories/PendingReceiveRepository.php';

/**
 * Log message to file and optionally to console
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    
    // Log to file
    $logFile = CRON_BASE_PATH . '/logs/cron_overdue_notifications.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
    
    // Also output to console if running from CLI
    if (php_sapi_name() === 'cli') {
        echo $logMessage . PHP_EOL;
    }
}

/**
 * Main cron job execution
 */
function runOverdueNotificationsCron() {
    logMessage('Starting overdue notifications cron job');
    
    try {
        // Initialize service
        $notificationService = new InventoryNotificationService();
        
        // Get threshold from config or use default (7 days)
        $thresholdDays = defined('OVERDUE_THRESHOLD_DAYS') 
            ? OVERDUE_THRESHOLD_DAYS 
            : PendingReceiveRepository::DEFAULT_OVERDUE_DAYS;
        
        logMessage("Using overdue threshold: $thresholdDays days");
        
        // Send overdue notifications
        $result = $notificationService->sendOverdueNotifications($thresholdDays);
        
        if ($result['success']) {
            $sent = $result['data']['notifications_sent'];
            $total = $result['data']['total_overdue'];
            
            logMessage("Overdue notifications sent: $sent of $total total overdue items");
            
            // Log any errors
            if (!empty($result['data']['errors'])) {
                foreach ($result['data']['errors'] as $error) {
                    logMessage("Error for pending_receive_id {$error['pending_receive_id']}: {$error['error']}", 'ERROR');
                }
            }
        } else {
            logMessage("Failed to send overdue notifications: " . $result['message'], 'ERROR');
        }
        
        // Cleanup old read notifications (older than 30 days)
        logMessage('Cleaning up old notifications');
        $cleanupResult = $notificationService->cleanupOldNotifications(30);
        
        if ($cleanupResult['success']) {
            $deleted = $cleanupResult['data']['deleted_count'];
            logMessage("Cleaned up $deleted old notifications");
        }
        
        logMessage('Overdue notifications cron job completed successfully');
        return true;
        
    } catch (Exception $e) {
        logMessage('Cron job failed with exception: ' . $e->getMessage(), 'ERROR');
        logMessage('Stack trace: ' . $e->getTraceAsString(), 'ERROR');
        return false;
    }
}

// Run the cron job
$success = runOverdueNotificationsCron();

// Exit with appropriate code
exit($success ? 0 : 1);
