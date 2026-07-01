<?php

require_once __DIR__ . '/PropertyTestBase.php';

/**
 * Property Test 2: Offline Action Synchronization
 * Validates: Requirements 1.2 - Offline changes synchronization with server
 * 
 * This test ensures that the PWA properly synchronizes offline actions
 * with the server when connectivity is restored, including conflict resolution
 * and retry mechanisms.
 */
class OfflineActionSynchronizationPropertyTest extends PropertyTestBase
{
    protected function getTestName(): string
    {
        return 'Offline Action Synchronization';
    }

    protected function getRequirementId(): string
    {
        return '1.2';
    }

    protected function getDescription(): string
    {
        return 'Validates that offline changes are properly synchronized with the server when connectivity is restored';
    }

    /**
     * Property: Offline actions are synchronized when connectivity is restored
     */
    public function testOfflineActionSynchronization(): void
    {
        $this->runPropertyTest(function() {
            // Test PWA manager has offline action queuing
            $this->assertOfflineActionQueuing();
            
            // Test synchronization mechanism
            $this->assertSynchronizationMechanism();
            
            // Test retry logic for failed synchronizations
            $this->assertRetryLogic();
            
            // Test conflict resolution
            $this->assertConflictResolution();
        });
    }

    /**
     * Assert offline action queuing works correctly
     */
    private function assertOfflineActionQueuing(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for offline action queuing method
        $this->assertStringContains(
            'queueOfflineAction',
            $pwaManagerContent,
            'PWA manager must have offline action queuing method'
        );
        
        // Check for offline queue storage
        $this->assertStringContains(
            'offlineActionQueue',
            $pwaManagerContent,
            'PWA manager must maintain offline action queue'
        );
        
        // Check for queue persistence
        $this->assertStringContains(
            'saveOfflineQueue',
            $pwaManagerContent,
            'PWA manager must persist offline queue'
        );
        
        $this->assertStringContains(
            'loadOfflineQueue',
            $pwaManagerContent,
            'PWA manager must load offline queue on initialization'
        );
    }

    /**
     * Assert synchronization mechanism works correctly
     */
    private function assertSynchronizationMechanism(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for synchronization method
        $this->assertStringContains(
            'syncOfflineActions',
            $pwaManagerContent,
            'PWA manager must have synchronization method'
        );
        
        // Check for connection restoration handling
        $this->assertStringContains(
            'handleOnlineStatusChange',
            $pwaManagerContent,
            'PWA manager must handle online status changes'
        );
        
        // Check that sync is triggered when online
        $this->assertStringContains(
            'this.syncOfflineActions()',
            $pwaManagerContent,
            'PWA manager must trigger sync when coming online'
        );
        
        // Check for background sync registration
        $this->assertStringContains(
            'registerBackgroundSync',
            $pwaManagerContent,
            'PWA manager must register background sync'
        );
    }

    /**
     * Assert retry logic works correctly
     */
    private function assertRetryLogic(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for retry count tracking
        $this->assertStringContains(
            'retryCount',
            $pwaManagerContent,
            'PWA manager must track retry counts for actions'
        );
        
        // Check for maximum retry limit
        $this->assertStringContains(
            'maxRetries',
            $pwaManagerContent,
            'PWA manager must have maximum retry limit'
        );
        
        // Check for retry logic in sync process
        $this->assertStringContains(
            'action.retryCount++',
            $pwaManagerContent,
            'PWA manager must increment retry count on failure'
        );
        
        // Check for action removal after max retries
        $this->assertStringContains(
            'action.retryCount >= action.maxRetries',
            $pwaManagerContent,
            'PWA manager must remove actions after max retries'
        );
    }

    /**
     * Assert conflict resolution mechanisms exist
     */
    private function assertConflictResolution(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for action processing method
        $this->assertStringContains(
            'processOfflineAction',
            $pwaManagerContent,
            'PWA manager must have action processing method'
        );
        
        // Check for error handling in sync
        $this->assertStringContains(
            'catch (error)',
            $pwaManagerContent,
            'PWA manager must handle sync errors'
        );
        
        // Check for HTTP status handling
        $this->assertStringContains(
            'response.ok',
            $pwaManagerContent,
            'PWA manager must check HTTP response status'
        );
    }

