<?php
/**
 * Installation Review Service
 * Handles section-wise approval/rejection workflow for installation reviews
 * 
 * Requirements: 12.1-12.7, 13.1-13.6, 14.3-14.5
 * - 12.1: Display review panel with approve/reject options for each section
 * - 12.2: Record approval with reviewer ID, timestamp, and optional remarks
 * - 12.3: Require rejection reason (minimum 10 characters)
 * - 12.4: Update section status to "rejected" and notify engineer
 * - 12.5: Update installation status to "contractor_approved" when all sections approved
 * - 12.6: Update installation status to "contractor_rejected" when any section rejected
 * - 12.7: Make installation available for ADV review when contractor approves all sections
 * - 13.1: Display final approval panel for ADV users
 * - 13.2: Display previous contractor review comments
 * - 13.3: Update installation status to "adv_approved" when ADV approves all sections
 * - 13.4: Require rejection reason and update status to "adv_rejected"
 * - 13.5: Notify contractor and engineer on ADV rejection
 * - 13.6: Prevent modifications to ADV-approved installations
 * - 14.3: Allow modification only of rejected sections
 * - 14.4: Reset section approval status to "pending" on resubmission
 * - 14.5: Update installation status to "pending_contractor_review" on resubmission
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/InstallationCheckpointRepository.php';
require_once __DIR__ . '/../repositories/InstallationSectionRemarkRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/InstallationCheckpoint.php';
require_once __DIR__ . '/../models/InstallationSectionRemark.php';
require_once __DIR__ . '/../config/InstallationSections.php';
require_once __DIR__ . '/InstallationNotificationService.php';

class InstallationReviewService {
    private $db;
    private $installationRepository;
    private $checkpointRepository;
    private $remarkRepository;
    private $notificationService;
    
    // Minimum rejection reason length (Requirements 12.3)
    const MIN_REJECTION_REASON_LENGTH = 10;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationRepository = new InstallationRepository();
        $this->checkpointRepository = new InstallationCheckpointRepository();
        $this->remarkRepository = new InstallationSectionRemarkRepository();
        $this->notificationService = new InstallationNotificationService();
    }

    
    /**
     * Approve a section of an installation
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param int $reviewerId Reviewer user ID
     * @param string|null $remarks Optional approval remarks
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return array Result with success status and data/errors
     * 
     * Requirements: 12.2, 13.3
     */
    public function approveSection(
        int $installationId, 
        string $section, 
        int $reviewerId, 
        ?string $remarks = null,
        string $level = InstallationCheckpoint::LEVEL_CONTRACTOR
    ): array {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            return [
                'success' => false,
                'message' => 'Invalid section identifier',
                'code' => 'INVALID_SECTION'
            ];
        }
        
        // Validate reviewer level
        if (!InstallationCheckpoint::isValidReviewerLevel($level)) {
            return [
                'success' => false,
                'message' => 'Invalid reviewer level',
                'code' => 'INVALID_REVIEWER_LEVEL'
            ];
        }
        
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is ADV-approved (immutable) - Requirements 13.6
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        // Validate installation status for review
        $validationResult = $this->validateInstallationForReview($installation, $level);
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message'],
                'code' => $validationResult['code']
            ];
        }
        
        try {
            // Update checkpoint status
            $checkpoint = $this->checkpointRepository->updateSectionStatus(
                $installationId,
                $section,
                $level,
                InstallationCheckpoint::STATUS_APPROVED,
                $reviewerId
            );
            
            // Create remark record (Requirements 12.2)
            $remarkData = [
                'installation_id' => $installationId,
                'section' => $section,
                'reviewer_id' => $reviewerId,
                'reviewer_level' => $level,
                'review_type' => InstallationSectionRemark::TYPE_APPROVAL,
                'remark' => $remarks
            ];
            $remark = $this->remarkRepository->create($remarkData);
            
            // Check if all sections are approved and update overall status
            $this->updateOverallStatusAfterApproval($installationId, $level);
            
            // Log audit
            $this->logAction($reviewerId, $installationId, 'section_approved', [
                'section' => $section,
                'level' => $level
            ]);
            
            return [
                'success' => true,
                'message' => 'Section approved successfully',
                'data' => [
                    'checkpoint' => $checkpoint,
                    'remark' => $remark
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to approve section: ' . $e->getMessage(),
                'code' => 'APPROVAL_ERROR'
            ];
        }
    }
    
    /**
     * Reject a section of an installation
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param int $reviewerId Reviewer user ID
     * @param string $reason Rejection reason (minimum 10 characters)
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return array Result with success status and data/errors
     * 
     * Requirements: 12.3, 12.4, 12.6, 13.4, 13.5
     */
    public function rejectSection(
        int $installationId, 
        string $section, 
        int $reviewerId, 
        string $reason,
        string $level = InstallationCheckpoint::LEVEL_CONTRACTOR
    ): array {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            return [
                'success' => false,
                'message' => 'Invalid section identifier',
                'code' => 'INVALID_SECTION'
            ];
        }
        
        // Validate reviewer level
        if (!InstallationCheckpoint::isValidReviewerLevel($level)) {
            return [
                'success' => false,
                'message' => 'Invalid reviewer level',
                'code' => 'INVALID_REVIEWER_LEVEL'
            ];
        }
        
        // Validate rejection reason length (Requirements 12.3)
        if (strlen(trim($reason)) < self::MIN_REJECTION_REASON_LENGTH) {
            return [
                'success' => false,
                'message' => 'Rejection reason must be at least ' . self::MIN_REJECTION_REASON_LENGTH . ' characters',
                'code' => 'REASON_TOO_SHORT'
            ];
        }
        
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is ADV-approved (immutable) - Requirements 13.6
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        // Validate installation status for review
        $validationResult = $this->validateInstallationForReview($installation, $level);
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message'],
                'code' => $validationResult['code']
            ];
        }
        
        try {
            // Update checkpoint status (Requirements 12.4)
            $checkpoint = $this->checkpointRepository->updateSectionStatus(
                $installationId,
                $section,
                $level,
                InstallationCheckpoint::STATUS_REJECTED,
                $reviewerId
            );
            
            // Create remark record with rejection reason (Requirements 12.3)
            $remarkData = [
                'installation_id' => $installationId,
                'section' => $section,
                'reviewer_id' => $reviewerId,
                'reviewer_level' => $level,
                'review_type' => InstallationSectionRemark::TYPE_REJECTION,
                'remark' => $reason
            ];
            $remark = $this->remarkRepository->create($remarkData);
            
            // Update overall installation status (Requirements 12.6, 13.4)
            $newStatus = $level === InstallationCheckpoint::LEVEL_CONTRACTOR
                ? Installation::STATUS_CONTRACTOR_REJECTED
                : Installation::STATUS_ADV_REJECTED;
            $this->installationRepository->updateStatus($installationId, $newStatus);
            
            // Log audit
            $this->logAction($reviewerId, $installationId, 'section_rejected', [
                'section' => $section,
                'level' => $level,
                'reason' => $reason
            ]);
            
            // Send notifications (Requirements 12.4, 13.5)
            if ($level === InstallationCheckpoint::LEVEL_ADV) {
                // ADV rejection - notify both contractor and engineer (Requirement 13.5)
                $this->notificationService->notifyAdvRejection($installationId, $section, $reason);
            } else {
                // Contractor rejection - notify engineer (Requirement 12.4)
                $this->notificationService->notifySectionRejected($installationId, $section, $reason, $level);
            }
            
            return [
                'success' => true,
                'message' => 'Section rejected successfully',
                'data' => [
                    'checkpoint' => $checkpoint,
                    'remark' => $remark,
                    'installation_status' => $newStatus
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to reject section: ' . $e->getMessage(),
                'code' => 'REJECTION_ERROR'
            ];
        }
    }

    
    /**
     * Approve all sections at once
     * 
     * @param int $installationId Installation ID
     * @param int $reviewerId Reviewer user ID
     * @param string|null $remarks Optional approval remarks
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return array Result with success status and data/errors
     * 
     * Requirements: 12.5, 12.7, 13.3
     */
    public function approveAllSections(
        int $installationId, 
        int $reviewerId, 
        ?string $remarks = null,
        string $level = InstallationCheckpoint::LEVEL_CONTRACTOR
    ): array {
        // Validate reviewer level
        if (!InstallationCheckpoint::isValidReviewerLevel($level)) {
            return [
                'success' => false,
                'message' => 'Invalid reviewer level',
                'code' => 'INVALID_REVIEWER_LEVEL'
            ];
        }
        
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is ADV-approved (immutable) - Requirements 13.6
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        // Validate installation status for review
        $validationResult = $this->validateInstallationForReview($installation, $level);
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message'],
                'code' => $validationResult['code']
            ];
        }
        
        try {
            // Approve all sections at checkpoint level
            $this->checkpointRepository->approveAllSections($installationId, $level, $reviewerId);
            
            // Create remark records for all sections
            $sections = InstallationSections::getAll();
            foreach ($sections as $section) {
                $remarkData = [
                    'installation_id' => $installationId,
                    'section' => $section,
                    'reviewer_id' => $reviewerId,
                    'reviewer_level' => $level,
                    'review_type' => InstallationSectionRemark::TYPE_APPROVAL,
                    'remark' => $remarks
                ];
                $this->remarkRepository->create($remarkData);
            }
            
            // Update overall installation status (Requirements 12.5, 12.7, 13.3)
            $newStatus = $level === InstallationCheckpoint::LEVEL_CONTRACTOR
                ? Installation::STATUS_CONTRACTOR_APPROVED
                : Installation::STATUS_ADV_APPROVED;
            $this->installationRepository->updateStatus($installationId, $newStatus);
            
            // Log audit
            $this->logAction($reviewerId, $installationId, 'all_sections_approved', [
                'level' => $level
            ]);
            
            return [
                'success' => true,
                'message' => 'All sections approved successfully',
                'data' => [
                    'installation_status' => $newStatus
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to approve all sections: ' . $e->getMessage(),
                'code' => 'APPROVAL_ERROR'
            ];
        }
    }
    
    /**
     * Get section status for a specific installation and section
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @return array|null Section status or null if not found
     * 
     * Requirements: 12.2
     */
    public function getSectionStatus(int $installationId, string $section): ?array {
        if (!InstallationSections::isValid($section)) {
            return null;
        }
        
        return $this->checkpointRepository->getSectionStatus($installationId, $section);
    }
    
    /**
     * Get all section statuses for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of checkpoint records indexed by section
     * 
     * Requirements: 12.5, 13.3
     */
    public function getAllSectionStatuses(int $installationId): array {
        return $this->checkpointRepository->getAllSectionStatuses($installationId);
    }
    
    /**
     * Get review history for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of remark records
     * 
     * Requirements: 13.2
     */
    public function getReviewHistory(int $installationId): array {
        return $this->remarkRepository->findByInstallationId($installationId);
    }
    
    /**
     * Get review history for a specific section
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @return array Array of remark records
     */
    public function getSectionReviewHistory(int $installationId, string $section): array {
        if (!InstallationSections::isValid($section)) {
            return [];
        }
        
        return $this->remarkRepository->findBySectionAndInstallation($installationId, $section);
    }
    
    /**
     * Check if all sections are approved at a given level
     * 
     * @param int $installationId Installation ID
     * @param string $level Reviewer level ('contractor' or 'adv')
     * @return bool True if all sections are approved
     * 
     * Requirements: 12.5, 13.3
     */
    public function areAllSectionsApproved(int $installationId, string $level): bool {
        if ($level === InstallationCheckpoint::LEVEL_CONTRACTOR) {
            return $this->checkpointRepository->areAllSectionsContractorApproved($installationId);
        } else {
            return $this->checkpointRepository->areAllSectionsAdvApproved($installationId);
        }
    }
    
    /**
     * Get rejected sections for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of rejected section identifiers
     * 
     * Requirements: 14.1, 14.3
     */
    public function getRejectedSections(int $installationId): array {
        $contractorRejected = $this->checkpointRepository->getContractorRejectedSections($installationId);
        $advRejected = $this->checkpointRepository->getAdvRejectedSections($installationId);
        
        $rejectedSections = [];
        
        foreach ($contractorRejected as $checkpoint) {
            $rejectedSections[] = [
                'section' => $checkpoint['section'],
                'level' => InstallationCheckpoint::LEVEL_CONTRACTOR,
                'reviewer_id' => $checkpoint['contractor_reviewer_id'],
                'reviewed_at' => $checkpoint['contractor_reviewed_at']
            ];
        }
        
        foreach ($advRejected as $checkpoint) {
            $rejectedSections[] = [
                'section' => $checkpoint['section'],
                'level' => InstallationCheckpoint::LEVEL_ADV,
                'reviewer_id' => $checkpoint['adv_reviewer_id'],
                'reviewed_at' => $checkpoint['adv_reviewed_at']
            ];
        }
        
        return $rejectedSections;
    }
    
    /**
     * Get editable sections (rejected sections that can be modified)
     * 
     * @param int $installationId Installation ID
     * @return array Array of section identifiers that are editable
     * 
     * Requirements: 14.3
     */
    public function getEditableSections(int $installationId): array {
        return $this->checkpointRepository->getEditableSections($installationId);
    }

    
    /**
     * Resubmit a rejected section
     * 
     * @param int $installationId Installation ID
     * @param string $section Section identifier
     * @param array $data Updated section data
     * @param int $engineerId Engineer user ID
     * @return array Result with success status and data/errors
     * 
     * Requirements: 14.4, 14.5
     */
    public function resubmitSection(
        int $installationId, 
        string $section, 
        array $data, 
        int $engineerId
    ): array {
        // Validate section
        if (!InstallationSections::isValid($section)) {
            return [
                'success' => false,
                'message' => 'Invalid section identifier',
                'code' => 'INVALID_SECTION'
            ];
        }
        
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is ADV-approved (immutable) - Requirements 13.6
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        // Check if section is editable (rejected)
        $editableSections = $this->getEditableSections($installationId);
        if (!in_array($section, $editableSections)) {
            return [
                'success' => false,
                'message' => 'Section is not editable. Only rejected sections can be modified.',
                'code' => 'SECTION_NOT_EDITABLE'
            ];
        }
        
        try {
            // Update section data in installation
            $this->installationRepository->update($installationId, $data);
            
            // Reset section status to pending (Requirements 14.4)
            // Reset both contractor and ADV status
            $this->checkpointRepository->resetSectionStatus(
                $installationId, 
                $section, 
                InstallationCheckpoint::LEVEL_CONTRACTOR
            );
            $this->checkpointRepository->resetSectionStatus(
                $installationId, 
                $section, 
                InstallationCheckpoint::LEVEL_ADV
            );
            
            // Update installation status to pending_contractor_review (Requirements 14.5)
            $this->installationRepository->updateStatus(
                $installationId, 
                Installation::STATUS_PENDING_CONTRACTOR_REVIEW
            );
            
            // Log audit
            $this->logAction($engineerId, $installationId, 'section_resubmitted', [
                'section' => $section
            ]);
            
            return [
                'success' => true,
                'message' => 'Section resubmitted successfully',
                'data' => [
                    'section' => $section,
                    'installation_status' => Installation::STATUS_PENDING_CONTRACTOR_REVIEW
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit section: ' . $e->getMessage(),
                'code' => 'RESUBMIT_ERROR'
            ];
        }
    }
    
    /**
     * Resubmit entire installation (reset all rejected sections)
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID
     * @return array Result with success status and data/errors
     * 
     * Requirements: 14.5
     */
    public function resubmitInstallation(int $installationId, int $engineerId): array {
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is ADV-approved (immutable) - Requirements 13.6
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'success' => false,
                'message' => 'Cannot modify ADV-approved installation',
                'code' => 'INSTALLATION_LOCKED'
            ];
        }
        
        // Check if installation is in a rejected state
        $rejectedStatuses = [
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_REJECTED
        ];
        if (!in_array($installation['status'], $rejectedStatuses)) {
            return [
                'success' => false,
                'message' => 'Installation is not in a rejected state',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        try {
            // Reset all section statuses to pending
            $this->checkpointRepository->resetAllSectionStatuses(
                $installationId, 
                InstallationCheckpoint::LEVEL_CONTRACTOR
            );
            $this->checkpointRepository->resetAllSectionStatuses(
                $installationId, 
                InstallationCheckpoint::LEVEL_ADV
            );
            
            // Update installation status to pending_contractor_review (Requirements 14.5)
            $this->installationRepository->updateStatus(
                $installationId, 
                Installation::STATUS_PENDING_CONTRACTOR_REVIEW
            );
            
            // Log audit
            $this->logAction($engineerId, $installationId, 'installation_resubmitted', []);
            
            return [
                'success' => true,
                'message' => 'Installation resubmitted successfully',
                'data' => [
                    'installation_status' => Installation::STATUS_PENDING_CONTRACTOR_REVIEW
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to resubmit installation: ' . $e->getMessage(),
                'code' => 'RESUBMIT_ERROR'
            ];
        }
    }
    
    /**
     * Check if user can review an installation
     * 
     * @param int $userId User ID
     * @param int $installationId Installation ID
     * @return array Result with 'canReview', 'level', and 'reason'
     * 
     * Requirements: 12.1, 13.1
     */
    public function canUserReview(int $userId, int $installationId): array {
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'canReview' => false,
                'level' => null,
                'reason' => 'Installation not found'
            ];
        }
        
        // Check if installation is ADV-approved (immutable)
        if ($installation['status'] === Installation::STATUS_ADV_APPROVED) {
            return [
                'canReview' => false,
                'level' => null,
                'reason' => 'Installation is already ADV-approved and cannot be modified'
            ];
        }
        
        // Get user role
        $userRole = $this->getUserRole($userId);
        
        // ADV users can review contractor-approved installations (Requirements 13.1)
        if ($userRole === 'adv') {
            if ($installation['status'] === Installation::STATUS_CONTRACTOR_APPROVED) {
                return [
                    'canReview' => true,
                    'level' => InstallationCheckpoint::LEVEL_ADV,
                    'reason' => null
                ];
            }
            return [
                'canReview' => false,
                'level' => null,
                'reason' => 'Installation must be contractor-approved for ADV review'
            ];
        }
        
        // Contractor admin/manager can review submitted installations (Requirements 12.1)
        if (in_array($userRole, ['contractor_admin', 'contractor_manager'])) {
            $reviewableStatuses = [
                Installation::STATUS_SUBMITTED,
                Installation::STATUS_PENDING_CONTRACTOR_REVIEW
            ];
            if (in_array($installation['status'], $reviewableStatuses)) {
                return [
                    'canReview' => true,
                    'level' => InstallationCheckpoint::LEVEL_CONTRACTOR,
                    'reason' => null
                ];
            }
            return [
                'canReview' => false,
                'level' => null,
                'reason' => 'Installation must be submitted for contractor review'
            ];
        }
        
        return [
            'canReview' => false,
            'level' => null,
            'reason' => 'User does not have permission to review installations'
        ];
    }
    
    /**
     * Validate review data structure
     * 
     * @param string $section Section identifier
     * @param array $data Review data
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validateReviewData(string $section, array $data): array {
        $errors = [];
        
        // Validate section
        if (!InstallationSections::isValid($section)) {
            $errors[] = [
                'field' => 'section',
                'message' => 'Invalid section identifier',
                'code' => 'INVALID_SECTION'
            ];
        }
        
        // Validate action
        if (!isset($data['action']) || !in_array($data['action'], ['approve', 'reject'])) {
            $errors[] = [
                'field' => 'action',
                'message' => 'Action must be "approve" or "reject"',
                'code' => 'INVALID_ACTION'
            ];
        }
        
        // Validate rejection reason if action is reject
        if (isset($data['action']) && $data['action'] === 'reject') {
            if (!isset($data['reason']) || strlen(trim($data['reason'])) < self::MIN_REJECTION_REASON_LENGTH) {
                $errors[] = [
                    'field' => 'reason',
                    'message' => 'Rejection reason must be at least ' . self::MIN_REJECTION_REASON_LENGTH . ' characters',
                    'code' => 'REASON_TOO_SHORT'
                ];
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }

    
    /**
     * Check if installation is modifiable (not ADV-approved)
     * 
     * @param int $installationId Installation ID
     * @return bool True if installation can be modified
     * 
     * Requirements: 13.6
     */
    public function isInstallationModifiable(int $installationId): bool {
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return false;
        }
        
        return $installation['status'] !== Installation::STATUS_ADV_APPROVED;
    }
    
    /**
     * Get contractor reviews for ADV display
     * 
     * @param int $installationId Installation ID
     * @return array Array of contractor remarks indexed by section
     * 
     * Requirements: 13.2
     */
    public function getContractorReviewsForAdv(int $installationId): array {
        return $this->remarkRepository->getContractorReviewsForAdv($installationId);
    }
    
    /**
     * Get rejection remarks for an installation
     * 
     * @param int $installationId Installation ID
     * @return array Array of rejection remark records
     * 
     * Requirements: 14.1
     */
    public function getRejectionRemarks(int $installationId): array {
        return $this->remarkRepository->getRejectionRemarks($installationId);
    }
    
    /**
     * Initialize checkpoints for an installation
     * Creates checkpoint records for all sections if they don't exist
     * 
     * @param int $installationId Installation ID
     * @return array Array of checkpoint records
     */
    public function initializeCheckpoints(int $installationId): array {
        return $this->checkpointRepository->createForInstallation($installationId);
    }
    
    /**
     * Validate installation status for review
     * 
     * @param array $installation Installation record
     * @param string $level Reviewer level
     * @return array Validation result with 'valid', 'message', and 'code'
     */
    private function validateInstallationForReview(array $installation, string $level): array {
        $status = $installation['status'];
        
        if ($level === InstallationCheckpoint::LEVEL_CONTRACTOR) {
            // Contractor can review submitted or pending_contractor_review installations
            $validStatuses = [
                Installation::STATUS_SUBMITTED,
                Installation::STATUS_PENDING_CONTRACTOR_REVIEW
            ];
            if (!in_array($status, $validStatuses)) {
                return [
                    'valid' => false,
                    'message' => 'Installation must be submitted for contractor review',
                    'code' => 'INVALID_STATUS_FOR_REVIEW'
                ];
            }
        } else {
            // ADV can review contractor-approved installations
            if ($status !== Installation::STATUS_CONTRACTOR_APPROVED) {
                return [
                    'valid' => false,
                    'message' => 'Installation must be contractor-approved for ADV review',
                    'code' => 'INVALID_STATUS_FOR_REVIEW'
                ];
            }
        }
        
        return ['valid' => true, 'message' => null, 'code' => null];
    }
    
    /**
     * Update overall installation status after section approval
     * 
     * @param int $installationId Installation ID
     * @param string $level Reviewer level
     */
    private function updateOverallStatusAfterApproval(int $installationId, string $level): void {
        if ($level === InstallationCheckpoint::LEVEL_CONTRACTOR) {
            // Check if all sections are contractor-approved
            if ($this->checkpointRepository->areAllSectionsContractorApproved($installationId)) {
                // Update to contractor_approved (Requirements 12.5, 12.7)
                $this->installationRepository->updateStatus(
                    $installationId, 
                    Installation::STATUS_CONTRACTOR_APPROVED
                );
            }
        } else {
            // Check if all sections are ADV-approved
            if ($this->checkpointRepository->areAllSectionsAdvApproved($installationId)) {
                // Update to adv_approved (Requirements 13.3)
                $this->installationRepository->updateStatus(
                    $installationId, 
                    Installation::STATUS_ADV_APPROVED
                );
            }
        }
    }
    
    /**
     * Get user role
     * 
     * @param int $userId User ID
     * @return string|null User role or null
     */
    private function getUserRole(int $userId): ?string {
        $sql = "SELECT r.name as role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?";
        $result = $this->db->getResults($sql, [$userId], 'i');
        
        if (empty($result)) {
            return null;
        }
        
        return $result[0]['role_name'] ?? null;
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
            $details['entity_type'] = 'installation_review';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log installation review action: " . $e->getMessage());
        }
    }
}
