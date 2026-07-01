// ADV CRM - Main JavaScript Application

const CRM = {
    baseUrl: '',
    csrfToken: '',

    init(baseUrl, csrfToken) {
        this.baseUrl = baseUrl;
        this.csrfToken = csrfToken;
    },

    // AJAX Helper
    async ajax(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const config = { ...defaults, ...options };
        
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(this.baseUrl + url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error?.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('AJAX Error:', error);
            throw error;
        }
    },

    // GET request
    get(url) {
        return this.ajax(url);
    },

    // POST request
    post(url, data) {
        return this.ajax(url, { method: 'POST', body: data });
    },

    // PUT request
    put(url, data) {
        return this.ajax(url, { method: 'PUT', body: data });
    },

    // DELETE request
    delete(url) {
        return this.ajax(url, { method: 'DELETE' });
    },

    // Show alert
    showAlert(message, type = 'success') {
        const alertDiv = document.getElementById('ajax-alert');
        const colors = {
            success: 'bg-green-100 border-green-400 text-green-700',
            error: 'bg-red-100 border-red-400 text-red-700',
            warning: 'bg-yellow-100 border-yellow-400 text-yellow-700',
            info: 'bg-blue-100 border-blue-400 text-blue-700'
        };
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        alertDiv.innerHTML = `
            <div class="${colors[type]} border px-4 py-3 rounded shadow-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas ${icons[type]} mr-2"></i>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.parentElement.classList.add('hidden')" class="ml-4">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        alertDiv.classList.remove('hidden');

        setTimeout(() => alertDiv.classList.add('hidden'), 5000);
    },

    // Form validation
    validateForm(form) {
        const errors = [];
        const inputs = form.querySelectorAll('[required]');
        
        inputs.forEach(input => {
            input.classList.remove('border-red-500');
            const errorSpan = input.parentElement.querySelector('.error-message');
            if (errorSpan) errorSpan.remove();

            if (!input.value.trim()) {
                errors.push({ field: input.name, message: `${input.dataset.label || input.name} is required` });
                input.classList.add('border-red-500');
                this.showFieldError(input, 'This field is required');
            } else if (input.type === 'email' && !this.isValidEmail(input.value)) {
                errors.push({ field: input.name, message: 'Invalid email format' });
                input.classList.add('border-red-500');
                this.showFieldError(input, 'Invalid email format');
            }
        });

        return errors;
    },

    showFieldError(input, message) {
        const span = document.createElement('span');
        span.className = 'error-message text-red-500 text-xs mt-1';
        span.textContent = message;
        input.parentElement.appendChild(span);
    },

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },

    // Loading state
    setLoading(element, loading = true) {
        if (loading) {
            element.disabled = true;
            element.dataset.originalText = element.innerHTML;
            element.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
        } else {
            element.disabled = false;
            element.innerHTML = element.dataset.originalText;
        }
    },

    // Format date
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    },

    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// DataTable helper
class DataTable {
    constructor(tableId, options = {}) {
        this.table = document.getElementById(tableId);
        this.tbody = this.table.querySelector('tbody');
        this.options = {
            perPage: options.perPage || 10,
            searchable: options.searchable !== false,
            sortable: options.sortable !== false,
            ...options
        };
        this.data = [];
        this.filteredData = [];
        this.currentPage = 1;
        this.sortColumn = null;
        this.sortDirection = 'asc';
        
        this.init();
    }

    init() {
        if (this.options.searchable) this.setupSearch();
        if (this.options.sortable) this.setupSort();
        this.setupPagination();
    }

    setData(data) {
        this.data = data;
        this.filteredData = [...data];
        this.currentPage = 1;
        this.render();
    }

    setupSearch() {
        const searchInput = document.getElementById(this.table.id + '-search');
        if (searchInput) {
            searchInput.addEventListener('input', CRM.debounce((e) => {
                this.search(e.target.value);
            }, 300));
        }
    }

    search(query) {
        query = query.toLowerCase();
        this.filteredData = this.data.filter(row => 
            Object.values(row).some(val => 
                String(val).toLowerCase().includes(query)
            )
        );
        this.currentPage = 1;
        this.render();
    }

    setupSort() {
        this.table.querySelectorAll('th[data-sort]').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => this.sort(th.dataset.sort));
        });
    }

    sort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }

        this.filteredData.sort((a, b) => {
            let valA = a[column], valB = b[column];
            if (typeof valA === 'string') valA = valA.toLowerCase();
            if (typeof valB === 'string') valB = valB.toLowerCase();
            
            if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        this.render();
    }

    setupPagination() {
        // Pagination will be rendered in render()
    }

    render() {
        const start = (this.currentPage - 1) * this.options.perPage;
        const end = start + this.options.perPage;
        const pageData = this.filteredData.slice(start, end);

        this.tbody.innerHTML = pageData.length ? 
            pageData.map(row => this.options.renderRow(row)).join('') :
            `<tr><td colspan="100" class="text-center py-8 text-gray-500">No data found</td></tr>`;

        this.renderPagination();
    }

    renderPagination() {
        const totalPages = Math.ceil(this.filteredData.length / this.options.perPage);
        const paginationDiv = document.getElementById(this.table.id + '-pagination');
        
        if (!paginationDiv) return;

        let html = `<div class="flex items-center justify-between">
            <span class="text-sm text-gray-600">
                Showing ${((this.currentPage - 1) * this.options.perPage) + 1} to 
                ${Math.min(this.currentPage * this.options.perPage, this.filteredData.length)} 
                of ${this.filteredData.length} entries
            </span>
            <div class="flex space-x-1">`;

        for (let i = 1; i <= totalPages; i++) {
            html += `<button onclick="window.dataTables['${this.table.id}'].goToPage(${i})" 
                class="px-3 py-1 rounded ${i === this.currentPage ? 'bg-primary text-white' : 'bg-gray-200 hover:bg-gray-300'}">${i}</button>`;
        }

        html += '</div></div>';
        paginationDiv.innerHTML = html;
    }

    goToPage(page) {
        this.currentPage = page;
        this.render();
    }
}

window.dataTables = {};
