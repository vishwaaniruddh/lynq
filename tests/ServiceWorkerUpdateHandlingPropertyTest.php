<?php

require_once __DIR__ . '/PropertyTestBase.php';

/**
 * Property Test 21: Service Worker Update Handling
 * Validates: Requirements 6.5 - Service worker update lifecycle management
 * 
 * This test ensures that the PWA properly handles service worker updates,
 * including detection, user notification, and graceful update application.
 */
class ServiceWorkerUpdateHandlingPropertyTest extends PropertyTestBase
{
    protected function getTestName(): string
    {
        return 'Service Worker Update Handling';
    }

    protected function getRequirementId(): string
    {
        return '6.5';
    }

    protected function getDescription(): string
    {
        return 'Validates that the PWA properly handles service worker updates with user notification and graceful update application';
    }

    /**
     * Property: Service worker update detection and handling
     */
    public function testServiceWorkerUpdateHandling(): void
    {
        $this->runPropertyTest(function() {
            // Test service worker registration and update detection
            $this->assertServiceWorkerUpdateDetection();
            
            // Test update notification system
            $this->assertUpdateNotificationSystem();
            
            // Test update application process
            $this->assertUpdateApplicationProcess();
            
            // Test update lifecycle management
            $this->assertUpdateLifecycleManagement();
        });
    }

    /**
     * Assert service worker update detection works correctly
     */
    private function assertServiceWorkerUpdateDetection(): void
    {
        // Check that service worker registration includes update detection
        $swContent = $this->getFileContent('/sw.js');
        
        $this->assertNotEmpty($swContent, 'Service worker file must exist');
        
        // Check PWA manager has update detection logic
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        $this->assertStringContains(
            'updatefound',
            $pwaManagerContent,
            'PWA manager must listen for service worker updates'
        );
        
        $this->assertStringContains(
            'handleServiceWorkerUpdate',
            $pwaManagerContent,
            'PWA manager must have update handling method'
        );
        
        $this->assertStringContains(
            'manageUpdateLifecycle',
            $pwaManagerContent,
            'PWA manager must have update lifecycle management'
        );
    }

    /**
     * Assert update notification system works correctly
     */
    private function assertUpdateNotificationSystem(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for update notification creation
        $this->assertStringContains(
            'showUpdateNotification',
            $pwaManagerContent,
            'PWA manager must show update notifications'
        );
        
        $this->assertStringContains(
            'createUpdateBanner',
            $pwaManagerContent,
            'PWA manager must create update notification UI'
        );
        
        // Check for user interaction handling
        $this->assertStringContains(
            'Update Now',
            $pwaManagerContent,
            'Update notification must have update action button'
        );
        
        $this->assertStringContains(
            'applyUpdate',
            $pwaManagerContent,
            'PWA manager must have update application method'
        );
    }

    /**
     * Assert update application process works correctly
     */
    private function assertUpdateApplicationProcess(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for SKIP_WAITING message handling
        $this->assertStringContains(
            'SKIP_WAITING',
            $pwaManagerContent,
            'PWA manager must send SKIP_WAITING message to service worker'
        );
        
        // Check for page reload after update
        $this->assertStringContains(
            'window.location.reload',
            $pwaManagerContent,
            'PWA manager must reload page after update'
        );
        
        // Check for controller change handling
        $this->assertStringContains(
            'controllerchange',
            $pwaManagerContent,
            'PWA manager must handle service worker controller changes'
        );
    }

    /**
     * Assert update lifecycle management works correctly
     */
    private function assertUpdateLifecycleManagement(): void
    {
        $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
        
        // Check for waiting service worker handling
        $this->assertStringContains(
            'handleWaitingServiceWorker',
            $pwaManagerContent,
            'PWA manager must handle waiting service workers'
        );
        
        // Check for new service worker handling
        $this->assertStringContains(
            'handleNewServiceWorker',
            $pwaManagerContent,
            'PWA manager must handle new service worker installations'
        );
        
        // Check for first time installation handling
        $this->assertStringContains(
            'handleFirstTimeInstallation',
            $pwaManagerContent,
            'PWA manager must handle first time installations'
        );
        
        // Check for critical data refresh after updates
        $this->assertStringContains(
            'refreshCriticalData',
            $pwaManagerContent,
            'PWA manager must refresh critical data after updates'
        );
        
        // Check for update success messaging
        $this->assertStringContains(
            'App Updated',
            $pwaManagerContent,
            'PWA manager must show update success messages'
        );
    }

