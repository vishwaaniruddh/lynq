<?php
/**
 * Installation Model
 * Represents an installation record for site equipment installation
 * 
 * Requirements: 3.4, 17.1, 17.2, 17.3
 * - 3.4: Create installation record with all captured data
 * - 17.1: Serialize all fields to JSON format correctly
 * - 17.2: Deserialize JSON data to produce equivalent installation objects
 * - 17.3: Round-trip data produces equivalent data
 */

require_once __DIR__ . '/BaseModel.php';

class Installation extends BaseModel {
    protected $table = 'installations';
    protected $fillable = [
        'site_id', 'feasibility_id', 'initiated_by', 'initiated_at',
        // Delegation fields (Requirements: 1.4)
        'contractor_id', 'delegated_by', 'delegated_at',
        // Assignment fields (Requirements: 2.4)
        'assigned_engineer_id', 'assigned_by', 'assigned_at',
        // ETA/ADA fields (Requirements: 3.3, 3.5)
        'eta_date', 'eta_submitted_at', 'ada_date', 'ada_submitted_at',
        // Site Information
        'atm_id', 'atm_id_2', 'atm_id_3', 'address', 'city', 'location', 'lho', 'state',
        'atm_working_1', 'atm_working_2', 'atm_working_3',
        // Vendor/Engineer Information
        'vendor_name', 'engineer_name', 'engineer_number',
        // Router Section
        'router_serial', 'router_make', 'router_model', 'router_fixed', 'router_fixed_remarks',
        'router_fixed_snaps', 'router_status', 'router_status_remarks', 'router_status_snaps',
        // Adaptor Section
        'adaptor_installed', 'adaptor_snaps', 'adaptor_status', 'adaptor_status_remarks', 'adaptor_status_snaps',
        // LAN Cable Section
        'lan_cable_installed', 'lan_cable_install_remark', 'lan_cable_install_snap',
        'lan_cable_status', 'lan_cable_status_not_working_reasons', 'lan_cable_status_remark', 'lan_cable_status_snap',
        // Antenna Section
        'antenna_installed', 'antenna_remarks', 'antenna_snaps',
        'antenna_status', 'antenna_status_remarks', 'antenna_status_snaps',
        // GPS Section
        'gps_installed', 'gps_remarks', 'gps_snaps',
        'gps_status', 'gps_status_remarks', 'gps_status_snaps',
        // WiFi Section
        'wifi_installed', 'wifi_remarks', 'wifi_snaps',
        'wifi_status', 'wifi_status_remarks', 'wifi_status_snaps',
        // Airtel SIM Section
        'airtel_sim_installed', 'airtel_sim_remarks', 'airtel_sim_snaps',
        'airtel_sim_status', 'airtel_sim_status_remarks', 'airtel_sim_status_snaps',
        // Vodafone SIM Section
        'vodafone_sim_installed', 'vodafone_sim_remarks', 'vodafone_sim_snaps',
        'vodafone_sim_status', 'vodafone_sim_status_remarks', 'vodafone_sim_status_snaps',
        // JIO SIM Section
        'jio_sim_installed', 'jio_sim_remarks', 'jio_sim_snaps',
        'jio_sim_status', 'jio_sim_status_remarks', 'jio_sim_status_snaps',
        // Verification Section
        'signature_image', 'vendor_stamp',
        // Status
        'status',
        // Audit
        'created_by', 'created_at', 'updated_at', 'submitted_by', 'submitted_at'
    ];
    
    // Status constants - New workflow states (Requirements: 1.4, 2.4, 3.3, 3.5)
    const STATUS_PENDING_ASSIGNMENT = 'pending_assignment';
    const STATUS_PENDING_ETA = 'pending_eta';
    const STATUS_PENDING_ADA = 'pending_ada';
    const STATUS_PENDING_MATERIALS = 'pending_materials';
    const STATUS_MATERIALS_RECEIVED = 'materials_received';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_PENDING_CONTRACTOR_REVIEW = 'pending_contractor_review';
    const STATUS_CONTRACTOR_APPROVED = 'contractor_approved';
    const STATUS_CONTRACTOR_REJECTED = 'contractor_rejected';
    const STATUS_ADV_APPROVED = 'adv_approved';
    const STATUS_ADV_REJECTED = 'adv_rejected';

    // Yes/No enum values
    const YES = 'yes';
    const NO = 'no';
    
