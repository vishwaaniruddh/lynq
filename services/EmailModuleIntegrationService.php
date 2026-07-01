<?php
/**
 * Email Module Integration Service
 * Handles integration between email system and CRM modules
 * Registers event listeners and processes module events for email triggers
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
 * - 5.1: Support feasibility module events
 * - 5.2: Support user creation events
 * - 5.3: Support site assignment events
 * - 5.4: Support material request events
 * - 5.5: Support dispatch operation events
 */

require_once __DIR__ . '/../config/autoload.php';

class EmailModuleIntegrationService {
    private $db;
    private $emailTriggerService;
    
    // Module event definitions
    const MODULE_FEASIBILITY = 'feasibility';
    const MODULE_INSTALLATION = 'installation';
    const MODULE_MATERIAL_REQUEST = 'material_request';
    const MODULE_DISPATCH = 'dispatch';
    const MODULE_USER = 'user';
    const MODULE_SITE = 'site';
    
    // Event type definitions
    const EVENT_FEASIBILITY_CREATED = 'feasibility_created';
    const EVENT_FEASIBILITY_SUBMITTED = 'feasibility_submitted';
    const EVENT_FEASIBILITY_APPROVED = 'feasibility_approved';
    const EVENT_FEASIBILITY_REJECTED = 'feasibility_rejected';
    
    const EVENT_INSTALLATION_INITIATED = 'installation_initiated';
    const EVENT_INSTALLATION_ASSIGNED = 'installation_assigned';
    const EVENT_INSTALLATION_SUBMITTED = 'installation_submitted';
    const EVENT_INSTALLATION_APPROVED = 'installation_approved';
    const EVENT_INSTALLATION_REJECTED = 'installation_rejected';
    
    const EVENT_MATERIAL_REQUEST_CREATED = 'material_request_created';
    const EVENT_MATERIAL_REQUEST_APPROVED = 'material_request_approved';
    const EVENT_MATERIAL_REQUEST_DISPATCHED = 'material_request_dispatched';
    const EVENT_MATERIAL_REQUEST_RECEIVED = 'material_request_received';
    
    const EVENT_DISPATCH_CREATED = 'dispatch_created';
    const EVENT_DISPATCH_SHIPPED = 'dispatch_shipped';
    const EVENT_DISPATCH_DELIVERED = 'dispatch_delivered';
    
    const EVENT_USER_CREATED = 'user_created';
    const EVENT_USER_ACTIVATED = 'user_activated';
    const EVENT_USER_DEACTIVATED = 'user_deactivated';
    
    const EVENT_SITE_CREATED = 'site_created';
    const EVENT_SITE_ASSIGNED = 'site_assigned';
    const EVENT_SITE_DELEGATED = 'site_delegated';
    
