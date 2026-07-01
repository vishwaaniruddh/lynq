<?php
/**
 * Security Event Service
 * Comprehensive security event logging system
 * 
 * Requirements: 4.5, 3.2 - Audit trail and security logging
 */

require_once __DIR__ . '/../config/autoload.php';

class SecurityEventService {
    private $db;
    
    // Event types
    const EVENT_LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    const EVENT_LOGIN_FAILED = 'LOGIN_FAILED';
    const EVENT_LOGOUT = 'LOGOUT';
    const EVENT_ACCOUNT_LOCKOUT = 'ACCOUNT_LOCKOUT';
    const EVENT_ACCOUNT_UNLOCKED = 'ACCOUNT_UNLOCKED';
    const EVENT_PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    const EVENT_PASSWORD_RESET = 'PASSWORD_RESET';
    const EVENT_PERMISSION_GRANTED = 'PERMISSION_GRANTED';
    const EVENT_PERMISSION_REVOKED = 'PERMISSION_REVOKED';
    const EVENT_CROSS_COMPANY_ACCESS = 'CROSS_COMPANY_ACCESS';
    const EVENT_UNAUTHORIZED_ACCESS = 'UNAUTHORIZED_ACCESS';
    const EVENT_IP_BLOCKED = 'IP_BLOCKED';
    const EVENT_IP_WHITELISTED = 'IP_WHITELISTED';
    const EVENT_SUSPICIOUS_ACTIVITY = 'SUSPICIOUS_ACTIVITY';
    const EVENT_SESSION_HIJACK_ATTEMPT = 'SESSION_HIJACK_ATTEMPT';
    const EVENT_CSRF_VIOLATION = 'CSRF_VIOLATION';
    const EVENT_SQL_INJECTION_ATTEMPT = 'SQL_INJECTION_ATTEMPT';
    const EVENT_XSS_ATTEMPT = 'XSS_ATTEMPT';
    const EVENT_2FA_ENABLED = '2FA_ENABLED';
    const EVENT_2FA_DISABLED = '2FA_DISABLED';
    const EVENT_USER_CREATED = 'USER_CREATED';
    const EVENT_USER_UPDATED = 'USER_UPDATED';
    const EVENT_USER_DELETED = 'USER_DELETED';
    
