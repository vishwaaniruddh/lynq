<?php
/**
 * Email Trigger Condition Engine
 * Handles advanced conditional logic evaluation for email triggers
 * Supports complex conditions, recipient rule processing, and trigger priority
 * 
 * Requirements: 4.2, 4.4, 4.5
 * - 4.2: Conditional logic engine for triggers
 * - 4.4: Recipient rule processing
 * - 4.5: Trigger priority and ordering
 */

require_once __DIR__ . '/../config/autoload.php';

class EmailTriggerConditionEngine {
    private $db;
    
    // Supported operators
    const OPERATORS = [
        '=' => 'equals',
        '!=' => 'not_equals',
        '>' => 'greater_than',
        '>=' => 'greater_than_or_equal',
        '<' => 'less_than',
        '<=' => 'less_than_or_equal',
        'contains' => 'contains',
        'not_contains' => 'not_contains',
        'starts_with' => 'starts_with',
        'ends_with' => 'ends_with',
        'in' => 'in_array',
        'not_in' => 'not_in_array',
        'is_null' => 'is_null',
        'is_not_null' => 'is_not_null',
        'regex' => 'regex_match',
        'between' => 'between_values'
    ];
    
    // Supported logical operators
    const LOGICAL_OPERATORS = ['AND', 'OR'];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Evaluate trigger conditions against event data
     * 
     * @param array $trigger Trigger data with conditions
     * @param array $eventData Event data to evaluate against
     * @return bool True if conditions are met
     */
    public function evaluateConditions(array $trigger, array $eventData): bool {
        // If no conditions are set, trigger always fires
        if (empty($trigger['conditions'])) {
            return true;
        }
        
        // If trigger is not active, never fire
        if (!$trigger['is_active']) {
            return false;
        }
        
        try {
            return $this->evaluateConditionGroup($trigger['conditions'], $eventData);
        } catch (Exception $e) {
            error_log("Failed to evaluate trigger conditions: " . $e->getMessage());
            return false; // Fail safe - don't trigger on evaluation errors
        }
    }
    
    /**
     * Evaluate a group of conditions (supports nested groups)
     * 
     * @param array $conditionGroup Group of conditions with operator
     * @param array $eventData Event data to evaluate against
     * @return bool True if condition group is met
     */
    private function evaluateConditionGroup(array $conditionGroup, array $eventData): bool {
        $operator = strtoupper($conditionGroup['operator'] ?? 'AND');
        $rules = $conditionGroup['rules'] ?? [];
        
        // Validate operator
        if (!in_array($operator, self::LOGICAL_OPERATORS)) {
            throw new InvalidArgumentException("Invalid logical operator: $operator");
        }
        
        // Empty rules group is considered true
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
        
        // Apply logical operator
        if ($operator === 'OR') {
            return in_array(true, $results);
        } else { // AND
            return !in_array(false, $results);
        }
    }
    
    /**
     * Evaluate individual condition
     * 
     * @param array $condition Condition definition
     * @param array $eventData Event data to evaluate against
     * @return bool True if condition is met
     */
    private function evaluateCondition(array $condition, array $eventData): bool {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? '';
        $caseSensitive = $condition['case_sensitive'] ?? false;
        
        // Validate operator
        if (!isset(self::OPERATORS[$operator])) {
            throw new InvalidArgumentException("Invalid operator: $operator");
        }
        
        // Get field value from event data (supports nested fields)
        $fieldValue = $this->getNestedValue($eventData, $field);
        
        // Apply case sensitivity for string operations
        if (!$caseSensitive && is_string($fieldValue) && is_string($value)) {
            $fieldValue = strtolower($fieldValue);
            $value = strtolower($value);
        }
        
        // Evaluate based on operator
        switch ($operator) {
            case '=':
                return $this->compareValues($fieldValue, $value, '=');
                
            case '!=':
                return $this->compareValues($fieldValue, $value, '!=');
                
            case '>':
                return $this->compareValues($fieldValue, $value, '>');
                
            case '>=':
                return $this->compareValues($fieldValue, $value, '>=');
                
            case '<':
                return $this->compareValues($fieldValue, $value, '<');
                
            case '<=':
                return $this->compareValues($fieldValue, $value, '<=');
                
            case 'contains':
                return is_string($fieldValue) && strpos($fieldValue, $value) !== false;
                
            case 'not_contains':
                return !is_string($fieldValue) || strpos($fieldValue, $value) === false;
                
            case 'starts_with':
                return is_string($fieldValue) && strpos($fieldValue, $value) === 0;
                
            case 'ends_with':
                return is_string($fieldValue) && substr($fieldValue, -strlen($value)) === $value;
                
            case 'in':
                $values = is_array($value) ? $value : explode(',', $value);
                return in_array($fieldValue, array_map('trim', $values));
                
            case 'not_in':
                $values = is_array($value) ? $value : explode(',', $value);
                return !in_array($fieldValue, array_map('trim', $values));
                
            case 'is_null':
                return $fieldValue === null || $fieldValue === '';
                
            case 'is_not_null':
                return $fieldValue !== null && $fieldValue !== '';
                
            case 'regex':
                return is_string($fieldValue) && preg_match($value, $fieldValue);
                
            case 'between':
                if (!is_array($value) || count($value) !== 2) {
                    throw new InvalidArgumentException("Between operator requires array with 2 values");
                }
                return $fieldValue >= $value[0] && $fieldValue <= $value[1];
                
            default:
                return false;
        }
    }
    