    // Registered event listeners
    private static $registeredListeners = [];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->emailTriggerService = new EmailTriggerService();
    }
    
    /**
     * Initialize module integration by registering all event listeners
     * This should be called during application bootstrap
     */
    public function initialize(): void {
        $this->registerFeasibilityEvents();
        $this->registerInstallationEvents();
        $this->registerMaterialRequestEvents();
        $this->registerDispatchEvents();
        $this->registerUserEvents();
        $this->registerSiteEvents();
    }
    
    /**
     * Register feasibility module event listeners
     * Requirements: 5.1
     */
    public function registerFeasibilityEvents(): void {
        $this->registerEventListener(self::MODULE_FEASIBILITY, self::EVENT_FEASIBILITY_CREATED);
        $this->registerEventListener(self::MODULE_FEASIBILITY, self::EVENT_FEASIBILITY_SUBMITTED);
        $this->registerEventListener(self::MODULE_FEASIBILITY, self::EVENT_FEASIBILITY_APPROVED);
        $this->registerEventListener(self::MODULE_FEASIBILITY, self::EVENT_FEASIBILITY_REJECTED);
    }
    
    /**
     * Register installation module event listeners
     */
    public function registerInstallationEvents(): void {
        $this->registerEventListener(self::MODULE_INSTALLATION, self::EVENT_INSTALLATION_INITIATED);
        $this->registerEventListener(self::MODULE_INSTALLATION, self::EVENT_INSTALLATION_ASSIGNED);
        $this->registerEventListener(self::MODULE_INSTALLATION, self::EVENT_INSTALLATION_SUBMITTED);
        $this->registerEventListener(self::MODULE_INSTALLATION, self::EVENT_INSTALLATION_APPROVED);
        $this->registerEventListener(self::MODULE_INSTALLATION, self::EVENT_INSTALLATION_REJECTED);
    }
    
    /**
     * Register material request module event listeners
     * Requirements: 5.4
     */
    public function registerMaterialRequestEvents(): void {
        $this->registerEventListener(self::MODULE_MATERIAL_REQUEST, self::EVENT_MATERIAL_REQUEST_CREATED);
        $this->registerEventListener(self::MODULE_MATERIAL_REQUEST, self::EVENT_MATERIAL_REQUEST_APPROVED);
        $this->registerEventListener(self::MODULE_MATERIAL_REQUEST, self::EVENT_MATERIAL_REQUEST_DISPATCHED);
        $this->registerEventListener(self::MODULE_MATERIAL_REQUEST, self::EVENT_MATERIAL_REQUEST_RECEIVED);
    }
    
    /**
     * Register dispatch module event listeners
     * Requirements: 5.5
     */
    public function registerDispatchEvents(): void {
        $this->registerEventListener(self::MODULE_DISPATCH, self::EVENT_DISPATCH_CREATED);
        $this->registerEventListener(self::MODULE_DISPATCH, self::EVENT_DISPATCH_SHIPPED);
        $this->registerEventListener(self::MODULE_DISPATCH, self::EVENT_DISPATCH_DELIVERED);
    }
    
    /**
     * Register user management event listeners
     * Requirements: 5.2
     */
    public function registerUserEvents(): void {
        $this->registerEventListener(self::MODULE_USER, self::EVENT_USER_CREATED);
        $this->registerEventListener(self::MODULE_USER, self::EVENT_USER_ACTIVATED);
        $this->registerEventListener(self::MODULE_USER, self::EVENT_USER_DEACTIVATED);
    }
    
    /**
     * Register site management event listeners
     * Requirements: 5.3
     */
    public function registerSiteEvents(): void {
        $this->registerEventListener(self::MODULE_SITE, self::EVENT_SITE_CREATED);
        $this->registerEventListener(self::MODULE_SITE, self::EVENT_SITE_ASSIGNED);
        $this->registerEventListener(self::MODULE_SITE, self::EVENT_SITE_DELEGATED);
    }
    
    /**
     * Register an event listener for a module and event type
     * 
     * @param string $moduleName Module name
     * @param string $eventType Event type
     */
    private function registerEventListener(string $moduleName, string $eventType): void {
        $key = "{$moduleName}.{$eventType}";
        
        if (!isset(self::$registeredListeners[$key])) {
            self::$registeredListeners[$key] = true;
            
            // Register with the email trigger service
            $this->emailTriggerService->registerEventListener($moduleName, $eventType);
        }
    }
    
    /**
     * Process feasibility module events
     * Requirements: 5.1
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public function processFeasibilityEvent(string $eventType, array $eventData): array {
        // Ensure required fields are present
        $eventData = $this->enrichFeasibilityEventData($eventData);
        
        return $this->emailTriggerService->processEvent(
            self::MODULE_FEASIBILITY,
            $eventType,
            $eventData,
            $eventData['company_id']
        );
    }
    
    /**
     * Process installation module events
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public function processInstallationEvent(string $eventType, array $eventData): array {
        // Ensure required fields are present
        $eventData = $this->enrichInstallationEventData($eventData);
        
        return $this->emailTriggerService->processEvent(
            self::MODULE_INSTALLATION,
            $eventType,
            $eventData,
            $eventData['company_id']
        );
    }
    
    /**
     * Process material request module events
     * Requirements: 5.4
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public function processMaterialRequestEvent(string $eventType, array $eventData): array {
        // Ensure required fields are present
        $eventData = $this->enrichMaterialRequestEventData($eventData);
        
        return $this->emailTriggerService->processEvent(
            self::MODULE_MATERIAL_REQUEST,
            $eventType,
            $eventData,
            $eventData['company_id']
        );
    }
    
    /**
     * Process dispatch module events
     * Requirements: 5.5
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public function processDispatchEvent(string $eventType, array $eventData): array {
        // Ensure required fields are present
        $eventData = $this->enrichDispatchEventData($eventData);
        
        return $this->emailTriggerService->processEvent(
            self::MODULE_DISPATCH,
            $eventType,
            $eventData,
            $eventData['company_id']
        );
    }
    
    /**
     * Process user management events
     * Requirements: 5.2
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public function processUserEvent(string $eventType, array $eventData): array {
        // Ensure required fields are present
        $eventData = $this->enrichUserEventData($eventData);
        
        return $this->emailTriggerService->processEvent(
            self::MODULE_USER,
            $eventType,
            $eventData,
            $eventData['company_id']
        );
    }
    
    /**
     * Process site management events
     * Requirements: 5.3
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     * @return array Processing result
     */
    public function processSiteEvent(string $eventType, array $eventData): array {
        // Ensure required fields are present
        $eventData = $this->enrichSiteEventData($eventData);
        
        return $this->emailTriggerService->processEvent(
            self::MODULE_SITE,
            $eventType,
            $eventData,
            $eventData['company_id']
        );
    }
    
    /**
     * Enrich feasibility event data with additional context
     * 
     * @param array $eventData Original event data
     * @return array Enriched event data
     */
    private function enrichFeasibilityEventData(array $eventData): array {
        // Get additional feasibility data if feasibility_id is provided
        if (isset($eventData['feasibility_id']) && !isset($eventData['site_name'])) {
            $sql = "SELECT 
                        fc.id as feasibility_id,
                        fc.assignment_id,
                        fc.site_id,
                        s.site_name,
                        s.lho,
                        s.address,
                        s.city,
                        s.state,
                        ea.engineer_id,
                        CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name,
                        eng.email as engineer_email,
                        sd.contractor_id,
                        comp.name as contractor_name
                    FROM feasibility_checks fc
                    LEFT JOIN sites s ON fc.site_id = s.id
                    LEFT JOIN engineer_assignments ea ON fc.assignment_id = ea.id
                    LEFT JOIN users eng ON ea.engineer_id = eng.id
                    LEFT JOIN site_delegations sd ON ea.delegation_id = sd.id
                    LEFT JOIN companies comp ON sd.contractor_id = comp.id
                    WHERE fc.id = ?";
            
            $result = $this->db->getResults($sql, [$eventData['feasibility_id']], 'i');
            if (!empty($result)) {
                $eventData = array_merge($eventData, $result[0]);
            }
        }
        
        return $eventData;
    }
    
    /**
     * Enrich installation event data with additional context
     * 
     * @param array $eventData Original event data
     * @return array Enriched event data
     */
    private function enrichInstallationEventData(array $eventData): array {
        // Get additional installation data if installation_id is provided
        if (isset($eventData['installation_id']) && !isset($eventData['site_name'])) {
            $sql = "SELECT 
                        i.id as installation_id,
                        i.site_id,
                        i.contractor_id,
                        i.engineer_id,
                        s.site_name,
                        s.lho,
                        s.address,
                        s.city,
                        s.state,
                        CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name,
                        eng.email as engineer_email,
                        comp.name as contractor_name
                    FROM installations i
                    LEFT JOIN sites s ON i.site_id = s.id
                    LEFT JOIN users eng ON i.engineer_id = eng.id
                    LEFT JOIN companies comp ON i.contractor_id = comp.id
                    WHERE i.id = ?";
            
            $result = $this->db->getResults($sql, [$eventData['installation_id']], 'i');
            if (!empty($result)) {
                $eventData = array_merge($eventData, $result[0]);
            }
        }
        
        return $eventData;
    }
    
    /**
     * Enrich material request event data with additional context
     * 
     * @param array $eventData Original event data
     * @return array Enriched event data
     */
    private function enrichMaterialRequestEventData(array $eventData): array {
        // Get additional material request data if request_id is provided
        if (isset($eventData['request_id']) && !isset($eventData['site_name'])) {
            $sql = "SELECT 
                        mr.id as request_id,
                        mr.site_id,
                        mr.material_master_id,
                        s.site_name,
                        s.lho,
                        s.address,
                        s.city,
                        s.state,
                        mm.name as material_master_name,
                        CONCAT(req.first_name, ' ', req.last_name) as requested_by_name,
                        req.email as requested_by_email
                    FROM material_requests mr
                    LEFT JOIN sites s ON mr.site_id = s.id
                    LEFT JOIN material_masters mm ON mr.material_master_id = mm.id
                    LEFT JOIN users req ON mr.requested_by = req.id
                    WHERE mr.id = ?";
            
            $result = $this->db->getResults($sql, [$eventData['request_id']], 'i');
            if (!empty($result)) {
                $eventData = array_merge($eventData, $result[0]);
            }
        }
        
        return $eventData;
    }
    
    /**
     * Enrich dispatch event data with additional context
     * 
     * @param array $eventData Original event data
     * @return array Enriched event data
     */
    private function enrichDispatchEventData(array $eventData): array {
        // Get additional dispatch data if dispatch_id is provided
        if (isset($eventData['dispatch_id']) && !isset($eventData['site_name'])) {
            $sql = "SELECT 
                        d.id as dispatch_id,
                        d.site_id,
                        d.contractor_id,
                        s.site_name,
                        s.lho,
                        s.address,
                        s.city,
                        s.state,
                        comp.name as contractor_name,
                        CONCAT(disp.first_name, ' ', disp.last_name) as dispatched_by_name,
                        disp.email as dispatched_by_email
                    FROM dispatches d
                    LEFT JOIN sites s ON d.site_id = s.id
                    LEFT JOIN companies comp ON d.contractor_id = comp.id
                    LEFT JOIN users disp ON d.dispatched_by = disp.id
                    WHERE d.id = ?";
            
            $result = $this->db->getResults($sql, [$eventData['dispatch_id']], 'i');
            if (!empty($result)) {
                $eventData = array_merge($eventData, $result[0]);
            }
        }
        
        return $eventData;
    }
    
    /**
     * Enrich user event data with additional context
     * 
     * @param array $eventData Original event data
     * @return array Enriched event data
     */
    private function enrichUserEventData(array $eventData): array {
        // Get additional user data if user_id is provided
        if (isset($eventData['user_id']) && !isset($eventData['user_name'])) {
            $sql = "SELECT 
                        u.id as user_id,
                        u.username,
                        u.email,
                        u.first_name,
                        u.last_name,
                        CONCAT(u.first_name, ' ', u.last_name) as user_name,
                        u.company_id,
                        comp.name as company_name,
                        r.name as role_name
                    FROM users u
                    LEFT JOIN companies comp ON u.company_id = comp.id
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.id = ?";
            
            $result = $this->db->getResults($sql, [$eventData['user_id']], 'i');
            if (!empty($result)) {
                $eventData = array_merge($eventData, $result[0]);
            }
        }
        
        return $eventData;
    }
    
    /**
     * Enrich site event data with additional context
     * 
     * @param array $eventData Original event data
     * @return array Enriched event data
     */
    private function enrichSiteEventData(array $eventData): array {
        // Get additional site data if site_id is provided
        if (isset($eventData['site_id']) && !isset($eventData['site_name'])) {
            $sql = "SELECT 
                        s.id as site_id,
                        s.site_name,
                        s.lho,
                        s.address,
                        s.city,
                        s.state,
                        s.country,
                        s.bank_name,
                        s.customer_name,
                        s.zone,
                        s.company_id
                    FROM sites s
                    WHERE s.id = ?";
            
            $result = $this->db->getResults($sql, [$eventData['site_id']], 'i');
            if (!empty($result)) {
                $eventData = array_merge($eventData, $result[0]);
            }
        }
        
        return $eventData;
    }
    
    /**
     * Get all registered event listeners
     * 
     * @return array List of registered event listeners
     */
    public function getRegisteredListeners(): array {
        return array_keys(self::$registeredListeners);
    }
    
    /**
     * Check if an event listener is registered
     * 
     * @param string $moduleName Module name
     * @param string $eventType Event type
     * @return bool True if listener is registered
     */
    public function isListenerRegistered(string $moduleName, string $eventType): bool {
        $key = "{$moduleName}.{$eventType}";
        return isset(self::$registeredListeners[$key]);
    }
    
    /**
     * Get available modules
     * 
     * @return array List of available modules
     */
    public function getAvailableModules(): array {
        return [
            self::MODULE_FEASIBILITY,
            self::MODULE_INSTALLATION,
            self::MODULE_MATERIAL_REQUEST,
            self::MODULE_DISPATCH,
            self::MODULE_USER,
            self::MODULE_SITE
        ];
    }
    
    /**
     * Get available events for a module
     * 
     * @param string $moduleName Module name
     * @return array List of available events
     */
    public function getAvailableEventsForModule(string $moduleName): array {
        switch ($moduleName) {
            case self::MODULE_FEASIBILITY:
                return [
                    self::EVENT_FEASIBILITY_CREATED,
                    self::EVENT_FEASIBILITY_SUBMITTED,
                    self::EVENT_FEASIBILITY_APPROVED,
                    self::EVENT_FEASIBILITY_REJECTED
                ];
                
            case self::MODULE_INSTALLATION:
                return [
                    self::EVENT_INSTALLATION_INITIATED,
                    self::EVENT_INSTALLATION_ASSIGNED,
                    self::EVENT_INSTALLATION_SUBMITTED,
                    self::EVENT_INSTALLATION_APPROVED,
                    self::EVENT_INSTALLATION_REJECTED
                ];
                
            case self::MODULE_MATERIAL_REQUEST:
                return [
                    self::EVENT_MATERIAL_REQUEST_CREATED,
                    self::EVENT_MATERIAL_REQUEST_APPROVED,
                    self::EVENT_MATERIAL_REQUEST_DISPATCHED,
                    self::EVENT_MATERIAL_REQUEST_RECEIVED
                ];
                
            case self::MODULE_DISPATCH:
                return [
                    self::EVENT_DISPATCH_CREATED,
                    self::EVENT_DISPATCH_SHIPPED,
                    self::EVENT_DISPATCH_DELIVERED
                ];
                
            case self::MODULE_USER:
                return [
                    self::EVENT_USER_CREATED,
                    self::EVENT_USER_ACTIVATED,
                    self::EVENT_USER_DEACTIVATED
                ];
                
            case self::MODULE_SITE:
                return [
                    self::EVENT_SITE_CREATED,
                    self::EVENT_SITE_ASSIGNED,
                    self::EVENT_SITE_DELEGATED
                ];
                
            default:
                return [];
        }
    }
}