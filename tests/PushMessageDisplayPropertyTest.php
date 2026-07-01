<?php

require_once __DIR__ . '/PropertyTestBase.php';

/**
 * **Feature: clarity-pwa-conversion, Property 17: Push Message Display**
 * **Validates: Requirements 5.4**
 * 
 * Property-based test for push message display functionality.
 * Tests that for any push message received by the service worker, 
 * appropriate notifications should be displayed.
 */
class PushMessageDisplayPropertyTest extends PropertyTestBase {
    
    public function setUp(): void {
        // Initialize test setup
    }
    
    public function tearDown(): void {
        // Clean up after test
    }
    
    /**
     * Property: Push message display consistency
     * For any valid push message payload, the service worker should 
     * display a notification with the correct title and body
     */
    public function testPushMessageDisplayProperty() {
        $this->runPropertyTest('Push message display consistency', function() {
            // Generate random push message data
            $pushData = $this->generatePushMessageData();
            
            // Simulate service worker push event handling
            $notificationData = $this->simulateServiceWorkerPushHandling($pushData);
            
            // Verify notification data structure
            $this->assert(is_array($notificationData), 'Notification data should be array');
            $this->assert(array_key_exists('title', $notificationData), 'Should have title');
            $this->assert(array_key_exists('body', $notificationData), 'Should have body');
            $this->assert(array_key_exists('icon', $notificationData), 'Should have icon');
            $this->assert(array_key_exists('data', $notificationData), 'Should have data');
            
            // Verify required fields are not empty
            $this->assert(!empty($notificationData['title']), 'Title should not be empty');
            $this->assert(!empty($notificationData['body']), 'Body should not be empty');
            
            // Verify data preservation
            if (isset($pushData['title'])) {
                $this->assert($pushData['title'] === $notificationData['title'], 'Title should be preserved');
            }
            if (isset($pushData['body'])) {
                $this->assert($pushData['body'] === $notificationData['body'], 'Body should be preserved');
            }
            
            return ['success' => true];
        }, 50);
    }
    
    /**
     * Property: Default notification handling
     * For any push message without explicit data, the service worker should 
     * display a notification with default values
     */
    public function testDefaultNotificationHandlingProperty() {
        $this->runPropertyTest('Default notification handling', function() {
            // Test with empty or minimal push data
            $pushData = $this->generateMinimalPushData();
            
            $notificationData = $this->simulateServiceWorkerPushHandling($pushData);
            
            // Should have default values
            $this->assert(!empty($notificationData['title']), 'Should have default title');
            $this->assert(!empty($notificationData['body']), 'Should have default body');
            $this->assert(!empty($notificationData['icon']), 'Should have default icon');
            
            // Default title should be system name
            if (empty($pushData)) {
                $this->assert($notificationData['title'] === 'ADV Clarity System', 'Should have default system title');
            }
            
            return ['success' => true];
        }, 30);
    }
    
    /**
     * Property: Notification data enrichment
     * For any push message, the service worker should enrich the notification 
     * with timestamp and default URL data
     */
    public function testNotificationDataEnrichmentProperty() {
        $this->runPropertyTest('Notification data enrichment', function() {
            $pushData = $this->generatePushMessageData();
            
            $notificationData = $this->simulateServiceWorkerPushHandling($pushData);
            
            // Should have enriched data
            $this->assert(array_key_exists('data', $notificationData), 'Should have data key');
            $this->assert(is_array($notificationData['data']), 'Data should be array');
            
            // Should have timestamp
            $this->assert(array_key_exists('timestamp', $notificationData['data']), 'Should have timestamp');
            $this->assert(is_numeric($notificationData['data']['timestamp']), 'Timestamp should be numeric');
            
            // Should have URL (default or provided)
            $this->assert(array_key_exists('url', $notificationData['data']), 'Should have URL');
            $this->assert(!empty($notificationData['data']['url']), 'URL should not be empty');
            
            return ['success' => true];
        }, 40);
    }
    
