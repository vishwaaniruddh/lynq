<?php
/**
 * Installation Delegation Service
 * Handles delegation of installation sites to contractors
 * 
 * Requirements: 1.3, 1.4, 1.6, 1.7
 * - 1.3: Display form to select contractor for installation
 * - 1.4: Create installation record with status "pending_assignment" linked to contractor
 * - 1.6: Hide button when feasibility is not ADV-approved
 * - 1.7: Hide button when installation already exists
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/FeasibilityCheckRepository.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/InstallationNotificationService.php';

class InstallationDelegationService {
    private $db;
    private $installationRepository;
    private $feasibilityRepository;
    private $siteRepository;
    private $companyRepository;
    private $notificationService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationRepository = new InstallationRepository();
        $this->feasibilityRepository = new FeasibilityCheckRepository();
        $this->siteRepository = new SiteRepository();
        $this->companyRepository = new CompanyRepository();
        $this->notificationService = new InstallationNotificationService();
    }
    
    /**
     * Delegate installation to a contractor
     * Creates an installation record with status "pending_assignment" and links to contractor
     * 
     * @param int $siteId Site ID
     * @param int $feasibilityId Feasibility check ID
     * @param int $contractorId Contractor company ID
     * @param int $delegatedBy User ID performing the delegation
     * @return array Result with success status and data/errors
     * 
     * Requirements: 1.3, 1.4
     */
    public function delegateInstallation(int $siteId, int $feasibilityId, int $contractorId, int $delegatedBy): array {
        // Validate delegation is allowed
        $canDelegate = $this->canDelegate($siteId, $feasibilityId);
        if (!$canDelegate['canDelegate']) {
            return [
                'success' => false,
                'message' => $canDelegate['reason'],
                'code' => $canDelegate['code']
            ];
        }
        
        // Validate contractor
        $validation = $this->validateDelegation($contractorId);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'code' => $validation['code']
            ];
        }
        
        // Get site data for pre-population
        $site = $this->siteRepository->findById($siteId);
        if (!$site) {
            return [
                'success' => false,
                'message' => 'Site not found',
                'code' => 'SITE_NOT_FOUND'
            ];
        }
        
        try {
            // Create installation record with delegation data (Requirement 1.4)
            $installationData = [
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'initiated_by' => $delegatedBy,
                'created_by' => $delegatedBy,
                // Delegation fields
                'contractor_id' => $contractorId,
                'delegated_by' => $delegatedBy,
                'delegated_at' => date('Y-m-d H:i:s'),
                // Pre-populate site information
                'atm_id' => $site['site_name'] ?? '',
                'address' => $site['address'] ?? '',
                'city' => $site['city'] ?? '',
                'location' => $site['address'] ?? '',
                'lho' => $site['lho'] ?? '',
                'state' => $site['state'] ?? '',
                // Set initial status (Requirement 1.4)
                'status' => Installation::STATUS_PENDING_ASSIGNMENT
            ];
            
            // Create installation record
            $installation = $this->installationRepository->create($installationData);
            
            // Log audit
            $this->logAction($delegatedBy, $installation['id'], 'installation_delegated', [
                'site_id' => $siteId,
                'feasibility_id' => $feasibilityId,
                'contractor_id' => $contractorId
            ]);
            
            // Send notification to contractor (Requirement 1.5)
            $this->notificationService->notifyInstallationDelegated($installation, $contractorId);
            
            return [
                'success' => true,
                'message' => 'Installation delegated successfully',
                'data' => $installation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delegate installation: ' . $e->getMessage(),
                'code' => 'DELEGATION_ERROR'
            ];
        }
    }

    
    /**
     * Get delegation details for an installation
     * 
     * @param int $installationId Installation ID
     * @return array|null Delegation details or null if not found
     */
    public function getDelegation(int $installationId): ?array {
        $installation = $this->installationRepository->findWithDetails($installationId);
        
        if (!$installation) {
            return null;
        }
        
        return [
            'installation_id' => $installation['id'],
            'site_id' => $installation['site_id'],
            'site_name' => $installation['site_name'] ?? $installation['atm_id'],
            'contractor_id' => $installation['contractor_id'],
            'contractor_name' => $installation['contractor_name'] ?? null,
            'delegated_by' => $installation['delegated_by'],
            'delegated_by_name' => $installation['delegated_by_name'] ?? null,
            'delegated_at' => $installation['delegated_at'],
            'status' => $installation['status']
        ];
    }
    
    /**
     * Get all delegations for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array List of delegated installations
     * 
     * Requirements: 2.1
     */
    public function getDelegationsByContractor(int $contractorId): array {
        return $this->installationRepository->findByContractor($contractorId);
    }
    
    /**
     * Get available contractors for delegation
     * Returns list of active contractor companies
     * 
     * @return array List of contractor companies
     * 
     * Requirements: 1.3
     */
    public function getAvailableContractors(): array {
        try {
            // Get all active contractor companies
            // Note: Using contact_email and contact_phone as per database schema
            $sql = "SELECT id, name, contact_email as email, contact_phone as phone, address, status 
                    FROM companies 
                    WHERE type = 'CONTRACTOR' AND status = 'ACTIVE'
                    ORDER BY name ASC";
            
            $result = $this->db->getResults($sql);
            
            return $result ?: [];
        } catch (Exception $e) {
            error_log("Failed to get available contractors: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if delegation is allowed for a site/feasibility
     * 
     * @param int $siteId Site ID
     * @param int $feasibilityId Feasibility check ID
     * @return array Result with 'canDelegate', 'reason', and 'code'
     * 
     * Requirements: 1.6, 1.7
     */
    public function canDelegate(int $siteId, int $feasibilityId): array {
        // Check if installation already exists (Requirement 1.7)
        $existingInstallation = $this->installationRepository->findBySiteId($siteId);
        if ($existingInstallation) {
            return [
                'canDelegate' => false,
                'reason' => 'An installation already exists for this site',
                'code' => 'INSTALLATION_EXISTS'
            ];
        }
        
        // Check feasibility approval status (Requirement 1.6)
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'canDelegate' => false,
                'reason' => 'Feasibility check not found',
                'code' => 'FEASIBILITY_NOT_FOUND'
            ];
        }
        
        $approvalStatus = $feasibility['approval_status'] ?? null;
        if ($approvalStatus !== 'adv_approved') {
            return [
                'canDelegate' => false,
                'reason' => 'Installation can only be delegated for ADV-approved feasibility checks',
                'code' => 'FEASIBILITY_NOT_APPROVED'
            ];
        }
        
        return [
            'canDelegate' => true,
            'reason' => null,
            'code' => null
        ];
    }
    
    /**
     * Validate contractor for delegation
     * 
     * @param int $contractorId Contractor company ID
     * @return array Validation result with 'isValid', 'message', and 'code'
     * 
     * Requirements: 1.3
     */
    public function validateDelegation(int $contractorId): array {
        try {
            // Check if contractor exists
            $sql = "SELECT id, name, type, status FROM companies WHERE id = ?";
            $result = $this->db->getResults($sql, [$contractorId], 'i');
            
            if (empty($result)) {
                return [
                    'isValid' => false,
                    'message' => 'Contractor not found',
                    'code' => 'INVALID_CONTRACTOR'
                ];
            }
            
            $contractor = $result[0];
            
            // Check if it's a contractor type
            if ($contractor['type'] !== 'CONTRACTOR') {
                return [
                    'isValid' => false,
                    'message' => 'Selected company is not a contractor',
                    'code' => 'NOT_A_CONTRACTOR'
                ];
            }
            
            // Check if contractor is active
            if ($contractor['status'] !== 'ACTIVE') {
                return [
                    'isValid' => false,
                    'message' => 'Contractor is not active',
                    'code' => 'CONTRACTOR_INACTIVE'
                ];
            }
            
            return [
                'isValid' => true,
                'message' => 'Valid contractor',
                'code' => null,
                'contractor' => $contractor
            ];
        } catch (Exception $e) {
            return [
                'isValid' => false,
                'message' => 'Failed to validate contractor: ' . $e->getMessage(),
                'code' => 'VALIDATION_ERROR'
            ];
        }
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
            error_log("Failed to log installation delegation action: " . $e->getMessage());
        }
    }
}
