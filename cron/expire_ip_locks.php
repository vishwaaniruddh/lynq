<?php
/**
 * Cron Job: Expire IP Locks
 * Automatically expires timed-out IP locks and logs timeout events
 * 
 * Requirements: 4.3, 9.3
 * - 4.3: Auto-expire locks after 20-minute timeout
 * - 9.3: Record timeout events in audit log
 * 
 * Recommended cron schedule: Run every minute
 * * * * * * php /path/to/clarity/new_crm/cron/expire_ip_locks.php
 * 
 * For Windows Task Scheduler:
 * - Program: php.exe
 * - Arguments: C:\path\to\clarity\new_crm\cron\expire_ip_locks.php
 * - Schedule: Every 1 minute
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
require_once CRON_BASE_PATH . '/repositories/IPLockRepository.php';
require_once CRON_BASE_PATH . '/repositories/ConfigurationAuditLogRepository.php';
require_once CRON_BASE_PATH . '/models/IPLock.php';
require_once CRON_BASE_PATH . '/models/IPMaster.php';
require_once CRON_BASE_PATH . '/models/ConfigurationAuditLog.php';

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
    $logFile = CRON_BASE_PATH . '/logs/cron_expire_ip_locks.log';
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
 * Get system user ID for audit logging
 * Uses the first user in the database
 * 
 * @return int System user ID
 */
function getSystemUserId() {
    try {
        $db = DatabaseConfig::getInstance();
        
        // Get first user from database
        $sql = "SELECT id FROM users ORDER BY id LIMIT 1";
        $result = $db->getResults($sql, [], '');
        
        if (!empty($result)) {
            return (int)$result[0]['id'];
        }
        
        // Default to 1 if no users found
        return 1;
        
    } catch (Exception $e) {
        logMessage("Error getting system user ID: " . $e->getMessage(), 'WARNING');
        return 1;
    }
}

/**
 * Main cron job execution
 * Expires timed-out locks and logs timeout events to audit log
 * 
 * Requirements: 4.3, 9.3
 */
function runExpireIPLocksCron() {
    logMessage('Starting IP lock expiry cron job');
    
    try {
        $lockRepository = new IPLockRepository();
        $auditLogRepository = new ConfigurationAuditLogRepository();
        $systemUserId = getSystemUserId();
        
        // Get expired locks before processing (for audit logging)
        $expiredLocks = $lockRepository->getExpiredLocksForAudit();
        
        if (empty($expiredLocks)) {
            logMessage('No expired locks found');
            logMessage('IP lock expiry cron job completed');
            return true;
        }
        
        logMessage("Found " . count($expiredLocks) . " expired lock(s) to process");
        
        // Process each expired lock
        $processedCount = 0;
        $errorCount = 0;
        
        foreach ($expiredLocks as $lock) {
            try {
                // Log the timeout event to audit log (Requirement 9.3)
                $details = [
                    'lock_id' => $lock['id'],
                    'locked_at' => $lock['locked_at'],
                    'expires_at' => $lock['expires_at'],
                    'original_user_id' => $lock['locked_by'],
                    'expired_by' => 'system_cron',
                    'network_ip' => $lock['network_ip'] ?? null,
                    'router_ip' => $lock['router_ip'] ?? null,
                    'site_ip' => $lock['site_ip'] ?? null,
                    'subnet_mask' => $lock['subnet_mask'] ?? null
                ];
                
                // Use the original user who created the lock for the audit log
                $userId = (int)$lock['locked_by'] ?: $systemUserId;
                
                $auditLogRepository->logLockExpired(
                    $userId,
                    $lock['router_serial_number'],
                    (int)$lock['ip_master_id'],
                    $details
                );
                
                logMessage("Logged expiry for lock ID {$lock['id']} (Router: {$lock['router_serial_number']}, IP Master: {$lock['ip_master_id']})");
                $processedCount++;
                
            } catch (Exception $e) {
                logMessage("Error logging expiry for lock ID {$lock['id']}: " . $e->getMessage(), 'ERROR');
                $errorCount++;
            }
        }
        
        // Now expire the locks in the database (Requirement 4.3)
        $expiredCount = $lockRepository->expireTimedOutLocks();
        
        logMessage("Expired $expiredCount lock(s) in database");
        logMessage("Audit logged: $processedCount, Errors: $errorCount");
        logMessage('IP lock expiry cron job completed successfully');
        
        return true;
        
    } catch (Exception $e) {
        logMessage('Cron job failed with exception: ' . $e->getMessage(), 'ERROR');
        logMessage('Stack trace: ' . $e->getTraceAsString(), 'ERROR');
        return false;
    }
}

// Run the cron job
$success = runExpireIPLocksCron();

// Exit with appropriate code
exit($success ? 0 : 1);
