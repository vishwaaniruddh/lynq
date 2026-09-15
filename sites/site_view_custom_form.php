<?php
/**
 * Dynamic Custom Site Details View Page
 * Displays standard site info + all dynamic custom form fields & multi-phone records
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/ProjectRepository.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$siteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$siteRepo = new SiteRepository();
$projectRepo = new ProjectRepository();

$site = $siteRepo->findById($siteId);
if (!$site) {
    header('Location: ../sites/index_new.php');
    exit;
}

$projectName = 'Global / Default';
if (!empty($site['project_id'])) {
    $proj = $projectRepo->findById($site['project_id']);
    if ($proj) {
        $projectName = $proj['name'] . ($proj['code'] ? " ({$proj['code']})" : '');
    }
}

// Parse custom fields JSON
$customData = null;
if (!empty($site['custom_fields_json'])) {
    $customData = is_string($site['custom_fields_json']) ? json_decode($site['custom_fields_json'], true) : $site['custom_fields_json'];
}

$customFields = $customData['fields'] ?? [];
$formName = $customData['form_name'] ?? 'Project Custom Form';

// Group custom fields by section
$customSections = [];
foreach ($customFields as $k => $f) {
    $sec = $f['section'] ?? 'Additional Details';
    if (!isset($customSections[$sec])) {
        $customSections[$sec] = [];
    }
    $customSections[$sec][$k] = $f;
}

$baseUrl = '..';
$pageTitle = 'Site View: ' . htmlspecialchars($site['site_name']);
$currentPage = 'sites_new';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites Master Tracking', 'url' => '../sites/index_new.php'],
    ['label' => htmlspecialchars($site['site_name'])]
];

ob_start();
?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-primary to-indigo-600 flex items-center justify-center text-white shadow-md">
                <i class="fas fa-sitemap text-xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($site['site_name']) ?></h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <?= htmlspecialchars($projectName) ?>
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $site['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600' ?>">
                        <?= ucfirst($site['status'] ?? 'active') ?>
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Added on <?= date('d M Y, h:i A', strtotime($site['created_at'])) ?>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="../sites/index_new.php" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center">
                <i class="fas fa-arrow-left mr-1.5"></i>Back to Tracking
            </a>
            <a href="../sites/site_edit_custom_form.php?id=<?= $site['id'] ?>" class="px-4 py-2 text-xs font-semibold text-white bg-primary hover:bg-indigo-700 rounded-lg transition shadow-md flex items-center">
                <i class="fas fa-edit mr-1.5"></i>Edit Site
            </a>
            <a href="../sites/delegate.php?id=<?= $site['id'] ?>" class="px-3.5 py-2 text-xs font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg transition flex items-center">
                <i class="fas fa-share-alt mr-1.5"></i>Delegate
            </a>
        </div>
    </div>

    <!-- 1. Essential Information -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm space-y-5">
        <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2.5 h-4 bg-primary rounded-full"></span>
                Essential Master Information
            </h3>
            <span class="text-xs text-gray-400">Core CRM Data</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <span class="text-gray-400 font-medium block mb-1">Target Project</span>
                <span class="text-gray-800 font-bold text-sm"><?= htmlspecialchars($projectName) ?></span>
            </div>
            <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <span class="text-gray-400 font-medium block mb-1">Bank Name</span>
                <span class="text-gray-800 font-bold"><?= htmlspecialchars($site['bank_name'] ?: 'Not Specified') ?></span>
            </div>
            <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <span class="text-gray-400 font-medium block mb-1">Customer Name</span>
                <span class="text-gray-800 font-bold"><?= htmlspecialchars($site['customer_name'] ?: 'Hitachi') ?></span>
            </div>
            <div class="bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <span class="text-gray-400 font-medium block mb-1">Location</span>
                <span class="text-gray-800 font-bold"><?= htmlspecialchars($site['city'] ?: '-') ?>, <?= htmlspecialchars($site['state'] ?: '-') ?></span>
                <span class="text-[10px] text-gray-400 block"><?= htmlspecialchars($site['country'] ?: 'India') ?></span>
            </div>
            <div class="sm:col-span-2 md:col-span-4 bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <span class="text-gray-400 font-medium block mb-1">Full Street Address</span>
                <p class="text-gray-800 font-medium leading-relaxed"><?= nl2br(htmlspecialchars($site['address'] ?: 'No address specified')) ?></p>
            </div>
        </div>
    </div>

    <!-- 2. Dynamic Custom Sections & Fields -->
    <?php if (!empty($customSections)): ?>
        <?php foreach ($customSections as $secTitle => $fields): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm space-y-4">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2.5 h-4 bg-indigo-600 rounded-full"></span>
                        <?= htmlspecialchars($secTitle) ?>
                    </h3>
                    <span class="text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full font-semibold">Custom Dynamic Fields</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs items-start">
                    <?php foreach ($fields as $key => $field): ?>
                        <div class="bg-gray-50/60 p-3.5 rounded-xl border border-gray-100 space-y-1 <?= in_array($field['type'] ?? '', ['textarea']) ? 'sm:col-span-2 md:col-span-3' : '' ?>">
                            <span class="text-gray-400 font-medium block"><?= htmlspecialchars($field['label'] ?? ucfirst($key)) ?></span>
                            
                            <?php if (($field['type'] ?? '') === 'phone'): ?>
                                <?php 
                                    $phones = is_array($field['value']) ? $field['value'] : (!empty($field['value']) ? [$field['value']] : []);
                                ?>
                                <?php if (!empty($phones)): ?>
                                    <div class="space-y-1 pt-0.5">
                                        <?php foreach ($phones as $p): ?>
                                            <div class="flex items-center gap-2">
                                                <a href="tel:<?= htmlspecialchars($p) ?>" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200 hover:bg-emerald-100 transition">
                                                    <i class="fas fa-phone-alt text-[10px]"></i> <?= htmlspecialchars($p) ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">None provided</span>
                                <?php endif; ?>

                            <?php elseif (($field['type'] ?? '') === 'file'): ?>
                                <?php if (!empty($field['value'])): ?>
                                    <a href="../<?= htmlspecialchars($field['value']) ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-600 font-semibold hover:underline pt-1">
                                        <i class="fas fa-paperclip"></i> View Uploaded File
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">No file uploaded</span>
                                <?php endif; ?>

                            <?php elseif (is_array($field['value'] ?? null)): ?>
                                <span class="text-gray-800 font-bold"><?= htmlspecialchars(implode(', ', $field['value'])) ?></span>

                            <?php else: ?>
                                <p class="text-gray-800 font-bold text-xs leading-relaxed">
                                    <?= !empty($field['value']) ? nl2br(htmlspecialchars($field['value'])) : '<span class="text-gray-400 font-normal italic">-</span>' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
