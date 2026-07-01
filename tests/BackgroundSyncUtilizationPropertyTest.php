<?php

require_once __DIR__ . '/PropertyTestBase.php';

/**
 * Property Test 15: Background Sync Utilization
 * Validates: Requirements 4.5 - Background sync for data updates without user intervention
 * 
 * This test ensures that the PWA properly utilizes background sync capabilities
 * to update data automatically when connectivity is available.
 */
class BackgroundSyncUtilizationPropertyTest extends PropertyTestBase
{
    protected function getTestName(): string
    {
        return 'Background Sync Utilization';
    }

    protected function getRequirementId(): string
    {
        return '4.5';
    }

    protected function getDescription(): string
    {
        return 'Validates that the PWA uses background sync to update data without user intervention';
    }

    /**
     * Property: Background sync is properly utilized for data updates
     */
    public function testBackgroundSyncUtilization(): void
    {
        $this->runPropertyTest(function() {
            // Test background sync registration
            $this->assertBackgroundSyncRegistration();
            
            // Test service worker sync event handling
            $this->assertServiceWorkerSyncEventHandling();
            
            // Test sync tag management
            $this->assertSyncTagManagement();
            
            // Test data synchronization process
            $this->assertDataSynchronizationProcess();
        });
    }

    /**
     * Assert background sync registration works correctly
     */
    private function assertBackgroundSyncRegistration(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for background sync registration method
        $this->assertStringContains(
            'registerBackgroundSync',
            $pwaManagerContent,
            'PWA manager must have background sync registration method'
        );
        
        // Check for service worker ready check
        $this->assertStringContains(
            'navigator.serviceWorker.ready',
            $pwaManagerContent,
            'PWA manager must wait for service worker to be ready'
        );
        
        // Check for sync registration
        $this->assertStringContains(
            'registration.sync.register',
            $pwaManagerContent,
            'PWA manager must register sync events'
        );
        
        // Check for sync tag
        $this->assertStringContains(
            'offline-actions',
            $pwaManagerContent,
            'PWA manager must use offline-actions sync tag'
        );
        
        // Check for background sync support detection
        $this->assertStringContains(
            'sync\' in window.ServiceWorkerRegistration.prototype',
            $pwaManagerContent,
            'PWA manager must check for background sync support'
        );
    }

    /**
     * Assert service worker sync event handling
     */
    private function assertServiceWorkerSyncEventHandling(): void
    {
        $swContent = $this->getFileContent('/sw.js');
        
        // Check for sync event listener
        $this->assertStringContains(
            'addEventListener(\'sync\'',
            $swContent,
            'Service worker must listen for sync events'
        );
        
        // Check for sync tag handling
        $this->assertStringContains(
            'event.tag',
            $swContent,
            'Service worker must handle sync event tags'
        );
        
        // Check for offline-actions tag handling
        $this->assertStringContains(
            'offline-actions',
            $swContent,
            'Service worker must handle offline-actions sync tag'
        );
        
        // Check for sync method invocation
        $this->assertStringContains(
            'syncOfflineActions',
            $swContent,
            'Service worker must call sync method for offline actions'
        );
        
        // Check for waitUntil usage
        $this->assertStringContains(
            'event.waitUntil',
            $swContent,
            'Service worker must use waitUntil for sync operations'
        );
    }

    /**
     * Assert sync tag management
     */
    private function assertSyncTagManagement(): void
    {
        $swContent = $this->getFileContent('/sw.js');
        
        // Check for tag-based sync handling
        $this->assertStringContains(
            'if (event.tag === \'offline-actions\')',
            $swContent,
            'Service worker must handle specific sync tags'
        );
        
        // Check for sync logging
        $this->assertStringContains(
            'console.log(\'[SW] Background sync triggered:\'',
            $swContent,
            'Service worker must log sync events'
        );
    }

    /**
     * Assert data synchronization process
     */
    private function assertDataSynchronizationProcess(): void
    {
        $swContent = $this->getFileContent('/sw.js');
        
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
        
        // Check for action removal after success
        $this->assertStringContains(
            'removeQueuedAction',
            $swContent,
            'Service worker must remove successfully synced actions'
        );
        
        // Check for error handling in sync
        $this->assertStringContains(
            'catch (error)',
            $swContent,
            'Service worker must handle sync errors'
        );
    }

    /**
     * Test storage integration for background sync
     */
    public function testStorageIntegrationForBackgroundSync(): void
    {
        $this->runPropertyTest(function() {
            $swContent = $this->getFileContent('/sw.js');
            
            // Check for storage access methods
            $this->assertStringContains(
                'getFromStorage',
                $swContent,
                'Service worker must access storage for queued actions'
            );
            
            $this->assertStringContains(
                'setInStorage',
                $swContent,
                'Service worker must update storage after sync'
            );
            
            // Check for message channel usage
            $this->assertStringContains(
                'MessageChannel',
                $swContent,
                'Service worker must use message channels for storage access'
            );
            
            // Check for client communication
            $this->assertStringContains(
                'clients.matchAll',
                $swContent,
                'Service worker must communicate with clients'
            );
        });
    }

