<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/CustomFormService.php';
require_once __DIR__ . '/../models/CustomForm.php';

$customFormService = new CustomFormService();
$customFormModel = new CustomForm();

echo "1. Testing Custom Form with Phone Field (Multiple Numbers enabled)...\n";
$formCode = 'TEST_PHONE_FORM_' . time();
$createRes = $customFormService->create([
    'form_name' => 'Phone Repeater Test Form',
    'form_code' => $formCode,
    'purpose' => 'site_add',
    'project_id' => 1,
    'description' => 'Test form for multi-number phone repeater',
    'status' => 'active',
    'fields' => [
        [
            'section_title' => 'Emergency Contacts',
            'field_key' => 'emergency_contact_numbers',
            'field_label' => 'Emergency Contact Numbers',
            'field_type' => 'phone',
            'is_required' => 1,
            'grid_width' => 6,
            'validation_rules' => [
                'multiple' => true,
                'strict_10_digit' => true
            ]
        ]
    ]
], 1, 1);

if (!$createRes['success']) {
    echo "FAILED: " . ($createRes['message'] ?? '') . "\n";
    print_r($createRes);
    exit(1);
}
$formId = $createRes['data']['id'];
echo "Created Form ID {$formId} with phone field.\n";

$fetched = $customFormModel->findWithFields($formId);
$phoneField = $fetched['fields'][0];
echo "Field Type: {$phoneField['field_type']}\n";
echo "Multiple Allowed: " . ($phoneField['validation_rules']['multiple'] ? 'YES' : 'NO') . "\n";
echo "Strict 10-Digit: " . ($phoneField['validation_rules']['strict_10_digit'] ? 'YES' : 'NO') . "\n";

if ($phoneField['field_type'] !== 'phone' || !$phoneField['validation_rules']['multiple']) {
    echo "FAILED: phone field verification failed\n";
    exit(1);
}

// Clean up
$db = DatabaseConfig::getInstance();
$db->executeQuery("DELETE FROM custom_form_fields WHERE form_id = ?", [$formId], 'i');
$db->executeQuery("DELETE FROM custom_forms WHERE id = ?", [$formId], 'i');

echo "\nAll Phone Field & Multiple Repeater Tests Passed Successfully!\n";
