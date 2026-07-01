<?php
/**
 * Download Bulk Upload Log Files
 * 
 * Handles downloading success/error CSV files from bulk uploads
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/BulkUploadLogService.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$logId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';

if ($logId <= 0 || !in_array($type, ['success', 'error'])) {
    http_response_code(400);
    exit('Invalid request');
}

$logService = new BulkUploadLogService();
$log = $logService->getLog($logId);

if (!$log) {
    http_response_code(404);
    exit('Log not found');
}

// Check permission - user must be the uploader or admin
$currentUser = $sessionService->getCurrentUser();
if ($log['uploaded_by'] != $currentUser['id'] && !isAdvUser()) {
    http_response_code(403);
    exit('Access denied');
}

// Get the appropriate file
$filename = $type === 'success' ? $log['success_file'] : $log['error_file'];

if (empty($filename)) {
    http_response_code(404);
    exit('File not found');
}

$filepath = $logService->getFilePath($filename);

if (!$filepath || !file_exists($filepath)) {
    http_response_code(404);
    exit('File not found');
}

// Set headers for download
$downloadName = $type === 'success' 
    ? 'success_records_' . date('Y-m-d', strtotime($log['created_at'])) . '.csv'
    : 'error_records_' . date('Y-m-d', strtotime($log['created_at'])) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filepath);
exit;
