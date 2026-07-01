<?php
/**
 * System Administration Service
 * Provides system health monitoring, user activity reporting, database maintenance,
 * and performance monitoring functionality
 * 
 * Requirements: 4.5, 7.4 - Audit trail and system administration
 */

require_once __DIR__ . '/../config/autoload.php';

class SystemAdminService {
    private $db;
    private $pdo;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->pdo = Database::getInstance()->getConnection();
    }
    
    /**
     * Get system health status
     * @return array Health status information
     */
    public function getSystemHealth() {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];
        
        // Database connection check
        $health['checks']['database'] = $this->checkDatabaseHealth();
        
        // Session storage check
        $health['checks']['sessions'] = $this->checkSessionHealth();
        
        // Disk space check
        $health['checks']['disk_space'] = $this->checkDiskSpace();
        
        // Memory usage check
        $health['checks']['memory'] = $this->checkMemoryUsage();
        
        // Security events check
        $health['checks']['security'] = $this->checkSecurityStatus();
        
        // Determine overall status
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'critical') {
                $health['status'] = 'critical';
                break;
            } elseif ($check['status'] === 'warning' && $health['status'] !== 'critical') {
                $health['status'] = 'warning';
            }
        }
        
        return $health;
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth() {
        try {
            $start = microtime(true);
            $stmt = $this->pdo->query("SELECT 1");
            $responseTime = (microtime(true) - $start) * 1000;
            
            // Get database size
            $stmt = $this->pdo->query("
                SELECT SUM(data_length + index_length) as size 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $dbSize = $result['size'] ?? 0;
            
            // Get table count
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $tableCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            return [
                'status' => $responseTime < 100 ? 'healthy' : ($responseTime < 500 ? 'warning' : 'critical'),
                'response_time_ms' => round($responseTime, 2),
                'database_size' => $this->formatBytes($dbSize),
                'database_size_bytes' => $dbSize,
                'table_count' => $tableCount,
                'message' => 'Database connection successful'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    
    /**
     * Check session storage health
     */
    private function checkSessionHealth() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM user_sessions WHERE expires_at > NOW()");
            $activeSessions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM user_sessions WHERE expires_at <= NOW()");
            $expiredSessions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            return [
                'status' => 'healthy',
                'active_sessions' => $activeSessions,
                'expired_sessions' => $expiredSessions,
                'message' => 'Session storage operational'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Session check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check disk space
     */
    private function checkDiskSpace() {
        $path = __DIR__;
        $totalSpace = @disk_total_space($path);
        $freeSpace = @disk_free_space($path);
        
        if ($totalSpace === false || $freeSpace === false) {
            return [
                'status' => 'warning',
                'message' => 'Unable to determine disk space'
            ];
        }
        
        $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
        
        return [
            'status' => $usedPercent < 80 ? 'healthy' : ($usedPercent < 90 ? 'warning' : 'critical'),
            'total' => $this->formatBytes($totalSpace),
            'free' => $this->formatBytes($freeSpace),
            'used_percent' => round($usedPercent, 1),
            'message' => sprintf('%.1f%% disk space used', $usedPercent)
        ];
    }
    
    /**
     * Check memory usage
     */
    private function checkMemoryUsage() {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usedPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;
        
        return [
            'status' => $usedPercent < 70 ? 'healthy' : ($usedPercent < 85 ? 'warning' : 'critical'),
            'current' => $this->formatBytes($memoryUsage),
            'peak' => $this->formatBytes($memoryPeak),
            'limit' => $this->formatBytes($memoryLimit),
            'used_percent' => round($usedPercent, 1),
            'message' => sprintf('%.1f%% memory used', $usedPercent)
        ];
    }
    
    /**
     * Check security status
     */
    private function checkSecurityStatus() {
        try {
            // Check for recent critical security events
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM security_events 
                WHERE severity = 'CRITICAL' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $criticalEvents = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Check for failed login attempts
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM login_attempts 
                WHERE success = 0 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute();
            $failedLogins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $status = 'healthy';
            if ($criticalEvents > 10 || $failedLogins > 50) {
                $status = 'critical';
            } elseif ($criticalEvents > 0 || $failedLogins > 20) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'critical_events_24h' => $criticalEvents,
                'failed_logins_1h' => $failedLogins,
                'message' => $status === 'healthy' ? 'No security concerns' : 'Security events detected'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Security check failed: ' . $e->getMessage()
            ];
        }
    }

    
    /**
     * Get user activity report
     * @param array $filters Filter options
     * @param int $limit Maximum results
     * @return array Activity report data
     */
    public function getUserActivityReport($filters = [], $limit = 100) {
        try {
            $where = ['1=1'];
            $params = [];
            
            if (!empty($filters['user_id'])) {
                $where[] = 'ual.user_id = ?';
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $where[] = 'ual.action LIKE ?';
                $params[] = '%' . $filters['action'] . '%';
            }
            
            if (!empty($filters['from_date'])) {
                $where[] = 'ual.timestamp >= ?';
                $params[] = $filters['from_date'];
            }
            
            if (!empty($filters['to_date'])) {
                $where[] = 'ual.timestamp <= ?';
                $params[] = $filters['to_date'];
            }
            
            if (!empty($filters['company_id'])) {
                $where[] = 'u.company_id = ?';
                $params[] = $filters['company_id'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "
                SELECT ual.*, u.username, u.email, c.name as company_name,
                       p.username as performed_by_username
                FROM user_audit_log ual
                LEFT JOIN users u ON ual.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN users p ON ual.performed_by = p.id
                WHERE {$whereClause}
                ORDER BY ual.timestamp DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get user activity report error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get activity statistics
     * @param int $days Number of days to analyze
     * @return array Statistics
     */
    public function getActivityStatistics($days = 30) {
        try {
            $stats = [];
            
            // Total activities by type
            $stmt = $this->pdo->prepare("
                SELECT action, COUNT(*) as count
                FROM user_audit_log
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action
                ORDER BY count DESC
            ");
            $stmt->execute([$days]);
            $stats['by_action'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Activities by day
            $stmt = $this->pdo->prepare("
                SELECT DATE(timestamp) as date, COUNT(*) as count
                FROM user_audit_log
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(timestamp)
                ORDER BY date
            ");
            $stmt->execute([$days]);
            $stats['by_day'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Most active users
            $stmt = $this->pdo->prepare("
                SELECT u.username, u.email, COUNT(*) as activity_count
                FROM user_audit_log ual
                JOIN users u ON ual.performed_by = u.id
                WHERE ual.timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY u.id, u.username, u.email
                ORDER BY activity_count DESC
                LIMIT 10
            ");
            $stmt->execute([$days]);
            $stats['most_active_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Total counts
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM user_audit_log
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats['total_activities'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Get activity statistics error: " . $e->getMessage());
            return [];
        }
    }

    
    /**
     * Get database tables information
     * @return array Table information
     */
    public function getDatabaseTablesInfo() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size,
                    create_time,
                    update_time
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                ORDER BY total_size DESC
            ");
            
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tables as &$table) {
                $table['data_length_formatted'] = $this->formatBytes($table['data_length']);
                $table['index_length_formatted'] = $this->formatBytes($table['index_length']);
                $table['total_size_formatted'] = $this->formatBytes($table['total_size']);
            }
            
            return $tables;
        } catch (Exception $e) {
            error_log("Get database tables info error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Optimize database tables
     * @return array Optimization results
     */
    public function optimizeTables() {
        try {
            $results = [];
            
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                try {
                    $this->pdo->exec("OPTIMIZE TABLE `{$table}`");
                    $results[$table] = ['status' => 'success', 'message' => 'Optimized'];
                } catch (Exception $e) {
                    $results[$table] = ['status' => 'error', 'message' => $e->getMessage()];
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Optimize tables error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Clean up old data (maintenance)
     * @param int $daysToKeep Days to keep data
     * @return array Cleanup results
     */
    public function cleanupOldData($daysToKeep = 90) {
        $results = [];
        
        try {
            // Clean old sessions
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$daysToKeep]);
            $results['sessions'] = ['deleted' => $stmt->rowCount()];
            
            // Clean old login attempts
            $stmt = $this->pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$daysToKeep]);
            $results['login_attempts'] = ['deleted' => $stmt->rowCount()];
            
            // Clean old security events (keep critical ones longer)
            $stmt = $this->pdo->prepare("
                DELETE FROM security_events 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND severity != 'CRITICAL'
            ");
            $stmt->execute([$daysToKeep]);
            $results['security_events'] = ['deleted' => $stmt->rowCount()];
            
            // Clean old API access logs
            $stmt = $this->pdo->prepare("DELETE FROM api_access_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$daysToKeep]);
            $results['api_access_log'] = ['deleted' => $stmt->rowCount()];
            
            // Clean old company access logs
            $stmt = $this->pdo->prepare("DELETE FROM company_access_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$daysToKeep]);
            $results['company_access_log'] = ['deleted' => $stmt->rowCount()];
            
            $results['status'] = 'success';
            $results['message'] = 'Cleanup completed successfully';
            
        } catch (Exception $e) {
            error_log("Cleanup old data error: " . $e->getMessage());
            $results['status'] = 'error';
            $results['message'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Create database backup (SQL dump)
     * @return array Backup result
     */
    public function createBackup() {
        try {
            $backupDir = __DIR__ . '/../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $filename = 'backup_' . date('Y-m-d_His') . '.sql';
            $filepath = $backupDir . '/' . $filename;
            
            // Get all tables
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $backup = "-- ADV CRM Database Backup\n";
            $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $backup .= "-- Database: clarity_db\n\n";
            $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Get create table statement
                $stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $backup .= $row['Create Table'] . ";\n\n";
                
                // Get table data
                $stmt = $this->pdo->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    
                    foreach ($rows as $row) {
                        $values = array_map(function($val) {
                            if ($val === null) return 'NULL';
                            return "'" . addslashes($val) . "'";
                        }, array_values($row));
                        
                        $backup .= "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }
            
            $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($filepath, $backup);
            
            return [
                'status' => 'success',
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $this->formatBytes(filesize($filepath)),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Create backup error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    
    /**
     * Get list of backups
     * @return array List of backup files
     */
    public function getBackups() {
        $backupDir = __DIR__ . '/../backups';
        $backups = [];
        
        if (!is_dir($backupDir)) {
            return $backups;
        }
        
        $files = glob($backupDir . '/backup_*.sql');
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => $this->formatBytes(filesize($file)),
                'size_bytes' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by date descending
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }
    
    /**
     * Delete a backup file
     * @param string $filename Backup filename
     * @return bool Success status
     */
    public function deleteBackup($filename) {
        $backupDir = __DIR__ . '/../backups';
        $filepath = $backupDir . '/' . basename($filename);
        
        if (file_exists($filepath) && strpos($filename, 'backup_') === 0) {
            return unlink($filepath);
        }
        
        return false;
    }
    
    /**
     * Get system configuration
     * @return array Configuration settings
     */
    public function getSystemConfiguration() {
        return [
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'display_errors' => ini_get('display_errors'),
                'error_reporting' => ini_get('error_reporting'),
                'session_gc_maxlifetime' => ini_get('session.gc_maxlifetime')
            ],
            'database' => [
                'host' => 'localhost',
                'database' => 'clarity_db',
                'charset' => 'utf8mb4'
            ],
            'security' => [
                'session_timeout' => defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600,
                'max_login_attempts' => defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5,
                'lockout_duration' => defined('LOCKOUT_DURATION') ? LOCKOUT_DURATION : 900,
                'password_min_length' => defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8
            ],
            'application' => [
                'base_path' => __DIR__ . '/..',
                'timezone' => date_default_timezone_get(),
                'server_time' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Get performance metrics
     * @return array Performance data
     */
    public function getPerformanceMetrics() {
        $metrics = [];
        
        // Database query performance
        try {
            $start = microtime(true);
            $this->pdo->query("SELECT COUNT(*) FROM users");
            $metrics['db_query_time'] = round((microtime(true) - $start) * 1000, 2);
        } catch (Exception $e) {
            $metrics['db_query_time'] = null;
        }
        
        // Active connections (approximate)
        try {
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $metrics['db_connections'] = $result['Value'] ?? 0;
        } catch (Exception $e) {
            $metrics['db_connections'] = null;
        }
        
        // Slow queries
        try {
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Slow_queries'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $metrics['slow_queries'] = $result['Value'] ?? 0;
        } catch (Exception $e) {
            $metrics['slow_queries'] = null;
        }
        
        // PHP metrics
        $metrics['php_memory_usage'] = memory_get_usage(true);
        $metrics['php_memory_peak'] = memory_get_peak_usage(true);
        $metrics['php_memory_usage_formatted'] = $this->formatBytes($metrics['php_memory_usage']);
        $metrics['php_memory_peak_formatted'] = $this->formatBytes($metrics['php_memory_peak']);
        
        // Request metrics (from API access log)
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_requests,
                    AVG(response_time) as avg_response_time,
                    MAX(response_time) as max_response_time
                FROM api_access_log
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $metrics['api_requests_1h'] = $result['total_requests'] ?? 0;
            $metrics['api_avg_response_time'] = round($result['avg_response_time'] ?? 0, 2);
            $metrics['api_max_response_time'] = round($result['max_response_time'] ?? 0, 2);
        } catch (Exception $e) {
            $metrics['api_requests_1h'] = 0;
            $metrics['api_avg_response_time'] = 0;
            $metrics['api_max_response_time'] = 0;
        }
        
        return $metrics;
    }
    
    /**
     * Get entity counts for dashboard
     * @return array Entity counts
     */
    public function getEntityCounts() {
        $counts = [];
        
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE status = 1");
            $counts['active_users'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE status = 0");
            $counts['inactive_users'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'ACTIVE'");
            $counts['active_companies'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM roles");
            $counts['roles'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM permissions");
            $counts['permissions'] = $stmt->fetchColumn();
            
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW()");
            $counts['active_sessions'] = $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Get entity counts error: " . $e->getMessage());
        }
        
        return $counts;
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($limit) {
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}
