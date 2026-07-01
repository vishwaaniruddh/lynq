<?php if (isset($_SESSION['flash_success'])): ?>
<div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 flex items-center justify-between animate-fade-in">
    <div class="flex items-center">
        <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center mr-3">
            <i class="fas fa-check text-green-600"></i>
        </div>
        <span class="text-green-800"><?php echo htmlspecialchars($_SESSION['flash_success']); ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 p-1">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center justify-between animate-fade-in">
    <div class="flex items-center">
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center mr-3">
            <i class="fas fa-exclamation-circle text-red-600"></i>
        </div>
        <span class="text-red-800"><?php echo htmlspecialchars($_SESSION['flash_error']); ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 p-1">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if (isset($_SESSION['flash_warning'])): ?>
<div class="mb-4 p-4 rounded-xl bg-yellow-50 border border-yellow-200 flex items-center justify-between animate-fade-in">
    <div class="flex items-center">
        <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center mr-3">
            <i class="fas fa-exclamation-triangle text-yellow-600"></i>
        </div>
        <span class="text-yellow-800"><?php echo htmlspecialchars($_SESSION['flash_warning']); ?></span>
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
