<?php
/**
 * Runner for Settings Batch Validation Atomicity Property Test
 */

require_once 'SettingsBatchValidationAtomicityPropertyTest.php';

try {
    $test = new SettingsBatchValidationAtomicityPropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "Error running test: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}