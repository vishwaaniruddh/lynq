<?php
/**
 * Note Service
 * Handles business logic for personal user notes
 * 
 * Requirements: 3.3, 6.3, 7.2, 8.1, 8.2
 * - 3.3: Create notes with user association
 * - 6.3: Update existing notes
 * - 7.2: Delete notes
 * - 8.1: Return only notes belonging to user
 * - 8.2: Deny access to other users' notes
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../repositories/NoteRepository.php';

class NoteService {
    private $db;
    private $noteRepository;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->noteRepository = new NoteRepository();
    }
    
    /**
     * Get all notes for a user sorted by updated_at DESC
     * Requirement 8.1
     * 
     * @param int $userId User ID
     * @return array Result with success status and notes data
     */
    public function getUserNotes(int $userId): array {
        try {
            $notes = $this->noteRepository->findByUserId($userId);
            
            return [
                'success' => true,
                'data' => $notes
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve notes: ' . $e->getMessage(),
                'code' => 'FETCH_ERROR'
            ];
        }
    }
    
    /**
     * Get a single note by ID with user authorization
     * Requirement 8.2
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID (for authorization)
     * @return array Result with success status and note data
     */
    public function getNote(int $noteId, int $userId): array {
        try {
            $note = $this->noteRepository->findByIdAndUserId($noteId, $userId);
            
            if ($note === null) {
                return [
                    'success' => false,
                    'message' => 'Note not found or access denied',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'data' => $note
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve note: ' . $e->getMessage(),
                'code' => 'FETCH_ERROR'
            ];
        }
    }
    
    /**
     * Search notes by title or content
     * Requirement 9.1
     * 
     * @param int $userId User ID
     * @param string $term Search term
     * @return array Result with success status and matching notes
     */
    public function searchNotes(int $userId, string $term): array {
        try {
            // Return all notes if search term is empty
            if (trim($term) === '') {
                return $this->getUserNotes($userId);
            }
            
            $notes = $this->noteRepository->search($userId, $term);
            
            return [
                'success' => true,
                'data' => $notes
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to search notes: ' . $e->getMessage(),
                'code' => 'SEARCH_ERROR'
            ];
        }
    }
    
    /**
     * Create a new note
     * Requirement 3.3
     * 
     * @param int $userId User ID
     * @param string $title Note title
     * @param string $content Note content
     * @return array Result with success status and created note
     */
    public function createNote(int $userId, string $title, string $content): array {
        try {
            // Validate title length
            if (strlen($title) > 255) {
                return [
                    'success' => false,
                    'message' => 'Title must be 255 characters or less',
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            $noteId = $this->noteRepository->createNote([
                'user_id' => $userId,
                'title' => $title,
                'content' => $content
            ]);
            
            // Retrieve the created note
            $note = $this->noteRepository->findByIdAndUserId($noteId, $userId);
            
            return [
                'success' => true,
                'message' => 'Note created successfully',
                'data' => $note
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create note: ' . $e->getMessage(),
                'code' => 'CREATE_ERROR'
            ];
        }
    }
    
    /**
     * Update an existing note
     * Requirement 6.3, 8.2
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID (for authorization)
     * @param string $title New title
     * @param string $content New content
     * @return array Result with success status and updated note
     */
    public function updateNote(int $noteId, int $userId, string $title, string $content): array {
        try {
            // Validate title length
            if (strlen($title) > 255) {
                return [
                    'success' => false,
                    'message' => 'Title must be 255 characters or less',
                    'code' => 'VALIDATION_ERROR'
                ];
            }
            
            // Check if note exists and belongs to user
            $existingNote = $this->noteRepository->findByIdAndUserId($noteId, $userId);
            if ($existingNote === null) {
                return [
                    'success' => false,
                    'message' => 'Note not found or access denied',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            // Update the note
            $this->noteRepository->updateNote($noteId, $userId, [
                'title' => $title,
                'content' => $content
            ]);
            
            // Retrieve the updated note
            $note = $this->noteRepository->findByIdAndUserId($noteId, $userId);
            
            return [
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => $note
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update note: ' . $e->getMessage(),
                'code' => 'UPDATE_ERROR'
            ];
        }
    }
    
    /**
     * Delete a note
     * Requirement 7.2, 8.2
     * 
     * @param int $noteId Note ID
     * @param int $userId User ID (for authorization)
     * @return array Result with success status
     */
    public function deleteNote(int $noteId, int $userId): array {
        try {
            // Check if note exists and belongs to user
            $existingNote = $this->noteRepository->findByIdAndUserId($noteId, $userId);
            if ($existingNote === null) {
                return [
                    'success' => false,
                    'message' => 'Note not found or access denied',
                    'code' => 'NOT_FOUND'
                ];
            }
            
            // Delete the note
            $deleted = $this->noteRepository->deleteNote($noteId, $userId);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete note',
                    'code' => 'DELETE_ERROR'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Note deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete note: ' . $e->getMessage(),
                'code' => 'DELETE_ERROR'
            ];
        }
    }
    
    /**
     * Get note count for a user
     * 
     * @param int $userId User ID
     * @return int Number of notes
     */
    public function getNoteCount(int $userId): int {
        return $this->noteRepository->countByUserId($userId);
    }
}
