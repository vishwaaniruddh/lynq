<?php
/**
 * Property Test for Event-Driven Email Queuing
 * **Feature: email-management-system, Property 11: Event-Driven Email Queuing**
 * **Validates: Requirements 4.3, 4.5, 5.2, 5.3, 5.4, 5.5**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/EmailModuleIntegrationService.php';
require_once __DIR__ . '/../services/EmailTriggerService.php';
require_once __DIR__ . '/../repositories/EmailTriggerRepository.php';
require_once __DIR__ . '/../repositories/EmailTemplateRepository.php';
require_once __DIR__ . '/../repositories/EmailQueueRepository.php';

class EventDrivenEmailQueuingPropertyTest extends PropertyTestBase {
    
    private $moduleIntegrationService;
    private $emailTriggerService;
    private $emailTriggerRepository;
    private $emailTemplateRepository;
    private $emailQueueRepository;
    
    // Test data IDs for cleanup
    private $createdTriggerIds = [];
    private $createdTemplateIds = [];
    private $createdEmailIds = [];
    private $testCompanyId;
    private $testUserId;
    
    public function __construct() {
        parent::__construct();
        $this->iterations = 10; // Reasonable number for testing
        $this->moduleIntegrationService = new EmailModuleIntegrationService();
        $this->emailTriggerService = new EmailTriggerService();
        $this->emailTriggerRepository = new EmailTriggerRepository();
        $this->emailTemplateRepository = new EmailTemplateRepository();
        $this->emailQueueRepository = new EmailQueueRepository();
        $this->setupTestData();
    }
    
    /**
     * Setup test company and user
     */
    private function setupTestData(): void {
        // Create unique test company for each test run
        $companyName = 'Test Email Company ' . uniqid() . '_' . time();
        $stmt = $this->executeQuery(
            "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
            [$companyName, 'adv', 1],
            'ssi'
        );
        $this->testCompanyId = $this->db->insert_id;
        $stmt->close();
        
        // Create test user
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
    
    public function runTests(): bool {
        echo "=== Event-Driven Email Queuing Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 11: Event-Driven Email Queuing
        $allPassed &= $this->runPropertyTest(
            "Property 11: System events automatically queue appropriate emails for all matching triggers",
            [$this, 'testEventTriggersEmailQueuing']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 11: Multiple triggers for same event process without duplication",
            [$this, 'testMultipleTriggersNoDuplication']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 11: Event data is properly enriched and passed to email templates",
            [$this, 'testEventDataEnrichment']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 11: System events automatically queue appropriate emails for all matching triggers
     * **Feature: email-management-system, Property 11: Event-Driven Email Queuing**
     * **Validates: Requirements 4.3, 4.5, 5.2, 5.3, 5.4, 5.5**
     */
    public function testEventTriggersEmailQueuing(): array {
        try {
            // Create unique test data for this iteration
            $testData = $this->createTestData();
            
            // Generate random module and event
            $modules = ['feasibility', 'installation', 'material_request', 'dispatch', 'user', 'site'];
            $moduleName = $modules[array_rand($modules)];
            
            $eventTypes = $this->moduleIntegrationService->getAvailableEventsForModule($moduleName);
            if (empty($eventTypes)) {
                $this->cleanupTestData($testData);
                return ['success' => true]; // Skip if no events available
            }
            
            $eventType = $eventTypes[array_rand($eventTypes)];
            
            // Create test template
            $templateData = [
                'name' => 'Test Template ' . uniqid() . '_' . time(),
                'subject' => 'Test Subject: {site_name}',
                'body_text' => 'Test body for {user_name} at {site_name}',
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId'],
                'is_active' => 1
            ];
            
            $this->emailTemplateRepository->setCurrentUser($testData['userId']);
            $template = $this->emailTemplateRepository->create($templateData);
            $testData['templateIds'][] = $template['id'];
            
            // Create test trigger
            $triggerData = [
                'name' => 'Test Trigger ' . uniqid(),
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'template_id' => $template['id'],
                'recipient_rules' => [
                    [
                        'type' => 'static',
                        'emails' => ['test@example.com', 'test2@example.com']
                    ]
                ],
                'is_active' => 1,
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId']
            ];
            
            $trigger = $this->emailTriggerService->createTrigger($triggerData, $testData['userId']);
            $testData['triggerIds'][] = $trigger['id'];
            
            // Count emails before event
            $emailCountBefore = $this->getEmailQueueCount();
            
            // Generate event data
            $eventData = $this->generateEventData($moduleName, $eventType, $testData['companyId']);
            
            // Process event
            $result = $this->processEventByModule($moduleName, $eventType, $eventData);
            
            // Verify processing result
            $this->assert(
                $result['success'] === true,
                "Event processing should succeed"
            );
            
            $this->assert(
                $result['triggered_count'] >= 1,
                "At least one trigger should be activated"
            );
            
            $this->assert(
                $result['queued_emails'] >= 2, // 2 recipients in static rule
                "Emails should be queued for all recipients"
            );
            
            // Verify emails were actually queued
            $emailCountAfter = $this->getEmailQueueCount();
            $this->assert(
                $emailCountAfter > $emailCountBefore,
                "Email queue should contain new emails after event processing"
            );
            
            // Verify queued emails have correct data
            $queuedEmails = $this->getRecentQueuedEmails($template['id']);
            $this->assert(
                count($queuedEmails) >= 2,
                "Should have queued emails for all recipients"
            );
            
            foreach ($queuedEmails as $email) {
                $testData['emailIds'][] = $email['id'];
                
                $this->assert(
                    $email['template_id'] == $template['id'],
                    "Queued email should reference correct template"
                );
                
                $this->assert(
                    $email['trigger_id'] == $trigger['id'],
                    "Queued email should reference correct trigger"
                );
                
                $this->assert(
                    $email['status'] === 'pending',
                    "Queued email should have pending status"
                );
            }
            
            // Cleanup
            $this->cleanupTestData($testData);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            if (isset($testData)) {
                $this->cleanupTestData($testData);
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 11: Multiple triggers for same event process without duplication
     * **Feature: email-management-system, Property 11: Event-Driven Email Queuing**
     * **Validates: Requirements 4.3, 4.5**
     */
    public function testMultipleTriggersNoDuplication(): array {
        try {
            // Generate random module and event
            $modules = ['feasibility', 'user', 'site'];
            $moduleName = $modules[array_rand($modules)];
            
            $eventTypes = $this->moduleIntegrationService->getAvailableEventsForModule($moduleName);
            if (empty($eventTypes)) {
                return ['success' => true]; // Skip if no events available
            }
            
            $eventType = $eventTypes[array_rand($eventTypes)];
            
            // Create multiple templates for same event (using different companies to avoid unique constraint)
            $numTriggers = $this->generateRandomInt(2, 4);
            $triggerIds = [];
            $templateIds = [];
            $companyIds = [];
            
            for ($i = 0; $i < $numTriggers; $i++) {
                // Create unique company for each template to avoid unique constraint
                $companyName = 'Test Company ' . uniqid() . '_' . time() . '_' . $i;
                $stmt = $this->executeQuery(
                    "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                    [$companyName, 'adv', 1],
                    'ssi'
                );
                $companyId = $this->db->insert_id;
                $companyIds[] = $companyId;
                $stmt->close();
                
                // Create template
                $templateData = [
                    'name' => "Test Template $i " . uniqid() . '_' . time() . '_' . $i,
                    'subject' => "Test Subject $i: {site_name}",
                    'body_text' => "Test body $i for {user_name}",
                    'module_name' => $moduleName,
                    'event_type' => $eventType,
                    'company_id' => $companyId,
                    'created_by' => $this->testUserId,
                    'is_active' => 1
                ];
                
                $template = $this->emailTemplateRepository->create($templateData);
                $templateIds[] = $template['id'];
                $this->createdTemplateIds[] = $template['id'];
                
                // Create trigger
                $triggerData = [
                    'name' => "Test Trigger $i " . uniqid(),
                    'module_name' => $moduleName,
                    'event_type' => $eventType,
                    'template_id' => $template['id'],
                    'recipient_rules' => [
                        [
                            'type' => 'static',
                            'emails' => ['unique' . $i . '@example.com']
                        ]
                    ],
                    'is_active' => 1,
                    'company_id' => $companyId,
                    'created_by' => $this->testUserId
                ];
                
                $trigger = $this->emailTriggerService->createTrigger($triggerData, $this->testUserId);
                $triggerIds[] = $trigger['id'];
                $this->createdTriggerIds[] = $trigger['id'];
            }
            
            // Count emails before event
            $emailCountBefore = $this->getEmailQueueCount();
            
            // Generate event data
            $eventData = $this->generateEventData($moduleName, $eventType);
            
            // Process event for each company
            $totalTriggered = 0;
            $totalQueued = 0;
            
            foreach ($companyIds as $companyId) {
                $result = $this->processEventByModule($moduleName, $eventType, array_merge($eventData, ['company_id' => $companyId]));
                $totalTriggered += $result['triggered_count'];
                $totalQueued += $result['queued_emails'];
            }
            
            // Verify all triggers were processed
            $this->assert(
                $totalTriggered === $numTriggers,
                "All $numTriggers triggers should be processed"
            );
            
            $this->assert(
                $totalQueued === $numTriggers, // 1 email per trigger
                "Should queue exactly $numTriggers emails (one per trigger)"
            );
            
            // Verify no duplicate emails
            $emailCountAfter = $this->getEmailQueueCount();
            $actualNewEmails = $emailCountAfter - $emailCountBefore;
            
            $this->assert(
                $actualNewEmails === $numTriggers,
                "Should create exactly $numTriggers new emails, not $actualNewEmails"
            );
            
            // Verify each trigger created exactly one email
            foreach ($triggerIds as $triggerId) {
                $triggerEmails = $this->getEmailsByTrigger($triggerId);
                $this->assert(
                    count($triggerEmails) === 1,
                    "Each trigger should create exactly one email"
                );
                
                if (!empty($triggerEmails)) {
                    $this->createdEmailIds[] = $triggerEmails[0]['id'];
                }
            }
            
            // Clean up additional companies
            foreach ($companyIds as $companyId) {
                if ($companyId !== $this->testCompanyId) {
                    try {
                        $stmt = $this->executeQuery(
                            "DELETE FROM companies WHERE id = ?",
                            [$companyId],
                            'i'
                        );
                        $stmt->close();
                    } catch (Exception $e) {
                        // Ignore cleanup errors
                    }
                }
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
     * Property 11: Event data is properly enriched and passed to email templates
     * **Feature: email-management-system, Property 11: Event-Driven Email Queuing**
     * **Validates: Requirements 5.2, 5.3, 5.4, 5.5**
     */
    public function testEventDataEnrichment(): array {
        try {
            // Test with user module events (simpler data structure)
            $moduleName = 'user';
            $eventType = 'user_created';
            
            // Create template with placeholders
            $templateData = [
                'name' => 'User Creation Template ' . uniqid() . '_' . time(),
                'subject' => 'Welcome {user_name}!',
                'body_text' => 'Hello {user_name}, your account {username} has been created for {company_name}.',
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'company_id' => $this->testCompanyId,
                'created_by' => $this->testUserId,
                'is_active' => 1
            ];
            
            $template = $this->emailTemplateRepository->create($templateData);
            $this->createdTemplateIds[] = $template['id'];
            
            // Create trigger
            $triggerData = [
                'name' => 'User Creation Trigger ' . uniqid(),
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'template_id' => $template['id'],
                'recipient_rules' => [
                    [
                        'type' => 'static',
                        'emails' => ['admin@example.com']
                    ]
                ],
                'is_active' => 1,
                'company_id' => $this->testCompanyId,
                'created_by' => $this->testUserId
            ];
            
            $trigger = $this->emailTriggerService->createTrigger($triggerData, $this->testUserId);
            $this->createdTriggerIds[] = $trigger['id'];
            
            // Generate event data with specific values
            $testUserName = 'Test User ' . uniqid();
            $testUsername = 'testuser' . uniqid();
            $testCompanyName = 'Test Company ' . uniqid();
            
            $eventData = [
                'user_id' => $this->testUserId,
                'user_name' => $testUserName,
                'username' => $testUsername,
                'company_id' => $this->testCompanyId,
                'company_name' => $testCompanyName
            ];
            
            // Process event
            $result = $this->moduleIntegrationService->processUserEvent($eventType, $eventData);
            
            // Verify processing succeeded
            $this->assert(
                $result['success'] === true,
                "Event processing should succeed"
            );
            
            $this->assert(
                $result['queued_emails'] >= 1,
                "At least one email should be queued"
            );
            
            // Get the queued email
            $queuedEmails = $this->getRecentQueuedEmails($template['id']);
            $this->assert(
                !empty($queuedEmails),
                "Should have queued emails"
            );
            
            $email = $queuedEmails[0];
            $this->createdEmailIds[] = $email['id'];
            
            // Verify placeholder replacement in subject
            $this->assert(
                strpos($email['subject'], $testUserName) !== false,
                "Subject should contain replaced user_name: {$email['subject']}"
            );
            
            // Verify placeholder replacement in body
            $this->assert(
                strpos($email['body_text'], $testUserName) !== false,
                "Body should contain replaced user_name"
            );
            
            $this->assert(
                strpos($email['body_text'], $testUsername) !== false,
                "Body should contain replaced username"
            );
            
            $this->assert(
                strpos($email['body_text'], $testCompanyName) !== false,
                "Body should contain replaced company_name"
            );
            
            // Verify no unreplaced placeholders remain
            $this->assert(
                strpos($email['subject'], '{') === false,
                "Subject should not contain unreplaced placeholders: {$email['subject']}"
            );
            
            $this->assert(
                strpos($email['body_text'], '{') === false,
                "Body should not contain unreplaced placeholders"
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
     * Create test data for a single iteration
     */
    private function createTestData(): array {
        // Create unique company for this iteration
        $companyName = 'Test Email Company ' . uniqid() . '_' . time();
        $stmt = $this->executeQuery(
            "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
            [$companyName, 'adv', 1],
            'ssi'
        );
        $companyId = $this->db->insert_id;
        $stmt->close();
        
        // Create test user
        $username = 'email_test_user_' . uniqid();
        $email = $username . '@test.com';
        
        // Get a valid role_id
        $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
        $roleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
        
        $stmt = $this->executeQuery(
            "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$username, $email, password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $companyId, $roleId, 1],
            'sssssiis'
        );
        $userId = $this->db->insert_id;
        $stmt->close();
        
        return [
            'companyId' => $companyId,
            'userId' => $userId,
            'templateIds' => [],
            'triggerIds' => [],
            'emailIds' => []
        ];
    }
    
    /**
     * Clean up test data for a single iteration
     */
    private function cleanupTestData(array $testData): void {
        // Delete created emails
        foreach ($testData['emailIds'] as $emailId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM email_queue WHERE id = ?",
                    [$emailId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete created triggers
        foreach ($testData['triggerIds'] as $triggerId) {
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
        
        // Delete created templates
        foreach ($testData['templateIds'] as $templateId) {
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
        
        // Delete test user
        if ($testData['userId']) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM users WHERE id = ?",
                    [$testData['userId']],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete test company
        if ($testData['companyId']) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM companies WHERE id = ?",
                    [$testData['companyId']],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
    
    /**
     * Process event by module type
     */
    private function processEventByModule(string $moduleName, string $eventType, array $eventData): array {
        switch ($moduleName) {
            case 'feasibility':
                return $this->moduleIntegrationService->processFeasibilityEvent($eventType, $eventData);
            case 'installation':
                return $this->moduleIntegrationService->processInstallationEvent($eventType, $eventData);
            case 'material_request':
                return $this->moduleIntegrationService->processMaterialRequestEvent($eventType, $eventData);
            case 'dispatch':
                return $this->moduleIntegrationService->processDispatchEvent($eventType, $eventData);
            case 'user':
                return $this->moduleIntegrationService->processUserEvent($eventType, $eventData);
            case 'site':
                return $this->moduleIntegrationService->processSiteEvent($eventType, $eventData);
            default:
                return [
                    'success' => false,
                    'triggered_count' => 0,
                    'queued_emails' => 0,
                    'errors' => ['Unknown module: ' . $moduleName]
                ];
        }
    }
    
    /**
     * Generate event data for testing
     */
    private function generateEventData(string $moduleName, string $eventType, int $companyId = null): array {
        $baseData = [
            'company_id' => $companyId ?? $this->testCompanyId,
            'user_id' => $this->testUserId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        switch ($moduleName) {
            case 'feasibility':
                return array_merge($baseData, [
                    'feasibility_id' => $this->generateRandomInt(1, 1000),
                    'site_id' => $this->generateRandomInt(1, 100),
                    'site_name' => 'Test Site ' . uniqid(),
                    'engineer_id' => $this->testUserId
                ]);
                
            case 'installation':
                return array_merge($baseData, [
                    'installation_id' => $this->generateRandomInt(1, 1000),
                    'site_id' => $this->generateRandomInt(1, 100),
                    'site_name' => 'Test Site ' . uniqid(),
                    'engineer_id' => $this->testUserId
                ]);
                
            case 'material_request':
                return array_merge($baseData, [
                    'request_id' => $this->generateRandomInt(1, 1000),
                    'site_id' => $this->generateRandomInt(1, 100),
                    'site_name' => 'Test Site ' . uniqid()
                ]);
                
            case 'dispatch':
                return array_merge($baseData, [
                    'dispatch_id' => $this->generateRandomInt(1, 1000),
                    'site_id' => $this->generateRandomInt(1, 100),
                    'site_name' => 'Test Site ' . uniqid()
                ]);
                
            case 'user':
                return array_merge($baseData, [
                    'user_name' => 'Test User ' . uniqid(),
                    'username' => 'testuser' . uniqid(),
                    'email' => 'test' . uniqid() . '@example.com'
                ]);
                
            case 'site':
                return array_merge($baseData, [
                    'site_id' => $this->generateRandomInt(1, 100),
                    'site_name' => 'Test Site ' . uniqid(),
                    'lho' => 'Test LHO ' . uniqid()
                ]);
                
            default:
                return $baseData;
        }
    }
    
    /**
     * Get email queue count
     */
    private function getEmailQueueCount(): int {
        $result = $this->getResults("SELECT COUNT(*) as count FROM email_queue");
        return (int)($result[0]['count'] ?? 0);
    }
    
    /**
     * Get recent queued emails for a template
     */
    private function getRecentQueuedEmails(int $templateId): array {
        return $this->getResults(
            "SELECT * FROM email_queue WHERE template_id = ? ORDER BY created_at DESC LIMIT 10",
            [$templateId],
            'i'
        );
    }
    
    /**
     * Get emails by trigger ID
     */
    private function getEmailsByTrigger(int $triggerId): array {
        return $this->getResults(
            "SELECT * FROM email_queue WHERE trigger_id = ? ORDER BY created_at DESC",
            [$triggerId],
            'i'
        );
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete created emails
        foreach ($this->createdEmailIds as $emailId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM email_queue WHERE id = ?",
                    [$emailId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
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
        
        // Delete test user
        if ($this->testUserId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM users WHERE id = ?",
                    [$this->testUserId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        // Delete test company
        if ($this->testCompanyId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM companies WHERE id = ?",
                    [$this->testCompanyId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        $this->createdEmailIds = [];
        $this->createdTriggerIds = [];
        $this->createdTemplateIds = [];
    }
}