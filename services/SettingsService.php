<?php
/**
 * Settings Service
 * Handles business logic for system settings management operations
 * 
 * This service enforces:
 * - Input validation according to setting constraints
 * - Audit logging for all setting changes
 * - Proper data type casting and storage
 * - Batch operations with atomic transactions
 */

require_once __DIR__ . '/../config/autoload.php';

class SettingsService {
    private $db;
    private $settingsRepository;
    private $settingsAuditRepository;
    private $systemSettingModel;
    private $settingsAuditModel;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->settingsRepository = new SettingsRepository();
        $this->settingsAuditRepository = new SettingsAuditRepository();
        $this->systemSettingModel = new SystemSetting();
        $this->settingsAuditModel = new SettingsAudit();
    }
    
    /**
     * Get all settings grouped by category with proper ordering
     * 
     * @return array Settings grouped by category
     */
    public function getSettingsByCategory(): array {
        return $this->systemSettingModel->getAllGroupedByCategory();
    }
    
    /**
     * Get a specific setting value with default value fallback
     * 
     * @param string $key Setting key
     * @param mixed $defaultValue Default value if setting not found or has no value
     * @return mixed Setting value with proper type casting
     */
    public function getSetting(string $key, $defaultValue = null) {
        return $this->systemSettingModel->getSettingValue($key, $defaultValue);
    }
    
    /**
     * Update a setting with validation and audit logging
     * 
     * @param string $key Setting key
     * @param mixed $value New setting value
     * @param int $userId User ID performing the update
     * @return bool Success status
     * @throws Exception on validation failure
     */
    public function updateSetting(string $key, $value, int $userId): bool {
        // Get the setting to validate against
        $setting = $this->systemSettingModel->findByKey($key);
        if (!$setting) {
            throw new InvalidArgumentException("Setting with key '{$key}' not found");
        }
        
        // Store old value for audit
        $oldValue = $setting['setting_value'];
        
        // Validate the new value
        $validation = $this->validateSettingValue($key, $value);
        if (!$validation['valid']) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $validation['errors']));
        }
        
        // Cast and prepare value for storage
        $castedValue = $this->systemSettingModel->castValue($value, $setting['data_type']);
        $storageValue = $castedValue === null ? null : 
            ($setting['data_type'] === SystemSetting::DATA_TYPE_JSON ? json_encode($castedValue) : (string) $castedValue);
        
        // Update the setting
        $updatedSetting = $this->systemSettingModel->update($setting['id'], ['setting_value' => $storageValue]);
        $success = $updatedSetting !== null;
        
        if ($success) {
            // Create audit log entry
            $this->settingsAuditModel->createAuditEntry(
                $setting['id'],
                $userId,
                $oldValue,
                $storageValue,
                SettingsAudit::ACTION_UPDATE
            );
        }
        
        return $success;
    }
    
    /**
     * Reset a setting to its default value with confirmation workflow
     * 
     * @param string $key Setting key
     * @param int $userId User ID performing the reset
     * @param bool $confirmed Whether the reset has been confirmed
     * @return array Result with success status and confirmation requirement
     * @throws Exception on validation failure
     */
    public function resetSetting(string $key, int $userId, bool $confirmed = false): array {
        // Get the setting
        $setting = $this->systemSettingModel->findByKey($key);
        if (!$setting) {
            throw new InvalidArgumentException("Setting with key '{$key}' not found");
        }
        
        // Check if confirmation is required and not provided
        if (!$confirmed) {
            return [
                'success' => false,
                'requires_confirmation' => true,
                'message' => "Are you sure you want to reset '{$key}' to its default value?",
                'current_value' => $setting['setting_value'],
                'default_value' => $setting['default_value']
            ];
        }
        
        // Store old value for audit
        $oldValue = $setting['setting_value'];
        $defaultValue = $setting['default_value'];
        
        // Reset to default
        $resetResult = $this->systemSettingModel->resetToDefault($key);
        $success = $resetResult !== null;
        
        if ($success) {
            // Create audit log entry
            $this->settingsAuditModel->createAuditEntry(
                $setting['id'],
                $userId,
                $oldValue,
                $defaultValue,
                SettingsAudit::ACTION_RESET
            );
        }
        
        return [
            'success' => $success,
            'requires_confirmation' => false,
            'message' => $success ? "Setting '{$key}' has been reset to default value" : "Failed to reset setting"
        ];
    }
    
    /**
     * Validate setting value with constraint checking
     * 
     * @param string $key Setting key
     * @param mixed $value Value to validate
     * @return array Validation result with valid flag and error messages
     */
    public function validateSettingValue(string $key, $value): array {
        $setting = $this->systemSettingModel->findByKey($key);
        if (!$setting) {
            return ['valid' => false, 'errors' => ["Setting with key '{$key}' not found"]];
        }
        
        return $this->systemSettingModel->validateValue(
            $value,
            $setting['validation_rules'],
            $setting['data_type']
        );
    }
    
    /**
     * Update multiple settings with atomic transactions
     * 
     * @param array $settings Array of setting key-value pairs
     * @param int $userId User ID performing the updates
     * @return array Result with success status and any validation errors
     */
    public function updateMultipleSettings(array $settings, int $userId): array {
        // First, validate all settings before making any changes
        $validationResult = $this->validateMultipleSettings($settings);
        if (!$validationResult['valid']) {
            return $validationResult;
        }
        
        // Start transaction for atomic updates
        $this->db->getConnection()->autocommit(false);
        
        try {
            $updatedSettings = [];
            $auditEntries = [];
            
            foreach ($settings as $key => $value) {
                $setting = $this->systemSettingModel->findByKey($key);
                if (!$setting) {
                    throw new Exception("Setting '{$key}' not found during update");
                }
                
                // Store old value for audit
                $oldValue = $setting['setting_value'];
                
                // Cast and prepare value for storage
                $castedValue = $this->systemSettingModel->castValue($value, $setting['data_type']);
                $storageValue = $castedValue === null ? null : 
                    ($setting['data_type'] === SystemSetting::DATA_TYPE_JSON ? json_encode($castedValue) : (string) $castedValue);
                
                // Update the setting
                $updateResult = $this->systemSettingModel->update($setting['id'], ['setting_value' => $storageValue]);
                $success = $updateResult !== null;
                if (!$success) {
                    throw new Exception("Failed to update setting '{$key}'");
                }
                
                $updatedSettings[] = $key;
                
                // Prepare audit entry
                $auditEntries[] = [
                    'setting_id' => $setting['id'],
                    'user_id' => $userId,
                    'old_value' => $oldValue,
                    'new_value' => $storageValue,
                    'action' => SettingsAudit::ACTION_UPDATE
                ];
            }
            
            // Create all audit entries
            foreach ($auditEntries as $auditData) {
                $this->settingsAuditModel->createAuditEntry(
                    $auditData['setting_id'],
                    $auditData['user_id'],
                    $auditData['old_value'],
                    $auditData['new_value'],
                    $auditData['action']
                );
            }
            
            // Commit transaction
            $this->db->getConnection()->commit();
            $this->db->getConnection()->autocommit(true);
            
            return [
                'valid' => true,
                'success' => true,
                'updated_settings' => $updatedSettings,
                'message' => 'All settings updated successfully'
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on any error
            $this->db->getConnection()->rollback();
            $this->db->getConnection()->autocommit(true);
            
            return [
                'valid' => false,
                'success' => false,
                'errors' => ['Transaction failed: ' . $e->getMessage()],
                'message' => 'Failed to update settings - no changes were made'
            ];
        }
    }
    
    /**
     * Validate multiple settings for batch validation
     * 
     * @param array $settings Array of setting key-value pairs
     * @return array Validation result with valid flag and any errors
     */
    public function validateMultipleSettings(array $settings): array {
        $errors = [];
        $validSettings = [];
        
        foreach ($settings as $key => $value) {
            $validation = $this->validateSettingValue($key, $value);
            if (!$validation['valid']) {
                $errors[$key] = $validation['errors'];
            } else {
                $validSettings[] = $key;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_settings' => $validSettings,
            'message' => empty($errors) ? 'All settings are valid' : 'Some settings have validation errors'
        ];
    }
    
    /**
     * Get audit trail for a specific setting
     * 
     * @param string $key Setting key
     * @param array $filters Optional filters (start_date, end_date, user_id, action)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Audit trail entries
     */
    public function getSettingAuditTrail(string $key, array $filters = [], int $limit = 50, int $offset = 0): array {
        $setting = $this->systemSettingModel->findByKey($key);
        if (!$setting) {
            return [];
        }
        
        return $this->settingsAuditRepository->getAuditTrail($setting['id'], $filters, $limit, $offset);
    }
    
    /**
     * Get all audit trail with filtering options
     * 
     * @param array $filters Optional filters (start_date, end_date, user_id, category, action, setting_key)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Audit trail entries
     */
    public function getAllAuditTrail(array $filters = [], int $limit = 100, int $offset = 0): array {
        return $this->settingsAuditRepository->getAllAuditTrail($filters, $limit, $offset);
    }
    
    /**
     * Get audit trail count for pagination
     * 
     * @param string|null $key Optional setting key to filter by
     * @param array $filters Optional filters
     * @return int Total count of audit entries
     */
    public function getAuditTrailCount(?string $key = null, array $filters = []): int {
        if ($key) {
            $setting = $this->systemSettingModel->findByKey($key);
            if (!$setting) {
                return 0;
            }
            return $this->settingsAuditRepository->getAuditTrailCount($setting['id'], $filters);
        }
        
        return $this->settingsAuditRepository->getAuditTrailCount(null, $filters);
    }
    
    /**
     * Export audit trail data
     * 
     * @param string $format Export format ('json' or 'csv')
     * @param array $filters Optional filters
     * @return string Exported data
     */
    public function exportAuditTrail(string $format = 'json', array $filters = []): string {
        return $this->settingsAuditRepository->exportAuditData($format, $filters);
    }
    
    /**
     * Get audit statistics
     * 
     * @param array $filters Optional filters (start_date, end_date, category)
     * @return array Statistics data
     */
    public function getAuditStatistics(array $filters = []): array {
        return $this->settingsAuditRepository->getAuditStatistics($filters);
    }
    
    /**
     * Get recent audit activity
     * 
     * @param int $limit Number of recent entries to return
     * @return array Recent audit entries
     */
    public function getRecentAuditActivity(int $limit = 10): array {
        return $this->settingsAuditRepository->getRecentActivity($limit);
    }
    
    /**
     * Check if a setting has been modified from its default value
     * 
     * @param string $key Setting key
     * @return bool True if setting has been modified
     */
    public function isSettingModified(string $key): bool {
        return $this->systemSettingModel->isModified($key);
    }
    
    /**
     * Get all available setting categories
     * 
     * @return array List of unique categories
     */
    public function getAvailableCategories(): array {
        $sql = "SELECT DISTINCT category FROM system_settings ORDER BY category";
        $results = $this->db->getResults($sql);
        
        return array_column($results, 'category');
    }
    
    /**
     * Get settings count by category
     * 
     * @return array Category counts
     */
    public function getSettingsCountByCategory(): array {
        $sql = "SELECT category, COUNT(*) as count FROM system_settings GROUP BY category ORDER BY category";
        $results = $this->db->getResults($sql);
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['category']] = (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Seed default settings (used during installation/migration)
     * 
     * @return bool Success status
     */
    public function seedDefaultSettings(): bool {
        // This method would be called during migration to populate default settings
        // Implementation would depend on the specific default settings required
        // For now, return true as this is typically handled by migrations
        return true;
    }
    
    /**
     * Verify integrity of audit entries
     * 
     * @param array $auditIds Optional array of specific audit IDs to verify
     * @return array Integrity verification results
     */
    public function verifyAuditIntegrity(array $auditIds = []): array {
        return $this->settingsAuditRepository->verifyAuditIntegrity($auditIds);
    }
    
    /**
     * Get comprehensive audit integrity report
     * 
     * @param array $filters Optional filters for audit entries
     * @return array Detailed integrity report
     */
    public function getAuditIntegrityReport(array $filters = []): array {
        return $this->settingsAuditRepository->getAuditIntegrityReport($filters);
    }
    
    /**
     * Get forensic audit trail with enhanced metadata
     * 
     * @param array $filters Optional filters including integrity_status
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Enhanced audit trail for forensic analysis
     */
    public function getForensicAuditTrail(array $filters = [], int $limit = 100, int $offset = 0): array {
        return $this->settingsAuditRepository->getForensicAuditTrail($filters, $limit, $offset);
    }
    
    /**
     * Get audit trail security summary
     * 
     * @param array $filters Optional filters
     * @return array Security-focused audit summary
     */
    public function getAuditSecuritySummary(array $filters = []): array {
        $auditData = $this->settingsAuditRepository->getAllAuditTrail($filters, 0, 0);
        
        $summary = [
            'total_changes' => count($auditData),
            'unique_users' => count(array_unique(array_column($auditData, 'user_id'))),
            'unique_ip_addresses' => count(array_unique(array_column($auditData, 'ip_address'))),
            'unique_sessions' => count(array_unique(array_filter(array_column($auditData, 'session_id')))),
            'actions_breakdown' => [],
            'integrity_status' => [
                'protected_entries' => 0,
                'legacy_entries' => 0
            ],
            'time_range' => [
                'earliest' => null,
                'latest' => null
            ],
            'suspicious_patterns' => []
        ];
        
        // Analyze actions
        foreach ($auditData as $entry) {
            $action = $entry['action'];
            $summary['actions_breakdown'][$action] = ($summary['actions_breakdown'][$action] ?? 0) + 1;
            
            // Count integrity status
            if (!empty($entry['integrity_hash'])) {
                $summary['integrity_status']['protected_entries']++;
            } else {
                $summary['integrity_status']['legacy_entries']++;
            }
            
            // Track time range
            $timestamp = $entry['timestamp'];
            if ($summary['time_range']['earliest'] === null || $timestamp < $summary['time_range']['earliest']) {
                $summary['time_range']['earliest'] = $timestamp;
            }
            if ($summary['time_range']['latest'] === null || $timestamp > $summary['time_range']['latest']) {
                $summary['time_range']['latest'] = $timestamp;
            }
        }
        
        // Detect suspicious patterns
        $summary['suspicious_patterns'] = $this->detectSuspiciousPatterns($auditData);
        
        return $summary;
    }
    
    /**
     * Detect suspicious patterns in audit data
     * 
     * @param array $auditData Raw audit data
     * @return array Detected suspicious patterns
     */
    private function detectSuspiciousPatterns(array $auditData): array {
        $patterns = [];
        
        // Group by user and IP for analysis
        $userActivity = [];
        $ipActivity = [];
        
        foreach ($auditData as $entry) {
            $userId = $entry['user_id'];
            $ipAddress = $entry['ip_address'];
            $timestamp = strtotime($entry['timestamp']);
            
            // Track user activity
            if (!isset($userActivity[$userId])) {
                $userActivity[$userId] = [];
            }
            $userActivity[$userId][] = $timestamp;
            
            // Track IP activity
            if (!isset($ipActivity[$ipAddress])) {
                $ipActivity[$ipAddress] = [];
            }
            $ipActivity[$ipAddress][] = $timestamp;
        }
        
        // Detect rapid successive changes (potential automation)
        foreach ($userActivity as $userId => $timestamps) {
            sort($timestamps);
            $rapidChanges = 0;
            for ($i = 1; $i < count($timestamps); $i++) {
                if ($timestamps[$i] - $timestamps[$i-1] < 5) { // Less than 5 seconds apart
                    $rapidChanges++;
                }
            }
            
            if ($rapidChanges > 5) {
                $patterns[] = [
                    'type' => 'rapid_changes',
                    'description' => "User ID {$userId} made {$rapidChanges} rapid successive changes",
                    'severity' => 'medium',
                    'user_id' => $userId
                ];
            }
        }
        
        // Detect unusual IP activity
        foreach ($ipActivity as $ipAddress => $timestamps) {
            if (count($timestamps) > 50) { // More than 50 changes from single IP
                $patterns[] = [
                    'type' => 'high_volume_ip',
                    'description' => "IP address {$ipAddress} performed " . count($timestamps) . " changes",
                    'severity' => 'high',
                    'ip_address' => $ipAddress
                ];
            }
        }
        
        return $patterns;
    }
}