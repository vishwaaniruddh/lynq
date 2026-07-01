<?php
/**
 * Runner script for IP_Master Bulk Upload Property Test
 * 
 * **Feature: ip-configuration-management, Property 21: Bulk Upload Validation**
 * **Validates: Requirements 10.1**
 * 
 * Usage: php run_ip_master_bulk_upload_test.php
 */

require_once __DIR__ . '/IPMasterBulkUploadTest.php';

echo "===========================================\n";
echo "IP_Master Bulk Upload Validation Test\n";
echo "Property 21: Bulk Upload Validation\n";
echo "Validates: Requirements 10.1\n";
echo "===========================================\n";

$test = new IPMasterBulkUploadTest();
$results = $test->runAllTests();

echo "\n===========================================\n";
if (in_array(false, $results, true)) {
    echo "RESULT: FAILED\n";
    exit(1);
} else {
    echo "RESULT: PASSED\n";
    exit(0);
}
