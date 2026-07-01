<?php
/**
 * Company Isolation Service
 * Handles multi-tenancy and company-based data isolation
 * 
 * This service ensures that users can only access data within their authorized scope:
 * - ADV users can access all companies
 * - Contractor users can only access their own company's data
 */

require_once __DIR__ . '/../config/autoload.php';

class CompanyIsolationService {
    private $db;
    private $userModel;
    private $companyModel;
    
    // Company types
    const COMPANY_TYPE_ADV = 'ADV';
    const COMPANY_TYPE_CONTRACTOR = 'CONTRACTOR';
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->userModel = new User();
        $this->companyModel = new Company();
    }
    
    /**
     * Check if a user can access data from a specific company
     * ADV users can access all companies, contractors only their own
     */
    public function canAccessCompany($userId, $targetCompanyId) {
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user) {
            $this->logAccessAttempt($userId, $targetCompanyId, 'DENIED', 'User not found');
            return false;
        }
        
        // ADV users can access all companies
        if ($user['company_type'] === self::COMPANY_TYPE_ADV) {
            return true;
        }
        
        // Contractor users can only access their own company
        $canAccess = (int)$user['company_id'] === (int)$targetCompanyId;
        
        if (!$canAccess) {
            $this->logAccessAttempt($userId, $targetCompanyId, 'DENIED', 'Cross-company access attempt');
        }
        
        return $canAccess;
    }
    
    /**
     * Get the list of company IDs a user can access
     */
    public function getAccessibleCompanyIds($userId) {
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user) {
            return [];
        }
        
        // ADV users can access all companies
        if ($user['company_type'] === self::COMPANY_TYPE_ADV) {
            $companies = $this->companyModel->findAll();
            return array_column($companies, 'id');
        }
        
        // Contractor users can only access their own company
        return [(int)$user['company_id']];
    }
    
    /**
     * Get accessible companies for a user (full company records)
     */
    public function getAccessibleCompanies($userId) {
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user) {
            return [];
        }
        
        // ADV users can access all companies
        if ($user['company_type'] === self::COMPANY_TYPE_ADV) {
            return $this->companyModel->findAll([], 'name');
        }
        
        // Contractor users can only access their own company
        $company = $this->companyModel->find($user['company_id']);
        return $company ? [$company] : [];
    }
    
    /**
     * Filter a SQL query to only include data from accessible companies
     * Returns the WHERE clause addition and parameters
     */
    public function getCompanyFilterClause($userId, $companyIdColumn = 'company_id') {
        $user = $this->userModel->findWithRelations($userId);
        
        if (!$user) {
            // No user found - return impossible condition
            return [
                'clause' => "$companyIdColumn = -1",
                'params' => [],
                'types' => ''
            ];
        }
        
        // ADV users see all - no filter needed
        if ($user['company_type'] === self::COMPANY_TYPE_ADV) {
            return [
                'clause' => '1=1',
                'params' => [],
                'types' => ''
            ];
        }
        
        // Contractor users only see their company
        return [
            'clause' => "$companyIdColumn = ?",
            'params' => [(int)$user['company_id']],
            'types' => 'i'
        ];
    }
    
    /**
     * Validate that a user operation targets a valid company
     * Used before create/update/delete operations
     */
    public function validateCompanyAccess($userId, $targetCompanyId) {
        if (!$this->canAccessCompany($userId, $targetCompanyId)) {
            throw new CompanyAccessDeniedException(
                "User $userId does not have access to company $targetCompanyId"
            );
        }
        return true;
    }
    
    /**
     * Check if user is from ADV company
     */
    public function isAdvUser($userId) {
        $user = $this->userModel->findWithRelations($userId);
        return $user && strtoupper($user['company_type'] ?? '') === self::COMPANY_TYPE_ADV;
    }
    
    /**
     * Check if user is from contractor company
     */
    public function isContractorUser($userId) {
        $user = $this->userModel->findWithRelations($userId);
        return $user && strtoupper($user['company_type'] ?? '') === self::COMPANY_TYPE_CONTRACTOR;
    }
    
    /**
     * Get user's company ID
     */
    public function getUserCompanyId($userId) {
        $user = $this->userModel->find($userId);
        return $user ? (int)$user['company_id'] : null;
    }
    
    /**
     * Get user's company type
     */
    public function getUserCompanyType($userId) {
        $user = $this->userModel->findWithRelations($userId);
        return $user ? $user['company_type'] : null;
    }
    
    /**
     * Validate company membership before any user operation
     * Ensures the target user belongs to an accessible company
     */
    public function validateUserCompanyMembership($actingUserId, $targetUserId) {
        $targetUser = $this->userModel->find($targetUserId);
        
        if (!$targetUser) {
            throw new InvalidArgumentException("Target user not found");
        }
        
        return $this->validateCompanyAccess($actingUserId, $targetUser['company_id']);
    }
    
    /**
     * Log cross-company access attempts for security auditing
     */
    private function logAccessAttempt($userId, $targetCompanyId, $result, $reason = null) {
        try {
            $sql = "INSERT INTO company_access_log 
                    (user_id, target_company_id, access_result, reason, ip_address, timestamp) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            
            $stmt = $this->db->executeQuery($sql, [
                $userId, 
                $targetCompanyId, 
                $result, 
                $reason,
                $ipAddress
            ], 'iisss');
            $stmt->close();
        } catch (Exception $e) {
            // Log to error log if database logging fails
            error_log("Failed to log company access attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Get company access log for auditing
     */
    public function getAccessLog($companyId = null, $limit = 100) {
        $sql = "SELECT cal.*, u.username, c.name as target_company_name
                FROM company_access_log cal
                LEFT JOIN users u ON cal.user_id = u.id
                LEFT JOIN companies c ON cal.target_company_id = c.id";
        
        $params = [];
        $types = '';
        
        if ($companyId !== null) {
            $sql .= " WHERE cal.target_company_id = ?";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY cal.timestamp DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        return $this->db->getResults($sql, $params, $types);
    }
}

/**
 * Custom exception for company access denial
 */
class CompanyAccessDeniedException extends Exception {
    public function __construct($message = "Company access denied", $code = 403, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
