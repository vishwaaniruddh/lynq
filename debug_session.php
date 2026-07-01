<?php
/**
 * Debug Session Script
 * Check session status and user data
 */

require_once __DIR__ . '/config/autoload.php';

header('Content-Type: application/json');

$sessionService = new SessionService();

$debug = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION ?? [],
    'is_logged_in' => $sessionService->isLoggedIn(),
    'current_user_id' => $sessionService->getCurrentUserId(),
    'current_user' => $sessionService->getCurrentUser()
];

// Check database session
if (isset($_SESSION['session_token'])) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
        $debug['db_session'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $debug['db_session_error'] = $e->getMessage();
    }
}

// Check user in database
if (isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username, email, status, company_id, role_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $debug['db_user'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $debug['db_user_error'] = $e->getMessage();
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT);
