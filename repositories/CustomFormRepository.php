<?php
/**
 * CustomFormRepository
 * Data access repository for dynamic custom forms and fields mapped to Projects
 */

require_once __DIR__ . '/BaseRepository.php';

class CustomFormRepository extends BaseRepository {
    protected $table = 'custom_forms';
    protected $primaryKey = 'id';
    protected $companyIdColumn = 'company_id';
    protected $applyCompanyFilter = false;
    
    /**
     * Find all Custom Forms with pagination and filters
     */
    public function findAllPaginated(array $filters = [], ?int $companyId = null): array {
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, min(100, (int)$filters['limit'])) : 10;
        $offset = ($page - 1) * $limit;
        $orderBy = $filters['orderBy'] ?? 'id';
        $orderDir = strtoupper($filters['orderDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        $whereClause = ["cf.`deleted_at` IS NULL"];
        $params = [];
        $types = '';
        
        if ($companyId !== null) {
            $whereClause[] = "(cf.`company_id` = ? OR cf.`company_id` IS NULL)";
            $params[] = $companyId;
            $types .= 'i';
        }
        
        // Purpose filter
        if (!empty($filters['purpose'])) {
            $whereClause[] = "cf.`purpose` = ?";
            $params[] = $filters['purpose'];
            $types .= 's';
        }
        
        // Project filter
        if (isset($filters['project_id']) && $filters['project_id'] !== '') {
            if ($filters['project_id'] === 'global' || $filters['project_id'] === '0') {
                $whereClause[] = "(cf.`project_id` IS NULL OR cf.`project_id` = 0)";
            } else {
                $whereClause[] = "cf.`project_id` = ?";
                $params[] = (int)$filters['project_id'];
                $types .= 'i';
            }
        }
        
        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $whereClause[] = "cf.`status` = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $whereClause[] = "(cf.`form_name` LIKE ? OR cf.`form_code` LIKE ? OR cf.`description` LIKE ? OR p.`name` LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ssss';
        }
        
        $whereSQL = ' WHERE ' . implode(' AND ', $whereClause);
        
        // Whitelist allowed order columns
        $allowedOrderColumns = ['id', 'form_code', 'form_name', 'purpose', 'project_id', 'status', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns)) {
            $orderBy = 'id';
        }
        
        $countSQL = "SELECT COUNT(*) as total 
                     FROM `{$this->table}` cf 
                     LEFT JOIN `projects` p ON cf.`project_id` = p.`id`" . $whereSQL;
        $countResult = $this->db->getResults($countSQL, $params, $types);
        $total = (int)($countResult[0]['total'] ?? 0);
        
        $dataSQL = "SELECT cf.*, 
                           COALESCE(p.`name`, 'Global / Default') as project_name,
                           p.`code` as project_code,
                           (SELECT COUNT(*) FROM `custom_form_fields` WHERE `form_id` = cf.`id` AND `status` = 'active') as field_count,
                           CONCAT(COALESCE(u.`first_name`, 'System'), ' ', COALESCE(u.`last_name`, '')) as created_by_name
                    FROM `{$this->table}` cf
                    LEFT JOIN `projects` p ON cf.`project_id` = p.`id`
                    LEFT JOIN `users` u ON cf.`created_by` = u.`id`" .
                    $whereSQL .
                    " ORDER BY cf.`$orderBy` $orderDir LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataTypes = $types . 'ii';
        
        $data = $this->db->getResults($dataSQL, $dataParams, $dataTypes);
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $total > 0 ? ceil($total / $limit) : 0
        ];
    }
    
    /**
     * Find Form with complete field details
     */
    public function findByIdWithFields(int $id): ?array {
        $sql = "SELECT cf.*, 
                       COALESCE(p.`name`, 'Global / Default') as project_name,
                       p.`code` as project_code,
                       CONCAT(COALESCE(u.`first_name`, 'System'), ' ', COALESCE(u.`last_name`, '')) as created_by_name
                FROM `{$this->table}` cf
                LEFT JOIN `projects` p ON cf.`project_id` = p.`id`
                LEFT JOIN `users` u ON cf.`created_by` = u.`id`
                WHERE cf.`id` = ? AND cf.`deleted_at` IS NULL";
        
        $results = $this->db->getResults($sql, [$id], 'i');
        if (empty($results)) {
            return null;
        }
        
        $form = $results[0];
        $form['fields'] = $this->getFormFields($id);
        return $form;
    }
    
    /**
     * Get fields for a form
     */
    public function getFormFields(int $formId): array {
        $sql = "SELECT * FROM `custom_form_fields` 
                WHERE `form_id` = ? AND `status` = 'active'
                ORDER BY `sort_order` ASC, `id` ASC";
        
        $fields = $this->db->getResults($sql, [$formId], 'i');
        
        foreach ($fields as &$field) {
            $field['options'] = !empty($field['options_json']) ? json_decode($field['options_json'], true) : [];
            $field['validation_rules'] = !empty($field['validation_rules_json']) ? json_decode($field['validation_rules_json'], true) : [];
        }
        
        return $fields;
    }
    
    /**
     * Create Form and its fields
     */
    public function createForm(array $formData, array $fieldsData = []): int {
        if (empty($formData['form_name'])) {
            throw new Exception("Form name is required");
        }
        if (empty($formData['purpose'])) {
            throw new Exception("Purpose is required");
        }
        
        // Generate or validate form_code
        $formCode = $formData['form_code'] ?? null;
        if (empty($formCode)) {
            $prefix = strtoupper($formData['purpose']);
            $projSuffix = !empty($formData['project_id']) ? 'PROJ_' . $formData['project_id'] : 'GLOBAL';
            $formCode = 'FORM_' . $prefix . '_' . $projSuffix . '_' . date('ymdHis');
        }
        
        if ($this->codeExists($formCode)) {
            throw new Exception("A form with code '{$formCode}' already exists");
        }
        
        $now = date('Y-m-d H:i:s');
        $projectId = !empty($formData['project_id']) ? (int)$formData['project_id'] : null;
        $isDefault = !empty($formData['is_default']) ? 1 : 0;
        
        $sql = "INSERT INTO `{$this->table}` 
                (`form_code`, `form_name`, `purpose`, `project_id`, `description`, `version`, `status`, `is_default`, `company_id`, `created_by`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->getConnection()->error);
        }
        
        $version = (int)($formData['version'] ?? 1);
        $status = $formData['status'] ?? 'active';
        $desc = $formData['description'] ?? null;
        $companyId = $formData['company_id'] ?? null;
        $createdBy = $formData['created_by'] ?? null;
        $formName = $formData['form_name'];
        $purpose = $formData['purpose'];
        
        $stmt->bind_param('sssisisiiiss', 
            $formCode, 
            $formName, 
            $purpose, 
            $projectId, 
            $desc, 
            $version, 
            $status, 
            $isDefault, 
            $companyId, 
            $createdBy, 
            $now, 
            $now
        );
        
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to insert form: " . $err);
        }
        
        $formId = $this->db->getConnection()->insert_id;
        $stmt->close();
        
        if (!empty($fieldsData)) {
            $this->saveFields($formId, $fieldsData);
        }
        
        return $formId;
    }
    
    /**
     * Update Form header and fields
     */
    public function updateForm(int $id, array $formData, ?array $fieldsData = null): bool {
        $existing = $this->findByIdWithFields($id);
        if (!$existing) {
            throw new Exception("Form not found");
        }
        
        if (!empty($formData['form_code']) && $formData['form_code'] !== $existing['form_code']) {
            if ($this->codeExists($formData['form_code'], $id)) {
                throw new Exception("A form with code '{$formData['form_code']}' already exists");
            }
        }
        
        $now = date('Y-m-d H:i:s');
        $projectId = !empty($formData['project_id']) ? (int)$formData['project_id'] : null;
        $isDefault = !empty($formData['is_default']) ? 1 : 0;
        
        $sql = "UPDATE `{$this->table}` SET 
                `form_code` = ?,
                `form_name` = ?,
                `purpose` = ?,
                `project_id` = ?,
                `description` = ?,
                `status` = ?,
                `is_default` = ?,
                `updated_by` = ?,
                `updated_at` = ?
                WHERE `id` = ?";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->getConnection()->error);
        }
        
        $formCode = $formData['form_code'] ?? $existing['form_code'];
        $formName = $formData['form_name'] ?? $existing['form_name'];
        $purpose = $formData['purpose'] ?? $existing['purpose'];
        $desc = array_key_exists('description', $formData) ? $formData['description'] : $existing['description'];
        $status = $formData['status'] ?? $existing['status'];
        $updatedBy = $formData['updated_by'] ?? null;
        
        $stmt->bind_param('sssissiisi', 
            $formCode,
            $formName,
            $purpose,
            $projectId,
            $desc,
            $status,
            $isDefault,
            $updatedBy,
            $now,
            $id
        );
        
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to update form: " . $err);
        }
        $stmt->close();
        
        if ($fieldsData !== null) {
            $this->saveFields($id, $fieldsData);
        }
        
        return true;
    }
    
    /**
     * Replace all fields for a form
     */
    public function saveFields(int $formId, array $fieldsData): bool {
        $delSql = "DELETE FROM `custom_form_fields` WHERE `form_id` = ?";
        $delStmt = $this->db->executeQuery($delSql, [$formId], 'i');
        $delStmt->close();
        
        if (empty($fieldsData)) {
            return true;
        }
        
        $now = date('Y-m-d H:i:s');
        $insSql = "INSERT INTO `custom_form_fields` 
                  (`form_id`, `section_title`, `field_key`, `field_label`, `field_type`, `placeholder`, `default_value`, `help_text`, `is_required`, `options_json`, `validation_rules_json`, `grid_width`, `sort_order`, `status`, `created_at`, `updated_at`) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->getConnection()->prepare($insSql);
        if (!$stmt) {
            throw new Exception("Prepare failed for fields insert: " . $this->db->getConnection()->error);
        }
        
        $sortOrder = 1;
        foreach ($fieldsData as $f) {
            $sectionTitle = !empty($f['section_title']) ? trim($f['section_title']) : 'General Information';
            $fieldKey = !empty($f['field_key']) ? preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($f['field_key']))) : 'field_' . $sortOrder;
            $fieldLabel = !empty($f['field_label']) ? trim($f['field_label']) : ucfirst(str_replace('_', ' ', $fieldKey));
            $fieldType = !empty($f['field_type']) ? trim($f['field_type']) : 'text';
            $placeholder = $f['placeholder'] ?? null;
            $defaultValue = $f['default_value'] ?? null;
            $helpText = $f['help_text'] ?? null;
            $isRequired = !empty($f['is_required']) ? 1 : 0;
            
            $optionsJson = null;
            if (!empty($f['options'])) {
                $optionsJson = is_array($f['options']) ? json_encode($f['options'], JSON_UNESCAPED_UNICODE) : $f['options'];
            } elseif (!empty($f['options_json'])) {
                $optionsJson = $f['options_json'];
            }
            
            $valRulesJson = null;
            if (!empty($f['validation_rules'])) {
                $valRulesJson = is_array($f['validation_rules']) ? json_encode($f['validation_rules'], JSON_UNESCAPED_UNICODE) : $f['validation_rules'];
            } elseif (!empty($f['validation_rules_json'])) {
                $valRulesJson = $f['validation_rules_json'];
            }
            
            $gridWidth = !empty($f['grid_width']) ? (int)$f['grid_width'] : 12;
            $status = !empty($f['status']) && in_array($f['status'], ['active', 'inactive']) ? $f['status'] : 'active';
            $order = isset($f['sort_order']) ? (int)$f['sort_order'] : $sortOrder;
            
            $stmt->bind_param(
                'isssssssissiisss',
                $formId,
                $sectionTitle,
                $fieldKey,
                $fieldLabel,
                $fieldType,
                $placeholder,
                $defaultValue,
                $helpText,
                $isRequired,
                $optionsJson,
                $valRulesJson,
                $gridWidth,
                $order,
                $status,
                $now,
                $now
            );
            
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception("Failed to insert field '{$fieldKey}': " . $err);
            }
            $sortOrder++;
        }
        
        $stmt->close();
        return true;
    }
    
    /**
     * Duplicate an existing form
     */
    public function duplicateForm(int $sourceId, array $overrides = [], int $userId = 1): int {
        $source = $this->findByIdWithFields($sourceId);
        if (!$source) {
            throw new Exception("Source form not found");
        }
        
        $newFormName = $overrides['form_name'] ?? ($source['form_name'] . ' (Copy)');
        $newPurpose = $overrides['purpose'] ?? $source['purpose'];
        $newProjectId = array_key_exists('project_id', $overrides) ? $overrides['project_id'] : $source['project_id'];
        
        $prefix = strtoupper($newPurpose);
        $projSuffix = !empty($newProjectId) ? 'PROJ_' . $newProjectId : 'GLOBAL';
        $newFormCode = 'FORM_' . $prefix . '_' . $projSuffix . '_' . date('ymdHis');
        
        $formData = [
            'form_code' => $newFormCode,
            'form_name' => $newFormName,
            'purpose' => $newPurpose,
            'project_id' => $newProjectId,
            'description' => $overrides['description'] ?? $source['description'],
            'version' => 1,
            'status' => 'active',
            'is_default' => 0,
            'company_id' => $source['company_id'],
            'created_by' => $userId
        ];
        
        return $this->createForm($formData, $source['fields']);
    }
    
    /**
     * Soft delete form
     */
    public function softDelete(int $id): bool {
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = NOW(), `updated_at` = NOW() WHERE `id` = ?";
        $stmt = $this->db->executeQuery($sql, [$id], 'i');
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }
    
    /**
     * Check if form code exists
     */
    public function codeExists(string $code, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}` WHERE `form_code` = ? AND `deleted_at` IS NULL";
        $params = [$code];
        $types = 's';
        
        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }
        
        $result = $this->db->getResults($sql, $params, $types);
        return (int)($result[0]['count'] ?? 0) > 0;
    }
}
