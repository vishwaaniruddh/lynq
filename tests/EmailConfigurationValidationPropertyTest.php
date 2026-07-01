<?php
/**
 * Property Test for Email Configuration Validation and Storage
 * **Feature: email-management-system, Property 1: Email Configuration Validation and Storage**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/EmailConfiguration.php';

class EmailConfigurationValidationPropertyTest extends PropertyTestBase {
    private $testCompanyId = 1; // ADV company
    private $testUserId = 2326; // admin user
    private $createdConfigs = [];
    
    public function runTests() {
        echo "Starting Email Configuration Validation Property Tests\n";
        
        $allPassed = true;
        
        // Property 1: Email Configuration Validation and Storage
        $allPassed &= $this->runPropertyTest(
            "Email Configuration Validation and Storage",
            [$this, 'testConfigurationValidationAndStorage']
        );
        
        $this->cleanupTestData();
        
        if ($allPassed) {
            echo "All Email Configuration property tests passed!\n";
            return true;
        } else {
            echo "Some Email Configuration property tests failed!\n";
            return false;
        }
    }
    
    /**
     * Property 1: For any email configuration (SMTP or IMAP), when settings are saved, 
     * the system should validate connection parameters and store credentials in encrypted format
     */
    public function testConfigurationValidationAndStorage() {
        $model = new EmailConfiguration();
        
        // Generate random valid configuration data
        $configData = $this->generateValidConfigurationData();
        
        try {
            // Create configuration
            $config = $model->create($configData);
            $this->createdConfigs[] = $config['id'];
            
            // Verify configuration was created
            $this->assert($config !== null, "Configuration should be created");
            $this->assert(isset($config['id']), "Configuration should have an ID");
            
            // Verify required fields are present
            $this->assert($config['name'] === $configData['name'], "Name should match input");
            $this->assert($config['type'] === $configData['type'], "Type should match input");
            $this->assert($config['host'] === $configData['host'], "Host should match input");
            $this->assert($config['port'] == $configData['port'], "Port should match input");
            $this->assert($config['username'] === $configData['username'], "Username should match input");
            $this->assert($config['encryption'] === $configData['encryption'], "Encryption should match input");
            
            // Verify password is encrypted (not stored in plain text)
            $this->assert(!isset($config['password']), "Plain password should not be in result");
            $this->assert(isset($config['password_encrypted']), "Encrypted password should be present");
            $this->assert($config['password_encrypted'] !== $configData['password'], "Password should be encrypted");
            
            // Verify password can be decrypted correctly
            $decryptedPassword = $model->getDecryptedPassword($config['id']);
            $this->assert($decryptedPassword === $configData['password'], "Decrypted password should match original");
            
            // Verify company isolation
            $this->assert($config['company_id'] == $configData['company_id'], "Company ID should match");
            
            // Verify timestamps are set
            $this->assert(!empty($config['created_at']), "Created timestamp should be set");
            $this->assert(!empty($config['updated_at']), "Updated timestamp should be set");
            
            return [
                'success' => true,
                'data' => $configData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $configData
            ];
        }
    }
    
    /**
     * Generate valid email configuration data
     */
    private function generateValidConfigurationData() {
        $types = ['smtp', 'imap'];
        $encryptions = ['none', 'ssl', 'tls'];
        $type = $this->generateRandomChoice($types);
        
        // Generate appropriate port based on type and encryption
        $encryption = $this->generateRandomChoice($encryptions);
        $port = $this->generatePortForTypeAndEncryption($type, $encryption);
        
        return [
            'name' => 'Test Config ' . $this->generateRandomString(8),
            'type' => $type,
            'host' => $this->generateRandomString(10) . '.example.com',
            'port' => $port,
            'username' => $this->generateRandomEmail(),
            'password' => $this->generateRandomString(16),
            'encryption' => $encryption,
            'is_default' => $this->generateRandomBool(),
            'is_active' => true,
            'company_id' => $this->testCompanyId,
            'created_by' => $this->testUserId
        ];
    }
    
    /**
     * Generate appropriate port for type and encryption
     */
    private function generatePortForTypeAndEncryption($type, $encryption) {
        if ($type === 'smtp') {
            switch ($encryption) {
                case 'ssl':
                    return 465;
                case 'tls':
                    return 587;
                default:
                    return 25;
            }
        } else { // imap
            switch ($encryption) {
                case 'ssl':
                    return 993;
                case 'tls':
                    return 143;
                default:
                    return 143;
            }
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        if (!empty($this->createdConfigs)) {
            $model = new EmailConfiguration();
            foreach ($this->createdConfigs as $configId) {
                try {
                    $model->delete($configId);
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
            $this->createdConfigs = [];
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new EmailConfigurationValidationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}