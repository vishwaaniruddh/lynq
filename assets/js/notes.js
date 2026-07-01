/**
 * Notes Manager Module
 * Requirements: 1.3, 2.2, 2.3, 2.4, 2.5, 3.1-3.4, 4.1-4.4, 5.1-5.4, 6.1-6.3, 7.1-7.3, 9.1-9.3
 * Handles all notes functionality including CRUD, drag/resize, and auto-save
 */

const NotesManager = {
    // Configuration
    baseUrl: '',
    autoSaveDelay: 2000, // 2 seconds
    minWidth: 300,
    minHeight: 200,
    maxWidth: 800,
    maxHeight: 600,
    
    // State
    isOpen: false,
    notes: [],
    currentNote: null,
    isDirty: false,
    autoSaveTimer: null,
    searchTerm: '',
    
    // Drag state
    isDragging: false,
    dragStartX: 0,
    dragStartY: 0,
    dragStartLeft: 0,
    dragStartTop: 0,
    
    // Resize state
    isResizing: false,
    resizeStartX: 0,
    resizeStartY: 0,
    resizeStartWidth: 0,
    resizeStartHeight: 0,
    
    /**
     * Initialize the notes manager
     */
    init: function(baseUrl) {
        this.baseUrl = baseUrl || '';
        this.setupDragHandlers();
        this.setupResizeHandlers();
        this.restorePositionAndSize();
    },
    
    /**
     * Toggle popup visibility
     * Requirements: 1.3
     */
    toggle: function() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    },
    
    /**
     * Open the notes popup
     * Requirements: 2.5
     */
    open: function() {
        const popup = document.getElementById('notesPopup');
        if (popup) {
            popup.classList.remove('hidden');
            this.isOpen = true;
            this.loadNotes();
            this.restorePositionAndSize();
        }
    },
    
    /**
     * Close the notes popup
     */
    close: function() {
        const popup = document.getElementById('notesPopup');
        if (popup) {
            popup.classList.add('hidden');
            this.isOpen = false;
            
            // Save any pending changes
            if (this.isDirty && this.currentNote) {
                this.saveNote();
            }
        }
    },
    
    /**
     * Load user's notes from API
     * Requirements: 5.1
     */
    loadNotes: async function() {
        try {
            const response = await fetch(this.baseUrl + '/api/notes/list.php');
            const data = await response.json();
            
            if (data.success) {
                this.notes = data.data.notes || [];
                this.renderNotesList();
            } else {
                console.error('Failed to load notes:', data.error);
            }
        } catch (error) {
            console.error('Error loading notes:', error);
        }
    },

    /**
     * Render the notes list
     * Requirements: 5.1, 5.2, 5.3, 5.4
     */
    renderNotesList: function() {
        const listContainer = document.getElementById('notesList');
        const emptyState = document.getElementById('notesEmptyState');
        const noResults = document.getElementById('notesNoResults');
        
        if (!listContainer) return;
        
        // Filter notes by search term
        let filteredNotes = this.notes;
        if (this.searchTerm) {
            const term = this.searchTerm.toLowerCase();
            filteredNotes = this.notes.filter(note => 
                (note.title && note.title.toLowerCase().includes(term)) ||
                (note.content && note.content.toLowerCase().includes(term))
            );
        }
        
        // Show appropriate state
        if (this.notes.length === 0) {
            listContainer.classList.add('hidden');
            emptyState.classList.remove('hidden');
            noResults.classList.add('hidden');
            return;
        }
        
        if (filteredNotes.length === 0) {
            listContainer.classList.add('hidden');
            emptyState.classList.add('hidden');
            noResults.classList.remove('hidden');
            return;
        }
        
        listContainer.classList.remove('hidden');
        emptyState.classList.add('hidden');
        noResults.classList.add('hidden');
        
        // Render note cards
        let html = '';
        filteredNotes.forEach(note => {
            const preview = this.truncateContent(note.content || '', 50);
            const highlightedTitle = this.highlightSearchTerm(note.title || 'Untitled');
            const highlightedPreview = this.highlightSearchTerm(preview);
            const timeAgo = this.formatTimeAgo(note.updated_at);
            
            html += `
                <div class="note-card p-3 rounded-lg cursor-pointer" onclick="NotesManager.openNote(${note.id})">
                    <h4 class="font-medium text-amber-900 text-sm truncate">${highlightedTitle}</h4>
                    <p class="text-amber-700 text-xs mt-1 line-clamp-2">${highlightedPreview || '<span class="italic text-amber-500">No content</span>'}</p>
                    <p class="text-amber-500 text-xs mt-2">${timeAgo}</p>
                </div>
            `;
        });
        
        listContainer.innerHTML = html;
    },
    
    /**
     * Truncate content to specified length with ellipsis
     * Requirements: 5.2 - Property 6: Content Preview Truncation
     */
    truncateContent: function(content, maxLength = 50) {
        content = (content || '').trim();
        
        if (content.length <= maxLength) {
            return content;
        }
        
        return content.substring(0, maxLength) + '...';
    },
    
    /**
     * Highlight search term in text
     * Requirements: 9.2
     */
    highlightSearchTerm: function(text) {
        if (!this.searchTerm || !text) return this.escapeHtml(text);
        
        const escaped = this.escapeHtml(text);
        const term = this.escapeHtml(this.searchTerm);
        const regex = new RegExp(`(${term})`, 'gi');
        return escaped.replace(regex, '<mark class="bg-amber-300 rounded px-0.5">$1</mark>');
    },
    
    /**
     * Search notes
     * Requirements: 9.1
     */
    search: function(term) {
        this.searchTerm = term;
        this.renderNotesList();
    },
    
    /**
     * Create new note
     * Requirements: 3.1
     */
    newNote: function() {
        this.currentNote = null;
        this.isDirty = false;
        this.showEditorView();
        
        // Clear inputs
        const titleInput = document.getElementById('noteTitleInput');
        const contentInput = document.getElementById('noteContentInput');
        
        if (titleInput) titleInput.value = '';
        if (contentInput) contentInput.value = '';
        
        // Focus title
        if (titleInput) titleInput.focus();
        
        this.updateSaveStatus('');
    },
    
    /**
     * Open existing note for editing
     * Requirements: 5.3, 6.1
     */
    openNote: async function(noteId) {
        try {
            const response = await fetch(this.baseUrl + '/api/notes/detail.php?id=' + noteId);
            const data = await response.json();
            
            if (data.success) {
                this.currentNote = data.data;
                this.isDirty = false;
                this.showEditorView();
                
                // Populate inputs
                const titleInput = document.getElementById('noteTitleInput');
                const contentInput = document.getElementById('noteContentInput');
                
                if (titleInput) titleInput.value = this.currentNote.title || '';
                if (contentInput) contentInput.value = this.currentNote.content || '';
                
                this.updateSaveStatus('');
            } else {
                console.error('Failed to load note:', data.error);
            }
        } catch (error) {
            console.error('Error loading note:', error);
        }
    },
    
    /**
     * Show editor view, hide list view
     */
    showEditorView: function() {
        document.getElementById('notesListView').classList.add('hidden');
        document.getElementById('notesEditorView').classList.remove('hidden');
    },
    
    /**
     * Show list view, hide editor view
     */
    showListView: function() {
        document.getElementById('notesEditorView').classList.add('hidden');
        document.getElementById('notesListView').classList.remove('hidden');
    },
    
    /**
     * Go back to list view
     */
    backToList: function() {
        // Save any pending changes
        if (this.isDirty) {
            this.saveNote();
        }
        
        this.currentNote = null;
        this.showListView();
        this.loadNotes();
    },

    /**
     * Handle content change - trigger auto-save
     * Requirements: 4.1, 6.2
     */
    onContentChange: function() {
        this.isDirty = true;
        this.updateSaveStatus('Unsaved changes');
        
        // Clear existing timer
        if (this.autoSaveTimer) {
            clearTimeout(this.autoSaveTimer);
        }
        
        // Set new auto-save timer
        this.autoSaveTimer = setTimeout(() => {
            this.saveNote();
        }, this.autoSaveDelay);
    },
    
    /**
     * Save note (create or update)
     * Requirements: 3.3, 4.2, 4.3, 4.4, 6.3
     */
    saveNote: async function() {
        const titleInput = document.getElementById('noteTitleInput');
        const contentInput = document.getElementById('noteContentInput');
        
        const title = titleInput ? titleInput.value : '';
        const content = contentInput ? contentInput.value : '';
        
        // Don't save empty notes
        if (!title.trim() && !content.trim()) {
            return;
        }
        
        this.updateSaveStatus('Saving...');
        
        try {
            let response;
            
            if (this.currentNote && this.currentNote.id) {
                // Update existing note
                response = await fetch(this.baseUrl + '/api/notes/update.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: this.currentNote.id,
                        title: title,
                        content: content
                    })
                });
            } else {
                // Create new note
                response = await fetch(this.baseUrl + '/api/notes/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        title: title,
                        content: content
                    })
                });
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.currentNote = data.data;
                this.isDirty = false;
                const now = new Date();
                this.updateSaveStatus('Saved at ' + now.toLocaleTimeString());
            } else {
                this.updateSaveStatus('Error saving');
                console.error('Failed to save note:', data.error);
                
                // Retry after 5 seconds on failure
                setTimeout(() => {
                    if (this.isDirty) {
                        this.saveNote();
                    }
                }, 5000);
            }
        } catch (error) {
            this.updateSaveStatus('Error saving');
            console.error('Error saving note:', error);
            
            // Retry after 5 seconds on failure
            setTimeout(() => {
                if (this.isDirty) {
                    this.saveNote();
                }
            }, 5000);
        }
    },
    
    /**
     * Update save status indicator
     * Requirements: 4.2, 4.3
     */
    updateSaveStatus: function(status) {
        const statusEl = document.getElementById('notesSaveStatus');
        if (statusEl) {
            statusEl.textContent = status;
        }
    },
    
    /**
     * Show delete confirmation dialog
     * Requirements: 7.1
     */
    confirmDelete: function() {
        if (!this.currentNote || !this.currentNote.id) {
            // New unsaved note - just go back
            this.backToList();
            return;
        }
        
        const modal = document.getElementById('notesDeleteModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    },
    
    /**
     * Cancel delete
     */
    cancelDelete: function() {
        const modal = document.getElementById('notesDeleteModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    },
    
    /**
     * Delete current note
     * Requirements: 7.2, 7.3
     */
    deleteNote: async function() {
        if (!this.currentNote || !this.currentNote.id) {
            this.cancelDelete();
            this.backToList();
            return;
        }
        
        try {
            const response = await fetch(this.baseUrl + '/api/notes/delete.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: this.currentNote.id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.cancelDelete();
                this.currentNote = null;
                this.isDirty = false;
                this.backToList();
            } else {
                console.error('Failed to delete note:', data.error);
                alert('Failed to delete note. Please try again.');
            }
        } catch (error) {
            console.error('Error deleting note:', error);
            alert('Failed to delete note. Please try again.');
        }
    },

    /**
     * Setup drag handlers for popup header
     * Requirements: 2.2
     */
    setupDragHandlers: function() {
        const header = document.getElementById('notesHeader');
        const popup = document.getElementById('notesPopup');
        
        if (!header || !popup) return;
        
        header.addEventListener('mousedown', (e) => {
            if (e.target.closest('button')) return; // Don't drag when clicking buttons
            
            this.isDragging = true;
            this.dragStartX = e.clientX;
            this.dragStartY = e.clientY;
            this.dragStartLeft = popup.offsetLeft;
            this.dragStartTop = popup.offsetTop;
            
            document.body.style.userSelect = 'none';
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!this.isDragging) return;
            
            const deltaX = e.clientX - this.dragStartX;
            const deltaY = e.clientY - this.dragStartY;
            
            let newLeft = this.dragStartLeft + deltaX;
            let newTop = this.dragStartTop + deltaY;
            
            // Keep within viewport
            const maxLeft = window.innerWidth - popup.offsetWidth;
            const maxTop = window.innerHeight - popup.offsetHeight;
            
            newLeft = Math.max(0, Math.min(newLeft, maxLeft));
            newTop = Math.max(0, Math.min(newTop, maxTop));
            
            popup.style.left = newLeft + 'px';
            popup.style.top = newTop + 'px';
        });
        
        document.addEventListener('mouseup', () => {
            if (this.isDragging) {
                this.isDragging = false;
                document.body.style.userSelect = '';
                this.savePositionAndSize();
            }
        });
    },
    
    /**
     * Setup resize handlers
     * Requirements: 2.3
     */
    setupResizeHandlers: function() {
        const handle = document.getElementById('notesResizeHandle');
        const popup = document.getElementById('notesPopup');
        
        if (!handle || !popup) return;
        
        handle.addEventListener('mousedown', (e) => {
            e.preventDefault();
            this.isResizing = true;
            this.resizeStartX = e.clientX;
            this.resizeStartY = e.clientY;
            this.resizeStartWidth = popup.offsetWidth;
            this.resizeStartHeight = popup.offsetHeight;
            
            document.body.style.userSelect = 'none';
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!this.isResizing) return;
            
            const deltaX = e.clientX - this.resizeStartX;
            const deltaY = e.clientY - this.resizeStartY;
            
            let newWidth = this.resizeStartWidth + deltaX;
            let newHeight = this.resizeStartHeight + deltaY;
            
            // Apply min/max constraints (Property 2: Resize Bounds Constraint)
            newWidth = Math.max(this.minWidth, Math.min(newWidth, this.maxWidth));
            newHeight = Math.max(this.minHeight, Math.min(newHeight, this.maxHeight));
            
            popup.style.width = newWidth + 'px';
            popup.style.height = newHeight + 'px';
        });
        
        document.addEventListener('mouseup', () => {
            if (this.isResizing) {
                this.isResizing = false;
                document.body.style.userSelect = '';
                this.savePositionAndSize();
            }
        });
    },
    
    /**
     * Save position and size to localStorage
     * Requirements: 2.4
     */
    savePositionAndSize: function() {
        const popup = document.getElementById('notesPopup');
        if (!popup) return;
        
        const state = {
            left: popup.offsetLeft,
            top: popup.offsetTop,
            width: popup.offsetWidth,
            height: popup.offsetHeight
        };
        
        localStorage.setItem('notesPopupState', JSON.stringify(state));
    },
    
    /**
     * Restore position and size from localStorage
     * Requirements: 2.5
     */
    restorePositionAndSize: function() {
        const popup = document.getElementById('notesPopup');
        if (!popup) return;
        
        const saved = localStorage.getItem('notesPopupState');
        if (saved) {
            try {
                const state = JSON.parse(saved);
                
                // Validate and apply position
                if (typeof state.left === 'number' && typeof state.top === 'number') {
                    // Ensure within viewport
                    const maxLeft = window.innerWidth - (state.width || popup.offsetWidth);
                    const maxTop = window.innerHeight - (state.height || popup.offsetHeight);
                    
                    popup.style.left = Math.max(0, Math.min(state.left, maxLeft)) + 'px';
                    popup.style.top = Math.max(0, Math.min(state.top, maxTop)) + 'px';
                }
                
                // Validate and apply size with constraints
                if (typeof state.width === 'number') {
                    popup.style.width = Math.max(this.minWidth, Math.min(state.width, this.maxWidth)) + 'px';
                }
                if (typeof state.height === 'number') {
                    popup.style.height = Math.max(this.minHeight, Math.min(state.height, this.maxHeight)) + 'px';
                }
            } catch (e) {
                console.error('Failed to restore notes popup state:', e);
            }
        }
    },
    
    /**
     * Format time ago string
     */
    formatTimeAgo: function(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
        
        return date.toLocaleDateString();
    },
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Get base URL from a data attribute or global variable
    const baseUrl = document.body.dataset.baseUrl || window.baseUrl || '';
    NotesManager.init(baseUrl);
});
