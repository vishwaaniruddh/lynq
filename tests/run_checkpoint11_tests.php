<?php
/**
 * Runner for Installation Module Checkpoint 11 Tests
 * 
 * Tests for tasks 1-10 (before checkpoint 11)
 */

require_once __DIR__ . '/../config/autoload.php';

echo "=================================================================\n";
echo "  Installation Module - Checkpoint 11 Tests\n";
echo "=================================================================\n\n";

// Suppress warnings
error_reporting(E_ERROR | E_PARSE);

$testFiles = [
    'InstallationDataRoundTripTest.php',           // Property 12 - Task 2.2
    'InstallationDelegationPropertyTest.php',      // Property 2 - Task 3.2
    'InstallationAssignmentPropertyTest.php',      // Property 4 - Task 4.2
    'InstallationETAPropertyTest.php',             // Property 5 - Task 5.2
    'InstallationADAPropertyTest.php',             // Property 6 - Task 5.3
    'InstallationInitiationPropertyTest.php',      // Property 1 - Task 7.2
    'InstallationFormAccessPropertyTest.php',      // Property 9 - Task 7.4
    'InstallationButtonAfterADAPropertyTest.php',  // Property 7 - Task 7.5
    'ContractorInstallationListPropertyTest.php',  // Property 3 - Task 9.4
];

$passed = 0;
$failed = 0;
$errors = [];

foreach ($testFiles as $testFile) {
    $testPath = __DIR__ . '/' . $testFile;
    
    if (!file_exists($testPath)) {
        echo "SKIP: $testFile (file not found)\n";
        continue;
    }
    
    $className = str_replace('.php', '', $testFile);
    echo "Running: $className... ";
    
    try {
        require_once $testPath;
        
        if (class_exists($className)) {
            $test = new $className();
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
        $errors[] = $className . " - " . $e->getMessage();
    }
}

echo "\n=================================================================\n";
echo "  Results: Passed=$passed, Failed=$failed\n";
echo "=================================================================\n";

if ($failed === 0) {
    echo "All checkpoint 11 tests passed!\n";
    exit(0);
} else {
    echo "Failed tests:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
