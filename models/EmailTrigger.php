<?php
/**
 * EmailTrigger Model
 * Manages automated email triggers with event association
 */

require_once __DIR__ . '/BaseModel.php';

class EmailTrigger extends BaseModel {
    protected $table = 'email_triggers';
    protected $fillable = [
        'name', 'module_name', 'event_type', 'template_id', 'recipient_rules',
        'conditions', 'is_active', 'company_id', 'created_by'
    ];
    
    /**
     * Create new email trigger with validation
     */
    public function create($data) {
        // Validate trigger data
        $this->validateTrigger($data);
        
        // Process JSON fields
        if (isset($data['recipient_rules']) && is_array($data['recipient_rules'])) {
            $data['recipient_rules'] = json_encode($data['recipient_rules']);
        }
        
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = json_encode($data['conditions']);
        }
        
        return parent::create($data);
    }
    
    /**
     * Update email trigger with validation
     */
    public function update($id, $data) {
        // Validate trigger data
        $this->validateTrigger($data);
        
        // Process JSON fields
        if (isset($data['recipient_rules']) && is_array($data['recipient_rules'])) {
            $data['recipient_rules'] = json_encode($data['recipient_rules']);
        }
        
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = json_encode($data['conditions']);
        }
        
        return parent::update($id, $data);
    }
    
    /**
     * Find trigger with decoded JSON fields
     */
    public function find($id) {
        $trigger = parent::find($id);
        if ($trigger) {
            $trigger = $this->decodeJsonFields($trigger);
        }
        return $trigger;
    }
    
    /**
     * Find triggers by module and event
     */
    public function findByModuleAndEvent($companyId, $moduleName, $eventType) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_id` = ? AND `module_name` = ? AND `event_type` = ? AND `is_active` = 1 
                ORDER BY `name`";
        $results = DatabaseConfig::getInstance()->getResults($sql, [$companyId, $moduleName, $eventType], 'iss');
        
        // Decode JSON fields for each trigger
        foreach ($results as &$trigger) {
            $trigger = $this->decodeJsonFields($trigger);
        }
        
        return $results;
    }
    
    /**
     * Get triggers by company with optional filtering
     */
    public function getByCompany($companyId, $filters = []) {
        $sql = "SELECT t.*, et.name as template_name, et.subject as template_subject 
                FROM `{$this->table}` t 
                LEFT JOIN `email_templates` et ON t.template_id = et.id 
                WHERE t.company_id = ?";
        $params = [$companyId];
        $types = 'i';
        
        if (isset($filters['module_name'])) {
            $sql .= " AND t.module_name = ?";
            $params[] = $filters['module_name'];
            $types .= 's';
        }
        
        if (isset($filters['event_type'])) {
            $sql .= " AND t.event_type = ?";
            $params[] = $filters['event_type'];
            $types .= 's';
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND t.is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY t.module_name, t.event_type, t.name";
        
        $results = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        
        // Decode JSON fields for each trigger
        foreach ($results as &$trigger) {
            $trigger = $this->decodeJsonFields($trigger);
        }
        
        return $results;
    }
    
    /**
     * Evaluate trigger conditions against event data
     */
    public function evaluateConditions($triggerId, $eventData) {
        $trigger = $this->find($triggerId);
        if (!$trigger || !$trigger['is_active']) {
            return false;
        }
        
        // If no conditions are set, trigger always fires
        if (empty($trigger['conditions'])) {
            return true;
        }
        
        return $this->evaluateConditionGroup($trigger['conditions'], $eventData);
    }
    
    /**
     * Get recipients for trigger based on recipient rules and event data
     */
    public function getRecipients($triggerId, $eventData) {
        $trigger = $this->find($triggerId);
        if (!$trigger || !$trigger['is_active']) {
            return [];
        }
        
        $recipients = [];
        $rules = $trigger['recipient_rules'];
        
        foreach ($rules as $rule) {
            $ruleRecipients = $this->processRecipientRule($rule, $eventData);
            $recipients = array_merge($recipients, $ruleRecipients);
        }
        
        // Remove duplicates and validate email addresses
        $recipients = array_unique($recipients);
        return array_filter($recipients, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
    }
    
    /**
     * Test trigger with sample data
     */
    public function testTrigger($triggerId, $sampleEventData = []) {
        $trigger = $this->find($triggerId);
        if (!$trigger) {
            throw new InvalidArgumentException("Trigger not found");
        }
        
        // Generate sample data if not provided
        if (empty($sampleEventData)) {
            $sampleEventData = $this->generateSampleEventData($trigger['module_name'], $trigger['event_type']);
        }
        
        $result = [
            'trigger_id' => $triggerId,
            'trigger_name' => $trigger['name'],
            'conditions_met' => $this->evaluateConditions($triggerId, $sampleEventData),
            'recipients' => $this->getRecipients($triggerId, $sampleEventData),
            'sample_data' => $sampleEventData
        ];
        
        return $result;
    }
    
    /**
     * Decode JSON fields in trigger data
     */
    private function decodeJsonFields($trigger) {
        if ($trigger['recipient_rules']) {
            $trigger['recipient_rules'] = json_decode($trigger['recipient_rules'], true);
        }
        
        if ($trigger['conditions']) {
            $trigger['conditions'] = json_decode($trigger['conditions'], true);
        }
        
        return $trigger;
    }
    
    /**
     * Evaluate a group of conditions
     */
    private function evaluateConditionGroup($conditions, $eventData) {
        $operator = $conditions['operator'] ?? 'AND';
        $rules = $conditions['rules'] ?? [];
        
        if (empty($rules)) {
            return true;
        }
        
        $results = [];
        foreach ($rules as $rule) {
            if (isset($rule['rules'])) {
                // Nested condition group
                $results[] = $this->evaluateConditionGroup($rule, $eventData);
            } else {
                // Individual condition
                $results[] = $this->evaluateCondition($rule, $eventData);
            }
        }
        
        if ($operator === 'OR') {
            return in_array(true, $results);
        } else {
            return !in_array(false, $results);
        }
    }
    
    /**
     * Evaluate individual condition
     */
    private function evaluateCondition($condition, $eventData) {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? '';
        
        $eventValue = $this->getNestedValue($eventData, $field);
        
        switch ($operator) {
            case '=':
                return $eventValue == $value;
            case '!=':
                return $eventValue != $value;
            case '>':
                return $eventValue > $value;
            case '>=':
                return $eventValue >= $value;
            case '<':
                return $eventValue < $value;
            case '<=':
                return $eventValue <= $value;
            case 'contains':
                return strpos($eventValue, $value) !== false;
            case 'not_contains':
                return strpos($eventValue, $value) === false;
            case 'in':
                return in_array($eventValue, explode(',', $value));
            case 'not_in':
                return !in_array($eventValue, explode(',', $value));
            default:
                return false;
        }
    }
    
    /**
     * Process recipient rule to get email addresses
     */
    private function processRecipientRule($rule, $eventData) {
        $type = $rule['type'] ?? 'static';
        $recipients = [];
        
        switch ($type) {
            case 'static':
                // Static email addresses
                $emails = $rule['emails'] ?? [];
                $recipients = is_array($emails) ? $emails : [$emails];
                break;
                
            case 'field':
                // Email from event data field
                $field = $rule['field'] ?? '';
                $email = $this->getNestedValue($eventData, $field);
                if ($email) {
                    $recipients = [$email];
                }
                break;
                
            case 'role':
                // Users with specific role
                $role = $rule['role'] ?? '';
                $companyId = $eventData['company_id'] ?? 0;
                $recipients = $this->getUsersByRole($companyId, $role);
                break;
                
            case 'query':
                // Custom database query
                $query = $rule['query'] ?? '';
                $recipients = $this->executeRecipientQuery($query, $eventData);
                break;
        }
        
        return $recipients;
    }
    
    /**
     * Get nested value from data using dot notation
     */
    private function getNestedValue($data, $path) {
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Get users by role for recipient rules
     */
    private function getUsersByRole($companyId, $role) {
        $sql = "SELECT u.email FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.company_id = ? AND r.name = ? AND u.status = 1";
        $results = DatabaseConfig::getInstance()->getResults($sql, [$companyId, $role], 'is');
        
        return array_column($results, 'email');
    }
    
    /**
     * Execute custom recipient query
     */
    private function executeRecipientQuery($query, $eventData) {
        // For security, only allow predefined queries or use a whitelist approach
        // This is a simplified implementation
        return [];
    }
    
    /**
     * Generate sample event data for testing
     */
    private function generateSampleEventData($moduleName, $eventType) {
        $baseData = [
            'company_id' => 1,
            'user_id' => 1,
            'user_email' => 'test@example.com',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        switch ($moduleName) {
            case 'sites':
                return array_merge($baseData, [
                    'site_id' => 1,
                    'site_name' => 'Test Site',
                    'engineer_email' => 'engineer@example.com'
                ]);
                
            case 'feasibility':
                return array_merge($baseData, [
                    'feasibility_id' => 1,
                    'site_id' => 1,
                    'status' => 'pending'
                ]);
                
            default:
                return $baseData;
        }
    }
    
    /**
     * Validate trigger data
     */
    private function validateTrigger($data) {
        $errors = [];
        
        // Validate required fields
        $requiredFields = ['name', 'module_name', 'event_type', 'template_id'];
        foreach ($requiredFields as $field) {
            if (isset($data[$field]) && empty(trim($data[$field]))) {
                $errors[] = "Field '$field' is required";
            }
        }
        
        // Validate template exists
        if (isset($data['template_id'])) {
            $templateModel = new EmailTemplate();
            $template = $templateModel->find($data['template_id']);
            if (!$template) {
                $errors[] = "Invalid template_id: template not found";
            }
        }
        
        // Validate recipient rules
        if (isset($data['recipient_rules'])) {
            $rules = is_string($data['recipient_rules']) ? 
                json_decode($data['recipient_rules'], true) : 
                $data['recipient_rules'];
                
            if (!is_array($rules) || empty($rules)) {
                $errors[] = "Recipient rules must be a non-empty array";
            }
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException("Trigger validation failed: " . implode(', ', $errors));
        }
    }
}