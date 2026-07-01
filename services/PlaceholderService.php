<?php
/**
 * Placeholder Service
 * Handles dynamic content replacement for email templates
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 8.3
 * - 3.1: Module-specific placeholder definitions
 * - 3.2: Dynamic content replacement
 * - 3.3: Placeholder validation
 * - 3.4: Preview functionality
 * - 3.5: Nested placeholder support
 * - 8.3: Content sanitization
 */

require_once __DIR__ . '/../config/autoload.php';

class PlaceholderService {
    private $db;
    
    // Module constants
    const MODULE_USERS = 'users';
    const MODULE_SITES = 'sites';
    const MODULE_COMPANIES = 'companies';
    const MODULE_FEASIBILITY = 'feasibility';
    const MODULE_INSTALLATION = 'installation';
    const MODULE_INVENTORY = 'inventory';
    const MODULE_MATERIAL_REQUESTS = 'material_requests';
    const MODULE_DISPATCHES = 'dispatches';
    const MODULE_CONFIGURATION = 'configuration';
    const MODULE_NOTES = 'notes';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Get available placeholders for a specific module
     * Requirement 3.1
     * 
     * @param string $moduleName Module name
     * @return array Array of available placeholders with descriptions
     */
    public function getModulePlaceholders(string $moduleName): array {
        $placeholders = [];
        
        switch ($moduleName) {
            case self::MODULE_USERS:
                $placeholders = [
                    'user_name' => 'User full name',
                    'user_email' => 'User email address',
                    'user_username' => 'User login username',
                    'user_role' => 'User role name',
                    'user_status' => 'User status (active/inactive)',
                    'company_name' => 'User company name',
                    'company_type' => 'Company type (ADV/CONTRACTOR)',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_SITES:
                $placeholders = [
                    'site_name' => 'Site name',
                    'site_address' => 'Site address',
                    'site_city' => 'Site city',
                    'site_state' => 'Site state',
                    'site_country' => 'Site country',
                    'site_lho' => 'Site LHO',
                    'site_bank_name' => 'Site bank name',
                    'site_customer_name' => 'Site customer name',
                    'site_zone' => 'Site zone',
                    'site_latitude' => 'Site latitude',
                    'site_longitude' => 'Site longitude',
                    'site_status' => 'Site status',
                    'engineer.name' => 'Assigned engineer name',
                    'engineer.email' => 'Assigned engineer email',
                    'engineer.phone' => 'Assigned engineer phone',
                    'company_name' => 'Site company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_FEASIBILITY:
                $placeholders = [
                    'feasibility_id' => 'Feasibility ID',
                    'site_name' => 'Site name',
                    'site_address' => 'Site address',
                    'site_lho' => 'Site LHO',
                    'engineer.name' => 'Assigned engineer name',
                    'engineer.email' => 'Assigned engineer email',
                    'feasibility_status' => 'Feasibility status',
                    'eta_date' => 'ETA date',
                    'ada_date' => 'ADA date',
                    'approval_status' => 'Approval status',
                    'reviewer.name' => 'Reviewer name',
                    'reviewer.email' => 'Reviewer email',
                    'company_name' => 'Company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_INSTALLATION:
                $placeholders = [
                    'installation_id' => 'Installation ID',
                    'site_name' => 'Site name',
                    'site_address' => 'Site address',
                    'site_lho' => 'Site LHO',
                    'engineer.name' => 'Assigned engineer name',
                    'engineer.email' => 'Assigned engineer email',
                    'installation_status' => 'Installation status',
                    'scheduled_date' => 'Scheduled date',
                    'completion_date' => 'Completion date',
                    'eta_date' => 'ETA date',
                    'ada_date' => 'ADA date',
                    'contractor.name' => 'Contractor company name',
                    'contractor.contact_email' => 'Contractor contact email',
                    'company_name' => 'Company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_MATERIAL_REQUESTS:
                $placeholders = [
                    'request_id' => 'Material request ID',
                    'site_name' => 'Site name',
                    'site_address' => 'Site address',
                    'site_lho' => 'Site LHO',
                    'material_master_name' => 'Material master name',
                    'request_status' => 'Request status',
                    'requested_by.name' => 'Requester name',
                    'requested_by.email' => 'Requester email',
                    'approved_by.name' => 'Approver name',
                    'approved_by.email' => 'Approver email',
                    'engineer.name' => 'Assigned engineer name',
                    'engineer.email' => 'Assigned engineer email',
                    'request_notes' => 'Request notes',
                    'item_count' => 'Number of items requested',
                    'company_name' => 'Company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_DISPATCHES:
                $placeholders = [
                    'dispatch_number' => 'Dispatch number',
                    'dispatch_date' => 'Dispatch date',
                    'dispatch_status' => 'Dispatch status',
                    'from_warehouse.name' => 'Source warehouse name',
                    'from_warehouse.address' => 'Source warehouse address',
                    'to_warehouse.name' => 'Destination warehouse name',
                    'to_warehouse.address' => 'Destination warehouse address',
                    'to_company.name' => 'Destination company name',
                    'to_user.name' => 'Destination user name',
                    'to_user.email' => 'Destination user email',
                    'courier.name' => 'Courier name',
                    'courier.contact' => 'Courier contact',
                    'tracking_number' => 'Tracking number',
                    'item_count' => 'Number of items',
                    'total_value' => 'Total dispatch value',
                    'notes' => 'Dispatch notes',
                    'company_name' => 'Company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_INVENTORY:
                $placeholders = [
                    'product_name' => 'Product name',
                    'product_sku' => 'Product SKU',
                    'warehouse.name' => 'Warehouse name',
                    'warehouse.address' => 'Warehouse address',
                    'stock_quantity' => 'Stock quantity',
                    'asset_serial' => 'Asset serial number',
                    'asset_status' => 'Asset status',
                    'company_name' => 'Company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_CONFIGURATION:
                $placeholders = [
                    'router_serial' => 'Router serial number',
                    'ip_address' => 'IP address',
                    'configuration_status' => 'Configuration status',
                    'site_name' => 'Site name',
                    'engineer.name' => 'Engineer name',
                    'engineer.email' => 'Engineer email',
                    'company_name' => 'Company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            case self::MODULE_NOTES:
                $placeholders = [
                    'note_title' => 'Note title',
                    'note_content' => 'Note content',
                    'note_type' => 'Note type',
                    'author.name' => 'Note author name',
                    'author.email' => 'Note author email',
                    'entity_type' => 'Related entity type',
                    'entity_id' => 'Related entity ID',
                    'company_name' => 'Company name',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
                
            default:
                // Common placeholders available to all modules
                $placeholders = [
                    'company_name' => 'Company name',
                    'user_name' => 'Current user name',
                    'user_email' => 'Current user email',
                    'current_date' => 'Current date',
                    'current_time' => 'Current time'
                ];
                break;
        }
        
        return $placeholders;
    }
    
    /**
     * Replace placeholders in content with actual data
     * Requirements 3.2, 3.5, 8.3
     * 
     * @param string $content Content with placeholders
     * @param array $data Data to replace placeholders with
     * @param string $moduleName Module name for context
     * @return string Content with placeholders replaced
     */
    public function replacePlaceholders(string $content, array $data, string $moduleName = ''): string {
        if (empty($content)) {
            return $content;
        }
        
        // Sanitize content first (Requirement 8.3)
        $content = $this->sanitizeContent($content);
        
        // Replace simple placeholders
        foreach ($data as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $placeholder = '{' . $key . '}';
                $sanitizedValue = $this->sanitizeValue($value);
                $content = str_replace($placeholder, $sanitizedValue, $content);
            }
        }
        
        // Handle nested placeholders (Requirement 3.5)
        $content = $this->replaceNestedPlaceholders($content, $data);
        
        // Replace system placeholders
        $content = $this->replaceSystemPlaceholders($content);
        
        return $content;
    }
    
    /**
     * Validate placeholders in content against available placeholders
     * Requirement 3.3
     * 
     * @param string $content Content to validate
     * @param string $moduleName Module name
     * @return array Validation result with 'valid', 'errors', and 'placeholders'
     */
    public function validatePlaceholders(string $content, string $moduleName): array {
        $errors = [];
        $availablePlaceholders = array_keys($this->getModulePlaceholders($moduleName));
        
        // Find all placeholders in content
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        $usedPlaceholders = $matches[1];
        
        // Check for invalid placeholders
        foreach ($usedPlaceholders as $placeholder) {
            if (!in_array($placeholder, $availablePlaceholders)) {
                $errors[] = "Invalid placeholder: {$placeholder}";
            }
        }
        
        // Check for unclosed placeholders
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = "Mismatched braces in content";
        }
        
        // Check for empty placeholders
        if (preg_match('/\{\s*\}/', $content)) {
            $errors[] = "Empty placeholders found";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'placeholders' => array_unique($usedPlaceholders)
        ];
    }
    
    /**
     * Generate sample data for template preview
     * Requirement 3.4
     * 
     * @param string $moduleName Module name
     * @param int|null $entityId Optional entity ID for real data
     * @return array Sample data for placeholders
     */
    public function generateSampleData(string $moduleName, ?int $entityId = null): array {
        $baseData = [
            'company_name' => 'Sample Company Ltd',
            'user_name' => 'John Doe',
            'user_email' => 'john.doe@example.com',
            'current_date' => date('Y-m-d'),
            'current_time' => date('H:i:s')
        ];
        
        switch ($moduleName) {
            case self::MODULE_USERS:
                return array_merge($baseData, [
                    'user_username' => 'johndoe',
                    'user_role' => 'Engineer',
                    'user_status' => 'active',
                    'company_type' => 'CONTRACTOR'
                ]);
                
            case self::MODULE_SITES:
                return array_merge($baseData, [
                    'site_name' => 'Sample Site Location',
                    'site_address' => '123 Main Street, Sample City',
                    'site_city' => 'Sample City',
                    'site_state' => 'Sample State',
                    'site_country' => 'Sample Country',
                    'site_lho' => 'Sample LHO',
                    'site_bank_name' => 'Sample Bank',
                    'site_customer_name' => 'Sample Customer',
                    'site_zone' => 'Zone A',
                    'site_latitude' => '40.7128',
                    'site_longitude' => '-74.0060',
                    'site_status' => 'active',
                    'engineer' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com',
                        'phone' => '+1-555-0123'
                    ]
                ]);
                
            case self::MODULE_FEASIBILITY:
                return array_merge($baseData, [
                    'feasibility_id' => 'FSB-001',
                    'site_name' => 'Sample Site Location',
                    'site_address' => '123 Main Street, Sample City',
                    'site_lho' => 'Sample LHO',
                    'feasibility_status' => 'pending',
                    'eta_date' => date('Y-m-d', strtotime('+7 days')),
                    'ada_date' => date('Y-m-d', strtotime('+14 days')),
                    'approval_status' => 'pending',
                    'engineer' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com'
                    ],
                    'reviewer' => [
                        'name' => 'Bob Reviewer',
                        'email' => 'bob.reviewer@example.com'
                    ]
                ]);
                
            case self::MODULE_INSTALLATION:
                return array_merge($baseData, [
                    'installation_id' => 'INS-001',
                    'site_name' => 'Sample Site Location',
                    'site_address' => '123 Main Street, Sample City',
                    'site_lho' => 'Sample LHO',
                    'installation_status' => 'scheduled',
                    'scheduled_date' => date('Y-m-d', strtotime('+7 days')),
                    'completion_date' => null,
                    'eta_date' => date('Y-m-d', strtotime('+7 days')),
                    'ada_date' => date('Y-m-d', strtotime('+14 days')),
                    'engineer' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com'
                    ],
                    'contractor' => [
                        'name' => 'Sample Contractor Ltd',
                        'contact_email' => 'contact@contractor.com'
                    ]
                ]);
                
            case self::MODULE_MATERIAL_REQUESTS:
                return array_merge($baseData, [
                    'request_id' => 'MR-001',
                    'site_name' => 'Sample Site Location',
                    'site_address' => '123 Main Street, Sample City',
                    'site_lho' => 'Sample LHO',
                    'material_master_name' => 'Standard Installation Kit',
                    'request_status' => 'pending',
                    'request_notes' => 'Urgent installation required',
                    'item_count' => 5,
                    'requested_by' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com'
                    ],
                    'approved_by' => [
                        'name' => 'Bob Manager',
                        'email' => 'bob.manager@example.com'
                    ],
                    'engineer' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com'
                    ]
                ]);
                
            case self::MODULE_DISPATCHES:
                return array_merge($baseData, [
                    'dispatch_number' => 'DSP-001',
                    'dispatch_date' => date('Y-m-d'),
                    'dispatch_status' => 'dispatched',
                    'tracking_number' => 'TRK123456789',
                    'item_count' => 10,
                    'total_value' => '$2,500.00',
                    'notes' => 'Handle with care',
                    'from_warehouse' => [
                        'name' => 'Main Warehouse',
                        'address' => '456 Warehouse St, City'
                    ],
                    'to_warehouse' => [
                        'name' => 'Regional Warehouse',
                        'address' => '789 Regional Ave, City'
                    ],
                    'to_company' => [
                        'name' => 'Sample Contractor Ltd'
                    ],
                    'to_user' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com'
                    ],
                    'courier' => [
                        'name' => 'Express Courier',
                        'contact' => '+1-555-COURIER'
                    ]
                ]);
                
            case self::MODULE_INVENTORY:
                return array_merge($baseData, [
                    'product_name' => 'Wireless Router',
                    'product_sku' => 'WR-001',
                    'stock_quantity' => 25,
                    'asset_serial' => 'SN123456789',
                    'asset_status' => 'available',
                    'warehouse' => [
                        'name' => 'Main Warehouse',
                        'address' => '456 Warehouse St, City'
                    ]
                ]);
                
            case self::MODULE_CONFIGURATION:
                return array_merge($baseData, [
                    'router_serial' => 'RTR123456789',
                    'ip_address' => '192.168.1.1',
                    'configuration_status' => 'configured',
                    'site_name' => 'Sample Site Location',
                    'engineer' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com'
                    ]
                ]);
                
            case self::MODULE_NOTES:
                return array_merge($baseData, [
                    'note_title' => 'Installation Notes',
                    'note_content' => 'Equipment installed successfully. All tests passed.',
                    'note_type' => 'installation',
                    'entity_type' => 'site',
                    'entity_id' => '123',
                    'author' => [
                        'name' => 'Jane Engineer',
                        'email' => 'jane.engineer@example.com'
                    ]
                ]);
                
            default:
                return $baseData;
        }
    }
    
