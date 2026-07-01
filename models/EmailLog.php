<?php
/**
 * EmailLog Model
 * Manages comprehensive email activity logging
 */

require_once __DIR__ . '/BaseModel.php';

class EmailLog extends BaseModel {
    protected $table = 'email_logs';
    protected $fillable = [
        'queue_id', 'to_email', 'subject', 'status', 'delivery_status',
        'error_message', 'template_id', 'trigger_id', 'company_id',
        'user_id', 'ip_address', 'user_agent', 'sent_at'
    ];
    
    // Status values
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    
    /**
     * Create new email log entry
     */
    public function create($data) {
        // Set default values
        $data['sent_at'] = $data['sent_at'] ?? date('Y-m-d H:i:s');
        
        // Validate log data
        $this->validateLogData($data);
        
        return parent::create($data);
    }
    
    /**
     * Log successful email delivery
     */
    public function logSuccess($queueId, $toEmail, $subject, $deliveryStatus = null, $additionalData = []) {
        $logData = array_merge([
            'queue_id' => $queueId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'status' => self::STATUS_SENT,
            'delivery_status' => $deliveryStatus
        ], $additionalData);
        
        return $this->create($logData);
    }
    
    /**
     * Log failed email delivery
     */
    public function logFailure($queueId, $toEmail, $subject, $errorMessage, $additionalData = []) {
        $logData = array_merge([
            'queue_id' => $queueId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage
        ], $additionalData);
        
        return $this->create($logData);
    }
    
    /**
     * Log bounced email
     */
    public function logBounce($queueId, $toEmail, $subject, $bounceReason, $additionalData = []) {
        $logData = array_merge([
            'queue_id' => $queueId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'status' => self::STATUS_BOUNCED,
            'error_message' => $bounceReason
        ], $additionalData);
        
        return $this->create($logData);
    }
    
    /**
     * Get email logs for company with filtering
     */
    public function getByCompany($companyId, $filters = [], $limit = 50, $offset = 0) {
        $sql = "SELECT el.*, et.name as template_name, etr.name as trigger_name, u.name as user_name 
                FROM `{$this->table}` el 
                LEFT JOIN `email_templates` et ON el.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON el.trigger_id = etr.id 
                LEFT JOIN `users` u ON el.user_id = u.id 
                WHERE el.company_id = ?";
        $params = [$companyId];
        $types = 'i';
        
        // Apply filters
        if (isset($filters['status'])) {
            $sql .= " AND el.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (isset($filters['template_id'])) {
            $sql .= " AND el.template_id = ?";
            $params[] = $filters['template_id'];
            $types .= 'i';
        }
        
        if (isset($filters['trigger_id'])) {
            $sql .= " AND el.trigger_id = ?";
            $params[] = $filters['trigger_id'];
            $types .= 'i';
        }
        
        if (isset($filters['to_email'])) {
            $sql .= " AND el.to_email LIKE ?";
            $params[] = '%' . $filters['to_email'] . '%';
            $types .= 's';
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND el.sent_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND el.sent_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY el.sent_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Get email delivery statistics
     */
    public function getDeliveryStats($companyId, $days = 30) {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    COUNT(*) * 100.0 / (SELECT COUNT(*) FROM `{$this->table}` WHERE company_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) as percentage
                FROM `{$this->table}` 
                WHERE company_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY status";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, $days, $companyId, $days], 'iiii');
    }
    
    /**
     * Get daily email volume
     */
    public function getDailyVolume($companyId, $days = 30) {
        $sql = "SELECT 
                    DATE(sent_at) as date,
                    status,
                    COUNT(*) as count
                FROM `{$this->table}` 
                WHERE company_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(sent_at), status 
                ORDER BY date DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, $days], 'ii');
    }
    
