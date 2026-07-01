<?php
/**
 * Runner script for Rejection Feedback Property Tests
 * 
 * Tests Properties 26, 27, and 29 for the Engineer Rejection Feedback UI
 * 
 * Usage: php run_rejection_feedback_property_test.php
 */

require_once __DIR__ . '/RejectionFeedbackPropertyTest.php';

echo "===========================================\n";
echo "Rejection Feedback Property Tests\n";
echo "===========================================\n\n";

$test = new RejectionFeedbackPropertyTest();
$passed = $test->runTests();

echo "\n===========================================\n";
if ($passed) {
    echo "All property tests PASSED!\n";
} else {
    echo "Some property tests FAILED!\n";
}
echo "===========================================\n";

exit($passed ? 0 : 1);
