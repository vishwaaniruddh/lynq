<?php
/**
 * Cron Job: Cleanup JWT Tokens
 * Removes expired refresh tokens and blacklist entries to prevent unbounded growth
 * 
 * Requirements: 3.5
 * - Remove expired entries from token_blacklist table
 * - Remove expired entries from refresh_tokens table
 * 
 * Recommended cron schedule: Run daily at midnight
 * 0 0 * * * php /path/to/clarity/new_crm/cron/cleanup_tokens.php
 * 
 * For Windows Task Scheduler:
 * - Program: php.exe
 * - Arguments: C:\path\to\clarity\new_crm\cron\cleanup_tokens.php
 * - Schedule: Daily at midnight
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
require_once CRON_BASE_PATH . '/repositories/RefreshTokenRepository.php';
require_once CRON_BASE_PATH . '/repositories/TokenBlacklistRepository.php';

/**
 * Log message to file and optionally to console
 * 
 * @param string $message Message to log
 * @param string $level Log level (INFO, WARNING, ERROR)
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    
    // Log to file
    $logFile = CRON_BASE_PATH . '/logs/cron_cleanup_tokens.log';
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
 * Cleans up expired refresh tokens and blacklist entries
 * 
 * Requirements: 3.5
 */
function runCleanupTokensCron() {
    logMessage('Starting JWT token cleanup cron job');
    
    try {
        $refreshTokenRepository = new RefreshTokenRepository();
        $tokenBlacklistRepository = new TokenBlacklistRepository();
        
        // Clean up expired refresh tokens
        logMessage('Cleaning up expired refresh tokens...');
        $refreshTokensRemoved = $refreshTokenRepository->cleanup();
        logMessage("Removed $refreshTokensRemoved expired refresh token(s)");
        
        // Clean up expired blacklist entries
        logMessage('Cleaning up expired blacklist entries...');
        $blacklistEntriesRemoved = $tokenBlacklistRepository->cleanup();
        logMessage("Removed $blacklistEntriesRemoved expired blacklist entry(ies)");
        
        // Log summary
        $totalRemoved = $refreshTokensRemoved + $blacklistEntriesRemoved;
        logMessage("Total entries cleaned up: $totalRemoved");
        logMessage('JWT token cleanup cron job completed successfully');
        
        return true;
        
    } catch (Exception $e) {
        logMessage('Cron job failed with exception: ' . $e->getMessage(), 'ERROR');
        logMessage('Stack trace: ' . $e->getTraceAsString(), 'ERROR');
        return false;
    }
}

// Run the cron job
$success = runCleanupTokensCron();

// Exit with appropriate code
exit($success ? 0 : 1);
