<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$testStatuses = [
    'pending_materials' => ['isApproved' => false, 'canEdit' => false],
    'materials_received' => ['isApproved' => false, 'canEdit' => true],
    'in_progress' => ['isApproved' => false, 'canEdit' => true],
    'submitted' => ['isApproved' => false, 'canEdit' => true],
    'pending_contractor_review' => ['isApproved' => false, 'canEdit' => true],
    'contractor_rejected' => ['isApproved' => false, 'canEdit' => true],
    'adv_rejected' => ['isApproved' => false, 'canEdit' => true],
    'contractor_approved' => ['isApproved' => true, 'canEdit' => false],
    'adv_approved' => ['isApproved' => true, 'canEdit' => false],
];

echo "Testing Approval & Edit Rules across statuses:\n";
foreach ($testStatuses as $status => $expected) {
    $isApproved = in_array($status, ['contractor_approved', 'adv_approved']);
    $canEdit = !$isApproved && !in_array($status, ['pending_assignment', 'pending_eta', 'pending_ada', 'pending_materials']);
    
    $apprPass = $isApproved === $expected['isApproved'] ? 'PASS' : 'FAIL';
    $editPass = $canEdit === $expected['canEdit'] ? 'PASS' : 'FAIL';
    
    echo "  Status: {$status} => isApproved: " . ($isApproved ? 'YES' : 'NO') . " [{$apprPass}], canEdit: " . ($canEdit ? 'YES' : 'NO') . " [{$editPass}]\n";
}
