/**
 * ADV Clarity Management System - Offline Form Handler
 * Specific utilities for handling form submissions when offline
 */

class OfflineFormHandler {
    constructor() {
        this.init();
    }

    /**
     * Initialize offline form handler
     */
    init() {
        this.setupFormInterception();
        this.setupFormRestoration();
        this.addOfflineIndicators();
    }

    /**
     * Set up form interception for offline scenarios
     */
    setupFormInterception() {
        // Intercept all form submissions
        document.addEventListener('submit', (event) => {
            const form = event.target;
            
            // Skip if not a modifying form or if online
            if (!this.isModifyingForm(form) || navigator.onLine) {
                return;
            }
            
            event.preventDefault();
            this.handleOfflineSubmission(form);
        });
    }

    /**
     * Check if form is a modifying operation
     */
    isModifyingForm(form) {
        const method = (form.method || 'GET').toUpperCase();
        return ['POST', 'PUT', 'DELETE'].includes(method);
    }

    /**
     * Handle form submission when offline
     */
    handleOfflineSubmission(form) {
        const formData = this.extractFormData(form);
        const action = this.createOfflineAction(form, formData);
        
        // Queue the action
        if (window.offlineUtils) {
            window.offlineUtils.queueOfflineAction(action);
        }
        
        // Save form data for restoration
        this.saveFormData(form, formData);
        
        // Show user feedback
        this.showOfflineSubmissionFeedback(form, action);
        
        // Clear form if requested
        if (form.dataset.clearOnOfflineSubmit !== 'false') {
            this.clearForm(form);
        }
    }

    /**
     * Extract form data
     */
    extractFormData(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                // Handle multiple values (checkboxes, multiple selects)
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    }

    /**
     * Create offline action from form
     */
    createOfflineAction(form, formData) {
        return {
            id: this.generateId(),
            type: 'form_submission',
            endpoint: form.action || window.location.pathname,
            method: form.method || 'POST',
            data: formData,
            timestamp: Date.now(),
            formId: form.id || null,
            formName: form.name || null,
            formTitle: this.getFormTitle(form)
        };
    }

    /**
     * Get form title for display purposes
     */
    getFormTitle(form) {
        // Try to find a title from various sources
        const titleElement = form.querySelector('h1, h2, h3, .form-title, [data-form-title]');
        if (titleElement) {
            return titleElement.textContent.trim();
        }
        
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) {
            return submitButton.textContent.trim() || submitButton.value;
        }
        