    // Working status enum values
    const WORKING = 'working';
    const NOT_WORKING = 'notWorking';
    
    /**
     * Get all valid statuses
     * 
     * @return array List of valid status values
     */
    public static function getStatuses(): array {
        return [
            self::STATUS_PENDING_ASSIGNMENT,
            self::STATUS_PENDING_ETA,
            self::STATUS_PENDING_ADA,
            self::STATUS_PENDING_MATERIALS,
            self::STATUS_MATERIALS_RECEIVED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_CONTRACTOR_REVIEW,
            self::STATUS_CONTRACTOR_APPROVED,
            self::STATUS_CONTRACTOR_REJECTED,
            self::STATUS_ADV_APPROVED,
            self::STATUS_ADV_REJECTED
        ];
    }
    
    /**
     * Check if a status is valid
     * 
     * @param string $status Status to check
     * @return bool True if valid
     */
    public static function isValidStatus(string $status): bool {
        return in_array($status, self::getStatuses());
    }
    
    /**
     * Get status label for display
     * 
     * @param string $status Status value
     * @return string Human-readable label
     */
    public static function getStatusLabel(string $status): string {
        return match($status) {
            self::STATUS_PENDING_ASSIGNMENT => 'Pending Assignment',
            self::STATUS_PENDING_ETA => 'Pending ETA',
            self::STATUS_PENDING_ADA => 'Pending ADA',
            self::STATUS_PENDING_MATERIALS => 'Pending Materials',
            self::STATUS_MATERIALS_RECEIVED => 'Materials Received',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_PENDING_CONTRACTOR_REVIEW => 'Pending Contractor Review',
            self::STATUS_CONTRACTOR_APPROVED => 'Contractor Approved',
            self::STATUS_CONTRACTOR_REJECTED => 'Contractor Rejected',
            self::STATUS_ADV_APPROVED => 'ADV Approved',
            self::STATUS_ADV_REJECTED => 'ADV Rejected',
            default => $status
        };
    }
    
