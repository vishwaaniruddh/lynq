<?php
/**
 * Master Error & Permission Showcase Page
 * Handles 403, 404, 500, 401 error states and provides administrative diagnostics.
 */

require_once __DIR__ . '/config/autoload.php';

$sessionService = new SessionService();
$isLoggedIn = $sessionService->isLoggedIn();
$currentUser = $isLoggedIn ? $sessionService->getCurrentUser() : null;

// Determine error code
$code = isset($_GET['code']) ? (int)$_GET['code'] : 0;
if (!$code && isset($_SERVER['REDIRECT_STATUS'])) {
    $code = (int)$_SERVER['REDIRECT_STATUS'];
}
if (!$code) {
    $code = (isset($_GET['message']) || isset($_GET['error']) || isset($_SESSION['error_message'])) ? 403 : 404;
}

// Determine error message
$message = $_GET['message'] ?? $_GET['error'] ?? $_SESSION['error_message'] ?? $_SESSION['flash_error'] ?? null;
if (isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
if (isset($_SESSION['flash_error'])) unset($_SESSION['flash_error']);

// Required permission parameter if provided
$requiredPermission = trim($_GET['required_permission'] ?? '');
if (empty($requiredPermission) && $message && preg_match('/permission[:\s]+([a-zA-Z0-9_\.]+)/i', $message, $m)) {
    $requiredPermission = $m[1];
}

// Error metadata definitions
$errorConfigs = [
    403 => [
        'title' => 'Access Restricted',
        'subtitle' => 'You do not have permission to perform this action',
        'icon' => 'fa-shield-halved',
        'badge' => 'Permission Denied',
        'badge_class' => 'bg-amber-100 text-amber-800 border-amber-300',
        'accent_gradient' => 'from-amber-500 to-rose-600',
        'icon_bg' => 'bg-amber-50 text-amber-600 border-amber-200',
        'default_msg' => 'Your account role does not have the required authorization to access this page or execute this operation.'
    ],
    404 => [
        'title' => 'Page Not Found',
        'subtitle' => 'The requested endpoint or URL was not found on this server',
        'icon' => 'fa-compass',
        'badge' => '404 Not Found',
        'badge_class' => 'bg-blue-100 text-blue-800 border-blue-300',
        'accent_gradient' => 'from-blue-500 to-indigo-600',
        'icon_bg' => 'bg-blue-50 text-blue-600 border-blue-200',
        'default_msg' => 'The page or resource you are looking for might have been moved, renamed, or is temporarily unavailable.'
    ],
    500 => [
        'title' => 'Server Encountered an Error',
        'subtitle' => 'An internal execution exception occurred while processing your request',
        'icon' => 'fa-triangle-exclamation',
        'badge' => '500 Server Error',
        'badge_class' => 'bg-red-100 text-red-800 border-red-300',
        'accent_gradient' => 'from-rose-500 to-red-600',
        'icon_bg' => 'bg-rose-50 text-rose-600 border-rose-200',
        'default_msg' => 'The server encountered an unexpected error. System administrators have been alerted.'
    ],
    401 => [
        'title' => 'Authentication Required',
        'subtitle' => 'Your session has expired or requires valid credentials',
        'icon' => 'fa-user-lock',
        'badge' => '401 Unauthorized',
        'badge_class' => 'bg-purple-100 text-purple-800 border-purple-300',
        'accent_gradient' => 'from-purple-500 to-indigo-600',
        'icon_bg' => 'bg-purple-50 text-purple-600 border-purple-200',
        'default_msg' => 'Please log in to verify your identity and access this secure workspace.'
    ]
];

$currentConfig = $errorConfigs[$code] ?? $errorConfigs[403];
$displayMessage = $message ? htmlspecialchars($message) : $currentConfig['default_msg'];

// Check permission engine if user is logged in
$userHasPerm = false;
$isAdmin = false;
if ($isLoggedIn && $currentUser) {
    $isAdmin = (strtoupper($currentUser['company_type'] ?? '') === 'ADV');
    if ($requiredPermission) {
        $permEngine = new PermissionEngine();
        $userHasPerm = $permEngine->can($currentUser['id'], $requiredPermission);
    }
}

// HTTP Response code
http_response_code(in_array($code, [401, 403, 404, 500]) ? $code : 403);

// Set layout metadata
$baseUrl = '';
$pageTitle = "{$code} - {$currentConfig['title']} | LYNQ";
$currentPage = 'error';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => "Error {$code}"]
];

