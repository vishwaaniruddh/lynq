/**
 * Global Tools Manager
 * Single FAB with right sidebar containing notes and tasks
 */
class GlobalToolsManager {
    static isOpen = false;
    static notes = '';
    static tasks = [];
    static saveTimeout = null;

    static init() {
        // Load saved data
        this.notes = localStorage.getItem('globalNotes') || '';
        this.tasks = JSON.parse(localStorage.getItem('globalTasks') || '[]');
        
        // Initialize notes
        const textarea = document.getElementById('notesTextarea');
        if (textarea) {
            textarea.value = this.notes;
            
            // Auto-save on input
            textarea.addEventListener('input', () => {
                clearTimeout(this.saveTimeout);
                this.saveTimeout = setTimeout(() => this.autoSaveNotes(), 1000);
            });
        }
        
        // Initialize tasks
        this.renderTasks();
        this.updateCounts();
        
        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closeSidebar();
            }
        });
    }

    static toggleSidebar() {
        console.log('Toggle sidebar called, isOpen:', this.isOpen);
        if (this.isOpen) {
            this.closeSidebar();
        } else {
            this.openSidebar();
        }
    }

    static openSidebar() {
        console.log('Opening sidebar...');
        this.isOpen = true;
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Show overlay first
        const overlay = document.getElementById('toolsOverlay');
        const sidebar = document.getElementById('toolsSidebar');
        const fab = document.getElementById('globalToolsFab');
        
        console.log('Elements found:', { overlay: !!overlay, sidebar: !!sidebar, fab: !!fab });
        
        // Force reflow to ensure initial state
        if (overlay) overlay.offsetHeight;
        
        // Add classes for smooth animation
        if (overlay) overlay.classList.add('active');
        if (fab) fab.classList.add('active');
        
        // Small delay for overlay to start showing, then slide in sidebar
        setTimeout(() => {
            console.log('Adding open class to sidebar');
            if (sidebar) {
                sidebar.classList.add('open');
                console.log('Sidebar classes:', sidebar.className);
            }
        }, 50);
    }

    static closeSidebar() {
        this.isOpen = false;
        
        const overlay = document.getElementById('toolsOverlay');
        const sidebar = document.getElementById('toolsSidebar');
        const fab = document.getElementById('globalToolsFab');
        
        // Remove sidebar first
        sidebar.classList.remove('open');
        fab.classList.remove('active');
        
        // Wait for sidebar animation to complete, then hide overlay
        setTimeout(() => {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }, 300);
        
        this.saveNotes(); // Save notes on close
    }

    // Notes Management
    static saveNotes() {
        const textarea = document.getElementById('notesTextarea');
        if (textarea) {
            this.notes = textarea.value;
            localStorage.setItem('globalNotes', this.notes);
            this.updateNotesStatus('Saved');
        }
    }

    static autoSaveNotes() {
        const textarea = document.getElementById('notesTextarea');
        if (textarea) {
            this.notes = textarea.value;
            localStorage.setItem('globalNotes', this.notes);
            this.updateNotesStatus('Auto-saved');
        }
    }

    static updateNotesStatus(message) {
        const status = document.getElementById('notesStatus');
        if (status) {
            status.textContent = message;
            status.style.color = '#10b981';
            setTimeout(() => {
                status.style.color = '#d97706';
            }, 2000);
        }
    }

    // Tasks Management
    static addTask(event) {
        if (event) event.preventDefault();
        
        const input = document.getElementById('newTaskInput');
        const text = input.value.trim();
        
        if (!text) return;
        
        const task = {
            id: Date.now(),
            text: text,
            completed: false,
            created: new Date().toISOString()
        };
        
        this.tasks.unshift(task);
        this.saveTasks();
        this.renderTasks();
        this.updateCounts();
        input.value = '';
    }

    static toggleTask(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (task) {
            task.completed = !task.completed;
            this.saveTasks();
            this.renderTasks();
            this.updateCounts();
        }
    }

    static deleteTask(taskId) {
        this.tasks = this.tasks.filter(t => t.id !== taskId);
        this.saveTasks();
        this.renderTasks();
        this.updateCounts();
    }

    static clearCompleted() {
        if (confirm('Clear all completed tasks?')) {
            this.tasks = this.tasks.filter(t => !t.completed);
            this.saveTasks();
            this.renderTasks();
            this.updateCounts();
        }
    }

    static saveTasks() {
        localStorage.setItem('globalTasks', JSON.stringify(this.tasks));
    }

    static renderTasks() {
        const container = document.getElementById('tasksList');
        const emptyState = document.getElementById('tasksEmptyState');
        
        if (!container) return;
        
        if (this.tasks.length === 0) {
            container.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }
        
        container.classList.remove('hidden');
        emptyState.classList.add('hidden');
        
        container.innerHTML = this.tasks.map(task => `
            <div class="task-item flex items-center gap-3 p-2 rounded-lg ${task.completed ? 'opacity-60' : ''}">
                <input type="checkbox" class="task-checkbox" ${task.completed ? 'checked' : ''} 
                       onchange="GlobalToolsManager.toggleTask(${task.id})">
                <span class="task-title flex-1 text-sm ${task.completed ? 'line-through text-gray-500' : 'text-gray-800'}">${this.escapeHtml(task.text)}</span>
                <button onclick="GlobalToolsManager.deleteTask(${task.id})" 
                        class="text-red-400 hover:text-red-600 transition p-1" title="Delete">
                    <i class="fas fa-trash text-xs"></i>
                </button>
            </div>
        `).join('');
    }

    static updateCounts() {
        const totalTasks = this.tasks.length;
        const completedTasks = this.tasks.filter(t => t.completed).length;
        const pendingTasks = totalTasks - completedTasks;
        
        // Update task count
        const tasksCount = document.getElementById('tasksCount');
        if (tasksCount) {
            tasksCount.textContent = totalTasks;
        }
        
        // Update completed count
        const tasksCompleted = document.getElementById('tasksCompleted');
        if (tasksCompleted) {
            tasksCompleted.textContent = `${completedTasks} completed`;
        }
        
        // Update pending count
        const tasksPending = document.getElementById('tasksPending');
        if (tasksPending) {
            tasksPending.textContent = `${pendingTasks} pending`;
        }
        
        // Update FAB badge
        const toolsBadge = document.getElementById('toolsBadge');
        const globalToolsFab = document.getElementById('globalToolsFab');
        if (toolsBadge && globalToolsFab) {
            if (pendingTasks > 0) {
                toolsBadge.textContent = pendingTasks;
                toolsBadge.classList.remove('hidden');
                globalToolsFab.classList.add('has-tasks');
            } else {
                toolsBadge.classList.add('hidden');
                globalToolsFab.classList.remove('has-tasks');
            }
        }
        
        // Show/hide clear completed button
        const clearBtn = document.getElementById('clearCompletedBtn');
        if (clearBtn) {
            if (completedTasks > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }
        }
    }

    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    GlobalToolsManager.init();
});