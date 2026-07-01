<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('companies.create') || !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to create companies';
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Create Company';
$currentPage = 'companies';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Companies', 'url' => 'index.php'],
    ['label' => 'Create']
];

$db = Database::getInstance()->getConnection();
$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'type' => $_POST['type'] ?? 'CONTRACTOR',
        'status' => $_POST['status'] ?? 'ACTIVE',
        'address' => trim($_POST['address'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? '')
    ];
    
    if (empty($formData['name'])) {
        $errors[] = 'Company name is required';
    }
    
    if (!empty($formData['contact_email']) && !filter_var($formData['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check name uniqueness
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM companies WHERE name = ?");
        $stmt->execute([$formData['name']]);
        if ($stmt->fetch()) {
            $errors[] = 'Company name already exists';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO companies (name, type, status, address, contact_phone, contact_email, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $formData['name'],
                $formData['type'],
                $formData['status'],
                $formData['address'],
                $formData['contact_phone'],
                $formData['contact_email']
            ]);
            
            $_SESSION['flash_success'] = 'Company created successfully';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error creating company: ' . $e->getMessage();
        }
    }
}

ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Create New Company</h3>
            <p class="text-sm text-gray-500">Add a new company to the system</p>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="p-4 bg-red-50 border-b border-red-100">
            <?php foreach ($errors as $error): ?>
            <p class="text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Company Name *</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                    placeholder="Enter company name">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                    <select name="type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="CONTRACTOR" <?php echo ($formData['type'] ?? '') === 'CONTRACTOR' ? 'selected' : ''; ?>>Contractor</option>
                        <option value="ADV" <?php echo ($formData['type'] ?? '') === 'ADV' ? 'selected' : ''; ?>>ADV</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="ACTIVE" <?php echo ($formData['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                        <option value="INACTIVE" <?php echo ($formData['status'] ?? '') === 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="SUSPENDED" <?php echo ($formData['status'] ?? '') === 'SUSPENDED' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                <input type="email" name="contact_email"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    value="<?php echo htmlspecialchars($formData['contact_email'] ?? ''); ?>"
                    placeholder="company@example.com">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                <input type="text" name="contact_phone"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    value="<?php echo htmlspecialchars($formData['contact_phone'] ?? ''); ?>"
                    placeholder="Phone number">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                <textarea name="address" rows="3"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    placeholder="Company address"><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-save mr-2"></i>Create Company
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
