<?php
/**
 * Settings Index Page
 * System settings management (ADV only)
 */

require_once __DIR__ . '/../config/autoload.php';

// Initialize services
$sessionService = new SessionService();
$authService = new AuthenticationService();
$settingsService = new SettingsService();

// Check authentication
if (!$sessionService->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// Check permission - ADV only
if (!can('system.manage') || !isAdvUser()) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

// Get current user
$currentUserId = $sessionService->getCurrentUserId();
$userModel = new User();
$currentUser = $userModel->findWithRelations($currentUserId);

if (!$currentUser) {
    header('Location: ../logout.php');
    exit;
}

// Get settings categories
try {
    $settingsCategories = $settingsService->getSettingsByCategory();
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    $settingsCategories = [];
}

// Page setup
$pageTitle = 'System Settings - ADV CRM';
$currentPage = 'settings';
$baseUrl = '..';
$isLoggedIn = true;
$menuService = new MenuService();

ob_start();
?>

<style>
/* Card hover effects */
.hover\:shadow-md:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Transition effects */
.transition-shadow {
    transition: box-shadow 0.15s ease-in-out;
}

.transition {
    transition: all 0.15s ease-in-out;
}

/* Alert animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

#alert-container:not(.hidden) {
    animation: fadeIn 0.3s ease;
}
</style>

<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
        <p class="text-gray-600 mt-1">Configure system-wide settings (ADV administrators only)</p>
    </div>
    
    <!-- Alert Messages -->
    <div id="alert-container" class="mb-6 hidden">
        <div id="alert-message" class="p-4 rounded-lg border"></div>
    </div>
    
    <!-- Settings Categories -->
    <?php if (empty($settingsCategories)): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mb-2"></i>
            <h3 class="font-semibold text-yellow-800 mb-2">No Settings Available</h3>
            <p class="text-yellow-700">System settings have not been initialized yet. Please run the settings migration.</p>
        </div>
    <?php else: ?>
        <!-- Settings Category Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($settingsCategories as $category => $settings): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-lg <?php echo getCategoryIconBg($category); ?> flex items-center justify-center mr-4">
                            <i class="<?php echo getCategoryIcon($category); ?> text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 capitalize"><?php echo htmlspecialchars($category); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo count($settings); ?> settings</p>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4"><?php echo getCategoryDescription($category); ?></p>
                    <button 
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                        onclick="openCategorySettings('<?php echo htmlspecialchars($category); ?>')"
                    >
                        Configure <?php echo htmlspecialchars($category); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- System Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-8">
        <h3 class="font-semibold text-gray-900 mb-4">System Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <p class="text-sm text-gray-500">PHP Version</p>
                <p class="font-medium"><?php echo phpversion(); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Server Software</p>
                <p class="font-medium"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Database</p>
                <p class="font-medium">MySQL</p>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3">
                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">Confirm Action</h3>
            </div>
            <p id="confirmation-message" class="text-gray-600 mb-6"></p>
            <div class="flex justify-end space-x-3">
                <button 
                    id="cancel-btn"
                    class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                    onclick="hideConfirmationModal()"
                >
                    Cancel
                </button>
                <button 
                    id="confirm-btn"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audit History Modal -->
<div id="audit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Setting Change History</h3>
                <button 
                    class="text-gray-400 hover:text-gray-600 transition"
                    onclick="hideAuditModal()"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="audit-content" class="p-6 overflow-y-auto max-h-[70vh]">
                <!-- Audit history will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Settings category navigation
function openCategorySettings(category) {
    // For now, show an alert - later this can navigate to a dedicated settings page
    showAlert(`Opening ${category} settings...`, 'info');
    
    // TODO: Navigate to category-specific settings page
    // window.location.href = `category.php?category=${encodeURIComponent(category)}`;
}

// Utility functions
function showAlert(message, type = 'info') {
    const container = document.getElementById('alert-container');
    const messageDiv = document.getElementById('alert-message');
    
    const colors = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
    };
    
    messageDiv.className = `p-4 rounded-lg border ${colors[type] || colors.info}`;
    messageDiv.textContent = message;
    container.classList.remove('hidden');
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
        container.classList.add('hidden');
    }, 3000);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add CSRF token to meta if not present
    if (!document.querySelector('meta[name="csrf-token"]')) {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
        document.head.appendChild(meta);
    }
});
</script>

<?php
// Helper functions for rendering
function getCategoryIcon($category) {
    $icons = [
        'general' => 'fas fa-cog text-gray-600',
        'security' => 'fas fa-shield-alt text-red-500',
        'email' => 'fas fa-envelope text-blue-500',
        'backup' => 'fas fa-database text-green-500',
        'logging' => 'fas fa-file-alt text-yellow-600',
        'performance' => 'fas fa-tachometer-alt text-purple-500'
    ];
    return $icons[$category] ?? 'fas fa-cog text-gray-600';
}

function getCategoryIconBg($category) {
    $backgrounds = [
        'general' => 'bg-gray-100',
        'security' => 'bg-red-100',
        'email' => 'bg-blue-100',
        'backup' => 'bg-green-100',
        'logging' => 'bg-yellow-100',
        'performance' => 'bg-purple-100'
    ];
    return $backgrounds[$category] ?? 'bg-gray-100';
}

function getCategoryDescription($category) {
    $descriptions = [
        'general' => 'Basic system configuration',
        'security' => 'Security and authentication settings',
        'email' => 'Email and notification configuration',
        'backup' => 'Database backup settings',
        'logging' => 'System logging configuration',
        'performance' => 'Performance and optimization settings'
    ];
    return $descriptions[$category] ?? 'System settings';
}
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
