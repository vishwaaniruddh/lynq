<?php
/**
 * Tasks Floating Action Button & Popup Component
 * Fixed bottom-right floating button with task checklist popup
 */
?>
<!-- Tasks FAB (Floating Action Button) -->
<button id="tasksFab" onclick="TasksManager.toggle()" 
        class="fixed bottom-4 right-4 md:bottom-6 md:right-6 z-40 w-12 h-12 md:w-14 md:h-14 bg-gradient-to-br from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center group"
        title="My Tasks">
    <i class="fas fa-tasks text-lg md:text-xl group-hover:scale-110 transition-transform"></i>
    <!-- Task count badge -->
    <span id="tasksFabBadge" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center hidden">0</span>
</button>

<!-- Tasks Popup Container -->
<div id="tasksPopup" class="fixed z-50 hidden inset-4 md:inset-auto md:bottom-[90px] md:right-6 md:w-[380px] md:max-h-[500px]">
    <div class="tasks-container bg-white rounded-xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden h-full md:h-auto md:max-h-[500px]">
        
        <!-- Header -->
        <div class="tasks-header bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fas fa-tasks text-white"></i>
                <span class="font-semibold text-white">My Tasks</span>
                <span id="tasksCount" class="text-xs bg-white/20 text-white px-2 py-0.5 rounded-full">0</span>
            </div>
            <button onclick="TasksManager.close()" class="text-white/80 hover:text-white transition p-1">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Add Task Input -->
        <div class="p-3 border-b border-gray-100 bg-gray-50">
            <form id="addTaskForm" onsubmit="TasksManager.addTask(event)" class="flex items-center space-x-2">
                <input type="text" id="newTaskInput" placeholder="Add a new task..." 
                       class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 transition"
                       maxlength="255">
                <button type="submit" 
                        class="px-3 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium transition flex items-center">
                    <i class="fas fa-plus"></i>
                </button>
            </form>
        </div>

        <!-- Tasks List -->
        <div id="tasksList" class="flex-1 overflow-y-auto p-3 space-y-2">
            <!-- Tasks will be rendered here -->
        </div>
        
        <!-- Empty State -->
        <div id="tasksEmptyState" class="hidden flex-1 flex flex-col items-center justify-center p-6 text-center">
            <i class="fas fa-clipboard-check text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 font-medium">No tasks yet</p>
            <p class="text-gray-400 text-sm mt-1">Add your first task above</p>
        </div>
        
        <!-- Loading State -->
        <div id="tasksLoading" class="hidden flex-1 flex items-center justify-center p-6">
            <i class="fas fa-spinner fa-spin text-2xl text-indigo-500"></i>
        </div>
        
        <!-- Footer with stats -->
        <div id="tasksFooter" class="px-4 py-2 border-t border-gray-100 bg-gray-50 flex justify-between items-center text-xs text-gray-500">
            <span id="tasksCompleted">0 completed</span>
            <button onclick="TasksManager.clearCompleted()" id="clearCompletedBtn" class="text-red-500 hover:text-red-600 transition hidden">
                Clear completed
            </button>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div id="taskEditModal" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="TasksManager.closeEditModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl p-5 w-[calc(100%-2rem)] max-w-sm md:w-96">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Task</h3>
        <input type="hidden" id="editTaskId">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" id="editTaskTitle" maxlength="255"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                <textarea id="editTaskDescription" rows="3" maxlength="1000"
                          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 resize-none"></textarea>
            </div>
        </div>
        <div class="flex justify-end space-x-2 mt-4">
            <button onclick="TasksManager.closeEditModal()" 
                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition text-sm">
                Cancel
            </button>
            <button onclick="TasksManager.saveEdit()" 
                    class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition text-sm">
                Save
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="taskDeleteModal" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-black/50" onclick="TasksManager.closeDeleteModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl p-5 w-80">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Task?</h3>
        <p class="text-gray-600 text-sm mb-4">This action cannot be undone.</p>
        <input type="hidden" id="deleteTaskId">
        <div class="flex justify-end space-x-2">
            <button onclick="TasksManager.closeDeleteModal()" 
                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition text-sm">
                Cancel
            </button>
            <button onclick="TasksManager.confirmDelete()" 
                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition text-sm">
                Delete
            </button>
        </div>
    </div>
</div>

<style>
/* Tasks popup styles */
.tasks-container {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
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
    width: 20px;
    height: 20px;
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
    font-size: 12px;
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

#tasksFab.has-tasks {
    animation: fabPulse 2s ease-in-out infinite;
}
</style>
