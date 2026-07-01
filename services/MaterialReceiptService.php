<?php
/**
 * Material Receipt Service
 * Handles business logic for material receipt confirmation operations
 * 
 * Requirements: 2.1, 2.2, 2.3
 * - 2.1: Display "Confirm Materials Received" button for pending_materials status
 * - 2.2: Record confirmation with timestamp and engineer ID
 * - 2.3: Update installation status to "materials_received"
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/MaterialReceiptRepository.php';
require_once __DIR__ . '/../repositories/InstallationRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../models/Installation.php';
require_once __DIR__ . '/../models/MaterialReceipt.php';

class MaterialReceiptService {
    private $db;
    private $materialReceiptRepository;
    private $installationRepository;
    private $userRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->materialReceiptRepository = new MaterialReceiptRepository();
        $this->installationRepository = new InstallationRepository();
        $this->userRepository = new UserRepository();
    }
    
    /**
     * Confirm material receipt for an installation
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID confirming receipt
     * @return array Result with success status and data/errors
     * 
     * Requirements: 2.1, 2.2, 2.3
     */
    public function confirmMaterialReceipt(int $installationId, int $engineerId): array {
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'success' => false,
                'message' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Validate that engineer can confirm materials (Requirement 2.1)
        $canConfirm = $this->canConfirmMaterials($installationId, $engineerId);
        if (!$canConfirm['canConfirm']) {
            return [
                'success' => false,
                'message' => $canConfirm['reason'],
                'code' => $canConfirm['code']
            ];
        }
        
        try {
            // Record material receipt with timestamp and engineer ID (Requirement 2.2)
            $receiptData = [
                'installation_id' => $installationId,
                'confirmed_by' => $engineerId,
                'confirmed_at' => date('Y-m-d H:i:s')
            ];
            
            $receipt = $this->materialReceiptRepository->create($receiptData);
            
            // Update installation status to "materials_received" (Requirement 2.3)
            $this->installationRepository->updateStatus($installationId, Installation::STATUS_MATERIALS_RECEIVED);
            
            // Log audit
            $this->logAction($engineerId, $installationId, 'material_receipt_confirmed', [
                'receipt_id' => $receipt['id'],
                'site_id' => $installation['site_id']
            ]);
            
            return [
                'success' => true,
                'message' => 'Material receipt confirmed successfully',
                'data' => [
                    'receipt' => $receipt,
                    'installation_status' => Installation::STATUS_MATERIALS_RECEIVED
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to confirm material receipt: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Get material receipt by installation ID
     * 
     * @param int $installationId Installation ID
     * @return array|null Material receipt record or null
     * 
     * Requirements: 2.2
     */
    public function getMaterialReceipt(int $installationId): ?array {
        return $this->materialReceiptRepository->findByInstallationId($installationId);
    }
    
    /**
     * Check if materials have been received for an installation
     * 
     * @param int $installationId Installation ID
     * @return bool True if materials have been received
     * 
     * Requirements: 2.3
     */
    public function hasMaterialsReceived(int $installationId): bool {
        return $this->materialReceiptRepository->hasMaterialsReceived($installationId);
    }
    
    /**
     * Validate that an engineer can confirm materials for an installation
     * 
     * @param int $installationId Installation ID
     * @param int $engineerId Engineer user ID
     * @return array Result with 'canConfirm', 'reason', and 'code'
     * 
     * Requirements: 2.1
     */
    public function canConfirmMaterials(int $installationId, int $engineerId): array {
        // Verify installation exists
        $installation = $this->installationRepository->findById($installationId);
        if (!$installation) {
            return [
                'canConfirm' => false,
                'reason' => 'Installation not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Check if installation status is "pending_materials" (Requirement 2.1)
        if ($installation['status'] !== Installation::STATUS_PENDING_MATERIALS) {
            return [
                'canConfirm' => false,
                'reason' => 'Materials can only be confirmed for installations with pending_materials status',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        // Check if materials have already been received
        if ($this->hasMaterialsReceived($installationId)) {
            return [
                'canConfirm' => false,
                'reason' => 'Materials have already been confirmed for this installation',
                'code' => 'ALREADY_CONFIRMED'
            ];
        }
        
        // Verify engineer exists
        $engineer = $this->userRepository->find($engineerId);
        if (!$engineer) {
            return [
                'canConfirm' => false,
                'reason' => 'Engineer not found',
                'code' => 'ENGINEER_NOT_FOUND'
            ];
        }
        
        // Verify engineer is active (handle both integer and string status)
        if (isset($engineer['status'])) {
            $status = $engineer['status'];
            $isActive = ($status === 'active' || $status === 1 || $status === '1');
            if (!$isActive) {
                return [
                    'canConfirm' => false,
                    'reason' => 'Engineer account is not active',
                    'code' => 'ENGINEER_INACTIVE'
                ];
            }
        }
        
        return [
            'canConfirm' => true,
            'reason' => null,
            'code' => null
        ];
    }
    
    /**
     * Get material receipt with full details
     * 
     * @param int $installationId Installation ID
     * @return array|null Material receipt with details or null
     */
    public function getMaterialReceiptWithDetails(int $installationId): ?array {
        return $this->materialReceiptRepository->findWithDetails(
            $this->getMaterialReceiptId($installationId) ?? 0
        );
    }
    
    /**
     * Get material receipt ID by installation ID
     * 
     * @param int $installationId Installation ID
     * @return int|null Material receipt ID or null
     */
    private function getMaterialReceiptId(int $installationId): ?int {
        $receipt = $this->materialReceiptRepository->findByInstallationId($installationId);
        return $receipt ? (int)$receipt['id'] : null;
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
            $details['entity_type'] = 'material_receipt';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log material receipt action: " . $e->getMessage());
        }
    }
}
