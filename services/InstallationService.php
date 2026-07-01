<?php
/**
 * Installation Service
 * Handles business logic for installation operations and workflow management
 * 
 * Requirements: 1.1, 1.2, 1.6, 1.7, 4.4, 4.5, 5.3, 5.4, 5.5, 18.1, 18.2, 18.3
 * - 1.1: Display "Initiate Installation" button for ADV-approved feasibility
 * - 1.2: Redirect to installation delegation page on click
 * - 1.6: Hide button when feasibility is not ADV-approved
 * - 1.7: Hide button when installation already exists
 * - 4.4: Prevent form access when status is "pending_materials"
 * - 4.5: Enable form access when status is "materials_received" or later
 * - 5.3: Validate all required fields before submission
 * - 5.4: Create installation record with all captured data
 * - 5.5: Update status to "submitted" on successful submission
 * - 18.1: Display all installations with current status
 * - 18.2: Display material receipt status, submission date, approval status
 * - 18.3: Filter installations by status
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/FeasibilityCheckRepository.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/MaterialReceiptRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../config/InstallationSections.php';
require_once __DIR__ . '/InstallationNotificationService.php';

class InstallationService {
    private $db;
    private $installationRepository;
    private $feasibilityRepository;
    private $siteRepository;
    private $materialReceiptRepository;
    private $notificationService;
    
    // Required fields for installation submission
    private $requiredFields = [
        'vendor_name',
        'engineer_name',
        'engineer_number'
    ];
    
    // Section-specific required fields
    private $sectionRequiredFields = [
        'router' => ['router_serial', 'router_make', 'router_model', 'router_fixed', 'router_status'],
        'adaptor' => ['adaptor_installed', 'adaptor_status'],
        'lan_cable' => ['lan_cable_installed', 'lan_cable_status'],
        'antenna' => ['antenna_installed', 'antenna_status'],
        'gps' => ['gps_installed', 'gps_status'],
        'wifi' => ['wifi_installed', 'wifi_status'],
        'airtel_sim' => ['airtel_sim_installed', 'airtel_sim_status'],
        'vodafone_sim' => ['vodafone_sim_installed', 'vodafone_sim_status'],
        'jio_sim' => ['jio_sim_installed', 'jio_sim_status'],
        'verification' => ['signature_image']
    ];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationRepository = new InstallationRepository();
        $this->feasibilityRepository = new FeasibilityCheckRepository();
        $this->siteRepository = new SiteRepository();
        $this->materialReceiptRepository = new MaterialReceiptRepository();
        $this->notificationService = new InstallationNotificationService();
    }

    
    /**
     * Initiate installation for a site with ADV-approved feasibility
     * 
     * Creates installation with status "pending_assignment" - no contractor assigned yet.
     * The contractor will be assigned via delegation in a separate step.
     * 
     * @param int $siteId Site ID
     * @param int $feasibilityId Feasibility check ID
     * @param int $initiatedBy User ID initiating the installation
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.1, 1.2, 1.6, 1.7
     * - 1.1: Display "Initiate Installation" button for ADV-approved feasibility
     * - 1.2: Redirect to installation delegation page on click
     * - 1.6: Hide button when feasibility is not ADV-approved
     * - 1.7: Hide button when installation already exists
     */
    public function initiateInstallation(int $siteId, int $feasibilityId, int $initiatedBy): array {
        // Verify site exists
        $site = $this->siteRepository->findById($siteId);
        if (!$site) {
            return [
                'success' => false,
                'message' => 'Site not found',
                'code' => 'SITE_NOT_FOUND'
            ];
        }
        
        // Verify feasibility check exists and is ADV-approved (Requirement 1.1, 1.6)
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'success' => false,
                'message' => 'Feasibility check not found',
                'code' => 'FEASIBILITY_NOT_FOUND'
            ];
        }
        
        // Check if feasibility is ADV-approved
        $approvalStatus = $feasibility['approval_status'] ?? null;
        if ($approvalStatus !== 'adv_approved') {
            return [
                'success' => false,
                'message' => 'Installation can only be initiated for ADV-approved feasibility checks',
                'code' => 'FEASIBILITY_NOT_APPROVED'
            ];
        }
        
        // Check if installation already exists for this site (Requirement 1.7)
        $existingInstallation = $this->installationRepository->findBySiteId($siteId);
        if ($existingInstallation) {
            return [
                'success' => false,
                'message' => 'An installation already exists for this site',
                'code' => 'INSTALLATION_EXISTS'
            ];
        }
        
        try {
            // Prepare installation data with site information (Requirement 1.2)
            // Note: No contractor_id assigned yet - will be set via delegation
            $installationData = [
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'initiated_by' => $initiatedBy,
                'created_by' => $initiatedBy,
                // Pre-populate site information
                'atm_id' => $site['site_name'] ?? '',
                'address' => $site['address'] ?? '',
                'city' => $site['city'] ?? '',
                'location' => $site['address'] ?? '',
                'lho' => $site['lho'] ?? '',
                'state' => $site['state'] ?? '',
                // Set initial status to pending_assignment (no contractor yet)
                // Requirement 1.4: Create installation record with status "pending_assignment"
                'status' => Installation::STATUS_PENDING_ASSIGNMENT
            ];
            
            // Create installation record
            $installation = $this->installationRepository->create($installationData);
            
            // Log audit
            $this->logAction($initiatedBy, $installation['id'], 'installation_initiated', [
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId
            ]);
            
            return [
                'success' => true,
                'message' => 'Installation initiated successfully',
                'data' => $installation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to initiate installation: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Get installation by ID
     * 
     * @param int $installationId Installation ID
     * @return array|null Installation record or null
     */
    public function getInstallation(int $installationId): ?array {
        return $this->installationRepository->findById($installationId);
    }
    
    /**
     * Get installation by site ID
     * 
     * @param int $siteId Site ID
     * @return array|null Installation record or null
     */
    public function getInstallationBySite(int $siteId): ?array {
        return $this->installationRepository->findBySiteId($siteId);
    }
    
    /**
     * Get installation by feasibility ID
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Installation record or null
     */
    public function getInstallationByFeasibility(int $feasibilityId): ?array {
        return $this->installationRepository->findByFeasibilityId($feasibilityId);
    }
    
    /**
     * Get installation with full details
     * 
     * @param int $installationId Installation ID
     * @return array|null Installation with details or null
     */
    public function getInstallationWithDetails(int $installationId): ?array {
        return $this->installationRepository->findWithDetails($installationId);
    }

    
    /**
     * Save installation form data (partial save)
     * 
     * @param int $installationId Installation ID
     * @param array $data Form data to save
     * @param int $engineerId Engineer user ID
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.3, 3.4
     */
    public function saveInstallationData(int $installationId, array $data, int $engineerId): array {
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if form access is allowed (Requirement 2.4, 2.5)
        if (!$this->canAccessForm($installationId)) {
            return [
                'success' => false,
                'message' => 'Form access is not allowed until materials are received',
                'code' => 'FORM_ACCESS_DENIED'
            ];
        }
        
        // Check if installation is locked (ADV-approved)
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        try {
            // Update installation with form data
            $updatedInstallation = $this->installationRepository->update($installationId, $data);
            
            // Update status to in_progress if currently materials_received
            if ($installation['status'] === Installation::STATUS_MATERIALS_RECEIVED) {
                $this->updateInstallationStatus($installationId, Installation::STATUS_IN_PROGRESS);
                $updatedInstallation['status'] = Installation::STATUS_IN_PROGRESS;
            }
            
            return [
                'success' => true,
                'message' => 'Installation data saved successfully',
                'data' => $updatedInstallation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to save installation data: ' . $e->getMessage(),
                'code' => 'SAVE_ERROR'
            ];
        }
    }
    
    /**
     * Submit installation form
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.3, 3.5
     */
    public function submitInstallation(int $installationId, int $engineerId): array {
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if form access is allowed
        if (!$this->canAccessForm($installationId)) {
            return [
                'success' => false,
                'message' => 'Form access is not allowed until materials are received',
                'code' => 'FORM_ACCESS_DENIED'
            ];
        }
        
        // Check if installation is locked
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        // Validate all required fields (Requirement 3.3)
        $validation = $this->validateInstallationData($installation);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            // Update status to submitted (Requirement 3.5)
            $updateData = [
                'status' => Installation::STATUS_SUBMITTED,
                'submitted_by' => $engineerId,
                'submitted_at' => date('Y-m-d H:i:s')
            ];
            
            $updatedInstallation = $this->installationRepository->update($installationId, $updateData);
            
            // Log audit
            $this->logAction($engineerId, $installationId, 'installation_submitted', [
                'site_id' => $installation['site_id']
            ]);
            
            return [
                'success' => true,
                'message' => 'Installation submitted successfully',
                'data' => $updatedInstallation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to submit installation: ' . $e->getMessage(),
                'code' => 'SUBMIT_ERROR'
            ];
        }
    }
    
    /**
     * Update specific section data
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param array $data Section data
     * @param int $engineerId Engineer user ID
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.3, 3.4
     */
    public function updateSection(int $installationId, string $section, array $data, int $engineerId): array {
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if form access is allowed
        if (!$this->canAccessForm($installationId)) {
            return [
                'success' => false,
                'message' => 'Form access is not allowed until materials are received',
                'code' => 'FORM_ACCESS_DENIED'
            ];
        }
        
        // Check if installation is locked
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        // Validate section
        if (!InstallationSections::isValid($section)) {
            return [
                'success' => false,
                'message' => 'Invalid section identifier',
                'code' => 'INVALID_SECTION'
            ];
        }
        
        // Validate section data
        $validation = $this->validateSection($section, $data);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            // Update section data
            $updatedInstallation = $this->installationRepository->update($installationId, $data);
            
            return [
                'success' => true,
                'message' => 'Section updated successfully',
                'data' => $updatedInstallation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update section: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }

    
    /**
     * Get installation status
     * 
     * @param int $installationId Installation ID
     * @return string|null Installation status or null if not found
     * 
     * Requirements: 2.4, 2.5
     */
    public function getInstallationStatus(int $installationId): ?string {
        $installation = $this->installationRepository->findById($installationId);
        return $installation['status'] ?? null;
    }
    
    /**
     * Update installation status
     * 
     * @param int $installationId Installation ID
     * @param string $status New status
     * @return bool True if update was successful
     */
    public function updateInstallationStatus(int $installationId, string $status): bool {
        if (!Installation::isValidStatus($status)) {
            return false;
        }
        
        try {
            $this->installationRepository->updateStatus($installationId, $status);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if form access is allowed based on material receipt status
     * 
     * Form access is denied for early workflow statuses (pending_assignment, pending_eta, 
     * pending_ada, pending_materials) and enabled for materials_received or later.
     * 
     * @param int $installationId Installation ID
     * @return bool True if form access is allowed
     * 
     * Requirements: 4.4, 4.5
     * - 4.4: Prevent form access when status is "pending_materials" or earlier
     * - 4.5: Enable form access when status is "materials_received" or later
     */
    public function canAccessForm(int $installationId): bool {
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return false;
        }
        
        $status = $installation['status'];
        
        // Form access is denied for early workflow statuses (Requirement 4.4)
        // These are statuses before materials are received
        $deniedStatuses = [
            Installation::STATUS_PENDING_ASSIGNMENT,
            Installation::STATUS_PENDING_ETA,
            Installation::STATUS_PENDING_ADA,
            Installation::STATUS_PENDING_MATERIALS
        ];
        
        if (in_array($status, $deniedStatuses)) {
            return false;
        }
        
        // Form access is enabled for materials_received or later (Requirement 4.5)
        $allowedStatuses = [
            Installation::STATUS_MATERIALS_RECEIVED,
            Installation::STATUS_IN_PROGRESS,
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_PENDING_CONTRACTOR_REVIEW,
            Installation::STATUS_CONTRACTOR_APPROVED,
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_APPROVED,
            Installation::STATUS_ADV_REJECTED
        ];
        
        return in_array($status, $allowedStatuses);
    }
    
    /**
     * Check if the Installation button should be visible after ADA submission
     * 
     * The Installation button/link should be visible when:
     * - ADA has been submitted (status is pending_materials or later)
     * - This allows the engineer to access the installation form after confirming materials
     * 
     * @param int $installationId Installation ID
     * @return bool True if Installation button should be visible
     * 
     * Requirements: 3.6
     * - 3.6: Display "Installation" button/link after ADA submission
     */
    public function canShowInstallationButton(int $installationId): bool {
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return false;
        }
        
        $status = $installation['status'];
        
        // Installation button is visible after ADA submission
        // ADA submission moves status to pending_materials
        // So button is visible for pending_materials and all later statuses
        $visibleStatuses = [
            Installation::STATUS_PENDING_MATERIALS,
            Installation::STATUS_MATERIALS_RECEIVED,
            Installation::STATUS_IN_PROGRESS,
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_PENDING_CONTRACTOR_REVIEW,
            Installation::STATUS_CONTRACTOR_APPROVED,
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_APPROVED,
            Installation::STATUS_ADV_REJECTED
        ];
        
        return in_array($status, $visibleStatuses);
    }
    
    /**
     * Check if the Initiate Installation button should be visible
     * 
     * The button should be visible when:
     * - Feasibility is ADV-approved
     * - No existing installation for the site
     * 
     * @param int $siteId Site ID
     * @param int $feasibilityId Feasibility check ID
     * @return array Result with 'visible' boolean and 'reason' if not visible
     * 
     * Requirements: 1.1, 1.6, 1.7
     * - 1.1: Display button for ADV-approved feasibility
     * - 1.6: Hide button when feasibility is not ADV-approved
     * - 1.7: Hide button when installation already exists
     */
    public function canShowInitiateButton(int $siteId, int $feasibilityId): array {
        // Check if installation already exists (Requirement 1.7)
        $existingInstallation = $this->installationRepository->findBySiteId($siteId);
        if ($existingInstallation) {
            return [
                'visible' => false,
                'reason' => 'Installation already exists for this site'
            ];
        }
        
        // Check feasibility approval status (Requirement 1.1, 1.6)
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'visible' => false,
                'reason' => 'Feasibility check not found'
            ];
        }
        
        $approvalStatus = $feasibility['approval_status'] ?? null;
        if ($approvalStatus !== 'adv_approved') {
            return [
                'visible' => false,
                'reason' => 'Feasibility must be ADV-approved to initiate installation'
            ];
        }
        
        return [
            'visible' => true,
            'reason' => null
        ];
    }
    
    /**
     * Validate installation data for all required fields
     * 
     * @param array $data Installation data to validate
     * @return array Validation result with 'isValid', 'message', and 'errors'
     * 
     * Requirements: 3.3
     */
    public function validateInstallationData(array $data): array {
        $errors = [];
        
        // Check basic required fields
        foreach ($this->requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[] = [
                    'field' => $field,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            }
        }
        
        // Check section-specific required fields
        foreach ($this->sectionRequiredFields as $section => $fields) {
            foreach ($fields as $field) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    $errors[] = [
                        'field' => $field,
                        'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                        'code' => 'REQUIRED_FIELD_MISSING'
                    ];
                }
            }
        }
        
        // Validate yes/no fields
        $yesNoFields = ['router_fixed', 'adaptor_installed', 'lan_cable_installed', 
                        'antenna_installed', 'gps_installed', 'wifi_installed',
                        'airtel_sim_installed', 'vodafone_sim_installed', 'jio_sim_installed'];
        foreach ($yesNoFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                if (!in_array($data[$field], [Installation::YES, Installation::NO])) {
                    $errors[] = [
                        'field' => $field,
                        'message' => "The {$field} field must be 'yes' or 'no'",
                        'code' => 'INVALID_YES_NO_VALUE'
                    ];
                }
            }
        }
        
        // Validate working status fields
        $workingFields = ['router_status', 'adaptor_status', 'lan_cable_status',
                          'antenna_status', 'gps_status', 'wifi_status',
                          'airtel_sim_status', 'vodafone_sim_status', 'jio_sim_status'];
        foreach ($workingFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                if (!in_array($data[$field], [Installation::WORKING, Installation::NOT_WORKING])) {
                    $errors[] = [
                        'field' => $field,
                        'message' => "The {$field} field must be 'working' or 'notWorking'",
                        'code' => 'INVALID_WORKING_STATUS'
                    ];
                }
            }
        }
        
        if (!empty($errors)) {
            return [
                'isValid' => false,
                'message' => $errors[0]['message'],
                'errors' => $errors
            ];
        }
        
        return [
            'isValid' => true,
            'message' => 'Valid installation data',
            'errors' => []
        ];
    }
    
    /**
     * Validate section-specific data
     * 
     * @param string $section Section identifier
     * @param array $data Section data to validate
     * @return array Validation result with 'isValid', 'message', and 'errors'
     * 
     * Requirements: 3.3
     */
    public function validateSection(string $section, array $data): array {
        $errors = [];
        
        // Map section to required fields
        $sectionMapping = [
            InstallationSections::ROUTER_FIXED => ['router_fixed'],
            InstallationSections::ROUTER_STATUS => ['router_status'],
            InstallationSections::ADAPTOR => ['adaptor_installed'],
            InstallationSections::ADAPTOR_STATUS => ['adaptor_status'],
            InstallationSections::LAN_CABLE_INSTALL => ['lan_cable_installed'],
            InstallationSections::LAN_CABLE_STATUS => ['lan_cable_status'],
            InstallationSections::ANTENNA => ['antenna_installed'],
            InstallationSections::ANTENNA_STATUS => ['antenna_status'],
            InstallationSections::GPS => ['gps_installed'],
            InstallationSections::GPS_STATUS => ['gps_status'],
            InstallationSections::WIFI => ['wifi_installed'],
            InstallationSections::WIFI_STATUS => ['wifi_status'],
            InstallationSections::AIRTEL_SIM => ['airtel_sim_installed'],
            InstallationSections::AIRTEL_SIM_STATUS => ['airtel_sim_status'],
            InstallationSections::VODAFONE_SIM => ['vodafone_sim_installed'],
            InstallationSections::VODAFONE_SIM_STATUS => ['vodafone_sim_status'],
            InstallationSections::JIO_SIM => ['jio_sim_installed'],
            InstallationSections::JIO_SIM_STATUS => ['jio_sim_status'],
            InstallationSections::VERIFICATION => ['signature_image']
        ];
        
        // Get required fields for this section
        $requiredFields = $sectionMapping[$section] ?? [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[] = [
                    'field' => $field,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            }
        }
        
        if (!empty($errors)) {
            return [
                'isValid' => false,
                'message' => $errors[0]['message'],
                'errors' => $errors
            ];
        }
        
        return [
            'isValid' => true,
            'message' => 'Valid section data',
            'errors' => []
        ];
    }

    
    /**
     * Get installation tracking data with filters
     * 
     * @param array $filters Filter options (status, date_from, date_to, page, limit)
     * @return array Paginated results with installations
     * 
     * Requirements: 16.1, 16.2, 16.3
     */
    public function getInstallationTracking(array $filters = []): array {
        return $this->installationRepository->findAllWithFilters($filters);
    }
    
    /**
     * Get installation status counts
     * 
     * @param int|null $companyId Optional company ID filter
     * @return array Status counts
     */
    public function getInstallationStatusCounts(?int $companyId = null): array {
        return $this->installationRepository->countByStatus($companyId);
    }
    
    /**
     * Check if installation can be initiated for a site
     * 
     * @param int $siteId Site ID
     * @param int $feasibilityId Feasibility check ID
     * @return array Result with 'canInitiate' and 'reason'
     * 
     * Requirements: 1.1, 1.5
     */
    public function canInitiateInstallation(int $siteId, int $feasibilityId): array {
        // Check if installation already exists
        $existingInstallation = $this->installationRepository->findBySiteId($siteId);
        if ($existingInstallation) {
            return [
                'canInitiate' => false,
                'reason' => 'Installation already exists for this site'
            ];
        }
        
        // Check feasibility approval status
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'canInitiate' => false,
                'reason' => 'Feasibility check not found'
            ];
        }
        
        $approvalStatus = $feasibility['approval_status'] ?? null;
        if ($approvalStatus !== 'adv_approved') {
            return [
                'canInitiate' => false,
                'reason' => 'Feasibility must be ADV-approved to initiate installation'
            ];
        }
        
        return [
            'canInitiate' => true,
            'reason' => null
        ];
    }
    
    /**
     * Get installations for export (no pagination)
     * 
     * @param array $filters Optional filters
     * @return array List of installations
     */
    public function getInstallationsForExport(array $filters = []): array {
        return $this->installationRepository->findAllForExport($filters);
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int $userId User performing the action
     * @param int $installationId Installation ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(int $userId, int $installationId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['installation_id'] = $installationId;
            $details['entity_type'] = 'installation';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log installation action: " . $e->getMessage());
        }
    }
}
