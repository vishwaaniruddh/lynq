<?php
/**
 * Permission Audit Trail Viewer
 * Detailed audit trail viewing interface
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

$permissionEngine = new PermissionEngine();
$companyModel = new Company();

// Get all contractor companies
$contractorCompanies = $companyModel->findByType('CONTRACTOR');

// Handle AJAX request for audit trail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_audit_trail') {
    header('Content-Type: application/json');
    
    try {
        $companyId = (int)$_POST['company_id'];
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
        
        $auditTrail = $permissionEngine->getPermissionAuditTrail($companyId, $limit);
        echo json_encode(['success' => true, 'audit_trail' => $auditTrail]);
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
    <title>Permission Audit Trail - CRM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
        .controls { margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
        .form-group { margin-bottom: 15px; display: inline-block; margin-right: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select, .form-group input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn:hover { opacity: 0.8; }
        .audit-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .audit-table th, .audit-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .audit-table th { background-color: #f8f9fa; font-weight: bold; }
        .audit-table tr:hover { background-color: #f5f5f5; }
        .action-delegated { color: #28a745; font-weight: bold; }
        .action-revoked { color: #dc3545; font-weight: bold; }
        .loading { text-align: center; padding: 20px; color: #666; }
        .no-data { text-align: center; padding: 20px; color: #666; font-style: italic; }
        .stats { display: flex; justify-content: space-around; margin-bottom: 20px; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; min-width: 120px; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Permission Audit Trail</h1>
            <p>View detailed audit logs for permission delegation and revocation activities</p>
        </div>

        <div class="controls">
            <div class="form-group">
                <label for="company_select">Select Company:</label>
                <select id="company_select">
                    <option value="">-- Select Company --</option>
                    <?php foreach ($contractorCompanies as $company): ?>
                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="limit_select">Records to Show:</label>
                <select id="limit_select">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </div>
            
            <div class="form-group" style="vertical-align: bottom;">
                <button class="btn btn-primary" onclick="loadAuditTrail()">Load Audit Trail</button>
            </div>
        </div>

        <div id="stats" class="stats" style="display: none;">
            <div class="stat-card">
                <div class="stat-number" id="total-records">0</div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="delegations">0</div>
                <div class="stat-label">Delegations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="revocations">0</div>
                <div class="stat-label">Revocations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="unique-permissions">0</div>
                <div class="stat-label">Unique Permissions</div>
            </div>
        </div>

        <div id="audit-container">
            <div class="no-data">
                Select a company and click "Load Audit Trail" to view permission audit logs
            </div>
        </div>
    </div>

    <script>
        function loadAuditTrail() {
            const companyId = document.getElementById('company_select').value;
            const limit = document.getElementById('limit_select').value;
            
            if (!companyId) {
                alert('Please select a company');
                return;
            }
            
            // Show loading state
            document.getElementById('audit-container').innerHTML = '<div class="loading">Loading audit trail...</div>';
            document.getElementById('stats').style.display = 'none';
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_audit_trail&company_id=${companyId}&limit=${limit}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayAuditTrail(data.audit_trail);
                    updateStats(data.audit_trail);
                } else {
                    document.getElementById('audit-container').innerHTML = 
                        `<div class="no-data">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('audit-container').innerHTML = 
                    `<div class="no-data">Error loading audit trail: ${error.message}</div>`;
            });
        }

        function displayAuditTrail(auditTrail) {
            if (auditTrail.length === 0) {
                document.getElementById('audit-container').innerHTML = 
                    '<div class="no-data">No audit records found for this company</div>';
                return;
            }

            let html = `
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Permission</th>
                            <th>Module</th>
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            auditTrail.forEach(record => {
                const actionClass = record.action === 'DELEGATED' ? 'action-delegated' : 'action-revoked';
                const timestamp = new Date(record.timestamp).toLocaleString();
                
                html += `
                    <tr>
                        <td>${timestamp}</td>
                        <td><span class="${actionClass}">${record.action}</span></td>
                        <td>${record.permission_name || 'N/A'}</td>
                        <td>${record.module || 'N/A'}</td>
                        <td>${record.performed_by_username || 'Unknown'}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            document.getElementById('audit-container').innerHTML = html;
        }

        function updateStats(auditTrail) {
            const totalRecords = auditTrail.length;
            let delegations = 0;
            let revocations = 0;
            const uniquePermissions = new Set();

            auditTrail.forEach(record => {
                if (record.action === 'DELEGATED') {
                    delegations++;
                } else if (record.action === 'REVOKED') {
                    revocations++;
                }
                
                if (record.permission_name) {
                    uniquePermissions.add(record.permission_name);
                }
            });

            document.getElementById('total-records').textContent = totalRecords;
            document.getElementById('delegations').textContent = delegations;
            document.getElementById('revocations').textContent = revocations;
            document.getElementById('unique-permissions').textContent = uniquePermissions.size;
            
            document.getElementById('stats').style.display = 'flex';
        }
    </script>
</body>
</html>