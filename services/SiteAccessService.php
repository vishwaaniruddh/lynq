<?php
/**
 * Site Access Service
 * Handles access control for site management operations
 * 
 * Requirements: 4.5, 5.5, 6.3
 * - 4.5: Deny access to sites not delegated to contractor's company
 * - 5.5: Only allow assignment of sites accepted by contractor
 * - 6.3: Deny access to sites not assigned to engineer
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/DelegationRepository.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class SiteAccessService {
    private $db;
    private $siteRepository;
    private $delegationRepository;
    private $assignmentRepository;
    private $userRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->siteRepository = new SiteRepository();
        $this->delegationRepository = new DelegationRepository();
        $this->assignmentRepository = new EngineerAssignmentRepository();
        $this->userRepository = new UserRepository();
    }
    
    /**
     * Check if an ADV user can access a site
     * ADV users can only access sites owned by their company
     * 
     * @param int $userId User ID
     * @param int $siteId Site ID
     * @return bool True if user can access the site
     * 
     * Requirements: 4.5
     */
    public function canAccessSite(int $userId, int $siteId): bool {
        // Get user's company (use find method from BaseRepository)
        $user = $this->userRepository->withoutCompanyFilter()->find($userId);
        if (!$user) {
            return false;
        }
        
        $userCompanyId = (int)($user['company_id'] ?? 0);
        if ($userCompanyId === 0) {
            return false;
        }
        
        // Get site
        $site = $this->siteRepository->findById($siteId);
        if (!$site) {
            return false;
        }
        
        // ADV users can access sites owned by their company
        return (int)$site['company_id'] === $userCompanyId;
    }
    
    /**
     * Check if a contractor user can access a delegation
     * Contractors can only access delegations assigned to their company
     * 
     * @param int $userId User ID
     * @param int $delegationId Delegation ID
     * @return bool True if user can access the delegation
     * 
     * Requirements: 4.1, 4.5
     */
    public function canAccessDelegation(int $userId, int $delegationId): bool {
        // Get user's company (use find method from BaseRepository)
        $user = $this->userRepository->withoutCompanyFilter()->find($userId);
        if (!$user) {
            return false;
        }
        
        $userCompanyId = (int)($user['company_id'] ?? 0);
        if ($userCompanyId === 0) {
            return false;
        }
        
        // Get delegation
        $delegation = $this->delegationRepository->findById($delegationId);
        if (!$delegation) {
            return false;
        }
        
        // Contractor users can access delegations assigned to their company
        return (int)$delegation['contractor_id'] === $userCompanyId;
    }
    
    /**
     * Check if an engineer can access an assignment
     * Engineers can only access assignments assigned to them
     * 
     * @param int $userId User ID (engineer)
     * @param int $assignmentId Assignment ID
     * @return bool True if engineer can access the assignment
     * 
     * Requirements: 6.1, 6.3
     */
    public function canAccessAssignment(int $userId, int $assignmentId): bool {
        return $this->assignmentRepository->canEngineerAccess($assignmentId, $userId);
    }
    
    /**
     * Check if a contractor user can access a site through delegation
     * Contractors can only access sites that have been delegated to their company
     * 
     * @param int $userId User ID
     * @param int $siteId Site ID
     * @return bool True if contractor can access the site
     * 
     * Requirements: 4.1, 4.5
     */
    public function canContractorAccessSite(int $userId, int $siteId): bool {
        // Get user's company (use find method from BaseRepository)
        $user = $this->userRepository->withoutCompanyFilter()->find($userId);
        if (!$user) {
            return false;
        }
        
        $userCompanyId = (int)($user['company_id'] ?? 0);
        if ($userCompanyId === 0) {
            return false;
        }
        
        // Check if there's any delegation (pending or accepted) for this site to the contractor's company
        return $this->delegationRepository->checkDuplicateDelegation($siteId, $userCompanyId);
    }
    
    /**
     * Check if an engineer can access a site through assignment
     * Engineers can only access sites that have been assigned to them
     * 
     * @param int $userId User ID (engineer)
     * @param int $siteId Site ID
     * @return bool True if engineer can access the site
     * 
     * Requirements: 6.1, 6.3
     */
    public function canEngineerAccessSite(int $userId, int $siteId): bool {
        // Get assignments for this site
        $assignments = $this->assignmentRepository->findBySite($siteId);
        
        // Check if any assignment belongs to this engineer
        foreach ($assignments as $assignment) {
            if ((int)$assignment['engineer_id'] === $userId) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all sites accessible by a contractor user
     * Returns only sites delegated to the contractor's company
     * 
     * @param int $userId User ID
     * @param array $filters Optional filters: status, page, limit
     * @return array Array of accessible sites with pagination
     * 
     * Requirements: 4.1
     */
    public function getAccessibleSitesForContractor(int $userId, array $filters = []): array {
        // Get user's company (use find method from BaseRepository)
        $user = $this->userRepository->withoutCompanyFilter()->find($userId);
        if (!$user) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => 10, 'totalPages' => 0];
        }
        
        $userCompanyId = (int)($user['company_id'] ?? 0);
        if ($userCompanyId === 0) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => 10, 'totalPages' => 0];
        }
        
        // Get delegations for this contractor
        return $this->delegationRepository->findByContractor($userCompanyId, $filters);
    }
    
    /**
     * Get all sites accessible by an engineer
     * Returns only sites assigned to the engineer
     * 
     * @param int $userId User ID (engineer)
     * @param array $filters Optional filters: status, city, state, page, limit
     * @return array Array of accessible sites with pagination
     * 
     * Requirements: 6.1
     */
    public function getAccessibleSitesForEngineer(int $userId, array $filters = []): array {
        return $this->assignmentRepository->findByEngineer($userId, $filters);
    }
    
    /**
     * Validate that a contractor can perform operations on a delegation
     * 
     * @param int $userId User ID
     * @param int $delegationId Delegation ID
     * @return array Result with 'success' and 'message'
     * 
     * Requirements: 4.5
     */
    public function validateContractorDelegationAccess(int $userId, int $delegationId): array {
        if (!$this->canAccessDelegation($userId, $delegationId)) {
            return [
                'success' => false,
                'message' => 'Access denied: This delegation does not belong to your company',
                'code' => 'AUTHORIZATION_ERROR'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Validate that an engineer can perform operations on an assignment
     * 
     * @param int $userId User ID (engineer)
     * @param int $assignmentId Assignment ID
     * @return array Result with 'success' and 'message'
     * 
     * Requirements: 6.3
     */
    public function validateEngineerAssignmentAccess(int $userId, int $assignmentId): array {
        if (!$this->canAccessAssignment($userId, $assignmentId)) {
            return [
                'success' => false,
                'message' => 'Access denied: This assignment is not assigned to you',
                'code' => 'AUTHORIZATION_ERROR'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Validate that an ADV user can perform operations on a site
     * 
     * @param int $userId User ID
     * @param int $siteId Site ID
     * @return array Result with 'success' and 'message'
     * 
     * Requirements: 4.5
     */
    public function validateAdvSiteAccess(int $userId, int $siteId): array {
        if (!$this->canAccessSite($userId, $siteId)) {
            return [
                'success' => false,
                'message' => 'Access denied: This site does not belong to your company',
                'code' => 'AUTHORIZATION_ERROR'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Get user's company ID
     * 
     * @param int $userId User ID
     * @return int|null Company ID or null if not found
     */
    public function getUserCompanyId(int $userId): ?int {
        $user = $this->userRepository->withoutCompanyFilter()->find($userId);
        if (!$user) {
            return null;
        }
        
        $companyId = (int)($user['company_id'] ?? 0);
        return $companyId > 0 ? $companyId : null;
    }
    
    /**
     * Check if user belongs to a specific company
     * 
     * @param int $userId User ID
     * @param int $companyId Company ID
     * @return bool True if user belongs to the company
     */
    public function userBelongsToCompany(int $userId, int $companyId): bool {
        $userCompanyId = $this->getUserCompanyId($userId);
        return $userCompanyId !== null && $userCompanyId === $companyId;
    }
}
