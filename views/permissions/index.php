<?php
/**
 * Permission Management Dashboard
 * Main interface for permission management and testing
 */

require_once __DIR__ . '/../../config/autoload.php';

// Check authentication and ADV access
$sessionService = new SessionService();
$permissionMiddleware = new PermissionMiddleware();

if (!$sessionService->isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

if (!$permissionMiddleware->requireAdvUser()) {
    exit; // Middleware handles the error response
}

$currentUserId = $sessionService->getCurrentUserId();
$userModel = new User();
$currentUser = $userModel->findWithRelations($currentUserId);

$permissionEngine = new PermissionEngine();
$companyModel = new Company();
$permissionModel = new Permission();

// Get all contractor companies for delegation
$contractorCompanies = $companyModel->findByType('CONTRACTOR');

// Get all available permissions
$allPermissions = $permissionModel->findContractorAccessible();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'delegate_permission':
                $companyId = (int)$_POST['company_id'];
                $permissionName = $_POST['permission_name'];
                
                $result = $permissionEngine->delegatePermission($companyId, $permissionName, $currentUserId);
                echo json_encode(['success' => true, 'message' => 'Permission delegated successfully']);
                break;
                
            case 'revoke_permission':
                $companyId = (int)$_POST['company_id'];
                $permissionName = $_POST['permission_name'];
                
                $result = $permissionEngine->revokePermission($companyId, $permissionName, $currentUserId);
                echo json_encode(['success' => $result, 'message' => $result ? 'Permission revoked successfully' : 'Permission was not delegated']);
                break;
                
            case 'test_permission':
                $userId = (int)$_POST['user_id'];
                $permissionName = $_POST['permission_name'];
                
                $hasPermission = $permissionEngine->can($userId, $permissionName);
                echo json_encode(['success' => true, 'has_permission' => $hasPermission]);
                break;
                
            case 'get_company_permissions':
                $companyId = (int)$_POST['company_id'];
                $permissions = $permissionEngine->getCompanyDelegatedPermissions($companyId);
                echo json_encode(['success' => true, 'permissions' => $permissions]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Management - CRM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #007bff; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select, .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-success { background-color: #28a745; color: white; }
        .btn:hover { opacity: 0.8; }
        .result { margin-top: 15px; padding: 10px; border-radius: 4px; }
        .result.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .result.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .permissions-list { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
        .permission-item { padding: 5px; border-bottom: 1px solid #eee; }
        .permission-item:last-child { border-bottom: none; }
        .tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .tab { padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab.active { border-bottom-color: #007bff; background-color: #f8f9fa; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Permission Management Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?> (ADV User)</p>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="showTab('delegation')">Permission Delegation</div>
            <div class="tab" onclick="showTab('audit')">Audit Trail</div>
            <div class="tab" onclick="showTab('testing')">Permission Testing</div>
        </div>

        <!-- Permission Delegation Tab -->
        <div id="delegation" class="tab-content active">
            <div class="section">
                <h3>Delegate Permissions to Contractor Companies</h3>
                <div class="form-group">
                    <label for="company_select">Select Contractor Company:</label>
                    <select id="company_select" onchange="loadCompanyPermissions()">
                        <option value="">-- Select Company --</option>
                        <?php foreach ($contractorCompanies as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="permission_select">Select Permission to Delegate:</label>
                    <select id="permission_select">
                        <option value="">-- Select Permission --</option>
                        <?php foreach ($allPermissions as $permission): ?>
                            <option value="<?= htmlspecialchars($permission['name']) ?>">
                                <?= htmlspecialchars($permission['name']) ?> - <?= htmlspecialchars($permission['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button class="btn btn-primary" onclick="delegatePermission()">Delegate Permission</button>
                <button class="btn btn-danger" onclick="revokePermission()">Revoke Permission</button>
                
                <div id="delegation_result" class="result" style="display: none;"></div>
                
                <div class="section">
                    <h4>Current Delegated Permissions</h4>
                    <div id="company_permissions" class="permissions-list">
                        <p>Select a company to view delegated permissions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Trail Tab -->
        <div id="audit" class="tab-content">
            <div class="section">
                <h3>Permission Audit Trail</h3>
                <div class="form-group">
                    <label for="audit_company_select">Select Company for Audit Trail:</label>
                    <select id="audit_company_select" onchange="loadAuditTrail()">
                        <option value="">-- Select Company --</option>
                        <?php foreach ($contractorCompanies as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="audit_trail" class="permissions-list" style="max-height: 400px;">
                    <p>Select a company to view audit trail</p>
                </div>
            </div>
        </div>

        <!-- Permission Testing Tab -->
        <div id="testing" class="tab-content">
            <div class="section">
                <h3>Test User Permissions</h3>
                <div class="form-group">
                    <label for="test_user_id">User ID to Test:</label>
                    <input type="number" id="test_user_id" placeholder="Enter user ID">
                </div>
                
                <div class="form-group">
                    <label for="test_permission">Permission to Test:</label>
                    <select id="test_permission">
                        <option value="">-- Select Permission --</option>
                        <?php foreach ($allPermissions as $permission): ?>
                            <option value="<?= htmlspecialchars($permission['name']) ?>">
                                <?= htmlspecialchars($permission['name']) ?> - <?= htmlspecialchars($permission['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button class="btn btn-success" onclick="testPermission()">Test Permission</button>
                
                <div id="test_result" class="result" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function delegatePermission() {
            const companyId = document.getElementById('company_select').value;
            const permissionName = document.getElementById('permission_select').value;
            
            if (!companyId || !permissionName) {
                showResult('delegation_result', 'Please select both company and permission', 'error');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delegate_permission&company_id=${companyId}&permission_name=${encodeURIComponent(permissionName)}`
            })
            .then(response => response.json())
            .then(data => {
                showResult('delegation_result', data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    loadCompanyPermissions();
                }
            })
            .catch(error => {
                showResult('delegation_result', 'Error: ' + error.message, 'error');
            });
        }

        function revokePermission() {
            const companyId = document.getElementById('company_select').value;
            const permissionName = document.getElementById('permission_select').value;
            
            if (!companyId || !permissionName) {
                showResult('delegation_result', 'Please select both company and permission', 'error');
                return;
            }
            
            if (!confirm('Are you sure you want to revoke this permission?')) {
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=revoke_permission&company_id=${companyId}&permission_name=${encodeURIComponent(permissionName)}`
            })
            .then(response => response.json())
            .then(data => {
                showResult('delegation_result', data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    loadCompanyPermissions();
                }
            })
            .catch(error => {
                showResult('delegation_result', 'Error: ' + error.message, 'error');
            });
        }

        function loadCompanyPermissions() {
            const companyId = document.getElementById('company_select').value;
            
            if (!companyId) {
                document.getElementById('company_permissions').innerHTML = '<p>Select a company to view delegated permissions</p>';
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_company_permissions&company_id=${companyId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.permissions.length === 0) {
                        html = '<p>No permissions delegated to this company</p>';
                    } else {
                        data.permissions.forEach(permission => {
                            html += `<div class="permission-item">
                                <strong>${permission.name}</strong> - ${permission.description}
                                <br><small>Granted: ${permission.granted_at}</small>
                            </div>`;
                        });
                    }
                    document.getElementById('company_permissions').innerHTML = html;
                }
            })
            .catch(error => {
                document.getElementById('company_permissions').innerHTML = '<p>Error loading permissions</p>';
            });
        }

        function loadAuditTrail() {
            const companyId = document.getElementById('audit_company_select').value;
            
            if (!companyId) {
                document.getElementById('audit_trail').innerHTML = '<p>Select a company to view audit trail</p>';
                return;
            }
            
            // This would typically be another AJAX call, but for simplicity, we'll use the PermissionEngine method
            // In a real implementation, you'd create a separate endpoint for this
            document.getElementById('audit_trail').innerHTML = '<p>Loading audit trail... (Feature requires additional endpoint)</p>';
        }

        function testPermission() {
            const userId = document.getElementById('test_user_id').value;
            const permissionName = document.getElementById('test_permission').value;
            
            if (!userId || !permissionName) {
                showResult('test_result', 'Please enter user ID and select permission', 'error');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=test_permission&user_id=${userId}&permission_name=${encodeURIComponent(permissionName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const message = `User ${userId} ${data.has_permission ? 'HAS' : 'DOES NOT HAVE'} permission: ${permissionName}`;
                    showResult('test_result', message, data.has_permission ? 'success' : 'error');
                } else {
                    showResult('test_result', data.message, 'error');
                }
            })
            .catch(error => {
                showResult('test_result', 'Error: ' + error.message, 'error');
            });
        }

        function showResult(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.className = `result ${type}`;
            element.style.display = 'block';
            
            // Hide after 5 seconds
            setTimeout(() => {
                element.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>