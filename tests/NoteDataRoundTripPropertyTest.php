<?php
/**
 * Property Test: Note Data Round-Trip
 * **Feature: notes-module, Property 4: Note Data Round-Trip**
 * **Validates: Requirements 3.3, 6.3**
 * 
 * Property: *For any* note with valid title and content, creating or updating 
 * the note and then retrieving it SHALL return the same title and content values.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/NoteService.php';

class NoteDataRoundTripPropertyTest extends PropertyTestBase {
    private $noteService;
    private $testUserIds = [];
    private $createdNoteIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->noteService = new NoteService();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_notes_rt_' . $this->generateRandomString(8);
        $email = $username . '@test.com';
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, status, company_id, created_at) 
                VALUES (?, ?, ?, 'Test', 'User', 1, 1, 1, NOW())";
        $stmt = $this->executeQuery($sql, [$username, $email, password_hash('test123', PASSWORD_DEFAULT)], 'sss');
        $userId = $this->db->insert_id;
        $stmt->close();
        
        $this->testUserIds[] = $userId;
        return $userId;
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete created notes
        if (!empty($this->createdNoteIds)) {
            $ids = implode(',', array_map('intval', $this->createdNoteIds));
            $sql = "DELETE FROM notes WHERE id IN ($ids)";
            $this->db->query($sql);
            $this->createdNoteIds = [];
        }
        
        // Delete test users
        if (!empty($this->testUserIds)) {
            $ids = implode(',', array_map('intval', $this->testUserIds));
            $sql = "DELETE FROM users WHERE id IN ($ids)";
            $this->db->query($sql);
            $this->testUserIds = [];
        }
    }
    
    /**
     * Generate random title (0-255 chars)
     */
    private function generateRandomTitle(): string {
        $length = $this->generateRandomInt(0, 255);
        return $this->generateRandomString($length);
    }
    
    /**
     * Generate random content (0-1000 chars for testing)
     */
    private function generateRandomContent(): string {
        $length = $this->generateRandomInt(0, 1000);
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Create note round-trip
     */
    public function testCreateNoteRoundTrip(): bool {
        return $this->runPropertyTest(
            'Note Data Round-Trip - Create and retrieve',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Generate random title and content
                $title = $this->generateRandomTitle();
                $content = $this->generateRandomContent();
                
                // Create note
                $createResult = $this->noteService->createNote($userId, $title, $content);
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create note: " . ($createResult['message'] ?? 'Unknown error'),
                        'data' => [
                            'title_length' => strlen($title),
                            'content_length' => strlen($content)
                        ]
                    ];
                }
                
                $noteId = $createResult['data']['id'];
                $this->createdNoteIds[] = $noteId;
                
                // Retrieve note
                $getResult = $this->noteService->getNote($noteId, $userId);
                
                if (!$getResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to retrieve note: " . ($getResult['message'] ?? 'Unknown error'),
                        'data' => ['note_id' => $noteId]
                    ];
                }
                
                $retrievedNote = $getResult['data'];
                
                // Verify title matches
                if ($retrievedNote['title'] !== $title) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Title mismatch after round-trip",
                        'data' => [
                            'original_title' => $title,
                            'retrieved_title' => $retrievedNote['title']
                        ]
                    ];
                }
                
                // Verify content matches
                if ($retrievedNote['content'] !== $content) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Content mismatch after round-trip",
                        'data' => [
                            'original_content_length' => strlen($content),
                            'retrieved_content_length' => strlen($retrievedNote['content'])
                        ]
                    ];
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Update note round-trip
     */
    public function testUpdateNoteRoundTrip(): bool {
        return $this->runPropertyTest(
            'Note Data Round-Trip - Update and retrieve',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create initial note
                $initialTitle = $this->generateRandomTitle();
                $initialContent = $this->generateRandomContent();
                
                $createResult = $this->noteService->createNote($userId, $initialTitle, $initialContent);
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create initial note",
                        'data' => []
                    ];
                }
                
                $noteId = $createResult['data']['id'];
                $this->createdNoteIds[] = $noteId;
                
                // Generate new random title and content
                $newTitle = $this->generateRandomTitle();
                $newContent = $this->generateRandomContent();
                
                // Update note
                $updateResult = $this->noteService->updateNote($noteId, $userId, $newTitle, $newContent);
                
                if (!$updateResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to update note: " . ($updateResult['message'] ?? 'Unknown error'),
                        'data' => [
                            'note_id' => $noteId,
                            'new_title_length' => strlen($newTitle),
                            'new_content_length' => strlen($newContent)
                        ]
                    ];
                }
                
                // Retrieve updated note
                $getResult = $this->noteService->getNote($noteId, $userId);
                
                if (!$getResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to retrieve updated note",
                        'data' => ['note_id' => $noteId]
                    ];
                }
                
                $retrievedNote = $getResult['data'];
                
                // Verify title matches new value
                if ($retrievedNote['title'] !== $newTitle) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Title mismatch after update round-trip",
                        'data' => [
                            'expected_title' => $newTitle,
                            'actual_title' => $retrievedNote['title']
                        ]
                    ];
                }
                
                // Verify content matches new value
                if ($retrievedNote['content'] !== $newContent) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Content mismatch after update round-trip",
                        'data' => [
                            'expected_content_length' => strlen($newContent),
                            'actual_content_length' => strlen($retrievedNote['content'])
                        ]
                    ];
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): bool {
        echo "=== Note Data Round-Trip Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testCreateNoteRoundTrip()) {
            $allPassed = false;
        }
        
        if (!$this->testUpdateNoteRoundTrip()) {
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
