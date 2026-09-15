<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/CustomFormService.php';

$service = new CustomFormService();

echo "Testing Forms List with Project Mapping:\n";
$listRes = $service->list();
if (!$listRes['success']) {
    echo "FAILED: " . ($listRes['message'] ?? 'unknown') . "\n";
    exit(1);
}
echo "Total forms: " . $listRes['data']['total'] . "\n";
foreach ($listRes['data']['data'] as $f) {
    echo "- Form #{$f['id']}: {$f['form_name']} [{$f['purpose']}] | Project: {$f['project_name']} | Fields: {$f['field_count']}\n";
}

echo "\nTesting Form Options Lookups (Projects list):\n";
$optRes = $service->getFormOptions();
if (!$optRes['success']) {
    echo "FAILED: " . ($optRes['message'] ?? 'unknown') . "\n";
    exit(1);
}
echo "Purposes: " . count($optRes['data']['purposes']) . "\n";
echo "Projects: " . count($optRes['data']['projects']) . "\n";
foreach ($optRes['data']['projects'] as $p) {
    echo "  * Project ID {$p['id']}: {$p['name']} ({$p['code']})\n";
}

echo "\nAll Project-mapped Forms Master Tests Passed Successfully!\n";
