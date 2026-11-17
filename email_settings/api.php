<?php
/**
 * Email Settings AJAX API Handler
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

$currentUser = $sessionService->getCurrentUser();
if (!isAdvUser()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. ADV administrators only.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$model = new SiteEmailSetting();

try {
    switch ($action) {
        case 'save':
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $type = trim($_POST['type'] ?? '');
            $targetId = !empty($_POST['target_id']) ? (int)$_POST['target_id'] : null;
            $targetName = trim($_POST['target_name'] ?? '');
            $toEmails = trim($_POST['to_emails'] ?? '');
            $ccEmails = trim($_POST['cc_emails'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
            $remarks = trim($_POST['remarks'] ?? '');

            if (!in_array($type, ['site', 'state', 'city', 'zone'], true)) {
                echo json_encode(['success' => false, 'error' => 'Invalid configuration level. Choose Site, State, City, or Zone.']);
                exit;
            }

            if (empty($targetName)) {
                echo json_encode(['success' => false, 'error' => 'Please select or provide a valid target (' . ucfirst($type) . ').']);
                exit;
            }

            // Validate TO emails
            if (empty($toEmails)) {
                echo json_encode(['success' => false, 'error' => 'At least one valid TO email address is required.']);
                exit;
            }

            $invalidTo = SiteEmailSetting::findInvalidEmails($toEmails);
            if (!empty($invalidTo)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'The following TO email address(es) are invalid: ' . implode(', ', $invalidTo)
                ]);
                exit;
            }

            $invalidCc = SiteEmailSetting::findInvalidEmails($ccEmails);
            if (!empty($invalidCc)) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'The following CC email address(es) are invalid: ' . implode(', ', $invalidCc)
                ]);
                exit;
            }

            // Check if rule already exists for this target
            $existing = $model->findExisting($type, $targetName, $targetId, $id);
            if ($existing) {
                // If creating without explicit id, auto-update existing
                if (!$id) {
                    $id = (int)$existing['id'];
                }
            }

            $currentUserId = $sessionService->getCurrentUserId();

            if ($id) {
                $model->updateSetting($id, [
                    'type' => $type,
                    'target_id' => $targetId,
                    'target_name' => $targetName,
                    'to_emails' => $toEmails,
                    'cc_emails' => $ccEmails,
                    'status' => $status,
                    'remarks' => $remarks,
                    'updated_by' => $currentUserId
                ]);
                $savedId = $id;
                $msg = ucfirst($type) . " email settings updated successfully.";
            } else {
                $savedId = $model->createSetting([
                    'type' => $type,
                    'target_id' => $targetId,
                    'target_name' => $targetName,
                    'to_emails' => $toEmails,
                    'cc_emails' => $ccEmails,
                    'status' => $status,
                    'remarks' => $remarks,
                    'created_by' => $currentUserId
                ]);
                $msg = ucfirst($type) . " email settings added successfully.";
            }

            $counts = $model->getCounts();
            $updatedItem = $model->find($savedId);

            echo json_encode([
                'success' => true,
                'message' => $msg,
                'id' => $savedId,
                'counts' => $counts,
                'item' => $updatedItem
            ]);
            break;

        case 'delete':
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'Invalid setting ID.']);
                exit;
            }

            $setting = $model->find($id);
            if (!$setting) {
                echo json_encode(['success' => false, 'error' => 'Email setting not found.']);
                exit;
            }

            $model->deleteSetting($id);
            $counts = $model->getCounts();

            echo json_encode([
                'success' => true,
                'message' => 'Email setting deleted successfully.',
                'counts' => $counts
            ]);
            break;

        case 'toggle_status':
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'Invalid setting ID.']);
                exit;
            }

            $newStatus = $model->toggleStatus($id);
            $counts = $model->getCounts();

            echo json_encode([
                'success' => true,
                'message' => 'Status changed to ' . ucfirst($newStatus) . '.',
                'status' => $newStatus,
                'counts' => $counts
            ]);
            break;

        case 'get_setting':
            $id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
            $setting = $model->find($id);
            if (!$setting) {
                echo json_encode(['success' => false, 'error' => 'Setting not found.']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'setting' => $setting
            ]);
            break;

        case 'resolve':
            $siteQuery = trim($_GET['site'] ?? $_POST['site'] ?? '');
            if (empty($siteQuery)) {
                echo json_encode(['success' => false, 'error' => 'Please select a site to resolve recipients.']);
                exit;
            }

            $resolution = $model->getEffectiveEmailsForSite($siteQuery);
            if (!$resolution['found']) {
                echo json_encode(['success' => false, 'error' => $resolution['error'] ?? 'Site not found.']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'resolution' => $resolution
            ]);
            break;

        case 'list':
            $type = trim($_GET['type'] ?? 'site');
            $data = $model->getByType($type);
            $counts = $model->getCounts();

            echo json_encode([
                'success' => true,
                'type' => $type,
                'data' => $data,
                'counts' => $counts
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action requested.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
