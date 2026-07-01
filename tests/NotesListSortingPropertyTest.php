<?php
/**
 * Property Test: Notes List Sorting
 * **Feature: notes-module, Property 5: Notes List Sorting**
 * **Validates: Requirements 5.1**
 * 
 * Property: *For any* set of notes belonging to a user, the notes list 
 * SHALL be sorted by updated_at timestamp in descending order (most recent first).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/NoteRepository.php';

class NotesListSortingPropertyTest extends PropertyTestBase {
    private $noteRepository;
    private $testUserId;
    private $createdNoteIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->noteRepository = new NoteRepository();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        // Create a test user
        $username = 'test_notes_sort_' . $this->generateRandomString(8);
        $email = $username . '@test.com';
        
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, status, company_id, created_at) 
                VALUES (?, ?, ?, 'Test', 'User', 1, 1, 1, NOW())";
        $stmt = $this->executeQuery($sql, [$username, $email, password_hash('test123', PASSWORD_DEFAULT)], 'sss');
        $userId = $this->db->insert_id;
        $stmt->close();
        
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
        
        // Delete test user
        if ($this->testUserId) {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $this->executeQuery($sql, [$this->testUserId], 'i');
            $stmt->close();
            $this->testUserId = null;
        }
    }
    
    /**
     * Generate random note data
     */
    private function generateRandomNote(int $userId): array {
        return [
            'user_id' => $userId,
            'title' => $this->generateRandomString($this->generateRandomInt(1, 50)),
            'content' => $this->generateRandomString($this->generateRandomInt(0, 200))
        ];
    }
    
    /**
     * Create a note with a specific timestamp
     */
    private function createNoteWithTimestamp(int $userId, string $title, string $content, string $updatedAt): int {
        $sql = "INSERT INTO notes (user_id, title, content, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->executeQuery($sql, [$userId, $title, $content, $updatedAt, $updatedAt], 'issss');
        $noteId = $this->db->insert_id;
        $stmt->close();
        
        $this->createdNoteIds[] = $noteId;
        return $noteId;
    }
    
    /**
     * Property Test: Notes are sorted by updated_at DESC
     */
    public function testNotesListSorting(): bool {
        return $this->runPropertyTest(
            'Notes List Sorting - updated_at DESC',
            function() {
                // Setup: Create test user
                $this->testUserId = $this->setupTestUser();
                
                // Generate random number of notes (2-10)
                $noteCount = $this->generateRandomInt(2, 10);
                $timestamps = [];
                
                // Create notes with random timestamps
                $baseTime = strtotime('2025-01-01 00:00:00');
                for ($i = 0; $i < $noteCount; $i++) {
                    // Generate random timestamp within a year
                    $randomOffset = $this->generateRandomInt(0, 365 * 24 * 60 * 60);
                    $timestamp = date('Y-m-d H:i:s', $baseTime + $randomOffset);
                    $timestamps[] = $timestamp;
                    
                    $this->createNoteWithTimestamp(
                        $this->testUserId,
                        'Note ' . ($i + 1),
                        'Content ' . ($i + 1),
                        $timestamp
                    );
                }
                
                // Retrieve notes using repository
                $notes = $this->noteRepository->findByUserId($this->testUserId);
                
                // Verify: Notes should be sorted by updated_at DESC
                $previousTimestamp = null;
                foreach ($notes as $index => $note) {
                    $currentTimestamp = strtotime($note['updated_at']);
                    
                    if ($previousTimestamp !== null) {
                        if ($currentTimestamp > $previousTimestamp) {
                            $this->cleanupTestData();
                            return [
                                'success' => false,
                                'message' => "Notes not sorted correctly: note at index $index has timestamp " . 
                                           $note['updated_at'] . " which is after previous timestamp",
                                'data' => [
                                    'note_count' => $noteCount,
                                    'failed_at_index' => $index,
                                    'current_timestamp' => $note['updated_at'],
                                    'previous_timestamp' => date('Y-m-d H:i:s', $previousTimestamp)
                                ]
                            ];
                        }
                    }
                    
                    $previousTimestamp = $currentTimestamp;
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
        echo "=== Notes List Sorting Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testNotesListSorting()) {
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
