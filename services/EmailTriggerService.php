<?php
/**
 * Email Trigger Service
 * Handles business logic for email trigger management and event processing
 * Provides trigger CRUD, event listener registration, condition evaluation, and recipient management
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/EmailTriggerConditionEngine.php';

class EmailTriggerService {
    private $db;
    private $emailTriggerRepository;
    private $emailTemplateRepository;
    private $emailQueueRepository;
    private $placeholderService;
    private $conditionEngine;
    
    // Event listeners registry
    private static $eventListeners = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->emailTriggerRepository = new EmailTriggerRepository();
        $this->emailTemplateRepository = new EmailTemplateRepository();
        $this->emailQueueRepository = new EmailQueueRepository();
        $this->placeholderService = new PlaceholderService();
        $this->conditionEngine = new EmailTriggerConditionEngine();
    }
    
    /**
     * Create a new email trigger with validation
     * 
     * @param array $triggerData Trigger data to create
     * @param int $actingUserId ID of user performing the action
     * @return array Created trigger data
     * @throws Exception on validation failure
     */
    public function createTrigger(array $triggerData, int $actingUserId): array {
        // Validate required fields
        $this->validateRequiredFields($triggerData, ['name', 'module_name', 'event_type', 'template_id', 'recipient_rules', 'company_id']);
        
        // Validate template exists and is accessible
        $this->validateTemplate($triggerData['template_id'], $actingUserId);
        
        // Validate recipient rules
        $this->validateRecipientRules($triggerData['recipient_rules']);
        
        // Validate conditions if provided
        if (isset($triggerData['conditions']) && !empty($triggerData['conditions'])) {
            $this->validateConditions($triggerData['conditions']);
        }
        
        // Set user context for repository
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        
        // Set default values
        $triggerData['is_active'] = $triggerData['is_active'] ?? true;
        $triggerData['created_by'] = $actingUserId;
        
        // Create trigger
        $trigger = $this->emailTriggerRepository->create($triggerData);
        
        // Register event listener for this trigger
        $this->registerEventListener($trigger['module_name'], $trigger['event_type']);
        
        return $trigger;
    }
    
    /**
     * Update an existing email trigger
     * 
     * @param int $triggerId Trigger ID to update
     * @param array $triggerData Updated trigger data
     * @param int $actingUserId ID of user performing the action
     * @return array Updated trigger data
     * @throws Exception on validation failure
     */
    public function updateTrigger(int $triggerId, array $triggerData, int $actingUserId): array {
        // Set user context for repository
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        
        // Get existing trigger
        $existingTrigger = $this->emailTriggerRepository->find($triggerId);
        if (!$existingTrigger) {
            throw new InvalidArgumentException('Trigger not found');
        }
        
        // Validate template if being updated
        if (isset($triggerData['template_id'])) {
            $this->validateTemplate($triggerData['template_id'], $actingUserId);
        }
        
        // Validate recipient rules if being updated
        if (isset($triggerData['recipient_rules'])) {
            $this->validateRecipientRules($triggerData['recipient_rules']);
        }
        
        // Validate conditions if being updated
        if (isset($triggerData['conditions'])) {
            $this->validateConditions($triggerData['conditions']);
        }
        
        // Update trigger
        $trigger = $this->emailTriggerRepository->update($triggerId, $triggerData);
        
        // Re-register event listener if module/event changed
        $moduleName = $triggerData['module_name'] ?? $existingTrigger['module_name'];
        $eventType = $triggerData['event_type'] ?? $existingTrigger['event_type'];
        $this->registerEventListener($moduleName, $eventType);
        
        return $trigger;
    }
    
    /**
     * Delete an email trigger
     * 
     * @param int $triggerId Trigger ID to delete
     * @param int $actingUserId ID of user performing the action
     * @return bool Success status
     * @throws Exception on validation failure
     */
    public function deleteTrigger(int $triggerId, int $actingUserId): bool {
        // Set user context for repository
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        
        // Get existing trigger
        $existingTrigger = $this->emailTriggerRepository->find($triggerId);
        if (!$existingTrigger) {
            throw new InvalidArgumentException('Trigger not found');
        }
        
        // Delete trigger
        return $this->emailTriggerRepository->delete($triggerId);
    }
    
    /**
     * Get trigger by ID with company isolation
     * 
     * @param int $triggerId Trigger ID
     * @param int $actingUserId ID of user performing the action
     * @return array|null Trigger data or null if not found/not accessible
     */
    public function getTrigger(int $triggerId, int $actingUserId): ?array {
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        return $this->emailTriggerRepository->find($triggerId);
    }
    
    /**
     * Get triggers by module with company isolation
     * 
     * @param string $moduleName Module name
     * @param int $actingUserId ID of user performing the action
     * @return array List of triggers
     */
    public function getTriggersByModule(string $moduleName, int $actingUserId): array {
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        return $this->emailTriggerRepository->getByModule($moduleName);
    }
    
    /**
     * Get triggers by company with optional filtering
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @param array $filters Optional filters (module_name, event_type, is_active)
     * @return array List of triggers
     */
    public function getTriggersByCompany(int $companyId, int $actingUserId, array $filters = []): array {
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        return $this->emailTriggerRepository->getByCompany($companyId, $filters);
    }
    
    /**
     * Search triggers with company isolation
     * 
     * @param string $searchTerm Search term
     * @param int $actingUserId ID of user performing the action
     * @param string|null $moduleName Optional module filter
     * @param int $limit Maximum results
     * @return array List of matching triggers
     */
    public function searchTriggers(string $searchTerm, int $actingUserId, ?string $moduleName = null, int $limit = 50): array {
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        return $this->emailTriggerRepository->search($searchTerm, $moduleName, $limit);
    }
    
    /**
     * Test trigger with sample data
     * 
     * @param int $triggerId Trigger ID
     * @param int $actingUserId ID of user performing the action
     * @param array $sampleEventData Optional custom sample data
     * @return array Test result
     */
    public function testTrigger(int $triggerId, int $actingUserId, array $sampleEventData = []): array {
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        return $this->emailTriggerRepository->testTrigger($triggerId, $sampleEventData);
    }
    
    /**
     * Process event and trigger appropriate emails
     * 
     * @param string $moduleName Module name
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @param int $companyId Company ID
     * @return array Processing result
     */
    public function processEvent(string $moduleName, string $eventType, array $eventData, int $companyId): array {
        $result = [
            'success' => true,
            'triggered_count' => 0,
            'queued_emails' => 0,
            'errors' => []
        ];
        
        try {
            // Get active triggers for this event
            $triggers = $this->emailTriggerRepository->getActiveTriggersForEvent($companyId, $moduleName, $eventType);
            
            // Sort triggers by priority
            $triggers = $this->conditionEngine->sortTriggersByPriority($triggers);
            
            foreach ($triggers as $trigger) {
                try {
                    // Evaluate trigger conditions
                    if (!$this->evaluateTriggerConditions($trigger, $eventData)) {
                        continue; // Conditions not met, skip this trigger
                    }
                    
                    // Get recipients for this trigger
                    $recipients = $this->getTriggerRecipients($trigger, $eventData);
                    
                    if (empty($recipients)) {
                        continue; // No recipients, skip this trigger
                    }
                    
                    // Generate email content using template and placeholders
                    $emailContent = $this->generateEmailContent($trigger, $eventData);
                    
                    // Queue emails for each recipient
                    foreach ($recipients as $recipient) {
                        $this->queueEmail($recipient, $emailContent, $trigger, $eventData);
                        $result['queued_emails']++;
                    }
                    
                    $result['triggered_count']++;
                    
                } catch (Exception $e) {
                    $result['errors'][] = [
                        'trigger_id' => $trigger['id'],
                        'trigger_name' => $trigger['name'],
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
     * Register event listener for module and event type
     * 
     * @param string $moduleName Module name
     * @param string $eventType Event type
     */
    public function registerEventListener(string $moduleName, string $eventType): void {
        $key = "{$moduleName}.{$eventType}";
        
        if (!isset(self::$eventListeners[$key])) {
            self::$eventListeners[$key] = true;
            
            // In a real implementation, this would register with the actual event system
            // For now, we just track that the listener is registered
        }
    }
    
    /**
     * Get registered event listeners
     * 
     * @return array List of registered event listeners
     */
    public function getRegisteredEventListeners(): array {
        return array_keys(self::$eventListeners);
    }
    
    /**
     * Clone trigger to different module or event
     * 
     * @param int $triggerId Trigger ID to clone
     * @param string $newModuleName New module name
     * @param string $newEventType New event type
     * @param int $actingUserId ID of user performing the action
     * @param string|null $newName Optional new name
     * @return array Cloned trigger data
     */
    public function cloneTrigger(int $triggerId, string $newModuleName, string $newEventType, int $actingUserId, ?string $newName = null): array {
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        return $this->emailTriggerRepository->cloneTrigger($triggerId, $newModuleName, $newEventType, $newName);
    }
    
    /**
     * Get trigger statistics by module
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @return array Module statistics
     */
    public function getModuleStats(int $companyId, int $actingUserId): array {
        $this->emailTriggerRepository->setCurrentUser($actingUserId);
        return $this->emailTriggerRepository->getModuleStats($companyId);
    }
    
    /**
     * Validate template exists and is accessible
     */
    private function validateTemplate(int $templateId, int $actingUserId): void {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        $template = $this->emailTemplateRepository->find($templateId);
        
        if (!$template) {
            throw new InvalidArgumentException('Template not found or not accessible');
        }
        
        if (!$template['is_active']) {
            throw new InvalidArgumentException('Template is not active');
        }
    }
    
    /**
     * Validate recipient rules
     */
    private function validateRecipientRules($recipientRules): void {
        if (!is_array($recipientRules)) {
            $recipientRules = json_decode($recipientRules, true);
        }
        
        if (!is_array($recipientRules) || empty($recipientRules)) {
            throw new InvalidArgumentException('Recipient rules must be a non-empty array');
        }
        
        foreach ($recipientRules as $rule) {
            if (!isset($rule['type'])) {
                throw new InvalidArgumentException('Each recipient rule must have a type');
            }
            
            switch ($rule['type']) {
                case 'static':
                    if (empty($rule['emails'])) {
                        throw new InvalidArgumentException('Static recipient rule must have emails');
                    }
                    break;
                    
                case 'field':
                    if (empty($rule['field'])) {
                        throw new InvalidArgumentException('Field recipient rule must specify a field');
                    }
                    break;
                    
                case 'role':
                    if (empty($rule['role'])) {
                        throw new InvalidArgumentException('Role recipient rule must specify a role');
                    }
                    break;
            }
        }
    }
    
    /**
     * Validate trigger conditions
     */
    private function validateConditions($conditions): void {
        if (!is_array($conditions)) {
            $conditions = json_decode($conditions, true);
        }
        
        if (!is_array($conditions)) {
            throw new InvalidArgumentException('Conditions must be a valid array or JSON');
        }
        
        // Use condition engine for validation
        $validation = $this->conditionEngine->validateConditions($conditions);
        if (!$validation['valid']) {
            throw new InvalidArgumentException('Invalid conditions: ' . implode(', ', $validation['errors']));
        }
    }
    
    /**
     * Evaluate trigger conditions against event data
     */
    private function evaluateTriggerConditions(array $trigger, array $eventData): bool {
        return $this->conditionEngine->evaluateConditions($trigger, $eventData);
    }
    
    /**
     * Get recipients for trigger based on recipient rules and event data
     */
    private function getTriggerRecipients(array $trigger, array $eventData): array {
        $recipientRules = $trigger['recipient_rules'] ?? [];
        return $this->conditionEngine->processRecipientRules($recipientRules, $eventData);
    }
    
    /**
     * Generate email content using template and placeholders
     */
    private function generateEmailContent(array $trigger, array $eventData): array {
        // Get template content
        $subject = $trigger['template_subject'] ?? '';
        $bodyText = $trigger['body_text'] ?? '';
        $bodyHtml = $trigger['body_html'] ?? '';
        
        // Replace placeholders with actual data
        $subject = $this->placeholderService->replacePlaceholders($subject, $eventData);
        $bodyText = $bodyText ? $this->placeholderService->replacePlaceholders($bodyText, $eventData) : null;
        $bodyHtml = $bodyHtml ? $this->placeholderService->replacePlaceholders($bodyHtml, $eventData) : null;
        
        return [
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml
        ];
    }
    
    /**
     * Queue email for sending
     */
    private function queueEmail(string $recipient, array $emailContent, array $trigger, array $eventData): void {
        $queueData = [
            'to_email' => $recipient,
            'subject' => $emailContent['subject'],
            'body_text' => $emailContent['body_text'],
            'body_html' => $emailContent['body_html'],
            'template_id' => $trigger['template_id'],
            'trigger_id' => $trigger['id'],
            'priority' => 'normal',
            'status' => 'pending',
            'company_id' => $eventData['company_id'] ?? $trigger['company_id'],
            'created_by' => $eventData['user_id'] ?? null
        ];
        
        $this->emailQueueRepository->create($queueData);
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