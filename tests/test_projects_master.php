<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/ProjectService.php';

$service = new ProjectService();

echo "1. Testing Project Creation:\n";
$createRes = $service->create([
    'name' => 'Automated Test Project ' . time(),
    'code' => 'PROJ_TEST_' . time(),
    'description' => 'Test project description',
    'status' => 1
], 1);

if (!$createRes['success']) {
    echo "FAILED creation: " . $createRes['message'] . "\n";
    exit(1);
}
$createdId = $createRes['data']['id'];
echo "Created Project ID: {$createdId} ({$createRes['data']['name']})\n";

echo "\n2. Testing Project List:\n";
$list = $service->getAll();
echo "Total active projects: " . $list['total'] . "\n";

echo "\n3. Testing Project Update:\n";
$updateRes = $service->update($createdId, [
    'name' => 'Automated Test Project (Updated)',
    'description' => 'Updated test description',
    'status' => 1
], 1);
if (!$updateRes['success']) {
    echo "FAILED update: " . $updateRes['message'] . "\n";
    exit(1);
}
echo "Updated Project name: {$updateRes['data']['name']}\n";

echo "\n4. Testing Soft Delete:\n";
$delRes = $service->delete($createdId, 1);
if (!$delRes['success']) {
    echo "FAILED soft delete: " . $delRes['message'] . "\n";
    exit(1);
}
echo "Soft delete returned success.\n";

echo "\n5. Verifying Project is excluded from active queries:\n";
$getAfter = $service->getById($createdId);
if ($getAfter !== null) {
    echo "FAILED: Soft-deleted project was still returned by getById()\n";
    exit(1);
}
echo "Verified: getById({$createdId}) returned null.\n";

echo "\n6. Verifying database record has deleted_at set:\n";
$db = DatabaseConfig::getInstance();
$raw = $db->getResults("SELECT id, name, deleted_at FROM `projects` WHERE `id` = ?", [$createdId], 'i');
if (empty($raw) || empty($raw[0]['deleted_at'])) {
    echo "FAILED: Raw database record does not have deleted_at set\n";
    exit(1);
}
echo "Verified: Database record exists with deleted_at = '{$raw[0]['deleted_at']}'\n";

echo "\nAll Project Master CRUD and Soft Delete Tests Passed Successfully!\n";
