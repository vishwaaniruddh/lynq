<?php
/**
 * CustomFormField Model
 * Represents individual configurable fields of a custom form
 */

require_once __DIR__ . '/BaseModel.php';

class CustomFormField extends BaseModel {
    protected $table = 'custom_form_fields';
    protected $fillable = [
        'form_id', 'section_title', 'field_key', 'field_label',
        'field_type', 'placeholder', 'default_value', 'help_text',
        'is_required', 'options_json', 'validation_rules_json',
        'grid_width', 'sort_order', 'status',
        'created_at', 'updated_at'
    ];
    
    // Field type constants
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_PHONE = 'phone';
    const TYPE_EMAIL = 'email';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_SELECT = 'select';
    const TYPE_RADIO = 'radio';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_FILE = 'file';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPE_HEADING = 'heading';
    
    public static function getFieldTypes(): array {
        return [
            self::TYPE_TEXT => 'Text (Single line)',
            self::TYPE_NUMBER => 'Number',
            self::TYPE_PHONE => 'Contact / Phone Number',
            self::TYPE_EMAIL => 'Email',
            self::TYPE_TEXTAREA => 'Text Area (Multi-line)',
            self::TYPE_SELECT => 'Dropdown (Select)',
            self::TYPE_RADIO => 'Radio Buttons',
            self::TYPE_CHECKBOX => 'Checkboxes',
            self::TYPE_FILE => 'File / Photo Upload',
            self::TYPE_DATE => 'Date',
            self::TYPE_DATETIME => 'Date & Time',
            self::TYPE_HEADING => 'Section Header / Divider'
        ];
    }
    
    public static function isValidFieldType(string $type): bool {
        return array_key_exists($type, self::getFieldTypes());
    }
}