    /**
     * Compare two values with type-aware comparison
     * 
     * @param mixed $fieldValue Value from event data
     * @param mixed $conditionValue Value from condition
     * @param string $operator Comparison operator
     * @return bool Comparison result
     */
    private function compareValues($fieldValue, $conditionValue, string $operator): bool {
        // Handle null values
        if ($fieldValue === null || $conditionValue === null) {
            switch ($operator) {
                case '=':
                    return $fieldValue === $conditionValue;
                case '!=':
                    return $fieldValue !== $conditionValue;
                default:
                    return false; // Can't compare null with other operators
            }
        }
        
        // Try to convert to same type for comparison
        if (is_numeric($fieldValue) && is_numeric($conditionValue)) {
            $fieldValue = (float)$fieldValue;
            $conditionValue = (float)$conditionValue;
        } elseif (is_string($fieldValue) && is_string($conditionValue)) {
            // String comparison - already handled above for case sensitivity
        }
        
        switch ($operator) {
            case '=':
                return $fieldValue == $conditionValue;
            case '!=':
                return $fieldValue != $conditionValue;
            case '>':
                return $fieldValue > $conditionValue;
            case '>=':
                return $fieldValue >= $conditionValue;
            case '<':
                return $fieldValue < $conditionValue;
            case '<=':
                return $fieldValue <= $conditionValue;
            default:
                return false;
        }
    }
    
