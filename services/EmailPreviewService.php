<?php
/**
 * Email Preview Service
 * Handles email template preview functionality with sample data generation
 * 
 * Requirements: 3.4
 * - 3.4: Preview functionality with sample data
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/PlaceholderService.php';
require_once __DIR__ . '/../models/EmailTemplate.php';

class EmailPreviewService {
    private $db;
    private $placeholderService;
    private $emailTemplateModel;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->placeholderService = new PlaceholderService();
        $this->emailTemplateModel = new EmailTemplate();
    }
    
    /**
     * Generate preview of email template with sample data
     * Requirement 3.4
     * 
     * @param int $templateId Template ID
     * @param array|null $customData Custom data to use instead of sample data
     * @param int|null $entityId Optional entity ID for real data
     * @return array Preview result with subject and body
     * @throws Exception if template not found
     */
    public function generateTemplatePreview(int $templateId, ?array $customData = null, ?int $entityId = null): array {
        // Get template
        $template = $this->emailTemplateModel->find($templateId);
        if (!$template) {
            throw new InvalidArgumentException("Template not found");
        }
        
        // Get data for preview
        if ($customData !== null) {
            $previewData = $customData;
        } elseif ($entityId !== null) {
            $previewData = $this->placeholderService->extractRealData($template['module_name'], $entityId, $template['company_id']);
        } else {
            $previewData = $this->placeholderService->generateSampleData($template['module_name']);
        }
        
        // Generate preview
        $preview = [
            'template_id' => $templateId,
            'template_name' => $template['name'],
            'module_name' => $template['module_name'],
            'event_type' => $template['event_type'],
            'subject' => $this->placeholderService->replacePlaceholders($template['subject'], $previewData, $template['module_name']),
            'body_text' => null,
            'body_html' => null,
            'data_used' => $previewData,
            'placeholders_found' => [],
            'validation_errors' => []
        ];
        
        // Process body_text if available
        if (!empty($template['body_text'])) {
            $preview['body_text'] = $this->placeholderService->replacePlaceholders($template['body_text'], $previewData, $template['module_name']);
            
            // Validate placeholders in body_text
            $textValidation = $this->placeholderService->validatePlaceholders($template['body_text'], $template['module_name']);
            $preview['placeholders_found'] = array_merge($preview['placeholders_found'], $textValidation['placeholders']);
            $preview['validation_errors'] = array_merge($preview['validation_errors'], $textValidation['errors']);
        }
        
        // Process body_html if available
        if (!empty($template['body_html'])) {
            $preview['body_html'] = $this->placeholderService->replacePlaceholders($template['body_html'], $previewData, $template['module_name']);
            
            // Validate placeholders in body_html
            $htmlValidation = $this->placeholderService->validatePlaceholders($template['body_html'], $template['module_name']);
            $preview['placeholders_found'] = array_merge($preview['placeholders_found'], $htmlValidation['placeholders']);
            $preview['validation_errors'] = array_merge($preview['validation_errors'], $htmlValidation['errors']);
        }
        
        // Validate placeholders in subject
        $subjectValidation = $this->placeholderService->validatePlaceholders($template['subject'], $template['module_name']);
        $preview['placeholders_found'] = array_merge($preview['placeholders_found'], $subjectValidation['placeholders']);
        $preview['validation_errors'] = array_merge($preview['validation_errors'], $subjectValidation['errors']);
        
        // Remove duplicates
        $preview['placeholders_found'] = array_unique($preview['placeholders_found']);
        $preview['validation_errors'] = array_unique($preview['validation_errors']);
        
        return $preview;
    }
    
    /**
     * Generate preview with custom content (not from saved template)
     * Requirement 3.4
     * 
     * @param string $subject Email subject
     * @param string|null $bodyText Email body text
     * @param string|null $bodyHtml Email body HTML
     * @param string $moduleName Module name for placeholder context
     * @param array|null $customData Custom data to use
     * @return array Preview result
     */
    public function generateContentPreview(string $subject, ?string $bodyText, ?string $bodyHtml, string $moduleName, ?array $customData = null): array {
        // Get data for preview
        $previewData = $customData ?? $this->placeholderService->generateSampleData($moduleName);
        
        $preview = [
            'module_name' => $moduleName,
            'subject' => $this->placeholderService->replacePlaceholders($subject, $previewData, $moduleName),
            'body_text' => null,
            'body_html' => null,
            'data_used' => $previewData,
            'placeholders_found' => [],
            'validation_errors' => []
        ];
        
        // Process body_text if provided
        if (!empty($bodyText)) {
            $preview['body_text'] = $this->placeholderService->replacePlaceholders($bodyText, $previewData, $moduleName);
            
            // Validate placeholders in body_text
            $textValidation = $this->placeholderService->validatePlaceholders($bodyText, $moduleName);
            $preview['placeholders_found'] = array_merge($preview['placeholders_found'], $textValidation['placeholders']);
            $preview['validation_errors'] = array_merge($preview['validation_errors'], $textValidation['errors']);
        }
        
        // Process body_html if provided
        if (!empty($bodyHtml)) {
            $preview['body_html'] = $this->placeholderService->replacePlaceholders($bodyHtml, $previewData, $moduleName);
            
            // Validate placeholders in body_html
            $htmlValidation = $this->placeholderService->validatePlaceholders($bodyHtml, $moduleName);
            $preview['placeholders_found'] = array_merge($preview['placeholders_found'], $htmlValidation['placeholders']);
            $preview['validation_errors'] = array_merge($preview['validation_errors'], $htmlValidation['errors']);
        }
        
        // Validate placeholders in subject
        $subjectValidation = $this->placeholderService->validatePlaceholders($subject, $moduleName);
        $preview['placeholders_found'] = array_merge($preview['placeholders_found'], $subjectValidation['placeholders']);
        $preview['validation_errors'] = array_merge($preview['validation_errors'], $subjectValidation['errors']);
        
        // Remove duplicates
        $preview['placeholders_found'] = array_unique($preview['placeholders_found']);
        $preview['validation_errors'] = array_unique($preview['validation_errors']);
        
        return $preview;
    }
    
    /**
     * Validate template placeholders in real-time
     * Requirement 3.4
     * 
     * @param string $content Content to validate
     * @param string $moduleName Module name for context
     * @return array Validation result
     */
    public function validateTemplatePlaceholders(string $content, string $moduleName): array {
        return $this->placeholderService->validatePlaceholders($content, $moduleName);
    }
    
    /**
     * Get available placeholders for a module
     * Requirement 3.4
     * 
     * @param string $moduleName Module name
     * @return array Available placeholders with descriptions
     */
    public function getAvailablePlaceholders(string $moduleName): array {
        return $this->placeholderService->getModulePlaceholders($moduleName);
    }
    
    /**
     * Generate sample data for a module
     * Requirement 3.4
     * 
     * @param string $moduleName Module name
     * @param int|null $entityId Optional entity ID for real data
     * @return array Sample data
     */
    public function generateSampleDataForModule(string $moduleName, ?int $entityId = null): array {
        if ($entityId !== null) {
            return $this->placeholderService->extractRealData($moduleName, $entityId);
        } else {
            return $this->placeholderService->generateSampleData($moduleName);
        }
    }
    
    /**
     * Preview email with trigger context
     * 
     * @param int $triggerId Email trigger ID
     * @param array|null $eventData Event data for trigger context
     * @return array Preview result with trigger information
     */
    public function generateTriggerPreview(int $triggerId, ?array $eventData = null): array {
        // This would integrate with EmailTrigger model
        // For now, return basic structure
        return [
            'trigger_id' => $triggerId,
            'preview' => null,
            'recipients' => [],
            'conditions_met' => false,
            'error' => 'Trigger preview not yet implemented'
        ];
    }
    
    /**
     * Batch preview multiple templates
     * 
     * @param array $templateIds Array of template IDs
     * @param array|null $customData Custom data to use
     * @return array Array of preview results
     */
    public function batchPreviewTemplates(array $templateIds, ?array $customData = null): array {
        $previews = [];
        
        foreach ($templateIds as $templateId) {
            try {
                $previews[$templateId] = $this->generateTemplatePreview($templateId, $customData);
            } catch (Exception $e) {
                $previews[$templateId] = [
                    'error' => $e->getMessage(),
                    'template_id' => $templateId
                ];
            }
        }
        
        return $previews;
    }
    
    /**
     * Get preview statistics
     * 
     * @param array $preview Preview result
     * @return array Statistics about the preview
     */
    public function getPreviewStatistics(array $preview): array {
        $stats = [
            'total_placeholders' => count($preview['placeholders_found'] ?? []),
            'validation_errors' => count($preview['validation_errors'] ?? []),
            'has_text_body' => !empty($preview['body_text']),
            'has_html_body' => !empty($preview['body_html']),
            'subject_length' => strlen($preview['subject'] ?? ''),
            'text_body_length' => strlen($preview['body_text'] ?? ''),
            'html_body_length' => strlen($preview['body_html'] ?? ''),
            'is_valid' => empty($preview['validation_errors'])
        ];
        
        return $stats;
    }
}