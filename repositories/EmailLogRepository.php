<?php
/**
 * EmailLog Repository with Company Isolation
 * Provides company-aware email log data access
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/EmailLog.php';

class EmailLogRepository extends BaseRepository {
    protected $table = 'email_logs';
    protected $companyIdColumn = 'company_id';
    
    private $emailLogModel;
    
    public function __construct() {
        parent::__construct();
        $this->emailLogModel = new EmailLog();
    }
    
    /**
     * Create email log entry with validation
     */
    public function create($data) {
        // Validate company access if user is set
        if ($this->currentUserId && isset($data[$this->companyIdColumn])) {
            $this->companyIsolationService->validateCompanyAccess(
                $this->currentUserId, 
                $data[$this->companyIdColumn]
            );
        }
        
        // Use model's create method for validation
        return $this->emailLogModel->create($data);
    }
    
    /**
     * Log successful email delivery
     */
    public function logSuccess($queueId, $toEmail, $subject, $deliveryStatus = null, $additionalData = []) {
        return $this->emailLogModel->logSuccess($queueId, $toEmail, $subject, $deliveryStatus, $additionalData);
    }
    
    /**
     * Log failed email delivery
     */
    public function logFailure($queueId, $toEmail, $subject, $errorMessage, $additionalData = []) {
        return $this->emailLogModel->logFailure($queueId, $toEmail, $subject, $errorMessage, $additionalData);
    }
    
    /**
     * Log bounced email
     */
    public function logBounce($queueId, $toEmail, $subject, $bounceReason, $additionalData = []) {
        return $this->emailLogModel->logBounce($queueId, $toEmail, $subject, $bounceReason, $additionalData);
    }
    
    /**
     * Get email logs for company with filtering
     */
    public function getByCompany($companyId, $filters = [], $limit = 50, $offset = 0) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailLogModel->getByCompany($companyId, $filters, $limit, $offset);
    }
    
    /**
     * Get email delivery statistics
     */
    public function getDeliveryStats($companyId, $days = 30) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailLogModel->getDeliveryStats($companyId, $days);
    }
    
    /**
     * Get daily email volume
     */
    public function getDailyVolume($companyId, $days = 30) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailLogModel->getDailyVolume($companyId, $days);
    }
    
    /**
     * Get top email templates by usage
     */
    public function getTopTemplates($companyId, $days = 30, $limit = 10) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailLogModel->getTopTemplates($companyId, $days, $limit);
    }
    
    /**
     * Get failed emails with error analysis
     */
    public function getFailureAnalysis($companyId, $days = 7) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailLogModel->getFailureAnalysis($companyId, $days);
    }
    
    /**
     * Get email logs for specific queue item
     */
    public function getByQueueId($queueId) {
        return $this->emailLogModel->getByQueueId($queueId);
    }
    
    /**
     * Get email logs for specific template
     */
    public function getByTemplate($templateId, $limit = 50) {
        return $this->emailLogModel->getByTemplate($templateId, $limit);
    }
    
    /**
     * Get email logs for specific trigger
     */
    public function getByTrigger($triggerId, $limit = 50) {
        return $this->emailLogModel->getByTrigger($triggerId, $limit);
    }
    
    /**
     * Search email logs with company isolation
     */
    public function search($searchTerm, $companyId = null, $limit = 50) {
        if ($companyId) {
            // Validate company access if user is set
            if ($this->currentUserId) {
                $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
            }
            return $this->emailLogModel->search($companyId, $searchTerm, $limit);
        }
        
        // If no specific company, use company isolation
        $sql = "SELECT el.*, et.name as template_name, etr.name as trigger_name 
                FROM `{$this->table}` el 
                LEFT JOIN `email_templates` et ON el.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON el.trigger_id = etr.id 
                WHERE (el.to_email LIKE ? OR el.subject LIKE ? OR el.error_message LIKE ?)";
        
        $searchPattern = "%$searchTerm%";
        $params = [$searchPattern, $searchPattern, $searchPattern];
        $types = 'sss';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'el.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY el.sent_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get logs by status with company isolation
     */
    public function getByStatus($status, $companyId = null, $limit = 50) {
        $sql = "SELECT el.*, et.name as template_name, etr.name as trigger_name, u.name as user_name 
                FROM `{$this->table}` el 
                LEFT JOIN `email_templates` et ON el.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON el.trigger_id = etr.id 
                LEFT JOIN `users` u ON el.user_id = u.id 
                WHERE el.status = ?";
        $params = [$status];
        $types = 's';
        
        // Add company filter
        if ($companyId) {
            // Validate company access if user is set
            if ($this->currentUserId) {
                $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
            }
            $sql .= " AND el.company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        } elseif ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'el.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY el.sent_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get logs by date range with company isolation
     */
    public function getByDateRange($startDate, $endDate, $companyId = null, $limit = 100) {
        $sql = "SELECT el.*, et.name as template_name, etr.name as trigger_name 
                FROM `{$this->table}` el 
                LEFT JOIN `email_templates` et ON el.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON el.trigger_id = etr.id 
                WHERE el.sent_at >= ? AND el.sent_at <= ?";
        $params = [$startDate, $endDate];
        $types = 'ss';
        
        // Add company filter
        if ($companyId) {
            // Validate company access if user is set
            if ($this->currentUserId) {
                $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
            }
            $sql .= " AND el.company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        } elseif ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'el.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY el.sent_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Clean up old email logs (admin only)
     */
    public function cleanupOldLogs($daysOld = 90) {
        // This operation should only be performed by system administrators
        // Disable company filter for cleanup
        $this->disableCompanyFilter();
        $result = $this->emailLogModel->cleanupOldLogs($daysOld);
        $this->enableCompanyFilter();
        
        return $result;
    }
    
    /**
     * Get email activity summary for company
     */
    public function getActivitySummary($companyId, $days = 7) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT 
                    DATE(sent_at) as date,
                    COUNT(*) as total_emails,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                    COUNT(DISTINCT template_id) as templates_used,
                    COUNT(DISTINCT trigger_id) as triggers_used
                FROM `{$this->table}` 
                WHERE company_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(sent_at) 
                ORDER BY date DESC";
        
        return $this->db->getResults($sql, [$companyId, $days], 'ii');
    }
}