<?php
/**
 * Installation ETA Service
 * Handles ETA and ADA tracking for installation sites
 * 
 * Requirements: 3.2, 3.3, 3.4, 3.5
 * - 3.2: Display ETA input form for pending_eta sites
 * - 3.3: Record ETA date and update status to pending_ada
 * - 3.4: Display ADA input form for pending_ada sites
 * - 3.5: Record ADA date and update status to pending_materials
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/InstallationNotificationService.php';

class InstallationETAService {
    private $db;
    private $installationRepository;
    private $notificationService;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->installationRepository = new InstallationRepository();
        $this->notificationService = new InstallationNotificationService();
    }
    
    /**
     * Submit ETA for an installation
     * Records ETA date and updates status to "pending_ada"
     * 
     * @param int $installationId Installation ID
     * @param string $etaDate ETA date (Y-m-d format)
     * @param int $engineerId Engineer user ID submitting the ETA
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.2, 3.3
     */
    public function submitETA(int $installationId, string $etaDate, int $engineerId): array {
        // Get installation
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if ETA submission is allowed
        $canSubmit = $this->canSubmitETA($installationId, $engineerId);
        if (!$canSubmit['canSubmit']) {
            return [
                'success' => false,
                'message' => $canSubmit['reason'],
                'code' => $canSubmit['code']
            ];
        }
        
        // Validate ETA date
        $validation = $this->validateETA($etaDate);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'code' => $validation['code']
            ];
        }
        
        try {
            // Update installation with ETA data (Requirement 3.3)
            $updateData = [
                'eta_date' => $etaDate,
                'eta_submitted_at' => date('Y-m-d H:i:s'),
                'status' => Installation::STATUS_PENDING_ADA
            ];
            
            $updatedInstallation = $this->installationRepository->update($installationId, $updateData);
            
            // Log audit
            $this->logAction($engineerId, $installationId, 'eta_submitted', [
                'eta_date' => $etaDate
            ]);
            
            return [
                'success' => true,
                'message' => 'ETA submitted successfully',
                'data' => $updatedInstallation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to submit ETA: ' . $e->getMessage(),
                'code' => 'ETA_SUBMISSION_ERROR'
            ];
        }
    }

    
    /**
     * Submit ADA for an installation
     * Records ADA date and updates status to "pending_materials"
     * 
     * @param int $installationId Installation ID
     * @param string $adaDate ADA date (Y-m-d format)
     * @param int $engineerId Engineer user ID submitting the ADA
     * @return array Result with success status and data/errors
     * 
     * Requirements: 3.4, 3.5
     */
    public function submitADA(int $installationId, string $adaDate, int $engineerId): array {
        // Get installation
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if ADA submission is allowed
        $canSubmit = $this->canSubmitADA($installationId, $engineerId);
        if (!$canSubmit['canSubmit']) {
            return [
                'success' => false,
                'message' => $canSubmit['reason'],
                'code' => $canSubmit['code']
            ];
        }
        
        // Validate ADA date
        $etaDate = $installation['eta_date'] ?? null;
        $validation = $this->validateADA($adaDate, $etaDate);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'code' => $validation['code']
            ];
        }
        
        try {
            // Update installation with ADA data (Requirement 3.5)
            $updateData = [
                'ada_date' => $adaDate,
                'ada_submitted_at' => date('Y-m-d H:i:s'),
                'status' => Installation::STATUS_PENDING_MATERIALS
            ];
            
            $updatedInstallation = $this->installationRepository->update($installationId, $updateData);
            
            // Log audit
            $this->logAction($engineerId, $installationId, 'ada_submitted', [
                'ada_date' => $adaDate,
                'eta_date' => $etaDate
            ]);
            
            return [
                'success' => true,
                'message' => 'ADA submitted successfully',
                'data' => $updatedInstallation
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to submit ADA: ' . $e->getMessage(),
                'code' => 'ADA_SUBMISSION_ERROR'
            ];
        }
    }
    
    /**
     * Get ETA for an installation
     * 
     * @param int $installationId Installation ID
     * @return array|null ETA data or null if not found
     */
    public function getETA(int $installationId): ?array {
        $installation = $this->installationRepository->findById($installationId);
        
        if (!$installation || empty($installation['eta_date'])) {
            return null;
        }
        
        return [
            'installation_id' => $installation['id'],
            'eta_date' => $installation['eta_date'],
            'eta_submitted_at' => $installation['eta_submitted_at'],
            'assigned_engineer_id' => $installation['assigned_engineer_id']
        ];
    }
    
    /**
     * Get ADA for an installation
     * 
     * @param int $installationId Installation ID
     * @return array|null ADA data or null if not found
     */
    public function getADA(int $installationId): ?array {
        $installation = $this->installationRepository->findById($installationId);
        
        if (!$installation || empty($installation['ada_date'])) {
            return null;
        }
        
        return [
            'installation_id' => $installation['id'],
            'ada_date' => $installation['ada_date'],
            'ada_submitted_at' => $installation['ada_submitted_at'],
            'assigned_engineer_id' => $installation['assigned_engineer_id']
        ];
    }
    
    /**
     * Get ETA and ADA data for an installation
     * 
     * @param int $installationId Installation ID
     * @return array|null ETA/ADA data or null if not found
     */
    public function getETAADAByInstallation(int $installationId): ?array {
        $installation = $this->installationRepository->findWithDetails($installationId);
        
        if (!$installation) {
            return null;
        }
        
        return [
            'installation_id' => $installation['id'],
            'site_id' => $installation['site_id'],
            'site_name' => $installation['site_name'] ?? $installation['atm_id'],
            'assigned_engineer_id' => $installation['assigned_engineer_id'],
            'assigned_engineer_name' => $installation['assigned_engineer_name'] ?? null,
            'eta_date' => $installation['eta_date'],
            'eta_submitted_at' => $installation['eta_submitted_at'],
            'ada_date' => $installation['ada_date'],
            'ada_submitted_at' => $installation['ada_submitted_at'],
            'status' => $installation['status']
        ];
    }

    
    /**
     * Check if ETA submission is allowed for an installation
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID
     * @return array Result with 'canSubmit', 'reason', and 'code'
     * 
     * Requirements: 3.2
     */
    public function canSubmitETA(int $installationId, int $engineerId): array {
        // Get installation
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'canSubmit' => false,
                'reason' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is in pending_eta status (Requirement 3.2)
        if ($installation['status'] !== Installation::STATUS_PENDING_ETA) {
            return [
                'canSubmit' => false,
                'reason' => 'Installation is not in pending_eta status',
                'code' => 'WRONG_STATUS'
            ];
        }
        
        // Check if engineer is assigned to this installation
        if ((int)$installation['assigned_engineer_id'] !== $engineerId) {
            return [
                'canSubmit' => false,
                'reason' => 'Engineer is not assigned to this installation',
                'code' => 'NOT_ASSIGNED'
            ];
        }
        
        return [
            'canSubmit' => true,
            'reason' => null,
            'code' => null
        ];
    }
    
    /**
     * Check if ADA submission is allowed for an installation
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID
     * @return array Result with 'canSubmit', 'reason', and 'code'
     * 
     * Requirements: 3.4
     */
    public function canSubmitADA(int $installationId, int $engineerId): array {
        // Get installation
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'canSubmit' => false,
                'reason' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation is in pending_ada status (Requirement 3.4)
        if ($installation['status'] !== Installation::STATUS_PENDING_ADA) {
            return [
                'canSubmit' => false,
                'reason' => 'Installation is not in pending_ada status',
                'code' => 'WRONG_STATUS'
            ];
        }
        
        // Check if engineer is assigned to this installation
        if ((int)$installation['assigned_engineer_id'] !== $engineerId) {
            return [
                'canSubmit' => false,
                'reason' => 'Engineer is not assigned to this installation',
                'code' => 'NOT_ASSIGNED'
            ];
        }
        
        return [
            'canSubmit' => true,
            'reason' => null,
            'code' => null
        ];
    }
    
    /**
     * Validate ETA date
     * 
     * @param string $etaDate ETA date (Y-m-d format)
     * @return array Validation result with 'isValid', 'message', and 'code'
     * 
     * Requirements: 3.2
     */
    public function validateETA(string $etaDate): array {
        // Check if date is valid format
        $date = DateTime::createFromFormat('Y-m-d', $etaDate);
        if (!$date || $date->format('Y-m-d') !== $etaDate) {
            return [
                'isValid' => false,
                'message' => 'Invalid date format. Expected Y-m-d',
                'code' => 'INVALID_DATE_FORMAT'
            ];
        }
        
        // Check if date is in the future (or today)
        $today = new DateTime('today');
        if ($date < $today) {
            return [
                'isValid' => false,
                'message' => 'ETA date must be today or in the future',
                'code' => 'DATE_IN_PAST'
            ];
        }
        
        return [
            'isValid' => true,
            'message' => 'Valid ETA date',
            'code' => null
        ];
    }
    
    /**
     * Validate ADA date
     * 
     * @param string $adaDate ADA date (Y-m-d format)
     * @param string|null $etaDate ETA date for comparison
     * @return array Validation result with 'isValid', 'message', and 'code'
     * 
     * Requirements: 3.4
     */
    public function validateADA(string $adaDate, ?string $etaDate): array {
        // Check if date is valid format
        $date = DateTime::createFromFormat('Y-m-d', $adaDate);
        if (!$date || $date->format('Y-m-d') !== $adaDate) {
            return [
                'isValid' => false,
                'message' => 'Invalid date format. Expected Y-m-d',
                'code' => 'INVALID_DATE_FORMAT'
            ];
        }
        
        // Check if ADA is not before ETA (if ETA exists)
        if ($etaDate) {
            $eta = DateTime::createFromFormat('Y-m-d', $etaDate);
            if ($eta && $date < $eta) {
                return [
                    'isValid' => false,
                    'message' => 'ADA date cannot be before ETA date',
                    'code' => 'ADA_BEFORE_ETA'
                ];
            }
        }
        
        return [
            'isValid' => true,
            'message' => 'Valid ADA date',
            'code' => null
        ];
    }

    
    /**
     * Get sites pending ETA for an engineer
     * Returns installations with status 'pending_eta' assigned to the engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of installations pending ETA
     * 
     * Requirements: 3.1
     */
    public function getPendingETASites(int $engineerId): array {
        return $this->installationRepository->findPendingETA($engineerId);
    }
    
    /**
     * Get sites pending ADA for an engineer
     * Returns installations with status 'pending_ada' assigned to the engineer
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of installations pending ADA
     * 
     * Requirements: 3.1
     */
    public function getPendingADASites(int $engineerId): array {
        return $this->installationRepository->findPendingADA($engineerId);
    }
    
    /**
     * Get all ETA/ADA pending sites for an engineer
     * Returns installations with status 'pending_eta' or 'pending_ada'
     * 
     * @param int $engineerId Engineer user ID
     * @return array List of installations with ETA/ADA status
     * 
     * Requirements: 3.1
     */
    public function getETAADAPendingSites(int $engineerId): array {
        $pendingETA = $this->getPendingETASites($engineerId);
        $pendingADA = $this->getPendingADASites($engineerId);
        
        return [
            'pending_eta' => $pendingETA,
            'pending_ada' => $pendingADA,
            'total_pending_eta' => count($pendingETA),
            'total_pending_ada' => count($pendingADA)
        ];
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
            error_log("Failed to log installation ETA/ADA action: " . $e->getMessage());
        }
    }
}
