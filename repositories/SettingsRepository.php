<?php
/**
 * Settings Repository
 * Provides data access for system settings with global scope (no company filtering)
 */

require_once __DIR__ . '/BaseRepository.php';

class SettingsRepository extends BaseRepository {
    protected $table = 'system_settings';
    protected $companyIdColumn = null; // System settings are global
    protected $applyCompanyFilter = false; // Disable company filtering for global settings
    
    /**
     * Find settings by category
     */
    public function findByCategory($category) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `category` = ? ORDER BY `setting_key`";
        $params = [$category];
        $types = 's';
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Find setting by key
     */
    public function findByKey($key) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `setting_key` = ?";
        $params = [$key];
        $types = 's';
        
        $result = $this->db->getResults($sql, $params, $types);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Update setting with validation
     */
    public function updateSetting($key, $value, $userId) {
        // First, get the setting to validate
        $setting = $this->findByKey($key);
        if (!$setting) {
            throw new Exception("Setting not found: $key");
        }
        
        // Validate the value according to the setting's data type and rules
        $this->validateSettingValue($setting, $value);
        
        // Update the setting
        $sql = "UPDATE `{$this->table}` SET `setting_value` = ?, `updated_at` = NOW() WHERE `setting_key` = ?";
        $params = [$value, $key];
        $types = 'ss';
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        if ($success) {
            // Log the change in audit table
            $this->logSettingChange($setting['id'], $userId, $setting['setting_value'], $value, 'UPDATE');
        }
        
        return $success;
    }
    
    /**
     * Reset setting to default value
     */
    public function resetToDefault($key, $userId) {
        $setting = $this->findByKey($key);
        if (!$setting) {
            throw new Exception("Setting not found: $key");
        }
        
        $oldValue = $setting['setting_value'];
        $defaultValue = $setting['default_value'];
        
        $sql = "UPDATE `{$this->table}` SET `setting_value` = `default_value`, `updated_at` = NOW() WHERE `setting_key` = ?";
        $params = [$key];
        $types = 's';
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $success = $stmt->affected_rows > 0;
        $stmt->close();
        
        if ($success) {
            // Log the reset in audit table
            $this->logSettingChange($setting['id'], $userId, $oldValue, $defaultValue, 'RESET');
        }
        
        return $success;
    }
    
    /**
     * Get all settings grouped by category
     */
    public function getAllByCategory() {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY `category`, `setting_key`";
        $results = $this->db->getResults($sql);
        
        $grouped = [];
        foreach ($results as $setting) {
            $grouped[$setting['category']][] = $setting;
        }
        
        return $grouped;
    }
    
    /**
     * Validate setting value according to its data type and validation rules
     */
    private function validateSettingValue($setting, $value) {
        $dataType = $setting['data_type'];
        $validationRules = $setting['validation_rules'] ? json_decode($setting['validation_rules'], true) : [];
        
        // Check if required
        if ($setting['is_required'] && ($value === null || trim($value) === '')) {
            throw new Exception("Setting '{$setting['setting_key']}' is required and cannot be empty");
        }
        
        // Data type validation
        switch ($dataType) {
            case 'integer':
                if (!is_numeric($value) || strpos($value, '.') !== false) {
                    throw new Exception("Setting '{$setting['setting_key']}' must be a valid integer");
                }
                $value = (int)$value;
                
                // Check min/max values
                if (isset($validationRules['min_value']) && $value < $validationRules['min_value']) {
                    throw new Exception("Setting '{$setting['setting_key']}' must be at least {$validationRules['min_value']}");
                }
                if (isset($validationRules['max_value']) && $value > $validationRules['max_value']) {
                    throw new Exception("Setting '{$setting['setting_key']}' must not exceed {$validationRules['max_value']}");
                }
                break;
                
            case 'boolean':
                if (!in_array($value, ['true', 'false', '1', '0', 'yes', 'no'])) {
                    throw new Exception("Setting '{$setting['setting_key']}' must be a valid boolean value");
                }
                break;
                
            case 'json':
                if (!empty(trim($value))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Setting '{$setting['setting_key']}' must be valid JSON");
                    }
                }
                break;
                
            case 'string':
            default:
                // String validation
                if (isset($validationRules['min_length']) && strlen($value) < $validationRules['min_length']) {
                    throw new Exception("Setting '{$setting['setting_key']}' must be at least {$validationRules['min_length']} characters long");
                }
                if (isset($validationRules['max_length']) && strlen($value) > $validationRules['max_length']) {
                    throw new Exception("Setting '{$setting['setting_key']}' must not exceed {$validationRules['max_length']} characters");
                }
                if (isset($validationRules['pattern']) && !preg_match('/' . $validationRules['pattern'] . '/', $value)) {
                    throw new Exception("Setting '{$setting['setting_key']}' does not match the required pattern");
                }
                if (isset($validationRules['allowed_values']) && !in_array($value, $validationRules['allowed_values'])) {
                    throw new Exception("Setting '{$setting['setting_key']}' must be one of: " . implode(', ', $validationRules['allowed_values']));
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Log setting change to audit table
     */
    private function logSettingChange($settingId, $userId, $oldValue, $newValue, $action) {
        $sql = "INSERT INTO `settings_audit` 
                (`setting_id`, `user_id`, `old_value`, `new_value`, `action`, `ip_address`, `user_agent`) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $params = [$settingId, $userId, $oldValue, $newValue, $action, $ipAddress, $userAgent];
        $types = 'iisssss';
        
        $stmt = $this->db->executeQuery($sql, $params, $types);
        $stmt->close();
    }
}