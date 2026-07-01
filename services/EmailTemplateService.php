<?php
/**
 * Email Template Service
 * Handles business logic for email template management operations
 * Provides template CRUD, validation, syntax checking, and rich text/HTML support
 */

require_once __DIR__ . '/../config/autoload.php';

class EmailTemplateService {
    private $db;
    private $emailTemplateRepository;
    private $placeholderService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->emailTemplateRepository = new EmailTemplateRepository();
        $this->placeholderService = new PlaceholderService();
    }
    
    /**
     * Create a new email template with validation
     * 
     * @param array $templateData Template data to create
     * @param int $actingUserId ID of user performing the action
     * @return array Created template data
     * @throws Exception on validation failure
     */
    public function createTemplate(array $templateData, int $actingUserId): array {
        // Validate required fields
        $this->validateRequiredFields($templateData, ['name', 'subject', 'module_name', 'event_type', 'company_id']);
        
        // Validate that at least one body is provided
        if (empty(trim($templateData['body_text'] ?? '')) && empty(trim($templateData['body_html'] ?? ''))) {
            throw new InvalidArgumentException('At least one of body_text or body_html must be provided');
        }
        
        // Validate company access
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        
        // Check for duplicate template (same module, event, company)
        $existing = $this->emailTemplateRepository->findByModuleAndEvent(
            $templateData['company_id'],
            $templateData['module_name'],
            $templateData['event_type']
        );
        
        if ($existing) {
            throw new InvalidArgumentException(
                "Template already exists for module '{$templateData['module_name']}' and event '{$templateData['event_type']}'"
            );
        }
        
        // Validate template syntax and placeholders
        $this->validateTemplateSyntax($templateData);
        
        // Set default values
        $templateData['is_active'] = $templateData['is_active'] ?? true;
        $templateData['created_by'] = $actingUserId;
        
        // Extract and validate placeholders
        $templateData['placeholders'] = $this->extractPlaceholders($templateData);
        
        // Create template
        $template = $this->emailTemplateRepository->create($templateData);
        
        return $template;
    }
    
    /**
     * Update an existing email template
     * 
     * @param int $templateId Template ID to update
     * @param array $templateData Updated template data
     * @param int $actingUserId ID of user performing the action
     * @return array Updated template data
     * @throws Exception on validation failure
     */
    public function updateTemplate(int $templateId, array $templateData, int $actingUserId): array {
        // Set user context for repository
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        
        // Get existing template
        $existingTemplate = $this->emailTemplateRepository->find($templateId);
        if (!$existingTemplate) {
            throw new InvalidArgumentException('Template not found');
        }
        
        // Validate template syntax and placeholders if content is being updated
        if (isset($templateData['subject']) || isset($templateData['body_text']) || isset($templateData['body_html'])) {
            $this->validateTemplateSyntax($templateData, $existingTemplate);
        }
        
        // Check for duplicate if module/event is being changed
        if ((isset($templateData['module_name']) && $templateData['module_name'] !== $existingTemplate['module_name']) ||
            (isset($templateData['event_type']) && $templateData['event_type'] !== $existingTemplate['event_type'])) {
            
            $checkModuleName = $templateData['module_name'] ?? $existingTemplate['module_name'];
            $checkEventType = $templateData['event_type'] ?? $existingTemplate['event_type'];
            
            $existing = $this->emailTemplateRepository->findByModuleAndEvent(
                $existingTemplate['company_id'],
                $checkModuleName,
                $checkEventType
            );
            
            if ($existing && $existing['id'] != $templateId) {
                throw new InvalidArgumentException(
                    "Template already exists for module '$checkModuleName' and event '$checkEventType'"
                );
            }
        }
        
        // Extract and validate placeholders if content is being updated
        if (isset($templateData['subject']) || isset($templateData['body_text']) || isset($templateData['body_html'])) {
            $templateData['placeholders'] = $this->extractPlaceholders($templateData, $existingTemplate);
        }
        
        // Update template
        $template = $this->emailTemplateRepository->update($templateId, $templateData);
        
        return $template;
    }
    
    /**
     * Delete an email template
     * 
     * @param int $templateId Template ID to delete
     * @param int $actingUserId ID of user performing the action
     * @return bool Success status
     * @throws Exception on validation failure
     */
    public function deleteTemplate(int $templateId, int $actingUserId): bool {
        // Set user context for repository
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        
        // Get existing template
        $existingTemplate = $this->emailTemplateRepository->find($templateId);
        if (!$existingTemplate) {
            throw new InvalidArgumentException('Template not found');
        }
        
        // Check if template is being used by any triggers
        $triggerCount = $this->getTemplateTriggerCount($templateId);
        if ($triggerCount > 0) {
            throw new InvalidArgumentException(
                "Cannot delete template: it is being used by $triggerCount email trigger(s)"
            );
        }
        
        // Delete template
        return $this->emailTemplateRepository->delete($templateId);
    }
    
    /**
     * Get template by ID with company isolation
     * 
     * @param int $templateId Template ID
     * @param int $actingUserId ID of user performing the action
     * @return array|null Template data or null if not found/not accessible
     */
    public function getTemplate(int $templateId, int $actingUserId): ?array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->find($templateId);
    }
    
    /**
     * Get templates by module with company isolation
     * 
     * @param string $moduleName Module name
     * @param int $actingUserId ID of user performing the action
     * @return array List of templates
     */
    public function getTemplatesByModule(string $moduleName, int $actingUserId): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->getByModule($moduleName);
    }
    
    /**
     * Get templates by company with optional filtering
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @param array $filters Optional filters (module_name, event_type, is_active)
     * @return array List of templates
     */
    public function getTemplatesByCompany(int $companyId, int $actingUserId, array $filters = []): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->getByCompany($companyId, $filters);
    }
    
    /**
     * Search templates with company isolation
     * 
     * @param string $searchTerm Search term
     * @param int $actingUserId ID of user performing the action
     * @param string|null $moduleName Optional module filter
     * @param int $limit Maximum results
     * @return array List of matching templates
     */
    public function searchTemplates(string $searchTerm, int $actingUserId, ?string $moduleName = null, int $limit = 50): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->search($searchTerm, $moduleName, $limit);
    }
    
    /**
     * Get templates grouped by module
     * 
     * @param int $actingUserId ID of user performing the action
     * @param int|null $companyId Optional company filter
     * @return array Templates grouped by module
     */
    public function getTemplatesGroupedByModule(int $actingUserId, ?int $companyId = null): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->getGroupedByModule($companyId);
    }
    
    /**
     * Validate template syntax and placeholders
     * 
     * @param string $templateContent Template content to validate
     * @param string $moduleName Module name for placeholder validation
     * @return array Validation result
     */
    public function validateTemplateSyntax(string $templateContent, string $moduleName): array {
        // Get available placeholders for the module
        $availablePlaceholders = $this->placeholderService->getAvailablePlaceholders($moduleName);
        
        return $this->emailTemplateRepository->validateTemplateSyntax($templateContent, $availablePlaceholders);
    }
    
    /**
     * Generate template preview with sample data
     * 
     * @param int $templateId Template ID
     * @param int $actingUserId ID of user performing the action
     * @param array $sampleData Optional custom sample data
     * @return array Preview data
     */
    public function generatePreview(int $templateId, int $actingUserId, array $sampleData = []): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->generatePreview($templateId, $sampleData);
    }
    
    /**
     * Clone template to different module or event
     * 
     * @param int $templateId Template ID to clone
     * @param string $newModuleName New module name
     * @param string $newEventType New event type
     * @param int $actingUserId ID of user performing the action
     * @param string|null $newName Optional new name
     * @return array Cloned template data
     */
    public function cloneTemplate(int $templateId, string $newModuleName, string $newEventType, int $actingUserId, ?string $newName = null): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->cloneTemplate($templateId, $newModuleName, $newEventType, $newName);
    }
    
    /**
     * Get template statistics by module
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @return array Module statistics
     */
    public function getModuleStats(int $companyId, int $actingUserId): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->getModuleStats($companyId);
    }
    
    /**
     * Get available modules for company
     * 
     * @param int $companyId Company ID
     * @param int $actingUserId ID of user performing the action
     * @return array Available modules
     */
    public function getAvailableModules(int $companyId, int $actingUserId): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->getAvailableModules($companyId);
    }
    
    /**
     * Get available event types for module
     * 
     * @param int $companyId Company ID
     * @param string $moduleName Module name
     * @param int $actingUserId ID of user performing the action
     * @return array Available event types
     */
    public function getEventTypesForModule(int $companyId, string $moduleName, int $actingUserId): array {
        $this->emailTemplateRepository->setCurrentUser($actingUserId);
        return $this->emailTemplateRepository->getEventTypesForModule($companyId, $moduleName);
    }
    
    /**
     * Validate template syntax for all content fields
     */
    private function validateTemplateSyntax(array $templateData, ?array $existingTemplate = null): void {
        $moduleName = $templateData['module_name'] ?? ($existingTemplate['module_name'] ?? '');
        
        if (empty($moduleName)) {
            throw new InvalidArgumentException('Module name is required for template validation');
        }
        
        // Get available placeholders for the module
        $availablePlaceholders = $this->placeholderService->getAvailablePlaceholders($moduleName);
        
        // Validate subject
        if (isset($templateData['subject'])) {
            $validation = $this->emailTemplateRepository->validateTemplateSyntax(
                $templateData['subject'], 
                $availablePlaceholders
            );
            if (!$validation['valid']) {
                throw new InvalidArgumentException(
                    'Subject validation failed: ' . implode(', ', $validation['errors'])
                );
            }
        }
        
        // Validate body_text
        if (isset($templateData['body_text']) && !empty($templateData['body_text'])) {
            $validation = $this->emailTemplateRepository->validateTemplateSyntax(
                $templateData['body_text'], 
                $availablePlaceholders
            );
            if (!$validation['valid']) {
                throw new InvalidArgumentException(
                    'Body text validation failed: ' . implode(', ', $validation['errors'])
                );
            }
        }
        
        // Validate body_html
        if (isset($templateData['body_html']) && !empty($templateData['body_html'])) {
            $validation = $this->emailTemplateRepository->validateTemplateSyntax(
                $templateData['body_html'], 
                $availablePlaceholders
            );
            if (!$validation['valid']) {
                throw new InvalidArgumentException(
                    'Body HTML validation failed: ' . implode(', ', $validation['errors'])
                );
            }
        }
    }
    
    /**
     * Extract placeholders from template content
     */
    private function extractPlaceholders(array $templateData, ?array $existingTemplate = null): array {
        $placeholders = [];
        
        // Extract from subject
        $subject = $templateData['subject'] ?? ($existingTemplate['subject'] ?? '');
        if ($subject) {
            $placeholders = array_merge($placeholders, $this->extractPlaceholdersFromContent($subject));
        }
        
        // Extract from body_text
        $bodyText = $templateData['body_text'] ?? ($existingTemplate['body_text'] ?? '');
        if ($bodyText) {
            $placeholders = array_merge($placeholders, $this->extractPlaceholdersFromContent($bodyText));
        }
        
        // Extract from body_html
        $bodyHtml = $templateData['body_html'] ?? ($existingTemplate['body_html'] ?? '');
        if ($bodyHtml) {
            $placeholders = array_merge($placeholders, $this->extractPlaceholdersFromContent($bodyHtml));
        }
        
        return array_unique($placeholders);
    }
    
    /**
     * Extract placeholders from content string
     */
    private function extractPlaceholdersFromContent(string $content): array {
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        return $matches[1] ?? [];
    }
    
    /**
     * Get count of triggers using this template
     */
    private function getTemplateTriggerCount(int $templateId): int {
        $sql = "SELECT COUNT(*) as count FROM email_triggers WHERE template_id = ?";
        $result = $this->db->getResults($sql, [$templateId], 'i');
        
        return (int)($result[0]['count'] ?? 0);
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
    
    /**
     * Sanitize HTML content for security
     * 
     * @param string $htmlContent HTML content to sanitize
     * @return string Sanitized HTML content
     */
    public function sanitizeHtmlContent(string $htmlContent): string {
        // Basic HTML sanitization - in production, use a proper HTML purifier library
        $allowedTags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><table><tr><td><th><thead><tbody><div><span>';
        
        // Strip dangerous tags and attributes
        $htmlContent = strip_tags($htmlContent, $allowedTags);
        
        // Remove dangerous attributes
        $htmlContent = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/', '', $htmlContent);
        $htmlContent = preg_replace('/\s*javascript\s*:\s*[^"\'>\s]*/', '', $htmlContent);
        
        return $htmlContent;
    }
    
    /**
     * Convert plain text to HTML with basic formatting
     * 
     * @param string $plainText Plain text content
     * @return string HTML formatted content
     */
    public function convertPlainTextToHtml(string $plainText): string {
        // Convert line breaks to <br> tags
        $html = nl2br(htmlspecialchars($plainText, ENT_QUOTES, 'UTF-8'));
        
        // Convert URLs to links
        $html = preg_replace(
            '/(https?:\/\/[^\s<>"]+)/',
            '<a href="$1" target="_blank">$1</a>',
            $html
        );
        
        // Convert email addresses to mailto links
        $html = preg_replace(
            '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
            '<a href="mailto:$1">$1</a>',
            $html
        );
        
        return $html;
    }
}