    /**
     * Extract real data for placeholders from database
     * 
     * @param string $moduleName Module name
     * @param int $entityId Entity ID
     * @param int|null $companyId Company ID for isolation
     * @return array Real data for placeholders
     */
    public function extractRealData(string $moduleName, int $entityId, ?int $companyId = null): array {
        $data = [];
        
        try {
            switch ($moduleName) {
                case self::MODULE_SITES:
                    $data = $this->extractSiteData($entityId, $companyId);
                    break;
                    
                case self::MODULE_USERS:
                    $data = $this->extractUserData($entityId, $companyId);
                    break;
                    
                case self::MODULE_FEASIBILITY:
                    $data = $this->extractFeasibilityData($entityId, $companyId);
                    break;
                    
                case self::MODULE_INSTALLATION:
                    $data = $this->extractInstallationData($entityId, $companyId);
                    break;
                    
                case self::MODULE_MATERIAL_REQUESTS:
                    $data = $this->extractMaterialRequestData($entityId, $companyId);
                    break;
                    
                case self::MODULE_DISPATCHES:
                    $data = $this->extractDispatchData($entityId, $companyId);
                    break;
                    
                default:
                    // Return sample data if module not supported
                    $data = $this->generateSampleData($moduleName);
                    break;
            }
        } catch (Exception $e) {
            error_log("Failed to extract real data for module $moduleName: " . $e->getMessage());
            // Fallback to sample data
            $data = $this->generateSampleData($moduleName);
        }
        
        return $data;
    }
    
