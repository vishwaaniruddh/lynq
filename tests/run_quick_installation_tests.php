<?php
/**
 * Quick Runner for Installation Module Tests (20 iterations)
 */

require_once __DIR__ . '/../config/autoload.php';

echo "=================================================================\n";
echo "  Installation Module - Quick Test (20 iterations)\n";
echo "=================================================================\n\n";

// Suppress audit log warnings
error_reporting(E_ERROR | E_PARSE);

$testFiles = [
    'InstallationButtonVisibilityPropertyTest.php',
    'InstallationInitiationPropertyTest.php',
    'MaterialReceiptConfirmationPropertyTest.php',
    'InstallationFormAccessPropertyTest.php',
    'InstallationSiteInfoPropertyTest.php',
    'InstallationValidationPropertyTest.php',
    'InstallationDataRoundTripTest.php',
    'InstallationSubmissionPropertyTest.php',
    'InstallationSectionDataRoundTripTest.php',
    'InstallationImageUploadPropertyTest.php',
    'InstallationVerificationDataRoundTripTest.php',
    'ContractorReviewPanelVisibilityPropertyTest.php',
    'SectionApprovalPropertyTest.php',
    'SectionRejectionValidationPropertyTest.php',
    'SectionRejectionStatusTransitionPropertyTest.php',
    'AllSectionsApprovedStatusPropertyTest.php',
    'AdvReviewPanelVisibilityPropertyTest.php',
    'AdvApprovalStatusPropertyTest.php',
    'AdvRejectionStatusPropertyTest.php',
    'AdvApprovedImmutabilityPropertyTest.php',
    'RejectionDisplayPropertyTest.php',
    'EditableSectionsRestrictionPropertyTest.php',
    'SectionResubmissionPropertyTest.php',
    'InstallationResubmissionPropertyTest.php',
    'ImageThumbnailDisplayPropertyTest.php',
    'MultipleImagesGridPropertyTest.php',
    'InstallationTrackingViewPropertyTest.php',
    'InstallationTrackingFilterPropertyTest.php',
    'InstallationExportRoundTripTest.php',
];

$passed = 0;
$failed = 0;
$errors = [];

foreach ($testFiles as $testFile) {
    $testPath = __DIR__ . '/' . $testFile;
    
    if (!file_exists($testPath)) {
        echo "SKIP: $testFile (not found)\n";
        continue;
    }
    
    $className = str_replace('.php', '', $testFile);
    echo "Running: $className... ";
    
    try {
        require_once $testPath;
        
        if (class_exists($className)) {
            $test = new $className();
            // Use reflection to set iterations to 20
            $reflection = new ReflectionClass($test);
            $property = $reflection->getProperty('iterations');
            $property->setAccessible(true);
            $property->setValue($test, 20);
            
            $result = $test->runTests();
            
            if ($result) {
                echo "PASSED\n";
                $passed++;
            } else {
                echo "FAILED\n";
                $failed++;
                $errors[] = $className;
            }
        } else {
            echo "SKIP (class not found)\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = $className;
    }
}

echo "\n=================================================================\n";
echo "  Results: Passed=$passed, Failed=$failed\n";
echo "=================================================================\n";

if ($failed === 0) {
    echo "All installation module tests passed!\n";
    exit(0);
} else {
    echo "Failed tests: " . implode(', ', $errors) . "\n";
    exit(1);
}
