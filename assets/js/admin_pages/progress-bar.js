/**
 * Progress Bar Component
 * Archive System - Quezon City Public Library
 * 
 * Calculates and displays form completion percentage in real-time
 */

class ProgressBar {
    /**
     * Initialize progress bar
     * @param {string} containerId - ID of the container element
     * @param {Array<string>} requiredFields - Array of required field IDs
     */
    constructor(containerId, requiredFields = []) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`Progress bar container "${containerId}" not found`);
            return;
        }

        this.requiredFields = new Set(requiredFields);
        this.fillElement = this.container.querySelector('.progress-bar-fill');
        this.percentageElement = this.container.querySelector('.progress-percentage');
        
        if (!this.fillElement || !this.percentageElement) {
            console.error('Progress bar elements not found');
            return;
        }

        this.updateProgress();
    }

    /**
     * Calculate completion percentage
     * @returns {number} Percentage (0-100)
     */
    getCompletionPercentage() {
        if (this.requiredFields.size === 0) {
            return 100;
        }

        let filledCount = 0;
        
        this.requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field) return;

            let isFilled = false;

            if (field.type === 'checkbox' || field.type === 'radio') {
                // For checkbox/radio, check if any in the group is checked
                const name = field.name;
                const group = document.querySelectorAll(`[name="${name}"]`);
                isFilled = Array.from(group).some(el => el.checked);
            } else if (field.tagName === 'SELECT') {
                // For select, check if a non-empty option is selected
                isFilled = field.value && field.value !== '';
            } else {
                // For text inputs, textarea, date, number
                isFilled = field.value && field.value.trim() !== '';
            }

            if (isFilled) {
                filledCount++;
            }
        });

        return Math.round((filledCount / this.requiredFields.size) * 100);
    }

    /**
     * Update progress bar visual display
     */
    updateProgress() {
        const percentage = this.getCompletionPercentage();
        
        // Update width
        this.fillElement.style.width = `${percentage}%`;
        
        // Update text
        this.percentageElement.textContent = `${percentage}%`;
        
        // Update color based on percentage
        let color;
        if (percentage <= 33) {
            color = '#dc3545'; // Red
        } else if (percentage <= 66) {
            color = '#ffc107'; // Yellow
        } else {
            color = '#28a745'; // Green
        }
        this.fillElement.style.backgroundColor = color;
    }

    /**
     * Add a required field to track
     * @param {string} fieldId - ID of the field to add
     */
    addRequiredField(fieldId) {
        this.requiredFields.add(fieldId);
        this.updateProgress();
    }

    /**
     * Remove a required field from tracking
     * @param {string} fieldId - ID of the field to remove
     */
    removeRequiredField(fieldId) {
        this.requiredFields.delete(fieldId);
        this.updateProgress();
    }

    /**
     * Bind to field change events
     * @param {Array<string>} fieldIds - Optional array of specific field IDs to bind
     */
    bindFieldEvents(fieldIds = null) {
        const fields = fieldIds 
            ? fieldIds.map(id => document.getElementById(id)).filter(el => el)
            : Array.from(this.requiredFields).map(id => document.getElementById(id)).filter(el => el);

        fields.forEach(field => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.addEventListener('change', () => this.updateProgress());
            } else {
                field.addEventListener('input', () => this.updateProgress());
                field.addEventListener('change', () => this.updateProgress());
                field.addEventListener('blur', () => this.updateProgress());
            }
        });
    }

    /**
     * Auto-detect required fields from DOM
     * Looks for elements with data-required="true" attribute
     */
    autoDetectRequiredFields() {
        const requiredElements = document.querySelectorAll('[data-required="true"]');
        requiredElements.forEach(element => {
            if (element.id) {
                this.requiredFields.add(element.id);
            }
        });
        this.bindFieldEvents();
        this.updateProgress();
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProgressBar;
}
