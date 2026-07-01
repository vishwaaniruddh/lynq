/**
 * Tasks Manager Module
 * Handles task checklist functionality with CRUD operations
 */

const TasksManager = {
    // Configuration
    baseUrl: '',
    
    // State
    isOpen: false,
    tasks: [],
    
    /**
     * Initialize the tasks manager
     */
    init: function(baseUrl) {
        this.baseUrl = baseUrl || '';
        this.loadTasks();
    },
    
    /**
     * Toggle popup visibility
     */
    toggle: function() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    },
    
    /**
     * Open the tasks popup
     */
    open: function() {
        const popup = document.getElementById('tasksPopup');
        if (popup) {
            popup.classList.remove('hidden');
            this.isOpen = true;
            this.loadTasks();
            document.getElementById('newTaskInput').focus();
        }
    },
    
    /**
     * Close the tasks popup
     */
    close: function() {
        const popup = document.getElementById('tasksPopup');
        if (popup) {
            popup.classList.add('hidden');
            this.isOpen = false;
        }
    },
    
    /**
     * Load user's tasks from API
     */
    loadTasks: async function() {
        this.showLoading(true);
        
        try {
            const response = await fetch(this.baseUrl + '/api/tasks/list.php');
            const data = await response.json();
            
            if (data.success) {
                this.tasks = data.data || [];
                this.renderTasksList();
                this.updateBadge();
            } else {
                console.error('Failed to load tasks:', data.message);
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
        } finally {
            this.showLoading(false);
        }
    },
    
    /**
     * Show/hide loading state
     */
    showLoading: function(show) {
        const loading = document.getElementById('tasksLoading');
        const list = document.getElementById('tasksList');
        const empty = document.getElementById('tasksEmptyState');
        
        if (show) {
            loading.classList.remove('hidden');
            list.classList.add('hidden');
            empty.classList.add('hidden');
        } else {
            loading.classList.add('hidden');
        }
    },

    /**
     * Render the tasks list
     */
    renderTasksList: function() {
        const listContainer = document.getElementById('tasksList');
        const emptyState = document.getElementById('tasksEmptyState');
        const footer = document.getElementById('tasksFooter');
        
        if (!listContainer) return;
        
        // Show appropriate state
        if (this.tasks.length === 0) {
            listContainer.classList.add('hidden');
            emptyState.classList.remove('hidden');
            footer.classList.add('hidden');
            return;
        }
        
        listContainer.classList.remove('hidden');
        emptyState.classList.add('hidden');
        footer.classList.remove('hidden');
        
        // Count stats
        const completedCount = this.tasks.filter(t => t.is_completed == 1).length;
        const pendingCount = this.tasks.length - completedCount;
        
        // Update footer
        document.getElementById('tasksCount').textContent = pendingCount;
        document.getElementById('tasksCompleted').textContent = `${completedCount} completed`;
        
        const clearBtn = document.getElementById('clearCompletedBtn');
        if (completedCount > 0) {
            clearBtn.classList.remove('hidden');
        } else {
            clearBtn.classList.add('hidden');
        }
        
        // Render task items
        let html = '';
        this.tasks.forEach(task => {
            const isCompleted = task.is_completed == 1;
            html += `
                <div class="task-item ${isCompleted ? 'completed' : ''} flex items-start space-x-3 p-2 rounded-lg group" data-task-id="${task.id}">
                    <input type="checkbox" 
                           class="task-checkbox mt-0.5" 
                           ${isCompleted ? 'checked' : ''} 
                           onchange="TasksManager.toggleTask(${task.id})">
                    <div class="flex-1 min-w-0">
                        <p class="task-title text-sm text-gray-800 ${isCompleted ? 'line-through text-gray-400' : ''}">${this.escapeHtml(task.title)}</p>
                        ${task.description ? `<p class="text-xs text-gray-400 mt-0.5 truncate">${this.escapeHtml(task.description)}</p>` : ''}
                    </div>
                    <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition">
                        <button onclick="TasksManager.openEditModal(${task.id})" class="p-1 text-gray-400 hover:text-indigo-500 transition" title="Edit">
                            <i class="fas fa-pen text-xs"></i>
                        </button>
                        <button onclick="TasksManager.openDeleteModal(${task.id})" class="p-1 text-gray-400 hover:text-red-500 transition" title="Delete">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        listContainer.innerHTML = html;
    },
    
    /**
     * Update the FAB badge with pending task count
     */
    updateBadge: function() {
        const badge = document.getElementById('tasksFabBadge');
        const fab = document.getElementById('tasksFab');
        const pendingCount = this.tasks.filter(t => t.is_completed == 0).length;
        
        if (pendingCount > 0) {
            badge.textContent = pendingCount > 9 ? '9+' : pendingCount;
            badge.classList.remove('hidden');
            fab.classList.add('has-tasks');
        } else {
            badge.classList.add('hidden');
            fab.classList.remove('has-tasks');
        }
    },
    
    /**
     * Add a new task
     */
    addTask: async function(event) {
        event.preventDefault();
        
        const input = document.getElementById('newTaskInput');
        const title = input.value.trim();
        
        if (!title) return;
        
        try {
            const response = await fetch(this.baseUrl + '/api/tasks/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title: title })
            });
            
            const data = await response.json();
            
            if (data.success) {
                input.value = '';
                this.loadTasks();
            } else {
                alert(data.message || 'Failed to create task');
            }
        } catch (error) {
            console.error('Error creating task:', error);
            alert('Failed to create task');
        }
    },
    
    /**
     * Toggle task completion
     */
    toggleTask: async function(taskId) {
        try {
            const response = await fetch(this.baseUrl + '/api/tasks/toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: taskId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update local state
                const task = this.tasks.find(t => t.id == taskId);
                if (task) {
                    task.is_completed = task.is_completed == 1 ? 0 : 1;
                }
                this.renderTasksList();
                this.updateBadge();
            } else {
                console.error('Failed to toggle task:', data.message);
                this.loadTasks(); // Reload to sync state
            }
        } catch (error) {
            console.error('Error toggling task:', error);
            this.loadTasks();
        }
    },
    
    /**
     * Open edit modal
     */
    openEditModal: function(taskId) {
        const task = this.tasks.find(t => t.id == taskId);
        if (!task) return;
        
        document.getElementById('editTaskId').value = taskId;
        document.getElementById('editTaskTitle').value = task.title || '';
        document.getElementById('editTaskDescription').value = task.description || '';
        document.getElementById('taskEditModal').classList.remove('hidden');
        document.getElementById('editTaskTitle').focus();
    },
    
    /**
     * Close edit modal
     */
    closeEditModal: function() {
        document.getElementById('taskEditModal').classList.add('hidden');
    },
    
    /**
     * Save edited task
     */
    saveEdit: async function() {
        const taskId = document.getElementById('editTaskId').value;
        const title = document.getElementById('editTaskTitle').value.trim();
        const description = document.getElementById('editTaskDescription').value.trim();
        
        if (!title) {
            alert('Title is required');
            return;
        }
        
        try {
            const response = await fetch(this.baseUrl + '/api/tasks/update.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: parseInt(taskId),
                    title: title,
                    description: description || null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.closeEditModal();
                this.loadTasks();
            } else {
                alert(data.message || 'Failed to update task');
            }
        } catch (error) {
            console.error('Error updating task:', error);
            alert('Failed to update task');
        }
    },
    
    /**
     * Open delete confirmation modal
     */
    openDeleteModal: function(taskId) {
        document.getElementById('deleteTaskId').value = taskId;
        document.getElementById('taskDeleteModal').classList.remove('hidden');
    },
    
    /**
     * Close delete modal
     */
    closeDeleteModal: function() {
        document.getElementById('taskDeleteModal').classList.add('hidden');
    },
    
    /**
     * Confirm and delete task
     */
    confirmDelete: async function() {
        const taskId = document.getElementById('deleteTaskId').value;
        
        try {
            const response = await fetch(this.baseUrl + '/api/tasks/delete.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(taskId) })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.closeDeleteModal();
                this.loadTasks();
            } else {
                alert(data.message || 'Failed to delete task');
            }
        } catch (error) {
            console.error('Error deleting task:', error);
            alert('Failed to delete task');
        }
    },
    
    /**
     * Clear all completed tasks
     */
    clearCompleted: async function() {
        const completedTasks = this.tasks.filter(t => t.is_completed == 1);
        
        if (completedTasks.length === 0) return;
        
        if (!confirm(`Delete ${completedTasks.length} completed task(s)?`)) return;
        
        try {
            for (const task of completedTasks) {
                await fetch(this.baseUrl + '/api/tasks/delete.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: task.id })
                });
            }
            this.loadTasks();
        } catch (error) {
            console.error('Error clearing completed tasks:', error);
            this.loadTasks();
        }
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
    const baseUrl = window.baseUrl || '';
    TasksManager.init(baseUrl);
});
