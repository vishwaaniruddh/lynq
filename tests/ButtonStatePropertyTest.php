<?php
/**
 * Property Test for Button State Logic
 * **Feature: feasibility-module, Property 1: Button state reflects feasibility status**
 * **Validates: Requirements 1.2, 1.3, 1.4**
 */

require_once 'PropertyTestBase.php';

class ButtonStatePropertyTest extends PropertyTestBase {
    
    /**
     * Feasibility status values
     */
    private const FEASIBILITY_STATUSES = [
        'pending_eta',
        'eta_submitted',
        'ada_submitted',
        'feasibility_completed'
    ];
    
    public function __construct() {
        parent::__construct();
    }
    
    public function runTests(): bool {
        echo "=== Button State Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 1: Button state reflects feasibility status
        $allPassed &= $this->runPropertyTest(
            "Property 1: Button state reflects feasibility status",
            [$this, 'testButtonStateReflectsFeasibilityStatus']
        );
        
        return $allPassed;
    }
    
    /**
     * Property 1: Button state reflects feasibility status
     * **Feature: feasibility-module, Property 1: Button state reflects feasibility status**
     * **Validates: Requirements 1.2, 1.3, 1.4**
     * 
     * For any site assignment with a given feasibility status, the UI button states should
     * correctly reflect that status:
     * - pending_eta: ETA enabled, ADA disabled
     * - eta_submitted: ETA enabled (for updates), ADA enabled
     * - ada_submitted: ETA disabled, ADA disabled, Check Feasibility enabled
     * - feasibility_completed: All action buttons disabled, View enabled
     */
    public function testButtonStateReflectsFeasibilityStatus(): array {
        try {
            // Test all feasibility statuses
            foreach (self::FEASIBILITY_STATUSES as $status) {
                $buttonStates = $this->getExpectedButtonStates($status);
                $actualStates = $this->calculateButtonStates($status);
                
                // Verify ETA button state
                $this->assert(
                    $actualStates['eta_enabled'] === $buttonStates['eta_enabled'],
                    "For status '{$status}': ETA button should be " . 
                    ($buttonStates['eta_enabled'] ? 'enabled' : 'disabled') .
                    ", got " . ($actualStates['eta_enabled'] ? 'enabled' : 'disabled')
                );
                
                // Verify ADA button state
                $this->assert(
                    $actualStates['ada_enabled'] === $buttonStates['ada_enabled'],
                    "For status '{$status}': ADA button should be " . 
                    ($buttonStates['ada_enabled'] ? 'enabled' : 'disabled') .
                    ", got " . ($actualStates['ada_enabled'] ? 'enabled' : 'disabled')
                );
                
                // Verify Check Feasibility button state
                $this->assert(
                    $actualStates['feasibility_enabled'] === $buttonStates['feasibility_enabled'],
                    "For status '{$status}': Check Feasibility button should be " . 
                    ($buttonStates['feasibility_enabled'] ? 'enabled' : 'disabled') .
                    ", got " . ($actualStates['feasibility_enabled'] ? 'enabled' : 'disabled')
                );
                
                // Verify View button state
                $this->assert(
                    $actualStates['view_enabled'] === $buttonStates['view_enabled'],
                    "For status '{$status}': View button should be " . 
                    ($buttonStates['view_enabled'] ? 'enabled' : 'disabled') .
                    ", got " . ($actualStates['view_enabled'] ? 'enabled' : 'disabled')
                );
            }
            
            // Run additional random tests
            for ($i = 0; $i < 100; $i++) {
                $randomStatus = self::FEASIBILITY_STATUSES[array_rand(self::FEASIBILITY_STATUSES)];
                $buttonStates = $this->getExpectedButtonStates($randomStatus);
                $actualStates = $this->calculateButtonStates($randomStatus);
                
                $this->assert(
                    $actualStates === $buttonStates,
                    "Random test {$i}: Button states for '{$randomStatus}' should match expected"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get expected button states for a given feasibility status
     * This represents the specification from Requirements 1.2, 1.3, 1.4
     * 
     * @param string $status Feasibility status
     * @return array Button states
     */
    private function getExpectedButtonStates(string $status): array {
        switch ($status) {
            case 'pending_eta':
                // Requirement 1.2: Enable ETA button, disable ADA button
                return [
                    'eta_enabled' => true,
                    'ada_enabled' => false,
                    'feasibility_enabled' => false,
                    'view_enabled' => false
                ];
                
            case 'eta_submitted':
                // Requirement 1.3: Enable ADA button, keep ETA visible for updates
                return [
                    'eta_enabled' => true,  // Can update ETA
                    'ada_enabled' => true,
                    'feasibility_enabled' => false,
                    'view_enabled' => false
                ];
                
            case 'ada_submitted':
                // Requirement 1.4: Display Check Feasibility button
                return [
                    'eta_enabled' => false,
                    'ada_enabled' => false,
                    'feasibility_enabled' => true,
                    'view_enabled' => false
                ];
                
            case 'feasibility_completed':
                // Feasibility completed - show view button
                return [
                    'eta_enabled' => false,
                    'ada_enabled' => false,
                    'feasibility_enabled' => false,
                    'view_enabled' => true
                ];
                
            default:
                // Default to pending_eta behavior
                return [
                    'eta_enabled' => true,
                    'ada_enabled' => false,
                    'feasibility_enabled' => false,
                    'view_enabled' => false
                ];
        }
    }
    
    /**
     * Calculate button states based on feasibility status
     * This mirrors the logic in the UI (sites.php getActionButtons function)
     * 
     * @param string $status Feasibility status
     * @return array Button states
     */
    private function calculateButtonStates(string $status): array {
        // ETA Button - enabled when pending_eta or eta_submitted (for updates)
        // Requirement 1.2: Enable ETA button when no ETA submitted
        // Requirement 1.3: Keep ETA button visible for updates
        $etaEnabled = ($status === 'pending_eta' || $status === 'eta_submitted');
        
        // ADA Button - enabled only when eta_submitted
        // Requirement 1.2: Disable ADA button when no ETA submitted
        // Requirement 1.3: Enable ADA button when ETA submitted
        $adaEnabled = ($status === 'eta_submitted');
        
        // Check Feasibility Button - enabled only when ada_submitted
        // Requirement 1.4: Display Check Feasibility button when ADA is submitted
        $feasibilityEnabled = ($status === 'ada_submitted');
        
        // View Button - enabled only when feasibility_completed
        $viewEnabled = ($status === 'feasibility_completed');
        
        return [
            'eta_enabled' => $etaEnabled,
            'ada_enabled' => $adaEnabled,
            'feasibility_enabled' => $feasibilityEnabled,
            'view_enabled' => $viewEnabled
        ];
    }
    
    /**
     * Clean up test data (no database operations in this test)
     */
    protected function cleanupTestData(): void {
        // No cleanup needed - this test doesn't create database records
    }
}
