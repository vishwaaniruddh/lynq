<?php
/**
 * Validation and Sanitization Utilities
 * Provides secure input validation and sanitization functions
 */

class ValidationUtils {
    
    /**
     * Validate and sanitize user input data
     * 
     * @param array $data Input data
     * @param array $rules Validation rules
     * @return array Validation result with sanitized data
     */
    public static function validateUserData($data, $rules) {
        $errors = [];
        $sanitized = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            // Apply validation rules
            foreach ($fieldRules as $rule => $ruleValue) {
                switch ($rule) {
                    case 'required':
                        if ($ruleValue && (is_null($value) || trim($value) === '')) {
                            $errors[$field][] = ucfirst($field) . ' is required';
                        }
                        break;
                        
                    case 'email':
                        if ($ruleValue && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = ucfirst($field) . ' must be a valid email address';
                        }
                        break;
                        
                    case 'min_length':
                        if ($value && strlen($value) < $ruleValue) {
                            $errors[$field][] = ucfirst($field) . " must be at least {$ruleValue} characters long";
                        }
                        break;
                        
                    case 'max_length':
                        if ($value && strlen($value) > $ruleValue) {
                            $errors[$field][] = ucfirst($field) . " must not exceed {$ruleValue} characters";
                        }
                        break;
                        
                    case 'numeric':
                        if ($ruleValue && $value && !is_numeric($value)) {
                            $errors[$field][] = ucfirst($field) . ' must be a number';
                        }
                        break;
                        
                    case 'integer':
                        if ($ruleValue && $value && !filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field][] = ucfirst($field) . ' must be an integer';
                        }
                        break;
                        
                    case 'alpha_numeric':
                        if ($ruleValue && $value && !ctype_alnum($value)) {
                            $errors[$field][] = ucfirst($field) . ' must contain only letters and numbers';
                        }
                        break;
                        
                    case 'password_strength':
                        if ($ruleValue && $value) {
                            $passwordErrors = self::validatePasswordStrength($value);
                            if (!empty($passwordErrors)) {
                                $errors[$field] = array_merge($errors[$field] ?? [], $passwordErrors);
                            }
                        }
                        break;
                }
            }
            
            // Sanitize the value if no errors
            if (!isset($errors[$field]) && $value !== null) {
                $sanitized[$field] = self::sanitizeInput($value, $fieldRules['type'] ?? 'string');
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized
        ];
    }
    
    /**
     * Sanitize input based on type
     * 
     * @param mixed $value Input value
     * @param string $type Data type
     * @return mixed Sanitized value
     */
    public static function sanitizeInput($value, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
                
            case 'integer':
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'url':
                return filter_var(trim($value), FILTER_SANITIZE_URL);
                
            case 'html':
                // For HTML content, use htmlspecialchars to prevent XSS
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                
            case 'string':
            default:
                // Remove HTML tags and encode special characters
                return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password Password to validate
     * @return array Array of error messages
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    /**
     * Sanitize array of data
     * 
     * @param array $data Input data array
     * @param string $type Data type for all values
     * @return array Sanitized data array
     */
    public static function sanitizeArray($data, $type = 'string') {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value, $type);
            } else {
                $sanitized[$key] = self::sanitizeInput($value, $type);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate CSRF token from request
     * 
     * @param array $requestData Request data containing csrf_token
     * @return bool True if valid
     */
    public static function validateCSRFFromRequest($requestData) {
        $token = $requestData['csrf_token'] ?? '';
        
        if (empty($token)) {
            return false;
        }
        
        $sessionService = new SessionService();
        return $sessionService->validateCSRFToken($token);
    }
    
    /**
     * Validate company access for user
     * 
     * @param int $userId User ID
     * @param int $targetCompanyId Target company ID
     * @return bool True if access allowed
     */
    public static function validateCompanyAccess($userId, $targetCompanyId) {
        try {
            $userModel = new User();
            $user = $userModel->findWithRelations($userId);
            
            if (!$user) {
                return false;
            }
            
            // ADV users can access all companies
            if ($user['company_type'] === COMPANY_TYPE_ADV) {
                return true;
            }
            
            // Contractor users can only access their own company
            return $user['company_id'] == $targetCompanyId;
            
        } catch (Exception $e) {
            error_log("Company access validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate role assignment restrictions
     * 
     * @param int $assignerUserId User making the assignment
     * @param int $targetUserId User being assigned role
     * @param int $roleId Role being assigned
     * @return array Validation result
     */
    public static function validateRoleAssignment($assignerUserId, $targetUserId, $roleId) {
        try {
            $userModel = new User();
            $roleModel = new Role();
            
            $assigner = $userModel->findWithRelations($assignerUserId);
            $target = $userModel->findWithRelations($targetUserId);
            $role = $roleModel->find($roleId);
            
            if (!$assigner || !$target || !$role) {
                return [
                    'valid' => false,
                    'error' => 'Invalid user or role data'
                ];
            }
            
            // ADV roles cannot be assigned to contractor users
            if ($role['company_type'] === COMPANY_TYPE_ADV && $target['company_type'] === COMPANY_TYPE_CONTRACTOR) {
                return [
                    'valid' => false,
                    'error' => 'ADV roles cannot be assigned to contractor users'
                ];
            }
            
            // Contractor roles cannot be assigned to ADV users
            if ($role['company_type'] === COMPANY_TYPE_CONTRACTOR && $target['company_type'] === COMPANY_TYPE_ADV) {
                return [
                    'valid' => false,
                    'error' => 'Contractor roles cannot be assigned to ADV users'
                ];
            }
            
            return ['valid' => true];
            
        } catch (Exception $e) {
            error_log("Role assignment validation error: " . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'System error during validation'
            ];
        }
    }
}