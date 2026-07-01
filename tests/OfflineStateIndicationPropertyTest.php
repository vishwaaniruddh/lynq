<?php
/**
 * Property Test for Offline State Indication
 * **Feature: clarity-pwa-conversion, Property 5: Offline State Indication**
 * **Validates: Requirements 1.5**
 */

require_once 'PropertyTestBase.php';

class OfflineStateIndicationPropertyTest extends PropertyTestBase {
    
    private $pwaManagerPath;
    private $testPages = [
        '/dashboard.php',
        '/inventory/',
        '/installation/',
        '/sites/',
        '/engineer/feasibility_list.php'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->pwaManagerPath = __DIR__ . '/../assets/js/pwa-manager.js';
    }
    
    public function runTests(): bool {
        echo "=== Offline State Indication Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 5: Offline State Indication
        $allPassed &= $this->runPropertyTest(
            "Property 5: PWA Manager displays offline indicators when offline",
            [$this, 'testOfflineIndicatorDisplay']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 5: PWA Manager handles online/offline state changes",
            [$this, 'testOnlineOfflineStateHandling']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 5: Offline indicators are visible and informative",
            [$this, 'testOfflineIndicatorVisibility']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 5: PWA Manager displays offline indicators when offline
     * **Feature: clarity-pwa-conversion, Property 5: Offline State Indication**
     * **Validates: Requirements 1.5**
     */
    public function testOfflineIndicatorDisplay(): array {
        try {
            $this->assert(
                file_exists($this->pwaManagerPath),
                "PWA Manager file should exist: assets/js/pwa-manager.js"
            );
            
            $pwaManagerContent = file_get_contents($this->pwaManagerPath);
            
            $this->assert(
                !empty($pwaManagerContent),
                "PWA Manager should not be empty"
            );
            
            // Test that PWA Manager contains offline indicator functionality
            $this->assert(
                strpos($pwaManagerContent, 'showOfflineIndicator') !== false,
                "PWA Manager should contain showOfflineIndicator method"
            );
            
            // Test that PWA Manager contains offline indicator hiding functionality
            $this->assert(
                strpos($pwaManagerContent, 'hideOfflineIndicator') !== false,
                "PWA Manager should contain hideOfflineIndicator method"
            );
            
            // Test that PWA Manager creates offline indicator element
            $offlineIndicatorPatterns = [
                'pwa-offline-indicator',
                'offline-indicator',
                'You\'re offline',
                'offline'
            ];
            
            $hasOfflineIndicator = false;
            foreach ($offlineIndicatorPatterns as $pattern) {
                if (strpos($pwaManagerContent, $pattern) !== false) {
                    $hasOfflineIndicator = true;
                    break;
                }
            }
            
            $this->assert(
                $hasOfflineIndicator,
                "PWA Manager should create offline indicator elements"
            );
            
            // Test that PWA Manager handles navigator.onLine
            $this->assert(
                strpos($pwaManagerContent, 'navigator.onLine') !== false,
                "PWA Manager should check navigator.onLine status"
            );
            
            return [
                'success' => true,
                'data' => [
                    'pwa_manager_size' => strlen($pwaManagerContent),
                    'has_show_offline' => strpos($pwaManagerContent, 'showOfflineIndicator') !== false,
                    'has_hide_offline' => strpos($pwaManagerContent, 'hideOfflineIndicator') !== false,
                    'has_navigator_online' => strpos($pwaManagerContent, 'navigator.onLine') !== false
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 5: PWA Manager handles online/offline state changes
     * **Feature: clarity-pwa-conversion, Property 5: Offline State Indication**
     * **Validates: Requirements 1.5**
     */
    public function testOnlineOfflineStateHandling(): array {
        try {
            $pwaManagerContent = file_get_contents($this->pwaManagerPath);
            
            // Test that PWA Manager listens for online/offline events
            $this->assert(
                strpos($pwaManagerContent, 'addEventListener') !== false &&
                (strpos($pwaManagerContent, 'online') !== false || strpos($pwaManagerContent, 'offline') !== false),
                "PWA Manager should listen for online/offline events"
            );
            
            // Test that PWA Manager has handleOnlineStatusChange method
            $this->assert(
                strpos($pwaManagerContent, 'handleOnlineStatusChange') !== false,
                "PWA Manager should have handleOnlineStatusChange method"
            );
            
            // Test that PWA Manager tracks online status
            $onlineStatusPatterns = [
                'this.isOnline',
                'isOnline',
                'onlineStatus'
            ];
            
            $hasOnlineStatus = false;
            foreach ($onlineStatusPatterns as $pattern) {
                if (strpos($pwaManagerContent, $pattern) !== false) {
                    $hasOnlineStatus = true;
                    break;
                }
            }
            
            $this->assert(
                $hasOnlineStatus,
                "PWA Manager should track online status"
            );
            
            // Test that PWA Manager dispatches custom events for status changes
            $customEventPatterns = [
                'dispatchEvent',
                'CustomEvent',
                'pwa-connection-change'
            ];
            
            $hasCustomEvents = false;
            foreach ($customEventPatterns as $pattern) {
                if (strpos($pwaManagerContent, $pattern) !== false) {
                    $hasCustomEvents = true;
                    break;
                }
            }
            
            $this->assert(
                $hasCustomEvents,
                "PWA Manager should dispatch custom events for status changes"
            );
            
            return [
                'success' => true,
                'data' => [
                    'has_event_listeners' => strpos($pwaManagerContent, 'addEventListener') !== false,
                    'has_status_handler' => strpos($pwaManagerContent, 'handleOnlineStatusChange') !== false,
                    'has_online_status' => $hasOnlineStatus,
                    'has_custom_events' => $hasCustomEvents
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 5: Offline indicators are visible and informative
     * **Feature: clarity-pwa-conversion, Property 5: Offline State Indication**
     * **Validates: Requirements 1.5**
     */
    public function testOfflineIndicatorVisibility(): array {
        try {
            $pwaManagerContent = file_get_contents($this->pwaManagerPath);
            
            // Test that offline indicator has proper CSS classes for visibility
            $visibilityPatterns = [
                'fixed',
                'bottom',
                'right',
                'z-',
                'bg-red',
                'text-white'
            ];
            
            $hasVisibilityStyles = 0;
            foreach ($visibilityPatterns as $pattern) {
                if (strpos($pwaManagerContent, $pattern) !== false) {
                    $hasVisibilityStyles++;
                }
            }
            
            $this->assert(
                $hasVisibilityStyles >= 3,
                "Offline indicator should have proper visibility styles (found $hasVisibilityStyles/6 patterns)"
            );
            
            // Test that offline indicator contains informative text
            $informativeTextPatterns = [
                'offline',
                'You\'re offline',
                'No connection',
                'wifi'
            ];
            
            $hasInformativeText = false;
            foreach ($informativeTextPatterns as $pattern) {
                if (stripos($pwaManagerContent, $pattern) !== false) {
                    $hasInformativeText = true;
                    break;
                }
            }
            
            $this->assert(
                $hasInformativeText,
                "Offline indicator should contain informative text"
            );
            
            // Test that offline indicator includes visual icons
            $iconPatterns = [
                'fa-wifi',
                'fas fa-',
                'icon',
                '<i class='
            ];
            
            $hasIcons = false;
            foreach ($iconPatterns as $pattern) {
                if (strpos($pwaManagerContent, $pattern) !== false) {
                    $hasIcons = true;
                    break;
                }
            }
            
            $this->assert(
                $hasIcons,
                "Offline indicator should include visual icons"
            );
            
            // Test that indicator can be shown and hidden dynamically
            $this->assert(
                strpos($pwaManagerContent, 'style.display') !== false ||
                strpos($pwaManagerContent, 'classList') !== false ||
                strpos($pwaManagerContent, 'hidden') !== false,
                "Offline indicator should support dynamic show/hide"
            );
            
            return [
                'success' => true,
                'data' => [
                    'visibility_styles_count' => $hasVisibilityStyles,
                    'has_informative_text' => $hasInformativeText,
                    'has_icons' => $hasIcons,
                    'supports_dynamic_display' => (
                        strpos($pwaManagerContent, 'style.display') !== false ||
                        strpos($pwaManagerContent, 'classList') !== false ||
                        strpos($pwaManagerContent, 'hidden') !== false
                    )
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}