    /**
     * Convert installation record to array
     * Implements serialization for Requirements 17.1
     * 
     * @param array $record Installation record from database
     * @return array Serialized installation data
     */
    public static function toArray(array $record): array {
        return [
            'id' => isset($record['id']) ? (int)$record['id'] : null,
            'site_id' => isset($record['site_id']) ? (int)$record['site_id'] : null,
            'feasibility_id' => isset($record['feasibility_id']) ? (int)$record['feasibility_id'] : null,
            'initiated_by' => isset($record['initiated_by']) ? (int)$record['initiated_by'] : null,
            'initiated_at' => $record['initiated_at'] ?? null,
            
            // Delegation fields (Requirements: 1.4)
            'contractor_id' => isset($record['contractor_id']) ? (int)$record['contractor_id'] : null,
            'delegated_by' => isset($record['delegated_by']) ? (int)$record['delegated_by'] : null,
            'delegated_at' => $record['delegated_at'] ?? null,
            
            // Assignment fields (Requirements: 2.4)
            'assigned_engineer_id' => isset($record['assigned_engineer_id']) ? (int)$record['assigned_engineer_id'] : null,
            'assigned_by' => isset($record['assigned_by']) ? (int)$record['assigned_by'] : null,
            'assigned_at' => $record['assigned_at'] ?? null,
            
            // ETA/ADA fields (Requirements: 3.3, 3.5)
            'eta_date' => $record['eta_date'] ?? null,
            'eta_submitted_at' => $record['eta_submitted_at'] ?? null,
            'ada_date' => $record['ada_date'] ?? null,
            'ada_submitted_at' => $record['ada_submitted_at'] ?? null,
            
            // Site Information
            'atm_id' => $record['atm_id'] ?? null,
            'atm_id_2' => $record['atm_id_2'] ?? null,
            'atm_id_3' => $record['atm_id_3'] ?? null,
            'address' => $record['address'] ?? null,
            'city' => $record['city'] ?? null,
            'location' => $record['location'] ?? null,
            'lho' => $record['lho'] ?? null,
            'state' => $record['state'] ?? null,
            'atm_working_1' => $record['atm_working_1'] ?? null,
            'atm_working_2' => $record['atm_working_2'] ?? null,
            'atm_working_3' => $record['atm_working_3'] ?? null,
            
            // Vendor/Engineer Information
            'vendor_name' => $record['vendor_name'] ?? null,
            'engineer_name' => $record['engineer_name'] ?? null,
            'engineer_number' => $record['engineer_number'] ?? null,
            
            // Router Section
            'router_serial' => $record['router_serial'] ?? null,
            'router_make' => $record['router_make'] ?? null,
            'router_model' => $record['router_model'] ?? null,
            'router_fixed' => $record['router_fixed'] ?? null,
            'router_fixed_remarks' => $record['router_fixed_remarks'] ?? null,
            'router_fixed_snaps' => $record['router_fixed_snaps'] ?? null,
            'router_status' => $record['router_status'] ?? null,
            'router_status_remarks' => $record['router_status_remarks'] ?? null,
            'router_status_snaps' => $record['router_status_snaps'] ?? null,
            
            // Adaptor Section
            'adaptor_installed' => $record['adaptor_installed'] ?? null,
            'adaptor_snaps' => $record['adaptor_snaps'] ?? null,
            'adaptor_status' => $record['adaptor_status'] ?? null,
            'adaptor_status_remarks' => $record['adaptor_status_remarks'] ?? null,
            'adaptor_status_snaps' => $record['adaptor_status_snaps'] ?? null,
            
            // LAN Cable Section
            'lan_cable_installed' => $record['lan_cable_installed'] ?? null,
            'lan_cable_install_remark' => $record['lan_cable_install_remark'] ?? null,
            'lan_cable_install_snap' => $record['lan_cable_install_snap'] ?? null,
            'lan_cable_status' => $record['lan_cable_status'] ?? null,
            'lan_cable_status_not_working_reasons' => $record['lan_cable_status_not_working_reasons'] ?? null,
            'lan_cable_status_remark' => $record['lan_cable_status_remark'] ?? null,
            'lan_cable_status_snap' => $record['lan_cable_status_snap'] ?? null,
            
            // Antenna Section
            'antenna_installed' => $record['antenna_installed'] ?? null,
            'antenna_remarks' => $record['antenna_remarks'] ?? null,
            'antenna_snaps' => $record['antenna_snaps'] ?? null,
            'antenna_status' => $record['antenna_status'] ?? null,
            'antenna_status_remarks' => $record['antenna_status_remarks'] ?? null,
            'antenna_status_snaps' => $record['antenna_status_snaps'] ?? null,
            
            // GPS Section
            'gps_installed' => $record['gps_installed'] ?? null,
            'gps_remarks' => $record['gps_remarks'] ?? null,
            'gps_snaps' => $record['gps_snaps'] ?? null,
            'gps_status' => $record['gps_status'] ?? null,
            'gps_status_remarks' => $record['gps_status_remarks'] ?? null,
            'gps_status_snaps' => $record['gps_status_snaps'] ?? null,
            
            // WiFi Section
            'wifi_installed' => $record['wifi_installed'] ?? null,
            'wifi_remarks' => $record['wifi_remarks'] ?? null,
            'wifi_snaps' => $record['wifi_snaps'] ?? null,
            'wifi_status' => $record['wifi_status'] ?? null,
            'wifi_status_remarks' => $record['wifi_status_remarks'] ?? null,
            'wifi_status_snaps' => $record['wifi_status_snaps'] ?? null,
            
            // Airtel SIM Section
            'airtel_sim_installed' => $record['airtel_sim_installed'] ?? null,
            'airtel_sim_remarks' => $record['airtel_sim_remarks'] ?? null,
            'airtel_sim_snaps' => $record['airtel_sim_snaps'] ?? null,
            'airtel_sim_status' => $record['airtel_sim_status'] ?? null,
            'airtel_sim_status_remarks' => $record['airtel_sim_status_remarks'] ?? null,
            'airtel_sim_status_snaps' => $record['airtel_sim_status_snaps'] ?? null,
            
            // Vodafone SIM Section
            'vodafone_sim_installed' => $record['vodafone_sim_installed'] ?? null,
            'vodafone_sim_remarks' => $record['vodafone_sim_remarks'] ?? null,
            'vodafone_sim_snaps' => $record['vodafone_sim_snaps'] ?? null,
            'vodafone_sim_status' => $record['vodafone_sim_status'] ?? null,
            'vodafone_sim_status_remarks' => $record['vodafone_sim_status_remarks'] ?? null,
            'vodafone_sim_status_snaps' => $record['vodafone_sim_status_snaps'] ?? null,
            
            // JIO SIM Section
            'jio_sim_installed' => $record['jio_sim_installed'] ?? null,
            'jio_sim_remarks' => $record['jio_sim_remarks'] ?? null,
            'jio_sim_snaps' => $record['jio_sim_snaps'] ?? null,
            'jio_sim_status' => $record['jio_sim_status'] ?? null,
            'jio_sim_status_remarks' => $record['jio_sim_status_remarks'] ?? null,
            'jio_sim_status_snaps' => $record['jio_sim_status_snaps'] ?? null,
            
            // Verification Section
            'signature_image' => $record['signature_image'] ?? null,
            'vendor_stamp' => $record['vendor_stamp'] ?? null,
            
            // Status
            'status' => $record['status'] ?? self::STATUS_PENDING_ASSIGNMENT,
            
            // Audit
            'created_by' => isset($record['created_by']) ? (int)$record['created_by'] : null,
            'created_at' => $record['created_at'] ?? null,
            'updated_at' => $record['updated_at'] ?? null,
            'submitted_by' => isset($record['submitted_by']) ? (int)$record['submitted_by'] : null,
            'submitted_at' => $record['submitted_at'] ?? null
        ];
    }

