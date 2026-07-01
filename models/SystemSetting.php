<?php
/**
 * SystemSetting Model
 * Manages system-wide configuration parameters
 */

require_once 'BaseModel.php';

class SystemSetting extends BaseModel {
    protected $table = 'system_settings';
    protected $fillable = [
        'category', 'setting_key', 'setting_value', 'default_value',
        'data_type', 'description', 'validation_rules', 'is_required'
    ];
    
    // Data type constants
    const DATA_TYPE_STRING = 'string';
    const DATA_TYPE_INTEGER = 'integer';
    const DATA_TYPE_BOOLEAN = 'boolean';
    const DATA_TYPE_JSON = 'json';
    
    /**
     * Find setting by key
     */
    public function findByKey($key) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `setting_key` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$key], 's');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find settings by category
     */
    public function findByCategory($category) {
        return $this->findAll(['category' => $category], 'setting_key ASC');
    }
    
    /**
     * Get all settings grouped by category
     */
    public function getAllGroupedByCategory() {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY `category` ASC, `setting_key` ASC";
        $results = DatabaseConfig::getInstance()->getResults($sql);
        
        $grouped = [];
        foreach ($results as $setting) {
            if (!isset($grouped[$setting['category']])) {
                $grouped[$setting['category']] = [];
            }
            $grouped[$setting['category']][] = $setting;
        }
        
        // Ensure each category's settings are sorted by setting_key
        foreach ($grouped as $category => $settings) {
            usort($grouped[$category], function($a, $b) {
                return strcmp($a['setting_key'], $b['setting_key']);
            });
        }
        
        return $grouped;
    }
    
    /**
     * Cast setting value to appropriate data type
     */
    public function castValue($value, $dataType) {
        if ($value === null) {
            return null;
        }
        
        switch ($dataType) {
            case self::DATA_TYPE_INTEGER:
                return (int) $value;
                
            case self::DATA_TYPE_BOOLEAN:
                // Only accept specific boolean values
                $validBooleanValues = ['true', 'false', '1', '0', 'yes', 'no'];
                if (!in_array(strtolower((string)$value), $validBooleanValues)) {
                    throw new InvalidArgumentException('Invalid boolean value. Must be one of: ' . implode(', ', $validBooleanValues));
                }
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case self::DATA_TYPE_JSON:
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new InvalidArgumentException('Invalid JSON value: ' . json_last_error_msg());
                    }
                    return $decoded;
                }
                return $value;
                
            case self::DATA_TYPE_STRING:
            default:
                return (string) $value;
        }
    }
    
    /**
     * Validate setting value against validation rules
     */
    public function validateValue($value, $validationRules, $dataType) {
        // Cast value to appropriate type first
        try {
            $castedValue = $this->castValue($value, $dataType);
        } catch (InvalidArgumentException $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
        
        $errors = [];
        
        if (empty($validationRules)) {
            return ['valid' => true, 'errors' => []];
        }
        
        // Parse validation rules if they're JSON string
        if (is_string($validationRules)) {
            $rules = json_decode($validationRules, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['valid' => false, 'errors' => ['Invalid validation rules format']];
            }
        } else {
            $rules = $validationRules;
        }
        
        // Required validation
        if (isset($rules['required']) && $rules['required'] && ($castedValue === null || $castedValue === '')) {
            $errors[] = 'This field is required';
        }
        
        // Skip other validations if value is empty and not required
        if ($castedValue === null || $castedValue === '') {
            return ['valid' => empty($errors), 'errors' => $errors];
        }
        
        // String validations
        if ($dataType === self::DATA_TYPE_STRING) {
            if (isset($rules['min_length']) && strlen($castedValue) < $rules['min_length']) {
                $errors[] = "Minimum length is {$rules['min_length']} characters";
            }
            
            if (isset($rules['max_length']) && strlen($castedValue) > $rules['max_length']) {
                $errors[] = "Maximum length is {$rules['max_length']} characters";
            }
            
            if (isset($rules['pattern']) && !preg_match('/' . $rules['pattern'] . '/', $castedValue)) {
                $errors[] = 'Value does not match required pattern';
            }
        }
        
        // Integer validations
        if ($dataType === self::DATA_TYPE_INTEGER) {
            if (isset($rules['min_value']) && $castedValue < $rules['min_value']) {
                $errors[] = "Minimum value is {$rules['min_value']}";
            }
            
            if (isset($rules['max_value']) && $castedValue > $rules['max_value']) {
                $errors[] = "Maximum value is {$rules['max_value']}";
            }
        }
        
        // Allowed values validation (takes precedence over other constraints)
        if (isset($rules['allowed_values'])) {
            if (!in_array($castedValue, $rules['allowed_values'])) {
                $errors[] = 'Value must be one of: ' . implode(', ', $rules['allowed_values']);
            }
            // If allowed_values is set, skip other constraint checks as allowed_values takes precedence
            return ['valid' => empty($errors), 'errors' => $errors];
        }
        
        // JSON schema validation (basic)
        if ($dataType === self::DATA_TYPE_JSON && isset($rules['schema'])) {
            // Basic JSON structure validation - could be enhanced with a proper JSON schema validator
            if (!is_array($castedValue) && !is_object($castedValue)) {
                $errors[] = 'JSON value must be an object or array';
            }
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    /**
     * Get setting value with proper type casting
     */
    public function getSettingValue($key, $defaultValue = null) {
        $setting = $this->findByKey($key);
        
        if (!$setting) {
            return $defaultValue;
        }
        
        $value = $setting['setting_value'] ?? $setting['default_value'];
        
        if ($value === null) {
            return $defaultValue;
        }
        
        return $this->castValue($value, $setting['data_type']);
    }
    
    /**
     * Update setting value with validation
     */
    public function updateSettingValue($key, $value) {
        $setting = $this->findByKey($key);
        
        if (!$setting) {
            throw new InvalidArgumentException("Setting with key '{$key}' not found");
        }
        
        // Validate the new value
        $validation = $this->validateValue($value, $setting['validation_rules'], $setting['data_type']);
        
        if (!$validation['valid']) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $validation['errors']));
        }
        
        // Cast and store the value
        $castedValue = $this->castValue($value, $setting['data_type']);
        
        // Convert to string for storage (except null)
        $storageValue = $castedValue === null ? null : 
            ($setting['data_type'] === self::DATA_TYPE_JSON ? json_encode($castedValue) : (string) $castedValue);
        
        return $this->update($setting['id'], ['setting_value' => $storageValue]);
    }
    
    /**
     * Reset setting to default value
     */
    public function resetToDefault($key) {
        $setting = $this->findByKey($key);
        
        if (!$setting) {
            throw new InvalidArgumentException("Setting with key '{$key}' not found");
        }
        
        return $this->update($setting['id'], ['setting_value' => $setting['default_value']]);
    }
    
    /**
     * Check if setting has been modified from default
     */
    public function isModified($key) {
        $setting = $this->findByKey($key);
        
        if (!$setting) {
            return false;
        }
        
        return $setting['setting_value'] !== $setting['default_value'];
    }
}