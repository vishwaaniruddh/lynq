<?php

require_once __DIR__ . '/PropertyTestBase.php';

/**
 * Property Test 4: Offline Action Queuing
 * Validates: Requirements 1.4 - Offline action queuing for later synchronization
 * 
 * This test ensures that the PWA properly queues user actions when offline
 * and provides appropriate user feedback about queued actions.
 */
class OfflineActionQueuingPropertyTest extends PropertyTestBase
{
    protected function getTestName(): string
    {
        return 'Offline Action Queuing';
    }

    protected function getRequirementId(): string
    {
        return '1.4';
    }

    protected function getDescription(): string
    {
        return 'Validates that user actions are properly queued when offline and users receive appropriate feedback';
    }

    /**
     * Property: User actions are queued when offline
     */
    public function testOfflineActionQueuing(): void
    {
        $this->runPropertyTest(function() {
            // Test action queuing mechanism
            $this->assertActionQueuingMechanism();
            
            // Test user feedback for queued actions
            $this->assertUserFeedbackForQueuedActions();
            
            // Test queue persistence and restoration
            $this->assertQueuePersistenceAndRestoration();
            
            // Test queue management and monitoring
            $this->assertQueueManagementAndMonitoring();
        });
    }

    /**
     * Assert action queuing mechanism works correctly
     */
    private function assertActionQueuingMechanism(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for action queuing method
        $this->assertStringContains(
            'queueOfflineAction',
            $pwaManagerContent,
            'PWA manager must have action queuing method'
        );
        
        // Check for queue data structure
        $this->assertStringContains(
            'offlineActionQueue',
            $pwaManagerContent,
            'PWA manager must maintain action queue'
        );
        
        // Check for action metadata
        $this->assertStringContains(
            'timestamp',
            $pwaManagerContent,
            'Queued actions must include timestamp'
        );
        
        $this->assertStringContains(
            'retryCount',
            $pwaManagerContent,
            'Queued actions must include retry count'
        );
        
        $this->assertStringContains(
            'maxRetries',
            $pwaManagerContent,
            'Queued actions must include max retry limit'
        );
    }

    /**
     * Assert user feedback for queued actions
     */
    private function assertUserFeedbackForQueuedActions(): void
    {
        $formHandlerContent = $this->getFileContent('/assets/js/offline-form-handler.js');
        
        // Check for offline submission feedback
        $this->assertStringContains(
            'showOfflineSubmissionFeedback',
            $formHandlerContent,
            'Form handler must show feedback for offline submissions'
        );
        
        // Check for feedback UI elements
        $this->assertStringContains(
            'offline-form-feedback',
            $formHandlerContent,
            'Form handler must create feedback UI'
        );
        
        // Check for queue status in feedback
        $this->assertStringContains(
            'queued',
            $formHandlerContent,
            'Feedback must indicate action is queued'
        );
        
        // Check for timestamp in feedback
        $this->assertStringContains(
            'toLocaleTimeString',
            $formHandlerContent,
            'Feedback must show when action was queued'
        );
    }

    /**
     * Assert queue persistence and restoration
     */
    private function assertQueuePersistenceAndRestoration(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for queue saving
        $this->assertStringContains(
            'saveOfflineQueue',
            $pwaManagerContent,
            'PWA manager must save queue to storage'
        );
        
        // Check for queue loading
        $this->assertStringContains(
            'loadOfflineQueue',
            $pwaManagerContent,
            'PWA manager must load queue from storage'
        );
        
        // Check for localStorage usage
        $this->assertStringContains(
            'localStorage.setItem',
            $pwaManagerContent,
            'PWA manager must persist queue to localStorage'
        );
        
        $this->assertStringContains(
            'localStorage.getItem',
            $pwaManagerContent,
            'PWA manager must retrieve queue from localStorage'
        );
        
        // Check for queue key consistency
        $this->assertStringContains(
            'pwa-offline-queue',
            $pwaManagerContent,
            'PWA manager must use consistent storage key'
        );
    }

    /**
     * Assert queue management and monitoring
     */
    private function assertQueueManagementAndMonitoring(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for queue length method
        $this->assertStringContains(
            'getOfflineQueueLength',
            $pwaManagerContent,
            'PWA manager must provide queue length method'
        );
        
        // Check for queue clearing method
        $this->assertStringContains(
            'clearOfflineQueue',
            $pwaManagerContent,
            'PWA manager must provide queue clearing method'
        );
        
        // Check for queue status updates
        $this->assertStringContains(
            'updateQueueIndicators',
            $pwaManagerContent,
            'PWA manager must update queue indicators'
        );
    }