    /**
     * Convert array data to installation record format
     * Implements deserialization for Requirements 17.2
     * 
     * @param array $data Input data array
     * @return array Installation record data
     */
    public static function fromArray(array $data): array {
        $record = [];
        
        // Integer fields (including new delegation and assignment fields - Requirements: 1.4, 2.4)
        $intFields = ['id', 'site_id', 'feasibility_id', 'initiated_by', 'created_by', 'submitted_by',
                      'contractor_id', 'delegated_by', 'assigned_engineer_id', 'assigned_by'];
        foreach ($intFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                $record[$field] = (int)$data[$field];
            } elseif (array_key_exists($field, $data)) {
                $record[$field] = null;
            }
        }
        
        // Date fields - ETA/ADA (Requirements: 3.3, 3.5)
        $dateFields = ['eta_date', 'ada_date'];
        foreach ($dateFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Site Information
        $siteFields = ['atm_id', 'atm_id_2', 'atm_id_3', 'address', 'city', 'location', 'lho', 'state',
                       'atm_working_1', 'atm_working_2', 'atm_working_3'];
        foreach ($siteFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Vendor/Engineer Information
        $vendorFields = ['vendor_name', 'engineer_name', 'engineer_number'];
        foreach ($vendorFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Router Section
        $routerFields = ['router_serial', 'router_make', 'router_model', 'router_fixed', 'router_fixed_remarks',
                         'router_fixed_snaps', 'router_status', 'router_status_remarks', 'router_status_snaps'];
        foreach ($routerFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Adaptor Section
        $adaptorFields = ['adaptor_installed', 'adaptor_snaps', 'adaptor_status', 'adaptor_status_remarks', 'adaptor_status_snaps'];
        foreach ($adaptorFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - LAN Cable Section
        $lanFields = ['lan_cable_installed', 'lan_cable_install_remark', 'lan_cable_install_snap',
                      'lan_cable_status', 'lan_cable_status_not_working_reasons', 'lan_cable_status_remark', 'lan_cable_status_snap'];
        foreach ($lanFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Antenna Section
        $antennaFields = ['antenna_installed', 'antenna_remarks', 'antenna_snaps',
                          'antenna_status', 'antenna_status_remarks', 'antenna_status_snaps'];
        foreach ($antennaFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - GPS Section
        $gpsFields = ['gps_installed', 'gps_remarks', 'gps_snaps',
                      'gps_status', 'gps_status_remarks', 'gps_status_snaps'];
        foreach ($gpsFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - WiFi Section
        $wifiFields = ['wifi_installed', 'wifi_remarks', 'wifi_snaps',
                       'wifi_status', 'wifi_status_remarks', 'wifi_status_snaps'];
        foreach ($wifiFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Airtel SIM Section
        $airtelFields = ['airtel_sim_installed', 'airtel_sim_remarks', 'airtel_sim_snaps',
                         'airtel_sim_status', 'airtel_sim_status_remarks', 'airtel_sim_status_snaps'];
        foreach ($airtelFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Vodafone SIM Section
        $vodafoneFields = ['vodafone_sim_installed', 'vodafone_sim_remarks', 'vodafone_sim_snaps',
                           'vodafone_sim_status', 'vodafone_sim_status_remarks', 'vodafone_sim_status_snaps'];
        foreach ($vodafoneFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - JIO SIM Section
        $jioFields = ['jio_sim_installed', 'jio_sim_remarks', 'jio_sim_snaps',
                      'jio_sim_status', 'jio_sim_status_remarks', 'jio_sim_status_snaps'];
        foreach ($jioFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // String fields - Verification Section
        $verificationFields = ['signature_image', 'vendor_stamp'];
        foreach ($verificationFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        // Status field
        if (array_key_exists('status', $data)) {
            $record['status'] = $data['status'] !== '' ? $data['status'] : self::STATUS_PENDING_ASSIGNMENT;
        }
        
        // Datetime fields (including new delegation, assignment, and ETA/ADA timestamps - Requirements: 1.4, 2.4, 3.3, 3.5)
        $datetimeFields = ['initiated_at', 'created_at', 'updated_at', 'submitted_at',
                           'delegated_at', 'assigned_at', 'eta_submitted_at', 'ada_submitted_at'];
        foreach ($datetimeFields as $field) {
            if (array_key_exists($field, $data)) {
                $record[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        
        return $record;
    }
    
    /**
     * Serialize installation to JSON string
     * 
     * @param array $record Installation record
     * @return string JSON string
     */
    public static function toJson(array $record): string {
        return json_encode(self::toArray($record), JSON_PRETTY_PRINT);
    }
    
    /**
     * Deserialize installation from JSON string
     * 
     * @param string $json JSON string
     * @return array Installation record data
     */
    public static function fromJson(string $json): array {
        $data = json_decode($json, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return self::fromArray($data);
    }
    
    /**
     * Validate installation data
     * 
     * @param array $data Installation data to validate
     * @return array Validation result with 'isValid' and 'errors'
     */
    public function validate(array $data): array {
        $errors = [];
        
        // Validate required fields for creation
        $requiredFields = ['site_id', 'feasibility_id', 'initiated_by', 'atm_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[] = [
                    'field' => $field,
                    'message' => "The {$field} field is required",
                    'code' => 'REQUIRED_FIELD_MISSING'
                ];
            }
        }
        
        // Validate status if provided
        if (isset($data['status']) && !self::isValidStatus($data['status'])) {
            $errors[] = [
                'field' => 'status',
                'message' => 'Invalid status value',
                'code' => 'INVALID_STATUS'
            ];
        }
        
        // Validate yes/no fields
        $yesNoFields = ['router_fixed', 'adaptor_installed', 'lan_cable_installed', 
                        'antenna_installed', 'gps_installed', 'wifi_installed',
                        'airtel_sim_installed', 'vodafone_sim_installed', 'jio_sim_installed'];
        foreach ($yesNoFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                if (!in_array($data[$field], [self::YES, self::NO])) {
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
                if (!in_array($data[$field], [self::WORKING, self::NOT_WORKING])) {
                    $errors[] = [
                        'field' => $field,
                        'message' => "The {$field} field must be 'working' or 'notWorking'",
                        'code' => 'INVALID_WORKING_STATUS'
                    ];
                }
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Find installation by site ID
     * 
     * @param int $siteId Site ID
     * @return array|null Installation record or null
     */
    public function findBySiteId(int $siteId): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `site_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$siteId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find installation by feasibility ID
     * 
     * @param int $feasibilityId Feasibility check ID
     * @return array|null Installation record or null
     */
    public function findByFeasibilityId(int $feasibilityId): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE `feasibility_id` = ?";
        $result = DatabaseConfig::getInstance()->getResults($sql, [$feasibilityId], 'i');
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Find installations by status
     * 
     * @param string $status Installation status
     * @return array List of installations
     */
    public function findByStatus(string $status): array {
        return $this->findAll(['status' => $status], 'created_at DESC');
    }
    
    /**
     * Update installation status
     * 
     * @param int $id Installation ID
     * @param string $status New status
     * @return array|null Updated installation or null
     */
    public function updateStatus(int $id, string $status): ?array {
        if (!self::isValidStatus($status)) {
            throw new InvalidArgumentException("Invalid status: $status");
        }
        
        return $this->update($id, ['status' => $status]);
    }
}
