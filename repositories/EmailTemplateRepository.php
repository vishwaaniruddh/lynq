<?php
/**
 * EmailTemplate Repository with Company Isolation and Module Organization
 * Provides company-aware email template data access with module filtering
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/EmailTemplate.php';

class EmailTemplateRepository extends BaseRepository {
    protected $table = 'email_templates';
    protected $companyIdColumn = 'company_id';
    
    private $emailTemplateModel;
    
    public function __construct() {
        parent::__construct();
        $this->emailTemplateModel = new EmailTemplate();
    }
    
    /**
     * Create email template with validation
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
        return $this->emailTemplateModel->create($data);
    }
    
    /**
     * Update email template with validation
     */
    public function update($id, $data) {
        // First verify access to the template
        $existing = $this->find($id);
        if (!$existing) {
            throw new Exception("Template not found or access denied");
        }
        
        // Use model's update method for validation
        return $this->emailTemplateModel->update($id, $data);
    }
    
    /**
     * Find template by module and event type
     */
    public function findByModuleAndEvent($companyId, $moduleName, $eventType) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailTemplateModel->findByModuleAndEvent($companyId, $moduleName, $eventType);
    }
    
    /**
     * Get templates by module with company isolation
     */
    public function getByModule($moduleName) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `module_name` = ?";
        $params = [$moduleName];
        $types = 's';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY `event_type`, `name`";
        
        $results = $this->db->getResults($sql, $params, $types);
        
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
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailTemplateModel->getByCompany($companyId, $filters);
    }
    
    /**
     * Search templates with company isolation and module filtering
     */
    public function search($searchTerm, $moduleName = null, $limit = 50) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE (`name` LIKE ? OR `subject` LIKE ? OR `body_text` LIKE ? OR `body_html` LIKE ?)";
        
        $searchPattern = "%$searchTerm%";
        $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern];
        $types = 'ssss';
        
        // Add module filter if specified
        if ($moduleName) {
            $sql .= " AND `module_name` = ?";
            $params[] = $moduleName;
            $types .= 's';
        }
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY `module_name`, `event_type`, `name` LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        $results = $this->db->getResults($sql, $params, $types);
        
        // Decode placeholders for each template
        foreach ($results as &$template) {
            if ($template['placeholders']) {
                $template['placeholders'] = json_decode($template['placeholders'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get templates grouped by module
     */
    public function getGroupedByModule($companyId = null) {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        $types = '';
        $whereClause = [];
        
        // Add company filter
        if ($companyId) {
            // Validate company access if user is set
            if ($this->currentUserId) {
                $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
            }
            $whereClause[] = "`company_id` = ?";
            $params[] = $companyId;
            $types .= 'i';
        } elseif ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                $this->companyIdColumn
            );
            $whereClause[] = $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY `module_name`, `event_type`, `name`";
        
        $results = $this->db->getResults($sql, $params, $types);
        
        // Group by module
        $grouped = [];
        foreach ($results as $template) {
            if ($template['placeholders']) {
                $template['placeholders'] = json_decode($template['placeholders'], true);
            }
            
            $moduleName = $template['module_name'];
            if (!isset($grouped[$moduleName])) {
                $grouped[$moduleName] = [];
            }
            $grouped[$moduleName][] = $template;
        }
        
        return $grouped;
    }
    
    /**
     * Get template statistics by module
     */
    public function getModuleStats($companyId) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT 
                    module_name,
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    COUNT(DISTINCT event_type) as event_types
                FROM `{$this->table}` 
                WHERE company_id = ? 
                GROUP BY module_name 
                ORDER BY module_name";
        
        return $this->db->getResults($sql, [$companyId], 'i');
    }
    
    /**
     * Get available modules for company
     */
    public function getAvailableModules($companyId) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT DISTINCT module_name FROM `{$this->table}` WHERE company_id = ? ORDER BY module_name";
        $results = $this->db->getResults($sql, [$companyId], 'i');
        
        return array_column($results, 'module_name');
    }
    
    /**
     * Get available event types for module
     */
    public function getEventTypesForModule($companyId, $moduleName) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT DISTINCT event_type FROM `{$this->table}` 
                WHERE company_id = ? AND module_name = ? 
                ORDER BY event_type";
        $results = $this->db->getResults($sql, [$companyId, $moduleName], 'is');
        
        return array_column($results, 'event_type');
    }
    
    /**
     * Validate template syntax
     */
    public function validateTemplateSyntax($templateContent, $availablePlaceholders = []) {
        return $this->emailTemplateModel->validateTemplateSyntax($templateContent, $availablePlaceholders);
    }
    
    /**
     * Generate template preview
     */
    public function generatePreview($templateId, $sampleData = []) {
        $template = $this->find($templateId);
        if (!$template) {
            throw new Exception("Template not found or access denied");
        }
        
        return $this->emailTemplateModel->generatePreview($templateId, $sampleData);
    }
    
    /**
     * Clone template to different module or event
     */
    public function cloneTemplate($templateId, $newModuleName, $newEventType, $newName = null) {
        $template = $this->find($templateId);
        if (!$template) {
            throw new Exception("Template not found or access denied");
        }
        
        // Prepare data for new template
        $newData = $template;
        unset($newData['id']);
        unset($newData['created_at']);
        unset($newData['updated_at']);
        
        $newData['module_name'] = $newModuleName;
        $newData['event_type'] = $newEventType;
        $newData['name'] = $newName ?: ($template['name'] . ' (Copy)');
        $newData['created_by'] = $this->currentUserId;
        
        return $this->create($newData);
    }
    
    /**
     * Get template version history (if versioning is implemented)
     */
    public function getVersionHistory($templateId, $limit = 10) {
        // This would be implemented if template versioning is added
        // For now, return empty array
        return [];
    }
}