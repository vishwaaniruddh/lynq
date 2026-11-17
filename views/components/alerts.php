<?php
// Collect query errors / messages if present
$getUrlError = $_GET['error'] ?? null;
if ($getUrlError) {
    $errorMap = [
        'permission_denied' => 'You do not have permission to perform this action.',
        'adv_required' => 'Access denied. This module is restricted to ADV administrators.',
        'company_access_denied' => 'Access denied. You cannot access resources from other companies.',
        'access_denied' => 'Access denied. You do not have permission to view this section.'
    ];
    $urlErrorMessage = $errorMap[$getUrlError] ?? $getUrlError;
}

$getUrlSuccess = $_GET['success'] ?? $_GET['msg'] ?? null;
$sessionErrorMessage = $_SESSION['error_message'] ?? null;
if (isset($_SESSION['error_message'])) unset($_SESSION['error_message']);
?>

<?php if (isset($_SESSION['flash_success']) || $getUrlSuccess): ?>
<div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 flex items-center justify-between animate-fade-in shadow-sm">
    <div class="flex items-center">
        <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center mr-3 text-green-600">
            <i class="fas fa-check"></i>
        </div>
        <span class="text-green-800 font-medium text-sm"><?php echo htmlspecialchars($_SESSION['flash_success'] ?? $getUrlSuccess); ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 p-1">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (isset($_SESSION['flash_error']) || $sessionErrorMessage || isset($urlErrorMessage)): ?>
<div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center justify-between animate-fade-in shadow-sm">
    <div class="flex items-center">
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center mr-3 text-red-600">
            <i class="fas fa-shield-halved"></i>
        </div>
        <div>
            <span class="text-red-800 font-semibold text-sm block"><?php echo htmlspecialchars($_SESSION['flash_error'] ?? $sessionErrorMessage ?? $urlErrorMessage); ?></span>
            <span class="text-red-600 text-xs">If you believe this is an error, please check with your system administrator or review permissions.</span>
        </div>
    </div>
    <div class="flex items-center space-x-2">
        <a href="/error.php?code=403&message=<?php echo urlencode($_SESSION['flash_error'] ?? $sessionErrorMessage ?? $urlErrorMessage); ?>" class="text-xs px-2.5 py-1 rounded-lg bg-red-100 hover:bg-red-200 text-red-800 font-semibold transition">
            Details
        </a>
        <button onclick="this.parentElement.parentElement.remove()" class="text-red-600 hover:text-red-800 p-1">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if (isset($_SESSION['flash_warning'])): ?>
<div class="mb-4 p-4 rounded-xl bg-yellow-50 border border-yellow-200 flex items-center justify-between animate-fade-in shadow-sm">
    <div class="flex items-center">
        <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center mr-3 text-yellow-600">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <span class="text-yellow-800 font-medium text-sm"><?php echo htmlspecialchars($_SESSION['flash_warning']); ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-yellow-600 hover:text-yellow-800 p-1">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php unset($_SESSION['flash_warning']); endif; ?>

<div id="ajax-alert" class="hidden fixed top-4 right-4 z-50 max-w-md"></div>

<style>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fade-in 0.3s ease-out; }
</style>
