<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/CustomFormService.php';

$service = new CustomFormService();

echo "1. Testing Form Update with status 'active'...\n";
$upRes = $service->update(1, [
    'form_name' => 'XTPL Site Addition Form',
    'purpose' => 'site_add',
    'project_id' => 1,
    'status' => 'active'
], 1);

if (!$upRes['success']) {
    echo "FAILED: " . $upRes['message'] . "\n";
    exit(1);
}

$updated = $service->get(1);
echo "Status after update: '{$updated['data']['status']}'\n";
echo "Updated at: '{$updated['data']['updated_at']}'\n";

if ($updated['data']['status'] !== 'active') {
    echo "FAILED: status was not saved as 'active'\n";
    exit(1);
}

echo "\n2. Testing Form Update with status 'draft'...\n";
$upDraft = $service->update(1, [
    'form_name' => 'XTPL Site Addition Form',
    'purpose' => 'site_add',
    'project_id' => 1,
    'status' => 'draft'
], 1);

$draftForm = $service->get(1);
echo "Status after draft update: '{$draftForm['data']['status']}'\n";
if ($draftForm['data']['status'] !== 'draft') {
    echo "FAILED: status was not saved as 'draft'\n";
    exit(1);
}

echo "\n3. Restoring status to 'active'...\n";
$service->update(1, [
    'form_name' => 'XTPL Site Addition Form',
    'purpose' => 'site_add',
    'project_id' => 1,
    'status' => 'active'
], 1);

$final = $service->get(1);
echo "Final status: '{$final['data']['status']}'\n";
echo "Final updated_at: '{$final['data']['updated_at']}'\n";

echo "\nForm Status Update Test Passed Successfully!\n";
