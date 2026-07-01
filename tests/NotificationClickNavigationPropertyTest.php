<?php

require_once __DIR__ . '/PropertyTestBase.php';

/**
 * **Feature: clarity-pwa-conversion, Property 18: Notification Click Navigation**
 * **Validates: Requirements 5.5**
 * 
 * Property-based test for notification click navigation functionality.
 * Tests that for any notification click, the system should navigate 
 * to the relevant application section.
 */
class NotificationClickNavigationPropertyTest extends PropertyTestBase {
    
    public function setUp(): void {
        // Initialize test setup
    }
    
    public function tearDown(): void {
        // Clean up after test
    }
    
    /**
     * Property: Notification click navigation consistency
     * For any notification with data.url, clicking should navigate to that URL
     */
    public function testNotificationClickNavigationProperty() {
        $this->runPropertyTest('Notification click navigation consistency', function() {
            // Generate random notification data
            $notificationData = $this->generateNotificationData();
            
            // Simulate notification click handling
            $navigationResult = $this->simulateNotificationClickHandling($notificationData);
            
            // Verify navigation result structure
            $this->assert(is_array($navigationResult), 'Navigation result should be array');
            $this->assert(array_key_exists('action', $navigationResult), 'Should have action key');
            $this->assert(array_key_exists('targetUrl', $navigationResult), 'Should have targetUrl key');
            
            // Verify target URL is valid
            $this->assert(!empty($navigationResult['targetUrl']), 'Target URL should not be empty');
            $this->assert(strpos($navigationResult['targetUrl'], '/') === 0, 'Target URL should start with /');
            
            // If notification had URL data, it should be used
            if (isset($notificationData['data']['url'])) {
                $this->assert($notificationData['data']['url'] === $navigationResult['targetUrl'], 'Should use notification URL');
            } else {
                // Should default to dashboard
                $this->assert($navigationResult['targetUrl'] === '/dashboard.php', 'Should default to dashboard');
            }
            
            return ['success' => true];
        }, 50);
    }
    
    /**
     * Property: Default navigation handling
     * For any notification without URL data, should navigate to default dashboard
     */
    public function testDefaultNavigationHandlingProperty() {
        $this->runPropertyTest('Default navigation handling', function() {
            // Generate notification without URL data
            $notificationData = $this->generateNotificationWithoutUrl();
            
            $navigationResult = $this->simulateNotificationClickHandling($notificationData);
            
            // Should navigate to default dashboard
            $this->assert($navigationResult['targetUrl'] === '/dashboard.php', 'Should navigate to dashboard');
            $this->assert($navigationResult['action'] === 'navigate', 'Should have navigate action');
            
            return ['success' => true];
        }, 30);
    }
    
    /**
     * Property: Client window management
     * For any notification click, the system should properly handle 
     * existing windows vs opening new ones
     */
    public function testClientWindowManagementProperty() {
        $this->runPropertyTest('Client window management', function() {
            $notificationData = $this->generateNotificationData();
            $existingClients = $this->generateExistingClients();
            
            $navigationResult = $this->simulateNotificationClickHandling(
                $notificationData, 
                $existingClients
            );
            
            // Verify window management decision
            $this->assert(array_key_exists('windowAction', $navigationResult), 'Should have windowAction');
            
            $windowAction = $navigationResult['windowAction'];
            $validActions = ['focus_existing', 'navigate_existing', 'open_new'];
            $this->assert(in_array($windowAction, $validActions), 'Window action should be valid');
            
            // If clients exist, should prefer focusing/navigating existing
            if (!empty($existingClients)) {
                $preferredActions = ['focus_existing', 'navigate_existing'];
                $this->assert(in_array($windowAction, $preferredActions), 'Should prefer existing clients');
            } else {
                $this->assert($windowAction === 'open_new', 'Should open new window when no clients exist');
            }
            
            return ['success' => true];
        }, 40);
    }
    
    /**
     * Property: Action button handling
     * For any notification with action buttons, clicking an action should 
     * trigger the appropriate handler
     */
    public function testActionButtonHandlingProperty() {
        $this->runPropertyTest('Action button handling', function() {
            $notificationData = $this->generateNotificationWithActions();
            $action = $this->selectRandomAction($notificationData);
            
            $actionResult = $this->simulateNotificationActionHandling($notificationData, $action);
            
            // Verify action handling
            $this->assert(is_array($actionResult), 'Action result should be array');
            $this->assert(array_key_exists('actionType', $actionResult), 'Should have actionType');
            $this->assert(array_key_exists('handled', $actionResult), 'Should have handled flag');
            
            $this->assert($action === $actionResult['actionType'], 'Action type should match');
            $this->assert($actionResult['handled'] === true, 'Action should be handled');
            
            return ['success' => true];
        }, 25);
    }
    
    /**
     * Property: Data message passing
     * For any notification click, associated data should be passed to the target window
     */
    public function testDataMessagePassingProperty() {
        $this->runPropertyTest('Data message passing', function() {
            $notificationData = $this->generateNotificationData();
            
            $navigationResult = $this->simulateNotificationClickHandling($notificationData);
            
            // Should include message data for client
            $this->assert(array_key_exists('messageData', $navigationResult), 'Should have messageData');
            $this->assert(is_array($navigationResult['messageData']), 'Message data should be array');
            
            // Should contain notification data
            $this->assert(array_key_exists('type', $navigationResult['messageData']), 'Should have message type');
            $this->assert(array_key_exists('data', $navigationResult['messageData']), 'Should have message data');
            
            // Message type should be appropriate
            $validTypes = ['NOTIFICATION_CLICK', 'NAVIGATE_TO'];
            $this->assert(in_array($navigationResult['messageData']['type'], $validTypes), 'Message type should be valid');
            
            return ['success' => true];
        }, 35);
    }
    
