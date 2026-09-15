<?php
/**
 * CustomForm Model
 * Represents a custom dynamic form schema mapped to purpose and project
 */

require_once __DIR__ . '/BaseModel.php';

class CustomForm extends BaseModel {
    protected $table = 'custom_forms';
    protected $fillable = [
        'form_code', 'form_name', 'purpose', 'project_id', 'customer_id',
        'description', 'version', 'status', 'is_default',
        'company_id', 'created_by', 'updated_by',
        'created_at', 'updated_at', 'deleted_at'
    ];
    
    // Purpose constants
    const PURPOSE_SITE_ADD = 'site_add';
    const PURPOSE_FEASIBILITY = 'feasibility';
    const PURPOSE_INSTALLATION = 'installation';
    
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DRAFT = 'draft';
    
    public static function getPurposes(): array {
        return [
            self::PURPOSE_SITE_ADD => 'Site Add Form',
            self::PURPOSE_FEASIBILITY => 'Feasibility Form',
            self::PURPOSE_INSTALLATION => 'Installation Form'
        ];
    }
    
    public static function getStatuses(): array {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_DRAFT
        ];
    }
    
    /**
     * Find form with its fields
     */
    public function findWithFields(int $id): ?array {
        $sql = "SELECT cf.*, p.name as project_name, p.code as project_code,
                       CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as created_by_name
                FROM `{$this->table}` cf
                LEFT JOIN projects p ON cf.project_id = p.id
                LEFT JOIN users u ON cf.created_by = u.id
                WHERE cf.id = ? AND cf.deleted_at IS NULL";
        
        $results = DatabaseConfig::getInstance()->getResults($sql, [$id], 'i');
        if (empty($results)) {
            return null;
        }
        
        $form = $results[0];
        $form['fields'] = $this->getFields($id);
        return $form;
    }
    
    /**
     * Get fields for a form ordered by sort_order
     */
    public function getFields(int $formId): array {
        $sql = "SELECT * FROM `custom_form_fields` 
                WHERE `form_id` = ? AND `status` = 'active'
                ORDER BY `sort_order` ASC, `id` ASC";
        
        $fields = DatabaseConfig::getInstance()->getResults($sql, [$formId], 'i');
        
        // Decode JSON properties
        foreach ($fields as &$field) {
            $field['options'] = !empty($field['options_json']) ? json_decode($field['options_json'], true) : [];
            $field['validation_rules'] = !empty($field['validation_rules_json']) ? json_decode($field['validation_rules_json'], true) : [];
            $field['resolved_options'] = $this->resolveFieldOptions($field['options']);
        }
        
        return $fields;
    }
    
    /**
     * Resolve options array from static options or live CRM Master data
     */
    public function resolveFieldOptions($options): array {
        if (is_array($options) && isset($options['source']) && $options['source'] === 'master') {
            $masterKey = $options['master_key'] ?? '';
            $db = DatabaseConfig::getInstance();
            switch ($masterKey) {
                case 'banks':
                    $rows = $db->getResults("SELECT id, name FROM banks WHERE status = 1 ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'customers':
                    $rows = $db->getResults("SELECT id, name FROM customers WHERE status = 1 ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'projects':
                    $rows = $db->getResults("SELECT id, name, code FROM projects WHERE status = 1 AND deleted_at IS NULL ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'] . ($r['code'] ? " ({$r['code']})" : ''), 'value' => (string)$r['id']], $rows);
                case 'lhos':
                    $rows = $db->getResults("SELECT id, lho_name AS name FROM lhos WHERE status = 'active' ORDER BY lho_name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'countries':
                    $rows = $db->getResults("SELECT id, name FROM countries WHERE status = 'active' ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'states':
                    $rows = $db->getResults("SELECT id, name FROM states WHERE status = 'active' ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'cities':
                    $rows = $db->getResults("SELECT id, name FROM cities WHERE status = 'active' ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'zones':
                    $rows = $db->getResults("SELECT id, name FROM zones WHERE status = 'active' ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'couriers':
                    $rows = $db->getResults("SELECT id, name FROM couriers WHERE status = 1 ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
                case 'product_categories':
                    $rows = $db->getResults("SELECT id, name FROM product_categories WHERE status = 'active' ORDER BY name ASC");
                    return array_map(fn($r) => ['label' => $r['name'], 'value' => $r['name']], $rows);
            }
        }
        return is_array($options) ? (isset($options['options']) ? $options['options'] : $options) : [];
    }
    
    /**
     * Find active form schema for a specific purpose and project
     * Falls back to Global / Default form if no project-specific form is active
     */
    public function findFormForProject(string $purpose, ?int $projectId = null): ?array {
        $db = DatabaseConfig::getInstance();
        
        if ($projectId) {
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `purpose` = ? AND `project_id` = ? AND `status` = 'active' AND `deleted_at` IS NULL
                    ORDER BY `id` DESC LIMIT 1";
            $res = $db->getResults($sql, [$purpose, $projectId], 'si');
            if (!empty($res)) {
                return $this->findWithFields((int)$res[0]['id']);
            }
        }
        
        // Fallback to default/global form
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `purpose` = ? AND (`project_id` IS NULL OR `project_id` = 0) AND `status` = 'active' AND `deleted_at` IS NULL
                ORDER BY `is_default` DESC, `id` DESC LIMIT 1";
        $res = $db->getResults($sql, [$purpose], 's');
        if (!empty($res)) {
            return $this->findWithFields((int)$res[0]['id']);
        }
        
        return null;
    }
    
    /**
     * Soft delete a form
     */
    public function softDelete(int $id): bool {
        $sql = "UPDATE `{$this->table}` SET `deleted_at` = NOW(), `updated_at` = NOW() WHERE `id` = ?";
        $stmt = DatabaseConfig::getInstance()->executeQuery($sql, [$id], 'i');
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }
}
