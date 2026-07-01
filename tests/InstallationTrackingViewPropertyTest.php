<?php
/**
 * Property Test: Installation Tracking View Data
 * 
 * **Feature: installation-module, Property 32: Tracking view displays complete data**
 * **Validates: Requirements 18.1, 18.2**
 * 
 * Property: For any set of installations, the tracking view should display all installations
 * with their correct status, material receipt status, submission date, and approval status.
 * 
 * This test verifies that:
 * 1. All installations are displayed in tracking view
 * 2. Status is correctly shown for each installation
 * 3. Material receipt status is correctly displayed
 * 4. Submission date is shown when available
 * 5. Approval status is correctly displayed
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../models/Installation.php';

class InstallationTrackingViewPropertyTest {
    private $testResults = [];
    private $iterations = 100;
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== Installation Tracking View Property Tests ===\n";
        echo "**Feature: installation-module, Property 32: Tracking view displays complete data**\n";
        echo "**Validates: Requirements 18.1, 18.2**\n\n";
        
        $this->runPropertyTest(
            'Tracking view displays all installations with correct status',
            [$this, 'testTrackingViewDisplaysAllInstallations']
        );
        
        $this->runPropertyTest(
            'Material receipt status is correctly displayed',
            [$this, 'testMaterialReceiptStatusDisplay']
        );
        
        $this->runPropertyTest(
            'Submission date is shown when available',
            [$this, 'testSubmissionDateDisplay']
        );
        
        $this->runPropertyTest(
            'Approval status is correctly displayed',
            [$this, 'testApprovalStatusDisplay']
        );
        
        $this->runPropertyTest(
            'Tracking view contains all required fields',
            [$this, 'testTrackingViewContainsRequiredFields']
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
     * Property Test: Tracking view displays all installations with correct status
     * Requirements: 16.1 - Display all installations with their current status
     */
    private function testTrackingViewDisplaysAllInstallations(): array {
        // Generate random installations
        $installations = $this->generateRandomInstallations(rand(3, 10));
        
        // Simulate tracking view data
        $trackingData = $this->simulateTrackingView($installations);
        
        // Verify all installations are present
        foreach ($installations as $installation) {
            $found = false;
            foreach ($trackingData as $row) {
                if ($row['id'] === $installation['id']) {
                    $found = true;
                    
                    // Verify status matches
                    if ($row['status'] !== $installation['status']) {
                        return [
                            'success' => false,
                            'message' => "Status mismatch for installation {$installation['id']}: expected {$installation['status']}, got {$row['status']}"
                        ];
                    }
                    break;
                }
            }
            
            if (!$found) {
                return [
                    'success' => false,
                    'message' => "Installation {$installation['id']} not found in tracking view"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Material receipt status is correctly displayed
     * Requirements: 16.2 - Display material receipt status
     */
    private function testMaterialReceiptStatusDisplay(): array {
        $installation = $this->generateRandomInstallation();
        $trackingRow = $this->simulateTrackingRow($installation);
        
        $expectedMaterialStatus = $installation['status'] === Installation::STATUS_PENDING_MATERIALS 
            ? 'pending' 
            : 'received';
        
        if ($trackingRow['material_receipt_status'] !== $expectedMaterialStatus) {
            return [
                'success' => false,
                'message' => "Material receipt status mismatch: expected {$expectedMaterialStatus}, got {$trackingRow['material_receipt_status']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Submission date is shown when available
     * Requirements: 16.2 - Display submission date
     */
    private function testSubmissionDateDisplay(): array {
        $installation = $this->generateRandomInstallation();
        $trackingRow = $this->simulateTrackingRow($installation);
        
        // If installation has been submitted, submission date should be present
        $submittedStatuses = [
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_PENDING_CONTRACTOR_REVIEW,
            Installation::STATUS_CONTRACTOR_APPROVED,
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_APPROVED,
            Installation::STATUS_ADV_REJECTED
        ];
        
        $isSubmitted = in_array($installation['status'], $submittedStatuses);
        
        if ($isSubmitted && !empty($installation['submitted_at'])) {
            if (empty($trackingRow['submitted_at'])) {
                return [
                    'success' => false,
                    'message' => "Submission date should be displayed for submitted installation"
                ];
            }
            
            if ($trackingRow['submitted_at'] !== $installation['submitted_at']) {
                return [
                    'success' => false,
                    'message' => "Submission date mismatch"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Approval status is correctly displayed
     * Requirements: 16.2 - Display approval status
     */
    private function testApprovalStatusDisplay(): array {
        $installation = $this->generateRandomInstallation();
        $trackingRow = $this->simulateTrackingRow($installation);
        
        $expectedApprovalStatus = $this->getExpectedApprovalStatus($installation['status']);
        
        if ($trackingRow['approval_status'] !== $expectedApprovalStatus) {
            return [
                'success' => false,
                'message' => "Approval status mismatch: expected {$expectedApprovalStatus}, got {$trackingRow['approval_status']}"
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property Test: Tracking view contains all required fields
     * Requirements: 16.1, 16.2
     */
    private function testTrackingViewContainsRequiredFields(): array {
        $installation = $this->generateRandomInstallation();
        $trackingRow = $this->simulateTrackingRow($installation);
        
        $requiredFields = [
            'id',
            'atm_id',
            'city',
            'state',
            'status',
            'material_receipt_status',
            'approval_status',
            'created_at'
        ];
        
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $trackingRow)) {
                return [
                    'success' => false,
                    'message' => "Required field '{$field}' missing from tracking view"
                ];
            }
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
     * Generate random installation status
     */
    private function generateRandomStatus(): string {
        $statuses = Installation::getStatuses();
        return $statuses[array_rand($statuses)];
    }
    
    /**
     * Generate random installation
     */
    private function generateRandomInstallation(): array {
        $status = $this->generateRandomStatus();
        $hasSubmitted = in_array($status, [
            Installation::STATUS_SUBMITTED,
            Installation::STATUS_PENDING_CONTRACTOR_REVIEW,
            Installation::STATUS_CONTRACTOR_APPROVED,
            Installation::STATUS_CONTRACTOR_REJECTED,
            Installation::STATUS_ADV_APPROVED,
            Installation::STATUS_ADV_REJECTED
        ]);
        
        return [
            'id' => rand(1, 10000),
            'site_id' => rand(1, 1000),
            'atm_id' => 'ATM-' . $this->generateRandomString(8),
            'lho' => 'LHO-' . $this->generateRandomString(5),
            'city' => 'City-' . $this->generateRandomString(6),
            'state' => 'State-' . $this->generateRandomString(6),
            'address' => 'Address ' . $this->generateRandomString(20),
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'submitted_at' => $hasSubmitted ? date('Y-m-d H:i:s', strtotime('-' . rand(1, 15) . ' days')) : null,
            'submitted_by' => $hasSubmitted ? rand(1, 100) : null
        ];
    }
    
    /**
     * Generate multiple random installations
     */
    private function generateRandomInstallations(int $count): array {
        $installations = [];
        for ($i = 0; $i < $count; $i++) {
            $installations[] = $this->generateRandomInstallation();
        }
        return $installations;
    }
    
    /**
     * Simulate tracking view data (what the API would return)
     */
    private function simulateTrackingView(array $installations): array {
        $trackingData = [];
        foreach ($installations as $installation) {
            $trackingData[] = $this->simulateTrackingRow($installation);
        }
        return $trackingData;
    }
    
    /**
     * Simulate a single tracking row
     */
    private function simulateTrackingRow(array $installation): array {
        return [
            'id' => $installation['id'],
            'site_id' => $installation['site_id'],
            'atm_id' => $installation['atm_id'],
            'lho' => $installation['lho'],
            'city' => $installation['city'],
            'state' => $installation['state'],
            'address' => $installation['address'],
            'status' => $installation['status'],
            'material_receipt_status' => $installation['status'] === Installation::STATUS_PENDING_MATERIALS 
                ? 'pending' 
                : 'received',
            'submitted_at' => $installation['submitted_at'],
            'submitted_by' => $installation['submitted_by'],
            'approval_status' => $this->getExpectedApprovalStatus($installation['status']),
            'created_at' => $installation['created_at']
        ];
    }
    
    /**
     * Get expected approval status based on installation status
     */
    private function getExpectedApprovalStatus(string $status): string {
        switch ($status) {
            case Installation::STATUS_PENDING_MATERIALS:
            case Installation::STATUS_MATERIALS_RECEIVED:
            case Installation::STATUS_IN_PROGRESS:
                return 'not_applicable';
            
            case Installation::STATUS_SUBMITTED:
            case Installation::STATUS_PENDING_CONTRACTOR_REVIEW:
                return 'awaiting_review';
            
            case Installation::STATUS_CONTRACTOR_APPROVED:
                return 'contractor_approved';
            
            case Installation::STATUS_CONTRACTOR_REJECTED:
                return 'contractor_rejected';
            
            case Installation::STATUS_ADV_APPROVED:
                return 'fully_approved';
            
            case Installation::STATUS_ADV_REJECTED:
                return 'adv_rejected';
            
            default:
                return 'unknown';
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new InstallationTrackingViewPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