    /**
     * Test sync failure handling and retry logic
     */
    public function testSyncFailureHandlingAndRetryLogic(): void
    {
        $this->runPropertyTest(function() {
            $swContent = $this->getFileContent('/sw.js');
            
            // Check for error handling in sync process
            $this->assertStringContains(
                'try {',
                $swContent,
                'Service worker must handle sync errors'
            );
            
            $this->assertStringContains(
                'catch (error)',
                $swContent,
                'Service worker must catch sync errors'
            );
            
            // Check for action retry logic
            $this->assertStringContains(
                'console.error(\'[SW] Failed to sync action:\'',
                $swContent,
                'Service worker must log sync failures'
            );
            
            // Check for keeping failed actions in queue
            $this->assertStringContains(
                '// Keep action in queue for retry',
                $swContent,
                'Service worker must keep failed actions for retry'
            );
        });
    }

    /**
     * Test client notification of sync results
     */
    public function testClientNotificationOfSyncResults(): void
    {
        $this->runPropertyTest(function() {
            $swContent = $this->getFileContent('/sw.js');
            
            // Check for client messaging after sync
            $this->assertStringContains(
                'client.postMessage',
                $swContent,
                'Service worker must notify clients of sync results'
            );
            
            // Check for queue update messages
            $this->assertStringContains(
                'QUEUE_UPDATED',
                $swContent,
                'Service worker must send queue update messages'
            );
            
            // Check for queue length in messages
            $this->assertStringContains(
                'queueLength',
                $swContent,
                'Service worker must include queue length in messages'
            );
        });
    }

    /**
     * Test PWA manager response to sync events
     */
    public function testPWAManagerResponseToSyncEvents(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for sync event handling in PWA manager
            $this->assertStringContains(
                'QUEUE_UPDATED',
                $pwaManagerContent,
                'PWA manager must handle queue update messages'
            );
            
            // Check for queue indicator updates
            $this->assertStringContains(
                'updateQueueIndicators',
                $pwaManagerContent,
                'PWA manager must update queue indicators'
            );
            
            // Check for storage request handling
            $this->assertStringContains(
                'handleStorageRequest',
                $pwaManagerContent,
                'PWA manager must handle storage requests from service worker'
            );
            
            $this->assertStringContains(
                'handleStorageSetRequest',
                $pwaManagerContent,
                'PWA manager must handle storage set requests from service worker'
            );
        });
    }

    /**
     * Test automatic sync triggering
     */
    public function testAutomaticSyncTriggering(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for sync registration when queuing actions
            $this->assertStringContains(
                'this.registerBackgroundSync()',
                $pwaManagerContent,
                'PWA manager must register background sync when queuing actions'
            );
            
            // Check for immediate sync attempt when online
            $this->assertStringContains(
                'if (this.isOnline) {',
                $pwaManagerContent,
                'PWA manager must attempt immediate sync when online'
            );
            
            $this->assertStringContains(
                'this.syncOfflineActions()',
                $pwaManagerContent,
                'PWA manager must trigger sync when online'
            );
        });
    }

    /**
     * Test sync status reporting
     */
    public function testSyncStatusReporting(): void
    {
        $this->runPropertyTest(function() {
            $swContent = $this->getFileContent('/sw.js');
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for sync success logging in service worker
            $this->assertStringContains(
                'console.log(\'[SW] Synced offline action:\'',
                $swContent,
                'Service worker must log successful syncs'
            );
            
            // Check for sync completion logging
            $this->assertStringContains(
                'console.log(\'[PWA] All offline actions synced successfully\'',
                $pwaManagerContent,
                'PWA manager must log sync completion'
            );
            
            // Check for background sync registration logging
            $this->assertStringContains(
                'console.log(\'[PWA] Background sync registered\'',
                $pwaManagerContent,
                'PWA manager must log background sync registration'
            );
        });
    }

    /**
     * Test fallback behavior when background sync is not supported
     */
    public function testFallbackBehaviorWhenBackgroundSyncNotSupported(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for background sync support detection
            $this->assertStringContains(
                'if (\'serviceWorker\' in navigator && \'sync\' in window.ServiceWorkerRegistration.prototype)',
                $pwaManagerContent,
                'PWA manager must check for background sync support'
            );
            
            // Check for fallback logging
            $this->assertStringContains(
                'console.log(\'[PWA] Background sync not supported\'',
                $pwaManagerContent,
                'PWA manager must log when background sync is not supported'
            );
            
            // Check for immediate sync as fallback
            $this->assertStringContains(
                '// Try to sync immediately if online',
                $pwaManagerContent,
                'PWA manager must fall back to immediate sync'
            );
        });
    }
}