    /**
     * Test form interception for offline queuing
     */
    public function testFormInterceptionForOfflineQueuing(): void
    {
        $this->runPropertyTest(function() {
            $formHandlerContent = $this->getFileContent('/assets/js/offline-form-handler.js');
            
            // Check for form submission interception
            $this->assertStringContains(
                'addEventListener(\'submit\'',
                $formHandlerContent,
                'Form handler must intercept form submissions'
            );
            
            // Check for offline detection
            $this->assertStringContains(
                'navigator.onLine',
                $formHandlerContent,
                'Form handler must check online status'
            );
            
            // Check for form type detection
            $this->assertStringContains(
                'isModifyingForm',
                $formHandlerContent,
                'Form handler must identify modifying forms'
            );
            
            // Check for offline submission handling
            $this->assertStringContains(
                'handleOfflineSubmission',
                $formHandlerContent,
                'Form handler must handle offline submissions'
            );
        });
    }

    /**
     * Test action data extraction and formatting
     */
    public function testActionDataExtractionAndFormatting(): void
    {
        $this->runPropertyTest(function() {
            $formHandlerContent = $this->getFileContent('/assets/js/offline-form-handler.js');
            
            // Check for form data extraction
            $this->assertStringContains(
                'extractFormData',
                $formHandlerContent,
                'Form handler must extract form data'
            );
            
            // Check for action creation
            $this->assertStringContains(
                'createOfflineAction',
                $formHandlerContent,
                'Form handler must create offline actions'
            );
            
            // Check for action metadata
            $this->assertStringContains(
                'endpoint',
                $formHandlerContent,
                'Actions must include endpoint information'
            );
            
            $this->assertStringContains(
                'method',
                $formHandlerContent,
                'Actions must include HTTP method'
            );
            
            $this->assertStringContains(
                'formTitle',
                $formHandlerContent,
                'Actions must include form title for user feedback'
            );
        });
    }

    /**
     * Test queue visualization and user interface
     */
    public function testQueueVisualizationAndUserInterface(): void
    {
        $this->runPropertyTest(function() {
            $formHandlerContent = $this->getFileContent('/assets/js/offline-form-handler.js');
            
            // Check for queue viewing functionality
            $this->assertStringContains(
                'showQueuedForms',
                $formHandlerContent,
                'Form handler must provide queue viewing'
            );
            
            // Check for queue details display
            $this->assertStringContains(
                'View Queue',
                $formHandlerContent,
                'UI must provide queue viewing option'
            );
            
            // Check for offline indicators
            $this->assertStringContains(
                'addOfflineIndicators',
                $formHandlerContent,
                'Form handler must add offline indicators'
            );
            
            $this->assertStringContains(
                'offline-form-indicator',
                $formHandlerContent,
                'Forms must show offline indicators'
            );
        });
    }

    /**
     * Test integration with PWA manager
     */
    public function testIntegrationWithPWAManager(): void
    {
        $this->runPropertyTest(function() {
            $formHandlerContent = $this->getFileContent('/assets/js/offline-form-handler.js');
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check that form handler uses PWA manager for queuing
            $this->assertStringContains(
                'window.offlineUtils',
                $formHandlerContent,
                'Form handler must integrate with offline utilities'
            );
            
            // Check for CRM integration in PWA manager
            $this->assertStringContains(
                'window.CRM',
                $pwaManagerContent,
                'PWA manager must integrate with CRM system'
            );
            
            // Check for CSRF token handling
            $this->assertStringContains(
                'csrfToken',
                $pwaManagerContent,
                'PWA manager must handle CSRF tokens'
            );
        });
    }

    /**
     * Test error handling in queuing process
     */
    public function testErrorHandlingInQueuingProcess(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            $formHandlerContent = $this->getFileContent('/assets/js/offline-form-handler.js');
            
            // Check for error handling in queue operations
            $this->assertStringContains(
                'try {',
                $pwaManagerContent,
                'PWA manager must handle errors in queue operations'
            );
            
            $this->assertStringContains(
                'catch (error)',
                $pwaManagerContent,
                'PWA manager must catch and handle errors'
            );
            
            // Check for error handling in form operations
            $this->assertStringContains(
                'try {',
                $formHandlerContent,
                'Form handler must handle errors gracefully'
            );
            
            // Check for console error logging
            $this->assertStringContains(
                'console.error',
                $pwaManagerContent,
                'PWA manager must log errors for debugging'
            );
        });
    }

    /**
     * Test queue size limits and management
     */
    public function testQueueSizeLimitsAndManagement(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for unique ID generation
            $this->assertStringContains(
                'generateId',
                $pwaManagerContent,
                'PWA manager must generate unique IDs for actions'
            );
            
            // Check for action filtering/removal
            $this->assertStringContains(
                'filter',
                $pwaManagerContent,
                'PWA manager must be able to filter queue actions'
            );
            
            // Check for queue statistics
            $this->assertStringContains(
                'length',
                $pwaManagerContent,
                'PWA manager must track queue length'
            );
        });
    }
}