/**
 * OU User Utility Functions
 * Common utility functions for OU user management
 */

class OuUserUtils {
    /**
     * Parse AD whenCreated timestamp
     * @param {string} value - AD timestamp string
     * @returns {number} - Unix timestamp
     */
    static parseAdWhenCreated(value) {
        if (!value) return 0;
        const m = String(value).match(/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/);
        if (!m) return 0;
        const [_, y, mo, d, h, mi, s] = m;
        const dt = new Date(Date.UTC(Number(y), Number(mo) - 1, Number(d), Number(h), Number(mi), Number(s)));
        return dt.getTime();
    }

    /**
     * Format user display name with fallback
     * @param {string} displayname - Display name
     * @param {string} username - Username fallback
     * @returns {string} - Formatted display name
     */
    static formatDisplayName(displayname, username) {
        return displayname || username || 'ไม่ระบุ';
    }

    /**
     * Create pagination button element
     * @param {string} label - Button label
     * @param {number} target - Target page
     * @param {boolean} disabled - Whether button is disabled
     * @param {boolean} active - Whether button is active
     * @param {Function} onClick - Click handler
     * @returns {HTMLElement} - Button element
     */
    static createPaginationButton(label, target, disabled = false, active = false, onClick = null) {
        const a = document.createElement('a');
        a.href = '#';
        a.className = `page-link${active ? ' active' : ''}`;
        a.textContent = label;
        
        const li = document.createElement('li');
        li.className = `page-item${disabled ? ' disabled' : ''}`;
        li.appendChild(a);
        
        if (!disabled && onClick) {
            a.addEventListener('click', (e) => { 
                e.preventDefault(); 
                onClick(target); 
            });
        }
        
        return li;
    }

    /**
     * Debounce function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} - Debounced function
     */
    static debounce(func, wait) {
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

    /**
     * Throttle function calls
     * @param {Function} func - Function to throttle
     * @param {number} limit - Time limit in milliseconds
     * @returns {Function} - Throttled function
     */
    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Sanitize HTML content
     * @param {string} str - String to sanitize
     * @returns {string} - Sanitized string
     */
    static sanitizeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Get CSRF token from meta tag
     * @returns {string} - CSRF token
     */
    static getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /**
     * Get CSRF param from meta tag
     * @returns {string} - CSRF param
     */
    static getCsrfParam() {
        const meta = document.querySelector('meta[name="csrf-param"]');
        return meta ? meta.content : '';
    }

    /**
     * Create FormData with CSRF token
     * @param {Object} data - Data to include
     * @returns {FormData} - FormData with CSRF token
     */
    static createFormDataWithCsrf(data = {}) {
        const formData = new FormData();
        
        // Add CSRF token
        const csrfParam = this.getCsrfParam();
        const csrfToken = this.getCsrfToken();
        if (csrfParam && csrfToken) {
            formData.append(csrfParam, csrfToken);
        }
        
        // Add other data
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });
        
        return formData;
    }

    /**
     * Show loading state on element
     * @param {HTMLElement} element - Element to show loading on
     * @param {string} text - Loading text
     */
    static showLoading(element, text = 'Loading...') {
        if (!element) return;
        
        element.dataset.originalContent = element.innerHTML;
        element.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
        element.disabled = true;
        element.style.opacity = '0.6';
    }

    /**
     * Hide loading state on element
     * @param {HTMLElement} element - Element to hide loading on
     */
    static hideLoading(element) {
        if (!element) return;
        
        if (element.dataset.originalContent) {
            element.innerHTML = element.dataset.originalContent;
            delete element.dataset.originalContent;
        }
        
        element.disabled = false;
        element.style.opacity = '1';
    }

    /**
     * Validate email format
     * @param {string} email - Email to validate
     * @returns {boolean} - Whether email is valid
     */
    static isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Validate username format (alphanumeric and underscore only)
     * @param {string} username - Username to validate
     * @returns {boolean} - Whether username is valid
     */
    static isValidUsername(username) {
        const re = /^[a-zA-Z0-9_]+$/;
        return re.test(username);
    }

    /**
     * Format date for display
     * @param {Date|string} date - Date to format
     * @param {string} locale - Locale for formatting
     * @returns {string} - Formatted date
     */
    static formatDate(date, locale = 'th-TH') {
        if (!date) return 'ไม่ระบุ';
        
        const d = new Date(date);
        if (isNaN(d.getTime())) return 'ไม่ระบุ';
        
        return d.toLocaleDateString(locale, {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * Get user-friendly OU display name
     * @param {string} ouPath - OU path string
     * @returns {string} - Formatted OU display name
     */
    static formatOuDisplay(ouPath) {
        if (!ouPath) return 'ไม่ระบุ';
        
        // Split by / and take the last two parts
        const parts = ouPath.split(' / ');
        if (parts.length > 1) {
            return parts[0] + ' / ' + parts[1];
        }
        
        return parts[0] || 'ไม่ระบุ';
    }

    /**
     * Create notification toast
     * @param {string} message - Message to display
     * @param {string} type - Type of notification (success, error, warning, info)
     * @param {number} duration - Duration in milliseconds
     */
    static showToast(message, type = 'success', duration = 5000) {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toastId = 'toast-' + Date.now();
        const iconClass = {
            success: 'fas fa-check-circle text-success',
            error: 'fas fa-exclamation-circle text-danger',
            warning: 'fas fa-exclamation-triangle text-warning',
            info: 'fas fa-info-circle text-info'
        }[type] || 'fas fa-info-circle text-info';
        
        const headerClass = type === 'error' ? 'toast-header bg-danger text-white' : 'toast-header';
        
        const toastHtml = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="${headerClass}">
                    <i class="${iconClass} me-2"></i>
                    <strong class="me-auto">${type === 'success' ? 'Success' : type === 'error' ? 'Error' : type === 'warning' ? 'Warning' : 'Info'}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${this.sanitizeHtml(message)}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        const toastElement = document.getElementById(toastId);
        const bsToast = new bootstrap.Toast(toastElement, { delay: duration });
        bsToast.show();
        
        // Remove element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    /**
     * Handle AJAX errors consistently
     * @param {Error} error - Error object
     * @param {string} defaultMessage - Default error message
     */
    static handleAjaxError(error, defaultMessage = 'An error occurred') {
        console.error('AJAX Error:', error);
        
        let message = defaultMessage;
        
        if (error.name === 'AbortError') {
            message = 'Request timeout. Please try again.';
        } else if (error.message) {
            message = error.message;
        }
        
        this.showToast(message, 'error');
    }

    /**
     * Create loading overlay
     * @param {string} message - Loading message
     * @returns {HTMLElement} - Loading overlay element
     */
    static createLoadingOverlay(message = 'Loading...') {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        
        overlay.innerHTML = `
            <div class="loading-content" style="
                background: white;
                padding: 2rem;
                border-radius: 8px;
                text-align: center;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            ">
                <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                <div>${this.sanitizeHtml(message)}</div>
            </div>
        `;
        
        return overlay;
    }

    /**
     * Show loading overlay
     * @param {string} message - Loading message
     * @returns {HTMLElement} - Loading overlay element
     */
    static showLoadingOverlay(message = 'Loading...') {
        const overlay = this.createLoadingOverlay(message);
        document.body.appendChild(overlay);
        return overlay;
    }

    /**
     * Hide loading overlay
     * @param {HTMLElement} overlay - Overlay to hide
     */
    static hideLoadingOverlay(overlay) {
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OuUserUtils;
}