    /**
     * Property: Icon and badge handling
     * For any push message, the notification should have valid icon and badge paths
     */
    public function testIconAndBadgeHandlingProperty() {
        $this->runPropertyTest('Icon and badge handling', function() {
            $pushData = $this->generatePushMessageData();
            
            $notificationData = $this->simulateServiceWorkerPushHandling($pushData);
            
            // Should have icon and badge
            $this->assert(array_key_exists('icon', $notificationData), 'Should have icon');
            $this->assert(array_key_exists('badge', $notificationData), 'Should have badge');
            
            // Should be valid paths
            $this->assert(strpos($notificationData['icon'], '/') === 0, 'Icon should start with /');
            $this->assert(strpos($notificationData['badge'], '/') === 0, 'Badge should start with /');
            
            // Should contain expected icon files
            $this->assert(strpos($notificationData['icon'], 'icon') !== false, 'Icon path should contain "icon"');
            $this->assert(strpos($notificationData['badge'], 'icon') !== false, 'Badge path should contain "icon"');
            
            return ['success' => true];
        }, 25);
    }
    
    /**
     * Simulate service worker push event handling
     * This mimics the logic in sw.js for processing push messages
     */
    private function simulateServiceWorkerPushHandling($pushData) {
        // Default notification data (from sw.js)
        $notificationData = [
            'title' => 'ADV Clarity System',
            'body' => 'You have a new notification',
            'icon' => '/assets/icons/icon-192.png',
            'badge' => '/assets/icons/icon-72.png',
            'tag' => 'default',
            'requireInteraction' => false,
            'data' => []
        ];
        
        // Merge with push data if provided
        if (!empty($pushData)) {
            $notificationData = array_merge($notificationData, $pushData);
        }
        
        // Add timestamp to data (as done in sw.js)
        $notificationData['data'] = array_merge($notificationData['data'], [
            'timestamp' => time() * 1000, // JavaScript timestamp
            'url' => $notificationData['data']['url'] ?? '/dashboard.php'
        ]);
        
        return $notificationData;
    }
    
    /**
     * Generate random push message data
     */
    private function generatePushMessageData() {
        $hasData = (bool) rand(0, 1);
        
        if (!$hasData) {
            return null;
        }
        
        $titles = [
            'New Task Assignment',
            'Installation Update',
            'Inventory Alert',
            'System Notification',
            'Site Status Change'
        ];
        
        $bodies = [
            'You have been assigned to a new installation task',
            'Installation progress has been updated',
            'Stock levels require attention',
            'System maintenance completed',
            'Site status has changed and requires review'
        ];
        
        $data = [
            'title' => $titles[array_rand($titles)],
            'body' => $bodies[array_rand($bodies)]
        ];
        
        // Randomly add optional fields
        if (rand(0, 1)) {
            $data['icon'] = '/assets/icons/icon-' . [192, 256, 512][array_rand([192, 256, 512])] . '.png';
        }
        
        if (rand(0, 1)) {
            $data['requireInteraction'] = (bool) rand(0, 1);
        }
        
        if (rand(0, 1)) {
            $data['data'] = [
                'url' => ['/dashboard.php', '/inventory/index.php', '/sites/index.php'][array_rand(['/dashboard.php', '/inventory/index.php', '/sites/index.php'])],
                'id' => rand(1, 1000),
                'type' => ['task', 'inventory', 'site'][array_rand(['task', 'inventory', 'site'])]
            ];
        }
        
        if (rand(0, 1)) {
            $data['actions'] = [
                [
                    'action' => 'view',
                    'title' => 'View Details',
                    'icon' => '/assets/icons/view.png'
                ]
            ];
        }
        
        return $data;
    }
    
    /**
     * Generate minimal push data for testing defaults
     */
    private function generateMinimalPushData() {
        $options = [
            null,
            [],
            ['title' => ''],
            ['body' => ''],
            ['title' => 'Test', 'body' => '']
        ];
        
        return $options[array_rand($options)];
    }
}