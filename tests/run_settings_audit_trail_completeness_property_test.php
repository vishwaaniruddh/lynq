<?php
/**
 * Runner for Settings Audit Trail Completeness Property Test
 */

require_once 'SettingsAuditTrailCompletenessPropertyTest.php';

try {
    $test = new SettingsAuditTrailCompletenessPropertyTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "Error running test: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}