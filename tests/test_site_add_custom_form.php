<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/CustomFormService.php';
require_once __DIR__ . '/../services/SiteService.php';

$customFormService = new CustomFormService();
$siteService = new SiteService();

echo "1. Checking or creating a custom 'site_add' form for project XTPL (Project ID: 1)...\n";
$customFormModel = new CustomForm();
$resolvedForm = $customFormModel->findFormForProject('site_add', 1);

if (!$resolvedForm) {
    $formRes = $customFormService->create([
        'form_name' => 'XTPL Site Addition Form',
        'form_code' => 'FORM_SITE_ADD_XTPL_' . time(),
        'purpose' => 'site_add',
        'project_id' => 1,
        'description' => 'Onboarding questionnaire for XTPL ATM deployments',
        'status' => 'active',
        'fields' => [
            [
                'section_title' => 'ATM Hardware Specifications',
                'field_key' => 'atm_model_type',
                'field_label' => 'ATM Hardware Model',
                'field_type' => 'select',
                'is_required' => 1,
                'grid_width' => 6,
                'options' => [
                    ['label' => 'Diebold Opteva 720', 'value' => 'diebold_720'],
                    ['label' => 'NCR SelfServ 6622', 'value' => 'ncr_6622'],
                    ['label' => 'Hitachi Cash Recycler 280', 'value' => 'hitachi_280']
                ]
            ],
            [
                'section_title' => 'ATM Hardware Specifications',
                'field_key' => 'atm_ip_preference',
                'field_label' => 'Static IP Requirement',
                'field_type' => 'radio',
                'is_required' => 1,
                'grid_width' => 6,
                'options' => [
                    ['label' => 'Static Dedicated IP', 'value' => 'static'],
                    ['label' => 'Dynamic DHCP with DDNS', 'value' => 'dynamic']
                ]
            ],
            [
                'section_title' => 'Site Readiness & Photos',
                'field_key' => 'power_outlet_type',
                'field_label' => 'UPS 3-Pin Socket Available',
                'field_type' => 'radio',
                'is_required' => 1,
                'grid_width' => 6,
                'options' => [
                    ['label' => 'Yes, Tested 230V', 'value' => 'yes'],
                    ['label' => 'No (Requires electrician)', 'value' => 'no']
                ]
            ],
            [
                'section_title' => 'Site Readiness & Photos',
                'field_key' => 'atm_kiosk_front_photo',
                'field_label' => 'ATM Kiosk Front Snapshot',
                'field_type' => 'file',
                'is_required' => 1,
                'grid_width' => 6,
                'validation_rules' => [
                    'allowed_extensions' => 'jpg,jpeg,png',
                    'max_size_mb' => 5,
                    'multiple' => false
                ]
            ]
        ]
    ], 1, 1);
    
    if (!$formRes['success']) {
        echo "FAILED: " . $formRes['message'] . "\n";
        exit(1);
    }
    $resolvedForm = $formRes['data'];
}

$formId = $resolvedForm['id'];
echo "Active Form ID: {$formId} ('{$resolvedForm['form_name']}') with " . count($resolvedForm['fields']) . " fields.\n";

echo "\n2. Testing Site Creation with Project & Dynamic Custom Fields:\n";
$uniqueSiteName = 'XTPL Dynamic Site ' . time();
$siteCreateRes = $siteService->createSite([
    'site_name' => $uniqueSiteName,
    'project_id' => 1,
    'lho' => 'DELHI',
    'bank_name' => 'State Bank of India',
    'customer_name' => 'Hitachi',
    'country' => 'India',
    'state' => 'Delhi',
    'city' => 'New Delhi',
    'zone' => 'North',
    'address' => 'Connaught Place Outer Circle, New Delhi 110001',
    'company_id' => 1,
    'status' => 'active',
    'custom_fields_json' => json_encode([
        'form_id' => $formId,
        'form_name' => $resolvedForm['form_name'],
        'fields' => [
            'atm_model_type' => ['label' => 'ATM Hardware Model', 'value' => 'diebold_720'],
            'atm_ip_preference' => ['label' => 'Static IP Requirement', 'value' => 'static'],
            'power_outlet_type' => ['label' => 'UPS 3-Pin Socket Available', 'value' => 'yes'],
            'atm_kiosk_front_photo' => ['label' => 'ATM Kiosk Front Snapshot', 'value' => 'atm_cp_front.jpg']
        ]
    ])
], 1);

if (!$siteCreateRes['success']) {
    echo "FAILED site creation: " . ($siteCreateRes['message'] ?? 'unknown') . "\n";
    print_r($siteCreateRes);
    exit(1);
}

$siteId = $siteCreateRes['data']['id'];
echo "Created Site ID: {$siteId} ('{$siteCreateRes['data']['site_name']}')\n";

echo "\n3. Verifying stored Site data in database:\n";
$db = DatabaseConfig::getInstance();
$stored = $db->getResults("SELECT id, site_name, project_id, custom_fields_json FROM sites WHERE id = ?", [$siteId], 'i');
echo "Stored project_id: " . $stored[0]['project_id'] . "\n";
echo "Stored custom_fields_json: " . $stored[0]['custom_fields_json'] . "\n";

if ((int)$stored[0]['project_id'] !== 1 || empty($stored[0]['custom_fields_json'])) {
    echo "FAILED: project_id or custom_fields_json not stored correctly\n";
    exit(1);
}

echo "\nAll Site Add Custom Form Integration Tests Passed Successfully!\n";
