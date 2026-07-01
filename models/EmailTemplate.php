<?php
/**
 * EmailTemplate Model
 * Manages email templates with placeholder support and validation
 */

require_once __DIR__ . '/BaseModel.php';

class EmailTemplate extends BaseModel {
    protected $table = 'email_templates';
    protected $fillable = [
        'name', 'subject', 'body_text', 'body_html', 'module_name', 
        'event_type', 'placeholders', 'is_active', 'company_id', 'created_by'
    ];
    
    // Common module names
    const MODULE_USERS = 'users';
    const MODULE_SITES = 'sites';
    const MODULE_FEASIBILITY = 'feasibility';
    const MODULE_INSTALLATION = 'installation';
    const MODULE_INVENTORY = 'inventory';
    const MODULE_MATERIAL_REQUESTS = 'material_requests';
    const MODULE_DISPATCHES = 'dispatches';
    
    // Common event types
    const EVENT_CREATED = 'created';
    const EVENT_UPDATED = 'updated';
    const EVENT_ASSIGNED = 'assigned';
    const EVENT_COMPLETED = 'completed';
    const EVENT_APPROVED = 'approved';
    const EVENT_REJECTED = 'rejected';
    
    /**
     * Create new email template with validation
     */
    public function create($data) {
        // Validate template data
        $this->validateTemplate($data);
        
        // Process placeholders
        if (isset($data['placeholders']) && is_array($data['placeholders'])) {
            $data['placeholders'] = json_encode($data['placeholders']);
        }
        
        return parent::create($data);
    }
    
    /**
     * Update email template with validation
     */
    public function update($id, $data) {
        // Validate template data
        $this->validateTemplate($data);
        
        // Process placeholders
        if (isset($data['placeholders']) && is_array($data['placeholders'])) {
            $data['placeholders'] = json_encode($data['placeholders']);
        }
        
        return parent::update($id, $data);
    }
    
    /**
     * Find template by module and event type
     */
    public function findByModuleAndEvent($companyId, $moduleName, $eventType) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_id` = ? AND `module_name` = ? AND `event_type` = ? AND `is_active` = 1 
                LIMIT 1";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$companyId, $moduleName, $eventType], 'iss');
        
        if (!empty($result)) {
            $template = $result[0];
            // Decode placeholders JSON
            if ($template['placeholders']) {
                $template['placeholders'] = json_decode($template['placeholders'], true);
            }
            return $template;
        }
        
        return null;
    }
    
    /**
     * Get templates by module
     */
    public function getByModule($companyId, $moduleName) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_id` = ? AND `module_name` = ? 
                ORDER BY `event_type`, `name`";
        $results = DatabaseConfig::getInstance()->getResults($sql, [$companyId, $moduleName], 'is');
        
