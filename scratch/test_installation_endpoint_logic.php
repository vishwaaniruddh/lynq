<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';
require_once 'c:/xampp/htdocs/lynq/models/Installation.php';
require_once 'c:/xampp/htdocs/lynq/models/CustomForm.php';
require_once 'c:/xampp/htdocs/lynq/repositories/InstallationRepository.php';
require_once 'c:/xampp/htdocs/lynq/repositories/InstallationSectionRemarkRepository.php';

$installationRepo = new InstallationRepository();
$remarkRepo = new InstallationSectionRemarkRepository();
$installationId = 45869;
$engineerId = 47886;

$installation = $installationRepo->findById($installationId);
$db = DatabaseConfig::getInstance();
$siteInfo = null;
if (!empty($installation['site_id'])) {
    $siteRes = $db->getResults("SELECT s.*, p.name as project_name, p.code as project_code 
                                FROM sites s 
                                LEFT JOIN projects p ON s.project_id = p.id 
                                WHERE s.id = ? LIMIT 1", [(int)$installation['site_id']], 'i');
    if (!empty($siteRes)) {
        $siteInfo = $siteRes[0];
    }
}

$customFormModel = new CustomForm();
$targetProjectId = !empty($siteInfo['project_id']) ? (int)$siteInfo['project_id'] : null;
$customForm = $customFormModel->findFormForProject('installation', $targetProjectId);

$isApproved = in_array($installation['status'], ['contractor_approved', 'adv_approved']);
$canEdit = !$isApproved && !in_array($installation['status'], ['pending_assignment', 'pending_eta', 'pending_ada', 'pending_materials']);

echo "Installation Status: " . $installation['status'] . "\n";
echo "isApproved: " . ($isApproved ? 'YES' : 'NO') . "\n";
echo "canEdit: " . ($canEdit ? 'YES' : 'NO') . "\n";
echo "Site Info ATM: " . ($siteInfo['atm_id'] ?? 'N/A') . " (Project: " . ($siteInfo['project_name'] ?? 'N/A') . ")\n";
echo "Custom Form Name: " . ($customForm['form_name'] ?? 'N/A') . " with " . count($customForm['fields'] ?? []) . " fields.\n";
