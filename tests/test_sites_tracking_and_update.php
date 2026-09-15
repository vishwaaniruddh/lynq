<?php
require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../services/SiteService.php';

$siteRepo = new SiteRepository();
$siteService = new SiteService();

echo "1. Testing SiteRepository::findByCompany with project_id = 1...\n";
$res = $siteRepo->findByCompany(1, ['project_id' => 1, 'limit' => 50]);
echo "Total XTPL sites found in page: " . count($res['data']) . " | Total count: " . $res['total'] . "\n";
if ($res['total'] !== 24) {
    echo "FAILED: expected 24 sites, found " . $res['total'] . "\n";
    exit(1);
}

$site1 = $res['data'][0];
echo "First site: {$site1['site_name']} | Project: {$site1['project_name']} | Bank: {$site1['bank_name']}\n";

echo "\n2. Testing SiteService::updateSite on Site ID {$site1['id']}...\n";
$updateRes = $siteService->updateSite($site1['id'], [
    'bank_name' => $site1['bank_name'],
    'address' => 'Updated Test Address, Village Ratti Rori'
], 2326);

if (!$updateRes['success']) {
    echo "FAILED update: " . ($updateRes['message'] ?? '') . "\n";
    exit(1);
}
echo "Site update verified successfully!\n";

echo "\nAll Site Tracking, Custom View & Edit Tests Passed Successfully!\n";
