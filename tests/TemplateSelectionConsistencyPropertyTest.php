<?php
/**
 * Property Test for Template Selection Consistency
 * **Feature: email-management-system, Property 13: Template Selection Consistency**
 * **Validates: Requirements 6.1**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/EmailTriggerService.php';
require_once __DIR__ . '/../services/EmailModuleIntegrationService.php';
require_once __DIR__ . '/../repositories/EmailTriggerRepository.php';
require_once __DIR__ . '/../repositories/EmailTemplateRepository.php';

class TemplateSelectionConsistencyPropertyTest extends PropertyTestBase {
    
    private $emailTriggerService;
    private $moduleIntegrationService;
    private $emailTriggerRepository;
    private $emailTemplateRepository;
    
    // Test data IDs for cleanup
    private $createdTriggerIds = [];
    private $createdTemplateIds = [];
    private $createdCompanyIds = [];
    private $createdUserIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->iterations = 8; // Reasonable number for testing
        $this->emailTriggerService = new EmailTriggerService();
        $this->moduleIntegrationService = new EmailModuleIntegrationService();
        $this->emailTriggerRepository = new EmailTriggerRepository();
        $this->emailTemplateRepository = new EmailTemplateRepository();
    }
    
    public function runTests(): bool {
        echo "=== Template Selection Consistency Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 13: Template Selection Consistency
        $allPassed &= $this->runPropertyTest(
            "Property 13: For any triggering event, the system selects appropriate template based on event type and module",
            [$this, 'testTemplateSelectionConsistency']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 13: Template selection is consistent across multiple trigger activations",
            [$this, 'testConsistentTemplateSelection']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 13: Only active templates are selected for email generation",
            [$this, 'testActiveTemplateSelection']
        );
        
        // Cleanup
        $this->cleanupIterationTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 13: For any triggering event, the system selects appropriate template based on event type and module
     * **Feature: email-management-system, Property 13: Template Selection Consistency**
     * **Validates: Requirements 6.1**
     */
    public function testTemplateSelectionConsistency(): array {
        try {
            // Create test data
            $testData = $this->createTestData();
            
            // Generate random module and event
            $modules = ['feasibility', 'user', 'site'];
            $moduleName = $modules[array_rand($modules)];
            
            $eventTypes = $this->moduleIntegrationService->getAvailableEventsForModule($moduleName);
            if (empty($eventTypes)) {
                $this->cleanupIterationTestData([$testData]);
                return ['success' => true]; // Skip if no events available
            }
            
            $eventType = $eventTypes[array_rand($eventTypes)];
            
            // Create template for specific module and event
            $templateData = [
                'name' => 'Test Template ' . uniqid(),
                'subject' => 'Test Subject for {user_name}',
                'body_text' => 'Test body for {user_name} in module ' . $moduleName,
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId'],
                'is_active' => 1
            ];
            
            $this->emailTemplateRepository->setCurrentUser($testData['userId']);
            $template = $this->emailTemplateRepository->create($templateData);
            $testData['templateIds'][] = $template['id'];
            
            // Create trigger that uses this template
            $triggerData = [
                'name' => 'Test Trigger ' . uniqid(),
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'template_id' => $template['id'],
                'recipient_rules' => [
                    [
                        'type' => 'static',
                        'emails' => ['test@example.com']
                    ]
                ],
                'is_active' => 1,
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId']
            ];
            
            $trigger = $this->emailTriggerService->createTrigger($triggerData, $testData['userId']);
            $testData['triggerIds'][] = $trigger['id'];
            
            // Generate event data
            $eventData = $this->generateEventData($moduleName, $eventType, $testData['companyId']);
            
            // Process event
            $result = $this->processEventByModule($moduleName, $eventType, $eventData);
            
            // Verify processing succeeded
            $this->assert(
                $result['success'] === true,
                "Event processing should succeed"
            );
            
            $this->assert(
                $result['triggered_count'] >= 1,
                "At least one trigger should be activated"
            );
            
            // Get the queued emails to verify template selection
            $queuedEmails = $this->getRecentQueuedEmails($template['id']);
            $this->assert(
                !empty($queuedEmails),
                "Should have queued emails using the correct template"
            );
            
            foreach ($queuedEmails as $email) {
                // Verify correct template was selected
                $this->assert(
                    $email['template_id'] == $template['id'],
                    "Queued email should use the correct template for module '$moduleName' and event '$eventType'"
                );
                
                // Verify template content matches module and event
                $this->assert(
                    strpos($email['body_text'], $moduleName) !== false,
                    "Email body should contain module-specific content"
                );
                
                // Verify trigger association
                $this->assert(
                    $email['trigger_id'] == $trigger['id'],
                    "Queued email should reference the correct trigger"
                );
            }
            
            // Cleanup
            $this->cleanupTestData([$testData]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            if (isset($testData)) {
                $this->cleanupTestData([$testData]);
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 13: Template selection is consistent across multiple trigger activations
     * **Feature: email-management-system, Property 13: Template Selection Consistency**
     * **Validates: Requirements 6.1**
     */
    public function testConsistentTemplateSelection(): array {
        try {
            // Create test data
            $testData = $this->createTestData();
            
            // Use user module for consistency
            $moduleName = 'user';
            $eventType = 'user_created';
            
            // Create template
            $templateData = [
                'name' => 'User Creation Template ' . uniqid(),
                'subject' => 'Welcome {user_name}!',
                'body_text' => 'Hello {user_name}, welcome to the system.',
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId'],
                'is_active' => 1
            ];
            
            $template = $this->emailTemplateRepository->create($templateData);
            $testData['templateIds'][] = $template['id'];
            
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
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId']
            ];
            
            $trigger = $this->emailTriggerService->createTrigger($triggerData, $testData['userId']);
            $testData['triggerIds'][] = $trigger['id'];
            
            // Process the same event multiple times
            $numActivations = $this->generateRandomInt(3, 6);
            $selectedTemplates = [];
            
            for ($i = 0; $i < $numActivations; $i++) {
                // Generate unique event data for each activation
                $eventData = [
                    'user_id' => $testData['userId'],
                    'user_name' => 'Test User ' . $i,
                    'username' => 'testuser' . $i,
                    'company_id' => $testData['companyId']
                ];
                
                // Process event
                $result = $this->moduleIntegrationService->processUserEvent($eventType, $eventData);
                
                // Verify processing succeeded
                $this->assert(
                    $result['success'] === true,
                    "Event processing should succeed for activation $i"
                );
                
                // Get queued emails for this activation
                $queuedEmails = $this->getRecentQueuedEmails($template['id'], 1);
                if (!empty($queuedEmails)) {
                    $selectedTemplates[] = $queuedEmails[0]['template_id'];
                }
            }
            
            // Verify all activations used the same template
            $uniqueTemplates = array_unique($selectedTemplates);
            $this->assert(
                count($uniqueTemplates) === 1,
                "All trigger activations should use the same template consistently"
            );
            
            $this->assert(
                $uniqueTemplates[0] == $template['id'],
                "All activations should use the correct template"
            );
            
            // Cleanup
            $this->cleanupTestData([$testData]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            if (isset($testData)) {
                $this->cleanupTestData([$testData]);
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 13: Only active templates are selected for email generation
     * **Feature: email-management-system, Property 13: Template Selection Consistency**
     * **Validates: Requirements 6.1**
     */
    public function testActiveTemplateSelection(): array {
        try {
            // Create test data
            $testData = $this->createTestData();
            
            // Use site module
            $moduleName = 'site';
            $eventType = 'site_created';
            
            // Create active template
            $activeTemplateData = [
                'name' => 'Active Template ' . uniqid(),
                'subject' => 'Site Created: {site_name}',
                'body_text' => 'Site {site_name} has been created.',
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId'],
                'is_active' => 1
            ];
            
            $activeTemplate = $this->emailTemplateRepository->create($activeTemplateData);
            $testData['templateIds'][] = $activeTemplate['id'];
            
            // Create inactive template (different company to avoid unique constraint)
            $inactiveTestData = $this->createTestData();
            $inactiveTemplateData = [
                'name' => 'Inactive Template ' . uniqid(),
                'subject' => 'Inactive Site Created: {site_name}',
                'body_text' => 'Inactive: Site {site_name} has been created.',
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'company_id' => $inactiveTestData['companyId'],
                'created_by' => $inactiveTestData['userId'],
                'is_active' => 0 // Inactive
            ];
            
            $this->emailTemplateRepository->setCurrentUser($inactiveTestData['userId']);
            $inactiveTemplate = $this->emailTemplateRepository->create($inactiveTemplateData);
            $inactiveTestData['templateIds'][] = $inactiveTemplate['id'];
            
            // Create trigger for active template
            $activeTriggerData = [
                'name' => 'Active Trigger ' . uniqid(),
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'template_id' => $activeTemplate['id'],
                'recipient_rules' => [
                    [
                        'type' => 'static',
                        'emails' => ['active@example.com']
                    ]
                ],
                'is_active' => 1,
                'company_id' => $testData['companyId'],
                'created_by' => $testData['userId']
            ];
            
            $activeTrigger = $this->emailTriggerService->createTrigger($activeTriggerData, $testData['userId']);
            $testData['triggerIds'][] = $activeTrigger['id'];
            
            // Create trigger for inactive template
            $inactiveTriggerData = [
                'name' => 'Inactive Trigger ' . uniqid(),
                'module_name' => $moduleName,
                'event_type' => $eventType,
                'template_id' => $inactiveTemplate['id'],
                'recipient_rules' => [
                    [
                        'type' => 'static',
                        'emails' => ['inactive@example.com']
                    ]
                ],
                'is_active' => 1,
                'company_id' => $inactiveTestData['companyId'],
                'created_by' => $inactiveTestData['userId']
            ];
            
            $inactiveTrigger = $this->emailTriggerService->createTrigger($inactiveTriggerData, $inactiveTestData['userId']);
            $inactiveTestData['triggerIds'][] = $inactiveTrigger['id'];
            
            // Process event for active template company
            $activeEventData = [
                'site_id' => 1,
                'site_name' => 'Test Site Active',
                'company_id' => $testData['companyId']
            ];
            
            $activeResult = $this->moduleIntegrationService->processSiteEvent($eventType, $activeEventData);
            
            // Process event for inactive template company
            $inactiveEventData = [
                'site_id' => 2,
                'site_name' => 'Test Site Inactive',
                'company_id' => $inactiveTestData['companyId']
            ];
            
            $inactiveResult = $this->moduleIntegrationService->processSiteEvent($eventType, $inactiveEventData);
            
            // Verify active template was used
            $this->assert(
                $activeResult['success'] === true,
                "Active template event processing should succeed"
            );
            
            $this->assert(
                $activeResult['triggered_count'] >= 1,
                "Active template should trigger emails"
            );
            
            // Verify inactive template was not used (trigger should fail to create emails)
            // The trigger itself might be processed but no emails should be queued due to inactive template
            $activeEmails = $this->getRecentQueuedEmails($activeTemplate['id']);
            $inactiveEmails = $this->getRecentQueuedEmails($inactiveTemplate['id']);
            
            $this->assert(
                !empty($activeEmails),
                "Active template should generate emails"
            );
            
            $this->assert(
                empty($inactiveEmails),
                "Inactive template should not generate emails"
            );
            
            // Verify only active templates are referenced in queued emails
            foreach ($activeEmails as $email) {
                $this->assert(
                    $email['template_id'] == $activeTemplate['id'],
                    "Queued email should only reference active templates"
                );
            }
            
            // Cleanup
            $this->cleanupTestData([$testData, $inactiveTestData]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            if (isset($testData)) {
                $this->cleanupTestData([$testData]);
            }
            if (isset($inactiveTestData)) {
                $this->cleanupTestData([$inactiveTestData]);
            }
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
        $companyName = 'Test Template Company ' . uniqid() . '_' . time();
        $stmt = $this->executeQuery(
            "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
            [$companyName, 'adv', 1],
            'ssi'
        );
        $companyId = $this->db->insert_id;
        $stmt->close();
        
        // Create test user
        $username = 'template_test_user_' . uniqid();
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
            'triggerIds' => []
        ];
    }
    
    /**
     * Clean up iteration test data
     */
    private function cleanupIterationTestData(array $testDataArray): void {
        foreach ($testDataArray as $testData) {
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
    }
    
    /**
     * Process event by module type
     */
    private function processEventByModule(string $moduleName, string $eventType, array $eventData): array {
        switch ($moduleName) {
            case 'feasibility':
                return $this->moduleIntegrationService->processFeasibilityEvent($eventType, $eventData);
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
    private function generateEventData(string $moduleName, string $eventType, int $companyId): array {
        $baseData = [
            'company_id' => $companyId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        switch ($moduleName) {
            case 'feasibility':
                return array_merge($baseData, [
                    'feasibility_id' => $this->generateRandomInt(1, 1000),
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
     * Get recent queued emails for a template
     */
    private function getRecentQueuedEmails(int $templateId, int $limit = 10): array {
        return $this->getResults(
            "SELECT * FROM email_queue WHERE template_id = ? ORDER BY created_at DESC LIMIT ?",
            [$templateId, $limit],
            'ii'
        );
    }
}