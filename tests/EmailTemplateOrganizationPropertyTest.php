<?php
/**
 * Property Test for Email Template Organization by Module
 * **Feature: email-management-system, Property 5: Template Organization by Module**
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/EmailTemplate.php';

class EmailTemplateOrganizationPropertyTest extends PropertyTestBase {
    private $testCompanyId = 1; // ADV company
    private $testUserId = 2326; // admin user
    private $createdTemplates = [];
    
    public function runTests() {
        echo "Starting Email Template Organization Property Tests\n";
        
        $allPassed = true;
        
        // Property 5: Template Organization by Module
        $allPassed &= $this->runPropertyTest(
            "Template Organization by Module",
            [$this, 'testTemplateOrganizationByModule']
        );
        
        $this->cleanupTestData();
        
        if ($allPassed) {
            echo "All Email Template Organization property tests passed!\n";
            return true;
        } else {
            echo "Some Email Template Organization property tests failed!\n";
            return false;
        }
    }
    
    /**
     * Property 5: For any email template, the system should categorize it by its associated module 
     * and make it available for that module's events
     */
    public function testTemplateOrganizationByModule() {
        $model = new EmailTemplate();
        
        try {
            // Generate random template data with specific module
            $templateData = $this->generateValidTemplateData();
            $moduleName = $templateData['module_name'];
            $eventType = $templateData['event_type'];
            
            // Create template
            $template = $model->create($templateData);
            $this->createdTemplates[] = $template['id'];
            
            // Verify template was created
            $this->assert($template !== null, "Template should be created");
            $this->assert(isset($template['id']), "Template should have an ID");
            
            // Verify template is categorized by module
            $this->assert($template['module_name'] === $moduleName, "Template should have correct module name");
            $this->assert($template['event_type'] === $eventType, "Template should have correct event type");
            
            // Test 1: Template should be findable by module and event
            $foundTemplate = $model->findByModuleAndEvent($this->testCompanyId, $moduleName, $eventType);
            $this->assert($foundTemplate !== null, "Template should be findable by module and event");
            $this->assert($foundTemplate['id'] == $template['id'], "Found template should match created template");
            $this->assert($foundTemplate['module_name'] === $moduleName, "Found template should have correct module");
            $this->assert($foundTemplate['event_type'] === $eventType, "Found template should have correct event type");
            
            // Test 2: Template should appear in module-specific listings
            $moduleTemplates = $model->getByModule($this->testCompanyId, $moduleName);
            $this->assert(!empty($moduleTemplates), "Module should have templates");
            
            $templateFound = false;
            foreach ($moduleTemplates as $moduleTemplate) {
                if ($moduleTemplate['id'] == $template['id']) {
                    $templateFound = true;
                    $this->assert($moduleTemplate['module_name'] === $moduleName, "Template in module list should have correct module");
                    break;
                }
            }
            $this->assert($templateFound, "Template should appear in module-specific listing");
            
            // Test 3: Template should NOT appear in different module listings
            $differentModule = $this->generateDifferentModule($moduleName);
            $differentModuleTemplates = $model->getByModule($this->testCompanyId, $differentModule);
            
            $templateFoundInWrongModule = false;
            foreach ($differentModuleTemplates as $moduleTemplate) {
                if ($moduleTemplate['id'] == $template['id']) {
                    $templateFoundInWrongModule = true;
                    break;
                }
            }
            $this->assert(!$templateFoundInWrongModule, "Template should NOT appear in different module listing");
            
            // Test 4: Template should be available for its module's events
            $companyTemplates = $model->getByCompany($this->testCompanyId, [
                'module_name' => $moduleName
            ]);
            
            $templateFoundInCompanyList = false;
            foreach ($companyTemplates as $companyTemplate) {
                if ($companyTemplate['id'] == $template['id']) {
                    $templateFoundInCompanyList = true;
                    $this->assert($companyTemplate['module_name'] === $moduleName, "Template in company list should have correct module");
                    break;
                }
            }
            $this->assert($templateFoundInCompanyList, "Template should be available in company's module-filtered list");
            
            // Test 5: Template should be filterable by event type within module
            $eventFilteredTemplates = $model->getByCompany($this->testCompanyId, [
                'module_name' => $moduleName,
                'event_type' => $eventType
            ]);
            
            $templateFoundInEventList = false;
            foreach ($eventFilteredTemplates as $eventTemplate) {
                if ($eventTemplate['id'] == $template['id']) {
                    $templateFoundInEventList = true;
                    $this->assert($eventTemplate['module_name'] === $moduleName, "Template in event list should have correct module");
                    $this->assert($eventTemplate['event_type'] === $eventType, "Template in event list should have correct event type");
                    break;
                }
            }
            $this->assert($templateFoundInEventList, "Template should be available in module+event filtered list");
            
            return [
                'success' => true,
                'data' => $templateData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => $templateData ?? []
            ];
        }
    }
    
    /**
     * Generate valid email template data
     */
    private function generateValidTemplateData() {
        $modules = [
            'users', 'sites', 'feasibility', 'installation', 
            'inventory', 'material_requests', 'dispatches'
        ];
        $events = [
            'created', 'updated', 'assigned', 'completed', 
            'approved', 'rejected', 'submitted'
        ];
        
        // Generate unique module/event combination to avoid constraint violations
        $moduleName = $this->generateRandomChoice($modules);
        $eventType = $this->generateRandomChoice($events);
        
        // Add random suffix to make it unique
        $uniqueSuffix = $this->generateRandomString(8);
        $eventType = $eventType . '_' . $uniqueSuffix;
        
        return [
            'name' => 'Test Template ' . $this->generateRandomString(8),
            'subject' => 'Test Subject: {user_name} - ' . $this->generateRandomString(6),
            'body_text' => 'Hello {user_name}, this is a test template for ' . $moduleName . ' ' . $eventType . '. Company: {company_name}',
            'body_html' => '<p>Hello <strong>{user_name}</strong>, this is a test template for ' . $moduleName . ' ' . $eventType . '.</p><p>Company: {company_name}</p>',
            'module_name' => $moduleName,
            'event_type' => $eventType,
            'placeholders' => json_encode(['user_name', 'company_name']),
            'is_active' => true,
            'company_id' => $this->testCompanyId,
            'created_by' => $this->testUserId
        ];
    }
    
    /**
     * Generate a different module name from the given one
     */
    private function generateDifferentModule($currentModule) {
        $modules = [
            'users', 'sites', 'feasibility', 'installation', 
            'inventory', 'material_requests', 'dispatches'
        ];
        
        $availableModules = array_filter($modules, function($module) use ($currentModule) {
            return $module !== $currentModule;
        });
        
        return $this->generateRandomChoice($availableModules);
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData() {
        if (!empty($this->createdTemplates)) {
            $model = new EmailTemplate();
            foreach ($this->createdTemplates as $templateId) {
                try {
                    $model->delete($templateId);
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
    $test = new EmailTemplateOrganizationPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}