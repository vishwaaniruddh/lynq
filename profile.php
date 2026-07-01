<?php
/**
 * User Profile Page
 * Enhanced profile management with new fields and revision history
 * 
 * Requirements: 1.1, 1.2, 2.1, 2.2, 2.3, 3.1, 3.2, 4.1, 4.2, 4.3, 5.1, 5.2, 6.1, 6.2, 6.3, 6.4, 7.1, 7.2, 7.3, 8.2, 8.3, 8.4
 * 
 * **Feature: user-profile-enhancement**
 */

require_once __DIR__ . '/config/autoload.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
$baseUrl = '';
$pageTitle = 'My Profile';
$currentPage = 'profile';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Profile']
];

$db = Database::getInstance()->getConnection();
$errors = [];
$success = false;

// Get full user data including new profile fields
try {
    $stmt = $db->prepare("
        SELECT u.*, c.name as company_name, c.type as company_type, r.name as role_name 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = 'Error loading profile';
}

ob_start();
?>

<div class="max-w-5xl mx-auto">
    <!-- Success/Error Messages -->
    <div id="profileMessages" class="mb-4 hidden">
        <div id="successMessage" class="hidden p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
            <i class="fas fa-check-circle mr-2"></i><span></span>
        </div>
        <div id="errorMessage" class="hidden p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
            <i class="fas fa-exclamation-circle mr-2"></i><span></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6 text-center">
                    <!-- Profile Picture Display - Requirements: 6.1 -->
                    <div id="profilePictureContainer" class="relative w-28 h-28 mx-auto mb-4">
                        <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/' . $user['profile_picture'])): ?>
                        <img id="profilePictureImg" src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                            alt="Profile Picture" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-lg">
                        <?php else: ?>
                        <div id="profilePictureDefault" class="w-28 h-28 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center border-4 border-white shadow-lg">
                            <span class="text-4xl font-bold text-white"><?php echo strtoupper(substr($user['first_name'] ?? $user['username'], 0, 1)); ?></span>
                        </div>
                        <img id="profilePictureImg" src="" alt="Profile Picture" class="hidden w-28 h-28 rounded-full object-cover border-4 border-white shadow-lg">
                        <?php endif; ?>
                        <!-- Upload overlay -->
                        <label for="profilePictureInput" class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-full opacity-0 hover:opacity-100 transition-opacity cursor-pointer">
                            <i class="fas fa-camera text-white text-xl"></i>
                        </label>
                        <input type="file" id="profilePictureInput" accept="image/jpeg,image/png,image/gif" class="hidden">
                    </div>
                    <p class="text-xs text-gray-500 mb-3">Click to upload (JPEG, PNG, GIF - Max 2MB)</p>
                    
                    <h3 id="profileCardName" class="text-xl font-semibold text-gray-800">
                        <?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username']); ?>
                    </h3>
                    <p class="text-gray-500"><?php echo htmlspecialchars($user['role_name']); ?></p>
                    <div class="mt-3">
                        <?php if ($user['company_type'] === 'ADV'): ?>
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">ADV User</span>
                        <?php else: ?>
                        <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">Contractor</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="border-t p-6 space-y-3">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-building w-5 mr-3"></i>
                        <span><?php echo htmlspecialchars($user['company_name']); ?></span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-envelope w-5 mr-3"></i>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <?php if (!empty($user['contact_number'])): ?>
                    <div id="profileCardPhone" class="flex items-center text-gray-600">
                        <i class="fas fa-phone w-5 mr-3"></i>
                        <span><?php echo htmlspecialchars($user['contact_number']); ?></span>
                    </div>
                    <?php else: ?>
                    <div id="profileCardPhone" class="flex items-center text-gray-600 hidden">
                        <i class="fas fa-phone w-5 mr-3"></i>
                        <span></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-calendar w-5 mr-3"></i>
                        <span>Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
                
                <!-- Revision History Link - Requirement 8.2 -->
                <div class="border-t p-4">
                    <button id="viewRevisionHistoryBtn" class="w-full text-center text-primary hover:text-blue-700 text-sm">
                        <i class="fas fa-history mr-2"></i>View Revision History
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Edit Profile Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Profile</h3>
                </div>
                
                <form id="profileForm" class="p-6 space-y-6">
                    <!-- Name Fields - Requirements: 1.1, 1.2 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="first_name" id="firstName" maxlength="100"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                placeholder="Enter your first name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="last_name" id="lastName" maxlength="100"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                placeholder="Enter your last name">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled
                            class="w-full px-4 py-2 border rounded-lg bg-gray-50 text-gray-500">
                        <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled
                            class="w-full px-4 py-2 border rounded-lg bg-gray-50 text-gray-500">
                        <p class="text-xs text-gray-500 mt-1">Contact administrator to change email</p>
                    </div>
                    
                    <!-- Contact Number - Requirements: 2.1, 2.2 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                        <input type="tel" name="contact_number" id="contactNumber" maxlength="20"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>"
                            placeholder="e.g., +1 (555) 123-4567">
                        <p id="contactNumberError" class="text-xs text-red-500 mt-1 hidden">Invalid format. Use digits, spaces, dashes, parentheses, or plus sign.</p>
                    </div>
                    
                    <!-- Address - Requirement: 3.1 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <textarea name="address" id="address" rows="2" maxlength="500"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
                            placeholder="Enter your address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Date of Birth and Sex - Requirements: 4.1, 5.1 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="dateOfBirth"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>"
                                max="<?php echo date('Y-m-d'); ?>">
                            <p id="dateOfBirthError" class="text-xs text-red-500 mt-1 hidden">Date must be in the past</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sex</label>
                            <select name="sex" id="sex"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select...</option>
                                <option value="male" <?php echo ($user['sex'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($user['sex'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($user['sex'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Bio - Requirements: 7.1, 7.2 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                        <textarea name="bio" id="bio" rows="4" maxlength="500"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
                            placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        <div class="flex justify-between items-center mt-1">
                            <p id="bioError" class="text-xs text-red-500 hidden">Bio must be 500 characters or less</p>
                            <p class="text-xs text-gray-500"><span id="bioCharCount"><?php echo strlen($user['bio'] ?? ''); ?></span>/500 characters</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['company_name']); ?>" disabled
                                class="w-full px-4 py-2 border rounded-lg bg-gray-50 text-gray-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['role_name']); ?>" disabled
                                class="w-full px-4 py-2 border rounded-lg bg-gray-50 text-gray-500">
                        </div>
                    </div>
                    
                    <div class="pt-4 border-t flex justify-between items-center">
                        <a href="change-password.php" class="text-primary hover:underline">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </a>
                        <button type="submit" id="saveProfileBtn" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Revision History Modal - Requirements: 8.2, 8.3, 8.4 -->
<div id="revisionHistoryModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] flex flex-col">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-history mr-2 text-primary"></i>Profile Revision History
            </h3>
            <button id="closeRevisionModalBtn" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="revisionHistoryContent" class="p-6 overflow-y-auto flex-1">
            <div id="revisionHistoryLoading" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                <p class="text-gray-500 mt-2">Loading revision history...</p>
            </div>
            <div id="revisionHistoryEmpty" class="text-center py-8 hidden">
                <i class="fas fa-inbox text-4xl text-gray-300"></i>
                <p class="text-gray-500 mt-2">No revision history found</p>
            </div>
            <div id="revisionHistoryList" class="space-y-4 hidden"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const saveProfileBtn = document.getElementById('saveProfileBtn');
    const bioTextarea = document.getElementById('bio');
    const bioCharCount = document.getElementById('bioCharCount');
    const bioError = document.getElementById('bioError');
    const contactNumber = document.getElementById('contactNumber');
    const contactNumberError = document.getElementById('contactNumberError');
    const dateOfBirth = document.getElementById('dateOfBirth');
    const dateOfBirthError = document.getElementById('dateOfBirthError');
    const profilePictureInput = document.getElementById('profilePictureInput');
    const viewRevisionHistoryBtn = document.getElementById('viewRevisionHistoryBtn');
    const revisionHistoryModal = document.getElementById('revisionHistoryModal');
    const closeRevisionModalBtn = document.getElementById('closeRevisionModalBtn');
    
    // Bio character counter - Requirement 7.2
    bioTextarea.addEventListener('input', function() {
        const length = this.value.length;
        bioCharCount.textContent = length;
        
        if (length > 500) {
            bioError.classList.remove('hidden');
            bioCharCount.parentElement.classList.add('text-red-500');
        } else {
            bioError.classList.add('hidden');
            bioCharCount.parentElement.classList.remove('text-red-500');
        }
    });
    
    // Contact number validation - Requirement 2.2
    function validateContactNumber(value) {
        if (!value || value.trim() === '') return true;
        return /^[\d\s\-\(\)\+]+$/.test(value);
    }
    
    contactNumber.addEventListener('input', function() {
        if (!validateContactNumber(this.value)) {
            contactNumberError.classList.remove('hidden');
            this.classList.add('border-red-500');
        } else {
            contactNumberError.classList.add('hidden');
            this.classList.remove('border-red-500');
        }
    });
    
    // Date of birth validation - Requirement 4.2
    function validateDateOfBirth(value) {
        if (!value) return true;
        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return selectedDate < today;
    }
    
    dateOfBirth.addEventListener('change', function() {
        if (!validateDateOfBirth(this.value)) {
            dateOfBirthError.classList.remove('hidden');
            this.classList.add('border-red-500');
        } else {
            dateOfBirthError.classList.add('hidden');
            this.classList.remove('border-red-500');
        }
    });
    
    // Show message helper
    function showMessage(type, message) {
        const messagesDiv = document.getElementById('profileMessages');
        const successDiv = document.getElementById('successMessage');
        const errorDiv = document.getElementById('errorMessage');
        
        messagesDiv.classList.remove('hidden');
        
        if (type === 'success') {
            successDiv.querySelector('span').textContent = message;
            successDiv.classList.remove('hidden');
            errorDiv.classList.add('hidden');
        } else {
            errorDiv.querySelector('span').textContent = message;
            errorDiv.classList.remove('hidden');
            successDiv.classList.add('hidden');
        }
        
        // Scroll to top to show message
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messagesDiv.classList.add('hidden');
        }, 5000);
    }
    
    // Form submission - Requirements: 2.2, 4.2, 7.2
    profileForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Validate all fields
        let isValid = true;
        
        if (!validateContactNumber(contactNumber.value)) {
            contactNumberError.classList.remove('hidden');
            contactNumber.classList.add('border-red-500');
            isValid = false;
        }
        
        if (!validateDateOfBirth(dateOfBirth.value)) {
            dateOfBirthError.classList.remove('hidden');
            dateOfBirth.classList.add('border-red-500');
            isValid = false;
        }
        
        if (bioTextarea.value.length > 500) {
            bioError.classList.remove('hidden');
            isValid = false;
        }
        
        if (!isValid) {
            showMessage('error', 'Please fix the validation errors before saving.');
            return;
        }
        
        // Disable button and show loading
        saveProfileBtn.disabled = true;
        saveProfileBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        
        try {
            const formData = {
                first_name: document.getElementById('firstName').value.trim(),
                last_name: document.getElementById('lastName').value.trim(),
                contact_number: contactNumber.value.trim() || null,
                address: document.getElementById('address').value.trim() || null,
                date_of_birth: dateOfBirth.value || null,
                sex: document.getElementById('sex').value || null,
                bio: bioTextarea.value.trim() || null
            };
            
            const response = await fetch('<?php echo $baseUrl; ?>/api/users/profile.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showMessage('success', result.message || 'Profile updated successfully');
                
                // Update profile card
                updateProfileCard(result.data);
            } else {
                let errorMsg = result.message || 'Failed to update profile';
                if (result.fields) {
                    const fieldErrors = Object.values(result.fields).join(', ');
                    errorMsg += ': ' + fieldErrors;
                }
                showMessage('error', errorMsg);
            }
        } catch (error) {
            console.error('Profile update error:', error);
            showMessage('error', 'An error occurred while updating your profile');
        } finally {
            saveProfileBtn.disabled = false;
            saveProfileBtn.innerHTML = '<i class="fas fa-save mr-2"></i>Save Changes';
        }
    });
    
    // Update profile card after save
    function updateProfileCard(data) {
        // Update name
        const fullName = [data.first_name, data.last_name].filter(Boolean).join(' ') || data.username;
        document.getElementById('profileCardName').textContent = fullName;
        
        // Update phone
        const phoneDiv = document.getElementById('profileCardPhone');
        if (data.contact_number) {
            phoneDiv.querySelector('span').textContent = data.contact_number;
            phoneDiv.classList.remove('hidden');
        } else {
            phoneDiv.classList.add('hidden');
        }
    }
    
    // Profile picture upload - Requirements: 6.2, 6.3, 6.4
    profilePictureInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showMessage('error', 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.');
            this.value = '';
            return;
        }
        
        // Validate file size (2MB)
        if (file.size > 2097152) {
            showMessage('error', 'File size must be under 2MB');
            this.value = '';
            return;
        }
        
        // Show preview immediately
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('profilePictureImg');
            const defaultDiv = document.getElementById('profilePictureDefault');
            
            img.src = e.target.result;
            img.classList.remove('hidden');
            if (defaultDiv) {
                defaultDiv.classList.add('hidden');
            }
        };
        reader.readAsDataURL(file);
        
        // Upload file
        const formData = new FormData();
        formData.append('profile_picture', file);
        
        try {
            const response = await fetch('<?php echo $baseUrl; ?>/api/users/profile-picture.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showMessage('success', 'Profile picture updated successfully');
            } else {
                showMessage('error', result.message || 'Failed to upload profile picture');
                // Revert preview on error
                this.value = '';
            }
        } catch (error) {
            console.error('Profile picture upload error:', error);
            showMessage('error', 'An error occurred while uploading your profile picture');
            this.value = '';
        }
    });
    
    // Revision history modal - Requirements: 8.2, 8.3, 8.4
    viewRevisionHistoryBtn.addEventListener('click', async function() {
        revisionHistoryModal.classList.remove('hidden');
        
        const loadingDiv = document.getElementById('revisionHistoryLoading');
        const emptyDiv = document.getElementById('revisionHistoryEmpty');
        const listDiv = document.getElementById('revisionHistoryList');
        
        loadingDiv.classList.remove('hidden');
        emptyDiv.classList.add('hidden');
        listDiv.classList.add('hidden');
        
        try {
            const response = await fetch('<?php echo $baseUrl; ?>/api/users/profile-revisions.php');
            const result = await response.json();
            
            loadingDiv.classList.add('hidden');
            
            if (result.success && result.data && result.data.length > 0) {
                listDiv.innerHTML = '';
                
                result.data.forEach(revision => {
                    const revisionEl = createRevisionElement(revision);
                    listDiv.appendChild(revisionEl);
                });
                
                listDiv.classList.remove('hidden');
            } else {
                emptyDiv.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Revision history error:', error);
            loadingDiv.classList.add('hidden');
            emptyDiv.classList.remove('hidden');
            emptyDiv.querySelector('p').textContent = 'Error loading revision history';
        }
    });
    
    function createRevisionElement(revision) {
        const div = document.createElement('div');
        div.className = 'border rounded-lg p-4 bg-gray-50';
        
        const changedFields = Array.isArray(revision.changed_fields) 
            ? revision.changed_fields 
            : JSON.parse(revision.changed_fields || '[]');
        const oldValues = typeof revision.old_values === 'object' 
            ? revision.old_values 
            : JSON.parse(revision.old_values || '{}');
        const newValues = typeof revision.new_values === 'object' 
            ? revision.new_values 
            : JSON.parse(revision.new_values || '{}');
        
        const date = new Date(revision.created_at);
        const formattedDate = date.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        
        let changesHtml = changedFields.map(field => {
            const oldVal = oldValues[field] || '<em>empty</em>';
            const newVal = newValues[field] || '<em>empty</em>';
            const fieldLabel = field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            return `
                <div class="text-sm">
                    <span class="font-medium text-gray-700">${fieldLabel}:</span>
                    <span class="text-red-500 line-through">${oldVal}</span>
                    <i class="fas fa-arrow-right text-gray-400 mx-1 text-xs"></i>
                    <span class="text-green-600">${newVal}</span>
                </div>
            `;
        }).join('');
        
        div.innerHTML = `
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs text-gray-500"><i class="fas fa-clock mr-1"></i>${formattedDate}</span>
                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">${changedFields.length} field(s) changed</span>
            </div>
            <div class="space-y-1">${changesHtml}</div>
        `;
        
        return div;
    }
    
    closeRevisionModalBtn.addEventListener('click', function() {
        revisionHistoryModal.classList.add('hidden');
    });
    
    // Close modal on outside click
    revisionHistoryModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !revisionHistoryModal.classList.contains('hidden')) {
            revisionHistoryModal.classList.add('hidden');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layouts/base.php';
?>