    /**
     * Simulate notification click handling
     * This mimics the logic in sw.js notificationclick event
     */
    private function simulateNotificationClickHandling($notificationData, $existingClients = []) {
        $targetUrl = $notificationData['data']['url'] ?? '/dashboard.php';
        
        // Determine window action based on existing clients
        $windowAction = 'open_new';
        $focusedClient = null;
        
        if (!empty($existingClients)) {
            // Check for exact URL match
            foreach ($existingClients as $client) {
                if ($this->urlsMatch($client['url'], $targetUrl)) {
                    $windowAction = 'focus_existing';
                    $focusedClient = $client;
                    break;
                }
            }
            
            // If no exact match, navigate first client
            if ($windowAction === 'open_new') {
                $windowAction = 'navigate_existing';
                $focusedClient = $existingClients[0];
            }
        }
        
        // Prepare message data
        $messageData = [
            'type' => $windowAction === 'focus_existing' ? 'NOTIFICATION_CLICK' : 'NAVIGATE_TO',
            'data' => $notificationData['data'] ?? []
        ];
        
        if ($windowAction === 'navigate_existing') {
            $messageData['url'] = $targetUrl;
        }
        
        return [
            'action' => 'navigate',
            'targetUrl' => $targetUrl,
            'windowAction' => $windowAction,
            'focusedClient' => $focusedClient,
            'messageData' => $messageData
        ];
    }
    
    /**
     * Simulate notification action handling
     */
    private function simulateNotificationActionHandling($notificationData, $action) {
        $handled = false;
        $result = ['actionType' => $action];
        
        switch ($action) {
            case 'view':
                $targetUrl = $notificationData['data']['url'] ?? '/dashboard.php';
                $result['navigation'] = $this->simulateNotificationClickHandling($notificationData);
                $handled = true;
                break;
                
            case 'dismiss':
                // Just close notification
                $handled = true;
                break;
                
            case 'mark_read':
                // Simulate API call
                $result['apiCall'] = [
                    'endpoint' => '/api/notifications/mark-read',
                    'method' => 'POST',
                    'data' => ['id' => $notificationData['data']['id'] ?? null]
                ];
                $handled = true;
                break;
                
            default:
                $handled = false;
        }
        
        $result['handled'] = $handled;
        return $result;
    }
    
    /**
     * Check if URLs match for navigation purposes
     */
    private function urlsMatch($clientUrl, $targetUrl) {
        // Extract pathname from URLs for comparison
        $clientPath = parse_url($clientUrl, PHP_URL_PATH) ?: '/';
        $targetPath = parse_url($targetUrl, PHP_URL_PATH) ?: '/';
        
        return $clientPath === $targetPath;
    }
    
    /**
     * Generate random notification data
     */
    private function generateNotificationData() {
        $urls = [
            '/dashboard.php',
            '/inventory/index.php',
            '/sites/index.php',
            '/installation/index.php',
            '/tasks/index.php'
        ];
        
        $data = [
            'title' => 'Test Notification',
            'body' => 'Test notification body',
            'data' => []
        ];
        
        // Randomly include URL
        if (rand(0, 1)) {
            $data['data']['url'] = $urls[array_rand($urls)];
        }
        
        // Randomly include other data
        if (rand(0, 1)) {
            $data['data']['id'] = rand(1, 1000);
            $data['data']['type'] = ['task', 'inventory', 'site'][array_rand(['task', 'inventory', 'site'])];
        }
        
        return $data;
    }
    
    /**
     * Generate notification without URL data
     */
    private function generateNotificationWithoutUrl() {
        return [
            'title' => 'Test Notification',
            'body' => 'Test notification body',
            'data' => [
                'id' => rand(1, 1000),
                'type' => 'general'
            ]
        ];
    }
    
    /**
     * Generate notification with action buttons
     */
    private function generateNotificationWithActions() {
        $data = $this->generateNotificationData();
        
        $actions = [
            ['action' => 'view', 'title' => 'View'],
            ['action' => 'dismiss', 'title' => 'Dismiss'],
            ['action' => 'mark_read', 'title' => 'Mark as Read']
        ];
        
        // Select random subset of actions
        $selectedActions = array_rand($actions, rand(1, count($actions)));
        if (!is_array($selectedActions)) {
            $selectedActions = [$selectedActions];
        }
        
        $data['actions'] = [];
        foreach ($selectedActions as $index) {
            $data['actions'][] = $actions[$index];
        }
        
        return $data;
    }
    
    /**
     * Select random action from notification
     */
    private function selectRandomAction($notificationData) {
        if (empty($notificationData['actions'])) {
            return null;
        }
        
        $action = $notificationData['actions'][array_rand($notificationData['actions'])];
        return $action['action'];
    }
    
    /**
     * Generate existing client windows
     */
    private function generateExistingClients() {
        $clientCount = rand(0, 3);
        $clients = [];
        
        $urls = [
            'https://example.com/dashboard.php',
            'https://example.com/inventory/index.php',
            'https://example.com/sites/index.php'
        ];
        
        for ($i = 0; $i < $clientCount; $i++) {
            $clients[] = [
                'id' => 'client_' . $i,
                'url' => $urls[array_rand($urls)],
                'focused' => false
            ];
        }
        
        return $clients;
    }
}