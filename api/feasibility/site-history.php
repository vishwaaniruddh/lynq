<?php
/**
 * Site History API
 * 
 * Returns the complete timeline of events for a site including:
 * - Site added
 * - Delegated to contractor
 * - Contractor accepted
 * - Assigned to engineer
 * - ETA submitted
 * - ADA submitted
 * - Feasibility completed
 * - Approval/Rejection events
 */

require_once __DIR__ . '/../../config/autoload.php';
require_once __DIR__ . '/../../services/FeasibilityService.php';
require_once __DIR__ . '/../../services/FeasibilityReviewService.php';

header('Content-Type: application/json');

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication required']
    ]);
    exit;
}

$siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if ($siteId <= 0 && $assignmentId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'INVALID_PARAMS', 'message' => 'Site ID or Assignment ID is required']
    ]);
    exit;
}

try {
    $db = DatabaseConfig::getInstance();
    $timeline = [];
    
    // Get site info
    $siteInfo = null;
    if ($siteId > 0) {
        $siteInfo = $db->getResults("SELECT * FROM sites WHERE id = ?", [$siteId], 'i')[0] ?? null;
    } elseif ($assignmentId > 0) {
        $siteInfo = $db->getResults("
            SELECT s.*, ea.id as assignment_id 
            FROM engineer_assignments ea 
            JOIN site_delegations sd ON ea.delegation_id = sd.id 
            JOIN sites s ON sd.site_id = s.id 
            WHERE ea.id = ?
        ", [$assignmentId], 'i')[0] ?? null;
        $siteId = $siteInfo['id'] ?? 0;
    }
    
    if (!$siteInfo) {
        throw new Exception('Site not found');
    }
    
    // 1. Site Added
    if (!empty($siteInfo['created_at'])) {
        $createdBy = null;
        if (!empty($siteInfo['created_by'])) {
            $user = $db->getResults("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?", [$siteInfo['created_by']], 'i')[0] ?? null;
            $createdBy = $user['name'] ?? null;
        }
        $timeline[] = [
            'event_type' => 'site_added',
            'event_title' => 'Site Added',
            'event_description' => 'Site was added to the system',
            'event_date' => $siteInfo['created_at'],
            'performed_by' => $createdBy
        ];
    }
    
    // 2. Get delegation info
    $delegations = $db->getResults("
        SELECT sd.*, 
               c.name as contractor_name,
               CONCAT(u.first_name, ' ', u.last_name) as delegated_by_name
        FROM site_delegations sd
        LEFT JOIN companies c ON sd.contractor_id = c.id
        LEFT JOIN users u ON sd.delegated_by = u.id
        WHERE sd.site_id = ?
        ORDER BY sd.created_at ASC
    ", [$siteId], 'i');
    
    foreach ($delegations as $delegation) {
        // Delegated to contractor
        $timeline[] = [
            'event_type' => 'delegated',
            'event_title' => 'Delegated to Contractor',
            'event_description' => 'Delegated to ' . ($delegation['contractor_name'] ?? 'Unknown'),
            'event_date' => $delegation['created_at'],
            'performed_by' => $delegation['delegated_by_name']
        ];
        
        // Contractor accepted
        if (!empty($delegation['accepted_at'])) {
            $timeline[] = [
                'event_type' => 'accepted',
                'event_title' => 'Contractor Accepted',
                'event_description' => ($delegation['contractor_name'] ?? 'Contractor') . ' accepted the delegation',
                'event_date' => $delegation['accepted_at'],
                'performed_by' => $delegation['contractor_name']
            ];
        }
    }
    
    // 3. Get engineer assignments
    $assignments = $db->getResults("
        SELECT ea.*, 
               CONCAT(u.first_name, ' ', u.last_name) as engineer_name,
               CONCAT(ab.first_name, ' ', ab.last_name) as assigned_by_name
        FROM engineer_assignments ea
        JOIN site_delegations sd ON ea.delegation_id = sd.id
        LEFT JOIN users u ON ea.engineer_id = u.id
        LEFT JOIN users ab ON ea.assigned_by = ab.id
        WHERE sd.site_id = ?
        ORDER BY ea.created_at ASC
    ", [$siteId], 'i');
    
    foreach ($assignments as $assignment) {
        // Assigned to engineer
        $timeline[] = [
            'event_type' => 'assigned',
            'event_title' => 'Assigned to Engineer',
            'event_description' => 'Assigned to ' . ($assignment['engineer_name'] ?? 'Unknown'),
            'event_date' => $assignment['created_at'],
            'performed_by' => $assignment['assigned_by_name']
        ];
        
        // Get ETA submissions
        $etas = $db->getResults("
            SELECT fe.*, CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name
            FROM feasibility_eta fe
            LEFT JOIN users u ON fe.submitted_by = u.id
            WHERE fe.assignment_id = ?
            ORDER BY fe.created_at ASC
        ", [$assignment['id']], 'i');
        
        foreach ($etas as $eta) {
            $timeline[] = [
                'event_type' => 'eta_submitted',
                'event_title' => 'ETA Submitted',
                'event_description' => 'Estimated arrival: ' . date('M d, Y h:i A', strtotime($eta['eta_datetime'])),
                'event_date' => $eta['created_at'],
                'performed_by' => $eta['submitted_by_name']
            ];
        }
        
        // Get ADA submissions
        $adas = $db->getResults("
            SELECT fa.*, CONCAT(u.first_name, ' ', u.last_name) as submitted_by_name
            FROM feasibility_ada fa
            LEFT JOIN users u ON fa.submitted_by = u.id
            WHERE fa.assignment_id = ?
            ORDER BY fa.created_at ASC
        ", [$assignment['id']], 'i');
        
        foreach ($adas as $ada) {
            $timeline[] = [
                'event_type' => 'ada_submitted',
                'event_title' => 'ADA Submitted',
                'event_description' => 'Actual arrival confirmed at location',
                'event_date' => $ada['created_at'],
                'performed_by' => $ada['submitted_by_name']
            ];
        }
        
        // Get feasibility check
        $feasibility = $db->getResults("
            SELECT fc.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
            FROM feasibility_checks fc
            LEFT JOIN users u ON fc.created_by = u.id
            WHERE fc.assignment_id = ?
            ORDER BY fc.created_at ASC
        ", [$assignment['id']], 'i');
        
        foreach ($feasibility as $fc) {
            $timeline[] = [
                'event_type' => 'feasibility_completed',
                'event_title' => 'Feasibility Completed',
                'event_description' => 'Feasibility check submitted for review',
                'event_date' => $fc['created_at'],
                'performed_by' => $fc['created_by_name']
            ];
            
            // Get review history
            $reviews = $db->getResults("
                SELECT fr.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
                FROM feasibility_reviews fr
                LEFT JOIN users u ON fr.reviewer_id = u.id
                WHERE fr.feasibility_id = ?
                ORDER BY fr.reviewed_at ASC
            ", [$fc['id']], 'i');
            
            foreach ($reviews as $review) {
                $eventType = $review['review_type'] === 'approval' 
                    ? ($review['reviewer_role'] === 'adv' ? 'adv_approved' : 'contractor_approved')
                    : ($review['reviewer_role'] === 'adv' ? 'adv_rejected' : 'contractor_rejected');
                
                $eventTitle = $review['review_type'] === 'approval'
                    ? ucfirst($review['reviewer_role'] ?? 'Reviewer') . ' Approved'
                    : ucfirst($review['reviewer_role'] ?? 'Reviewer') . ' Rejected';
                
                $timeline[] = [
                    'event_type' => $eventType,
                    'event_title' => $eventTitle,
                    'event_description' => $review['review_type'] === 'rejection' ? ($review['reason'] ?? '') : ($review['comments'] ?? ''),
                    'event_date' => $review['reviewed_at'],
                    'performed_by' => $review['reviewer_name']
                ];
            }
            
            // Check for resubmissions
            if (!empty($fc['resubmitted_at'])) {
                $timeline[] = [
                    'event_type' => 'resubmitted',
                    'event_title' => 'Feasibility Resubmitted',
                    'event_description' => 'Corrections made and resubmitted for review',
                    'event_date' => $fc['resubmitted_at'],
                    'performed_by' => $fc['created_by_name']
                ];
            }
        }
    }
    
    // Sort timeline by date
    usort($timeline, function($a, $b) {
        return strtotime($a['event_date']) - strtotime($b['event_date']);
    });
    
    echo json_encode([
        'success' => true,
        'data' => [
            'site_id' => $siteId,
            'site_name' => $siteInfo['site_name'] ?? '',
            'lho' => $siteInfo['lho'] ?? '',
            'city' => $siteInfo['city'] ?? '',
            'timeline' => $timeline
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]
    ]);
}
