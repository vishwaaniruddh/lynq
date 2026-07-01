<?php
/**
 * Runner for Image Thumbnail Display Property Test
 * **Feature: feasibility-module, Property 16: Image thumbnail display**
 * **Validates: Requirements 9.1, 9.4**
 */

require_once __DIR__ . '/ImageThumbnailDisplayPropertyTest.php';

echo "========================================\n";
echo "Image Thumbnail Display Property Test\n";
echo "========================================\n\n";

$test = new ImageThumbnailDisplayPropertyTest();
$result = $test->runTests();

echo "\n========================================\n";
if ($result) {
    echo "All tests PASSED!\n";
    exit(0);
} else {
    echo "Some tests FAILED!\n";
    exit(1);
}
