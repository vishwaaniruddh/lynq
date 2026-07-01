<?php
/**
 * ADV Clarity Management System - PWA Test Base Class
 * Base class for PWA-related tests with common utilities and setup
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../config/pwa.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/SyncService.php';
require_once __DIR__ . '/../services/AnalyticsService.php';

class PWATestBase extends PropertyTestBase {
    
    protected $notificationService;
    protected $syncService;
    protected $analyticsService;
    protected $testUserId;
    protected $testCompanyId;
    
    public function setUp(): void {
        parent::setUp();
        
        // Initialize PWA services
        $this->notificationService = new NotificationService();
        $this->syncService = new SyncService();
        $this->analyticsService = new AnalyticsService();
        
        // Create test user and company
        $this->testUserId = $this->createTestUser();
        $this->testCompanyId = $this->createTestCompany();
    }
    
    public function tearDown(): void {
        // Clean up test data
        $this->cleanupPWATestData();
        parent::tearDown();
    }
    
    /**
     * Create test user for PWA testing
     */
    protected function createTestUser() {
        $db = DatabaseConfig::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, company_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $username = 'pwa_test_user_' . uniqid();
        $email = $username . '@test.com';
        $password = password_hash('test123', PASSWORD_DEFAULT);
        $companyId = $this->testCompanyId ?: 1;
        
        $stmt->bind_param("sssi", $username, $email, $password, $companyId);
        $stmt->execute();
        
        return $db->insert_id;
    }
    
    /**
     * Create test company for PWA testing
     */
    protected function createTestCompany() {
        $db = DatabaseConfig::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO companies (name, type, created_at)
            VALUES (?, 'contractor', NOW())
        ");
        
        $companyName = 'PWA Test Company ' . uniqid();
        $stmt->bind_param("s", $companyName);
        $stmt->execute();
        
        return $db->insert_id;
    }
    
    /**
     * Create test push subscription
     */
    protected function createTestPushSubscription($userId = null, $companyId = null) {
        $userId = $userId ?: $this->testUserId;
        $companyId = $companyId ?: $this->testCompanyId;
        
        $endpoint = 'https://fcm.googleapis.com/fcm/send/' . uniqid();
        $p256dh = base64_encode(random_bytes(65));
        $auth = base64_encode(random_bytes(16));
        
        return $this->notificationService->saveSubscription(
            $userId, $companyId, $endpoint, $p256dh, $auth
        );
    }
    
    /**
     * Create test sync queue item
     */
    protected function createTestSyncQueueItem($userId = null, $companyId = null, $status = 'pending') {
        $userId = $userId ?: $this->testUserId;
        $companyId = $companyId ?: $this->testCompanyId;
        
        $db = DatabaseConfig::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO sync_queue 
            (user_id, company_id, entity_type, entity_id, action_data, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $entityType = 'test_entity';
        $entityId = rand(1, 1000);
        $actionData = json_encode([
            'method' => 'POST',
            'endpoint' => '/api/test',
            'data' => ['test' => 'data']
        ]);
        
        $stmt->bind_param("iisiss", $userId, $companyId, $entityType, $entityId, $actionData, $status);
        $stmt->execute();
        
        return $db->insert_id;
    }
    
    /**
     * Create test analytics event
     */
    protected function createTestAnalyticsEvent($userId = null, $companyId = null, $eventType = 'test_event') {
        $userId = $userId ?: $this->testUserId;
        $companyId = $companyId ?: $this->testCompanyId;
        
        return $this->analyticsService->trackPWAEvent([
            'user_id' => $userId,
            'company_id' => $companyId,
            'event_type' => $eventType,
            'event_data' => ['test' => 'data'],
            'user_agent' => 'PWA Test Agent',
            'ip_address' => '127.0.0.1'
        ]);
    }
    
    /**
     * Generate test offline action
     */
    protected function generateTestOfflineAction() {
        return [
            'id' => uniqid(),
            'endpoint' => '/api/test/action',
            'method' => 'POST',
            'data' => [
                'id' => rand(1, 1000),
                'name' => 'Test Action ' . uniqid(),
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timestamp' => time(),
            'retryCount' => 0,
            'maxRetries' => 3
        ];
    }
    
    /**
     * Generate test push notification data
     */
    protected function generateTestNotificationData() {
        return [
            'title' => 'Test Notification ' . uniqid(),
            'body' => 'This is a test notification for PWA testing',
            'icon' => '/assets/icons/icon-192.png',
            'badge' => '/assets/icons/icon-72.png',
            'tag' => 'test-' . uniqid(),
            'data' => [
                'url' => '/dashboard.php',
                'action' => 'test',
                'timestamp' => time()
            ]
        ];
    }
    
    /**
     * Mock service worker environment
     */
    protected function mockServiceWorkerEnvironment() {
        // Mock global objects that would be available in service worker
        $GLOBALS['self'] = new stdClass();
        $GLOBALS['caches'] = new stdClass();
        $GLOBALS['clients'] = new stdClass();
        
        return true;
    }
    
    /**
     * Simulate offline condition
     */
    protected function simulateOfflineCondition() {
        // This would be used in integration tests
        // For unit tests, we mock the network conditions
        return [
            'online' => false,
            'connection' => 'none',
            'effectiveType' => 'none'
        ];
    }
    
    /**
     * Simulate online condition
     */
    protected function simulateOnlineCondition() {
        return [
            'online' => true,
            'connection' => 'wifi',
            'effectiveType' => '4g'
        ];
    }
    
    /**
     * Assert PWA feature is enabled
     */
    protected function assertPWAFeatureEnabled($feature) {
        $config = getPWAConfig();
        $this->assertTrue(
            $config['features'][$feature] ?? false,
            "PWA feature '$feature' should be enabled"
        );
    }
    
    /**
     * Assert push subscription is valid
     */
    protected function assertValidPushSubscription($subscription) {
        $this->assertIsArray($subscription);
        $this->assertArrayHasKey('id', $subscription);
        $this->assertArrayHasKey('user_id', $subscription);
        $this->assertArrayHasKey('company_id', $subscription);
        $this->assertArrayHasKey('endpoint', $subscription);
        $this->assertArrayHasKey('p256dh_key', $subscription);
        $this->assertArrayHasKey('auth_key', $subscription);
        
        // Validate endpoint format
        $this->assertStringStartsWith('https://', $subscription['endpoint']);
        
        // Validate key lengths
        $this->assertGreaterThan(0, strlen($subscription['p256dh_key']));
        $this->assertGreaterThan(0, strlen($subscription['auth_key']));
    }
    
    /**
     * Assert sync queue item is valid
     */
    protected function assertValidSyncQueueItem($item) {
        $this->assertIsArray($item);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('user_id', $item);
        $this->assertArrayHasKey('company_id', $item);
        $this->assertArrayHasKey('entity_type', $item);
        $this->assertArrayHasKey('action_data', $item);
        $this->assertArrayHasKey('status', $item);
        
        // Validate status
        $validStatuses = ['pending', 'processing', 'completed', 'failed', 'conflict', 'resolved'];
        $this->assertContains($item['status'], $validStatuses);
        
        // Validate action data is valid JSON
        $actionData = json_decode($item['action_data'], true);
        $this->assertIsArray($actionData);
        $this->assertArrayHasKey('method', $actionData);
        $this->assertArrayHasKey('endpoint', $actionData);
    }
    
    /**
     * Assert analytics event is valid
     */
    protected function assertValidAnalyticsEvent($eventType, $eventData = []) {
        // This would typically check the database for the event
        $db = DatabaseConfig::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT * FROM pwa_analytics 
            WHERE user_id = ? AND company_id = ? AND event_type = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        
        $stmt->bind_param("iis", $this->testUserId, $this->testCompanyId, $eventType);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        
        $this->assertNotNull($event, "Analytics event '$eventType' should exist");
        $this->assertEquals($eventType, $event['event_type']);
        
        if (!empty($eventData)) {
            $storedData = json_decode($event['event_data'], true);
            foreach ($eventData as $key => $value) {
                $this->assertEquals($value, $storedData[$key] ?? null);
            }
        }
    }
    
    /**
     * Clean up PWA test data
     */
    protected function cleanupPWATestData() {
        $db = DatabaseConfig::getInstance()->getConnection();
        
        // Clean up in reverse order of dependencies
        $tables = [
            'pwa_analytics',
            'sync_queue', 
            'push_subscriptions',
            'users',
            'companies'
        ];
        
        foreach ($tables as $table) {
            if ($table === 'users' && $this->testUserId) {
                $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->bind_param("i", $this->testUserId);
                $stmt->execute();
            } elseif ($table === 'companies' && $this->testCompanyId) {
                $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->bind_param("i", $this->testCompanyId);
                $stmt->execute();
            } else {
                // Clean up by user/company for other tables
                if ($this->testUserId && $this->testCompanyId) {
                    $stmt = $db->prepare("DELETE FROM $table WHERE user_id = ? AND company_id = ?");
                    $stmt->bind_param("ii", $this->testUserId, $this->testCompanyId);
                    $stmt->execute();
                }
            }
        }
    }
}
?>