<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/CustomFormService.php';

$service = new CustomFormService();

echo "Testing Form Duplication (Cloning Form #1 for Diebold):\n";
$dupRes = $service->duplicate(1, [
    'form_name' => 'Diebold Site Addition Form',
    'customer_id' => 3
], 1);

if (!$dupRes['success']) {
    echo "FAILED Duplicate: " . $dupRes['message'] . "\n";
    exit(1);
}

$clonedId = $dupRes['data']['id'];
echo "Successfully duplicated Form #1 -> Cloned Form #{$clonedId} ({$dupRes['data']['form_name']})\n";
echo "Cloned fields count: " . count($dupRes['data']['fields']) . "\n";

echo "\nTesting Form Update on Cloned Form #{$clonedId}:\n";
$updateRes = $service->update($clonedId, [
    'form_name' => 'Diebold Site Addition Form (Updated)',
    'purpose' => 'site_add',
    'customer_id' => 3,
    'status' => 'active',
    'fields' => [
        [
            'section_title' => 'General Info',
            'field_key' => 'diebold_site_id',
            'field_label' => 'Diebold Terminal ID',
            'field_type' => 'text',
            'is_required' => 1,
            'grid_width' => 12
        ],
        [
            'section_title' => 'Power Backup',
            'field_key' => 'ups_backup_time',
            'field_label' => 'UPS Backup Capacity (Hours)',
            'field_type' => 'number',
            'is_required' => 1,
            'grid_width' => 6
        ]
    ]
], 1);

if (!$updateRes['success']) {
    echo "FAILED Update: " . $updateRes['message'] . "\n";
    exit(1);
}
echo "Successfully updated Cloned Form #{$clonedId} (Fields: " . count($updateRes['data']['fields']) . ")\n";

echo "\nTesting Soft Delete on Cloned Form #{$clonedId}:\n";
$delRes = $service->delete($clonedId, 1);
if (!$delRes['success']) {
    echo "FAILED Delete: " . $delRes['message'] . "\n";
    exit(1);
}
echo "Successfully soft deleted form #{$clonedId}\n";

$verifyDel = $service->get($clonedId);
if ($verifyDel['success']) {
    echo "FAILED: Deleted form is still returned by get()\n";
    exit(1);
} else {
    echo "Verified: get({$clonedId}) returned NOT_FOUND as expected.\n";
}

echo "\nAll Extended Tests Passed Flawlessly!\n";
