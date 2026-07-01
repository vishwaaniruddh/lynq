<?php
require_once __DIR__ . '/../config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!can('companies.update') || !isAdvUser()) {
    $_SESSION['flash_error'] = 'You do not have permission to edit companies';
    header('Location: ../dashboard.php');
    exit;
}

$companyId = $_GET['id'] ?? null;
if (!$companyId) {
    header('Location: index.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Edit Company';
$currentPage = 'companies';
$isLoggedIn = true;

$db = Database::getInstance()->getConnection();
$errors = [];

try {
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        $_SESSION['flash_error'] = 'Company not found';
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading company';
    header('Location: index.php');
    exit;
}

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Companies', 'url' => 'index.php'],
    ['label' => 'Edit: ' . $company['name']]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
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
        $stmt = $db->prepare("SELECT id FROM companies WHERE name = ? AND id != ?");
        $stmt->execute([$formData['name'], $companyId]);
        if ($stmt->fetch()) {
            $errors[] = 'Company name already exists';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE companies SET name = ?, status = ?, address = ?, contact_phone = ?, contact_email = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $formData['name'],
                $formData['status'],
                $formData['address'],
                $formData['contact_phone'],
                $formData['contact_email'],
                $companyId
            ]);
            
            $_SESSION['flash_success'] = 'Company updated successfully';
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error updating company: ' . $e->getMessage();
        }
    }
    
    $company = array_merge($company, $formData);
}

ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Edit Company</h3>
            <p class="text-sm text-gray-500">Update company details</p>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="p-4 bg-red-50 border-b">
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
                    value="<?php echo htmlspecialchars($company['name']); ?>">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <input type="text" value="<?php echo $company['type']; ?>" disabled
                        class="w-full px-4 py-2 border rounded-lg bg-gray-50 text-gray-500">
                    <p class="text-xs text-gray-500 mt-1">Company type cannot be changed</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="ACTIVE" <?php echo $company['status'] === 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                        <option value="INACTIVE" <?php echo $company['status'] === 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="SUSPENDED" <?php echo $company['status'] === 'SUSPENDED' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                <input type="email" name="contact_email"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    value="<?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                <input type="text" name="contact_phone"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    value="<?php echo htmlspecialchars($company['contact_phone'] ?? ''); ?>">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                <textarea name="address" rows="3"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                <a href="index.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition">
                    <i class="fas fa-save mr-2"></i>Update Company
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>
