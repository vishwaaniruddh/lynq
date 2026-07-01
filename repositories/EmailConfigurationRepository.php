<?php
/**
 * EmailConfiguration Repository with Company Isolation
 * Provides company-aware email configuration data access with audit logging
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/EmailConfiguration.php';

class EmailConfigurationRepository extends BaseRepository {
    protected $table = 'email_configurations';
    protected $companyIdColumn = 'company_id';
    
    private $emailConfigModel;
    
    public function __construct() {
        parent::__construct();
        $this->emailConfigModel = new EmailConfiguration();
    }
    
    /**
     * Create email configuration with audit logging
     * Override BaseRepository create to use model's encryption
     */
    public function create($data) {
        // Validate company access if user is set
        if ($this->currentUserId && isset($data[$this->companyIdColumn])) {
            $this->companyIsolationService->validateCompanyAccess(
                $this->currentUserId, 
                $data[$this->companyIdColumn]
            );
        }
        
        // Use model's create method directly for proper password encryption
        $config = $this->emailConfigModel->create($data);
        
        // Log configuration creation
        $this->logConfigurationChange('created', $config['id'], $data);
        
        return $config;
    }
    
    /**
     * Update email configuration with audit logging
     * Override BaseRepository update to use model's encryption
     */
    public function update($id, $data) {
        // Get existing configuration for audit logging
        $existing = $this->find($id);
        if (!$existing) {
            throw new Exception("Configuration not found or access denied");
        }
        
        // Use model's update method for proper password encryption
        $config = $this->emailConfigModel->update($id, $data);
        
        // Log configuration update
        $this->logConfigurationChange('updated', $id, $data, $existing);
        
        return $config;
    }
    
    /**
     * Delete email configuration with audit logging
     * Override BaseRepository delete to use model's method
     */
    public function delete($id) {
        // Get existing configuration for audit logging
        $existing = $this->find($id);
        if (!$existing) {
            throw new Exception("Configuration not found or access denied");
        }
        
        // Use model's delete method
        $result = $this->emailConfigModel->delete($id);
        
        if ($result) {
            // Log configuration deletion
            $this->logConfigurationChange('deleted', $id, [], $existing);
        }
        
        return $result;
    }
    
    /**
     * Find configuration by ID with company isolation
     * Override to remove sensitive data
     */
    public function find($id) {
        $config = parent::find($id);
        if ($config) {
            // Remove password_encrypted from result for security
            unset($config['password_encrypted']);
        }
        return $config;
    }
    
    /**
     * Test connection for email configuration
     */
    public function testConnection($id) {
        $config = parent::find($id); // Use parent to get full config with encrypted password
        if (!$config) {
            throw new Exception("Configuration not found or access denied");
        }
        
        $result = $this->emailConfigModel->testConnection($id);
        
        // Log connection test
        $this->logConfigurationChange('connection_tested', $id, [
            'test_result' => $result['success'] ? 'success' : 'failed',
            'test_message' => $result['message']
        ]);
        
        return $result;
    }
    
    /**
     * Get default configuration for company and type
     */
    public function getDefaultForCompany($companyId, $type) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailConfigModel->getDefaultForCompany($companyId, $type);
    }
    
    /**
     * Get configurations by company with company isolation
     */
    public function getByCompany($companyId, $type = null) {
        // Validate company access if user is set
        if ($this->currentUserId) {
            $this->companyIsolationService->validateCompanyAccess($this->currentUserId, $companyId);
        }
        
        return $this->emailConfigModel->getByCompany($companyId, $type);
    }
    
    /**
     * Get audit trail for configuration
     */
    public function getAuditTrail($configId, $limit = 50) {
        $config = parent::find($configId); // Use parent to check existence
        if (!$config) {
            throw new Exception("Configuration not found or access denied");
        }
        
        $sql = "SELECT cal.*, u.username, u.first_name, u.last_name 
                FROM email_configuration_audit_log cal 
                LEFT JOIN users u ON cal.user_id = u.id 
                WHERE cal.table_name = 'email_configurations' AND cal.record_id = ? 
                ORDER BY cal.created_at DESC 
                LIMIT ?";
        
        return $this->db->getResults($sql, [$configId, $limit], 'ii');
    }
    
    /**
     * Log configuration changes for audit trail
     */
    private function logConfigurationChange($action, $configId, $newData = [], $oldData = []) {
        if (!$this->currentUserId) {
            return; // Skip logging if no user context
        }
        
        $logData = [
            'table_name' => 'email_configurations',
            'record_id' => $configId,
            'action' => $action,
            'old_values' => !empty($oldData) ? json_encode($this->sanitizeForLog($oldData)) : null,
            'new_values' => !empty($newData) ? json_encode($this->sanitizeForLog($newData)) : null,
            'user_id' => $this->currentUserId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Insert audit log using direct query
        $sql = "INSERT INTO email_configuration_audit_log 
                (table_name, record_id, action, old_values, new_values, user_id, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bind_param('sisssisss',
                $logData['table_name'],
                $logData['record_id'],
                $logData['action'],
                $logData['old_values'],
                $logData['new_values'],
                $logData['user_id'],
                $logData['ip_address'],
                $logData['user_agent'],
                $logData['created_at']
            );
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Log audit failure but don't break the main operation
            error_log("Failed to log email configuration audit: " . $e->getMessage());
        }
    }
    
    /**
     * Sanitize data for audit logging (remove sensitive information)
     */
    private function sanitizeForLog($data) {
        $sanitized = $data;
        
        // Remove sensitive fields from audit log
        $sensitiveFields = ['password', 'password_encrypted'];
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }
        
        return $sanitized;
    }
}