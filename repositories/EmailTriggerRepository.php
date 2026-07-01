<?php
/**
 * EmailTrigger Repository with Company Isolation and Event Management
 * Provides company-aware email trigger data access with event management
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/EmailTrigger.php';

class EmailTriggerRepository extends BaseRepository {
    protected $table = 'email_triggers';
    protected $companyIdColumn = 'company_id';
    
    private $emailTriggerModel;
    
    public function __construct() {
        parent::__construct();
        $this->emailTriggerModel = new EmailTrigger();
    }
    
    /**
     * Create email trigger with validation
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
        return $this->emailTriggerModel->create($data);
    }
    
    /**
     * Update email trigger with validation
     */
    public function update($id, $data) {
        // First verify access to the trigger
        $existing = $this->find($id);
        if (!$existing) {
            throw new Exception("Trigger not found or access denied");
        }
        
        // Use model's update method for validation
        return $this->emailTriggerModel->update($id, $data);
    }
    
    /**
     * Find trigger with decoded JSON fields
     */
    public function find($id) {
        $trigger = parent::find($id);
        if ($trigger) {
            // Decode JSON fields
            if ($trigger['recipient_rules']) {
                $trigger['recipient_rules'] = json_decode($trigger['recipient_rules'], true);
            }
            if ($trigger['conditions']) {
                $trigger['conditions'] = json_decode($trigger['conditions'], true);
            }
        }
        return $trigger;
    }
    
    /**
     * Find triggers by module and event
     */
    public function findByModuleAndEvent($companyId, $moduleName, $eventType) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailTriggerModel->findByModuleAndEvent($companyId, $moduleName, $eventType);
    }
    
    /**
     * Get triggers by company with optional filtering
     */
    public function getByCompany($companyId, $filters = []) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailTriggerModel->getByCompany($companyId, $filters);
    }
    
    /**
     * Get triggers by module with company isolation
     */
    public function getByModule($moduleName) {
        $sql = "SELECT t.*, et.name as template_name, et.subject as template_subject 
                FROM `{$this->table}` t 
                LEFT JOIN `email_templates` et ON t.template_id = et.id 
                WHERE t.module_name = ?";
        $params = [$moduleName];
        $types = 's';
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                't.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY t.event_type, t.name";
        
        $results = $this->db->getResults($sql, $params, $types);
        
        // Decode JSON fields for each trigger
        foreach ($results as &$trigger) {
            if ($trigger['recipient_rules']) {
                $trigger['recipient_rules'] = json_decode($trigger['recipient_rules'], true);
            }
            if ($trigger['conditions']) {
                $trigger['conditions'] = json_decode($trigger['conditions'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get active triggers for event processing
     */
    public function getActiveTriggersForEvent($companyId, $moduleName, $eventType) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        $sql = "SELECT t.*, et.name as template_name, et.subject as template_subject,
                       et.body_text, et.body_html, et.placeholders
                FROM `{$this->table}` t 
                LEFT JOIN `email_templates` et ON t.template_id = et.id 
                WHERE t.company_id = ? AND t.module_name = ? AND t.event_type = ? AND t.is_active = 1
                ORDER BY t.name";
        
        $results = $this->db->getResults($sql, [$companyId, $moduleName, $eventType], 'iss');
        
        // Decode JSON fields for each trigger
        foreach ($results as &$trigger) {
            if ($trigger['recipient_rules']) {
                $trigger['recipient_rules'] = json_decode($trigger['recipient_rules'], true);
            }
            if ($trigger['conditions']) {
                $trigger['conditions'] = json_decode($trigger['conditions'], true);
            }
            if ($trigger['placeholders']) {
                $trigger['placeholders'] = json_decode($trigger['placeholders'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Test trigger with sample data
     */
    public function testTrigger($triggerId, $sampleEventData = []) {
        $trigger = $this->find($triggerId);
        if (!$trigger) {
            throw new Exception("Trigger not found or access denied");
        }
        
        return $this->emailTriggerModel->testTrigger($triggerId, $sampleEventData);
    }
    
    /**
     * Evaluate trigger conditions
     */
    public function evaluateConditions($triggerId, $eventData) {
        $trigger = $this->find($triggerId);
        if (!$trigger) {
            throw new Exception("Trigger not found or access denied");
        }
        
        return $this->emailTriggerModel->evaluateConditions($triggerId, $eventData);
    }
    
    /**
     * Get recipients for trigger
     */
    public function getRecipients($triggerId, $eventData) {
        $trigger = $this->find($triggerId);
        if (!$trigger) {
            throw new Exception("Trigger not found or access denied");
        }
        
        return $this->emailTriggerModel->getRecipients($triggerId, $eventData);
    }
    
    /**
     * Get trigger statistics by module
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
                    COUNT(DISTINCT event_type) as event_types,
                    COUNT(DISTINCT template_id) as templates_used
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
     * Search triggers with company isolation
     */
    public function search($searchTerm, $moduleName = null, $limit = 50) {
        $sql = "SELECT t.*, et.name as template_name, et.subject as template_subject 
                FROM `{$this->table}` t 
                LEFT JOIN `email_templates` et ON t.template_id = et.id 
                WHERE (t.name LIKE ? OR et.name LIKE ? OR et.subject LIKE ?)";
        
        $searchPattern = "%$searchTerm%";
        $params = [$searchPattern, $searchPattern, $searchPattern];
        $types = 'sss';
        
        // Add module filter if specified
        if ($moduleName) {
            $sql .= " AND t.module_name = ?";
            $params[] = $moduleName;
            $types .= 's';
        }
        
        // Add company filter if enabled and user is set
        if ($this->applyCompanyFilter && $this->currentUserId && $this->companyIdColumn) {
            $filter = $this->companyIsolationService->getCompanyFilterClause(
                $this->currentUserId, 
                't.company_id'
            );
            $sql .= " AND " . $filter['clause'];
            $params = array_merge($params, $filter['params']);
            $types .= $filter['types'];
        }
        
        $sql .= " ORDER BY t.module_name, t.event_type, t.name LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        $results = $this->db->getResults($sql, $params, $types);
        
        // Decode JSON fields for each trigger
        foreach ($results as &$trigger) {
            if ($trigger['recipient_rules']) {
                $trigger['recipient_rules'] = json_decode($trigger['recipient_rules'], true);
            }
            if ($trigger['conditions']) {
                $trigger['conditions'] = json_decode($trigger['conditions'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Clone trigger to different module or event
     */
    public function cloneTrigger($triggerId, $newModuleName, $newEventType, $newName = null) {
        $trigger = $this->find($triggerId);
        if (!$trigger) {
            throw new Exception("Trigger not found or access denied");
        }
        
        // Prepare data for new trigger
        $newData = $trigger;
        unset($newData['id']);
        unset($newData['created_at']);
        unset($newData['updated_at']);
        unset($newData['template_name']);
        unset($newData['template_subject']);
        
        $newData['module_name'] = $newModuleName;
        $newData['event_type'] = $newEventType;
        $newData['name'] = $newName ?: ($trigger['name'] . ' (Copy)');
        $newData['created_by'] = $this->currentUserId;
        
        return $this->create($newData);
    }
}