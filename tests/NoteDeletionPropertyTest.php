<?php
/**
 * Property Test: Note Deletion Removes Record
 * **Feature: notes-module, Property 7: Note Deletion Removes Record**
 * **Validates: Requirements 7.2, 7.3**
 * 
 * Property: *For any* note that is deleted, querying for that note 
 * SHALL return null/not found.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/NoteService.php';
require_once __DIR__ . '/../repositories/NoteRepository.php';

class NoteDeletionPropertyTest extends PropertyTestBase {
    private $noteService;
    private $noteRepository;
    private $testUserIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->noteService = new NoteService();
        $this->noteRepository = new NoteRepository();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_notes_del_' . $this->generateRandomString(8);
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
        // Delete test users (notes will cascade delete)
        if (!empty($this->testUserIds)) {
            $ids = implode(',', array_map('intval', $this->testUserIds));
            $sql = "DELETE FROM users WHERE id IN ($ids)";
            $this->db->query($sql);
            $this->testUserIds = [];
        }
    }
    
    /**
     * Generate random title
     */
    private function generateRandomTitle(): string {
        $length = $this->generateRandomInt(1, 100);
        return $this->generateRandomString($length);
    }
    
    /**
     * Generate random content
     */
    private function generateRandomContent(): string {
        $length = $this->generateRandomInt(0, 500);
        return $this->generateRandomString($length);
    }
    
    /**
     * Property Test: Deleted note returns null on query
     */
    public function testDeletedNoteReturnsNull(): bool {
        return $this->runPropertyTest(
            'Note Deletion - Deleted note returns null',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create a note
                $title = $this->generateRandomTitle();
                $content = $this->generateRandomContent();
                
                $createResult = $this->noteService->createNote($userId, $title, $content);
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create note for deletion test",
                        'data' => []
                    ];
                }
                
                $noteId = $createResult['data']['id'];
                
                // Verify note exists before deletion
                $beforeDelete = $this->noteService->getNote($noteId, $userId);
                if (!$beforeDelete['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Note not found before deletion",
                        'data' => ['note_id' => $noteId]
                    ];
                }
                
                // Delete the note
                $deleteResult = $this->noteService->deleteNote($noteId, $userId);
                
                if (!$deleteResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to delete note: " . ($deleteResult['message'] ?? 'Unknown error'),
                        'data' => ['note_id' => $noteId]
                    ];
                }
                
                // Verify note no longer exists via service
                $afterDelete = $this->noteService->getNote($noteId, $userId);
                if ($afterDelete['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Note still accessible via service after deletion",
                        'data' => ['note_id' => $noteId]
                    ];
                }
                
                // Verify note no longer exists via repository
                $directQuery = $this->noteRepository->findByIdAndUserId($noteId, $userId);
                if ($directQuery !== null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Note still exists in database after deletion",
                        'data' => ['note_id' => $noteId]
                    ];
                }
                
                // Cleanup
                $this->cleanupTestData();
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Deleted note removed from user's notes list
     */
    public function testDeletedNoteRemovedFromList(): bool {
        return $this->runPropertyTest(
            'Note Deletion - Deleted note removed from list',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create multiple notes
                $noteCount = $this->generateRandomInt(2, 5);
                $noteIds = [];
                
                for ($i = 0; $i < $noteCount; $i++) {
                    $createResult = $this->noteService->createNote(
                        $userId,
                        'Note ' . ($i + 1),
                        'Content ' . ($i + 1)
                    );
                    
                    if ($createResult['success']) {
                        $noteIds[] = $createResult['data']['id'];
                    }
                }
                
                if (count($noteIds) < 2) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create enough notes for test",
                        'data' => ['created_count' => count($noteIds)]
                    ];
                }
                
                // Pick a random note to delete
                $deleteIndex = array_rand($noteIds);
                $noteIdToDelete = $noteIds[$deleteIndex];
                
                // Get notes list before deletion
                $beforeResult = $this->noteService->getUserNotes($userId);
                $countBefore = count($beforeResult['data']);
                
                // Delete the note
                $this->noteService->deleteNote($noteIdToDelete, $userId);
                
                // Get notes list after deletion
                $afterResult = $this->noteService->getUserNotes($userId);
                $countAfter = count($afterResult['data']);
                
                // Verify count decreased by 1
                if ($countAfter !== $countBefore - 1) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Notes count did not decrease by 1 after deletion",
                        'data' => [
                            'count_before' => $countBefore,
                            'count_after' => $countAfter,
                            'expected_after' => $countBefore - 1
                        ]
                    ];
                }
                
                // Verify deleted note is not in the list
                foreach ($afterResult['data'] as $note) {
                    if ((int)$note['id'] === $noteIdToDelete) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Deleted note still appears in notes list",
                            'data' => ['deleted_note_id' => $noteIdToDelete]
                        ];
                    }
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
        echo "=== Note Deletion Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testDeletedNoteReturnsNull()) {
            $allPassed = false;
        }
        
        if (!$this->testDeletedNoteRemovedFromList()) {
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