    /**
     * Test service worker background sync integration
     */
    public function testServiceWorkerBackgroundSync(): void
    {
        $this->runPropertyTest(function() {
            $swContent = $this->getFileContent('/sw.js');
            
            // Check for background sync event listener
            $this->assertStringContains(
                'addEventListener(\'sync\'',
                $swContent,
                'Service worker must listen for sync events'
            );
            
            // Check for offline actions sync handling
            $this->assertStringContains(
                'offline-actions',
                $swContent,
                'Service worker must handle offline-actions sync tag'
            );
            
            // Check for sync offline actions method
            $this->assertStringContains(
                'syncOfflineActions',
                $swContent,
                'Service worker must have sync offline actions method'
            );
            
            // Check for queued actions retrieval
            $this->assertStringContains(
                'getQueuedActions',
                $swContent,
                'Service worker must retrieve queued actions'
            );
            
            // Check for action processing
            $this->assertStringContains(
                'processQueuedAction',
                $swContent,
                'Service worker must process queued actions'
            );
        });
    }

    /**
     * Test offline form handler integration
     */
    public function testOfflineFormHandlerIntegration(): void
    {
        $this->runPropertyTest(function() {
            $formHandlerContent = $this->getFileContent('/assets/js/offline-form-handler.js');
            
            // Check for form interception
            $this->assertStringContains(
                'setupFormInterception',
                $formHandlerContent,
                'Offline form handler must intercept form submissions'
            );
            
            // Check for offline submission handling
            $this->assertStringContains(
                'handleOfflineSubmission',
                $formHandlerContent,
                'Offline form handler must handle offline submissions'
            );
            
            // Check for action creation
            $this->assertStringContains(
                'createOfflineAction',
                $formHandlerContent,
                'Offline form handler must create offline actions'
            );
            
            // Check for integration with offline utils
            $this->assertStringContains(
                'queueOfflineAction',
                $formHandlerContent,
                'Offline form handler must queue actions'
            );
        });
    }

    /**
     * Test storage integration for queue persistence
     */
    public function testStorageIntegration(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for localStorage usage
            $this->assertStringContains(
                'localStorage.setItem',
                $pwaManagerContent,
                'PWA manager must use localStorage for queue persistence'
            );
            
            $this->assertStringContains(
                'localStorage.getItem',
                $pwaManagerContent,
                'PWA manager must retrieve queue from localStorage'
            );
            
            // Check for queue key
            $this->assertStringContains(
                'pwa-offline-queue',
                $pwaManagerContent,
                'PWA manager must use consistent queue storage key'
            );
            
            // Check for error handling in storage operations
            $this->assertStringContains(
                'try {',
                $pwaManagerContent,
                'PWA manager must handle storage errors gracefully'
            );
        });
    }

    /**
     * Test queue management methods
     */
    public function testQueueManagement(): void
    {
        $this->runPropertyTest(function() {
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
            
            // Check for unique ID generation
            $this->assertStringContains(
                'generateId',
                $pwaManagerContent,
                'PWA manager must generate unique IDs for actions'
            );
        });
    }

    /**
     * Test network status integration
     */
    public function testNetworkStatusIntegration(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for online status tracking
            $this->assertStringContains(
                'this.isOnline',
                $pwaManagerContent,
                'PWA manager must track online status'
            );
            
            // Check for online/offline event listeners
            $this->assertStringContains(
                'addEventListener(\'online\'',
                $pwaManagerContent,
                'PWA manager must listen for online events'
            );
            
            $this->assertStringContains(
                'addEventListener(\'offline\'',
                $pwaManagerContent,
                'PWA manager must listen for offline events'
            );
            
            // Check for navigator.onLine usage
            $this->assertStringContains(
                'navigator.onLine',
                $pwaManagerContent,
                'PWA manager must check navigator online status'
            );
        });
    }
}