        // Decode placeholders for each template
        foreach ($results as &$template) {
            if ($template['placeholders']) {
                $template['placeholders'] = json_decode($template['placeholders'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get templates by company with optional filtering
     */
    public function getByCompany($companyId, $filters = []) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_id` = ?";
        $params = [$companyId];
        $types = 'i';
        
        if (isset($filters['module_name'])) {
            $sql .= " AND `module_name` = ?";
            $params[] = $filters['module_name'];
            $types .= 's';
        }
        
        if (isset($filters['event_type'])) {
            $sql .= " AND `event_type` = ?";
            $params[] = $filters['event_type'];
            $types .= 's';
        }
        
        if (isset($filters['is_active'])) {
            $sql .= " AND `is_active` = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY `module_name`, `event_type`, `name`";
        
        $results = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        
        // Decode placeholders for each template
        foreach ($results as &$template) {
            if ($template['placeholders']) {
                $template['placeholders'] = json_decode($template['placeholders'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Validate template syntax and placeholders
     */
    public function validateTemplateSyntax($templateContent, $availablePlaceholders = []) {
        $errors = [];
        
        // Find all placeholders in template
        preg_match_all('/\{([^}]+)\}/', $templateContent, $matches);
        $usedPlaceholders = $matches[1];
        
        // Check for invalid placeholders
        if (!empty($availablePlaceholders)) {
            foreach ($usedPlaceholders as $placeholder) {
                if (!in_array($placeholder, $availablePlaceholders)) {
                    $errors[] = "Invalid placeholder: {$placeholder}";
                }
            }
        }
        
        // Check for unclosed placeholders
        $openBraces = substr_count($templateContent, '{');
        $closeBraces = substr_count($templateContent, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = "Mismatched braces in template";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'placeholders' => array_unique($usedPlaceholders)
        ];
    }
    
    /**
     * Generate preview of template with sample data
     */
    public function generatePreview($templateId, $sampleData = []) {
        $template = $this->find($templateId);
        if (!$template) {
            throw new InvalidArgumentException("Template not found");
        }
        
        // Use sample data or generate default sample data
        if (empty($sampleData)) {
            $sampleData = $this->generateSampleData($template['module_name']);
        }
        
        $preview = [
            'subject' => $this->replacePlaceholders($template['subject'], $sampleData),
            'body_text' => $template['body_text'] ? $this->replacePlaceholders($template['body_text'], $sampleData) : null,
            'body_html' => $template['body_html'] ? $this->replacePlaceholders($template['body_html'], $sampleData) : null
        ];
        
        return $preview;
    }
    
    /**
     * Replace placeholders in content with actual data
     */
    public function replacePlaceholders($content, $data) {
        if (empty($content)) {
            return $content;
        }
        
        // Replace simple placeholders
        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $content = str_replace($placeholder, $value, $content);
        }
        
        // Handle nested placeholders (e.g., {site.engineer.name})
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        foreach ($matches[1] as $placeholder) {
            if (strpos($placeholder, '.') !== false) {
                $value = $this->getNestedValue($data, $placeholder);
                if ($value !== null) {
                    $content = str_replace('{' . $placeholder . '}', $value, $content);
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Get nested value from data array using dot notation
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
     * Generate sample data for template preview
     */
    private function generateSampleData($moduleName) {
        $baseData = [
            'company_name' => 'Sample Company Ltd',
            'user_name' => 'John Doe',
            'user_email' => 'john.doe@example.com',
            'current_date' => date('Y-m-d'),
            'current_time' => date('H:i:s')
        ];
        
        switch ($moduleName) {
            case self::MODULE_SITES:
                return array_merge($baseData, [
                    'site_name' => 'Sample Site Location',
                    'site_address' => '123 Main Street, City, State',
                    'engineer' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane@example.com'
                    ]
                ]);
                
            case self::MODULE_FEASIBILITY:
                return array_merge($baseData, [
                    'feasibility_id' => 'FSB-001',
                    'site_name' => 'Sample Site Location',
                    'status' => 'Pending Review'
                ]);
                
            case self::MODULE_INSTALLATION:
                return array_merge($baseData, [
                    'installation_id' => 'INS-001',
                    'site_name' => 'Sample Site Location',
                    'scheduled_date' => date('Y-m-d', strtotime('+7 days'))
                ]);
                
            case self::MODULE_MATERIAL_REQUESTS:
                return array_merge($baseData, [
                    'request_id' => 'MR-001',
                    'requested_items' => '5x Router, 10x Cable',
                    'urgency' => 'High'
                ]);
                
            default:
                return $baseData;
        }
    }
    
    /**
     * Validate template data
     */
    private function validateTemplate($data) {
        $errors = [];
        
        // Validate required fields
        $requiredFields = ['name', 'subject'];
        foreach ($requiredFields as $field) {
            if (isset($data[$field]) && empty(trim($data[$field]))) {
                $errors[] = "Field '$field' is required";
            }
        }
        
        // Validate that at least one body is provided
        if (isset($data['body_text']) || isset($data['body_html'])) {
            if (empty(trim($data['body_text'] ?? '')) && empty(trim($data['body_html'] ?? ''))) {
                $errors[] = "At least one of body_text or body_html must be provided";
            }
        }
        
        // Validate module name
        if (isset($data['module_name']) && empty(trim($data['module_name']))) {
            $errors[] = "Module name is required";
        }
        
        // Validate event type
        if (isset($data['event_type']) && empty(trim($data['event_type']))) {
            $errors[] = "Event type is required";
        }
        
        // Validate template syntax if content is provided
        if (isset($data['subject'])) {
            $validation = $this->validateTemplateSyntax($data['subject']);
            if (!$validation['valid']) {
                $errors = array_merge($errors, array_map(function($error) {
                    return "Subject: $error";
                }, $validation['errors']));
            }
        }
        
        if (isset($data['body_text'])) {
            $validation = $this->validateTemplateSyntax($data['body_text']);
            if (!$validation['valid']) {
                $errors = array_merge($errors, array_map(function($error) {
                    return "Body text: $error";
                }, $validation['errors']));
            }
        }
        
        if (isset($data['body_html'])) {
            $validation = $this->validateTemplateSyntax($data['body_html']);
            if (!$validation['valid']) {
                $errors = array_merge($errors, array_map(function($error) {
                    return "Body HTML: $error";
                }, $validation['errors']));
            }
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException("Template validation failed: " . implode(', ', $errors));
        }
    }
}