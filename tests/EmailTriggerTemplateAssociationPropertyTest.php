<?php
/**
 * Property Test for Trigger-Template Association
 * **Feature: email-management-system, Property 10: Trigger-Template Association**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/EmailTrigger.php';
require_once __DIR__ . '/../models/EmailTemplate.php';

class EmailTriggerTemplateAssociationPropertyTest extends PropertyTestBase {
    private $testCompanyId = 1; // ADV company
    private $testUserId = 2326; // admin user
    private $createdTriggers = [];
    private $createdTemplates = [];
    
    public function runTests() {
        echo "Starting Email Trigger-Template Association Property Tests\n";
        
        $allPassed = true;
        
        // Property 10: Trigger-Template Association
        $allPassed &= $this->runPropertyTest(
            "Trigger-Template Association",
            [$this, 'testTriggerTemplateAssociation']
        );
        
        $this->cleanupTestData();
        
        if ($allPassed) {
            echo "All Email Trigger-Template Association property tests passed!\n";
            return true;
        } else {
            echo "Some Email Trigger-Template Association property tests failed!\n";
            return false;
        }
    }
    
    /**
     * Property 10: For any enabled email trigger, the system should properly associate 
     * it with specific templates and recipient rules, and this association should remain consistent
     */
    public function testTriggerTemplateAssociation() {
        $templateModel = new EmailTemplate();
        $triggerModel = new EmailTrigger();
        
        try {
            // First create a template
            $templateData = $this->generateValidTemplateData();
            $template = $templateModel->create($templateData);
            $this->createdTemplates[] = $template['id'];
            
            // Generate trigger data that references the template
            $triggerData = $this->generateValidTriggerData($template['id'], $template['module_name'], $template['event_type']);
            
            // Create trigger
            $trigger = $triggerModel->create($triggerData);
            $this->createdTriggers[] = $trigger['id'];
            
            // Verify trigger was created
            $this->assert($trigger !== null, "Trigger should be created");
            $this->assert(isset($trigger['id']), "Trigger should have an ID");
            
            // Verify template association
            $this->assert($trigger['template_id'] == $template['id'], "Trigger should be associated with correct template");
            $this->assert($trigger['module_name'] === $template['module_name'], "Trigger module should match template module");
            $this->assert($trigger['event_type'] === $template['event_type'], "Trigger event should match template event");
            
            // Verify recipient rules are stored correctly
            $this->assert(!empty($trigger['recipient_rules']), "Recipient rules should be present");
            $recipientRules = is_string($trigger['recipient_rules']) ? 
                json_decode($trigger['recipient_rules'], true) : 
                $trigger['recipient_rules'];
            $this->assert(is_array($recipientRules), "Recipient rules should be an array");
            $this->assert(!empty($recipientRules), "Recipient rules should not be empty");
            
            // Test trigger retrieval by module and event
            $foundTriggers = $triggerModel->findByModuleAndEvent(
                $this->testCompanyId, 
                $trigger['module_name'], 
                $trigger['event_type']
            );
            $this->assert(!empty($foundTriggers), "Should find triggers by module and event");
            
            $foundTrigger = null;
            foreach ($foundTriggers as $ft) {
                if ($ft['id'] == $trigger['id']) {
                    $foundTrigger = $ft;
                    break;
                }
            }
            $this->assert($foundTrigger !== null, "Should find the created trigger");
            $this->assert($foundTrigger['template_id'] == $template['id'], "Found trigger should maintain template association");
            
            // Test trigger condition evaluation (if conditions exist)
            if (!empty($trigger['conditions'])) {
                $sampleEventData = $this->generateSampleEventData($trigger['module_name'], $trigger['event_type']);
                $conditionResult = $triggerModel->evaluateConditions($trigger['id'], $sampleEventData);
                $this->assert(is_bool($conditionResult), "Condition evaluation should return boolean");
            }
            
            // Test recipient retrieval
            $sampleEventData = $this->generateSampleEventData($trigger['module_name'], $trigger['event_type']);
            $recipients = $triggerModel->getRecipients($trigger['id'], $sampleEventData);
            $this->assert(is_array($recipients), "Recipients should be an array");
            
            // Verify all recipients are valid email addresses
            foreach ($recipients as $recipient) {
                $this->assert(filter_var($recipient, FILTER_VALIDATE_EMAIL), "Recipient should be valid email: $recipient");
            }
            
            // Test trigger testing functionality
            $testResult = $triggerModel->testTrigger($trigger['id'], $sampleEventData);
            $this->assert(isset($testResult['trigger_id']), "Test result should include trigger ID");
            $this->assert($testResult['trigger_id'] == $trigger['id'], "Test result should reference correct trigger");
            $this->assert(isset($testResult['conditions_met']), "Test result should include condition evaluation");
            $this->assert(isset($testResult['recipients']), "Test result should include recipients");
            
            // Verify company isolation
            $this->assert($trigger['company_id'] == $this->testCompanyId, "Trigger should belong to correct company");
            
            // Verify timestamps are set
            $this->assert(!empty($trigger['created_at']), "Created timestamp should be set");
            $this->assert(!empty($trigger['updated_at']), "Updated timestamp should be set");
            
            return [
                'success' => true,
                'data' => [
                    'template' => $templateData,
                    'trigger' => $triggerData
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'template' => $templateData ?? null,
                    'trigger' => $triggerData ?? null
                ]
            ];
        }
    }
    
    /**
     * Generate valid email template data
     */
    private function generateValidTemplateData() {
        $modules = ['users', 'sites', 'feasibility', 'installation', 'inventory', 'material_requests'];
        $events = ['created', 'updated', 'assigned', 'completed', 'approved', 'rejected'];
        
        $module = $this->generateRandomChoice($modules);
        $event = $this->generateRandomChoice($events);
        
        // Add randomness to ensure uniqueness
        $uniqueSuffix = $this->generateRandomString(8);
        $module = $module . '_' . $uniqueSuffix;
        
        return [
            'name' => 'Test Template ' . $this->generateRandomString(8),
            'subject' => 'Test Subject {user_name}',
            'body_text' => 'Test body content for {user_name} from {company_name}',
            'module_name' => $module,
            'event_type' => $event,
            'placeholders' => ['user_name', 'company_name'],
            'is_active' => true,
            'company_id' => $this->testCompanyId,
            'created_by' => $this->testUserId
        ];
    }
    
    /**
     * Generate valid trigger data
     */
    private function generateValidTriggerData($templateId, $moduleName, $eventType) {
        $recipientRules = $this->generateRecipientRules();
        $conditions = $this->generateRandomBool() ? $this->generateConditions() : null;
        
        return [
            'name' => 'Test Trigger ' . $this->generateRandomString(8),
            'module_name' => $moduleName,
            'event_type' => $eventType,
            'template_id' => $templateId,
            'recipient_rules' => $recipientRules,
            'conditions' => $conditions,
            'is_active' => true,
            'company_id' => $this->testCompanyId,
            'created_by' => $this->testUserId
        ];
    }
    
    /**
     * Generate recipient rules
     */
    private function generateRecipientRules() {
        $ruleTypes = ['static', 'field', 'role'];
        $ruleType = $this->generateRandomChoice($ruleTypes);
        
        switch ($ruleType) {
            case 'static':
                return [
                    [
                        'type' => 'static',
                        'emails' => [$this->generateRandomEmail(), $this->generateRandomEmail()]
                    ]
                ];
            case 'field':
                return [
                    [
                        'type' => 'field',
                        'field' => 'user_email'
                    ]
                ];
            case 'role':
                return [
                    [
                        'type' => 'role',
                        'role' => 'admin'
                    ]
                ];
            default:
                return [
                    [
                        'type' => 'static',
                        'emails' => [$this->generateRandomEmail()]
                    ]
                ];
        }
    }
    
    /**
     * Generate conditions
     */
    private function generateConditions() {
        return [
            'operator' => 'AND',
            'rules' => [
                [
                    'field' => 'status',
                    'operator' => '=',
                    'value' => 'active'
                ]
            ]
        ];
    }
    
    /**
     * Generate sample event data
     */
    private function generateSampleEventData($moduleName, $eventType) {
        return [
            'company_id' => $this->testCompanyId,
            'user_id' => $this->testUserId,
            'user_email' => 'test@example.com',
            'user_name' => 'Test User',
            'company_name' => 'Test Company',
            'module_name' => $moduleName,
            'event_type' => $eventType,
            'status' => 'active',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        // Clean up triggers first (due to foreign key constraints)
        if (!empty($this->createdTriggers)) {
            $triggerModel = new EmailTrigger();
            foreach ($this->createdTriggers as $triggerId) {
                try {
                    $triggerModel->delete($triggerId);
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
            $this->createdTriggers = [];
        }
        
        // Then clean up templates
        if (!empty($this->createdTemplates)) {
            $templateModel = new EmailTemplate();
            foreach ($this->createdTemplates as $templateId) {
                try {
                    $templateModel->delete($templateId);
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
            $this->createdTemplates = [];
        }
    }
}

// Run the test if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new EmailTriggerTemplateAssociationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}