    /**
     * Get nested value from data using dot notation
     * 
     * @param array $data Data array
     * @param string $path Dot-separated path (e.g., 'user.profile.name')
     * @return mixed Value at path or null if not found
     */
    private function getNestedValue(array $data, string $path) {
        if (empty($path)) {
            return null;
        }
        
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
     * Process recipient rules to get email addresses
     * 
     * @param array $recipientRules Array of recipient rules
     * @param array $eventData Event data for dynamic recipients
     * @return array Array of email addresses
     */
    public function processRecipientRules(array $recipientRules, array $eventData): array {
        $recipients = [];
        
        foreach ($recipientRules as $rule) {
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
     * Process individual recipient rule
     * 
     * @param array $rule Recipient rule definition
     * @param array $eventData Event data for dynamic recipients
     * @return array Array of email addresses
     */
    private function processRecipientRule(array $rule, array $eventData): array {
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
                // Custom database query (with security restrictions)
                $query = $rule['query'] ?? '';
                $recipients = $this->executeRecipientQuery($query, $eventData);
                break;
                
            case 'conditional':
                // Conditional recipients based on event data
                if ($this->evaluateConditions(['conditions' => $rule['conditions']], $eventData)) {
                    $recipients = $this->processRecipientRules($rule['recipients'] ?? [], $eventData);
                }
                break;
        }
        
        return $recipients;
    }
    
    /**
     * Get users by role for recipient rules
     * 
     * @param int $companyId Company ID
     * @param string $role Role name
     * @return array Array of email addresses
     */
    private function getUsersByRole(int $companyId, string $role): array {
        try {
            $sql = "SELECT u.email FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.company_id = ? AND r.name = ? AND u.status = 1";
            $results = $this->db->getResults($sql, [$companyId, $role], 'is');
            
            return array_column($results, 'email');
        } catch (Exception $e) {
            error_log("Failed to get users by role: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Execute custom recipient query (with security restrictions)
     * 
     * @param string $query SQL query template
     * @param array $eventData Event data for parameter substitution
     * @return array Array of email addresses
     */
    private function executeRecipientQuery(string $query, array $eventData): array {
        // For security, only allow predefined queries or use a whitelist approach
        // This is a simplified implementation - in production, use prepared statements
        // and strict query validation
        
        $allowedQueries = [
            'site_engineers' => "SELECT u.email FROM users u 
                               JOIN engineer_assignments ea ON u.id = ea.engineer_id 
                               WHERE ea.site_id = ? AND u.status = 1",
            'company_admins' => "SELECT u.email FROM users u 
                                JOIN roles r ON u.role_id = r.id 
                                WHERE u.company_id = ? AND r.name LIKE '%admin%' AND u.status = 1"
        ];
        
        if (!isset($allowedQueries[$query])) {
            error_log("Attempted to execute unauthorized recipient query: $query");
            return [];
        }
        
        try {
            $sql = $allowedQueries[$query];
            $params = [];
            
            // Simple parameter substitution based on query type
            switch ($query) {
                case 'site_engineers':
                    $params = [$eventData['site_id'] ?? 0];
                    break;
                case 'company_admins':
                    $params = [$eventData['company_id'] ?? 0];
                    break;
            }
            
            $results = $this->db->getResults($sql, $params, str_repeat('i', count($params)));
            return array_column($results, 'email');
            
        } catch (Exception $e) {
            error_log("Failed to execute recipient query: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Sort triggers by priority and ordering
     * 
     * @param array $triggers Array of triggers
     * @return array Sorted triggers
     */
    public function sortTriggersByPriority(array $triggers): array {
        usort($triggers, function($a, $b) {
            // First sort by priority (higher priority first)
            $priorityA = $a['priority'] ?? 0;
            $priorityB = $b['priority'] ?? 0;
            
            if ($priorityA !== $priorityB) {
                return $priorityB - $priorityA; // Higher priority first
            }
            
            // Then sort by name for consistent ordering
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });
        
        return $triggers;
    }
    
    /**
     * Validate condition structure
     * 
     * @param array $conditions Conditions to validate
     * @return array Validation result with 'valid' and 'errors'
     */
    public function validateConditions(array $conditions): array {
        $errors = [];
        
        try {
            $this->validateConditionGroup($conditions, $errors);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate condition group structure
     * 
     * @param array $conditionGroup Condition group to validate
     * @param array &$errors Array to collect errors
     */
    private function validateConditionGroup(array $conditionGroup, array &$errors): void {
        if (!isset($conditionGroup['operator'])) {
            $errors[] = 'Condition group must have an operator';
            return;
        }
        
        $operator = strtoupper($conditionGroup['operator']);
        if (!in_array($operator, self::LOGICAL_OPERATORS)) {
            $errors[] = "Invalid logical operator: {$conditionGroup['operator']}";
        }
        
        if (!isset($conditionGroup['rules']) || !is_array($conditionGroup['rules'])) {
            $errors[] = 'Condition group must have rules array';
            return;
        }
        
        foreach ($conditionGroup['rules'] as $index => $rule) {
            if (isset($rule['rules'])) {
                // Nested condition group
                $this->validateConditionGroup($rule, $errors);
            } else {
                // Individual condition
                $this->validateCondition($rule, $errors, "Rule $index");
            }
        }
    }
    
    /**
     * Validate individual condition
     * 
     * @param array $condition Condition to validate
     * @param array &$errors Array to collect errors
     * @param string $context Context for error messages
     */
    private function validateCondition(array $condition, array &$errors, string $context = 'Condition'): void {
        $requiredFields = ['field', 'operator'];
        foreach ($requiredFields as $field) {
            if (!isset($condition[$field])) {
                $errors[] = "$context must have $field";
            }
        }
        
        if (isset($condition['operator']) && !isset(self::OPERATORS[$condition['operator']])) {
            $errors[] = "$context has invalid operator: {$condition['operator']}";
        }
        
        // Validate value based on operator
        if (isset($condition['operator'])) {
            switch ($condition['operator']) {
                case 'between':
                    if (!isset($condition['value']) || !is_array($condition['value']) || count($condition['value']) !== 2) {
                        $errors[] = "$context with 'between' operator must have array value with 2 elements";
                    }
                    break;
                    
                case 'regex':
                    if (isset($condition['value']) && @preg_match($condition['value'], '') === false) {
                        $errors[] = "$context has invalid regex pattern";
                    }
                    break;
            }
        }
    }
    
    /**
     * Get supported operators
     * 
     * @return array Array of supported operators with descriptions
     */
    public function getSupportedOperators(): array {
        return self::OPERATORS;
    }
    
    /**
     * Get supported logical operators
     * 
     * @return array Array of supported logical operators
     */
    public function getSupportedLogicalOperators(): array {
        return self::LOGICAL_OPERATORS;
    }
}