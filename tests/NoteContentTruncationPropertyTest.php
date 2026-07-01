<?php
/**
 * Property Test: Content Preview Truncation
 * **Feature: notes-module, Property 6: Content Preview Truncation**
 * **Validates: Requirements 5.2**
 * 
 * Property: *For any* note content, the preview displayed in the list SHALL be 
 * at most 50 characters, with ellipsis appended if truncated.
 */

require_once __DIR__ . '/PropertyTestBase.php';

class NoteContentTruncationPropertyTest extends PropertyTestBase {
    
    /**
     * Truncate content to preview length (50 chars max with ellipsis)
     * This is the function that will be used in the frontend JavaScript
     * We test the logic here to ensure correctness
     */
    private function truncateContent(string $content, int $maxLength = 50): string {
        $content = trim($content);
        
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        return substr($content, 0, $maxLength) . '...';
    }
    
    /**
     * Generate random content of specified length
     */
    private function generateContentOfLength(int $length): string {
        if ($length <= 0) {
            return '';
        }
        return $this->generateRandomString($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ');
    }
    
    /**
     * Property Test: Truncated content is at most 50 chars + ellipsis
     */
    public function testTruncatedContentMaxLength(): bool {
        return $this->runPropertyTest(
            'Content Preview Truncation - Max length constraint',
            function() {
                // Generate random content length (0 to 500 chars)
                $contentLength = $this->generateRandomInt(0, 500);
                $content = $this->generateContentOfLength($contentLength);
                
                // Truncate content
                $preview = $this->truncateContent($content);
                
                // Calculate expected max length
                // If content > 50, preview should be 50 + 3 (ellipsis) = 53
                // If content <= 50, preview should be same as content
                $maxAllowedLength = 53; // 50 chars + "..."
                
                if (strlen($preview) > $maxAllowedLength) {
                    return [
                        'success' => false,
                        'message' => "Preview exceeds maximum allowed length",
                        'data' => [
                            'original_length' => strlen($content),
                            'preview_length' => strlen($preview),
                            'max_allowed' => $maxAllowedLength,
                            'preview' => $preview
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Short content is not truncated
     */
    public function testShortContentNotTruncated(): bool {
        return $this->runPropertyTest(
            'Content Preview Truncation - Short content preserved',
            function() {
                // Generate content that is 50 chars or less
                $contentLength = $this->generateRandomInt(0, 50);
                $content = $this->generateContentOfLength($contentLength);
                
                // Truncate content
                $preview = $this->truncateContent($content);
                
                // Short content should not have ellipsis
                if (strlen(trim($content)) <= 50 && strpos($preview, '...') !== false) {
                    return [
                        'success' => false,
                        'message' => "Short content should not have ellipsis",
                        'data' => [
                            'original_length' => strlen($content),
                            'preview' => $preview
                        ]
                    ];
                }
                
                // Short content should be preserved exactly (after trim)
                if (trim($content) !== $preview && strlen(trim($content)) <= 50) {
                    return [
                        'success' => false,
                        'message' => "Short content should be preserved exactly",
                        'data' => [
                            'original' => trim($content),
                            'preview' => $preview
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Long content has ellipsis
     */
    public function testLongContentHasEllipsis(): bool {
        return $this->runPropertyTest(
            'Content Preview Truncation - Long content has ellipsis',
            function() {
                // Generate content that is more than 50 chars
                $contentLength = $this->generateRandomInt(51, 500);
                $content = $this->generateContentOfLength($contentLength);
                
                // Truncate content
                $preview = $this->truncateContent($content);
                
                // Long content should end with ellipsis
                if (substr($preview, -3) !== '...') {
                    return [
                        'success' => false,
                        'message' => "Long content preview should end with ellipsis",
                        'data' => [
                            'original_length' => strlen($content),
                            'preview' => $preview,
                            'preview_ending' => substr($preview, -3)
                        ]
                    ];
                }
                
                // Preview without ellipsis should be exactly 50 chars
                $previewWithoutEllipsis = substr($preview, 0, -3);
                if (strlen($previewWithoutEllipsis) !== 50) {
                    return [
                        'success' => false,
                        'message' => "Truncated preview (without ellipsis) should be exactly 50 chars",
                        'data' => [
                            'original_length' => strlen($content),
                            'preview_without_ellipsis_length' => strlen($previewWithoutEllipsis),
                            'expected' => 50
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Truncated content is prefix of original
     */
    public function testTruncatedContentIsPrefix(): bool {
        return $this->runPropertyTest(
            'Content Preview Truncation - Preview is prefix of original',
            function() {
                // Generate random content
                $contentLength = $this->generateRandomInt(1, 500);
                $content = $this->generateContentOfLength($contentLength);
                $trimmedContent = trim($content);
                
                // Truncate content
                $preview = $this->truncateContent($content);
                
                // Get the actual text part (without ellipsis if present)
                $previewText = $preview;
                if (substr($preview, -3) === '...') {
                    $previewText = substr($preview, 0, -3);
                }
                
                // Preview text should be a prefix of the original content
                if (strlen($previewText) > 0 && strpos($trimmedContent, $previewText) !== 0) {
                    return [
                        'success' => false,
                        'message' => "Preview should be a prefix of original content",
                        'data' => [
                            'original_start' => substr($trimmedContent, 0, 60),
                            'preview_text' => $previewText
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Empty content handling
     */
    public function testEmptyContentHandling(): bool {
        return $this->runPropertyTest(
            'Content Preview Truncation - Empty content handling',
            function() {
                // Test various empty-ish content
                $emptyContents = ['', '   ', "\t", "\n", "  \n  "];
                $content = $this->generateRandomChoice($emptyContents);
                
                // Truncate content
                $preview = $this->truncateContent($content);
                
                // Empty content should result in empty preview
                if ($preview !== '') {
                    return [
                        'success' => false,
                        'message' => "Empty/whitespace content should result in empty preview",
                        'data' => [
                            'original' => json_encode($content),
                            'preview' => json_encode($preview)
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Note Content Truncation Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testTruncatedContentMaxLength()) {
            $allPassed = false;
        }
        
        if (!$this->testShortContentNotTruncated()) {
            $allPassed = false;
        }
        
        if (!$this->testLongContentHasEllipsis()) {
            $allPassed = false;
        }
        
        if (!$this->testTruncatedContentIsPrefix()) {
            $allPassed = false;
        }
        
        if (!$this->testEmptyContentHandling()) {
            $allPassed = false;
        }
        
        echo "\n";
        if ($allPassed) {
            echo "All property tests PASSED!\n";
        } else {
            echo "Some property tests FAILED!\n";
        }
        
        return $allPassed;
    }
}