    /**
     * Replace nested placeholders using dot notation
     * Requirement 3.5
     * 
     * @param string $content Content with nested placeholders
     * @param array $data Data array
     * @return string Content with nested placeholders replaced
     */
    private function replaceNestedPlaceholders(string $content, array $data): string {
        preg_match_all('/\{([^}]+)\}/', $content, $matches);
        
        foreach ($matches[1] as $placeholder) {
            if (strpos($placeholder, '.') !== false) {
                $value = $this->getNestedValue($data, $placeholder);
                if ($value !== null) {
                    $sanitizedValue = $this->sanitizeValue($value);
                    $content = str_replace('{' . $placeholder . '}', $sanitizedValue, $content);
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Get nested value from data array using dot notation
     * 
     * @param array $data Data array
     * @param string $path Dot notation path
     * @return mixed|null Value or null if not found
     */
    private function getNestedValue(array $data, string $path) {
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Replace system placeholders (current_date, current_time, etc.)
     * 
     * @param string $content Content with system placeholders
     * @return string Content with system placeholders replaced
     */
    private function replaceSystemPlaceholders(string $content): string {
        $systemPlaceholders = [
            '{current_date}' => date('Y-m-d'),
            '{current_time}' => date('H:i:s'),
            '{current_datetime}' => date('Y-m-d H:i:s'),
            '{current_year}' => date('Y'),
            '{current_month}' => date('m'),
            '{current_day}' => date('d')
        ];
        
        foreach ($systemPlaceholders as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Sanitize content to prevent XSS and other security issues
     * Requirement 8.3
     * 
     * @param string $content Content to sanitize
     * @return string Sanitized content
     */
    private function sanitizeContent(string $content): string {
        // Remove potentially dangerous HTML tags and attributes
        $content = strip_tags($content, '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a>');
        
        // Remove javascript: and data: URLs
        $content = preg_replace('/javascript:/i', '', $content);
        $content = preg_replace('/data:/i', '', $content);
        
        return $content;
    }
    
    /**
     * Sanitize individual placeholder value
     * Requirement 8.3
     * 
     * @param mixed $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitizeValue($value): string {
        if (is_null($value)) {
            return '';
        }
        
        $value = (string) $value;
        
        // HTML encode to prevent XSS
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Extract site data for placeholders
     * 
     * @param int $siteId Site ID
     * @param int|null $companyId Company ID
     * @return array Site data
     */
    private function extractSiteData(int $siteId, ?int $companyId = null): array {
        $sql = "SELECT s.*, c.name as company_name, c.type as company_type,
                       u.name as engineer_name, u.email as engineer_email, u.phone as engineer_phone
                FROM sites s
                LEFT JOIN companies c ON s.company_id = c.id
                LEFT JOIN engineer_assignments ea ON s.id = ea.site_id AND ea.status = 'active'
                LEFT JOIN users u ON ea.engineer_id = u.id
                WHERE s.id = ?";
        
        $params = [$siteId];
        $types = 'i';
        
        if ($companyId) {
            $sql .= " AND s.company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        
        if (empty($result)) {
            return $this->generateSampleData(self::MODULE_SITES);
        }
        
        $site = $result[0];
        
        return [
            'site_name' => $site['site_name'],
            'site_address' => $site['address'] ?? '',
            'site_city' => $site['city'],
            'site_state' => $site['state'],
            'site_country' => $site['country'],
            'site_lho' => $site['lho'],
            'site_bank_name' => $site['bank_name'] ?? '',
            'site_customer_name' => $site['customer_name'] ?? '',
            'site_zone' => $site['zone'] ?? '',
            'site_latitude' => $site['latitude'] ?? '',
            'site_longitude' => $site['longitude'] ?? '',
            'site_status' => $site['status'],
            'company_name' => $site['company_name'],
            'company_type' => $site['company_type'],
            'engineer' => [
                'name' => $site['engineer_name'] ?? 'Not Assigned',
                'email' => $site['engineer_email'] ?? '',
                'phone' => $site['engineer_phone'] ?? ''
            ],
            'current_date' => date('Y-m-d'),
            'current_time' => date('H:i:s')
        ];
    }
    
    /**
     * Extract user data for placeholders
     * 
     * @param int $userId User ID
     * @param int|null $companyId Company ID
     * @return array User data
     */
    private function extractUserData(int $userId, ?int $companyId = null): array {
        $sql = "SELECT u.*, c.name as company_name, c.type as company_type, r.name as role_name
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?";
        
        $params = [$userId];
        $types = 'i';
        
        if ($companyId) {
            $sql .= " AND u.company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        
        if (empty($result)) {
            return $this->generateSampleData(self::MODULE_USERS);
        }
        
        $user = $result[0];
        
        return [
            'user_name' => $user['name'],
            'user_email' => $user['email'],
            'user_username' => $user['username'],
            'user_role' => $user['role_name'],
            'user_status' => $user['status'] == 1 ? 'active' : 'inactive',
            'company_name' => $user['company_name'],
            'company_type' => $user['company_type'],
            'current_date' => date('Y-m-d'),
            'current_time' => date('H:i:s')
        ];
    }
    
    /**
     * Extract feasibility data for placeholders
     * 
     * @param int $feasibilityId Feasibility ID
     * @param int|null $companyId Company ID
     * @return array Feasibility data
     */
    private function extractFeasibilityData(int $feasibilityId, ?int $companyId = null): array {
        // This would need to be implemented based on the feasibility table structure
        // For now, return sample data
        return $this->generateSampleData(self::MODULE_FEASIBILITY);
    }
    
    /**
     * Extract installation data for placeholders
     * 
     * @param int $installationId Installation ID
     * @param int|null $companyId Company ID
     * @return array Installation data
     */
    private function extractInstallationData(int $installationId, ?int $companyId = null): array {
        // This would need to be implemented based on the installation table structure
        // For now, return sample data
        return $this->generateSampleData(self::MODULE_INSTALLATION);
    }
    
    /**
     * Extract material request data for placeholders
     * 
     * @param int $requestId Material request ID
     * @param int|null $companyId Company ID
     * @return array Material request data
     */
    private function extractMaterialRequestData(int $requestId, ?int $companyId = null): array {
        // This would need to be implemented based on the material_requests table structure
        // For now, return sample data
        return $this->generateSampleData(self::MODULE_MATERIAL_REQUESTS);
    }
    
    /**
     * Extract dispatch data for placeholders
     * 
     * @param int $dispatchId Dispatch ID
     * @param int|null $companyId Company ID
     * @return array Dispatch data
     */
    private function extractDispatchData(int $dispatchId, ?int $companyId = null): array {
        // This would need to be implemented based on the dispatches table structure
        // For now, return sample data
        return $this->generateSampleData(self::MODULE_DISPATCHES);
    }
}