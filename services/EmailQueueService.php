<?php
/**
 * Email Queue Service
 * Handles business logic for email queue management and processing
 * Provides priority processing, retry logic with exponential backoff, and delivery status tracking
 */

require_once __DIR__ . '/../config/autoload.php';

class EmailQueueService {
    private $db;
    private $emailQueueRepository;
    private $emailLogRepository;
    private $emailService;
    
    // Processing limits
    const MAX_PROCESSING_TIME = 300; // 5 minutes
    const BATCH_SIZE = 10;
    const MAX_RETRY_ATTEMPTS = 5;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->emailQueueRepository = new EmailQueueRepository();
        $this->emailLogRepository = new EmailLogRepository();
        $this->emailService = new EmailService();
    }
    
    /**
     * Queue email for sending
     * 
     * @param array $emailData Email data to queue
     * @param int|null $actingUserId ID of user performing the action
     * @return array Queued email data
     * @throws Exception on validation failure
     */
    public function queueEmail(array $emailData, ?int $actingUserId = null): array {
        // Validate required fields
        $this->validateRequiredFields($emailData, ['to_email', 'subject', 'company_id']);
        
        // Ensure at least one body is provided
        if (empty($emailData['body_text']) && empty($emailData['body_html'])) {
            throw new InvalidArgumentException('At least one of body_text or body_html must be provided');
        }
        
        // Set user context if provided
        if ($actingUserId) {
            $this->emailQueueRepository->setCurrentUser($actingUserId);
        }
        
        // Set default values
        $emailData['status'] = EmailQueue::STATUS_PENDING;
        $emailData['priority'] = $emailData['priority'] ?? EmailQueue::PRIORITY_NORMAL;
        $emailData['attempts'] = 0;
        $emailData['max_attempts'] = $emailData['max_attempts'] ?? self::MAX_RETRY_ATTEMPTS;
        $emailData['scheduled_at'] = $emailData['scheduled_at'] ?? date('Y-m-d H:i:s');
        $emailData['created_by'] = $actingUserId;
        
        // Create queue entry
        return $this->emailQueueRepository->create($emailData);
    }
    
    /**
     * Process email queue with priority handling
     * 
     * @param int $batchSize Number of emails to process in this batch
     * @return array Processing result
     */
    public function processQueue(int $batchSize = self::BATCH_SIZE): array {
        $result = [
            'success' => true,
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        $startTime = time();
        
        try {
            // Get pending emails ordered by priority and schedule
            $pendingEmails = $this->emailQueueRepository->getPendingEmails($batchSize);
            
            foreach ($pendingEmails as $email) {
                // Check processing time limit
                if (time() - $startTime > self::MAX_PROCESSING_TIME) {
                    break;
                }
                
                try {
                    $this->processEmail($email);
                    $result['processed']++;
                    $result['sent']++;
                    
                } catch (Exception $e) {
                    $result['processed']++;
                    $result['failed']++;
                    $result['errors'][] = [
                        'email_id' => $email['id'],
                        'to_email' => $email['to_email'],
                        'error' => $e->getMessage()
                    ];
                }
            }
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = [
                'general_error' => $e->getMessage()
            ];
        }
        
        return $result;
    }
    
    /**
     * Process individual email
     * 
     * @param array $email Email data from queue
     * @throws Exception on processing failure
     */
    private function processEmail(array $email): void {
        // Mark email as processing
        $this->emailQueueRepository->markAsProcessing($email['id']);
        
        try {
            // Prepare email data for sending
            $emailData = [
                'to' => $email['to_email'],
                'cc' => $email['cc_email'],
                'bcc' => $email['bcc_email'],
                'subject' => $email['subject'],
                'body_text' => $email['body_text'],
                'body_html' => $email['body_html'],
                'template_id' => $email['template_id'],
                'trigger_id' => $email['trigger_id'],
                'company_id' => $email['company_id'],
                'user_id' => $email['created_by']
            ];
            
            // Send email using EmailService
            $sendResult = $this->emailService->sendEmail($emailData, null, $email['company_id']);
            
            if ($sendResult['success']) {
                // Mark as sent
                $this->emailQueueRepository->markAsSent($email['id']);
                
                // Log successful delivery
                $this->logEmailDelivery($email, 'sent', null);
                
            } else {
                // Handle send failure
                $this->handleEmailFailure($email, $sendResult['message']);
            }
            
        } catch (Exception $e) {
            // Handle processing exception
            $this->handleEmailFailure($email, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle email sending failure with retry logic
     * 
     * @param array $email Email data
     * @param string $errorMessage Error message
     */
    private function handleEmailFailure(array $email, string $errorMessage): void {
        // Mark as failed (will determine if retry is needed)
        $updatedEmail = $this->emailQueueRepository->markAsFailed($email['id'], $errorMessage);
        
        // Log failed delivery
        $this->logEmailDelivery($email, 'failed', $errorMessage);
        
        // If email can be retried, schedule it with exponential backoff
        if ($updatedEmail['status'] === EmailQueue::STATUS_PENDING) {
            $retryTime = $this->emailQueueRepository->getRetrySchedule($updatedEmail['attempts']);
            $this->emailQueueRepository->scheduleEmail($email['id'], $retryTime);
        }
    }
    
    /**
     * Retry failed email
     * 
     * @param int $emailId Email ID to retry
     * @param int $actingUserId ID of user performing the action
     * @return array Updated email data
     * @throws Exception on validation failure
     */
    public function retryEmail(int $emailId, int $actingUserId): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        return $this->emailQueueRepository->retryEmail($emailId);
    }
    
    /**
     * Schedule email for later delivery
     * 
     * @param int $emailId Email ID to schedule
     * @param string $scheduledAt Scheduled delivery time
     * @param int $actingUserId ID of user performing the action
     * @return array Updated email data
     * @throws Exception on validation failure
     */
    public function scheduleEmail(int $emailId, string $scheduledAt, int $actingUserId): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        
        // Validate scheduled time
        if (strtotime($scheduledAt) <= time()) {
            throw new InvalidArgumentException('Scheduled time must be in the future');
        }
        
        return $this->emailQueueRepository->scheduleEmail($emailId, $scheduledAt);
    }
    
    /**
     * Get queue statistics for company
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @return array Queue statistics
     */
    public function getQueueStats(int $companyId, int $actingUserId): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        return $this->emailQueueRepository->getQueueStats($companyId);
    }
    
    /**
     * Get emails by status with company isolation
     * 
     * @param int $companyId Company ID
     * @param string $status Email status
     * @param int $actingUserId ID of user performing the action
     * @param int $limit Maximum results
     * @return array List of emails
     */
    public function getEmailsByStatus(int $companyId, string $status, int $actingUserId, int $limit = 50): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        return $this->emailQueueRepository->getByStatus($companyId, $status, $limit);
    }
    
    /**
     * Get emails by priority with company isolation
     * 
     * @param string $priority Email priority
     * @param int $actingUserId ID of user performing the action
     * @param int|null $companyId Optional company filter
     * @param int $limit Maximum results
     * @return array List of emails
     */
    public function getEmailsByPriority(string $priority, int $actingUserId, ?int $companyId = null, int $limit = 50): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        return $this->emailQueueRepository->getByPriority($priority, $companyId, $limit);
    }
    
    /**
     * Search emails in queue with company isolation
     * 
     * @param string $searchTerm Search term
     * @param int $actingUserId ID of user performing the action
     * @param int|null $companyId Optional company filter
     * @param int $limit Maximum results
     * @return array List of matching emails
     */
    public function searchEmails(string $searchTerm, int $actingUserId, ?int $companyId = null, int $limit = 50): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        return $this->emailQueueRepository->search($searchTerm, $companyId, $limit);
    }
    
    /**
     * Get delivery metrics for company
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @param int $days Number of days to include in metrics
     * @return array Delivery metrics
     */
    public function getDeliveryMetrics(int $companyId, int $actingUserId, int $days = 7): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        return $this->emailQueueRepository->getDeliveryMetrics($companyId, $days);
    }
    
    /**
     * Clean up old processed emails (admin only)
     * 
     * @param int $daysOld Age threshold in days
     * @return int Number of emails deleted
     */
    public function cleanupOldEmails(int $daysOld = 30): int {
        return $this->emailQueueRepository->cleanupOldEmails($daysOld);
    }
    
    /**
     * Get email by ID with company isolation
     * 
     * @param int $emailId Email ID
     * @param int $actingUserId ID of user performing the action
     * @return array|null Email data or null if not found/not accessible
     */
    public function getEmail(int $emailId, int $actingUserId): ?array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        return $this->emailQueueRepository->find($emailId);
    }
    
    /**
     * Cancel pending email
     * 
     * @param int $emailId Email ID to cancel
     * @param int $actingUserId ID of user performing the action
     * @return bool Success status
     * @throws Exception on validation failure
     */
    public function cancelEmail(int $emailId, int $actingUserId): bool {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        
        $email = $this->emailQueueRepository->find($emailId);
        if (!$email) {
            throw new InvalidArgumentException('Email not found');
        }
        
        if ($email['status'] !== EmailQueue::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending emails can be cancelled');
        }
        
        return $this->emailQueueRepository->delete($emailId);
    }
    
    /**
     * Update email priority
     * 
     * @param int $emailId Email ID
     * @param string $priority New priority
     * @param int $actingUserId ID of user performing the action
     * @return array Updated email data
     * @throws Exception on validation failure
     */
    public function updateEmailPriority(int $emailId, string $priority, int $actingUserId): array {
        $this->emailQueueRepository->setCurrentUser($actingUserId);
        
        $email = $this->emailQueueRepository->find($emailId);
        if (!$email) {
            throw new InvalidArgumentException('Email not found');
        }
        
        if ($email['status'] !== EmailQueue::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending emails can have priority updated');
        }
        
        $validPriorities = [EmailQueue::PRIORITY_LOW, EmailQueue::PRIORITY_NORMAL, EmailQueue::PRIORITY_HIGH];
        if (!in_array($priority, $validPriorities)) {
            throw new InvalidArgumentException('Invalid priority value');
        }
        
        return $this->emailQueueRepository->update($emailId, ['priority' => $priority]);
    }
    
    /**
     * Get queue processing status
     * 
     * @return array Processing status information
     */
    public function getProcessingStatus(): array {
        // Get counts by status across all companies
        $sql = "SELECT 
                    status,
                    priority,
                    COUNT(*) as count,
                    MIN(scheduled_at) as earliest_scheduled,
                    MAX(scheduled_at) as latest_scheduled
                FROM email_queue 
                WHERE status IN (?, ?, ?)
                GROUP BY status, priority 
                ORDER BY status, priority";
        
        $results = $this->db->getResults($sql, [
            EmailQueue::STATUS_PENDING,
            EmailQueue::STATUS_PROCESSING,
            EmailQueue::STATUS_FAILED
        ], 'sss');
        
        $status = [
            'pending' => ['total' => 0, 'by_priority' => []],
            'processing' => ['total' => 0, 'by_priority' => []],
            'failed' => ['total' => 0, 'by_priority' => []],
            'earliest_scheduled' => null,
            'latest_scheduled' => null
        ];
        
        foreach ($results as $result) {
            $statusKey = $result['status'];
            $priority = $result['priority'];
            $count = (int)$result['count'];
            
            $status[$statusKey]['total'] += $count;
            $status[$statusKey]['by_priority'][$priority] = $count;
            
            if (!$status['earliest_scheduled'] || $result['earliest_scheduled'] < $status['earliest_scheduled']) {
                $status['earliest_scheduled'] = $result['earliest_scheduled'];
            }
            
            if (!$status['latest_scheduled'] || $result['latest_scheduled'] > $status['latest_scheduled']) {
                $status['latest_scheduled'] = $result['latest_scheduled'];
            }
        }
        
        return $status;
    }
    
    /**
     * Log email delivery for audit trail
     * 
     * @param array $email Email data
     * @param string $status Delivery status
     * @param string|null $errorMessage Error message if failed
     */
    private function logEmailDelivery(array $email, string $status, ?string $errorMessage = null): void {
        try {
            $logData = [
                'queue_id' => $email['id'],
                'to_email' => $email['to_email'],
                'subject' => $email['subject'],
                'status' => $status,
                'error_message' => $errorMessage,
                'template_id' => $email['template_id'],
                'trigger_id' => $email['trigger_id'],
                'company_id' => $email['company_id'],
                'user_id' => $email['created_by'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            $this->emailLogRepository->create($logData);
            
        } catch (Exception $e) {
            error_log("Failed to log email delivery: " . $e->getMessage());
        }
    }
    
    /**
     * Validate required fields
     * 
     * @param array $data Data to validate
     * @param array $requiredFields List of required field names
     * @throws InvalidArgumentException if required field is missing
     */
    private function validateRequiredFields(array $data, array $requiredFields): void {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                throw new InvalidArgumentException("$field is required");
            }
        }
    }
}