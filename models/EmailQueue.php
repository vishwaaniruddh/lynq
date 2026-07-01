<?php
/**
 * EmailQueue Model
 * Manages email queue with retry mechanisms
 */

require_once __DIR__ . '/BaseModel.php';

class EmailQueue extends BaseModel {
    protected $table = 'email_queue';
    protected $fillable = [
        'to_email', 'cc_email', 'bcc_email', 'subject', 'body_text', 'body_html',
        'template_id', 'trigger_id', 'priority', 'status', 'attempts', 'max_attempts',
        'error_message', 'scheduled_at', 'sent_at', 'company_id', 'created_by'
    ];
    
    // Priority levels
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    
    // Status values
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    
    /**
     * Create new email queue entry
     */
    public function create($data) {
        // Set default values
        $data['status'] = $data['status'] ?? self::STATUS_PENDING;
        $data['priority'] = $data['priority'] ?? self::PRIORITY_NORMAL;
        $data['attempts'] = $data['attempts'] ?? 0;
        $data['max_attempts'] = $data['max_attempts'] ?? 3;
        $data['scheduled_at'] = $data['scheduled_at'] ?? date('Y-m-d H:i:s');
        
        // Validate email data
        $this->validateEmailData($data);
        
        return parent::create($data);
    }
    
    /**
     * Get pending emails for processing
     */
    public function getPendingEmails($limit = 10) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `status` = ? AND `scheduled_at` <= NOW() AND `attempts` < `max_attempts`
                ORDER BY `priority` DESC, `scheduled_at` ASC 
                LIMIT ?";
        return DatabaseConfig::getInstance()->getResults($sql, [self::STATUS_PENDING, $limit], 'si');
    }
    
    /**
     * Get emails by status
     */
    public function getByStatus($companyId, $status, $limit = 50) {
        $sql = "SELECT eq.*, et.name as template_name, etr.name as trigger_name 
                FROM `{$this->table}` eq 
                LEFT JOIN `email_templates` et ON eq.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON eq.trigger_id = etr.id 
                WHERE eq.company_id = ? AND eq.status = ? 
                ORDER BY eq.created_at DESC 
                LIMIT ?";
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, $status, $limit], 'isi');
    }
    
    /**
     * Get queue statistics for company
     */
    public function getQueueStats($companyId) {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    AVG(attempts) as avg_attempts
                FROM `{$this->table}` 
                WHERE company_id = ? 
                GROUP BY status";
        $results = DatabaseConfig::getInstance()->getResults($sql, [$companyId], 'i');
        
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'total' => 0
        ];
        
        foreach ($results as $result) {
            $stats[$result['status']] = (int)$result['count'];
            $stats['total'] += (int)$result['count'];
        }
        
        return $stats;
    }
    
    /**
     * Mark email as processing
     */
    public function markAsProcessing($id) {
        $sql = "UPDATE `{$this->table}` SET `status` = ?, `attempts` = `attempts` + 1 WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [self::STATUS_PROCESSING, $id], 'si');
        $stmt->close();
        
        return $this->find($id);
    }
    
    /**
     * Mark email as sent
     */
    public function markAsSent($id, $deliveryStatus = null) {
        $sql = "UPDATE `{$this->table}` SET `status` = ?, `sent_at` = NOW() WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [self::STATUS_SENT, $id], 'si');
        $stmt->close();
        
        return $this->find($id);
    }
    
    /**
     * Mark email as failed
     */
    public function markAsFailed($id, $errorMessage = null) {
        $email = $this->find($id);
        if (!$email) {
            return null;
        }
        
        $newStatus = ($email['attempts'] >= $email['max_attempts']) ? self::STATUS_FAILED : self::STATUS_PENDING;
        
        $sql = "UPDATE `{$this->table}` SET `status` = ?, `error_message` = ? WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$newStatus, $errorMessage, $id], 'ssi');
        $stmt->close();
        
        return $this->find($id);
    }
    
    /**
     * Retry failed email
     */
    public function retryEmail($id) {
        $email = $this->find($id);
        if (!$email) {
            throw new InvalidArgumentException("Email not found");
        }
        
        if ($email['status'] !== self::STATUS_FAILED) {
            throw new InvalidArgumentException("Only failed emails can be retried");
        }
        
        $sql = "UPDATE `{$this->table}` SET 
                    `status` = ?, 
                    `attempts` = 0, 
                    `error_message` = NULL, 
                    `scheduled_at` = NOW() 
                WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [self::STATUS_PENDING, $id], 'si');
        $stmt->close();
        
        return $this->find($id);
    }
    
    /**
     * Schedule email for later delivery
     */
    public function scheduleEmail($id, $scheduledAt) {
        $sql = "UPDATE `{$this->table}` SET `scheduled_at` = ? WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$scheduledAt, $id], 'si');
        $stmt->close();
        
        return $this->find($id);
    }
    
    /**
     * Get retry schedule for failed email
     */
    public function getRetrySchedule($attempts) {
        // Exponential backoff: 1min, 5min, 15min, 30min, 1hr, 2hr, 4hr, 8hr
        $delays = [60, 300, 900, 1800, 3600, 7200, 14400, 28800];
        $delayIndex = min($attempts, count($delays) - 1);
        
        return date('Y-m-d H:i:s', time() + $delays[$delayIndex]);
    }
    
    /**
     * Clean up old processed emails
     */
    public function cleanupOldEmails($daysOld = 30) {
        $sql = "DELETE FROM `{$this->table}` 
                WHERE `status` IN (?, ?) AND `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [self::STATUS_SENT, self::STATUS_FAILED, $daysOld], 'ssi');
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        
        return $deletedCount;
    }
    
    /**
     * Get email delivery metrics
     */
    public function getDeliveryMetrics($companyId, $days = 7) {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    status,
                    COUNT(*) as count,
                    AVG(attempts) as avg_attempts
                FROM `{$this->table}` 
                WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), status 
                ORDER BY date DESC";
        
        return DatabaseConfig::getInstance()->getResults($sql, [$companyId, $days], 'ii');
    }
    
    /**
     * Validate email data before queuing
     */
    private function validateEmailData($data) {
        $errors = [];
        
        // Validate required fields
        if (empty($data['to_email'])) {
            $errors[] = "Recipient email is required";
        }
        
        if (empty($data['subject'])) {
            $errors[] = "Email subject is required";
        }
        
        if (empty($data['body_text']) && empty($data['body_html'])) {
            $errors[] = "Email body (text or HTML) is required";
        }
        
        // Validate email addresses
        $emailFields = ['to_email', 'cc_email', 'bcc_email'];
        foreach ($emailFields as $field) {
            if (!empty($data[$field])) {
                $emails = is_array($data[$field]) ? $data[$field] : explode(',', $data[$field]);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Invalid email address in $field: $email";
                    }
                }
            }
        }
        
        // Validate priority
        if (isset($data['priority']) && !in_array($data['priority'], [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH])) {
            $errors[] = "Invalid priority value";
        }
        
        // Validate status
        if (isset($data['status']) && !in_array($data['status'], [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_SENT, self::STATUS_FAILED])) {
            $errors[] = "Invalid status value";
        }
        
        // Validate max_attempts
        if (isset($data['max_attempts']) && (!is_numeric($data['max_attempts']) || $data['max_attempts'] < 1 || $data['max_attempts'] > 10)) {
            $errors[] = "Max attempts must be between 1 and 10";
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException("Email validation failed: " . implode(', ', $errors));
        }
    }
}