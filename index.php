<?php
require_once __DIR__ . '/config/autoload.php';

$sessionService = new SessionService();

if ($sessionService->isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: views/auth/login.php');
}
exit;
