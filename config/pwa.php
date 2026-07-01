<?php
/**
 * ADV Clarity Management System - PWA Configuration
 * Configuration constants and settings for PWA functionality
 */

// VAPID Keys for Push Notifications
// In production, generate proper VAPID keys using web-push library
define('VAPID_PUBLIC_KEY', 'BEl62iUYgUivxIkv69yViEuiBIa40HI80NqIUHI80NqIUHI80NqIUHI80NqIUHI80NqIUHI80NqIUHI80NqIUHI80NqI');
define('VAPID_PRIVATE_KEY', 'your-vapid-private-key-here');
define('VAPID_SUBJECT', 'mailto:admin@advclarity.com');

// PWA Cache Configuration
define('PWA_CACHE_VERSION', 'v1.0.0');
define('PWA_CACHE_TTL_API', 5 * 60); // 5 minutes in seconds
define('PWA_CACHE_TTL_ASSETS', 24 * 60 * 60); // 24 hours in seconds
define('PWA_CACHE_TTL_OFFLINE', 7 * 24 * 60 * 60); // 7 days in seconds

// Offline Sync Configuration
define('PWA_SYNC_MAX_RETRIES', 3);
define('PWA_SYNC_RETRY_DELAY', 30); // seconds
define('PWA_SYNC_BATCH_SIZE', 10); // actions per batch

// Analytics Configuration
define('PWA_ANALYTICS_ENABLED', true);
define('PWA_ANALYTICS_RETENTION_DAYS', 90);
define('PWA_ANALYTICS_BATCH_SIZE', 50);

// Push Notification Configuration
define('PWA_PUSH_ENABLED', true);
define('PWA_PUSH_DEFAULT_TTL', 24 * 60 * 60); // 24 hours
define('PWA_PUSH_MAX_SUBSCRIPTIONS_PER_USER', 5);

// Installation Prompt Configuration
define('PWA_INSTALL_PROMPT_DELAY', 3); // days between prompts
define('PWA_INSTALL_MAX_DISMISSALS', 3); // max dismissals before stopping prompts

// Service Worker Configuration
define('PWA_SW_UPDATE_CHECK_INTERVAL', 30 * 60); // 30 minutes in seconds
define('PWA_SW_CACHE_MAX_SIZE', 50 * 1024 * 1024); // 50MB in bytes
define('PWA_SW_CACHE_MAX_AGE', 30 * 24 * 60 * 60); // 30 days in seconds

// PWA Feature Flags
define('PWA_BACKGROUND_SYNC_ENABLED', true);
define('PWA_OFFLINE_FALLBACK_ENABLED', true);
define('PWA_INSTALL_BANNER_ENABLED', true);
define('PWA_UPDATE_NOTIFICATIONS_ENABLED', true);

// PWA Manifest Configuration
define('PWA_MANIFEST_NAME', 'ADV Clarity Management System');
define('PWA_MANIFEST_SHORT_NAME', 'Clarity CRM');
define('PWA_MANIFEST_DESCRIPTION', 'Comprehensive ATM Installation and Inventory Management System');
define('PWA_MANIFEST_THEME_COLOR', '#4a90e2');
define('PWA_MANIFEST_BACKGROUND_COLOR', '#ffffff');

// PWA Icon Sizes
define('PWA_ICON_SIZES', [72, 96, 128, 144, 152, 192, 384, 512]);
define('PWA_MASKABLE_ICON_SIZES', [192, 512]);

// PWA Screenshot Configuration
define('PWA_SCREENSHOT_WIDE_WIDTH', 1280);
define('PWA_SCREENSHOT_WIDE_HEIGHT', 720);
define('PWA_SCREENSHOT_NARROW_WIDTH', 390);
define('PWA_SCREENSHOT_NARROW_HEIGHT', 844);

// PWA Security Configuration
define('PWA_REQUIRE_HTTPS', true);
define('PWA_ALLOW_HTTP_LOCALHOST', true);
define('PWA_CSP_ENABLED', true);

