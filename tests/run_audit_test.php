<?php
require_once 'AuditFieldMaintenanceTest.php';
$test = new AuditFieldMaintenanceTest();
$result = $test->runTests();
$test->cleanupTestData();
