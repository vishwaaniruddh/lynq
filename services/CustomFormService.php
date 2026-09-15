<?php
/**
 * CustomFormService
 * Business logic service for Custom Forms & Dynamic Form Builder mapped to Projects
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/CustomFormRepository.php';
require_once __DIR__ . '/../models/CustomForm.php';
require_once __DIR__ . '/../models/CustomFormField.php';

class CustomFormService {
    private $repository;
    private $db;
    
    public function __construct() {
        $this->repository = new CustomFormRepository();
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Get paginated list of custom forms
     */
    public function list(array $filters = [], ?int $companyId = null): array {
        try {
            $result = $this->repository->findAllPaginated($filters, $companyId);
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'LIST_ERROR'
            ];
        }
    }
    
    /**
     * Get form by ID with fields
     */
    public function get(int $id): array {
        try {
            $form = $this->repository->findByIdWithFields($id);
            if (!$form) {
                return [
                    'success' => false,
                    'message' => 'Form not found',
                    'code' => 'NOT_FOUND'
                ];
            }
            return [
                'success' => true,
                'data' => $form
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'GET_ERROR'
            ];
        }
    }
    
    /**
     * Create a new custom form with fields
     */
    public function create(array $input, int $userId, ?int $companyId = null): array {
        $validation = $this->validateFormData($input);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        $fields = $input['fields'] ?? [];
        $fieldsValidation = $this->validateFieldsData($fields);
        if (!$fieldsValidation['isValid']) {
            return [
                'success' => false,
                'message' => 'Fields validation failed',
                'errors' => $fieldsValidation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        try {
            $formData = [
                'form_name' => trim($input['form_name']),
                'form_code' => !empty($input['form_code']) ? trim($input['form_code']) : null,
                'purpose' => trim($input['purpose']),
                'project_id' => !empty($input['project_id']) ? (int)$input['project_id'] : null,
                'description' => $input['description'] ?? null,
                'version' => !empty($input['version']) ? (int)$input['version'] : 1,
                'status' => !empty($input['status']) ? $input['status'] : 'active',
                'is_default' => !empty($input['is_default']) ? 1 : 0,
                'company_id' => $companyId,
                'created_by' => $userId
            ];
            
            $formId = $this->repository->createForm($formData, $fields);
            $newForm = $this->repository->findByIdWithFields($formId);
            
            return [
                'success' => true,
                'message' => 'Custom Form created successfully',
                'data' => $newForm
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing custom form
     */
    public function update(int $id, array $input, int $userId): array {
        $existing = $this->repository->findByIdWithFields($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Form not found',
                'code' => 'NOT_FOUND'
            ];
        }
        
        $validation = $this->validateFormData($input, $id);
        if (!$validation['isValid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'code' => 'VALIDATION_ERROR'
            ];
        }
        
        $fields = isset($input['fields']) && is_array($input['fields']) ? $input['fields'] : null;
        if ($fields !== null) {
            $fieldsValidation = $this->validateFieldsData($fields);
            if (!$fieldsValidation['isValid']) {
                return [
                    'success' => false,
                    'message' => 'Fields validation failed',
                    'errors' => $fieldsValidation['errors'],
                    'code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        try {
            $formData = [
                'form_name' => trim($input['form_name']),
                'form_code' => !empty($input['form_code']) ? trim($input['form_code']) : $existing['form_code'],
                'purpose' => trim($input['purpose']),
                'project_id' => !empty($input['project_id']) ? (int)$input['project_id'] : null,
                'description' => $input['description'] ?? null,
                'status' => !empty($input['status']) ? $input['status'] : $existing['status'],
                'is_default' => !empty($input['is_default']) ? 1 : 0,
                'updated_by' => $userId
            ];
            
            $this->repository->updateForm($id, $formData, $fields);
            $updatedForm = $this->repository->findByIdWithFields($id);
            
            return [
                'success' => true,
                'message' => 'Custom Form updated successfully',
                'data' => $updatedForm
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Duplicate a form
     */
    public function duplicate(int $id, array $overrides, int $userId): array {
        try {
            $newId = $this->repository->duplicateForm($id, $overrides, $userId);
            $cloned = $this->repository->findByIdWithFields($newId);
            return [
                'success' => true,
                'message' => 'Form duplicated successfully',
                'data' => $cloned
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'DUPLICATE_ERROR'
            ];
        }
    }
    
    /**
     * Soft delete a form
     */
    public function delete(int $id, int $userId): array {
        try {
            $existing = $this->repository->findByIdWithFields($id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => 'Form not found',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            $this->repository->softDelete($id);
            return [
                'success' => true,
                'message' => 'Form deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Get lookup options (active projects, purposes, field types, and master data sources)
     */
    public function getFormOptions(): array {
        $projects = $this->db->getResults("SELECT id, name, code FROM `projects` WHERE `status` = 1 AND `deleted_at` IS NULL ORDER BY `name` ASC");
        $banks = $this->db->getResults("SELECT id, name FROM `banks` WHERE `status` = 1 ORDER BY `name` ASC");
        $customers = $this->db->getResults("SELECT id, name FROM `customers` WHERE `status` = 1 ORDER BY `name` ASC");
        $lhos = $this->db->getResults("SELECT id, lho_name AS name FROM `lhos` WHERE `status` = 'active' ORDER BY `lho_name` ASC");
        $countries = $this->db->getResults("SELECT id, name FROM `countries` WHERE `status` = 'active' ORDER BY `name` ASC");
        $states = $this->db->getResults("SELECT id, name FROM `states` WHERE `status` = 'active' ORDER BY `name` ASC");
        $cities = $this->db->getResults("SELECT id, name FROM `cities` WHERE `status` = 'active' ORDER BY `name` ASC");
        $zones = $this->db->getResults("SELECT id, name FROM `zones` WHERE `status` = 'active' ORDER BY `name` ASC");
        $couriers = $this->db->getResults("SELECT id, name FROM `couriers` WHERE `status` = 1 ORDER BY `name` ASC");
        $productCategories = $this->db->getResults("SELECT id, name FROM `product_categories` WHERE `status` = 'active' ORDER BY `name` ASC");
        
        return [
            'success' => true,
            'data' => [
                'purposes' => CustomForm::getPurposes(),
                'statuses' => CustomForm::getStatuses(),
                'field_types' => CustomFormField::getFieldTypes(),
                'projects' => $projects,
                'master_sources' => [
                    'banks' => ['label' => 'Bank Master', 'count' => count($banks), 'items' => $banks],
                    'customers' => ['label' => 'Customer Master', 'count' => count($customers), 'items' => $customers],
                    'projects' => ['label' => 'Project Master', 'count' => count($projects), 'items' => $projects],
                    'lhos' => ['label' => 'LHO Master', 'count' => count($lhos), 'items' => $lhos],
                    'countries' => ['label' => 'Country Master', 'count' => count($countries), 'items' => $countries],
                    'states' => ['label' => 'State Master', 'count' => count($states), 'items' => $states],
                    'cities' => ['label' => 'City Master', 'count' => count($cities), 'items' => $cities],
                    'zones' => ['label' => 'Zone Master', 'count' => count($zones), 'items' => $zones],
                    'couriers' => ['label' => 'Courier Master', 'count' => count($couriers), 'items' => $couriers],
                    'product_categories' => ['label' => 'Product Categories', 'count' => count($productCategories), 'items' => $productCategories]
                ]
            ]
        ];
    }
    
    /**
     * Validate form header data
     */
    private function validateFormData(array $data, ?int $excludeId = null): array {
        $errors = [];
        
        if (empty($data['form_name']) || trim($data['form_name']) === '') {
            $errors['form_name'] = 'Form Name is required';
        } elseif (strlen($data['form_name']) > 255) {
            $errors['form_name'] = 'Form Name must not exceed 255 characters';
        }
        
        if (empty($data['purpose']) || trim($data['purpose']) === '') {
            $errors['purpose'] = 'Purpose is required';
        }
        
        if (!empty($data['form_code'])) {
            if ($this->repository->codeExists(trim($data['form_code']), $excludeId)) {
                $errors['form_code'] = 'Form Code is already in use';
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate fields array
     */
    private function validateFieldsData(array $fields): array {
        $errors = [];
        $keysSeen = [];
        
        foreach ($fields as $idx => $f) {
            $fieldKey = !empty($f['field_key']) ? trim($f['field_key']) : '';
            $fieldLabel = !empty($f['field_label']) ? trim($f['field_label']) : '';
            $fieldType = !empty($f['field_type']) ? trim($f['field_type']) : 'text';
            
            if ($fieldType !== CustomFormField::TYPE_HEADING && empty($fieldKey)) {
                $errors["field_{$idx}_key"] = "Field key is required for field #" . ($idx + 1);
            } elseif (!empty($fieldKey)) {
                if (in_array(strtolower($fieldKey), $keysSeen)) {
                    $errors["field_{$idx}_key"] = "Duplicate field key '{$fieldKey}' found";
                }
                $keysSeen[] = strtolower($fieldKey);
            }
            
            if (empty($fieldLabel)) {
                $errors["field_{$idx}_label"] = "Field label is required for field #" . ($idx + 1);
            }
            
            // Check choice types have either master source configured or at least one custom option
            if (in_array($fieldType, [CustomFormField::TYPE_SELECT, CustomFormField::TYPE_RADIO, CustomFormField::TYPE_CHECKBOX])) {
                $options = !empty($f['options']) ? $f['options'] : (!empty($f['options_json']) ? json_decode($f['options_json'], true) : []);
                
                if (is_array($options) && isset($options['source']) && $options['source'] === 'master') {
                    if (empty($options['master_key'])) {
                        $errors["field_{$idx}_options"] = "Master data source must be selected for '{$fieldLabel}'";
                    }
                } elseif (empty($options) || (is_array($options) && count($options) === 0)) {
                    $errors["field_{$idx}_options"] = "At least one choice option is required for '{$fieldLabel}' ({$fieldType})";
                }
            }
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }
}