// PWA Performance Configuration
define('PWA_PRELOAD_CRITICAL_RESOURCES', true);
define('PWA_LAZY_LOAD_IMAGES', true);
define('PWA_COMPRESS_RESPONSES', true);

// PWA Debugging
define('PWA_DEBUG_MODE', false);
define('PWA_LOG_LEVEL', 'info'); // debug, info, warn, error
define('PWA_CONSOLE_LOGGING', true);

/**
 * Get PWA configuration as array
 */
function getPWAConfig() {
    return [
        'vapid' => [
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
            'subject' => VAPID_SUBJECT
        ],
        'cache' => [
            'version' => PWA_CACHE_VERSION,
            'ttl' => [
                'api' => PWA_CACHE_TTL_API,
                'assets' => PWA_CACHE_TTL_ASSETS,
                'offline' => PWA_CACHE_TTL_OFFLINE
            ]
        ],
        'sync' => [
            'maxRetries' => PWA_SYNC_MAX_RETRIES,
            'retryDelay' => PWA_SYNC_RETRY_DELAY,
            'batchSize' => PWA_SYNC_BATCH_SIZE
        ],
        'analytics' => [
            'enabled' => PWA_ANALYTICS_ENABLED,
            'retentionDays' => PWA_ANALYTICS_RETENTION_DAYS,
            'batchSize' => PWA_ANALYTICS_BATCH_SIZE
        ],
        'push' => [
            'enabled' => PWA_PUSH_ENABLED,
            'defaultTTL' => PWA_PUSH_DEFAULT_TTL,
            'maxSubscriptionsPerUser' => PWA_PUSH_MAX_SUBSCRIPTIONS_PER_USER
        ],
        'install' => [
            'promptDelay' => PWA_INSTALL_PROMPT_DELAY,
            'maxDismissals' => PWA_INSTALL_MAX_DISMISSALS
        ],
        'serviceWorker' => [
            'updateCheckInterval' => PWA_SW_UPDATE_CHECK_INTERVAL,
            'cacheMaxSize' => PWA_SW_CACHE_MAX_SIZE,
            'cacheMaxAge' => PWA_SW_CACHE_MAX_AGE
        ],
        'features' => [
            'backgroundSync' => PWA_BACKGROUND_SYNC_ENABLED,
            'offlineFallback' => PWA_OFFLINE_FALLBACK_ENABLED,
            'installBanner' => PWA_INSTALL_BANNER_ENABLED,
            'updateNotifications' => PWA_UPDATE_NOTIFICATIONS_ENABLED
        ],
        'manifest' => [
            'name' => PWA_MANIFEST_NAME,
            'shortName' => PWA_MANIFEST_SHORT_NAME,
            'description' => PWA_MANIFEST_DESCRIPTION,
            'themeColor' => PWA_MANIFEST_THEME_COLOR,
            'backgroundColor' => PWA_MANIFEST_BACKGROUND_COLOR
        ],
        'debug' => [
            'enabled' => PWA_DEBUG_MODE,
            'logLevel' => PWA_LOG_LEVEL,
            'consoleLogging' => PWA_CONSOLE_LOGGING
        ]
    ];
}

/**
 * Check if PWA is enabled and properly configured
 */
function isPWAEnabled() {
    // Check if HTTPS is required and we're on HTTP
    if (PWA_REQUIRE_HTTPS && !isHTTPS() && !isLocalhost()) {
        return false;
    }
    
    // Check if required constants are defined
    if (!defined('VAPID_PUBLIC_KEY') || empty(VAPID_PUBLIC_KEY)) {
        return false;
    }
    
    return true;
}

/**
 * Check if connection is HTTPS
 */
function isHTTPS() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           $_SERVER['SERVER_PORT'] == 443 ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Check if running on localhost
 */
function isLocalhost() {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    return in_array($host, ['localhost', '127.0.0.1', '::1']) || 
           strpos($host, 'localhost:') === 0;
}
?>