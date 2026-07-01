<?php
/**
 * EmailConfiguration Model
 * Manages email server configurations with encrypted credential handling
 */

require_once __DIR__ . '/BaseModel.php';

class EmailConfiguration extends BaseModel {
    protected $table = 'email_configurations';
    protected $fillable = [
        'name', 'type', 'host', 'port', 'username', 'password_encrypted',
        'encryption', 'is_default', 'is_active', 'company_id', 'created_by'
    ];
    protected $hidden = ['password_encrypted'];
    
    // Configuration types
    const TYPE_SMTP = 'smtp';
    const TYPE_IMAP = 'imap';
    
    // Encryption types
    const ENCRYPTION_NONE = 'none';
    const ENCRYPTION_SSL = 'ssl';
    const ENCRYPTION_TLS = 'tls';
    
    /**
     * Create new email configuration with encrypted password
     */
    public function create($data) {
        // Encrypt password before storing
        if (isset($data['password'])) {
            $data['password_encrypted'] = $this->encryptPassword($data['password']);
            unset($data['password']);
        }
        
        // Validate configuration data
        $this->validateConfiguration($data);
        
        // Handle default configuration logic
        if (isset($data['is_default']) && $data['is_default']) {
            $this->clearDefaultForCompany($data['company_id'], $data['type']);
        }
        
        return parent::create($data);
    }
    
    /**
     * Update email configuration
     */
    public function update($id, $data) {
        // Encrypt password if provided
        if (isset($data['password'])) {
            $data['password_encrypted'] = $this->encryptPassword($data['password']);
            unset($data['password']);
        }
        
        // Validate configuration data
        $this->validateConfiguration($data);
        
        // Handle default configuration logic
        if (isset($data['is_default']) && $data['is_default']) {
            $existing = $this->find($id);
            if ($existing) {
                $this->clearDefaultForCompany($existing['company_id'], $data['type'] ?? $existing['type']);
            }
        }
        
        return parent::update($id, $data);
    }
    
    /**
     * Get decrypted password for configuration
     */
    public function getDecryptedPassword($id) {
        $config = $this->find($id);
        if (!$config) {
            throw new InvalidArgumentException("Configuration not found");
        }
        
        return $this->decryptPassword($config['password_encrypted']);
    }
    
    /**
     * Test connection to email server
     */
    public function testConnection($id) {
        $config = $this->find($id);
        if (!$config) {
            throw new InvalidArgumentException("Configuration not found");
        }
        
        $password = $this->decryptPassword($config['password_encrypted']);
        
        if ($config['type'] === self::TYPE_SMTP) {
            return $this->testSMTPConnection($config, $password);
        } else {
            return $this->testIMAPConnection($config, $password);
        }
    }
    
    /**
     * Get default configuration for company and type
     */
    public function getDefaultForCompany($companyId, $type) {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `company_id` = ? AND `type` = ? AND `is_default` = 1 AND `is_active` = 1 
                LIMIT 1";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$companyId, $type], 'is');
        
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Get all configurations for company
     */
    public function getByCompany($companyId, $type = null) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `company_id` = ?";
        $params = [$companyId];
        $types = 'i';
        
        if ($type) {
            $sql .= " AND `type` = ?";
            $params[] = $type;
            $types .= 's';
        }
        
        $sql .= " ORDER BY `is_default` DESC, `name` ASC";
        
        $results = DatabaseConfig::getInstance()->getResults($sql, $params, $types);
        
        // Remove encrypted passwords from results
        foreach ($results as &$result) {
            $result = $this->removeHidden($result);
        }
        
        return $results;
    }
    
    /**
     * Validate configuration data
     */
    private function validateConfiguration($data) {
        $errors = [];
        
        // Validate type
        if (isset($data['type']) && !in_array($data['type'], [self::TYPE_SMTP, self::TYPE_IMAP])) {
            $errors[] = "Invalid configuration type";
        }
        
        // Validate encryption
        if (isset($data['encryption']) && !in_array($data['encryption'], [self::ENCRYPTION_NONE, self::ENCRYPTION_SSL, self::ENCRYPTION_TLS])) {
            $errors[] = "Invalid encryption type";
        }
        
        // Validate port
        if (isset($data['port']) && (!is_numeric($data['port']) || $data['port'] < 1 || $data['port'] > 65535)) {
            $errors[] = "Port must be between 1 and 65535";
        }
        
        // Validate required fields
        $requiredFields = ['name', 'host', 'port', 'username'];
        foreach ($requiredFields as $field) {
            if (isset($data[$field]) && empty(trim($data[$field]))) {
                $errors[] = "Field '$field' is required";
            }
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException("Validation failed: " . implode(', ', $errors));
        }
    }
    
    /**
     * Clear default flag for other configurations of same type and company
     */
    private function clearDefaultForCompany($companyId, $type) {
        $sql = "UPDATE `{$this->table}` SET `is_default` = 0 
                WHERE `company_id` = ? AND `type` = ?";
        DatabaseConfig::getInstance()->executeQuery($sql, [$companyId, $type], 'is');
    }
    
    /**
     * Encrypt password using application encryption key
     */
    private function encryptPassword($password) {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt password using application encryption key
     */
    private function decryptPassword($encryptedPassword) {
        $key = $this->getEncryptionKey();
        $data = base64_decode($encryptedPassword);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key from environment or generate one
     */
    private function getEncryptionKey() {
        // In production, this should come from environment variables
        // For now, using a default key - should be changed in production
        return hash('sha256', 'adv_crm_email_encryption_key_2026', true);
    }
    
    /**
     * Test SMTP connection
     */
    private function testSMTPConnection($config, $password) {
        try {
            $socket = fsockopen($config['host'], $config['port'], $errno, $errstr, 10);
            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "Connection failed: $errstr ($errno)"
                ];
            }
            
            // Read initial response
            $response = fgets($socket);
            if (strpos($response, '220') !== 0) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => "Invalid SMTP response: $response"
                ];
            }
            
            // Send EHLO command
            fwrite($socket, "EHLO " . gethostname() . "\r\n");
            $response = fgets($socket);
            
            // Send QUIT command
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test IMAP connection
     */
    private function testIMAPConnection($config, $password) {
        try {
            $socket = fsockopen($config['host'], $config['port'], $errno, $errstr, 10);
            if (!$socket) {
                return [
                    'success' => false,
                    'message' => "Connection failed: $errstr ($errno)"
                ];
            }
            
            // Read initial response
            $response = fgets($socket);
            if (strpos($response, '* OK') !== 0) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => "Invalid IMAP response: $response"
                ];
            }
            
            // Send LOGOUT command
            fwrite($socket, "A001 LOGOUT\r\n");
            fclose($socket);
            
            return [
                'success' => true,
                'message' => 'IMAP connection successful'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
}