    // Severity levels
    const SEVERITY_INFO = 'INFO';
    const SEVERITY_WARNING = 'WARNING';
    const SEVERITY_CRITICAL = 'CRITICAL';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Log a security event
     * 
     * @param string $eventType Type of event
     * @param string $severity Severity level
     * @param int|null $userId User ID (if applicable)
     * @param string $ipAddress IP address
     * @param array|null $details Additional details
     * @return int|false Event ID or false on failure
     */
    public function logEvent($eventType, $severity, $userId, $ipAddress, $details = null) {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $detailsJson = $details ? json_encode($details) : null;
            
            $sql = "INSERT INTO security_events (event_type, severity, user_id, ip_address, user_agent, details) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->executeQuery($sql, [
                $eventType,
                $severity,
                $userId,
                $ipAddress,
                $userAgent,
                $detailsJson
            ], 'ssisss');
            
            $eventId = $this->db->getConnection()->insert_id;
            $stmt->close();
            
            // Also log critical events to PHP error log
            if ($severity === self::SEVERITY_CRITICAL) {
                error_log("SECURITY CRITICAL: {$eventType} - User: {$userId} - IP: {$ipAddress} - Details: {$detailsJson}");
            }
            
            return $eventId;
            
        } catch (Exception $e) {
            error_log("Security event logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log login success
     */
    public function logLoginSuccess($userId, $ipAddress, $details = []) {
        return $this->logEvent(
            self::EVENT_LOGIN_SUCCESS,
            self::SEVERITY_INFO,
            $userId,
            $ipAddress,
            $details
        );
    }
    
    /**
     * Log login failure
     */
    public function logLoginFailed($identifier, $ipAddress, $reason = null) {
        return $this->logEvent(
            self::EVENT_LOGIN_FAILED,
            self::SEVERITY_WARNING,
            null,
            $ipAddress,
            ['identifier' => $identifier, 'reason' => $reason]
        );
    }
    
    /**
     * Log unauthorized access attempt
     */
    public function logUnauthorizedAccess($userId, $ipAddress, $resource, $action) {
        return $this->logEvent(
            self::EVENT_UNAUTHORIZED_ACCESS,
            self::SEVERITY_WARNING,
            $userId,
            $ipAddress,
            ['resource' => $resource, 'action' => $action]
        );
    }
    
    /**
     * Log cross-company access attempt
     */
    public function logCrossCompanyAccess($userId, $ipAddress, $targetCompanyId, $action) {
        return $this->logEvent(
            self::EVENT_CROSS_COMPANY_ACCESS,
            self::SEVERITY_CRITICAL,
            $userId,
            $ipAddress,
            ['target_company_id' => $targetCompanyId, 'action' => $action]
        );
    }
    
    /**
     * Log suspicious activity
     */
    public function logSuspiciousActivity($userId, $ipAddress, $description, $details = []) {
        $details['description'] = $description;
        return $this->logEvent(
            self::EVENT_SUSPICIOUS_ACTIVITY,
            self::SEVERITY_CRITICAL,
            $userId,
            $ipAddress,
            $details
        );
    }
    
    /**
     * Get security events with filtering
     * 
     * @param array $filters Filter options
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return array Events
     */
    public function getEvents($filters = [], $limit = 100, $offset = 0) {
        try {
            $where = [];
            $params = [];
            $types = '';
            
            if (!empty($filters['event_type'])) {
                $where[] = 'event_type = ?';
                $params[] = $filters['event_type'];
                $types .= 's';
            }
            
            if (!empty($filters['severity'])) {
                $where[] = 'severity = ?';
                $params[] = $filters['severity'];
                $types .= 's';
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = ?';
                $params[] = $filters['user_id'];
                $types .= 'i';
            }
            
            if (!empty($filters['ip_address'])) {
                $where[] = 'ip_address = ?';
                $params[] = $filters['ip_address'];
                $types .= 's';
            }
            
            if (!empty($filters['from_date'])) {
                $where[] = 'created_at >= ?';
                $params[] = $filters['from_date'];
                $types .= 's';
            }
            
            if (!empty($filters['to_date'])) {
                $where[] = 'created_at <= ?';
                $params[] = $filters['to_date'];
                $types .= 's';
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT se.*, u.username, u.email 
                    FROM security_events se 
                    LEFT JOIN users u ON se.user_id = u.id 
                    {$whereClause} 
                    ORDER BY se.created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            
            return $this->db->getResults($sql, $params, $types);
            
        } catch (Exception $e) {
            error_log("Get security events error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get event count for statistics
     */
    public function getEventCount($filters = []) {
        try {
            $where = [];
            $params = [];
            $types = '';
            
            if (!empty($filters['event_type'])) {
                $where[] = 'event_type = ?';
                $params[] = $filters['event_type'];
                $types .= 's';
            }
            
            if (!empty($filters['severity'])) {
                $where[] = 'severity = ?';
                $params[] = $filters['severity'];
                $types .= 's';
            }
            
            if (!empty($filters['from_date'])) {
                $where[] = 'created_at >= ?';
                $params[] = $filters['from_date'];
                $types .= 's';
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT COUNT(*) as count FROM security_events {$whereClause}";
            
            $results = empty($params) 
                ? $this->db->getResults($sql) 
                : $this->db->getResults($sql, $params, $types);
            
            return $results[0]['count'] ?? 0;
            
        } catch (Exception $e) {
            error_log("Get event count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get security statistics
     */
    public function getStatistics($hours = 24) {
        try {
            $sql = "SELECT 
                        event_type,
                        severity,
                        COUNT(*) as count
                    FROM security_events 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                    GROUP BY event_type, severity
                    ORDER BY count DESC";
            
            $results = $this->db->getResults($sql, [$hours], 'i');
            
            $stats = [
                'total' => 0,
                'by_type' => [],
                'by_severity' => [
                    'INFO' => 0,
                    'WARNING' => 0,
                    'CRITICAL' => 0
                ]
            ];
            
            foreach ($results as $row) {
                $stats['total'] += $row['count'];
                $stats['by_type'][$row['event_type']] = ($stats['by_type'][$row['event_type']] ?? 0) + $row['count'];
                $stats['by_severity'][$row['severity']] += $row['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get security statistics error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clean up old events (retention policy)
     * 
     * @param int $daysToKeep Number of days to retain events
     * @return int Number of deleted records
     */
    public function cleanupOldEvents($daysToKeep = 90) {
        try {
            $sql = "DELETE FROM security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->executeQuery($sql, [$daysToKeep], 'i');
            $deleted = $stmt->affected_rows;
            $stmt->close();
            
            return $deleted;
            
        } catch (Exception $e) {
            error_log("Cleanup old events error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get all event types
     */
    public function getEventTypes() {
        return [
            self::EVENT_LOGIN_SUCCESS,
            self::EVENT_LOGIN_FAILED,
            self::EVENT_LOGOUT,
            self::EVENT_ACCOUNT_LOCKOUT,
            self::EVENT_ACCOUNT_UNLOCKED,
            self::EVENT_PASSWORD_CHANGED,
            self::EVENT_PASSWORD_RESET,
            self::EVENT_PERMISSION_GRANTED,
            self::EVENT_PERMISSION_REVOKED,
            self::EVENT_CROSS_COMPANY_ACCESS,
            self::EVENT_UNAUTHORIZED_ACCESS,
            self::EVENT_IP_BLOCKED,
            self::EVENT_IP_WHITELISTED,
            self::EVENT_SUSPICIOUS_ACTIVITY,
            self::EVENT_SESSION_HIJACK_ATTEMPT,
            self::EVENT_CSRF_VIOLATION,
            self::EVENT_SQL_INJECTION_ATTEMPT,
            self::EVENT_XSS_ATTEMPT,
            self::EVENT_2FA_ENABLED,
            self::EVENT_2FA_DISABLED,
            self::EVENT_USER_CREATED,
            self::EVENT_USER_UPDATED,
            self::EVENT_USER_DELETED
        ];
    }
}
