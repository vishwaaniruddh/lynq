<?php
/**
 * EmailQueue Repository with Company Isolation
 * Provides company-aware email queue data access
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/EmailQueue.php';

class EmailQueueRepository extends BaseRepository {
    protected $table = 'email_queue';
    protected $companyIdColumn = 'company_id';
    
    private $emailQueueModel;
    
    public function __construct() {
        parent::__construct();
        $this->emailQueueModel = new EmailQueue();
    }
    
    /**
     * Create email queue entry with validation
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
        return $this->emailQueueModel->create($data);
    }
    
    /**
     * Get pending emails for processing (no company filter for processing)
     */
    public function getPendingEmails($limit = 10) {
        // Disable company filter for queue processing
        $this->disableCompanyFilter();
        $result = $this->emailQueueModel->getPendingEmails($limit);
        $this->enableCompanyFilter();
        
        return $result;
    }
    
    /**
     * Get emails by status with company isolation
     */
    public function getByStatus($companyId, $status, $limit = 50) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailQueueModel->getByStatus($companyId, $status, $limit);
    }
    
    /**
     * Get queue statistics for company
     */
    public function getQueueStats($companyId) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailQueueModel->getQueueStats($companyId);
    }
    
    /**
     * Mark email as processing
     */
    public function markAsProcessing($id) {
        // No company filter needed for processing operations
        $this->disableCompanyFilter();
        $result = $this->emailQueueModel->markAsProcessing($id);
        $this->enableCompanyFilter();
        
        return $result;
    }
    
    /**
     * Mark email as sent
     */
    public function markAsSent($id, $deliveryStatus = null) {
        // No company filter needed for processing operations
        $this->disableCompanyFilter();
        $result = $this->emailQueueModel->markAsSent($id, $deliveryStatus);
        $this->enableCompanyFilter();
        
        return $result;
    }
    
    /**
     * Mark email as failed
     */
    public function markAsFailed($id, $errorMessage = null) {
        // No company filter needed for processing operations
        $this->disableCompanyFilter();
        $result = $this->emailQueueModel->markAsFailed($id, $errorMessage);
        $this->enableCompanyFilter();
        
        return $result;
    }
    
    /**
     * Retry failed email
     */
    public function retryEmail($id) {
        $email = $this->find($id);
        if (!$email) {
            throw new Exception("Email not found or access denied");
        }
        
        // Disable company filter for retry operation
        $this->disableCompanyFilter();
        $result = $this->emailQueueModel->retryEmail($id);
        $this->enableCompanyFilter();
        
        return $result;
    }
    
    /**
     * Schedule email for later delivery
     */
    public function scheduleEmail($id, $scheduledAt) {
        $email = $this->find($id);
        if (!$email) {
            throw new Exception("Email not found or access denied");
        }
        
        return $this->emailQueueModel->scheduleEmail($id, $scheduledAt);
    }
    
    /**
     * Get delivery metrics for company
     */
    public function getDeliveryMetrics($companyId, $days = 7) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailQueueModel->getDeliveryMetrics($companyId, $days);
    }
    
    /**
     * Clean up old processed emails (admin only)
     */
    public function cleanupOldEmails($daysOld = 30) {
        // This operation should only be performed by system administrators
        // Disable company filter for cleanup
        $this->disableCompanyFilter();
        $result = $this->emailQueueModel->cleanupOldEmails($daysOld);
        $this->enableCompanyFilter();
        
        return $result;
    }
    
    /**
     * Get emails by priority with company isolation
     */
    public function getByPriority($priority, $companyId = null, $limit = 50) {
        $sql = "SELECT eq.*, et.name as template_name, etr.name as trigger_name 
                FROM `{$this->table}` eq 
                LEFT JOIN `email_templates` et ON eq.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON eq.trigger_id = etr.id 
                WHERE eq.priority = ?";
        $params = [$priority];
        $types = 's';
        
        // Add company filter
        if ($companyId) {
            // Validate company access if user is set
            if ($this->currentUserId) {
                $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
            }
            $sql .= " AND eq.company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        } elseif ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'eq.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY eq.scheduled_at ASC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Search emails in queue with company isolation
     */
    public function search($searchTerm, $companyId = null, $limit = 50) {
        $sql = "SELECT eq.*, et.name as template_name, etr.name as trigger_name 
                FROM `{$this->table}` eq 
                LEFT JOIN `email_templates` et ON eq.template_id = et.id 
                LEFT JOIN `email_triggers` etr ON eq.trigger_id = etr.id 
                WHERE (eq.to_email LIKE ? OR eq.subject LIKE ? OR eq.error_message LIKE ?)";
        
        $searchPattern = "%$searchTerm%";
        $params = [$searchPattern, $searchPattern, $searchPattern];
        $types = 'sss';
        
        // Add company filter
        if ($companyId) {
            // Validate company access if user is set
            if ($this->currentUserId) {
                $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
            }
            $sql .= " AND eq.company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        } elseif ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'eq.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY eq.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get emails by template with company isolation
     */
    public function getByTemplate($templateId, $limit = 50) {
        $sql = "SELECT eq.*, et.name as template_name 
                FROM `{$this->table}` eq 
                LEFT JOIN `email_templates` et ON eq.template_id = et.id 
                WHERE eq.template_id = ?";
        $params = [$templateId];
        $types = 'i';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'eq.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY eq.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get emails by trigger with company isolation
     */
    public function getByTrigger($triggerId, $limit = 50) {
        $sql = "SELECT eq.*, etr.name as trigger_name 
                FROM `{$this->table}` eq 
                LEFT JOIN `email_triggers` etr ON eq.trigger_id = etr.id 
                WHERE eq.trigger_id = ?";
        $params = [$triggerId];
        $types = 'i';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                'eq.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY eq.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get retry schedule for failed email
     */
    public function getRetrySchedule($attempts) {
        return $this->emailQueueModel->getRetrySchedule($attempts);
    }
}