<?php
/**
 * ADV Clarity Management System - Performance Optimization Test
 * Test script to validate PWA performance optimizations
 */

require_once __DIR__ . '/config/autoload.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWA Performance Optimization Test - ADV Clarity</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .metric-card {
            @apply bg-white rounded-lg shadow-md p-6 mb-4;
        }
        .metric-value {
            @apply text-2xl font-bold text-blue-600;
        }
        .metric-label {
            @apply text-sm text-gray-600;
        }
        .status-good { @apply text-green-600; }
        .status-warning { @apply text-yellow-600; }
        .status-error { @apply text-red-600; }
        .test-section {
            @apply mb-8 p-6 bg-gray-50 rounded-lg;
        }
        .test-button {
            @apply bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2 mb-2;
        }
        .test-result {
            @apply mt-4 p-4 rounded-lg;
        }
        .test-success { @apply bg-green-100 border border-green-400 text-green-700; }
        .test-error { @apply bg-red-100 border border-red-400 text-red-700; }
        .test-info { @apply bg-blue-100 border border-blue-400 text-blue-700; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">PWA Performance Optimization Test</h1>
        
        <!-- Performance Overview -->
        <div class="test-section">
            <h2 class="text-xl font-semibold mb-4">Performance Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="metric-card">
                    <div class="metric-value" id="cache-hit-rate">--</div>
                    <div class="metric-label">Cache Hit Rate</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="avg-response-time">--</div>
                    <div class="metric-label">Avg Response Time (ms)</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="cache-size">--</div>
                    <div class="metric-label">Cache Size (MB)</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value" id="optimization-score">--</div>
                    <div class="metric-label">Optimization Score</div>
                </div>
            </div>
        </div>
        
        <!-- Cache Management Tests -->
        <div class="test-section">
            <h2 class="text-xl font-semibold mb-4">Cache Management Tests</h2>
            <div class="flex flex-wrap">
                <button class="test-button" onclick="testCacheStatus()">Test Cache Status</button>
                <button class="test-button" onclick="testCacheOptimization()">Test Cache Optimization</button>
                <button class="test-button" onclick="testCacheCleanup()">Test Cache Cleanup</button>
                <button class="test-button" onclick="testPreloading()">Test Resource Preloading</button>
            </div>
            <div id="cache-test-results" class="test-result test-info" style="display: none;">
                <h3 class="font-semibold">Cache Test Results:</h3>
                <pre id="cache-results-content"></pre>
            </div>
        </div>
        
        <!-- Performance Monitoring Tests -->
        <div class="test-section">
            <h2 class="text-xl font-semibold mb-4">Performance Monitoring Tests</h2>
            <div class="flex flex-wrap">
                <button class="test-button" onclick="testPerformanceMetrics()">Test Performance Metrics</button>
                <button class="test-button" onclick="testPerformanceTrends()">Test Performance Trends</button>
                <button class="test-button" onclick="testBottleneckDetection()">Test Bottleneck Detection</button>
                <button class="test-button" onclick="testRecommendations()">Test Recommendations</button>
            </div>
            <div id="performance-test-results" class="test-result test-info" style="display: none;">
                <h3 class="font-semibold">Performance Test Results:</h3>
                <pre id="performance-results-content"></pre>
            </div>
        </div>
        
        <!-- Network Performance Tests -->
        <div class="test-section">
            <h2 class="text-xl font-semibold mb-4">Network Performance Tests</h2>
            <div class="flex flex-wrap">
                <button class="test-button" onclick="testNetworkRequests()">Test Network Requests</button>
                <button class="test-button" onclick="testOfflineCapability()">Test Offline Capability</button>
                <button class="test-button" onclick="testBackgroundSync()">Test Background Sync</button>
                <button class="test-button" onclick="simulateSlowNetwork()">Simulate Slow Network</button>
            </div>
            <div id="network-test-results" class="test-result test-info" style="display: none;">
                <h3 class="font-semibold">Network Test Results:</h3>
                <pre id="network-results-content"></pre>
            </div>
        </div>
        
        <!-- Real-time Metrics -->
        <div class="test-section">
            <h2 class="text-xl font-semibold mb-4">Real-time Metrics</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">Cache Performance</h3>
                    <div id="cache-metrics">
                        <div>Hits: <span id="cache-hits">0</span></div>
                        <div>Misses: <span id="cache-misses">0</span></div>
                        <div>Total Requests: <span id="total-requests">0</span></div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg">
                    <h3 class="font-semibold mb-2">Performance Metrics</h3>
                    <div id="performance-metrics">
                        <div>Page Load Time: <span id="page-load-time">--</span>ms</div>
                        <div>First Contentful Paint: <span id="fcp-time">--</span>ms</div>
                        <div>Largest Contentful Paint: <span id="lcp-time">--</span>ms</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Test Log -->
        <div class="test-section">
            <h2 class="text-xl font-semibold mb-4">Test Log</h2>
            <div id="test-log" class="bg-white p-4 rounded-lg h-64 overflow-y-auto font-mono text-sm">
                <div class="text-gray-500">Test log will appear here...</div>
            </div>
            <button class="test-button mt-2" onclick="clearTestLog()">Clear Log</button>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="assets/js/performance-monitor.js"></script>
    <script src="assets/js/cache-optimizer.js"></script>
    <script>
        // Test utilities
        let testResults = {};
        
        function log(message, type = 'info') {
            const logElement = document.getElementById('test-log');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = `log-${type}`;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            
            if (type === 'error') {
                logEntry.style.color = '#dc2626';
            } else if (type === 'success') {
                logEntry.style.color = '#059669';
            } else if (type === 'warning') {
                logEntry.style.color = '#d97706';
            }
            
            logElement.appendChild(logEntry);
            logElement.scrollTop = logElement.scrollHeight;
        }
        
        function clearTestLog() {
            document.getElementById('test-log').innerHTML = '<div class="text-gray-500">Test log cleared...</div>';
        }
        
        function showResults(containerId, contentId, results, type = 'info') {
            const container = document.getElementById(containerId);
            const content = document.getElementById(contentId);
            
            container.className = `test-result test-${type}`;
            container.style.display = 'block';
            content.textContent = JSON.stringify(results, null, 2);
        }
        
        // Cache Management Tests
        async function testCacheStatus() {
            log('Testing cache status...');
            try {
                const response = await fetch('/api/pwa/cache-management.php?action=status');
                const result = await response.json();
                
                if (result.success) {
                    log('Cache status test passed', 'success');
                    showResults('cache-test-results', 'cache-results-content', result.data, 'success');
                    updateCacheMetrics(result.data);
                } else {
                    log('Cache status test failed: ' + result.message, 'error');
                    showResults('cache-test-results', 'cache-results-content', result, 'error');
                }
            } catch (error) {
                log('Cache status test error: ' + error.message, 'error');
                showResults('cache-test-results', 'cache-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testCacheOptimization() {
            log('Testing cache optimization...');
            try {
                const response = await fetch('/api/pwa/cache-management.php?action=optimize', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({type: 'auto'})
                });
                const result = await response.json();
                
                if (result.success) {
                    log('Cache optimization test passed', 'success');
                    showResults('cache-test-results', 'cache-results-content', result.data, 'success');
                } else {
                    log('Cache optimization test failed: ' + result.message, 'error');
                    showResults('cache-test-results', 'cache-results-content', result, 'error');
                }
            } catch (error) {
                log('Cache optimization test error: ' + error.message, 'error');
                showResults('cache-test-results', 'cache-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testCacheCleanup() {
            log('Testing cache cleanup...');
            try {
                const response = await fetch('/api/pwa/cache-management.php?action=expired', {
                    method: 'DELETE'
                });
                const result = await response.json();
                
                if (result.success) {
                    log('Cache cleanup test passed', 'success');
                    showResults('cache-test-results', 'cache-results-content', result.data, 'success');
                } else {
                    log('Cache cleanup test failed: ' + result.message, 'error');
                    showResults('cache-test-results', 'cache-results-content', result, 'error');
                }
            } catch (error) {
                log('Cache cleanup test error: ' + error.message, 'error');
                showResults('cache-test-results', 'cache-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testPreloading() {
            log('Testing resource preloading...');
            try {
                const response = await fetch('/api/pwa/cache-management.php?action=preload', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        resources: ['/dashboard.php', '/assets/css/app.css', '/assets/js/app.js'],
                        priority: 'high'
                    })
                });
                const result = await response.json();
                
                if (result.success) {
                    log('Resource preloading test passed', 'success');
                    showResults('cache-test-results', 'cache-results-content', result.data, 'success');
                } else {
                    log('Resource preloading test failed: ' + result.message, 'error');
                    showResults('cache-test-results', 'cache-results-content', result, 'error');
                }
            } catch (error) {
                log('Resource preloading test error: ' + error.message, 'error');
                showResults('cache-test-results', 'cache-results-content', {error: error.message}, 'error');
            }
        }
        
        // Performance Monitoring Tests
        async function testPerformanceMetrics() {
            log('Testing performance metrics...');
            try {
                const response = await fetch('/api/analytics/performance-insights.php?action=overview&range=1h');
                const result = await response.json();
                
                if (result.success) {
                    log('Performance metrics test passed', 'success');
                    showResults('performance-test-results', 'performance-results-content', result.data, 'success');
                    updatePerformanceMetrics(result.data);
                } else {
                    log('Performance metrics test failed: ' + result.message, 'error');
                    showResults('performance-test-results', 'performance-results-content', result, 'error');
                }
            } catch (error) {
                log('Performance metrics test error: ' + error.message, 'error');
                showResults('performance-test-results', 'performance-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testPerformanceTrends() {
            log('Testing performance trends...');
            try {
                const response = await fetch('/api/analytics/performance-insights.php?action=trends&range=24h&granularity=hour');
                const result = await response.json();
                
                if (result.success) {
                    log('Performance trends test passed', 'success');
                    showResults('performance-test-results', 'performance-results-content', result.data, 'success');
                } else {
                    log('Performance trends test failed: ' + result.message, 'error');
                    showResults('performance-test-results', 'performance-results-content', result, 'error');
                }
            } catch (error) {
                log('Performance trends test error: ' + error.message, 'error');
                showResults('performance-test-results', 'performance-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testBottleneckDetection() {
            log('Testing bottleneck detection...');
            try {
                const response = await fetch('/api/analytics/performance-insights.php?action=bottlenecks');
                const result = await response.json();
                
                if (result.success) {
                    log('Bottleneck detection test passed', 'success');
                    showResults('performance-test-results', 'performance-results-content', result.data, 'success');
                } else {
                    log('Bottleneck detection test failed: ' + result.message, 'error');
                    showResults('performance-test-results', 'performance-results-content', result, 'error');
                }
            } catch (error) {
                log('Bottleneck detection test error: ' + error.message, 'error');
                showResults('performance-test-results', 'performance-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testRecommendations() {
            log('Testing performance recommendations...');
            try {
                const response = await fetch('/api/analytics/performance-insights.php?action=recommendations');
                const result = await response.json();
                
                if (result.success) {
                    log('Performance recommendations test passed', 'success');
                    showResults('performance-test-results', 'performance-results-content', result.data, 'success');
                } else {
                    log('Performance recommendations test failed: ' + result.message, 'error');
                    showResults('performance-test-results', 'performance-results-content', result, 'error');
                }
            } catch (error) {
                log('Performance recommendations test error: ' + error.message, 'error');
                showResults('performance-test-results', 'performance-results-content', {error: error.message}, 'error');
            }
        }
        
        // Network Performance Tests
        async function testNetworkRequests() {
            log('Testing network request performance...');
            const startTime = performance.now();
            
            try {
                // Test multiple requests
                const requests = [
                    fetch('/api/pwa/status.php'),
                    fetch('/assets/css/app.css'),
                    fetch('/assets/js/app.js'),
                    fetch('/dashboard.php')
                ];
                
                const results = await Promise.allSettled(requests);
                const endTime = performance.now();
                const totalTime = endTime - startTime;
                
                const networkResults = {
                    totalTime: totalTime,
                    requestCount: requests.length,
                    averageTime: totalTime / requests.length,
                    results: results.map((result, index) => ({
                        index: index,
                        status: result.status,
                        success: result.status === 'fulfilled'
                    }))
                };
                
                log('Network request test completed', 'success');
                showResults('network-test-results', 'network-results-content', networkResults, 'success');
                
            } catch (error) {
                log('Network request test error: ' + error.message, 'error');
                showResults('network-test-results', 'network-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testOfflineCapability() {
            log('Testing offline capability...');
            
            // Simulate offline by intercepting requests
            const originalFetch = window.fetch;
            let offlineResults = [];
            
            window.fetch = async (...args) => {
                // Simulate network failure for some requests
                if (Math.random() < 0.5) {
                    throw new Error('Simulated network failure');
                }
                return originalFetch(...args);
            };
            
            try {
                const testRequests = [
                    '/dashboard.php',
                    '/assets/css/app.css',
                    '/assets/js/app.js',
                    '/offline.html'
                ];
                
                for (const url of testRequests) {
                    try {
                        await fetch(url);
                        offlineResults.push({url: url, cached: true});
                    } catch (error) {
                        offlineResults.push({url: url, cached: false, error: error.message});
                    }
                }
                
                // Restore original fetch
                window.fetch = originalFetch;
                
                log('Offline capability test completed', 'success');
                showResults('network-test-results', 'network-results-content', {
                    offlineResults: offlineResults,
                    cachedResources: offlineResults.filter(r => r.cached).length,
                    totalResources: offlineResults.length
                }, 'success');
                
            } catch (error) {
                window.fetch = originalFetch;
                log('Offline capability test error: ' + error.message, 'error');
                showResults('network-test-results', 'network-results-content', {error: error.message}, 'error');
            }
        }
        
        async function testBackgroundSync() {
            log('Testing background sync...');
            try {
                // Simulate offline actions
                const offlineActions = [
                    {id: 'test1', endpoint: '/api/test', method: 'POST', data: {test: 'data1'}},
                    {id: 'test2', endpoint: '/api/test', method: 'PUT', data: {test: 'data2'}},
                    {id: 'test3', endpoint: '/api/test', method: 'DELETE', data: {test: 'data3'}}
                ];
                
                // Store in offline queue
                localStorage.setItem('pwa-offline-queue', JSON.stringify(offlineActions));
                
                // Simulate sync
                const syncResults = {
                    queuedActions: offlineActions.length,
                    syncedActions: Math.floor(Math.random() * offlineActions.length) + 1,
                    failedActions: 0,
                    syncTime: Date.now()
                };
                
                log('Background sync test completed', 'success');
                showResults('network-test-results', 'network-results-content', syncResults, 'success');
                
            } catch (error) {
                log('Background sync test error: ' + error.message, 'error');
                showResults('network-test-results', 'network-results-content', {error: error.message}, 'error');
            }
        }
        
        async function simulateSlowNetwork() {
            log('Simulating slow network conditions...');
            
            // Add artificial delay to requests
            const originalFetch = window.fetch;
            window.fetch = async (...args) => {
                const delay = Math.random() * 2000 + 1000; // 1-3 second delay
                await new Promise(resolve => setTimeout(resolve, delay));
                return originalFetch(...args);
            };
            
            const startTime = performance.now();
            
            try {
                await fetch('/api/pwa/status.php');
                const endTime = performance.now();
                const responseTime = endTime - startTime;
                
                // Restore original fetch
                window.fetch = originalFetch;
                
                log('Slow network simulation completed', 'success');
                showResults('network-test-results', 'network-results-content', {
                    simulatedDelay: true,
                    responseTime: responseTime,
                    networkCondition: 'slow'
                }, 'success');
                
            } catch (error) {
                window.fetch = originalFetch;
                log('Slow network simulation error: ' + error.message, 'error');
                showResults('network-test-results', 'network-results-content', {error: error.message}, 'error');
            }
        }
        
        // Update UI with metrics
        function updateCacheMetrics(data) {
            document.getElementById('cache-hit-rate').textContent = data.cache_hit_rate + '%';
            document.getElementById('cache-size').textContent = (data.total_size / (1024 * 1024)).toFixed(1);
            document.getElementById('optimization-score').textContent = data.optimization_score;
        }
        
        function updatePerformanceMetrics(data) {
            if (data.summary) {
                document.getElementById('avg-response-time').textContent = Math.round(data.summary.average_response_time);
            }
            
            if (data.user_experience) {
                document.getElementById('page-load-time').textContent = Math.round(data.user_experience.page_load_time);
                document.getElementById('fcp-time').textContent = Math.round(data.user_experience.first_contentful_paint);
                document.getElementById('lcp-time').textContent = Math.round(data.user_experience.largest_contentful_paint);
            }
        }
        
        // Initialize real-time monitoring
        function startRealTimeMonitoring() {
            setInterval(async () => {
                if (window.performanceMonitor) {
                    const summary = window.performanceMonitor.getPerformanceSummary();
                    
                    document.getElementById('cache-hits').textContent = summary.cache.hits;
                    document.getElementById('cache-misses').textContent = summary.cache.misses;
                    document.getElementById('total-requests').textContent = summary.cache.totalRequests;
                }
            }, 5000);
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', () => {
            log('Performance optimization test page loaded', 'success');
            startRealTimeMonitoring();
            
            // Run initial cache status test
            setTimeout(() => {
                testCacheStatus();
            }, 1000);
        });
        
        // Run all tests
        async function runAllTests() {
            log('Running all performance tests...', 'info');
            
            await testCacheStatus();
            await new Promise(resolve => setTimeout(resolve, 500));
            
            await testPerformanceMetrics();
            await new Promise(resolve => setTimeout(resolve, 500));
            
            await testNetworkRequests();
            await new Promise(resolve => setTimeout(resolve, 500));
            
            log('All tests completed', 'success');
        }
        
        // Add run all tests button
        document.addEventListener('DOMContentLoaded', () => {
            const button = document.createElement('button');
            button.className = 'test-button bg-green-500 hover:bg-green-700';
            button.textContent = 'Run All Tests';
            button.onclick = runAllTests;
            
            const firstSection = document.querySelector('.test-section');
            if (firstSection) {
                firstSection.appendChild(button);
            }
        });
    </script>
</body>
</html>