        return form.name || form.id || 'Form Submission';
    }

    /**
     * Save form data for restoration
     */
    saveFormData(form, data) {
        const formId = this.getFormIdentifier(form);
        const savedForms = this.getSavedFormData();
        
        savedForms[formId] = {
            data: data,
            timestamp: Date.now(),
            url: window.location.pathname,
            title: this.getFormTitle(form)
        };
        
        try {
            localStorage.setItem('clarity-offline-forms', JSON.stringify(savedForms));
        } catch (error) {
            console.error('[OfflineForm] Failed to save form data:', error);
        }
    }

    /**
     * Get form identifier
     */
    getFormIdentifier(form) {
        return form.id || form.name || form.action || 'anonymous-form';
    }

    /**
     * Get saved form data
     */
    getSavedFormData() {
        try {
            const saved = localStorage.getItem('clarity-offline-forms');
            return saved ? JSON.parse(saved) : {};
        } catch (error) {
            console.error('[OfflineForm] Failed to load saved forms:', error);
            return {};
        }
    }

    /**
     * Show offline submission feedback
     */
    showOfflineSubmissionFeedback(form, action) {
        // Create feedback overlay
        const overlay = document.createElement('div');
        overlay.className = 'offline-form-feedback';
        overlay.innerHTML = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-md mx-4 shadow-xl">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-wifi-slash text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Form Saved Offline</h3>
                            <p class="text-sm text-gray-600">You're currently offline</p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">
                            Your form "${action.formTitle}" has been saved and will be submitted automatically when you're back online.
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded p-3">
                            <div class="flex items-center text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span>Queued at ${new Date(action.timestamp).toLocaleTimeString()}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button onclick="this.closest('.offline-form-feedback').remove()" 
                                class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400 transition">
                            OK
                        </button>
                        <button onclick="offlineFormHandler.showQueuedForms()" 
                                class="flex-1 bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
                            View Queue
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.remove();
            }
        }, 10000);
    }

    /**
     * Clear form fields
     */
    clearForm(form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            } else if (input.type !== 'hidden' && input.type !== 'submit' && input.type !== 'button') {
                input.value = '';
            }
        });
    }

    /**
     * Set up form restoration
     */
    setupFormRestoration() {
        // Check for saved forms on page load
        document.addEventListener('DOMContentLoaded', () => {
            this.checkForSavedForms();
        });
    }

    /**
     * Check for saved forms and offer restoration
     */
    checkForSavedForms() {
        const savedForms = this.getSavedFormData();
        const currentUrl = window.location.pathname;
        
        // Find forms that match current page
        const matchingForms = Object.entries(savedForms).filter(([formId, formData]) => {
            return formData.url === currentUrl;
        });
        
        if (matchingForms.length > 0) {
            this.showFormRestorationPrompt(matchingForms);
        }
    }

    /**
     * Show form restoration prompt
     */
    showFormRestorationPrompt(matchingForms) {
        const modal = document.createElement('div');
        modal.className = 'form-restoration-modal';
        modal.innerHTML = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-lg mx-4 shadow-xl">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-history text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Restore Saved Forms</h3>
                            <p class="text-sm text-gray-600">We found ${matchingForms.length} saved form${matchingForms.length !== 1 ? 's' : ''}</p>
                        </div>
                    </div>
                    
                    <div class="mb-4 max-h-48 overflow-y-auto">
                        ${matchingForms.map(([formId, formData]) => `
                            <div class="border rounded p-3 mb-2 last:mb-0">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900">${formData.title}</div>
                                        <div class="text-sm text-gray-600">Saved: ${new Date(formData.timestamp).toLocaleString()}</div>
                                    </div>
                                    <button onclick="offlineFormHandler.restoreForm('${formId}')" 
                                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition">
                                        Restore
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="flex space-x-3">
                        <button onclick="this.closest('.form-restoration-modal').remove()" 
                                class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400 transition">
                            Dismiss
                        </button>
                        <button onclick="offlineFormHandler.clearSavedForms(); this.closest('.form-restoration-modal').remove()" 
                                class="flex-1 bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700 transition">
                            Clear All
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }

    /**
     * Restore form data
     */
    restoreForm(formId) {
        const savedForms = this.getSavedFormData();
        const formData = savedForms[formId];
        
        if (!formData) {
            return;
        }
        
        // Find the form to restore
        const form = document.getElementById(formId) || 
                    document.querySelector(`form[name="${formId}"]`) ||
                    document.querySelector(`form[action*="${formId}"]`) ||
                    document.querySelector('form'); // Fallback to first form
        
        if (form) {
            this.populateForm(form, formData.data);
            this.showRestorationNotice(form, formData.timestamp);
            
            // Remove from saved forms
            delete savedForms[formId];
            localStorage.setItem('clarity-offline-forms', JSON.stringify(savedForms));
        }
        
        // Close modal
        const modal = document.querySelector('.form-restoration-modal');
        if (modal) {
            modal.remove();
        }
    }

    /**
     * Populate form with data
     */
    populateForm(form, data) {
        Object.entries(data).forEach(([name, value]) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (field) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    if (Array.isArray(value)) {
                        field.checked = value.includes(field.value);
                    } else {
                        field.checked = field.value === value;
                    }
                } else if (field.tagName === 'SELECT') {
                    field.value = value;
                } else {
                    field.value = value;
                }
            }
        });
    }

    /**
     * Show restoration notice
     */
    showRestorationNotice(form, timestamp) {
        const notice = document.createElement('div');
        notice.className = 'form-restoration-notice mb-4';
        notice.innerHTML = `
            <div class="bg-green-50 border border-green-200 rounded p-3">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800 text-sm">
                        Form data restored from ${new Date(timestamp).toLocaleString()}
                    </span>
                    <button onclick="this.closest('.form-restoration-notice').remove()" 
                            class="ml-auto text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        form.insertBefore(notice, form.firstChild);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.remove();
            }
        }, 10000);
    }

    /**
     * Add offline indicators to forms
     */
    addOfflineIndicators() {
        // Add indicators when connection status changes
        window.addEventListener('pwa-connection-change', (event) => {
            this.updateFormIndicators(event.detail.isOnline);
        });
        
        // Initial setup
        this.updateFormIndicators(navigator.onLine);
    }

    /**
     * Update form indicators based on connection status
     */
    updateFormIndicators(isOnline) {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            if (this.isModifyingForm(form)) {
                if (isOnline) {
                    this.removeOfflineIndicator(form);
                } else {
                    this.addOfflineIndicator(form);
                }
            }
        });
    }

    /**
     * Add offline indicator to form
     */
    addOfflineIndicator(form) {
        // Remove existing indicator
        this.removeOfflineIndicator(form);
        
        const indicator = document.createElement('div');
        indicator.className = 'offline-form-indicator';
        indicator.innerHTML = `
            <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-wifi-slash text-yellow-600 mr-2"></i>
                    <span class="text-yellow-800 text-sm">
                        You're offline. Form submissions will be queued for later.
                    </span>
                </div>
            </div>
        `;
        
        form.insertBefore(indicator, form.firstChild);
    }

    /**
     * Remove offline indicator from form
     */
    removeOfflineIndicator(form) {
        const indicator = form.querySelector('.offline-form-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    /**
     * Show queued forms
     */
    showQueuedForms() {
        if (window.offlineUtils) {
            window.offlineUtils.showQueueDetails();
        }
    }

    /**
     * Clear saved forms
     */
    clearSavedForms() {
        localStorage.removeItem('clarity-offline-forms');
    }

    /**
     * Generate unique ID
     */
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Get form statistics
     */
    getFormStats() {
        const savedForms = this.getSavedFormData();
        return {
            savedForms: Object.keys(savedForms).length,
            totalSize: JSON.stringify(savedForms).length
        };
    }
}

// Initialize offline form handler when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.offlineFormHandler = new OfflineFormHandler();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineFormHandler;
}