    /**
     * Get top email templates by usage
     */
    public function getTopTemplates($companyId, $days = 30, $limit = 10) {
        $sql = "SELECT 
                    el.template_id,
                    et.name as template_name,
                    et.module_name,
                    et.event_type,
                    COUNT(*) as usage_count,
                    SUM(CASE WHEN el.status = 'sent' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN el.status = 'failed' THEN 1 ELSE 0 END) as failure_count
                FROM `{$this->table}` el 
                JOIN `email_templates` et ON el.template_id = et.id 
                WHERE el.company_id = ? AND el.sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY el.template_id, et.name, et.module_name, et.event_type 
                ORDER BY usage_count DESC 
                LIMIT ?";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, $days, $limit], 'iii');
    }
    
    /**
     * Get failed emails with error analysis
     */
    public function getFailureAnalysis($companyId, $days = 7) {
        $sql = "SELECT 
                    error_message,
                    COUNT(*) as count,
                    GROUP_CONCAT(DISTINCT to_email SEPARATOR ', ') as affected_emails
                FROM `{$this->table}` 
                WHERE company_id = ? AND status = 'failed' AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY error_message 
                ORDER BY count DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, $days], 'ii');
    }
    
    /**
     * Get email logs for specific queue item
     */
    public function getByQueueId($queueId) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `queue_id` = ? ORDER BY `sent_at` DESC";
        return DatabaseConfig::getInstance()->getResults($sql, [$queueId], 'i');
    }
    
    /**
     * Get email logs for specific template
     */
    public function getByTemplate($templateId, $limit = 50) {
        $sql = "SELECT el.*, u.name as user_name 
                FROM `{$this->table}` el 
                LEFT JOIN `users` u ON el.user_id = u.id 
                WHERE el.template_id = ? 
                ORDER BY el.sent_at DESC 
                LIMIT ?";
        return DatabaseConfig::getInstance()->getResults($sql, [$templateId, $limit], 'ii');
    }
    
    /**
     * Get email logs for specific trigger
     */
    public function getByTrigger($triggerId, $limit = 50) {
        $sql = "SELECT el.*, u.name as user_name 
                FROM `{$this->table}` el 
                LEFT JOIN `users` u ON el.user_id = u.id 
                WHERE el.trigger_id = ? 
                ORDER BY el.sent_at DESC 
                LIMIT ?";
        return DatabaseConfig::getInstance()->getResults($sql, [$triggerId, $limit], 'ii');
    }
    
    /**
     * Search email logs
     */
    public function search($companyId, $searchTerm, $limit = 50) {
        $sql = "SELECT el.*, et.name as template_name, etr.name as trigger_name 
                FROM `{$this->table}` el 
                LEFT JOIN `email_templates` et ON el.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON el.trigger_id = etr.id 
                WHERE el.company_id = ? AND (
                    el.to_email LIKE ? OR 
                    el.subject LIKE ? OR 
                    el.error_message LIKE ? OR
                    et.name LIKE ? OR
                    etr.name LIKE ?
                )
                ORDER BY el.sent_at DESC 
                LIMIT ?";
        
        $searchPattern = '%' . $searchTerm . '%';
        $params = [$companyId, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $limit];
        $types = 'isssssi';
        
        return DatabaseConfig::getInstance()->getResults($sql, $params, $types);
    }
    
    /**
     * Clean up old email logs
     */
    public function cleanupOldLogs($daysOld = 90) {
        $sql = "DELETE FROM `{$this->table}` WHERE `sent_at` < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$daysOld], 'i');
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        
        return $deletedCount;
    }
    
    /**
     * Validate log data
     */
    private function validateLogData($data) {
        $errors = [];
        
        // Validate required fields
        if (empty($data['to_email'])) {
            $errors[] = "Recipient email is required";
        }
        
        if (empty($data['subject'])) {
            $errors[] = "Email subject is required";
        }
        
        if (empty($data['status'])) {
            $errors[] = "Status is required";
        }
        
        // Validate email address
        if (!empty($data['to_email']) && !filter_var($data['to_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid recipient email address";
        }
        
        // Validate status
        if (isset($data['status']) && !in_array($data['status'], [self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_BOUNCED])) {
            $errors[] = "Invalid status value";
        }
        
        // Validate IP address if provided
        if (!empty($data['ip_address']) && !filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
            $errors[] = "Invalid IP address";
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException("Email log validation failed: " . implode(', ', $errors));
        }
    }
}