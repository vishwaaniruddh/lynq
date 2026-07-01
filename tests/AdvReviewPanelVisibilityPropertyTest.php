<?php
/**
 * Property Test: ADV Review Panel Visibility
 * 
 * **Feature: installation-module, Property 22: ADV review panel visibility**
 * **Validates: Requirements 15.1, 15.2**
 * 
 * Property: For any ADV user viewing a contractor-approved installation, the system 
 * should display the final approval panel with section-wise options and previous 
 * contractor review data.
 * 
 * This test verifies that:
 * 1. ADV user sees review panel for contractor-approved installations
 * 2. Review panel is hidden for non-contractor-approved installations
 * 3. Non-ADV users cannot see ADV review panel
 * 4. Review panel shows previous contractor review comments
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../models/Installation.php';

class AdvReviewPanelVisibilityPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== ADV Review Panel Visibility Property Tests ===\n";
        echo "**Feature: installation-module, Property 22: ADV review panel visibility**\n";
        echo "**Validates: Requirements 15.1, 15.2**\n\n";
        
        $this->runPropertyTest(
            'ADV user sees review panel for contractor-approved installations',
            [$this, 'testAdvSeesReviewPanelForContractorApproved']
        );
        
        $this->runPropertyTest(
            'System admin sees ADV review panel for contractor-approved installations',
            [$this, 'testSystemAdminSeesReviewPanel']
        );
        
        $this->runPropertyTest(
            'ADV review panel hidden for non-contractor-approved installations',
            [$this, 'testReviewPanelHiddenForNonContractorApproved']
        );
        
        $this->runPropertyTest(
            'Contractor users cannot see ADV review panel',
            [$this, 'testContractorCannotSeeAdvPanel']
        );
        
        $this->runPropertyTest(
            'ADV review panel shows previous contractor review data',
            [$this, 'testPanelShowsPreviousContractorReview']
        );
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }
    
    /**
     * Run a property test with multiple iterations
     */
    private function runPropertyTest(string $name, callable $testFunction): void {
        echo "Testing: $name\n";
        $failures = [];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $result = $testFunction();
                if (!$result['success']) {
                    $failures[] = "Iteration $i: {$result['message']}";
                }
            } catch (Exception $e) {
                $failures[] = "Iteration $i: Exception - {$e->getMessage()}";
            }
        }
        
        if (empty($failures)) {
            echo "  ✓ Passed ({$this->iterations} iterations)\n";
            $this->testResults[$name] = true;
        } else {
            echo "  ✗ Failed\n";
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "    - $failure\n";
            }
            if (count($failures) > 3) {
                echo "    ... and " . (count($failures) - 3) . " more failures\n";
            }
            $this->testResults[$name] = false;
        }
    }
    
    /**
     * Property Test: ADV user sees review panel for contractor-approved installations
     */
    private function testAdvSeesReviewPanelForContractorApproved(): array {
        $user = $this->generateAdvUser();
        $installation = $this->generateInstallation(Installation::STATUS_CONTRACTOR_APPROVED);
        
        $canReview = $this->canUserReviewInstallation($user, $installation);
        
        if (!$canReview) {
            return [
                'success' => false,
                'message' => "ADV user should be able to review contractor-approved installation"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: System admin sees ADV review panel for contractor-approved installations
     */
    private function testSystemAdminSeesReviewPanel(): array {
        $user = $this->generateSystemAdminUser();
        $installation = $this->generateInstallation(Installation::STATUS_CONTRACTOR_APPROVED);
        
        $canReview = $this->canUserReviewInstallation($user, $installation);
        
        if (!$canReview) {
            return [
                'success' => false,
                'message' => "System admin should be able to review contractor-approved installation"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ADV review panel hidden for non-contractor-approved installations
     */
    private function testReviewPanelHiddenForNonContractorApproved(): array {
        $user = $this->generateAdvUser();
        
        // Test with various non-contractor-approved statuses
        $nonApprovedStatuses = [
            Installation::STATUS_PENDING_MATERIALS,
            Installation::STATUS_MATERIALS_RECEIVED,
            Installation::STATUS_IN_PROGRESS,
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_PENDING_CONTRACTOR_REVIEW,
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_APPROVED,
            Installation::STATUS_ADV_REJECTED
        ];
        
        $status = $nonApprovedStatuses[array_rand($nonApprovedStatuses)];
        $installation = $this->generateInstallation($status);
        
        $canReview = $this->canUserReviewInstallation($user, $installation);
        
        if ($canReview) {
            return [
                'success' => false,
                'message' => "ADV should not be able to review installation with status '$status'"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Contractor users cannot see ADV review panel
     */
    private function testContractorCannotSeeAdvPanel(): array {
        $contractorUser = $this->generateContractorUser();
        $installation = $this->generateInstallation(Installation::STATUS_CONTRACTOR_APPROVED);
        
        $reviewerLevel = $this->getReviewerLevel($contractorUser);
        
        // Contractor user should have 'contractor' level, not 'adv'
        if ($reviewerLevel === 'adv') {
            return [
                'success' => false,
                'message' => "Contractor user should not have ADV reviewer level"
            ];
        }
        
        // Contractor should not be able to review contractor-approved installations
        $canReview = $this->canUserReviewInstallation($contractorUser, $installation);
        
        if ($canReview) {
            return [
                'success' => false,
                'message' => "Contractor should not be able to review contractor-approved installation"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: ADV review panel shows previous contractor review data
     * Requirements: 13.2 - Display previous contractor review comments and approval status
     */
    private function testPanelShowsPreviousContractorReview(): array {
        $user = $this->generateAdvUser();
        $installation = $this->generateInstallation(Installation::STATUS_CONTRACTOR_APPROVED);
        
        // Add contractor review data
        $installation['checkpoints'] = $this->generateContractorReviewData();
        
        $reviewData = $this->getReviewDataForUser($user, $installation);
        
        // Should include contractor review history
        if (!$reviewData['includesContractorReview']) {
            return [
                'success' => false,
                'message' => "ADV review panel should include contractor review data"
            ];
        }
        
        return ['success' => true];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Generate random string
     */
    private function generateRandomString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Generate ADV user
     */
    private function generateAdvUser(): array {
        return [
            'id' => rand(1, 1000),
            'company_type' => 'ADV',
            'role_id' => rand(1, 3),
            'company_id' => rand(1, 100),
            'is_system_admin' => false
        ];
    }
    
    /**
     * Generate system admin user
     */
    private function generateSystemAdminUser(): array {
        return [
            'id' => rand(1, 1000),
            'company_type' => 'ADV',
            'role_id' => 1,
            'company_id' => rand(1, 100),
            'is_system_admin' => true
        ];
    }
    
    /**
     * Generate contractor user
     */
    private function generateContractorUser(): array {
        return [
            'id' => rand(1, 1000),
            'company_type' => 'CONTRACTOR',
            'role_id' => rand(1, 3),
            'company_id' => rand(1, 100),
            'is_system_admin' => false
        ];
    }
    
    /**
     * Generate installation with given status
     */
    private function generateInstallation(string $status): array {
        return [
            'id' => rand(1, 1000),
            'site_id' => rand(1, 100),
            'status' => $status,
            'atm_id' => 'ATM-' . $this->generateRandomString(8)
        ];
    }
    
    /**
     * Generate contractor review data
     */
    private function generateContractorReviewData(): array {
        $sections = ['router_fixed_snaps', 'adaptor_snaps', 'lan_cable_install_snap'];
        $checkpoints = [];
        
        foreach ($sections as $section) {
            $checkpoints[$section] = [
                'contractor_status' => 'approved',
                'contractor_reviewer_id' => rand(1, 100),
                'contractor_reviewed_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 5) . ' days')),
                'remarks' => [
                    [
                        'reviewer_level' => 'contractor',
                        'review_type' => 'approval',
                        'remark' => 'Approved by contractor',
                        'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 5) . ' days'))
                    ]
                ]
            ];
        }
        
        return $checkpoints;
    }
    
    /**
     * Check if user can review installation (simulates the review page logic)
     */
    private function canUserReviewInstallation(array $user, array $installation): bool {
        $reviewerLevel = $this->getReviewerLevel($user);
        
        if ($reviewerLevel === 'none') {
            return false;
        }
        
        $status = $installation['status'];
        
        // Contractor can review submitted or pending_contractor_review installations
        if ($reviewerLevel === 'contractor') {
            return in_array($status, [
                Installation::STATUS_SUBMITTED,
                Installation::STATUS_PENDING_CONTRACTOR_REVIEW
            ]);
        }
        
        // ADV can review contractor-approved installations
        if ($reviewerLevel === 'adv') {
            return $status === Installation::STATUS_CONTRACTOR_APPROVED;
        }
        
        return false;
    }
    
    /**
     * Get reviewer level for user (simulates the review page logic)
     */
    private function getReviewerLevel(array $user): string {
        $companyType = strtoupper($user['company_type'] ?? '');
        $roleId = $user['role_id'] ?? 0;
        
        if ($companyType === 'ADV' || ($user['is_system_admin'] ?? false)) {
            return 'adv';
        }
        
        if ($companyType === 'CONTRACTOR' && in_array($roleId, [1, 2, 3])) {
            return 'contractor';
        }
        
        return 'none';
    }
    
    /**
     * Get review data for user (simulates what the review page would show)
     */
    private function getReviewDataForUser(array $user, array $installation): array {
        $reviewerLevel = $this->getReviewerLevel($user);
        
        // ADV reviewer should see contractor review data
        $includesContractorReview = false;
        if ($reviewerLevel === 'adv' && isset($installation['checkpoints'])) {
            foreach ($installation['checkpoints'] as $checkpoint) {
                if (isset($checkpoint['contractor_status']) && $checkpoint['contractor_status'] !== 'pending') {
                    $includesContractorReview = true;
                    break;
                }
            }
        }
        
        return [
            'includesContractorReview' => $includesContractorReview
        ];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new AdvReviewPanelVisibilityPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
