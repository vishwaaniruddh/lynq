<?php
/**
 * PWA Integration Test Page
 * Simple test page to verify PWA features are working
 */

// Enable PWA testing
define('PWA_TESTING', true);

// Mock session data for testing
$isLoggedIn = true;
$currentUser = [
    'username' => 'test_user',
    'email' => 'test@example.com',
    'role_name' => 'Administrator',
    'company_type' => 'ADV'
];

$pageTitle = 'PWA Integration Test';
$baseUrl = '';

// Start output buffering for content
ob_start();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">PWA Integration Test</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- PWA Status -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h2 class="text-lg font-semibold text-blue-800 mb-3">PWA Status</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Service Worker:</span>
                        <span id="sw-status" class="font-medium">Checking...</span>
                    </div>
                    <div class="flex justify-between">
                        <span>PWA Manager:</span>
                        <span id="pwa-manager-status" class="font-medium">Checking...</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Offline Utils:</span>
                        <span id="offline-utils-status" class="font-medium">Checking...</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Network Status:</span>
                        <span id="network-status-status" class="font-medium">Checking...</span>
                    </div>
                </div>
            </div>
            
            <!-- Connection Status -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h2 class="text-lg font-semibold text-green-800 mb-3">Connection Status</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Online Status:</span>
                        <span id="online-status" class="font-medium">Checking...</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Queue Length:</span>
                        <span id="queue-length" class="font-medium">0</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Cache Entries:</span>
                        <span id="cache-entries" class="font-medium">0</span>
                    </div>
                </div>
            </div>
            
            <!-- Test Form -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h2 class="text-lg font-semibold text-yellow-800 mb-3">Offline Form Test</h2>
                <form id="test-form" method="POST" action="/test-endpoint">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Test Input:</label>
                            <input type="text" name="test_input" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter test data">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Test Message:</label>
                            <textarea name="test_message" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Enter test message"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                            Submit Test Form
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- PWA Actions -->
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <h2 class="text-lg font-semibold text-purple-800 mb-3">PWA Actions</h2>
                <div class="space-y-2">
                    <button onclick="testOfflineMode()" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition text-sm">
                        Simulate Offline Mode
                    </button>
                    <button onclick="testCacheData()" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition text-sm">
                        Test Data Caching
                    </button>
                    <button onclick="showQueueDetails()" class="w-full bg-teal-600 text-white py-2 px-4 rounded-md hover:bg-teal-700 transition text-sm">
                        Show Queue Details
                    </button>
                    <button onclick="runIntegrationTest()" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition text-sm">
                        Run Integration Test
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Test Results -->
        <div id="test-results" class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4 hidden">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Test Results</h2>
            <div id="test-results-content" class="text-sm text-gray-600">
                <!-- Results will be populated here -->
            </div>
        </div>
    </div>
</div>

<script>
// PWA Integration Test Functions
document.addEventListener('DOMContentLoaded', function() {
    // Update status indicators
    updateStatusIndicators();
    
    // Set up periodic updates
    setInterval(updateStatusIndicators, 5000);
    
    // Listen for PWA events
    window.addEventListener('pwa-connection-change', function(event) {
        updateStatusIndicators();
    });
});

function updateStatusIndicators() {
    // Service Worker Status
    const swStatus = document.getElementById('sw-status');
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then(registration => {
            swStatus.textContent = registration ? 'Active' : 'Not Registered';
            swStatus.className = registration ? 'font-medium text-green-600' : 'font-medium text-red-600';
        });
    } else {
        swStatus.textContent = 'Not Supported';
        swStatus.className = 'font-medium text-red-600';
    }
    
    // PWA Manager Status
    const pwaManagerStatus = document.getElementById('pwa-manager-status');
    if (typeof window.pwaManager !== 'undefined') {
        pwaManagerStatus.textContent = 'Active';
        pwaManagerStatus.className = 'font-medium text-green-600';
    } else {
        pwaManagerStatus.textContent = 'Not Found';
        pwaManagerStatus.className = 'font-medium text-red-600';
    }
    
    // Offline Utils Status
    const offlineUtilsStatus = document.getElementById('offline-utils-status');
    if (typeof window.offlineUtils !== 'undefined') {
        offlineUtilsStatus.textContent = 'Active';
        offlineUtilsStatus.className = 'font-medium text-green-600';
    } else {
        offlineUtilsStatus.textContent = 'Not Found';
        offlineUtilsStatus.className = 'font-medium text-red-600';
    }
    
    // Network Status
    const networkStatusStatus = document.getElementById('network-status-status');
    if (typeof window.networkStatus !== 'undefined') {
        networkStatusStatus.textContent = 'Active';
        networkStatusStatus.className = 'font-medium text-green-600';
    } else {
        networkStatusStatus.textContent = 'Not Found';
        networkStatusStatus.className = 'font-medium text-red-600';
    }
    
    // Online Status
    const onlineStatus = document.getElementById('online-status');
    onlineStatus.textContent = navigator.onLine ? 'Online' : 'Offline';
    onlineStatus.className = navigator.onLine ? 'font-medium text-green-600' : 'font-medium text-red-600';
    
    // Queue Length
    const queueLength = document.getElementById('queue-length');
    if (window.offlineUtils) {
        queueLength.textContent = window.offlineUtils.getOfflineQueueLength();
    }
    
    // Cache Entries
    const cacheEntries = document.getElementById('cache-entries');
    if (window.offlineDataManager) {
        const stats = window.offlineDataManager.getCacheStats();
        cacheEntries.textContent = stats.totalEntries;
    }
}

function testOfflineMode() {
    // Simulate offline mode by dispatching offline event
    window.dispatchEvent(new Event('offline'));
    
    showTestResult('Offline mode simulated. Check status indicators and try submitting the form.');
}

function testCacheData() {
    if (window.offlineDataManager) {
        // Cache some test data
        window.offlineDataManager.cacheData('test-data', {
            message: 'This is test cached data',
            timestamp: new Date().toISOString()
        }, 60000); // 1 minute TTL
        
        showTestResult('Test data cached successfully. Check cache entries count.');
    } else {
        showTestResult('Offline data manager not available.');
    }
}

function showQueueDetails() {
    if (window.offlineUtils) {
        window.offlineUtils.showQueueDetails();
    } else {
        showTestResult('Offline utils not available.');
    }
}

function runIntegrationTest() {
    if (window.pwaIntegrationTest) {
        window.pwaIntegrationTest.runTests().then(results => {
            showTestResult(`Integration test completed. Passed: ${results.passed}/${results.total}`);
        });
    } else {
        showTestResult('Integration test not available. Make sure PWA_TESTING is enabled.');
    }
}

function showTestResult(message) {
    const resultsDiv = document.getElementById('test-results');
    const contentDiv = document.getElementById('test-results-content');
    
    const timestamp = new Date().toLocaleTimeString();
    contentDiv.innerHTML = `<div class="mb-2"><strong>${timestamp}:</strong> ${message}</div>` + contentDiv.innerHTML;
    
    resultsDiv.classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();

// Include the base layout
include __DIR__ . '/views/layouts/base.php';
?>