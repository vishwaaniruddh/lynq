<?php
/**
 * Bulk Upload History Page
 * 
 * Shows history of bulk upload operations with download links
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/BulkUploadLogService.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Check ADV access
if (!isAdvUser()) {
    $_SESSION['flash_error'] = 'Access denied. ADV users only.';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Bulk Upload History';
$currentPage = 'sites';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Sites', 'url' => 'index.php'],
    ['label' => 'Bulk Upload', 'url' => 'bulk_upload.php'],
    ['label' => 'History']
];

// Get upload history
$logService = new BulkUploadLogService();
$page = max(1, (int)($_GET['page'] ?? 1));
$logs = $logService->getLogs([
    'upload_type' => 'sites',
    'page' => $page,
    'limit' => 20
]);

ob_start();
?>

<div class="max-w-6xl mx-auto">
    <!-- Header Card -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Bulk Upload History</h3>
                <p class="text-sm text-gray-500">View past bulk upload operations and download results</p>
            </div>
            <div class="flex gap-3">
                <a href="bulk_upload.php" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-upload mr-2"></i>New Upload
                </a>
                <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sites
                </a>
            </div>
        </div>
    </div>

    <!-- History Table -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Success</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Downloads</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($logs['data'])): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                            <p>No upload history found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs['data'] as $log): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($log['original_filename']); ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <?php echo $log['total_rows']; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                <?php echo $log['success_count']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($log['error_count'] > 0): ?>
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                                <?php echo $log['error_count']; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <?php echo htmlspecialchars($log['uploaded_by_name'] ?? 'Unknown'); ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex gap-2">
                                <?php if (!empty($log['success_file']) && $logService->fileExists($log['success_file'])): ?>
                                <a href="download_bulk_log.php?id=<?php echo $log['id']; ?>&type=success" 
                                   class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200 transition"
                                   title="Download Success Records">
                                    <i class="fas fa-download mr-1"></i>Success
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($log['error_file']) && $logService->fileExists($log['error_file'])): ?>
                                <a href="download_bulk_log.php?id=<?php echo $log['id']; ?>&type=error" 
                                   class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200 transition"
                                   title="Download Error Records">
                                    <i class="fas fa-download mr-1"></i>Errors
                                </a>
                                <?php endif; ?>
                                
                                <?php if (empty($log['success_file']) && empty($log['error_file'])): ?>
                                <span class="text-gray-400 text-xs">No files</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($logs['totalPages'] > 1): ?>
        <div class="px-6 py-4 border-t flex items-center justify-between">
            <div class="text-sm text-gray-500">
                Page <?php echo $logs['page']; ?> of <?php echo $logs['totalPages']; ?>
            </div>
            <div class="flex gap-2">
                <?php if ($logs['page'] > 1): ?>
                <a href="?page=<?php echo $logs['page'] - 1; ?>" class="px-3 py-1 border rounded hover:bg-gray-50">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php if ($logs['page'] < $logs['totalPages']): ?>
                <a href="?page=<?php echo $logs['page'] + 1; ?>" class="px-3 py-1 border rounded hover:bg-gray-50">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
