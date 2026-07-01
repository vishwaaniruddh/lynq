<?php
/**
 * Runner for All Installation Module Property Tests
 * 
 * This script runs all property tests for the installation module
 * to verify the final checkpoint.
 */

echo "=================================================================\n";
echo "  Installation Module - Final Checkpoint Test Suite\n";
echo "=================================================================\n\n";

$testFiles = [
    // Property 1: Button visibility
    'InstallationButtonVisibilityPropertyTest.php',
    // Property 2: Installation delegation
    'InstallationInitiationPropertyTest.php',
    // Property 3: Contractor installation list
    'ContractorInstallationListPropertyTest.php',
    // Property 4: Engineer assignment
    'InstallationAssignmentPropertyTest.php',
    // Property 5: ETA submission
    'InstallationETAPropertyTest.php',
    // Property 6: ADA submission
    'InstallationADAPropertyTest.php',
    // Property 7: Installation button after ADA
    'InstallationButtonAfterADAPropertyTest.php',
    // Property 8: Material receipt confirmation
    'MaterialReceiptConfirmationPropertyTest.php',
    // Property 9: Form access control
    'InstallationFormAccessPropertyTest.php',
    // Property 10: Site info pre-population
    'InstallationSiteInfoPropertyTest.php',
    // Property 11: Required field validation
    'InstallationValidationPropertyTest.php',
    // Property 12: Installation data round-trip
    'InstallationDataRoundTripTest.php',
    // Property 13: Installation submission status
    'InstallationSubmissionPropertyTest.php',
    // Property 14: Section data round-trip
    'InstallationSectionDataRoundTripTest.php',
    // Property 15: Image upload validation
    'InstallationImageUploadPropertyTest.php',
    // Property 16: Verification data round-trip
    'InstallationVerificationDataRoundTripTest.php',
    // Property 17: Contractor review panel visibility
    'ContractorReviewPanelVisibilityPropertyTest.php',
    // Property 18: Section approval
    'SectionApprovalPropertyTest.php',
    // Property 19: Section rejection validation
    'SectionRejectionValidationPropertyTest.php',
    // Property 20: Section rejection status transition
    'SectionRejectionStatusTransitionPropertyTest.php',
    // Property 21: All sections approved status
    'AllSectionsApprovedStatusPropertyTest.php',
    // Property 22: ADV review panel visibility
    'AdvReviewPanelVisibilityPropertyTest.php',
    // Property 23: ADV approval status
    'AdvApprovalStatusPropertyTest.php',
    // Property 24: ADV rejection status
    'AdvRejectionStatusPropertyTest.php',
    // Property 25: ADV-approved immutability
    'AdvApprovedImmutabilityPropertyTest.php',
    // Property 26: Rejection display
    'RejectionDisplayPropertyTest.php',
    // Property 27: Editable sections restriction
    'EditableSectionsRestrictionPropertyTest.php',
    // Property 28: Section resubmission
    'SectionResubmissionPropertyTest.php',
    // Property 29: Installation resubmission
    'InstallationResubmissionPropertyTest.php',
    // Property 30: Image thumbnail display
    'ImageThumbnailDisplayPropertyTest.php',
    // Property 31: Multiple images grid
    'MultipleImagesGridPropertyTest.php',
    // Property 32: Tracking view data
    'InstallationTrackingViewPropertyTest.php',
    // Property 33: Tracking filter
    'InstallationTrackingFilterPropertyTest.php',
    // Property 34: Export round-trip
    'InstallationExportRoundTripTest.php',
];

$passed = 0;
$failed = 0;
$errors = [];

foreach ($testFiles as $testFile) {
    $testPath = __DIR__ . '/' . $testFile;
    
    if (!file_exists($testPath)) {
        echo "⚠ SKIP: $testFile (file not found)\n";
        continue;
    }
    
    // Get class name from file name
    $className = str_replace('.php', '', $testFile);
    
    echo "Running: $className... ";
    
    try {
        require_once $testPath;
        
        if (class_exists($className)) {
            $test = new $className();
            $result = $test->runTests();
            
            if ($result) {
                echo "✓ PASSED\n";
                $passed++;
            } else {
                echo "✗ FAILED\n";
                $failed++;
                $errors[] = $className;
            }
        } else {
            echo "⚠ SKIP (class not found)\n";
        }
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = $className . " (Exception: " . $e->getMessage() . ")";
    }
}

echo "\n=================================================================\n";
echo "  Test Results Summary\n";
echo "=================================================================\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if (!empty($errors)) {
    echo "\nFailed Tests:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n";

if ($failed === 0) {
    echo "✓ All installation module tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the errors above.\n";
    exit(1);
}
