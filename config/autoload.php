<?php
/**
 * Autoloader for ADV CRM Users Module
 */

// Set timezone to Asia/Kolkata (IST)
date_default_timezone_set('Asia/Kolkata');

spl_autoload_register(function ($className) {
    $baseDir = __DIR__ . '/../';
    
    // Convert namespace to directory structure
    $classFile = str_replace('\\', '/', $className) . '.php';
    
    // Define possible directories to search
    $directories = [
        'models/',
        'controllers/',
        'services/',
        'repositories/',
        'middleware/',
        'utils/',
        'migrations/'
    ];
    
    foreach ($directories as $dir) {
        $file = $baseDir . $dir . $classFile;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // Try direct file path
    $file = $baseDir . $classFile;
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load configuration
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/constants.php';

// Load JWT configuration (if exists)
if (file_exists(__DIR__ . '/jwt.php')) {
    // JWT config is loaded on-demand by JWTService
}

// Load permission utilities (global functions)
require_once __DIR__ . '/../utils/PermissionUtils.php';

// Load authentication helper (global functions for unified JWT/session auth)
// Requirements: 7.1 - Include JWTService, JWTAuthMiddleware, JWTCookieService, repositories
require_once __DIR__ . '/../utils/AuthHelper.php';