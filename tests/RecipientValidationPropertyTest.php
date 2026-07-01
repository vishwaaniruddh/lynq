<?php
/**
 * Property Test for Recipient Validation
 * **Feature: email-management-system, Property 12: Recipient Validation**
 * **Validates: Requirements 4.4**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/EmailTriggerService.php';
require_once __DIR__ . '/../repositories/EmailTriggerRepository.php';

class RecipientValidationPropertyTest extends PropertyTestBase {
    
    private $emailTriggerService;
    private $emailTriggerRepository;
    
    // Test data IDs for cleanup
    private $createdTriggerIds = [];
    private $createdTemplateIds = [];
    private $testCompanyId;
    private $testUserId;
    
    public function __construct() {
        parent::__construct();
        $this->iterations = 20; // Reduce iterations for faster testing
        $this->emailTriggerService = new EmailTriggerService();
        $this->emailTriggerRepository = new EmailTriggerRepository();
        $this->setupTestData();
    }
    
    /**
     * Setup test company and user
     */
    private function setupTestData(): void {
        // Get or create test company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'adv' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testCompanyId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Email Company ' . uniqid(), 'adv', 1],
                'ssi'
            );
            $this->testCompanyId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create test user
        $result = $this->getResults(
            "SELECT id FROM users WHERE company_id = ? AND status = 1 LIMIT 1",
            [$this->testCompanyId],
            'i'
        );
        if (!empty($result)) {
            $this->testUserId = (int)$result[0]['id'];
        } else {
            $username = 'email_test_user_' . uniqid();
            $email = $username . '@test.com';
            
            // Get a valid role_id
            $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
            $roleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
            
            $stmt = $this->executeQuery(
                "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$username, $email, password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $this->testCompanyId, $roleId, 1],
                'sssssiis'
            );
            $this->testUserId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    public function runTests(): bool {
        echo "=== Recipient Validation Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 12: Recipient Validation
        $allPassed &= $this->runPropertyTest(
            "Property 12: Valid email addresses are accepted as recipients",
            [$this, 'testValidEmailAddressesAccepted']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 12: Invalid email addresses are rejected as recipients",
            [$this, 'testInvalidEmailAddressesRejected']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 12: Recipient availability is validated before queuing emails",
            [$this, 'testRecipientAvailabilityValidation']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 12: Valid email addresses are accepted as recipients
     * **Feature: email-management-system, Property 12: Recipient Validation**
     * **Validates: Requirements 4.4**
     */
    public function testValidEmailAddressesAccepted(): array {
        try {
            // Generate random valid email addresses
            $validEmails = [
                $this->generateRandomEmail(),
                'user.' . $this->generateRandomString(5) . '@example.com',
                'test+' . $this->generateRandomString(3) . '@domain.org',
                $this->generateRandomString(8) . '@' . $this->generateRandomString(6) . '.net'
            ];
            
            // Create a test template first
            $templateId = $this->createTestTemplate();
            
            // Create trigger with valid email recipients
            $triggerData = [
                'name' => 'Test Trigger ' . uniqid(),
                'module_name' => 'users',
                'event_type' => 'created',
                'template_id' => $templateId,
                'recipient_rules' => [
                    [
                        'type' => 'static',
                        'emails' => $validEmails
                    ]
                ],
                'company_id' => $this->testCompanyId
            ];
            
            // Create trigger - this should succeed
            $trigger = $this->emailTriggerService->createTrigger($triggerData, $this->testUserId);
            $this->createdTriggerIds[] = $trigger['id'];
            
            $this->assert(
                isset($trigger['id']) && $trigger['id'] > 0,
                "Trigger should be created successfully with valid email addresses"
            );
            
            // Test recipient extraction
            $eventData = [
                'company_id' => $this->testCompanyId,
                'user_id' => $this->testUserId,
                'site_id' => 1
            ];
            
            $recipients = $this->emailTriggerRepository->getRecipients($trigger['id'], $eventData);
            
            $this->assert(
                count($recipients) === count($validEmails),
                "All valid email addresses should be returned as recipients"
            );
            
            // Verify all returned recipients are valid email addresses
            foreach ($recipients as $recipient) {
                $this->assert(
                    filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false,
                    "Recipient '$recipient' should be a valid email address"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 12: Invalid email addresses are rejected as recipients
     * **Feature: email-management-system, Property 12: Recipient Validation**
     * **Validates: Requirements 4.4**
     */
    public function testInvalidEmailAddressesRejected(): array {
        try {
            // Generate random invalid email addresses
            $invalidEmails = [
                'invalid-email',
                '@domain.com',
                'user@',
                'user..name@domain.com',
                'user name@domain.com',
                'user@domain',
                ''
            ];
            
            // Mix with some valid emails
            $mixedEmails = array_merge($invalidEmails, [$this->generateRandomEmail()]);
            
            // Create a test template first
            $templateId = $this->createTestTemplate();
            
            // Create trigger with mixed valid/invalid email recipients
            $triggerData = [
                'name' => 'Test Trigger ' . uniqid(),
                'module_name' => 'users',
                'event_type' => 'created',
                'template_id' => $templateId,
                'recipient_rules' => [
                    [
                        'type' => 'static',
                        'emails' => $mixedEmails
                    ]
                ],
                'company_id' => $this->testCompanyId
            ];
            
            // Create trigger - this should succeed (validation happens during recipient extraction)
            $trigger = $this->emailTriggerService->createTrigger($triggerData, $this->testUserId);
            $this->createdTriggerIds[] = $trigger['id'];
            
            // Test recipient extraction - invalid emails should be filtered out
            $eventData = [
                'company_id' => $this->testCompanyId,
                'user_id' => $this->testUserId,
                'site_id' => 1
            ];
            
            $recipients = $this->emailTriggerRepository->getRecipients($trigger['id'], $eventData);
            
            // Should only return valid email addresses
            $this->assert(
                count($recipients) === 1,
                "Only valid email addresses should be returned as recipients"
            );
            
            // Verify all returned recipients are valid email addresses
            foreach ($recipients as $recipient) {
                $this->assert(
                    filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false,
                    "Recipient '$recipient' should be a valid email address"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 12: Recipient availability is validated before queuing emails
     * **Feature: email-management-system, Property 12: Recipient Validation**
     * **Validates: Requirements 4.4**
     */
    public function testRecipientAvailabilityValidation(): array {
        try {
            // Create a test template first
            $templateId = $this->createTestTemplate();
            
            // Create trigger with field-based recipient (should extract from event data)
            $triggerData = [
                'name' => 'Test Trigger ' . uniqid(),
                'module_name' => 'users',
                'event_type' => 'created',
                'template_id' => $templateId,
                'recipient_rules' => [
                    [
                        'type' => 'field',
                        'field' => 'user_email'
                    ]
                ],
                'company_id' => $this->testCompanyId
            ];
            
            $trigger = $this->emailTriggerService->createTrigger($triggerData, $this->testUserId);
            $this->createdTriggerIds[] = $trigger['id'];
            
            // Test with valid email in event data
            $eventDataWithValidEmail = [
                'company_id' => $this->testCompanyId,
                'user_id' => $this->testUserId,
                'user_email' => $this->generateRandomEmail(),
                'site_id' => 1
            ];
            
            $recipients = $this->emailTriggerRepository->getRecipients($trigger['id'], $eventDataWithValidEmail);
            
            $this->assert(
                count($recipients) === 1,
                "Should return one recipient when valid email is available in event data"
            );
            
            // Test with invalid email in event data
            $eventDataWithInvalidEmail = [
                'company_id' => $this->testCompanyId,
                'user_id' => $this->testUserId,
                'user_email' => 'invalid-email',
                'site_id' => 1
            ];
            
            $recipients = $this->emailTriggerRepository->getRecipients($trigger['id'], $eventDataWithInvalidEmail);
            
            $this->assert(
                count($recipients) === 0,
                "Should return no recipients when invalid email is in event data"
            );
            
            // Test with missing email field in event data
            $eventDataWithoutEmail = [
                'company_id' => $this->testCompanyId,
                'user_id' => $this->testUserId,
                'site_id' => 1
            ];
            
            $recipients = $this->emailTriggerRepository->getRecipients($trigger['id'], $eventDataWithoutEmail);
            
            $this->assert(
                count($recipients) === 0,
                "Should return no recipients when email field is missing from event data"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a test email template
     */
    private function createTestTemplate(): int {
        $uniqueName = 'Test Template ' . uniqid() . '_' . microtime(true);
        $stmt = $this->executeQuery(
            "INSERT INTO email_templates (name, subject, body_text, module_name, event_type, is_active, company_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $uniqueName,
                'Test Subject ' . uniqid(),
                'Test body content',
                'users', // Use different module to avoid unique constraint
                'created',
                1,
                $this->testCompanyId,
                $this->testUserId
            ],
            'sssssiis'
        );
        $templateId = $this->db->insert_id;
        $this->createdTemplateIds[] = $templateId;
        $stmt->close();
        
        return $templateId;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete created triggers
        foreach ($this->createdTriggerIds as $triggerId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM email_triggers WHERE id = ?",
                    [$triggerId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdTriggerIds = [];
        
        // Delete created templates
        foreach ($this->createdTemplateIds as $templateId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM email_templates WHERE id = ?",
                    [$templateId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdTemplateIds = [];
    }
}