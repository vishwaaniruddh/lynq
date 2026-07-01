<?php
/**
 * Feasibility Review Service
 * Handles business logic for feasibility check approval/rejection workflow
 * 
 * Requirements: 10.2, 10.3, 10.5, 10.6, 10.7, 11.3, 11.4, 11.5, 12.3, 12.4
 * - 10.2: Record approval with reviewer ID, timestamp, and optional comments
 * - 10.3: Require rejection type selection (overall or section-specific)
 * - 10.5: Require reason text (minimum 10 characters) for rejections
 * - 10.6: Update status to contractor_rejected on rejection
 * - 10.7: Update status to contractor_approved on approval
 * - 11.3: Update status to adv_approved on ADV final approval
 * - 11.4: Allow overall or section-specific rejection with required reason
 * - 11.5: Update status to adv_rejected on ADV rejection
 * - 12.3: Allow modification only of rejected sections
 * - 12.4: Reset approval_status to pending_contractor_review on resubmission
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/FeasibilityReviewRepository.php';
require_once __DIR__ . '/../repositories/FeasibilityCheckRepository.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';

class FeasibilityReviewService {
    private $db;
    private $reviewRepository;
    private $feasibilityRepository;
    private $assignmentRepository;
    
    // Valid feasibility sections
    const VALID_SECTIONS = [
        'atm_information',
        'network_information',
        'power_infrastructure',
        'electrical_measurements',
        'site_access',
        'environmental_factors',
        'remarks'
    ];
    
    // Section field mappings for editable sections
    const SECTION_FIELDS = [
        'atm_information' => [
            'no_of_atm', 'atm_id_1', 'atm_id_2', 'atm_id_3',
            'atm_1_status', 'atm_2_status', 'atm_3_status'
        ],
        'network_information' => [
            'operator', 'signal_status', 'operator_2', 'signal_status_2',
            'backroom_network_remark', 'backroom_network_snap'
        ],
        'power_infrastructure' => [
            'ups_available', 'no_of_ups', 'ups_battery_backup',
            'ups_working_1', 'ups_working_2', 'ups_working_3',
            'power_socket_availability', 'power_socket_availability_ups',
            'ups_available_snap', 'no_of_ups_snap', 'ups_working_snap',
            'power_socket_availability_snap'
        ],
        'electrical_measurements' => [
            'earthing', 'earthing_voltage',
            'power_fluctuation_en', 'power_fluctuation_pe', 'power_fluctuation_pn',
            'frequent_power_cut', 'frequent_power_cut_from', 'frequent_power_cut_to',
            'frequent_power_cut_remark', 'earthing_snap', 'power_fluctuation_snap'
        ],
        'site_access' => [
            'em_lock_available', 'em_lock_password', 'password_received',
            'backroom_key_name', 'backroom_key_number', 'backroom_key_status'
        ],
        'environmental_factors' => [
            'antenna_routing_detail', 'router_antenna_position', 'router_position',
            'nearest_shop_name', 'nearest_shop_number', 'nearest_shop_distance',
            'backroom_disturbing_material', 'backroom_disturbing_material_remark',
            'router_antenna_snap', 'antenna_routing_snap'
        ],
        'remarks' => [
            'remarks', 'remarks_snap'
        ]
    ];
    
    // Minimum rejection reason length
    const MIN_REJECTION_REASON_LENGTH = 10;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->reviewRepository = new FeasibilityReviewRepository();
        $this->feasibilityRepository = new FeasibilityCheckRepository();
        $this->assignmentRepository = new EngineerAssignmentRepository();
    }
    
    /**
     * Approve feasibility check by contractor (admin or manager)
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param int $reviewerId Reviewer user ID
     * @param string|null $comments Optional comments
     * @return array Result with success status and data/errors
     * 
     * Requirements: 10.2, 10.7
     */
    public function approveByContractor(int $feasibilityId, int $reviewerId, ?string $comments = null): array {
        // Verify feasibility check exists
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'success' => false,
                'message' => 'Feasibility check not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify user can review
        $canReview = $this->canUserReview($reviewerId, $feasibilityId);
        if (!$canReview['canReview']) {
            return [
                'success' => false,
                'message' => $canReview['reason'],
                'code' => 'UNAUTHORIZED_REVIEWER'
            ];
        }
        
        // Verify reviewer role is contractor_admin or contractor_manager
        if (!in_array($canReview['reviewerRole'], ['contractor_admin', 'contractor_manager'])) {
            return [
                'success' => false,
                'message' => 'Only contractor admin or manager can approve at this level',
                'code' => 'INVALID_REVIEWER_ROLE'
            ];
        }
        
        // Verify feasibility is in correct status for contractor review
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        if (!in_array($currentStatus, ['pending_contractor_review', 'contractor_rejected'])) {
            return [
                'success' => false,
                'message' => 'Feasibility check is not pending contractor review',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            // Create review record (Requirement 10.2)
            $reviewData = [
                'feasibility_id' => $feasibilityId,
                'reviewer_id' => $reviewerId,
                'reviewer_role' => $canReview['reviewerRole'],
                'review_type' => 'approval',
                'comments' => $comments
            ];
            
            $review = $this->reviewRepository->create($reviewData);
            
            // Update feasibility approval_status to contractor_approved (Requirement 10.7)
            $this->updateFeasibilityApprovalStatus($feasibilityId, 'contractor_approved');
            
            // Update assignment feasibility_status
            $this->updateAssignmentFeasibilityStatus($feasibility['assignment_id'], 'contractor_approved');
            
            return [
                'success' => true,
                'message' => 'Feasibility check approved by contractor',
                'data' => $review
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to approve feasibility check: ' . $e->getMessage(),
                'code' => 'APPROVAL_ERROR'
            ];
        }
    }

    
    /**
     * Reject feasibility check by contractor (admin or manager)
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param int $reviewerId Reviewer user ID
     * @param string $rejectionType Rejection type (overall or section_specific)
     * @param array $rejectedSections Array of rejected section names (for section_specific)
     * @param string $reason Rejection reason (min 10 characters)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 10.3, 10.5, 10.6
     */
    public function rejectByContractor(int $feasibilityId, int $reviewerId, string $rejectionType, array $rejectedSections, string $reason): array {
        // Verify feasibility check exists
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'success' => false,
                'message' => 'Feasibility check not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify user can review
        $canReview = $this->canUserReview($reviewerId, $feasibilityId);
        if (!$canReview['canReview']) {
            return [
                'success' => false,
                'message' => $canReview['reason'],
                'code' => 'UNAUTHORIZED_REVIEWER'
            ];
        }
        
        // Verify reviewer role is contractor_admin or contractor_manager
        if (!in_array($canReview['reviewerRole'], ['contractor_admin', 'contractor_manager'])) {
            return [
                'success' => false,
                'message' => 'Only contractor admin or manager can reject at this level',
                'code' => 'INVALID_REVIEWER_ROLE'
            ];
        }
        
        // Verify feasibility is in correct status for contractor review
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        if (!in_array($currentStatus, ['pending_contractor_review', 'contractor_rejected'])) {
            return [
                'success' => false,
                'message' => 'Feasibility check is not pending contractor review',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        // Validate rejection data (Requirements 10.3, 10.5)
        $validation = $this->validateReviewData([
            'review_type' => 'rejection',
            'rejection_type' => $rejectionType,
            'rejected_sections' => $rejectedSections,
            'reason' => $reason
        ]);
        
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            // Create review record
            $reviewData = [
                'feasibility_id' => $feasibilityId,
                'reviewer_id' => $reviewerId,
                'reviewer_role' => $canReview['reviewerRole'],
                'review_type' => 'rejection',
                'rejection_type' => $rejectionType,
                'rejected_sections' => $rejectionType === 'section_specific' ? $rejectedSections : [],
                'reason' => $reason
            ];
            
            $review = $this->reviewRepository->create($reviewData);
            
            // Update feasibility approval_status to contractor_rejected (Requirement 10.6)
            $this->updateFeasibilityApprovalStatus($feasibilityId, 'contractor_rejected');
            
            // Update assignment feasibility_status
            $this->updateAssignmentFeasibilityStatus($feasibility['assignment_id'], 'contractor_rejected');
            
            return [
                'success' => true,
                'message' => 'Feasibility check rejected by contractor',
                'data' => $review
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reject feasibility check: ' . $e->getMessage(),
                'code' => 'REJECTION_ERROR'
            ];
        }
    }
    
    /**
     * Approve feasibility check by ADV (final approval)
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param int $reviewerId Reviewer user ID
     * @param string|null $comments Optional comments
     * @return array Result with success status and data/errors
     * 
     * Requirements: 11.3
     */
    public function approveByADV(int $feasibilityId, int $reviewerId, ?string $comments = null): array {
        // Verify feasibility check exists
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'success' => false,
                'message' => 'Feasibility check not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify user can review
        $canReview = $this->canUserReview($reviewerId, $feasibilityId);
        if (!$canReview['canReview']) {
            return [
                'success' => false,
                'message' => $canReview['reason'],
                'code' => 'UNAUTHORIZED_REVIEWER'
            ];
        }
        
        // Verify reviewer role is ADV
        if ($canReview['reviewerRole'] !== 'adv') {
            return [
                'success' => false,
                'message' => 'Only ADV users can provide final approval',
                'code' => 'INVALID_REVIEWER_ROLE'
            ];
        }
        
        // Verify feasibility is not already ADV approved/rejected
        // ADV can approve directly from any status (bypassing contractor if needed)
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        if (in_array($currentStatus, ['adv_approved', 'adv_rejected'])) {
            return [
                'success' => false,
                'message' => 'Feasibility check has already been reviewed by ADV',
                'code' => 'ALREADY_REVIEWED'
            ];
        }
        
        try {
            // Create review record
            $reviewData = [
                'feasibility_id' => $feasibilityId,
                'reviewer_id' => $reviewerId,
                'reviewer_role' => 'adv',
                'review_type' => 'approval',
                'comments' => $comments
            ];
            
            $review = $this->reviewRepository->create($reviewData);
            
            // Update feasibility approval_status to adv_approved (Requirement 11.3)
            $this->updateFeasibilityApprovalStatus($feasibilityId, 'adv_approved');
            
            // Update assignment feasibility_status
            $this->updateAssignmentFeasibilityStatus($feasibility['assignment_id'], 'adv_approved');
            
            return [
                'success' => true,
                'message' => 'Feasibility check approved by ADV (final approval)',
                'data' => $review
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to approve feasibility check: ' . $e->getMessage(),
                'code' => 'APPROVAL_ERROR'
            ];
        }
    }
    
    /**
     * Reject feasibility check by ADV
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param int $reviewerId Reviewer user ID
     * @param string $rejectionType Rejection type (overall or section_specific)
     * @param array $rejectedSections Array of rejected section names (for section_specific)
     * @param string $reason Rejection reason (min 10 characters)
     * @return array Result with success status and data/errors
     * 
     * Requirements: 11.4, 11.5
     */
    public function rejectByADV(int $feasibilityId, int $reviewerId, string $rejectionType, array $rejectedSections, string $reason): array {
        // Verify feasibility check exists
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'success' => false,
                'message' => 'Feasibility check not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify user can review
        $canReview = $this->canUserReview($reviewerId, $feasibilityId);
        if (!$canReview['canReview']) {
            return [
                'success' => false,
                'message' => $canReview['reason'],
                'code' => 'UNAUTHORIZED_REVIEWER'
            ];
        }
        
        // Verify reviewer role is ADV
        if ($canReview['reviewerRole'] !== 'adv') {
            return [
                'success' => false,
                'message' => 'Only ADV users can reject at this level',
                'code' => 'INVALID_REVIEWER_ROLE'
            ];
        }
        
        // Verify feasibility is not already ADV approved/rejected
        // ADV can reject directly from any status (bypassing contractor if needed)
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        if (in_array($currentStatus, ['adv_approved', 'adv_rejected'])) {
            return [
                'success' => false,
                'message' => 'Feasibility check has already been reviewed by ADV',
                'code' => 'ALREADY_REVIEWED'
            ];
        }
        
        // Validate rejection data (Requirement 11.4)
        $validation = $this->validateReviewData([
            'review_type' => 'rejection',
            'rejection_type' => $rejectionType,
            'rejected_sections' => $rejectedSections,
            'reason' => $reason
        ]);
        
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            // Create review record
            $reviewData = [
                'feasibility_id' => $feasibilityId,
                'reviewer_id' => $reviewerId,
                'reviewer_role' => 'adv',
                'review_type' => 'rejection',
                'rejection_type' => $rejectionType,
                'rejected_sections' => $rejectionType === 'section_specific' ? $rejectedSections : [],
                'reason' => $reason
            ];
            
            $review = $this->reviewRepository->create($reviewData);
            
            // Update feasibility approval_status to adv_rejected (Requirement 11.5)
            $this->updateFeasibilityApprovalStatus($feasibilityId, 'adv_rejected');
            
            // Update assignment feasibility_status
            $this->updateAssignmentFeasibilityStatus($feasibility['assignment_id'], 'adv_rejected');
            
            return [
                'success' => true,
                'message' => 'Feasibility check rejected by ADV',
                'data' => $review
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reject feasibility check: ' . $e->getMessage(),
                'code' => 'REJECTION_ERROR'
            ];
        }
    }

    
    /**
     * Validate review data
     * 
     * @param array $data Review data to validate
     * @return array Validation result with isValid, message, and errors
     * 
     * Requirements: 10.3, 10.4, 10.5, 11.4
     */
    public function validateReviewData(array $data): array {
        $errors = [];
        
        // Validate review_type
        if (empty($data['review_type'])) {
            $errors[] = [
                'field' => 'review_type',
                'message' => 'Review type is required',
                'code' => 'REQUIRED_FIELD_MISSING'
            ];
        } elseif (!in_array($data['review_type'], ['approval', 'rejection'])) {
            $errors[] = [
                'field' => 'review_type',
                'message' => 'Review type must be approval or rejection',
                'code' => 'INVALID_VALUE'
            ];
        }
        
        // For rejections, validate additional fields (Requirements 10.3, 10.4, 10.5)
        if (isset($data['review_type']) && $data['review_type'] === 'rejection') {
            // Validate rejection_type (Requirement 10.3)
            if (empty($data['rejection_type'])) {
                $errors[] = [
                    'field' => 'rejection_type',
                    'message' => 'Rejection type is required for rejections',
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            } elseif (!in_array($data['rejection_type'], ['overall', 'section_specific'])) {
                $errors[] = [
                    'field' => 'rejection_type',
                    'message' => 'Rejection type must be overall or section_specific',
                    'code' => 'INVALID_REJECTION_TYPE'
                ];
            }
            
            // For section_specific rejection, validate sections (Requirement 10.4)
            if (isset($data['rejection_type']) && $data['rejection_type'] === 'section_specific') {
                if (empty($data['rejected_sections']) || !is_array($data['rejected_sections'])) {
                    $errors[] = [
                        'field' => 'rejected_sections',
                        'message' => 'At least one section must be selected for section-specific rejection',
                        'code' => 'REQUIRED_FIELD_MISSING'
                    ];
                } else {
                    // Validate section names
                    $invalidSections = array_diff($data['rejected_sections'], self::VALID_SECTIONS);
                    if (!empty($invalidSections)) {
                        $errors[] = [
                            'field' => 'rejected_sections',
                            'message' => 'Invalid section names: ' . implode(', ', $invalidSections),
                            'code' => 'INVALID_SECTION_NAME'
                        ];
                    }
                }
            }
            
            // Validate reason (Requirement 10.5)
            if (empty($data['reason'])) {
                $errors[] = [
                    'field' => 'reason',
                    'message' => 'Rejection reason is required',
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            } elseif (mb_strlen(trim($data['reason']), 'UTF-8') < self::MIN_REJECTION_REASON_LENGTH) {
                $errors[] = [
                    'field' => 'reason',
                    'message' => 'Rejection reason must be at least ' . self::MIN_REJECTION_REASON_LENGTH . ' characters',
                    'code' => 'REASON_TOO_SHORT'
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
            'message' => 'Valid review data',
            'errors' => []
        ];
    }
    
    /**
     * Check if a user can review a feasibility check
     * 
     * @param int $userId User ID
     * @param int $feasibilityId Feasibility check ID
     * @return array Result with canReview, reviewerRole, and reason
     * 
     * Requirements: 10.1, 11.1
     */
    public function canUserReview(int $userId, int $feasibilityId): array {
        // Get user details with role information
        $userSql = "SELECT u.id, r.name as role_name, r.company_type as role_company_type, 
                           u.company_id, c.type as company_type 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.id
                    LEFT JOIN companies c ON u.company_id = c.id 
                    WHERE u.id = ? AND u.status = 1";
        $userResult = $this->db->getResults($userSql, [$userId], 'i');
        
        if (empty($userResult)) {
            return [
                'canReview' => false,
                'reviewerRole' => null,
                'reason' => 'User not found or inactive'
            ];
        }
        
        $user = $userResult[0];
        $roleName = strtolower($user['role_name'] ?? '');
        $companyType = strtolower($user['company_type'] ?? '');
        
        // Get feasibility check details
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'canReview' => false,
                'reviewerRole' => null,
                'reason' => 'Feasibility check not found'
            ];
        }
        
        // Determine reviewer role based on user role and company type
        $reviewerRole = null;
        
        // ADV users can review (Super Admin, ADV Admin, ADV Manager)
        if ($companyType === 'adv' && in_array($roleName, ['super admin', 'adv admin', 'adv manager', 'adv user'])) {
            $reviewerRole = 'adv';
        }
        // Contractor admin can review
        elseif ($companyType === 'contractor' && $roleName === 'contractor admin') {
            $reviewerRole = 'contractor_admin';
        }
        // Contractor manager can review
        elseif ($companyType === 'contractor' && $roleName === 'contractor manager') {
            $reviewerRole = 'contractor_manager';
        }
        
        if (!$reviewerRole) {
            return [
                'canReview' => false,
                'reviewerRole' => null,
                'reason' => 'User does not have permission to review feasibility checks'
            ];
        }
        
        // For contractor reviewers, verify they belong to the same contractor
        if (in_array($reviewerRole, ['contractor_admin', 'contractor_manager'])) {
            // Get the contractor ID from the assignment
            $assignmentSql = "SELECT sd.contractor_id 
                              FROM engineer_assignments ea 
                              JOIN site_delegations sd ON ea.delegation_id = sd.id 
                              WHERE ea.id = ?";
            $assignmentResult = $this->db->getResults($assignmentSql, [$feasibility['assignment_id']], 'i');
            
            if (!empty($assignmentResult)) {
                $contractorId = (int)$assignmentResult[0]['contractor_id'];
                if ((int)$user['company_id'] !== $contractorId) {
                    return [
                        'canReview' => false,
                        'reviewerRole' => null,
                        'reason' => 'User can only review feasibility checks from their own contractor'
                    ];
                }
            }
        }
        
        return [
            'canReview' => true,
            'reviewerRole' => $reviewerRole,
            'reason' => null
        ];
    }
    
    /**
     * Get reviews for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array List of reviews
     */
    public function getReviewsByFeasibility(int $feasibilityId): array {
        return $this->reviewRepository->findByFeasibility($feasibilityId);
    }
    
    /**
     * Get the latest review for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Latest review or null
     */
    public function getLatestReview(int $feasibilityId): ?array {
        return $this->reviewRepository->findLatest($feasibilityId);
    }
    
    /**
     * Get review history for a feasibility check
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array Review history
     * 
     * Requirements: 12.5
     */
    public function getReviewHistory(int $feasibilityId): array {
        return $this->reviewRepository->getHistory($feasibilityId);
    }
    
    /**
     * Get editable sections for a rejected feasibility check
     * Returns only the sections that were rejected and can be edited
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array Result with editable sections and fields
     * 
     * Requirements: 12.3
     */
    public function getEditableSections(int $feasibilityId): array {
        // Get feasibility check
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'success' => false,
                'message' => 'Feasibility check not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if feasibility is rejected
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        if (!in_array($currentStatus, ['contractor_rejected', 'adv_rejected'])) {
            return [
                'success' => false,
                'message' => 'Feasibility check is not rejected',
                'code' => 'INVALID_STATUS',
                'editableSections' => [],
                'editableFields' => []
            ];
        }
        
        // Get the latest rejection
        $latestRejection = $this->reviewRepository->getLatestRejection($feasibilityId);
        if (!$latestRejection) {
            return [
                'success' => false,
                'message' => 'No rejection found',
                'code' => 'NO_REJECTION',
                'editableSections' => [],
                'editableFields' => []
            ];
        }
        
        // Determine editable sections
        $editableSections = [];
        $editableFields = [];
        
        if ($latestRejection['rejection_type'] === 'overall') {
            // For overall rejection, all sections are editable
            $editableSections = self::VALID_SECTIONS;
            foreach (self::SECTION_FIELDS as $section => $fields) {
                $editableFields = array_merge($editableFields, $fields);
            }
        } else {
            // For section-specific rejection, only rejected sections are editable
            $rejectedSections = $latestRejection['rejected_sections'] ?? [];
            if (is_string($rejectedSections)) {
                $rejectedSections = json_decode($rejectedSections, true) ?? [];
            }
            
            $editableSections = $rejectedSections;
            foreach ($rejectedSections as $section) {
                if (isset(self::SECTION_FIELDS[$section])) {
                    $editableFields = array_merge($editableFields, self::SECTION_FIELDS[$section]);
                }
            }
        }
        
        return [
            'success' => true,
            'editableSections' => $editableSections,
            'editableFields' => array_unique($editableFields),
            'rejectionType' => $latestRejection['rejection_type'],
            'rejectionReason' => $latestRejection['reason'],
            'rejectedBy' => $latestRejection['reviewer_name'] ?? 'Unknown',
            'rejectedAt' => $latestRejection['reviewed_at']
        ];
    }

    
    /**
     * Resubmit a rejected feasibility check with corrected data
     * Only allows editing of rejected sections
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param array $updatedData Updated feasibility data
     * @param int $engineerId Engineer user ID
     * @return array Result with success status and data/errors
     * 
     * Requirements: 12.3, 12.4
     */
    public function resubmitFeasibility(int $feasibilityId, array $updatedData, int $engineerId): array {
        // Get feasibility check
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'success' => false,
                'message' => 'Feasibility check not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify engineer is the creator
        if ((int)$feasibility['created_by'] !== $engineerId) {
            return [
                'success' => false,
                'message' => 'Only the original engineer can resubmit this feasibility check',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        // Check if feasibility is rejected
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        if (!in_array($currentStatus, ['contractor_rejected', 'adv_rejected'])) {
            return [
                'success' => false,
                'message' => 'Only rejected feasibility checks can be resubmitted',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        // Check if ADV-approved (immutable) - Requirement 11.6
        if ($currentStatus === 'adv_approved') {
            return [
                'success' => false,
                'message' => 'ADV-approved feasibility checks cannot be modified',
                'code' => 'IMMUTABLE'
            ];
        }
        
        // Get editable sections (Requirement 12.3)
        $editableInfo = $this->getEditableSections($feasibilityId);
        if (!$editableInfo['success']) {
            return $editableInfo;
        }
        
        $editableFields = $editableInfo['editableFields'];
        
        // Filter updated data to only include editable fields
        $filteredData = [];
        foreach ($updatedData as $field => $value) {
            if (in_array($field, $editableFields)) {
                $filteredData[$field] = $value;
            }
        }
        
        if (empty($filteredData)) {
            return [
                'success' => false,
                'message' => 'No valid fields to update. Only rejected sections can be modified.',
                'code' => 'NO_VALID_FIELDS'
            ];
        }
        
        try {
            // Update feasibility check with filtered data
            $updated = $this->feasibilityRepository->updateFeasibilityCheck($feasibilityId, $filteredData);
            
            // Reset approval_status to pending_contractor_review (Requirement 12.4)
            $this->updateFeasibilityApprovalStatus($feasibilityId, 'pending_contractor_review');
            
            // Update assignment feasibility_status
            $this->updateAssignmentFeasibilityStatus($feasibility['assignment_id'], 'pending_contractor_review');
            
            return [
                'success' => true,
                'message' => 'Feasibility check resubmitted successfully',
                'data' => $updated
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit feasibility check: ' . $e->getMessage(),
                'code' => 'RESUBMIT_ERROR'
            ];
        }
    }
    
    /**
     * Check if a feasibility check can be modified
     * ADV-approved feasibility checks are immutable
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array Result with canModify and reason
     * 
     * Requirements: 11.6
     */
    public function canModifyFeasibility(int $feasibilityId): array {
        $feasibility = $this->feasibilityRepository->findById($feasibilityId);
        if (!$feasibility) {
            return [
                'canModify' => false,
                'reason' => 'Feasibility check not found'
            ];
        }
        
        $currentStatus = $feasibility['approval_status'] ?? 'pending_contractor_review';
        
        // ADV-approved is immutable (Requirement 11.6)
        if ($currentStatus === 'adv_approved') {
            return [
                'canModify' => false,
                'reason' => 'ADV-approved feasibility checks cannot be modified'
            ];
        }
        
        // Only rejected status allows modification
        if (!in_array($currentStatus, ['contractor_rejected', 'adv_rejected'])) {
            return [
                'canModify' => false,
                'reason' => 'Only rejected feasibility checks can be modified'
            ];
        }
        
        return [
            'canModify' => true,
            'reason' => null
        ];
    }
    
    /**
     * Submit a review (generic method for both approval and rejection)
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param array $reviewData Review data
     * @param int $reviewerId Reviewer user ID
     * @return array Result with success status and data/errors
     */
    public function submitReview(int $feasibilityId, array $reviewData, int $reviewerId): array {
        $reviewType = $reviewData['review_type'] ?? '';
        
        // Determine reviewer role
        $canReview = $this->canUserReview($reviewerId, $feasibilityId);
        if (!$canReview['canReview']) {
            return [
                'success' => false,
                'message' => $canReview['reason'],
                'code' => 'UNAUTHORIZED_REVIEWER'
            ];
        }
        
        $reviewerRole = $canReview['reviewerRole'];
        
        if ($reviewType === 'approval') {
            if ($reviewerRole === 'adv') {
                return $this->approveByADV($feasibilityId, $reviewerId, $reviewData['comments'] ?? null);
            } else {
                return $this->approveByContractor($feasibilityId, $reviewerId, $reviewData['comments'] ?? null);
            }
        } elseif ($reviewType === 'rejection') {
            $rejectionType = $reviewData['rejection_type'] ?? 'overall';
            $rejectedSections = $reviewData['rejected_sections'] ?? [];
            $reason = $reviewData['reason'] ?? '';
            
            if ($reviewerRole === 'adv') {
                return $this->rejectByADV($feasibilityId, $reviewerId, $rejectionType, $rejectedSections, $reason);
            } else {
                return $this->rejectByContractor($feasibilityId, $reviewerId, $rejectionType, $rejectedSections, $reason);
            }
        }
        
        return [
            'success' => false,
            'message' => 'Invalid review type',
            'code' => 'INVALID_REVIEW_TYPE'
        ];
    }
    
    /**
     * Get pending reviews for a contractor
     * 
     * @param int $contractorId Contractor company ID
     * @return array List of feasibility checks pending contractor review
     */
    public function getPendingContractorReviews(int $contractorId): array {
        $sql = "SELECT fc.*, 
                       s.site_name, s.lho, s.city, s.address,
                       CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name,
                       ea.assigned_at
                FROM feasibility_checks fc
                JOIN engineer_assignments ea ON fc.assignment_id = ea.id
                JOIN site_delegations sd ON ea.delegation_id = sd.id
                JOIN sites s ON fc.site_id = s.id
                JOIN users eng ON ea.engineer_id = eng.id
                WHERE sd.contractor_id = ?
                  AND fc.approval_status = 'pending_contractor_review'
                ORDER BY fc.created_at DESC";
        
        return $this->db->getResults($sql, [$contractorId], 'i');
    }
    
    /**
     * Get pending reviews for ADV
     * 
     * @return array List of feasibility checks pending ADV review
     */
    public function getPendingADVReviews(): array {
        $sql = "SELECT fc.*, 
                       s.site_name, s.lho, s.city, s.address,
                       CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name,
                       CONCAT(con.first_name, ' ', con.last_name) as contractor_name,
                       ea.assigned_at,
                       (SELECT reviewed_at FROM feasibility_reviews 
                        WHERE feasibility_id = fc.id AND review_type = 'approval' 
                        ORDER BY reviewed_at DESC LIMIT 1) as contractor_approved_at
                FROM feasibility_checks fc
                JOIN engineer_assignments ea ON fc.assignment_id = ea.id
                JOIN site_delegations sd ON ea.delegation_id = sd.id
                JOIN sites s ON fc.site_id = s.id
                JOIN users eng ON ea.engineer_id = eng.id
                JOIN users con ON sd.contractor_id = con.company_id
                WHERE fc.approval_status = 'contractor_approved'
                ORDER BY fc.created_at DESC";
        
        return $this->db->getResults($sql);
    }
    
    /**
     * Update feasibility approval status
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param string $status New approval status
     * @return bool Success
     */
    private function updateFeasibilityApprovalStatus(int $feasibilityId, string $status): bool {
        $validStatuses = [
            'pending_contractor_review',
            'contractor_approved',
            'contractor_rejected',
            'adv_approved',
            'adv_rejected'
        ];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $sql = "UPDATE feasibility_checks SET approval_status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$status, $feasibilityId], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Update assignment feasibility status
     * 
     * @param int $assignmentId Assignment ID
     * @param string $status New feasibility status
     * @return bool Success
     */
    private function updateAssignmentFeasibilityStatus(int $assignmentId, string $status): bool {
        $sql = "UPDATE engineer_assignments SET feasibility_status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->executeQuery($sql, [$status, $assignmentId], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows > 0;
    }
    
    /**
     * Get valid sections list
     * 
     * @return array List of valid section names
     */
    public static function getValidSections(): array {
        return self::VALID_SECTIONS;
    }
    
    /**
     * Get section fields mapping
     * 
     * @return array Section to fields mapping
     */
    public static function getSectionFields(): array {
        return self::SECTION_FIELDS;
    }
    
    /**
     * Get section label
     * 
     * @param string $section Section name
     * @return string Human-readable section label
     */
    public static function getSectionLabel(string $section): string {
        $labels = [
            'atm_information' => 'ATM Information',
            'network_information' => 'Network Information',
            'power_infrastructure' => 'Power Infrastructure',
            'electrical_measurements' => 'Electrical Measurements',
            'site_access' => 'Site Access',
            'environmental_factors' => 'Environmental Factors',
            'remarks' => 'Remarks'
        ];
        
        return $labels[$section] ?? ucwords(str_replace('_', ' ', $section));
    }
}
