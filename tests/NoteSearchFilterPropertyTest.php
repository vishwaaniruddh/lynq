<?php
/**
 * Property Test: Search Filter Accuracy
 * **Feature: notes-module, Property 9: Search Filter Accuracy**
 * **Validates: Requirements 9.1**
 * 
 * Property: *For any* search term and set of notes, the search results 
 * SHALL contain only notes where the title or content contains the 
 * search term (case-insensitive).
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../services/NoteService.php';
require_once __DIR__ . '/../repositories/NoteRepository.php';

class NoteSearchFilterPropertyTest extends PropertyTestBase {
    private $noteService;
    private $noteRepository;
    private $testUserIds = [];
    private $createdNoteIds = [];
    
    public function __construct() {
        parent::__construct();
        $this->noteService = new NoteService();
        $this->noteRepository = new NoteRepository();
    }
    
    /**
     * Set up test user
     */
    private function setupTestUser(): int {
        $username = 'test_notes_search_' . $this->generateRandomString(8);
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
     * Generate a unique search term
     */
    private function generateSearchTerm(): string {
        // Generate a unique term that's unlikely to appear randomly
        return 'SRCH' . $this->generateRandomString(6) . 'TERM';
    }
    
    /**
     * Check if string contains term (case-insensitive)
     */
    private function containsTerm(string $haystack, string $needle): bool {
        return stripos($haystack, $needle) !== false;
    }
    
    /**
     * Property Test: Search results contain only matching notes
     */
    public function testSearchResultsContainOnlyMatches(): bool {
        return $this->runPropertyTest(
            'Search Filter - Results contain only matching notes',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Generate a unique search term
                $searchTerm = $this->generateSearchTerm();
                
                // Create notes - some with the search term, some without
                $notesWithTerm = $this->generateRandomInt(1, 3);
                $notesWithoutTerm = $this->generateRandomInt(1, 3);
                
                $expectedMatchIds = [];
                
                // Create notes that should match (term in title or content)
                for ($i = 0; $i < $notesWithTerm; $i++) {
                    // Randomly put term in title or content
                    $inTitle = $this->generateRandomBool();
                    
                    if ($inTitle) {
                        $title = 'Note with ' . $searchTerm . ' in title';
                        $content = 'Regular content without the term';
                    } else {
                        $title = 'Regular title without the term';
                        $content = 'Content with ' . $searchTerm . ' embedded';
                    }
                    
                    $createResult = $this->noteService->createNote($userId, $title, $content);
                    if ($createResult['success']) {
                        $noteId = $createResult['data']['id'];
                        $this->createdNoteIds[] = $noteId;
                        $expectedMatchIds[] = $noteId;
                    }
                }
                
                // Create notes that should NOT match
                for ($i = 0; $i < $notesWithoutTerm; $i++) {
                    $title = 'Unrelated note ' . ($i + 1);
                    $content = 'This content has nothing special';
                    
                    $createResult = $this->noteService->createNote($userId, $title, $content);
                    if ($createResult['success']) {
                        $this->createdNoteIds[] = $createResult['data']['id'];
                    }
                }
                
                // Perform search
                $searchResult = $this->noteService->searchNotes($userId, $searchTerm);
                
                if (!$searchResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Search failed: " . ($searchResult['message'] ?? 'Unknown error'),
                        'data' => ['search_term' => $searchTerm]
                    ];
                }
                
                $results = $searchResult['data'];
                
                // Verify: All results contain the search term
                foreach ($results as $note) {
                    $titleContains = $this->containsTerm($note['title'], $searchTerm);
                    $contentContains = $this->containsTerm($note['content'] ?? '', $searchTerm);
                    
                    if (!$titleContains && !$contentContains) {
                        $this->cleanupTestData();
                        return [
                            'success' => false,
                            'message' => "Search returned note that doesn't contain search term",
                            'data' => [
                                'search_term' => $searchTerm,
                                'note_id' => $note['id'],
                                'note_title' => $note['title']
                            ]
                        ];
                    }
                }
                
                // Verify: Result count matches expected
                if (count($results) !== count($expectedMatchIds)) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Search returned wrong number of results",
                        'data' => [
                            'expected_count' => count($expectedMatchIds),
                            'actual_count' => count($results),
                            'search_term' => $searchTerm
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
     * Property Test: Search is case-insensitive
     */
    public function testSearchIsCaseInsensitive(): bool {
        return $this->runPropertyTest(
            'Search Filter - Case insensitive matching',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Generate a search term with mixed case
                $baseTerm = $this->generateSearchTerm();
                
                // Create note with lowercase term
                $createResult = $this->noteService->createNote(
                    $userId,
                    'Note with ' . strtolower($baseTerm),
                    'Content here'
                );
                
                if (!$createResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Failed to create test note",
                        'data' => []
                    ];
                }
                
                $this->createdNoteIds[] = $createResult['data']['id'];
                
                // Search with uppercase term
                $searchResult = $this->noteService->searchNotes($userId, strtoupper($baseTerm));
                
                if (!$searchResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Search failed",
                        'data' => []
                    ];
                }
                
                // Verify: Should find the note despite case difference
                if (count($searchResult['data']) !== 1) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Case-insensitive search failed to find note",
                        'data' => [
                            'search_term' => strtoupper($baseTerm),
                            'note_term' => strtolower($baseTerm),
                            'results_count' => count($searchResult['data'])
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
     * Property Test: Empty search returns all notes
     */
    public function testEmptySearchReturnsAll(): bool {
        return $this->runPropertyTest(
            'Search Filter - Empty search returns all notes',
            function() {
                // Setup: Create test user
                $userId = $this->setupTestUser();
                
                // Create random number of notes
                $noteCount = $this->generateRandomInt(1, 5);
                
                for ($i = 0; $i < $noteCount; $i++) {
                    $createResult = $this->noteService->createNote(
                        $userId,
                        'Note ' . ($i + 1),
                        'Content ' . ($i + 1)
                    );
                    
                    if ($createResult['success']) {
                        $this->createdNoteIds[] = $createResult['data']['id'];
                    }
                }
                
                // Search with empty string
                $searchResult = $this->noteService->searchNotes($userId, '');
                
                if (!$searchResult['success']) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Empty search failed",
                        'data' => []
                    ];
                }
                
                // Verify: Should return all notes
                if (count($searchResult['data']) !== $noteCount) {
                    $this->cleanupTestData();
                    return [
                        'success' => false,
                        'message' => "Empty search did not return all notes",
                        'data' => [
                            'expected_count' => $noteCount,
                            'actual_count' => count($searchResult['data'])
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
        echo "=== Note Search Filter Property Tests ===\n\n";
        
        $allPassed = true;
        
        if (!$this->testSearchResultsContainOnlyMatches()) {
            $allPassed = false;
        }
        
        if (!$this->testSearchIsCaseInsensitive()) {
            $allPassed = false;
        }
        
        if (!$this->testEmptySearchReturnsAll()) {
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