    /**
     * Test update notification UI elements
     */
    public function testUpdateNotificationUI(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check update banner structure
            $this->assertStringContains(
                'pwa-update-banner',
                $pwaManagerContent,
                'Update notification must have proper ID'
            );
            
            $this->assertStringContains(
                'bg-blue-600',
                $pwaManagerContent,
                'Update notification must have proper styling'
            );
            
            $this->assertStringContains(
                'fa-download',
                $pwaManagerContent,
                'Update notification must have download icon'
            );
            
            // Check auto-dismiss functionality
            $this->assertStringContains(
                'setTimeout',
                $pwaManagerContent,
                'Update notification must have auto-dismiss timer'
            );
            
            // Check dismiss button functionality
            $this->assertStringContains(
                'pwa-dismiss-btn',
                $pwaManagerContent,
                'Update notification must have dismiss button'
            );
        });
    }

    /**
     * Test periodic update checking
     */
    public function testPeriodicUpdateChecking(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for periodic update checking setup
            $this->assertStringContains(
                'setupUpdateChecking',
                $pwaManagerContent,
                'PWA manager must set up periodic update checking'
            );
            
            $this->assertStringContains(
                'setInterval',
                $pwaManagerContent,
                'PWA manager must use intervals for periodic checks'
            );
            
            $this->assertStringContains(
                'checkForUpdates',
            $pwaManagerContent,
                'PWA manager must have update checking method'
            );
            
            // Check for visibility change handling
            $this->assertStringContains(
                'visibilitychange',
                $pwaManagerContent,
                'PWA manager must check for updates when page becomes visible'
            );
        });
    }

    /**
     * Test service worker message handling for updates
     */
    public function testServiceWorkerMessageHandling(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for service worker message handling
            $this->assertStringContains(
                'handleServiceWorkerMessage',
                $pwaManagerContent,
                'PWA manager must handle service worker messages'
            );
            
            // Check for specific message types
            $this->assertStringContains(
                'CACHE_UPDATED',
                $pwaManagerContent,
                'PWA manager must handle cache update messages'
            );
            
            // Check for message event listener
            $this->assertStringContains(
                'addEventListener(\'message\'',
                $pwaManagerContent,
                'PWA manager must listen for service worker messages'
            );
        });
    }

    /**
     * Test update state management
     */
    public function testUpdateStateManagement(): void
    {
        $this->runPropertyTest(function() {
            $pwaManagerContent = $this->getFileContent('/assets/js/pwa-manager.js');
            
            // Check for update available flag
            $this->assertStringContains(
                'updateAvailable',
                $pwaManagerContent,
                'PWA manager must track update availability state'
            );
            
            // Check for update flag management
            $this->assertStringContains(
                'this.updateAvailable = true',
                $pwaManagerContent,
                'PWA manager must set update available flag'
            );
            
            $this->assertStringContains(
                'this.updateAvailable = false',
                $pwaManagerContent,
                'PWA manager must clear update available flag'
            );
        });
    }

    /**
     * Test integration with base layout
     */
    public function testBaseLayoutIntegration(): void
    {
        $this->runPropertyTest(function() {
            $baseLayoutContent = $this->getFileContent('/views/layouts/base.php');
            
            // Check that PWA manager is loaded
            $this->assertStringContains(
                'pwa-manager.js',
                $baseLayoutContent,
                'Base layout must include PWA manager script'
            );
            
            // Check for service worker registration in base layout
            $this->assertStringContains(
                'serviceWorker.register',
                $baseLayoutContent,
                'Base layout must include service worker registration'
            );
            
            // Check for update handling in base layout
            $this->assertStringContains(
                'updatefound',
                $baseLayoutContent,
                'Base layout must handle service worker updates'
            );
        });
    }
}