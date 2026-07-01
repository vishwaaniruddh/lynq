/**
 * ADV Clarity Management System - PWA Integration Test
 * Tests PWA functionality integration with existing application
 */

class PWAIntegrationTest {
    constructor() {
        this.tests = [];
        this.results = {
            passed: 0,
            failed: 0,
            total: 0
        };
    }

    /**
     * Run all PWA integration tests
     */
    async runTests() {
        console.log('[PWA Test] Starting PWA Integration Tests...');
        
        // Test service worker registration
        await this.testServiceWorkerRegistration();
        
        // Test PWA manager initialization
        await this.testPWAManagerInitialization();
        
        // Test offline utilities
        await this.testOfflineUtilities();
        
        // Test network status monitoring
        await this.testNetworkStatusMonitoring();
        
        // Test offline form handling
        await this.testOfflineFormHandling();
        
        // Test data caching
        await this.testDataCaching();
        
        // Test manifest and meta tags
        await this.testManifestAndMetaTags();
        
        // Test backward compatibility
        await this.testBackwardCompatibility();
        
        // Display results
        this.displayResults();
        
        return this.results;
    }

    /**
     * Test service worker registration
     */
    async testServiceWorkerRegistration() {
        const testName = 'Service Worker Registration';
        
        try {
            if (!('serviceWorker' in navigator)) {
                this.addTest(testName, false, 'Service Workers not supported');
                return;
            }
            
            const registration = await navigator.serviceWorker.getRegistration('/');
            
            if (registration) {
                this.addTest(testName, true, 'Service worker registered successfully');
            } else {
                this.addTest(testName, false, 'Service worker not registered');
            }
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Test PWA manager initialization
     */
    async testPWAManagerInitialization() {
        const testName = 'PWA Manager Initialization';
        
        try {
            if (typeof window.pwaManager !== 'undefined' && window.pwaManager) {
                this.addTest(testName, true, 'PWA Manager initialized');
            } else {
                this.addTest(testName, false, 'PWA Manager not found');
            }
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Test offline utilities
     */
    async testOfflineUtilities() {
        const testName = 'Offline Utilities';
        
        try {
            if (typeof window.offlineUtils !== 'undefined' && window.offlineUtils) {
                // Test queue functionality
                const queueLength = window.offlineUtils.getOfflineQueueLength();
                
                if (typeof queueLength === 'number') {
                    this.addTest(testName, true, 'Offline utilities working');
                } else {
                    this.addTest(testName, false, 'Queue functionality not working');
                }
            } else {
                this.addTest(testName, false, 'Offline utilities not found');
            }
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Test network status monitoring
     */
    async testNetworkStatusMonitoring() {
        const testName = 'Network Status Monitoring';
        
        try {
            if (typeof window.networkStatus !== 'undefined' && window.networkStatus) {
                const status = window.networkStatus.getStatus();
                
                if (status && typeof status.isOnline === 'boolean') {
                    this.addTest(testName, true, 'Network status monitoring active');
                } else {
                    this.addTest(testName, false, 'Network status not working');
                }
            } else {
                this.addTest(testName, false, 'Network status monitor not found');
            }
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Test offline form handling
     */
    async testOfflineFormHandling() {
        const testName = 'Offline Form Handling';
        
        try {
            if (typeof window.offlineFormHandler !== 'undefined' && window.offlineFormHandler) {
                const stats = window.offlineFormHandler.getFormStats();
                
                if (stats && typeof stats.savedForms === 'number') {
                    this.addTest(testName, true, 'Offline form handler working');
                } else {
                    this.addTest(testName, false, 'Form handler not working properly');
                }
            } else {
                this.addTest(testName, false, 'Offline form handler not found');
            }
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Test data caching
     */
    async testDataCaching() {
        const testName = 'Data Caching';
        
        try {
            if (typeof window.offlineDataManager !== 'undefined' && window.offlineDataManager) {
                const stats = window.offlineDataManager.getCacheStats();
                
                if (stats && typeof stats.totalEntries === 'number') {
                    this.addTest(testName, true, 'Data caching working');
                } else {
                    this.addTest(testName, false, 'Cache stats not available');
                }
            } else {
                this.addTest(testName, false, 'Offline data manager not found');
            }
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Test manifest and meta tags
     */
    async testManifestAndMetaTags() {
        const testName = 'Manifest and Meta Tags';
        
        try {
            // Check for manifest link
            const manifestLink = document.querySelector('link[rel="manifest"]');
            if (!manifestLink) {
                this.addTest(testName, false, 'Manifest link not found');
                return;
            }
            
            // Check for theme color
            const themeColor = document.querySelector('meta[name="theme-color"]');
            if (!themeColor) {
                this.addTest(testName, false, 'Theme color meta tag not found');
                return;
            }
            
            // Check for Apple PWA meta tags
            const appleCapable = document.querySelector('meta[name="apple-mobile-web-app-capable"]');
            if (!appleCapable) {
                this.addTest(testName, false, 'Apple PWA meta tags not found');
                return;
            }
            
            this.addTest(testName, true, 'All PWA meta tags present');
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Test backward compatibility
     */
    async testBackwardCompatibility() {
        const testName = 'Backward Compatibility';
        
        try {
            // Check if existing functionality still works
            let compatibilityIssues = [];
            
            // Test if forms still work normally when online
            const forms = document.querySelectorAll('form');
            if (forms.length === 0) {
                compatibilityIssues.push('No forms found to test');
            }
            
            // Test if navigation still works
            const navLinks = document.querySelectorAll('a[href]');
            if (navLinks.length === 0) {
                compatibilityIssues.push('No navigation links found');
            }
            
            // Test if existing JavaScript still works
            if (typeof window.CRM === 'undefined' && typeof window.NotificationManager === 'undefined') {
                compatibilityIssues.push('Existing JavaScript objects not found');
            }
            
            if (compatibilityIssues.length === 0) {
                this.addTest(testName, true, 'Backward compatibility maintained');
            } else {
                this.addTest(testName, false, `Issues: ${compatibilityIssues.join(', ')}`);
            }
        } catch (error) {
            this.addTest(testName, false, `Error: ${error.message}`);
        }
    }

    /**
     * Add test result
     */
    addTest(name, passed, message) {
        this.tests.push({
            name,
            passed,
            message,
            timestamp: new Date().toISOString()
        });
        
        this.results.total++;
        if (passed) {
            this.results.passed++;
        } else {
            this.results.failed++;
        }
        
        console.log(`[PWA Test] ${passed ? '✓' : '✗'} ${name}: ${message}`);
    }

    /**
     * Display test results
     */
    displayResults() {
        console.log('\n[PWA Test] Integration Test Results:');
        console.log(`Total Tests: ${this.results.total}`);
        console.log(`Passed: ${this.results.passed}`);
        console.log(`Failed: ${this.results.failed}`);
        console.log(`Success Rate: ${((this.results.passed / this.results.total) * 100).toFixed(1)}%`);
        
        if (this.results.failed > 0) {
            console.log('\nFailed Tests:');
            this.tests.filter(test => !test.passed).forEach(test => {
                console.log(`- ${test.name}: ${test.message}`);
            });
        }
        
        // Create visual indicator
        this.createVisualIndicator();
    }

    /**
     * Create visual test results indicator
     */
    createVisualIndicator() {
        // Remove existing indicator
        const existing = document.getElementById('pwa-test-indicator');
        if (existing) {
            existing.remove();
        }
        
        const indicator = document.createElement('div');
        indicator.id = 'pwa-test-indicator';
        indicator.className = 'fixed bottom-4 left-4 bg-white border border-gray-200 rounded-lg shadow-lg p-3 z-50 max-w-xs';
        
        const successRate = (this.results.passed / this.results.total) * 100;
        const statusColor = successRate >= 80 ? 'text-green-600' : successRate >= 60 ? 'text-yellow-600' : 'text-red-600';
        const statusIcon = successRate >= 80 ? 'fa-check-circle' : successRate >= 60 ? 'fa-exclamation-triangle' : 'fa-times-circle';
        
        indicator.innerHTML = `
            <div class="flex items-center space-x-2 mb-2">
                <i class="fas ${statusIcon} ${statusColor}"></i>
                <span class="font-semibold text-gray-800">PWA Integration Test</span>
                <button onclick="this.closest('#pwa-test-indicator').remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="text-sm text-gray-600">
                <div>Tests: ${this.results.passed}/${this.results.total} passed</div>
                <div>Success Rate: ${successRate.toFixed(1)}%</div>
            </div>
            <div class="mt-2">
                <button onclick="console.log(window.pwaIntegrationTest.tests)" 
                        class="text-xs text-blue-600 hover:text-blue-800">
                    View Details in Console
                </button>
            </div>
        `;
        
        document.body.appendChild(indicator);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.remove();
            }
        }, 10000);
    }

    /**
     * Get test results
     */
    getResults() {
        return {
            results: this.results,
            tests: this.tests
        };
    }
}

// Initialize and run tests when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait a bit for all PWA components to initialize
    setTimeout(async () => {
        window.pwaIntegrationTest = new PWAIntegrationTest();
        await window.pwaIntegrationTest.runTests();
    }, 2000);
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PWAIntegrationTest;
}