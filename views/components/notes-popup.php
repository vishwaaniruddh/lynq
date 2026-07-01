<?php
/**
 * Notes Popup Component
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5 - Draggable, resizable notepad popup
 */
?>
<!-- Notes FAB (Floating Action Button) -->
<button id="notesFab" onclick="NotesManager.toggle()" 
        class="fixed bottom-[72px] right-4 md:bottom-24 md:right-6 z-40 w-12 h-12 md:w-14 md:h-14 bg-gradient-to-br from-amber-400 to-orange-500 hover:from-amber-500 hover:to-orange-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center group"
        title="My Notes">
    <i class="fas fa-sticky-note text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
</button>

<!-- Notes Popup Container -->
<div id="notesPopup" class="fixed z-50 hidden inset-4 md:inset-auto md:bottom-[160px] md:right-6 md:w-[400px] md:h-[500px]">
    <!-- Notepad styled container -->
    <div class="notes-container bg-amber-50 rounded-lg shadow-2xl border border-amber-200 flex flex-col h-full overflow-hidden"
         style="background: linear-gradient(to bottom, #fef3c7 0%, #fffbeb 100%);">
        
        <!-- Draggable Header -->
        <div id="notesHeader" class="notes-header bg-amber-400 px-4 py-3 flex items-center justify-between md:cursor-move select-none rounded-t-lg"
             style="background: linear-gradient(to bottom, #fbbf24 0%, #f59e0b 100%);">
            <div class="flex items-center space-x-2">
                <i class="fas fa-sticky-note text-amber-800"></i>
                <span class="font-semibold text-amber-900">My Notes</span>
            </div>
            <button onclick="NotesManager.close()" class="text-amber-800 hover:text-amber-950 transition p-1">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Notepad lines decoration -->
        <div class="notes-lines absolute left-0 right-0 top-12 bottom-0 pointer-events-none overflow-hidden opacity-30"
             style="background-image: repeating-linear-gradient(transparent, transparent 27px, #d97706 28px);"></div>
        
        <!-- Red margin line -->
        <div class="absolute top-12 bottom-0 w-px bg-red-300 opacity-50" style="left: 40px;"></div>
        
        <!-- Content Area -->
        <div id="notesContent" class="flex-1 overflow-hidden relative">
            <!-- List View -->
            <div id="notesListView" class="h-full flex flex-col">
                <!-- Search and New Note -->
                <div class="p-3 border-b border-amber-200 bg-amber-50/80">
                    <div class="flex items-center space-x-2">
                        <div class="relative flex-1">
                            <input type="text" id="notesSearchInput" placeholder="Search notes..." 
                                   class="w-full pl-8 pr-3 py-2 bg-white border border-amber-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition"
                                   oninput="NotesManager.search(this.value)">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-amber-400 text-sm"></i>
                        </div>
                        <button onclick="NotesManager.newNote()" 
                                class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-medium transition flex items-center space-x-1">
                            <i class="fas fa-plus text-xs"></i>
                            <span>New</span>
                        </button>
                    </div>
                </div>

                <!-- Notes List -->
                <div id="notesList" class="flex-1 overflow-y-auto p-3 space-y-2">
                    <!-- Notes will be rendered here -->
                </div>
                
                <!-- Empty State -->
                <div id="notesEmptyState" class="hidden flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <i class="fas fa-sticky-note text-4xl text-amber-300 mb-3"></i>
                    <p class="text-amber-700 font-medium">No notes yet</p>
                    <p class="text-amber-600 text-sm mt-1">Click "New" to create your first note</p>
                </div>
                
                <!-- No Results State -->
                <div id="notesNoResults" class="hidden flex-1 flex flex-col items-center justify-center p-6 text-center">
                    <i class="fas fa-search text-4xl text-amber-300 mb-3"></i>
                    <p class="text-amber-700 font-medium">No matching notes</p>
                    <p class="text-amber-600 text-sm mt-1">Try a different search term</p>
                </div>
            </div>
            
            <!-- Editor View -->
            <div id="notesEditorView" class="h-full flex flex-col hidden">
                <!-- Editor Header -->
                <div class="p-3 border-b border-amber-200 bg-amber-50/80 flex items-center justify-between">
                    <button onclick="NotesManager.backToList()" 
                            class="text-amber-700 hover:text-amber-900 transition flex items-center space-x-1">
                        <i class="fas fa-arrow-left text-sm"></i>
                        <span class="text-sm">Back</span>
                    </button>
                    <div class="flex items-center space-x-2">
                        <span id="notesSaveStatus" class="text-xs text-amber-600"></span>
                        <button onclick="NotesManager.confirmDelete()" 
                                class="text-red-500 hover:text-red-700 transition p-1" title="Delete note">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Title Input -->
                <div class="px-4 pt-3" style="padding-left: 48px;">
                    <input type="text" id="noteTitleInput" placeholder="Note title..." 
                           class="w-full bg-transparent border-0 text-lg font-semibold text-amber-900 placeholder-amber-400 focus:ring-0 focus:outline-none"
                           oninput="NotesManager.onContentChange()">
                </div>
                
                <!-- Content Textarea -->
                <div class="flex-1 px-4 pb-3" style="padding-left: 48px;">
                    <textarea id="noteContentInput" placeholder="Write your note here..." 
                              class="w-full h-full bg-transparent border-0 text-amber-800 placeholder-amber-400 focus:ring-0 focus:outline-none resize-none text-sm leading-7"
                              style="line-height: 28px;"
                              oninput="NotesManager.onContentChange()"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Resize Handle - only on desktop -->
        <div id="notesResizeHandle" class="hidden md:block absolute bottom-0 right-0 w-4 h-4 cursor-se-resize">
            <svg class="w-full h-full text-amber-400" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22 22H20V20H22V22ZM22 18H18V22H22V18ZM18 22H14V18H18V22ZM22 14H14V22H22V14Z" opacity="0.5"/>
            </svg>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="notesDeleteModal" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="NotesManager.cancelDelete()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl p-6 w-[calc(100%-2rem)] max-w-xs md:w-80">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Note?</h3>
        <p class="text-gray-600 text-sm mb-4">This action cannot be undone.</p>
        <div class="flex justify-end space-x-2">
            <button onclick="NotesManager.cancelDelete()" 
                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition text-sm">
                Cancel
            </button>
            <button onclick="NotesManager.deleteNote()" 
                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition text-sm">
                Delete
            </button>
        </div>
    </div>
</div>

<style>
/* Notes popup specific styles */
#notesPopup {
    min-width: 280px;
    min-height: 200px;
}

@media (min-width: 768px) {
    #notesPopup {
        max-width: 800px;
        max-height: 600px;
    }
}

.notes-container {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(0, 0, 0, 0.05);
}

/* Note card styles */
.note-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(217, 119, 6, 0.2);
    transition: all 0.2s ease;
}

.note-card:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(217, 119, 6, 0.4);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Scrollbar styling for notes */
#notesList::-webkit-scrollbar {
    width: 6px;
}

#notesList::-webkit-scrollbar-track {
    background: transparent;
}

#notesList::-webkit-scrollbar-thumb {
    background: rgba(217, 119, 6, 0.3);
    border-radius: 3px;
}

#notesList::-webkit-scrollbar-thumb:hover {
    background: rgba(217, 119, 6, 0.5);
}

/* Textarea line height to match notepad lines */
#noteContentInput {
    background-image: repeating-linear-gradient(
        transparent,
        transparent 27px,
        rgba(217, 119, 6, 0.1) 28px
    );
}
</style>
