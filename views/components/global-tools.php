<?php
/**
 * Global Tools Component
 * Single FAB with right sidebar containing notes and tasks
 */
?>

<!-- Single Global Tools FAB -->
<button id="globalToolsFab" onclick="GlobalToolsManager.toggleSidebar()" 
        class="fixed bottom-6 right-6 z-40 w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center group"
        title="Quick Tools">
    <i class="fas fa-tools text-xl group-hover:scale-110 transition-transform"></i>
    <span id="toolsBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center hidden">0</span>
</button>

<!-- Tools Overlay -->
<div id="toolsOverlay" class="fixed inset-0 bg-black/30 z-50 opacity-0 invisible transition-all duration-300" onclick="GlobalToolsManager.closeSidebar()"></div>

<!-- Right Tools Sidebar -->
<div id="toolsSidebar" class="fixed right-0 top-0 w-96 h-full bg-white shadow-2xl z-50 flex flex-col">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <i class="fas fa-tools text-white"></i>
            <span class="font-semibold text-white">Quick Tools</span>
        </div>
        <button onclick="GlobalToolsManager.closeSidebar()" class="text-white/80 hover:text-white transition p-1">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto">
        <!-- Notes Section -->
        <div class="border-b border-gray-200">
            <div class="p-4 bg-amber-50 border-l-4 border-amber-400">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-amber-800 flex items-center">
                        <i class="fas fa-sticky-note mr-2"></i>Quick Notes
                    </h3>
                    <span id="notesStatus" class="text-xs text-amber-600">Auto-saved</span>
                </div>
                <textarea id="notesTextarea" placeholder="Write your notes here..." 
                          class="w-full h-32 p-3 border border-amber-200 rounded-lg text-sm resize-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition"></textarea>
                <div class="mt-2 flex justify-end">
                    <button onclick="GlobalToolsManager.saveNotes()" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded text-xs transition">
                        Save
                    </button>
                </div>
            </div>
        </div>

        <!-- Tasks Section -->
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-indigo-800 flex items-center">
                    <i class="fas fa-tasks mr-2"></i>Task List
                    <span id="tasksCount" class="ml-2 text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">0</span>
                </h3>
                <button onclick="GlobalToolsManager.clearCompleted()" id="clearCompletedBtn" class="text-xs text-red-500 hover:text-red-600 transition hidden">
                    Clear completed
                </button>
            </div>
            
            <!-- Add Task Form -->
            <form id="addTaskForm" onsubmit="GlobalToolsManager.addTask(event)" class="flex items-center space-x-2 mb-4">
                <input type="text" id="newTaskInput" placeholder="Add a new task..." 
                       class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition"
                       maxlength="255">
                <button type="submit" 
                        class="px-3 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm transition">
                    <i class="fas fa-plus"></i>
                </button>
            </form>

            <!-- Tasks List -->
            <div id="tasksList" class="space-y-2 max-h-64 overflow-y-auto">
                <!-- Tasks will be rendered here -->
            </div>
            
            <!-- Empty State -->
            <div id="tasksEmptyState" class="hidden text-center py-8">
                <i class="fas fa-clipboard-check text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500 font-medium">No tasks yet</p>
                <p class="text-gray-400 text-sm">Add your first task above</p>
            </div>
        </div>

        <!-- Footer Stats -->
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
            <div class="flex justify-between items-center text-xs text-gray-500">
                <span id="tasksCompleted">0 completed</span>
                <span id="tasksPending">0 pending</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Global Tools Styles */
#globalToolsFab {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
}

#globalToolsFab.active {
    transform: rotate(45deg);
}

#toolsOverlay {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: opacity;
}

#toolsOverlay.active {
    opacity: 1;
    visibility: visible;
}

#toolsSidebar {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
    transform: translate3d(100%, 0, 0) !important;
}

#toolsSidebar.open {
    transform: translate3d(0, 0, 0) !important;
}

/* Task item styles */
.task-item {
    transition: all 0.2s ease;
}

.task-item:hover {
    background: #f9fafb;
}

.task-item.completed .task-title {
    text-decoration: line-through;
    color: #9ca3af;
}

/* Custom checkbox */
.task-checkbox {
    appearance: none;
    width: 18px;
    height: 18px;
    border: 2px solid #d1d5db;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.task-checkbox:checked {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border-color: #6366f1;
}

.task-checkbox:checked::after {
    content: '✓';
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    font-weight: bold;
}

/* Scrollbar styling */
#tasksList::-webkit-scrollbar {
    width: 4px;
}

#tasksList::-webkit-scrollbar-track {
    background: transparent;
}

#tasksList::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 2px;
}

#tasksList::-webkit-scrollbar-thumb:hover {
    background: #d1d5db;
}

/* FAB pulse animation for pending tasks */
@keyframes fabPulse {
    0%, 100% { box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4); }
    50% { box-shadow: 0 4px 25px rgba(99, 102, 241, 0.6); }
}

#globalToolsFab.has-tasks {
    animation: fabPulse 2s ease-in-out infinite;
}

/* Mobile responsive */
@media (max-width: 768px) {
    #toolsSidebar {
        width: 100%;
    }
    
    #globalToolsFab {
        bottom: 1rem;
        right: 1rem;
        width: 3rem;
        height: 3rem;
    }
    
    #globalToolsFab i {
        font-size: 1rem;
    }
}
</style>