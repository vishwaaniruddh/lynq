<?php
/**
 * Property Test: User Isolation
 * **Feature: notes-module, Property 8: User Isolation**
 * **Validates: Requirements 8.1, 8.2, 8.3**
 * 
 * Property: *For any* user, querying notes SHALL return only notes where user_id 
 * matches the requesting user's ID, and attempting to access another user's note 
 * SHALL be denied.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../repositories/NoteRepository.php';

class NoteUserIsolationPropertyTest extends PropertyTestBase {
    private $noteRepository;
    private $testUserIds = [];
    private $createdNoteIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->noteRepository = new NoteRepository();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_notes_iso_' . $this->generateRandomString(8);
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
     * Create a note for a user
     */
    private function createNote(int $userId, string $title, string $content): int {
        $noteId = $this->noteRepository->createNote([
            'user_id' => $userId,
            'title' => $title,
            'content' => $content
        ]);
        
        $this->createdNoteIds[] = $noteId;
        return $noteId;
    }
    
    /**
     * Property Test: findByUserId returns only user's own notes
     */
    public function testFindByUserIdIsolation(): bool {
        return $this->runPropertyTest(
            'User Isolation - findByUserId returns only own notes',
            function() {
                // Setup: Create two test users
                $user1Id = $this->setupTestUser();
                $user2Id = $this->setupTestUser();
                
                // Create random number of notes for each user
                $user1NoteCount = $this->generateRandomInt(1, 5);
                $user2NoteCount = $this->generateRandomInt(1, 5);
                
                $user1NoteIds = [];
                $user2NoteIds = [];
                
                for ($i = 0; $i < $user1NoteCount; $i++) {
                    $user1NoteIds[] = $this->createNote(
                        $user1Id,
                        'User1 Note ' . ($i + 1),
                        'Content for user 1'
                    );
                }
                
                for ($i = 0; $i < $user2NoteCount; $i++) {
                    $user2NoteIds[] = $this->createNote(
                        $user2Id,
                        'User2 Note ' . ($i + 1),
                        'Content for user 2'
                    );
                }
                
                // Test: Query notes for user 1
                $user1Notes = $this->noteRepository->findByUserId($user1Id);
                
                // Verify: All returned notes belong to user 1
                foreach ($user1Notes as $note) {
                    if ((int)$note['user_id'] !== $user1Id) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "findByUserId returned note belonging to different user",
                            'data' => [
                                'expected_user_id' => $user1Id,
                                'actual_user_id' => $note['user_id'],
                                'note_id' => $note['id']
                            ]
                        ];
                    }
                }
                
                // Verify: Count matches expected
                if (count($user1Notes) !== $user1NoteCount) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "findByUserId returned wrong number of notes",
                        'data' => [
                            'expected_count' => $user1NoteCount,
                            'actual_count' => count($user1Notes)
                        ]
                    ];
                }
                
                // Test: Query notes for user 2
                $user2Notes = $this->noteRepository->findByUserId($user2Id);
                
                // Verify: All returned notes belong to user 2
                foreach ($user2Notes as $note) {
                    if ((int)$note['user_id'] !== $user2Id) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "findByUserId returned note belonging to different user",
                            'data' => [
                                'expected_user_id' => $user2Id,
                                'actual_user_id' => $note['user_id'],
                                'note_id' => $note['id']
                            ]
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
     * Property Test: findByIdAndUserId denies access to other user's notes
     */
    public function testFindByIdAndUserIdDeniesAccess(): bool {
        return $this->runPropertyTest(
            'User Isolation - findByIdAndUserId denies access to other user notes',
            function() {
                // Setup: Create two test users
                $user1Id = $this->setupTestUser();
                $user2Id = $this->setupTestUser();
                
                // Create a note for user 1
                $noteId = $this->createNote(
                    $user1Id,
                    'Private Note',
                    'This is user 1 private content'
                );
                
                // Test: User 1 can access their own note
                $note = $this->noteRepository->findByIdAndUserId($noteId, $user1Id);
                if ($note === null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Owner cannot access their own note",
                        'data' => [
                            'note_id' => $noteId,
                            'user_id' => $user1Id
                        ]
                    ];
                }
                
                // Test: User 2 cannot access user 1's note
                $unauthorizedNote = $this->noteRepository->findByIdAndUserId($noteId, $user2Id);
                if ($unauthorizedNote !== null) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "User was able to access another user's note",
                        'data' => [
                            'note_id' => $noteId,
                            'owner_user_id' => $user1Id,
                            'requesting_user_id' => $user2Id
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
     * Property Test: Notes are stored with correct user_id
     */
    public function testNoteStoredWithCorrectUserId(): bool {
        return $this->runPropertyTest(
            'User Isolation - Notes stored with correct user_id',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create a note
                $title = $this->generateRandomString(20);
                $content = $this->generateRandomString(100);
                
                $noteId = $this->createNote($userId, $title, $content);
                
                // Retrieve note directly from database
                $sql = "SELECT * FROM notes WHERE id = ?";
                $result = $this->getResults($sql, [$noteId], 'i');
                
                if (empty($result)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Note was not stored in database",
                        'data' => ['note_id' => $noteId]
                    ];
                }
                
                $note = $result[0];
                
                // Verify user_id is correct
                if ((int)$note['user_id'] !== $userId) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Note stored with incorrect user_id",
                        'data' => [
                            'expected_user_id' => $userId,
                            'actual_user_id' => $note['user_id']
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
        echo "=== Note User Isolation Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testFindByUserIdIsolation()) {
            $allPassed = false;
        }
        
        if (!$this->testFindByIdAndUserIdDeniesAccess()) {
            $allPassed = false;
        }
        
        if (!$this->testNoteStoredWithCorrectUserId()) {
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
