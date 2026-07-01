<?php
/**
 * Email Event Dispatcher
 * Provides static methods for dispatching events from module services to email system
 * This acts as a bridge between existing module services and the email trigger system
 */

require_once __DIR__ . '/../config/autoload.php';

class EmailEventDispatcher {
    private static $moduleIntegrationService = null;
    
    /**
     * Get or create module integration service instance
     * 
     * @return EmailModuleIntegrationService
     */
    private static function getModuleIntegrationService(): EmailModuleIntegrationService {
        if (self::$moduleIntegrationService === null) {
            self::$moduleIntegrationService = new EmailModuleIntegrationService();
        }
        return self::$moduleIntegrationService;
    }
    
    /**
     * Dispatch feasibility event
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public static function dispatchFeasibilityEvent(string $eventType, array $eventData): array {
        try {
            return self::getModuleIntegrationService()->processFeasibilityEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch feasibility event: " . $e->getMessage());
            return [
                'success' => false,
                'triggered_count' => 0,
                'queued_emails' => 0,
                'errors' => ['dispatch_error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Dispatch installation event
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public static function dispatchInstallationEvent(string $eventType, array $eventData): array {
        try {
            return self::getModuleIntegrationService()->processInstallationEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch installation event: " . $e->getMessage());
            return [
                'success' => false,
                'triggered_count' => 0,
                'queued_emails' => 0,
                'errors' => ['dispatch_error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Dispatch material request event
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public static function dispatchMaterialRequestEvent(string $eventType, array $eventData): array {
        try {
            return self::getModuleIntegrationService()->processMaterialRequestEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch material request event: " . $e->getMessage());
            return [
                'success' => false,
                'triggered_count' => 0,
                'queued_emails' => 0,
                'errors' => ['dispatch_error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Dispatch dispatch event
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public static function dispatchDispatchEvent(string $eventType, array $eventData): array {
        try {
            return self::getModuleIntegrationService()->processDispatchEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch dispatch event: " . $e->getMessage());
            return [
                'success' => false,
                'triggered_count' => 0,
                'queued_emails' => 0,
                'errors' => ['dispatch_error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Dispatch user event
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public static function dispatchUserEvent(string $eventType, array $eventData): array {
        try {
            return self::getModuleIntegrationService()->processUserEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch user event: " . $e->getMessage());
            return [
                'success' => false,
                'triggered_count' => 0,
                'queued_emails' => 0,
                'errors' => ['dispatch_error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Dispatch site event
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public static function dispatchSiteEvent(string $eventType, array $eventData): array {
        try {
            return self::getModuleIntegrationService()->processSiteEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch site event: " . $e->getMessage());
            return [
                'success' => false,
                'triggered_count' => 0,
                'queued_emails' => 0,
                'errors' => ['dispatch_error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Initialize email module integration
     * This should be called during application bootstrap
     */
    public static function initialize(): void {
        self::getModuleIntegrationService()->initialize();
    }
}