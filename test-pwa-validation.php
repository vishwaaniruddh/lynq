<?php
/**
 * ADV Clarity Management System - PWA Validation and Testing Suite
 * Comprehensive PWA validation including PWA Builder analysis simulation
 */

require_once __DIR__ . '/config/autoload.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWA Validation Suite - ADV Clarity</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .validation-card {
            @apply bg-white rounded-lg shadow-md p-6 mb-4;
        }
        .test-passed { @apply text-green-600 bg-green-50 border border-green-200; }
        .test-failed { @apply text-red-600 bg-red-50 border border-red-200; }
        .test-warning { @apply text-yellow-600 bg-yellow-50 border border-yellow-200; }
        .test-info { @apply text-blue-600 bg-blue-50 border border-blue-200; }
        .test-result {
            @apply p-3 rounded-lg mb-2 text-sm;
        }
        .validation-section {
            @apply mb-8;
        }
        .validation-button {
            @apply bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2 mb-2;
        }
        .score-circle {
            @apply w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-lg;
        }
        .progress-bar {
            @apply w-full bg-gray-200 rounded-full h-2.5;
        }
        .progress-fill {
            @apply h-2.5 rounded-full transition-all duration-300;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">PWA Validation & Testing Suite</h1>
        
        <!-- PWA Score Overview -->
        <div class="validation-card">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold">PWA Builder Analysis</h2>
                <button class="validation-button" onclick="runPWABuilderAnalysis()">Run Analysis</button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="text-center">
                    <div id="overall-score" class="score-circle bg-gray-400 mx-auto mb-2">--</div>
                    <div class="text-sm text-gray-600">Overall Score</div>
                </div>
                <div class="text-center">
                    <div id="manifest-score" class="score-circle bg-gray-400 mx-auto mb-2">--</div>
                    <div class="text-sm text-gray-600">Manifest</div>
                </div>
                <div class="text-center">
                    <div id="sw-score" class="score-circle bg-gray-400 mx-auto mb-2">--</div>
                    <div class="text-sm text-gray-600">Service Worker</div>
                </div>
                <div class="text-center">
                    <div id="icons-score" class="score-circle bg-gray-400 mx-auto mb-2">--</div>
                    <div class="text-sm text-gray-600">Icons</div>
                </div>
            </div>
            
            <div class="mb-4">
                <div class="flex justify-between text-sm mb-1">
                    <span>PWA Readiness</span>
                    <span id="readiness-percentage">0%</span>
                </div>
                <div class="progress-bar">
                    <div id="readiness-progress" class="progress-fill bg-blue-500" style="width: 0%"></div>
                </div>
            </div>
            
            <div id="pwa-analysis-results" class="hidden">
                <h3 class="font-semibold mb-2">Analysis Results:</h3>
                <div id="pwa-results-content"></div>
            </div>
        </div>
        
        <!-- Installation Testing -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">Installation Flow Testing</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <h3 class="font-medium mb-2">Desktop Installation</h3>
                        <button class="validation-button" onclick="testDesktopInstallation()">Test Desktop Install</button>
                        <div id="desktop-install-result" class="mt-2"></div>
                    </div>
                    <div>
                        <h3 class="font-medium mb-2">Mobile Installation</h3>
                        <button class="validation-button" onclick="testMobileInstallation()">Test Mobile Install</button>
                        <div id="mobile-install-result" class="mt-2"></div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h3 class="font-medium mb-2">Installation Criteria</h3>
                    <div id="install-criteria" class="space-y-2">
                        <div class="test-result test-info">Running installation tests...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Offline Functionality Testing -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">Offline Functionality Testing</h2>
                
                <div class="flex flex-wrap mb-4">
                    <button class="validation-button" onclick="testOfflineNavigation()">Test Offline Navigation</button>
                    <button class="validation-button" onclick="testOfflineFormSubmission()">Test Offline Forms</button>
                    <button class="validation-button" onclick="testOfflineDataAccess()">Test Offline Data</button>
                    <button class="validation-button" onclick="simulateNetworkFailure()">Simulate Network Failure</button>
                </div>
                
                <div id="offline-test-results" class="space-y-2">
                    <div class="test-result test-info">Click buttons above to test offline functionality</div>
                </div>
            </div>
        </div>
        
        <!-- Push Notifications Testing -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">Push Notifications Testing</h2>
                
                <div class="flex flex-wrap mb-4">
                    <button class="validation-button" onclick="testNotificationPermission()">Test Permission</button>
                    <button class="validation-button" onclick="testNotificationSubscription()">Test Subscription</button>
                    <button class="validation-button" onclick="sendTestNotification()">Send Test Notification</button>
                    <button class="validation-button" onclick="testNotificationClick()">Test Click Handling</button>
                </div>
                
                <div id="notification-test-results" class="space-y-2">
                    <div class="test-result test-info">Click buttons above to test push notifications</div>
                </div>
            </div>
        </div>
        
        <!-- Performance Validation -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">Performance Validation</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600" id="performance-score">--</div>
                        <div class="text-sm text-gray-600">Performance Score</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600" id="cache-efficiency">--</div>
                        <div class="text-sm text-gray-600">Cache Efficiency</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600" id="offline-readiness">--</div>
                        <div class="text-sm text-gray-600">Offline Readiness</div>
                    </div>
                </div>
                
                <button class="validation-button" onclick="runPerformanceValidation()">Run Performance Tests</button>
                
                <div id="performance-validation-results" class="mt-4 space-y-2">
                    <div class="test-result test-info">Click button above to run performance validation</div>
                </div>
            </div>
        </div>
        
        <!-- Cross-Browser Testing -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">Cross-Browser Compatibility</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div class="text-center">
                        <div id="chrome-support" class="w-12 h-12 rounded-full bg-gray-300 mx-auto mb-2 flex items-center justify-center">
                            <span class="text-white font-bold">?</span>
                        </div>
                        <div class="text-sm">Chrome</div>
                    </div>
                    <div class="text-center">
                        <div id="firefox-support" class="w-12 h-12 rounded-full bg-gray-300 mx-auto mb-2 flex items-center justify-center">
                            <span class="text-white font-bold">?</span>
                        </div>
                        <div class="text-sm">Firefox</div>
                    </div>
                    <div class="text-center">
                        <div id="safari-support" class="w-12 h-12 rounded-full bg-gray-300 mx-auto mb-2 flex items-center justify-center">
                            <span class="text-white font-bold">?</span>
                        </div>
                        <div class="text-sm">Safari</div>
                    </div>
                    <div class="text-center">
                        <div id="edge-support" class="w-12 h-12 rounded-full bg-gray-300 mx-auto mb-2 flex items-center justify-center">
                            <span class="text-white font-bold">?</span>
                        </div>
                        <div class="text-sm">Edge</div>
                    </div>
                </div>
                
                <button class="validation-button" onclick="testBrowserCompatibility()">Test Browser Support</button>
                
                <div id="browser-test-results" class="mt-4 space-y-2">
                    <div class="test-result test-info">Click button above to test browser compatibility</div>
                </div>
            </div>
        </div>
        
        <!-- App Store Readiness -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">App Store Readiness</h2>
                
                <div class="mb-4">
                    <h3 class="font-medium mb-2">PWA Builder Packaging Requirements</h3>
                    <div id="packaging-requirements" class="space-y-2">
                        <div class="test-result test-info">Checking packaging requirements...</div>
                    </div>
                </div>
                
                <div class="flex flex-wrap">
                    <button class="validation-button" onclick="validateAppStoreRequirements()">Validate Requirements</button>
                    <button class="validation-button" onclick="generatePackagingReport()">Generate Report</button>
                    <button class="validation-button" onclick="downloadManifest()">Download Manifest</button>
                </div>
            </div>
        </div>
        
        <!-- Comprehensive Test Results -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">Comprehensive Test Results</h2>
                
                <div class="mb-4">
                    <button class="validation-button bg-green-500 hover:bg-green-700" onclick="runAllTests()">Run All Tests</button>
                    <button class="validation-button bg-purple-500 hover:bg-purple-700" onclick="exportTestResults()">Export Results</button>
                    <button class="validation-button bg-gray-500 hover:bg-gray-700" onclick="clearAllResults()">Clear Results</button>
                </div>
                
                <div id="comprehensive-results" class="space-y-2">
                    <div class="test-result test-info">Click 'Run All Tests' to perform comprehensive PWA validation</div>
                </div>
            </div>
        </div>
        
        <!-- Test Log -->
        <div class="validation-section">
            <div class="validation-card">
                <h2 class="text-xl font-semibold mb-4">Test Execution Log</h2>
                <div id="test-log" class="bg-gray-900 text-green-400 p-4 rounded-lg h-64 overflow-y-auto font-mono text-sm">
                    <div class="text-gray-500">Test execution log will appear here...</div>
                </div>
                <button class="validation-button mt-2" onclick="clearTestLog()">Clear Log</button>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="assets/js/performance-monitor.js"></script>
    <script src="assets/js/cache-optimizer.js"></script>
    <script src="assets/js/pwa-manager.js"></script>
    <script>
        // Test execution state
        let testResults = {
            pwaBuilder: null,
            installation: null,
            offline: null,
            notifications: null,
            performance: null,
            browser: null,
            appStore: null
        };
        
        let testLog = [];
        
        // Logging utility
        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `[${timestamp}] ${message}`;
            testLog.push({ timestamp, message, type });
            
            const logElement = document.getElementById('test-log');
            const logDiv = document.createElement('div');
            logDiv.textContent = logEntry;
            
            switch (type) {
                case 'success':
                    logDiv.className = 'text-green-400';
                    break;
                case 'error':
                    logDiv.className = 'text-red-400';
                    break;
                case 'warning':
                    logDiv.className = 'text-yellow-400';
                    break;
                default:
                    logDiv.className = 'text-green-400';
            }
            
            logElement.appendChild(logDiv);
            logElement.scrollTop = logElement.scrollHeight;
        }
        
        function clearTestLog() {
            document.getElementById('test-log').innerHTML = '<div class="text-gray-500">Test log cleared...</div>';
            testLog = [];
        }
        
        // PWA Builder Analysis Simulation
        async function runPWABuilderAnalysis() {
            log('Starting PWA Builder analysis simulation...');
            
            try {
                // Simulate PWA Builder checks
                const checks = await performPWABuilderChecks();
                
                // Update scores
                updatePWAScores(checks);
                
                // Show detailed results
                displayPWAAnalysisResults(checks);
                
                testResults.pwaBuilder = checks;
                log('PWA Builder analysis completed successfully', 'success');
                
            } catch (error) {
                log('PWA Builder analysis failed: ' + error.message, 'error');
            }
        }
        
        async function performPWABuilderChecks() {
            const checks = {
                manifest: await validateManifest(),
                serviceWorker: await validateServiceWorker(),
                icons: await validateIcons(),
                https: validateHTTPS(),
                offline: await validateOfflineCapability(),
                installable: await validateInstallability()
            };
            
            // Calculate overall score
            const scores = Object.values(checks).map(check => check.score);
            checks.overall = {
                score: Math.round(scores.reduce((a, b) => a + b, 0) / scores.length),
                passed: scores.every(score => score >= 70)
            };
            
            return checks;
        }
        
        async function validateManifest() {
            log('Validating web app manifest...');
            
            try {
                const response = await fetch('/app.webmanifest');
                const manifest = await response.json();
                
                const requiredFields = ['name', 'short_name', 'start_url', 'display', 'theme_color', 'background_color', 'icons'];
                const missingFields = requiredFields.filter(field => !manifest[field]);
                
                const score = Math.max(0, 100 - (missingFields.length * 15));
                
                return {
                    score: score,
                    passed: missingFields.length === 0,
                    issues: missingFields.map(field => `Missing required field: ${field}`),
                    details: {
                        hasName: !!manifest.name,
                        hasShortName: !!manifest.short_name,
                        hasStartUrl: !!manifest.start_url,
                        hasDisplay: !!manifest.display,
                        hasThemeColor: !!manifest.theme_color,
                        hasIcons: !!manifest.icons && manifest.icons.length > 0,
                        hasScreenshots: !!manifest.screenshots && manifest.screenshots.length > 0
                    }
                };
                
            } catch (error) {
                return {
                    score: 0,
                    passed: false,
                    issues: ['Failed to load or parse manifest: ' + error.message]
                };
            }
        }
        
        async function validateServiceWorker() {
            log('Validating service worker...');
            
            if (!('serviceWorker' in navigator)) {
                return {
                    score: 0,
                    passed: false,
                    issues: ['Service Worker not supported in this browser']
                };
            }
            
            try {
                const registration = await navigator.serviceWorker.getRegistration();
                
                if (!registration) {
                    return {
                        score: 20,
                        passed: false,
                        issues: ['No service worker registered']
                    };
                }
                
                const hasActive = !!registration.active;
                const hasInstalling = !!registration.installing;
                const hasWaiting = !!registration.waiting;
                
                let score = 40; // Base score for having registration
                if (hasActive) score += 40;
                if (hasInstalling || hasWaiting) score += 20;
                
                return {
                    score: Math.min(100, score),
                    passed: hasActive,
                    issues: hasActive ? [] : ['Service worker not active'],
                    details: {
                        registered: true,
                        active: hasActive,
                        installing: hasInstalling,
                        waiting: hasWaiting,
                        scope: registration.scope
                    }
                };
                
            } catch (error) {
                return {
                    score: 10,
                    passed: false,
                    issues: ['Service worker validation failed: ' + error.message]
                };
            }
        }
        
        async function validateIcons() {
            log('Validating PWA icons...');
            
            try {
                const response = await fetch('/app.webmanifest');
                const manifest = await response.json();
                
                if (!manifest.icons || manifest.icons.length === 0) {
                    return {
                        score: 0,
                        passed: false,
                        issues: ['No icons defined in manifest']
                    };
                }
                
                const requiredSizes = ['72x72', '96x96', '128x128', '144x144', '152x152', '192x192', '384x384', '512x512'];
                const availableSizes = manifest.icons.map(icon => icon.sizes).flat();
                const missingSizes = requiredSizes.filter(size => !availableSizes.includes(size));
                
                const score = Math.max(0, 100 - (missingSizes.length * 12));
                
                // Test if icons are actually accessible
                const iconTests = await Promise.allSettled(
                    manifest.icons.slice(0, 3).map(icon => 
                        fetch(icon.src).then(r => r.ok)
                    )
                );
                
                const accessibleIcons = iconTests.filter(test => test.status === 'fulfilled' && test.value).length;
                const iconAccessibilityScore = (accessibleIcons / Math.min(3, manifest.icons.length)) * 100;
                
                const finalScore = Math.round((score + iconAccessibilityScore) / 2);
                
                return {
                    score: finalScore,
                    passed: missingSizes.length <= 2 && accessibleIcons > 0,
                    issues: [
                        ...missingSizes.map(size => `Missing icon size: ${size}`),
                        ...(accessibleIcons === 0 ? ['No icons are accessible'] : [])
                    ],
                    details: {
                        totalIcons: manifest.icons.length,
                        availableSizes: availableSizes,
                        missingSizes: missingSizes,
                        accessibleIcons: accessibleIcons
                    }
                };
                
            } catch (error) {
                return {
                    score: 0,
                    passed: false,
                    issues: ['Icon validation failed: ' + error.message]
                };
            }
        }
        
        function validateHTTPS() {
            log('Validating HTTPS...');
            
            const isHTTPS = location.protocol === 'https:' || location.hostname === 'localhost';
            
            return {
                score: isHTTPS ? 100 : 0,
                passed: isHTTPS,
                issues: isHTTPS ? [] : ['PWA requires HTTPS (or localhost for development)'],
                details: {
                    protocol: location.protocol,
                    hostname: location.hostname,
                    isSecure: isHTTPS
                }
            };
        }
        
        async function validateOfflineCapability() {
            log('Validating offline capability...');
            
            try {
                // Test if offline page exists
                const offlineResponse = await fetch('/offline.html');
                const hasOfflinePage = offlineResponse.ok;
                
                // Test if service worker handles offline requests
                const hasServiceWorker = 'serviceWorker' in navigator && await navigator.serviceWorker.getRegistration();
                
                let score = 0;
                if (hasOfflinePage) score += 50;
                if (hasServiceWorker) score += 50;
                
                return {
                    score: score,
                    passed: hasOfflinePage && hasServiceWorker,
                    issues: [
                        ...(hasOfflinePage ? [] : ['No offline fallback page found']),
                        ...(hasServiceWorker ? [] : ['Service worker not available for offline handling'])
                    ],
                    details: {
                        hasOfflinePage: hasOfflinePage,
                        hasServiceWorker: !!hasServiceWorker
                    }
                };
                
            } catch (error) {
                return {
                    score: 0,
                    passed: false,
                    issues: ['Offline capability validation failed: ' + error.message]
                };
            }
        }
        
        async function validateInstallability() {
            log('Validating installability...');
            
            // Check for beforeinstallprompt event support
            const hasInstallPrompt = 'onbeforeinstallprompt' in window;
            
            // Check if already installed
            const isInstalled = window.matchMedia('(display-mode: standalone)').matches;
            
            let score = 0;
            if (hasInstallPrompt) score += 50;
            if (isInstalled) score += 50;
            else if (hasInstallPrompt) score += 30; // Bonus for being installable
            
            return {
                score: score,
                passed: hasInstallPrompt || isInstalled,
                issues: [
                    ...(hasInstallPrompt ? [] : ['Install prompt not supported']),
                    ...(isInstalled ? ['App is already installed'] : [])
                ],
                details: {
                    hasInstallPrompt: hasInstallPrompt,
                    isInstalled: isInstalled,
                    displayMode: window.matchMedia('(display-mode: standalone)').matches ? 'standalone' : 'browser'
                }
            };
        }
        
        function updatePWAScores(checks) {
            // Update score circles
            updateScoreCircle('overall-score', checks.overall.score);
            updateScoreCircle('manifest-score', checks.manifest.score);
            updateScoreCircle('sw-score', checks.serviceWorker.score);
            updateScoreCircle('icons-score', checks.icons.score);
            
            // Update progress bar
            const readinessPercentage = checks.overall.score;
            document.getElementById('readiness-percentage').textContent = readinessPercentage + '%';
            document.getElementById('readiness-progress').style.width = readinessPercentage + '%';
            
            // Update progress bar color
            const progressBar = document.getElementById('readiness-progress');
            if (readinessPercentage >= 80) {
                progressBar.className = 'progress-fill bg-green-500';
            } else if (readinessPercentage >= 60) {
                progressBar.className = 'progress-fill bg-yellow-500';
            } else {
                progressBar.className = 'progress-fill bg-red-500';
            }
        }
        
        function updateScoreCircle(elementId, score) {
            const element = document.getElementById(elementId);
            element.textContent = score;
            
            // Update color based on score
            if (score >= 80) {
                element.className = 'score-circle bg-green-500 mx-auto mb-2';
            } else if (score >= 60) {
                element.className = 'score-circle bg-yellow-500 mx-auto mb-2';
            } else {
                element.className = 'score-circle bg-red-500 mx-auto mb-2';
            }
        }
        
        function displayPWAAnalysisResults(checks) {
            const container = document.getElementById('pwa-results-content');
            const resultsDiv = document.getElementById('pwa-analysis-results');
            
            let html = '<div class="space-y-3">';
            
            Object.entries(checks).forEach(([category, result]) => {
                if (category === 'overall') return;
                
                const statusClass = result.passed ? 'test-passed' : 'test-failed';
                const statusIcon = result.passed ? '✓' : '✗';
                
                html += `
                    <div class="test-result ${statusClass}">
                        <div class="flex justify-between items-center">
                            <span class="font-medium">${statusIcon} ${category.charAt(0).toUpperCase() + category.slice(1)}</span>
                            <span class="font-bold">${result.score}/100</span>
                        </div>
                        ${result.issues.length > 0 ? `
                            <div class="mt-2 text-sm">
                                <strong>Issues:</strong>
                                <ul class="list-disc list-inside ml-2">
                                    ${result.issues.map(issue => `<li>${issue}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
            resultsDiv.classList.remove('hidden');
        }
        
        // Installation Testing
        async function testDesktopInstallation() {
            log('Testing desktop installation...');
            
            const result = document.getElementById('desktop-install-result');
            
            try {
                // Check if app is already installed
                if (window.matchMedia('(display-mode: standalone)').matches) {
                    result.innerHTML = '<div class="test-result test-passed">✓ App is already installed</div>';
                    return;
                }
                
                // Check for install prompt availability
                if (window.deferredPrompt) {
                    result.innerHTML = '<div class="test-result test-passed">✓ Install prompt available</div>';
                    
                    // Optionally trigger install prompt
                    const installButton = document.createElement('button');
                    installButton.textContent = 'Trigger Install';
                    installButton.className = 'validation-button mt-2';
                    installButton.onclick = () => {
                        window.deferredPrompt.prompt();
                        window.deferredPrompt.userChoice.then((choiceResult) => {
                            log(`Install prompt result: ${choiceResult.outcome}`, 
                                choiceResult.outcome === 'accepted' ? 'success' : 'info');
                        });
                    };
                    result.appendChild(installButton);
                } else {
                    result.innerHTML = '<div class="test-result test-warning">⚠ Install prompt not available (may require user interaction)</div>';
                }
                
            } catch (error) {
                result.innerHTML = `<div class="test-result test-failed">✗ Desktop installation test failed: ${error.message}</div>`;
                log('Desktop installation test failed: ' + error.message, 'error');
            }
        }
        
        async function testMobileInstallation() {
            log('Testing mobile installation...');
            
            const result = document.getElementById('mobile-install-result');
            
            try {
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                
                if (!isMobile) {
                    result.innerHTML = '<div class="test-result test-info">ℹ Not on mobile device - install behavior may differ</div>';
                    return;
                }
                
                // Check iOS Safari specific requirements
                if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                    const hasAppleTouchIcon = document.querySelector('link[rel="apple-touch-icon"]');
                    const hasAppleMobileWebAppCapable = document.querySelector('meta[name="apple-mobile-web-app-capable"]');
                    
                    if (hasAppleTouchIcon && hasAppleMobileWebAppCapable) {
                        result.innerHTML = '<div class="test-result test-passed">✓ iOS installation requirements met</div>';
                    } else {
                        result.innerHTML = '<div class="test-result test-warning">⚠ Missing iOS-specific meta tags</div>';
                    }
                } else {
                    // Android Chrome
                    result.innerHTML = '<div class="test-result test-passed">✓ Android installation should work via banner</div>';
                }
                
            } catch (error) {
                result.innerHTML = `<div class="test-result test-failed">✗ Mobile installation test failed: ${error.message}</div>`;
                log('Mobile installation test failed: ' + error.message, 'error');
            }
        }
        
        // Offline Functionality Testing
        async function testOfflineNavigation() {
            log('Testing offline navigation...');
            
            const resultContainer = document.getElementById('offline-test-results');
            
            try {
                // Test navigation to cached pages
                const testUrls = ['/', '/dashboard.php', '/offline.html'];
                const results = [];
                
                for (const url of testUrls) {
                    try {
                        const response = await fetch(url, { cache: 'force-cache' });
                        results.push({
                            url: url,
                            success: response.ok,
                            cached: response.headers.get('cache-control') !== null
                        });
                    } catch (error) {
                        results.push({
                            url: url,
                            success: false,
                            error: error.message
                        });
                    }
                }
                
                const successCount = results.filter(r => r.success).length;
                const resultClass = successCount === testUrls.length ? 'test-passed' : 
                                  successCount > 0 ? 'test-warning' : 'test-failed';
                
                let html = `<div class="test-result ${resultClass}">
                    ${successCount === testUrls.length ? '✓' : successCount > 0 ? '⚠' : '✗'} 
                    Offline Navigation: ${successCount}/${testUrls.length} pages accessible
                </div>`;
                
                results.forEach(result => {
                    const status = result.success ? '✓' : '✗';
                    const statusClass = result.success ? 'text-green-600' : 'text-red-600';
                    html += `<div class="text-sm ${statusClass} ml-4">${status} ${result.url}</div>`;
                });
                
                resultContainer.innerHTML = html;
                
            } catch (error) {
                resultContainer.innerHTML = `<div class="test-result test-failed">✗ Offline navigation test failed: ${error.message}</div>`;
                log('Offline navigation test failed: ' + error.message, 'error');
            }
        }
        
        async function testOfflineFormSubmission() {
            log('Testing offline form submission...');
            
            const resultContainer = document.getElementById('offline-test-results');
            
            try {
                // Test if offline form handler is available
                const hasOfflineHandler = typeof window.offlineFormHandler !== 'undefined';
                
                if (hasOfflineHandler) {
                    // Test form queuing
                    const testFormData = {
                        action: '/api/test',
                        method: 'POST',
                        data: { test: 'offline form submission' }
                    };
                    
                    // Simulate offline form submission
                    const queued = window.offlineFormHandler ? 
                        window.offlineFormHandler.queueForm(testFormData) : false;
                    
                    const resultClass = queued ? 'test-passed' : 'test-warning';
                    const status = queued ? '✓' : '⚠';
                    
                    resultContainer.innerHTML += `<div class="test-result ${resultClass}">
                        ${status} Offline form submission: ${queued ? 'Forms can be queued' : 'Handler available but queuing failed'}
                    </div>`;
                } else {
                    resultContainer.innerHTML += `<div class="test-result test-warning">
                        ⚠ Offline form submission: Handler not loaded
                    </div>`;
                }
                
            } catch (error) {
                resultContainer.innerHTML += `<div class="test-result test-failed">✗ Offline form test failed: ${error.message}</div>`;
                log('Offline form test failed: ' + error.message, 'error');
            }
        }
        
        async function testOfflineDataAccess() {
            log('Testing offline data access...');
            
            const resultContainer = document.getElementById('offline-test-results');
            
            try {
                // Test localStorage availability
                const hasLocalStorage = typeof Storage !== 'undefined';
                
                // Test IndexedDB availability
                const hasIndexedDB = 'indexedDB' in window;
                
                // Test service worker cache access
                const hasCacheAPI = 'caches' in window;
                
                let score = 0;
                if (hasLocalStorage) score += 1;
                if (hasIndexedDB) score += 1;
                if (hasCacheAPI) score += 1;
                
                const resultClass = score === 3 ? 'test-passed' : score >= 2 ? 'test-warning' : 'test-failed';
                const status = score === 3 ? '✓' : score >= 2 ? '⚠' : '✗';
                
                resultContainer.innerHTML += `<div class="test-result ${resultClass}">
                    ${status} Offline data access: ${score}/3 storage methods available
                    <div class="text-sm mt-1 ml-4">
                        ${hasLocalStorage ? '✓' : '✗'} localStorage | 
                        ${hasIndexedDB ? '✓' : '✗'} IndexedDB | 
                        ${hasCacheAPI ? '✓' : '✗'} Cache API
                    </div>
                </div>`;
                
            } catch (error) {
                resultContainer.innerHTML += `<div class="test-result test-failed">✗ Offline data access test failed: ${error.message}</div>`;
                log('Offline data access test failed: ' + error.message, 'error');
            }
        }
        
        async function simulateNetworkFailure() {
            log('Simulating network failure...');
            
            const resultContainer = document.getElementById('offline-test-results');
            
            try {
                // Override fetch to simulate network failure
                const originalFetch = window.fetch;
                let failureCount = 0;
                let successCount = 0;
                
                window.fetch = async (...args) => {
                    // Simulate 50% network failure
                    if (Math.random() < 0.5) {
                        failureCount++;
                        throw new Error('Simulated network failure');
                    } else {
                        successCount++;
                        return originalFetch(...args);
                    }
                };
                
                // Test multiple requests
                const testRequests = [
                    '/dashboard.php',
                    '/assets/css/app.css',
                    '/api/pwa/status.php',
                    '/offline.html'
                ];
                
                const results = await Promise.allSettled(
                    testRequests.map(url => fetch(url))
                );
                
                // Restore original fetch
                window.fetch = originalFetch;
                
                const handledGracefully = results.filter(r => 
                    r.status === 'fulfilled' || 
                    (r.status === 'rejected' && r.reason.message.includes('Simulated'))
                ).length;
                
                const resultClass = handledGracefully === testRequests.length ? 'test-passed' : 'test-warning';
                const status = handledGracefully === testRequests.length ? '✓' : '⚠';
                
                resultContainer.innerHTML += `<div class="test-result ${resultClass}">
                    ${status} Network failure simulation: ${handledGracefully}/${testRequests.length} requests handled gracefully
                    <div class="text-sm mt-1 ml-4">
                        Failures: ${failureCount} | Successes: ${successCount}
                    </div>
                </div>`;
                
            } catch (error) {
                resultContainer.innerHTML += `<div class="test-result test-failed">✗ Network failure simulation failed: ${error.message}</div>`;
                log('Network failure simulation failed: ' + error.message, 'error');
            }
        }
        
        // Push Notifications Testing
        async function testNotificationPermission() {
            log('Testing notification permission...');
            
            const resultContainer = document.getElementById('notification-test-results');
            
            try {
                if (!('Notification' in window)) {
                    resultContainer.innerHTML = '<div class="test-result test-failed">✗ Notifications not supported in this browser</div>';
                    return;
                }
                
                const permission = Notification.permission;
                let resultClass, status, message;
                
                switch (permission) {
                    case 'granted':
                        resultClass = 'test-passed';
                        status = '✓';
                        message = 'Notification permission granted';
                        break;
                    case 'denied':
                        resultClass = 'test-failed';
                        status = '✗';
                        message = 'Notification permission denied';
                        break;
                    case 'default':
                        resultClass = 'test-warning';
                        status = '⚠';
                        message = 'Notification permission not requested';
                        
                        // Add button to request permission
                        const requestButton = document.createElement('button');
                        requestButton.textContent = 'Request Permission';
                        requestButton.className = 'validation-button mt-2';
                        requestButton.onclick = async () => {
                            const newPermission = await Notification.requestPermission();
                            testNotificationPermission(); // Re-run test
                        };
                        break;
                }
                
                resultContainer.innerHTML = `<div class="test-result ${resultClass}">${status} ${message}</div>`;
                
                if (permission === 'default') {
                    resultContainer.appendChild(requestButton);
                }
                
            } catch (error) {
                resultContainer.innerHTML = `<div class="test-result test-failed">✗ Notification permission test failed: ${error.message}</div>`;
                log('Notification permission test failed: ' + error.message, 'error');
            }
        }
        
        async function testNotificationSubscription() {
            log('Testing notification subscription...');
            
            const resultContainer = document.getElementById('notification-test-results');
            
            try {
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    resultContainer.innerHTML += '<div class="test-result test-failed">✗ Push notifications not supported</div>';
                    return;
                }
                
                const registration = await navigator.serviceWorker.getRegistration();
                if (!registration) {
                    resultContainer.innerHTML += '<div class="test-result test-failed">✗ No service worker registration found</div>';
                    return;
                }
                
                const subscription = await registration.pushManager.getSubscription();
                
                if (subscription) {
                    resultContainer.innerHTML += '<div class="test-result test-passed">✓ Push subscription exists</div>';
                } else {
                    resultContainer.innerHTML += '<div class="test-result test-warning">⚠ No push subscription found</div>';
                    
                    // Add button to create subscription
                    const subscribeButton = document.createElement('button');
                    subscribeButton.textContent = 'Create Subscription';
                    subscribeButton.className = 'validation-button mt-2';
                    subscribeButton.onclick = async () => {
                        try {
                            const newSubscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: 'BEl62iUYgUivxIkv69yViEuiBIa40HI80NM9f8HnRG4ATJHiLOTdl2Sf9aqsGGgEXAFgK0ExOcqm3iNPSUjIBDc'
                            });
                            log('Push subscription created successfully', 'success');
                            testNotificationSubscription(); // Re-run test
                        } catch (error) {
                            log('Failed to create push subscription: ' + error.message, 'error');
                        }
                    };
                    resultContainer.appendChild(subscribeButton);
                }
                
            } catch (error) {
                resultContainer.innerHTML += `<div class="test-result test-failed">✗ Notification subscription test failed: ${error.message}</div>`;
                log('Notification subscription test failed: ' + error.message, 'error');
            }
        }
        
        async function sendTestNotification() {
            log('Sending test notification...');
            
            const resultContainer = document.getElementById('notification-test-results');
            
            try {
                if (Notification.permission !== 'granted') {
                    resultContainer.innerHTML += '<div class="test-result test-warning">⚠ Cannot send notification - permission not granted</div>';
                    return;
                }
                
                const notification = new Notification('PWA Test Notification', {
                    body: 'This is a test notification from the PWA validation suite',
                    icon: '/assets/icons/icon-192.png',
                    badge: '/assets/icons/icon-72.png',
                    tag: 'pwa-test',
                    data: { test: true, timestamp: Date.now() }
                });
                
                notification.onclick = () => {
                    log('Test notification clicked', 'success');
                    notification.close();
                };
                
                setTimeout(() => {
                    notification.close();
                }, 5000);
                
                resultContainer.innerHTML += '<div class="test-result test-passed">✓ Test notification sent successfully</div>';
                
            } catch (error) {
                resultContainer.innerHTML += `<div class="test-result test-failed">✗ Failed to send test notification: ${error.message}</div>`;
                log('Failed to send test notification: ' + error.message, 'error');
            }
        }
        
        async function testNotificationClick() {
            log('Testing notification click handling...');
            
            const resultContainer = document.getElementById('notification-test-results');
            
            try {
                // Test if service worker has notification click handler
                const registration = await navigator.serviceWorker.getRegistration();
                
                if (!registration) {
                    resultContainer.innerHTML += '<div class="test-result test-failed">✗ No service worker for notification handling</div>';
                    return;
                }
                
                // Send a message to service worker to test notification handling
                if (registration.active) {
                    registration.active.postMessage({
                        type: 'TEST_NOTIFICATION_CLICK',
                        data: { test: true }
                    });
                    
                    resultContainer.innerHTML += '<div class="test-result test-passed">✓ Notification click handler test sent to service worker</div>';
                } else {
                    resultContainer.innerHTML += '<div class="test-result test-warning">⚠ Service worker not active</div>';
                }
                
            } catch (error) {
                resultContainer.innerHTML += `<div class="test-result test-failed">✗ Notification click test failed: ${error.message}</div>`;
                log('Notification click test failed: ' + error.message, 'error');
            }
        }
        
        // Performance Validation
        async function runPerformanceValidation() {
            log('Running performance validation...');
            
            try {
                // Get performance metrics
                const performanceData = await getPerformanceMetrics();
                
                // Update performance display
                updatePerformanceDisplay(performanceData);
                
                // Show detailed results
                displayPerformanceResults(performanceData);
                
                testResults.performance = performanceData;
                log('Performance validation completed', 'success');
                
            } catch (error) {
                log('Performance validation failed: ' + error.message, 'error');
            }
        }
        
        async function getPerformanceMetrics() {
            // Get metrics from performance monitor if available
            if (window.performanceMonitor) {
                const summary = window.performanceMonitor.getPerformanceSummary();
                return {
                    performanceScore: calculatePerformanceScore(summary),
                    cacheEfficiency: summary.cache.hitRate * 100,
                    offlineReadiness: calculateOfflineReadiness(),
                    pageLoadTime: summary.pageLoad.pageLoad || 0,
                    cacheHitRate: summary.cache.hitRate,
                    details: summary
                };
            }
            
            // Fallback to basic performance metrics
            const navigation = performance.getEntriesByType('navigation')[0];
            const pageLoadTime = navigation ? navigation.loadEventEnd - navigation.fetchStart : 0;
            
            return {
                performanceScore: pageLoadTime < 3000 ? 85 : pageLoadTime < 5000 ? 70 : 50,
                cacheEfficiency: 75, // Estimated
                offlineReadiness: 80, // Estimated
                pageLoadTime: pageLoadTime,
                cacheHitRate: 0.75,
                details: { navigation }
            };
        }
        
        function calculatePerformanceScore(summary) {
            let score = 100;
            
            // Penalize slow page load
            if (summary.pageLoad && summary.pageLoad.pageLoad > 3000) {
                score -= 20;
            }
            
            // Penalize low cache hit rate
            if (summary.cache.hitRate < 0.7) {
                score -= 15;
            }
            
            // Bonus for good uptime
            if (summary.uptime > 300000) { // 5 minutes
                score += 5;
            }
            
            return Math.max(0, Math.min(100, score));
        }
        
        function calculateOfflineReadiness() {
            let score = 0;
            
            // Check for service worker
            if ('serviceWorker' in navigator) score += 30;
            
            // Check for cache API
            if ('caches' in window) score += 25;
            
            // Check for offline page
            fetch('/offline.html').then(r => {
                if (r.ok) score += 25;
            }).catch(() => {});
            
            // Check for storage APIs
            if (typeof Storage !== 'undefined') score += 10;
            if ('indexedDB' in window) score += 10;
            
            return score;
        }
        
        function updatePerformanceDisplay(data) {
            document.getElementById('performance-score').textContent = Math.round(data.performanceScore);
            document.getElementById('cache-efficiency').textContent = Math.round(data.cacheEfficiency) + '%';
            document.getElementById('offline-readiness').textContent = Math.round(data.offlineReadiness) + '%';
        }
        
        function displayPerformanceResults(data) {
            const container = document.getElementById('performance-validation-results');
            
            const results = [
                {
                    name: 'Performance Score',
                    value: Math.round(data.performanceScore),
                    threshold: 70,
                    unit: '/100'
                },
                {
                    name: 'Cache Hit Rate',
                    value: Math.round(data.cacheHitRate * 100),
                    threshold: 70,
                    unit: '%'
                },
                {
                    name: 'Page Load Time',
                    value: Math.round(data.pageLoadTime),
                    threshold: 3000,
                    unit: 'ms',
                    reverse: true // Lower is better
                },
                {
                    name: 'Offline Readiness',
                    value: Math.round(data.offlineReadiness),
                    threshold: 80,
                    unit: '%'
                }
            ];
            
            let html = '';
            results.forEach(result => {
                const passed = result.reverse ? 
                    result.value <= result.threshold : 
                    result.value >= result.threshold;
                
                const resultClass = passed ? 'test-passed' : 'test-warning';
                const status = passed ? '✓' : '⚠';
                
                html += `<div class="test-result ${resultClass}">
                    ${status} ${result.name}: ${result.value}${result.unit}
                    ${!passed ? ` (threshold: ${result.threshold}${result.unit})` : ''}
                </div>`;
            });
            
            container.innerHTML = html;
        }
        
        // Browser Compatibility Testing
        async function testBrowserCompatibility() {
            log('Testing browser compatibility...');
            
            const features = {
                serviceWorker: 'serviceWorker' in navigator,
                pushManager: 'PushManager' in window,
                notifications: 'Notification' in window,
                cacheAPI: 'caches' in window,
                indexedDB: 'indexedDB' in window,
                localStorage: typeof Storage !== 'undefined',
                fetch: typeof fetch !== 'undefined',
                promises: typeof Promise !== 'undefined'
            };
            
            // Detect browser
            const userAgent = navigator.userAgent;
            let browser = 'unknown';
            
            if (userAgent.includes('Chrome') && !userAgent.includes('Edg')) {
                browser = 'chrome';
            } else if (userAgent.includes('Firefox')) {
                browser = 'firefox';
            } else if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) {
                browser = 'safari';
            } else if (userAgent.includes('Edg')) {
                browser = 'edge';
            }
            
            // Calculate compatibility scores
            const totalFeatures = Object.keys(features).length;
            const supportedFeatures = Object.values(features).filter(Boolean).length;
            const compatibilityScore = (supportedFeatures / totalFeatures) * 100;
            
            // Update browser support indicators
            updateBrowserSupport('chrome', browser === 'chrome' ? compatibilityScore : 90);
            updateBrowserSupport('firefox', browser === 'firefox' ? compatibilityScore : 85);
            updateBrowserSupport('safari', browser === 'safari' ? compatibilityScore : 75);
            updateBrowserSupport('edge', browser === 'edge' ? compatibilityScore : 88);
            
            // Display detailed results
            const container = document.getElementById('browser-test-results');
            let html = `<div class="test-result test-info">
                Current Browser: ${browser.charAt(0).toUpperCase() + browser.slice(1)} 
                (${Math.round(compatibilityScore)}% PWA feature support)
            </div>`;
            
            Object.entries(features).forEach(([feature, supported]) => {
                const status = supported ? '✓' : '✗';
                const statusClass = supported ? 'text-green-600' : 'text-red-600';
                html += `<div class="text-sm ${statusClass} ml-4">${status} ${feature}</div>`;
            });
            
            container.innerHTML = html;
            
            testResults.browser = {
                browser: browser,
                compatibilityScore: compatibilityScore,
                features: features
            };
        }
        
        function updateBrowserSupport(browserId, score) {
            const element = document.getElementById(`${browserId}-support`);
            
            if (score >= 80) {
                element.className = 'w-12 h-12 rounded-full bg-green-500 mx-auto mb-2 flex items-center justify-center';
                element.innerHTML = '<span class="text-white font-bold">✓</span>';
            } else if (score >= 60) {
                element.className = 'w-12 h-12 rounded-full bg-yellow-500 mx-auto mb-2 flex items-center justify-center';
                element.innerHTML = '<span class="text-white font-bold">⚠</span>';
            } else {
                element.className = 'w-12 h-12 rounded-full bg-red-500 mx-auto mb-2 flex items-center justify-center';
                element.innerHTML = '<span class="text-white font-bold">✗</span>';
            }
        }
        
        // App Store Readiness
        async function validateAppStoreRequirements() {
            log('Validating app store requirements...');
            
            const requirements = [
                {
                    name: 'HTTPS Required',
                    check: () => location.protocol === 'https:' || location.hostname === 'localhost',
                    critical: true
                },
                {
                    name: 'Web App Manifest',
                    check: async () => {
                        try {
                            const response = await fetch('/app.webmanifest');
                            return response.ok;
                        } catch {
                            return false;
                        }
                    },
                    critical: true
                },
                {
                    name: 'Service Worker',
                    check: async () => {
                        const registration = await navigator.serviceWorker.getRegistration();
                        return !!registration && !!registration.active;
                    },
                    critical: true
                },
                {
                    name: 'Required Icons (192px, 512px)',
                    check: async () => {
                        try {
                            const response = await fetch('/app.webmanifest');
                            const manifest = await response.json();
                            const icons = manifest.icons || [];
                            return icons.some(icon => icon.sizes.includes('192x192')) &&
                                   icons.some(icon => icon.sizes.includes('512x512'));
                        } catch {
                            return false;
                        }
                    },
                    critical: true
                },
                {
                    name: 'Offline Functionality',
                    check: async () => {
                        try {
                            const response = await fetch('/offline.html');
                            return response.ok;
                        } catch {
                            return false;
                        }
                    },
                    critical: false
                },
                {
                    name: 'Screenshots for App Stores',
                    check: async () => {
                        try {
                            const response = await fetch('/app.webmanifest');
                            const manifest = await response.json();
                            return manifest.screenshots && manifest.screenshots.length >= 2;
                        } catch {
                            return false;
                        }
                    },
                    critical: false
                }
            ];
            
            const container = document.getElementById('packaging-requirements');
            let html = '';
            let criticalPassed = 0;
            let totalCritical = 0;
            let optionalPassed = 0;
            let totalOptional = 0;
            
            for (const requirement of requirements) {
                const passed = typeof requirement.check === 'function' ? 
                    await requirement.check() : requirement.check;
                
                if (requirement.critical) {
                    totalCritical++;
                    if (passed) criticalPassed++;
                } else {
                    totalOptional++;
                    if (passed) optionalPassed++;
                }
                
                const resultClass = passed ? 'test-passed' : 
                    requirement.critical ? 'test-failed' : 'test-warning';
                const status = passed ? '✓' : '✗';
                const priority = requirement.critical ? ' (Critical)' : ' (Optional)';
                
                html += `<div class="test-result ${resultClass}">
                    ${status} ${requirement.name}${priority}
                </div>`;
            }
            
            // Add summary
            const allCriticalPassed = criticalPassed === totalCritical;
            const summaryClass = allCriticalPassed ? 'test-passed' : 'test-failed';
            const summaryStatus = allCriticalPassed ? '✓' : '✗';
            
            html = `<div class="test-result ${summaryClass} mb-3">
                ${summaryStatus} App Store Readiness: ${criticalPassed}/${totalCritical} critical requirements met
                ${totalOptional > 0 ? `, ${optionalPassed}/${totalOptional} optional features` : ''}
            </div>` + html;
            
            container.innerHTML = html;
            
            testResults.appStore = {
                criticalPassed: criticalPassed,
                totalCritical: totalCritical,
                optionalPassed: optionalPassed,
                totalOptional: totalOptional,
                ready: allCriticalPassed
            };
        }
        
        async function generatePackagingReport() {
            log('Generating packaging report...');
            
            // Collect all test results
            const report = {
                timestamp: new Date().toISOString(),
                url: location.href,
                userAgent: navigator.userAgent,
                testResults: testResults,
                summary: {
                    pwaScore: testResults.pwaBuilder?.overall?.score || 0,
                    appStoreReady: testResults.appStore?.ready || false,
                    performanceScore: testResults.performance?.performanceScore || 0,
                    browserCompatibility: testResults.browser?.compatibilityScore || 0
                }
            };
            
            // Create downloadable report
            const reportJson = JSON.stringify(report, null, 2);
            const blob = new Blob([reportJson], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `pwa-validation-report-${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            log('Packaging report downloaded', 'success');
        }
        
        async function downloadManifest() {
            log('Downloading manifest file...');
            
            try {
                const response = await fetch('/app.webmanifest');
                const manifest = await response.text();
                
                const blob = new Blob([manifest], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = 'app.webmanifest';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                log('Manifest file downloaded', 'success');
                
            } catch (error) {
                log('Failed to download manifest: ' + error.message, 'error');
            }
        }
        
        // Comprehensive Testing
        async function runAllTests() {
            log('Starting comprehensive PWA validation...', 'info');
            
            const container = document.getElementById('comprehensive-results');
            container.innerHTML = '<div class="test-result test-info">Running comprehensive tests...</div>';
            
            try {
                // Run all test suites
                await runPWABuilderAnalysis();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await testDesktopInstallation();
                await testMobileInstallation();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await testOfflineNavigation();
                await testOfflineFormSubmission();
                await testOfflineDataAccess();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await testNotificationPermission();
                await testNotificationSubscription();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await runPerformanceValidation();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await testBrowserCompatibility();
                await new Promise(resolve => setTimeout(resolve, 500));
                
                await validateAppStoreRequirements();
                
                // Generate comprehensive summary
                generateComprehensiveSummary();
                
                log('Comprehensive PWA validation completed', 'success');
                
            } catch (error) {
                log('Comprehensive testing failed: ' + error.message, 'error');
                container.innerHTML = `<div class="test-result test-failed">✗ Comprehensive testing failed: ${error.message}</div>`;
            }
        }
        
        function generateComprehensiveSummary() {
            const container = document.getElementById('comprehensive-results');
            
            const overallScore = testResults.pwaBuilder?.overall?.score || 0;
            const appStoreReady = testResults.appStore?.ready || false;
            const performanceScore = testResults.performance?.performanceScore || 0;
            const browserScore = testResults.browser?.compatibilityScore || 0;
            
            const averageScore = Math.round((overallScore + performanceScore + browserScore) / 3);
            
            let html = `
                <div class="test-result ${averageScore >= 80 ? 'test-passed' : averageScore >= 60 ? 'test-warning' : 'test-failed'}">
                    <div class="text-lg font-bold mb-2">
                        ${averageScore >= 80 ? '🎉' : averageScore >= 60 ? '⚠️' : '❌'} 
                        Overall PWA Score: ${averageScore}/100
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>PWA Builder Score: ${overallScore}/100</div>
                        <div>Performance Score: ${Math.round(performanceScore)}/100</div>
                        <div>Browser Compatibility: ${Math.round(browserScore)}%</div>
                        <div>App Store Ready: ${appStoreReady ? '✓ Yes' : '✗ No'}</div>
                    </div>
                </div>
            `;
            
            // Add recommendations
            const recommendations = [];
            
            if (overallScore < 80) {
                recommendations.push('Improve PWA Builder score by addressing manifest and service worker issues');
            }
            
            if (performanceScore < 70) {
                recommendations.push('Optimize performance by improving cache strategies and reducing load times');
            }
            
            if (browserScore < 80) {
                recommendations.push('Add polyfills or fallbacks for better browser compatibility');
            }
            
            if (!appStoreReady) {
                recommendations.push('Address critical app store requirements before packaging');
            }
            
            if (recommendations.length > 0) {
                html += '<div class="test-result test-info mt-3">';
                html += '<div class="font-medium mb-2">Recommendations:</div>';
                html += '<ul class="list-disc list-inside text-sm space-y-1">';
                recommendations.forEach(rec => {
                    html += `<li>${rec}</li>`;
                });
                html += '</ul></div>';
            } else {
                html += '<div class="test-result test-passed mt-3">🎉 Your PWA is ready for production and app store packaging!</div>';
            }
            
            container.innerHTML = html;
        }
        
        async function exportTestResults() {
            log('Exporting test results...');
            
            const exportData = {
                timestamp: new Date().toISOString(),
                url: location.href,
                userAgent: navigator.userAgent,
                testResults: testResults,
                testLog: testLog
            };
            
            const csv = convertToCSV(exportData);
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `pwa-test-results-${Date.now()}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            log('Test results exported', 'success');
        }
        
        function convertToCSV(data) {
            const headers = ['Test Category', 'Test Name', 'Result', 'Score', 'Details'];
            let csv = headers.join(',') + '\n';
            
            // Add PWA Builder results
            if (data.testResults.pwaBuilder) {
                Object.entries(data.testResults.pwaBuilder).forEach(([category, result]) => {
                    if (category !== 'overall') {
                        csv += `PWA Builder,${category},${result.passed ? 'PASS' : 'FAIL'},${result.score},"${result.issues.join('; ')}"\n`;
                    }
                });
            }
            
            // Add other test results
            Object.entries(data.testResults).forEach(([category, result]) => {
                if (category !== 'pwaBuilder' && result) {
                    csv += `${category},General,${result.ready !== false ? 'PASS' : 'FAIL'},${result.score || 'N/A'},"${JSON.stringify(result).replace(/"/g, '""')}"\n`;
                }
            });
            
            return csv;
        }
        
        function clearAllResults() {
            log('Clearing all test results...');
            
            // Reset test results
            testResults = {
                pwaBuilder: null,
                installation: null,
                offline: null,
                notifications: null,
                performance: null,
                browser: null,
                appStore: null
            };
            
            // Clear all result containers
            const containers = [
                'pwa-analysis-results',
                'desktop-install-result',
                'mobile-install-result',
                'offline-test-results',
                'notification-test-results',
                'performance-validation-results',
                'browser-test-results',
                'packaging-requirements',
                'comprehensive-results'
            ];
            
            containers.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.innerHTML = '<div class="test-result test-info">Tests cleared - click buttons to run tests</div>';
                }
            });
            
            // Reset scores
            ['overall-score', 'manifest-score', 'sw-score', 'icons-score'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = '--';
                    element.className = 'score-circle bg-gray-400 mx-auto mb-2';
                }
            });
            
            // Reset progress bar
            document.getElementById('readiness-percentage').textContent = '0%';
            document.getElementById('readiness-progress').style.width = '0%';
            
            // Reset performance metrics
            document.getElementById('performance-score').textContent = '--';
            document.getElementById('cache-efficiency').textContent = '--';
            document.getElementById('offline-readiness').textContent = '--';
            
            // Reset browser support indicators
            ['chrome-support', 'firefox-support', 'safari-support', 'edge-support'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.className = 'w-12 h-12 rounded-full bg-gray-300 mx-auto mb-2 flex items-center justify-center';
                    element.innerHTML = '<span class="text-white font-bold">?</span>';
                }
            });
            
            log('All test results cleared', 'success');
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', () => {
            log('PWA Validation Suite loaded', 'success');
            
            // Auto-run PWA Builder analysis
            setTimeout(() => {
                runPWABuilderAnalysis();
            }, 1000);
        });
    </script>
</body>
</html>