ob_start();
?>
<div class="max-w-5xl mx-auto space-y-6 py-4">

    <!-- Interactive Showcase Navigation Dock -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 p-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-primary font-bold text-xs">
                    <i class="fas fa-layer-group"></i>
                </span>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Error Showcase & Preview Dock</h3>
                    <p class="text-xs text-gray-400">Preview different error scenarios and diagnostic states</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto">
                <a href="/error.php?code=403&message=You+do+not+have+permission+to+perform+this+action&required_permission=masters.banks.view" 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg transition <?php echo ($code === 403 && $requiredPermission) ? 'bg-amber-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                   <i class="fas fa-lock mr-1"></i> 403 Permission
                </a>
                <a href="/error.php?code=403&message=Access+denied.+This+module+is+restricted+to+ADV+administrators." 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg transition <?php echo ($code === 403 && !$requiredPermission) ? 'bg-amber-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                   <i class="fas fa-building mr-1"></i> 403 ADV Only
                </a>
                <a href="/error.php?code=404&message=The+requested+module+or+page+could+not+be+found" 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg transition <?php echo $code === 404 ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                   <i class="fas fa-compass mr-1"></i> 404 Not Found
                </a>
                <a href="/error.php?code=500&message=Database+query+timeout+or+connection+failure" 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg transition <?php echo $code === 500 ? 'bg-rose-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                   <i class="fas fa-triangle-exclamation mr-1"></i> 500 Server
                </a>
                <a href="/error.php?code=401&message=Authentication+token+expired.+Please+log+in+again." 
                   class="px-3 py-1.5 text-xs font-semibold rounded-lg transition <?php echo $code === 401 ? 'bg-purple-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                   <i class="fas fa-user-lock mr-1"></i> 401 Auth
                </a>
            </div>
        </div>
    </div>

    <!-- Main Error Display Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-200/80 overflow-hidden relative">
        <!-- Colored top gradient accent bar -->
        <div class="h-2 w-full bg-gradient-to-r <?php echo $currentConfig['accent_gradient']; ?>"></div>

        <div class="p-6 md:p-10">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
                <!-- Large Status Icon -->
                <div class="w-20 h-20 md:w-24 md:h-24 rounded-2xl flex items-center justify-center text-3xl md:text-4xl shadow-inner border <?php echo $currentConfig['icon_bg']; ?> flex-shrink-0">
                    <i class="fas <?php echo $currentConfig['icon']; ?>"></i>
                </div>

                <!-- Error Title & Description -->
                <div class="flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-1 text-xs font-bold rounded-md border <?php echo $currentConfig['badge_class']; ?>">
                            HTTP <?php echo $code; ?> &bull; <?php echo $currentConfig['badge']; ?>
                        </span>
                        <?php if ($requiredPermission): ?>
                        <span class="px-2.5 py-1 text-xs font-mono font-semibold rounded-md bg-gray-100 text-gray-700 border border-gray-200">
                            Required: <span class="text-indigo-600"><?php echo htmlspecialchars($requiredPermission); ?></span>
                        </span>
                        <?php endif; ?>
                    </div>

                    <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight">
                        <?php echo $currentConfig['title']; ?>
                    </h1>
                    
                    <p class="text-base text-gray-600 font-medium leading-relaxed">
                        <?php echo $displayMessage; ?>
                    </p>
                </div>
            </div>

            <!-- Context / Troubleshooting Details -->
            <div class="mt-8 pt-6 border-t border-gray-100 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Diagnostic Column 1: Current Session -->
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 space-y-3">
                    <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <i class="fas fa-user-circle text-primary"></i>
                        <span>User Session Identity</span>
                    </div>
                    <?php if ($isLoggedIn && $currentUser): ?>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between items-center py-1 border-b border-gray-200/60">
                                <span class="text-gray-500">Username:</span>
                                <span class="font-bold text-gray-800"><?php echo htmlspecialchars($currentUser['username'] ?? 'Unknown'); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-gray-200/60">
                                <span class="text-gray-500">Role:</span>
                                <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-semibold text-[11px]">
                                    <?php echo htmlspecialchars($currentUser['role_name'] ?? 'Super Admin'); ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-gray-200/60">
                                <span class="text-gray-500">Company:</span>
                                <span class="font-medium text-gray-700"><?php echo htmlspecialchars($currentUser['company_name'] ?? 'ADV'); ?> (<?php echo htmlspecialchars($currentUser['company_type'] ?? 'ADV'); ?>)</span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-gray-500">Privilege Level:</span>
                                <span class="font-semibold <?php echo $isAdmin ? 'text-emerald-600' : 'text-amber-600'; ?>">
                                    <?php echo $isAdmin ? '✔ ADV Administrator' : 'Contractor / Partner'; ?>
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="py-3 text-center text-gray-400 text-xs">
                            <i class="fas fa-user-slash text-2xl mb-2 text-gray-300 block"></i>
                            Not currently authenticated.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Diagnostic Column 2: Permission Engine Status -->
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 space-y-3">
                    <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <i class="fas fa-key text-amber-500"></i>
                        <span>Authorization Check</span>
                    </div>
                    <div class="space-y-2 text-xs">
                        <?php if ($requiredPermission): ?>
                            <div class="p-2.5 rounded-xl bg-white border border-gray-200">
                                <span class="text-gray-400 block text-[10px] uppercase font-semibold">Checked Permission:</span>
                                <span class="font-mono font-bold text-gray-800 text-xs"><?php echo htmlspecialchars($requiredPermission); ?></span>
                            </div>
                            <div class="flex items-center space-x-2 p-2 rounded-xl <?php echo $userHasPerm ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                                <i class="fas <?php echo $userHasPerm ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <span class="font-semibold"><?php echo $userHasPerm ? 'Granted in database' : 'Denied / Missing in role'; ?></span>
                            </div>
                        <?php else: ?>
                            <div class="p-3 rounded-xl bg-white border border-gray-200 text-gray-500">
                                <p>Standard module guard triggered. Ensure your role is granted access to this module under Permissions Management.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Diagnostic Column 3: Recommended Resolution -->
                <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 space-y-3">
                    <div class="flex items-center space-x-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <i class="fas fa-lightbulb text-indigo-500"></i>
                        <span>Recommended Resolution</span>
                    </div>
                    <div class="text-xs text-gray-600 space-y-2">
                        <?php if ($isAdmin): ?>
                            <p class="leading-relaxed">As an <strong>ADV Administrator</strong>, you can assign missing permissions to your role or sync full system permissions.</p>
                            <div class="pt-1">
                                <a href="/views/permissions/index.php" class="inline-flex items-center text-xs font-bold text-primary hover:underline">
                                    <i class="fas fa-sliders mr-1.5"></i> Open Permissions Manager &rarr;
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="leading-relaxed">Please contact your system administrator or ADV supervisor to request permissions for this section.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Toolbar -->
            <div class="mt-8 pt-6 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="/dashboard.php" class="px-5 py-2.5 bg-primary hover:bg-indigo-600 text-white font-semibold rounded-xl shadow-sm transition inline-flex items-center text-sm">
                        <i class="fas fa-house mr-2"></i> Return to Dashboard
                    </a>
                    <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='/dashboard.php'" 
                            class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition inline-flex items-center text-sm">
                        <i class="fas fa-arrow-left mr-2"></i> Go Back
                    </button>
                    <?php if ($isAdmin): ?>
                    <a href="/views/permissions/index.php" class="px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-primary font-semibold rounded-xl transition inline-flex items-center text-sm">
                        <i class="fas fa-user-shield mr-2"></i> Manage Permissions
                    </a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center space-x-2 text-xs text-gray-400">
                    <a href="/views/auth/login.php" class="hover:text-primary transition inline-flex items-center">
                        <i class="fas fa-right-to-bracket mr-1"></i> Switch Account
                    </a>
                    <span>&bull;</span>
                    <span>Server: Apache/XAMPP</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// If user is logged in, use the full application frame with sidebar and header
if ($isLoggedIn) {
    include __DIR__ . '/views/layouts/base.php';
} else {
    // If not logged in, render sleek standalone frame
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?></title>
        <link rel="icon" type="image/png" href="/assets/fav.png">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: '#6366f1',
                            dark: { 900: '#0f0f23', 800: '#1a1a2e' }
                        },
                        fontFamily: { sans: ['Inter', 'sans-serif'] }
                    }
                }
            }
        </script>
    </head>
    <body class="bg-gray-100 min-h-screen font-sans flex flex-col justify-center py-10 px-4 sm:px-6">
        <div class="max-w-4xl w-full mx-auto mb-4 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="text-2xl font-black text-gray-900 tracking-wider">LYNQ</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary font-bold">SYSTEM</span>
            </div>
            <a href="/views/auth/login.php" class="text-xs font-semibold text-primary hover:underline">
                <i class="fas fa-arrow-right-to-bracket mr-1"></i> Sign In
            </a>
        </div>
        <?php echo $content; ?>
    </body>
    </html>
    <?php
}
?>

