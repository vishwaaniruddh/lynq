<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/CustomFormService.php';
require_once __DIR__ . '/../models/CustomForm.php';

$customFormService = new CustomFormService();
$customFormModel = new CustomForm();

echo "1. Testing Form Options API for Master Data Sources...\n";
$optRes = $customFormService->getFormOptions();
if (!$optRes['success'] || empty($optRes['data']['master_sources']['banks']['items'])) {
    echo "FAILED: master_sources.banks not returned properly\n";
    exit(1);
}
$banksInMaster = count($optRes['data']['master_sources']['banks']['items']);
echo "Successfully loaded {$banksInMaster} banks in Master Data Source.\n";

echo "\n2. Creating a Custom Form with Master-Backed Dropdown (Bank Master)...\n";
$formCode = 'TEST_FORM_MASTER_' . time();
$createRes = $customFormService->create([
    'form_name' => 'Master Synced Test Form',
    'form_code' => $formCode,
    'purpose' => 'site_add',
    'project_id' => 1,
    'description' => 'Form with master-synced bank dropdown',
    'status' => 'active',
    'fields' => [
        [
            'section_title' => 'Bank Info',
            'field_key' => 'allocated_bank_name',
            'field_label' => 'Bank Name',
            'field_type' => 'select',
            'is_required' => 1,
            'grid_width' => 6,
            'options' => [
                'source' => 'master',
                'master_key' => 'banks'
            ]
        ]
    ]
], 1, 1);

if (!$createRes['success']) {
    echo "FAILED to create form: " . ($createRes['message'] ?? '') . "\n";
    print_r($createRes);
    exit(1);
}
$formId = $createRes['data']['id'];
echo "Created Form ID {$formId} with Master-backed Dropdown.\n";

echo "\n3. Testing Master Field Resolution in CustomForm Model...\n";
$fetched = $customFormModel->findWithFields($formId);
$field0 = $fetched['fields'][0];
echo "Field Label: {$field0['field_label']}\n";
echo "Resolved Options Count: " . count($field0['resolved_options']) . "\n";
echo "First Bank: " . $field0['resolved_options'][0]['label'] . "\n";
echo "Last Bank: " . end($field0['resolved_options'])['label'] . "\n";

if (count($field0['resolved_options']) !== 13) {
    echo "FAILED: Expected 13 resolved bank options, got " . count($field0['resolved_options']) . "\n";
    exit(1);
}

echo "\nAll Master-Backed Custom Form Options Tests Passed Successfully!\n";
