<?php
/**
 * Feasibility Service
 * Handles business logic for feasibility check operations
 * 
 * Requirements: 4.3, 4.4, 4.5
 * - 4.3: Validate all required fields before submission
 * - 4.4: Create feasibility check record with all captured data
 * - 4.5: Update feasibility status to feasibility_completed
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/FeasibilityCheckRepository.php';
require_once __DIR__ . '/../repositories/FeasibilityADARepository.php';
require_once __DIR__ . '/../repositories/FeasibilityETARepository.php';
require_once __DIR__ . '/../repositories/EngineerAssignmentRepository.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/EmailEventDispatcher.php';

class FeasibilityService {
    private $db;
    private $feasibilityRepository;
    private $adaRepository;
    private $etaRepository;
    private $assignmentRepository;
    private $siteRepository;
    
    // Required fields for feasibility check
    private $requiredFields = [
        'no_of_atm',
        'operator',
        'signal_status',
        'ups_available',
        'earthing'
    ];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->feasibilityRepository = new FeasibilityCheckRepository();
        $this->adaRepository = new FeasibilityADARepository();
        $this->etaRepository = new FeasibilityETARepository();
        $this->assignmentRepository = new EngineerAssignmentRepository();
        $this->siteRepository = new SiteRepository();
    }
    
    /**
     * Create a feasibility check for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @param array $data Feasibility check data
     * @param int $engineerId Engineer user ID creating the check
     * @return array Result with success status and data/errors
     * 
     * Requirements: 4.3, 4.4, 4.5
     */
    public function createFeasibilityCheck(int $assignmentId, array $data, int $engineerId): array {
        // Verify assignment exists
        $assignment = $this->assignmentRepository->findById($assignmentId);
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Assignment not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        // Verify engineer is assigned to this assignment
        if ((int)$assignment['engineer_id'] !== $engineerId) {
            return [
                'success' => false,
                'message' => 'You are not authorized to create feasibility check for this assignment',
                'code' => 'UNAUTHORIZED'
            ];
        }
        
        // Verify ADA has been submitted first
        if (!$this->adaRepository->hasADA($assignmentId)) {
            return [
                'success' => false,
                'message' => 'ADA must be submitted before feasibility check',
                'code' => 'PREREQUISITE_NOT_MET'
            ];
        }
        
        // Check if feasibility check already exists
        if ($this->feasibilityRepository->hasFeasibilityCheck($assignmentId)) {
            return [
                'success' => false,
                'message' => 'Feasibility check has already been submitted for this assignment',
                'code' => 'DUPLICATE_ERROR'
            ];
        }
        
        // Validate required fields (Requirement 4.3)
        $validation = $this->validateFeasibilityData($data);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            // Prepare data for creation
            $checkData = array_merge($data, [
                'assignment_id' => $assignmentId,
                'site_id' => $assignment['site_id'],
                'created_by' => $engineerId,
                'status' => 'active'
            ]);
            
            // Create feasibility check record (Requirement 4.4)
            $check = $this->feasibilityRepository->create($checkData);
            
            // Update assignment feasibility_status to feasibility_completed (Requirement 4.5)
            $this->updateAssignmentFeasibilityStatus($assignmentId, 'feasibility_completed');
            
            // Log audit
            $this->logAction($engineerId, $assignmentId, 'feasibility_check_created', [
                'check_id' => $check['id'],
                'site_id' => $assignment['site_id']
            ]);
            
            // Dispatch email event for feasibility submission
            $this->dispatchFeasibilityEvent('feasibility_submitted', [
                'feasibility_id' => $check['id'],
                'assignment_id' => $assignmentId,
                'site_id' => $assignment['site_id'],
                'engineer_id' => $engineerId,
                'company_id' => $assignment['company_id'] ?? 1,
                'user_id' => $engineerId
            ]);
            
            return [
                'success' => true,
                'message' => 'Feasibility check created successfully',
                'data' => $check
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create feasibility check: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Get feasibility check by ID
     * 
     * @param int $checkId Feasibility check ID
     * @return array|null Feasibility check record or null
     */
    public function getFeasibilityCheck(int $checkId): ?array {
        return $this->feasibilityRepository->findById($checkId);
    }
    
    /**
     * Get feasibility check by assignment ID
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null Feasibility check record or null
     * 
     * Requirements: 4.4
     */
    public function getFeasibilityByAssignment(int $assignmentId): ?array {
        return $this->feasibilityRepository->findByAssignment($assignmentId);
    }
    
    /**
     * Get feasibility status for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return string Feasibility status
     */
    public function getFeasibilityStatus(int $assignmentId): string {
        $assignment = $this->assignmentRepository->findById($assignmentId);
        return $assignment['feasibility_status'] ?? 'pending_eta';
    }
    
    /**
     * Update feasibility status for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @param string $status New feasibility status
     * @return bool Success
     */
    public function updateFeasibilityStatus(int $assignmentId, string $status): bool {
        $validStatuses = ['pending_eta', 'eta_submitted', 'ada_submitted', 'feasibility_completed'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        return $this->updateAssignmentFeasibilityStatus($assignmentId, $status);
    }
    
    /**
     * Update feasibility check data
     * 
     * @param int $feasibilityId Feasibility check ID
     * @param array $data Data to update
     * @return bool Success
     */
    public function updateFeasibility(int $feasibilityId, array $data): bool {
        $result = $this->feasibilityRepository->updateFeasibilityCheck($feasibilityId, $data);
        return $result !== null;
    }
    
    /**
     * Validate feasibility data for required fields
     * 
     * @param array $data Feasibility check data
     * @return array Validation result with isValid, message, and errors
     * 
     * Requirements: 4.3
     */
    public function validateFeasibilityData(array $data): array {
        $errors = [];
        
        // Check required fields
        foreach ($this->requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[] = [
                    'field' => $field,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            }
        }
        
        // Validate no_of_atm is a positive integer
        if (isset($data['no_of_atm']) && $data['no_of_atm'] !== '') {
            if (!is_numeric($data['no_of_atm']) || (int)$data['no_of_atm'] < 0) {
                $errors[] = [
                    'field' => 'no_of_atm',
                    'message' => 'Number of ATMs must be a non-negative integer',
                    'code' => 'INVALID_VALUE'
                ];
            }
        }
        
        // Validate remarks length (max 2000 characters)
        if (isset($data['remarks']) && mb_strlen($data['remarks'], 'UTF-8') > 2000) {
            $errors[] = [
                'field' => 'remarks',
                'message' => 'Remarks must not exceed 2000 characters',
                'code' => 'MAX_LENGTH_EXCEEDED'
            ];
        }
        
        // Validate no_of_ups if provided
        if (isset($data['no_of_ups']) && $data['no_of_ups'] !== '' && $data['no_of_ups'] !== null) {
            if (!is_numeric($data['no_of_ups']) || (int)$data['no_of_ups'] < 0) {
                $errors[] = [
                    'field' => 'no_of_ups',
                    'message' => 'Number of UPS must be a non-negative integer',
                    'code' => 'INVALID_VALUE'
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
            'message' => 'Valid feasibility data',
            'errors' => []
        ];
    }
    
    /**
     * Get feasibility tracking data with filters
     * Returns all assignments with their feasibility status, ETA, ADA, and location
     * 
     * @param array $filters Filter options
     * @return array Paginated results
     * 
     * Requirements: 8.1, 8.3
     */
    public function getFeasibilityTracking(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause - filter by assignment status (assigned, in_progress, completed)
        $where = "ea.status IN ('assigned', 'in_progress', 'completed')";
        $params = [];
        $types = '';
        
        // Track if we need ETA/ADA joins for filtering
        $needsEtaJoin = false;
        $needsAdaJoin = false;
        
        // Filter by feasibility status (Requirement 8.3)
        if (!empty($filters['status'])) {
            // Check if it's an approval workflow status
            $approvalStatuses = ['pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected'];
            
            if (in_array($filters['status'], $approvalStatuses)) {
                $where .= " AND fc.approval_status = ?";
                $params[] = $filters['status'];
                $types .= 's';
            } elseif ($filters['status'] === 'pending_eta') {
                // Filter for sites without ETA
                $where .= " AND eta.id IS NULL";
                $needsEtaJoin = true;
            } elseif ($filters['status'] === 'eta_submitted') {
                // Filter for sites with ETA
                $where .= " AND eta.id IS NOT NULL";
                $needsEtaJoin = true;
            } elseif ($filters['status'] === 'ada_submitted') {
                // Filter for sites with ADA
                $where .= " AND ada.id IS NOT NULL";
                $needsAdaJoin = true;
            } elseif ($filters['status'] === 'feasibility_completed') {
                // Filter for completed feasibility (includes all completed statuses)
                $where .= " AND ea.feasibility_status IN ('feasibility_completed', 'pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected')";
            } else {
                $where .= " AND ea.feasibility_status = ?";
                $params[] = $filters['status'];
                $types .= 's';
            }
        }
        
        // Filter by contractor
        if (!empty($filters['contractor_id'])) {
            $where .= " AND sd.contractor_id = ?";
            $params[] = (int)$filters['contractor_id'];
            $types .= 'i';
        }
        
        // Filter by engineer
        if (!empty($filters['engineer_id'])) {
            $where .= " AND ea.engineer_id = ?";
            $params[] = (int)$filters['engineer_id'];
            $types .= 'i';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where .= " AND (s.site_name LIKE ? OR s.lho LIKE ? OR s.city LIKE ? OR s.address LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(ea.assigned_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(ea.assigned_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Count total (include all necessary joins for filtering)
        $countSql = "SELECT COUNT(*) as total 
                     FROM `engineer_assignments` ea
                     LEFT JOIN `sites` s ON ea.site_id = s.id
                     LEFT JOIN `site_delegations` sd ON ea.delegation_id = sd.id
                     LEFT JOIN `feasibility_checks` fc ON ea.id = fc.assignment_id
                     LEFT JOIN `feasibility_eta` eta ON ea.id = eta.assignment_id AND eta.is_current = TRUE
                     LEFT JOIN `feasibility_ada` ada ON ea.id = ada.assignment_id
                     WHERE {$where}";
        $countResult = $this->db->getResults($countSql, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        // Get paginated data (Requirement 8.1, 8.2)
        $sql = "SELECT 
                    ea.id as assignment_id,
                    ea.site_id,
                    ea.engineer_id,
                    ea.feasibility_status,
                    ea.assigned_at,
                    s.site_name, s.lho, s.address, s.city, s.state, s.country,
                    s.bank_name, s.customer_name, s.zone,
                    s.latitude as site_latitude, s.longitude as site_longitude,
                    CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name,
                    eng.email as engineer_email,
                    sd.contractor_id,
                    comp.name as contractor_name,
                    eta.eta_datetime, eta.submitted_at as eta_submitted_at,
                    ada.ada_datetime, ada.latitude as ada_latitude, ada.longitude as ada_longitude,
                    ada.submitted_at as ada_submitted_at,
                    fc.id as feasibility_check_id, fc.created_at as feasibility_completed_at,
                    fc.approval_status,
                    inst.id as installation_id, inst.status as installation_status
                FROM `engineer_assignments` ea
                LEFT JOIN `sites` s ON ea.site_id = s.id
                LEFT JOIN `users` eng ON ea.engineer_id = eng.id
                LEFT JOIN `site_delegations` sd ON ea.delegation_id = sd.id
                LEFT JOIN `companies` comp ON sd.contractor_id = comp.id
                LEFT JOIN `feasibility_eta` eta ON ea.id = eta.assignment_id AND eta.is_current = TRUE
                LEFT JOIN `feasibility_ada` ada ON ea.id = ada.assignment_id
                LEFT JOIN `feasibility_checks` fc ON ea.id = fc.assignment_id
                LEFT JOIN `installations` inst ON s.id = inst.site_id
                WHERE {$where}
                ORDER BY ea.assigned_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $data = $this->db->getResults($sql, $params, $types);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
    
    /**
     * Export feasibility data
     * Returns all assignments with complete feasibility data for export
     * 
     * @param array $filters Optional filters
     * @return array Feasibility data for export
     * 
     * Requirements: 8.4
     */
    public function exportFeasibilityData(array $filters = []): array {
        // Build WHERE clause - filter by assignment status (assigned, in_progress, completed)
        $where = "ea.status IN ('assigned', 'in_progress', 'completed')";
        $params = [];
        $types = '';
        
        // Filter by feasibility status
        if (!empty($filters['status'])) {
            $where .= " AND ea.feasibility_status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Filter by contractor
        if (!empty($filters['contractor_id'])) {
            $where .= " AND sd.contractor_id = ?";
            $params[] = (int)$filters['contractor_id'];
            $types .= 'i';
        }
        
        // Filter by engineer
        if (!empty($filters['engineer_id'])) {
            $where .= " AND ea.engineer_id = ?";
            $params[] = (int)$filters['engineer_id'];
            $types .= 'i';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where .= " AND (s.site_name LIKE ? OR s.lho LIKE ? OR s.city LIKE ? OR s.address LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(ea.assigned_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(ea.assigned_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        // Get all data for export
        $sql = "SELECT 
                    s.site_name, s.lho, s.address, s.city, s.state, s.country,
                    s.bank_name, s.customer_name, s.zone,
                    CONCAT(con.first_name, ' ', con.last_name) as contractor_name,
                    CONCAT(eng.first_name, ' ', eng.last_name) as engineer_name,
                    ea.feasibility_status,
                    eta.eta_datetime,
                    ada.ada_datetime, ada.latitude as ada_latitude, ada.longitude as ada_longitude,
                    fc.no_of_atm, fc.atm_id_1, fc.atm_1_status, fc.atm_id_2, fc.atm_2_status,
                    fc.atm_id_3, fc.atm_3_status,
                    fc.operator, fc.signal_status, fc.operator_2, fc.signal_status_2,
                    fc.backroom_network_remark,
                    fc.ups_available, fc.no_of_ups, fc.ups_battery_backup,
                    fc.ups_working_1, fc.ups_working_2, fc.ups_working_3,
                    fc.power_socket_availability, fc.power_socket_availability_ups,
                    fc.earthing, fc.earthing_voltage,
                    fc.power_fluctuation_en, fc.power_fluctuation_pe, fc.power_fluctuation_pn,
                    fc.frequent_power_cut, fc.frequent_power_cut_from, fc.frequent_power_cut_to,
                    fc.frequent_power_cut_remark,
                    fc.em_lock_available, fc.em_lock_password, fc.password_received,
                    fc.backroom_key_name, fc.backroom_key_number, fc.backroom_key_status,
                    fc.antenna_routing_detail, fc.router_antenna_position, fc.router_position,
                    fc.nearest_shop_name, fc.nearest_shop_number, fc.nearest_shop_distance,
                    fc.backroom_disturbing_material, fc.backroom_disturbing_material_remark,
                    fc.remarks,
                    fc.created_at
                FROM `engineer_assignments` ea
                LEFT JOIN `sites` s ON ea.site_id = s.id
                LEFT JOIN `users` eng ON ea.engineer_id = eng.id
                LEFT JOIN `site_delegations` sd ON ea.delegation_id = sd.id
                LEFT JOIN `users` con ON sd.contractor_id = con.id
                LEFT JOIN `feasibility_eta` eta ON ea.id = eta.assignment_id AND eta.is_current = TRUE
                LEFT JOIN `feasibility_ada` ada ON ea.id = ada.assignment_id
                LEFT JOIN `feasibility_checks` fc ON ea.id = fc.assignment_id
                WHERE {$where}
                ORDER BY ea.assigned_at DESC";
        
        if (empty($params)) {
            return $this->db->getResults($sql);
        }
        
        return $this->db->getResults($sql, $params, $types);
    }
    
    /**
     * Get master site information for feasibility form
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return array|null Site information or null
     * 
     * Requirements: 4.2
     */
    public function getMasterSiteInfo(int $assignmentId): ?array {
        $assignment = $this->assignmentRepository->findById($assignmentId);
        if (!$assignment) {
            return null;
        }
        
        return [
            'site_name' => $assignment['site_name'] ?? '',
            'lho' => $assignment['lho'] ?? '',
            'address' => $assignment['address'] ?? '',
            'city' => $assignment['city'] ?? '',
            'state' => $assignment['state'] ?? '',
            'country' => $assignment['country'] ?? '',
            'bank_name' => $assignment['bank_name'] ?? '',
            'customer_name' => $assignment['customer_name'] ?? '',
            'latitude' => $assignment['latitude'] ?? '',
            'longitude' => $assignment['longitude'] ?? '',
            'zone' => $assignment['zone'] ?? ''
        ];
    }
    
    /**
     * Check if feasibility check exists for an assignment
     * 
     * @param int $assignmentId Engineer assignment ID
     * @return bool True if feasibility check exists
     */
    public function hasFeasibilityCheck(int $assignmentId): bool {
        return $this->feasibilityRepository->hasFeasibilityCheck($assignmentId);
    }
    
    /**
     * Get feasibility checks by site
     * 
     * @param int $siteId Site ID
     * @return array List of feasibility checks
     */
    public function getFeasibilityBySite(int $siteId): array {
        return $this->feasibilityRepository->findBySite($siteId);
    }
    
    /**
     * Get feasibility status counts
     * 
     * @param int|null $contractorId Optional contractor ID to filter counts
     * @return array Status counts
     */
    public function getFeasibilityStatusCounts(?int $contractorId = null): array {
        $params = [];
        $types = '';
        $contractorJoin = '';
        $contractorWhere = '';
        
        // If contractor_id is provided, filter by contractor's engineers only
        if ($contractorId !== null) {
            $contractorJoin = " JOIN `site_delegations` sd ON ea.delegation_id = sd.id";
            $contractorWhere = " AND sd.contractor_id = ?";
            $params[] = $contractorId;
            $types .= 'i';
        }
        
        // Get feasibility progress counts based on actual ETA/ADA records
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN eta.id IS NULL THEN 1 ELSE 0 END) as pending_eta,
                    SUM(CASE WHEN eta.id IS NOT NULL THEN 1 ELSE 0 END) as eta_submitted,
                    SUM(CASE WHEN ada.id IS NOT NULL THEN 1 ELSE 0 END) as ada_submitted,
                    SUM(CASE WHEN ea.feasibility_status IN ('feasibility_completed', 'pending_contractor_review', 'contractor_approved', 'contractor_rejected', 'adv_approved', 'adv_rejected') THEN 1 ELSE 0 END) as feasibility_completed
                FROM `engineer_assignments` ea
                {$contractorJoin}
                LEFT JOIN `feasibility_eta` eta ON ea.id = eta.assignment_id AND eta.is_current = TRUE
                LEFT JOIN `feasibility_ada` ada ON ea.id = ada.assignment_id
                WHERE ea.status IN ('assigned', 'in_progress', 'completed'){$contractorWhere}";
        
        if (empty($params)) {
            $result = $this->db->getResults($sql);
        } else {
            $result = $this->db->getResults($sql, $params, $types);
        }
        
        // Get approval workflow counts from feasibility_checks
        $approvalParams = $params;
        $approvalTypes = $types;
        $approvalContractorJoin = $contractorId !== null ? " JOIN `site_delegations` sd ON ea.delegation_id = sd.id" : "";
        
        $approvalSql = "SELECT 
                    SUM(CASE WHEN fc.approval_status = 'pending_contractor_review' THEN 1 ELSE 0 END) as pending_contractor_review,
                    SUM(CASE WHEN fc.approval_status = 'contractor_approved' THEN 1 ELSE 0 END) as contractor_approved,
                    SUM(CASE WHEN fc.approval_status = 'contractor_rejected' THEN 1 ELSE 0 END) as contractor_rejected,
                    SUM(CASE WHEN fc.approval_status = 'adv_approved' THEN 1 ELSE 0 END) as adv_approved,
                    SUM(CASE WHEN fc.approval_status = 'adv_rejected' THEN 1 ELSE 0 END) as adv_rejected
                FROM `feasibility_checks` fc
                JOIN `engineer_assignments` ea ON fc.assignment_id = ea.id
                {$approvalContractorJoin}
                WHERE ea.status IN ('assigned', 'in_progress', 'completed'){$contractorWhere}";
        
        if (empty($approvalParams)) {
            $approvalResult = $this->db->getResults($approvalSql);
        } else {
            $approvalResult = $this->db->getResults($approvalSql, $approvalParams, $approvalTypes);
        }
        
        return [
            'total' => (int)($result[0]['total'] ?? 0),
            'pending_eta' => (int)($result[0]['pending_eta'] ?? 0),
            'eta_submitted' => (int)($result[0]['eta_submitted'] ?? 0),
            'ada_submitted' => (int)($result[0]['ada_submitted'] ?? 0),
            'feasibility_completed' => (int)($result[0]['feasibility_completed'] ?? 0),
            // Approval workflow counts
            'pending_contractor_review' => (int)($approvalResult[0]['pending_contractor_review'] ?? 0),
            'contractor_approved' => (int)($approvalResult[0]['contractor_approved'] ?? 0),
            'contractor_rejected' => (int)($approvalResult[0]['contractor_rejected'] ?? 0),
            'adv_approved' => (int)($approvalResult[0]['adv_approved'] ?? 0),
            'adv_rejected' => (int)($approvalResult[0]['adv_rejected'] ?? 0)
        ];
    }
    
    /**
     * Update assignment feasibility status
     * 
     * @param int $assignmentId Assignment ID
     * @param string $status New feasibility status
     * @return bool Success
     */
    private function updateAssignmentFeasibilityStatus(int $assignmentId, string $status): bool {
        $sql = "UPDATE `engineer_assignments` SET `feasibility_status` = ?, `updated_at` = NOW() WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$status, $assignmentId], 'si');
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows > 0;
    }
    
    /**
     * Log action for audit trail
     * 
     * @param int $userId User performing the action
     * @param int $assignmentId Assignment ID
     * @param string $action Action type
     * @param array $details Additional details
     */
    private function logAction(int $userId, int $assignmentId, string $action, array $details): void {
        try {
            $sql = "INSERT INTO user_audit_log (user_id, action, details, performed_by, ip_address) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $details['assignment_id'] = $assignmentId;
            $details['entity_type'] = 'feasibility_check';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId,
                $action,
                json_encode($details),
                $userId,
                $ipAddress
            ], 'issis');
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log feasibility action: " . $e->getMessage());
        }
    }
    
    /**
     * Dispatch feasibility event to email system
     * 
     * @param string $eventType Event type
     * @param array $eventData Event data
     */
    private function dispatchFeasibilityEvent(string $eventType, array $eventData): void {
        try {
            EmailEventDispatcher::dispatchFeasibilityEvent($eventType, $eventData);
        } catch (Exception $e) {
            error_log("Failed to dispatch feasibility email event: " . $e->getMessage());
        }
    }
}
