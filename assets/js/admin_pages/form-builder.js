/**
 * Form Builder JavaScript
 * Archive System - Quezon City Public Library
 */

const FormBuilder = {
    fields: [],
    selectedFieldIndex: null,
    sortable: null,

    init() {
        this.loadExistingFields();
        this.initSortable();
        this.bindEvents();

        // If we have existing fields, render them
        if (this.fields.length > 0) {
            this.renderCanvas();
        }
    },

    loadExistingFields() {
        const fieldsData = document.getElementById('formFieldsData').value;
        if (fieldsData && fieldsData !== '[]') {
            this.fields = JSON.parse(fieldsData);
        }
    },

    initSortable() {
        const canvas = document.getElementById('formCanvas');

        // Initialize SortableJS for drag-and-drop within canvas
        this.sortable = new Sortable(canvas, {
            animation: 150,
            handle: '.field-drag-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: (evt) => {
                this.reorderFields(evt.oldIndex, evt.newIndex);
            }
        });

        // Make field types draggable
        document.querySelectorAll('.field-type-item').forEach(item => {
            item.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('fieldType', item.dataset.fieldType);
            });
        });

        // Make canvas accept drops
        canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        canvas.addEventListener('drop', (e) => {
            e.preventDefault();
            const fieldType = e.dataTransfer.getData('fieldType');
            if (fieldType) {
                this.addField(fieldType);
            }
        });
    },

    bindEvents() {
        // Save buttons with confirmation
        document.getElementById('saveDraftBtn').addEventListener('click', () => {
            this.showConfirmation(
                'Save as Draft',
                'Save this form as a draft? You can publish it later.',
                () => this.saveForm('draft')
            );
        });

        document.getElementById('publishFormBtn').addEventListener('click', () => {
            this.showConfirmation(
                'Publish Form',
                'Publish this form? It will be available for use immediately.',
                () => this.saveForm('active'),
                'Publish',
                'btn-primary'
            );
        });

        document.getElementById('previewFormBtn').addEventListener('click', () => {
            this.previewForm();
        });

        // Field configuration - Auto-save on Enter key for field label
        document.getElementById('fieldLabel').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.saveFieldConfig();
            }
        });

        // Auto-save on blur (when user clicks away)
        document.getElementById('fieldLabel').addEventListener('blur', () => {
            this.saveFieldConfig();
        });

        // Auto-save on change for other fields
        document.getElementById('fieldType').addEventListener('change', (e) => {
            this.toggleOptionsField(e.target.value);
            this.saveFieldConfig();
        });

        document.getElementById('fieldRequired').addEventListener('change', () => {
            this.saveFieldConfig();
        });

        document.getElementById('fieldPlaceholder').addEventListener('blur', () => {
            this.saveFieldConfig();
        });

        document.getElementById('addOptionBtn').addEventListener('click', () => {
            this.addOption();
        });

        // Delegate events
        document.addEventListener('click', (e) => {
            if (e.target.closest('.form-field-item') && !e.target.closest('.delete-field')) {
                const fieldItem = e.target.closest('.form-field-item');
                const items = Array.from(document.querySelectorAll('.form-field-item'));
                const index = items.indexOf(fieldItem);
                this.selectField(index);
            }

            if (e.target.closest('.delete-field')) {
                e.stopPropagation();
                const fieldItem = e.target.closest('.form-field-item');
                const items = Array.from(document.querySelectorAll('.form-field-item'));
                const index = items.indexOf(fieldItem);
                this.confirmDeleteField(index);
            }

            if (e.target.closest('.remove-option')) {
                const index = parseInt(e.target.closest('.remove-option').dataset.index);
                this.removeOption(index);
            }
        });

        // Handle Enter key in option inputs to add new option
        document.addEventListener('keypress', (e) => {
            if (e.target.classList.contains('option-input') && e.key === 'Enter') {
                e.preventDefault();
                this.addOption();
                // Focus the new input
                setTimeout(() => {
                    const inputs = document.querySelectorAll('.option-input');
                    inputs[inputs.length - 1].focus();
                }, 100);
            }
        });

        // Auto-save options on blur
        document.addEventListener('blur', (e) => {
            if (e.target.classList.contains('option-input')) {
                this.saveFieldConfig();
            }
        }, true);
    },

    addField(fieldType) {
        const newField = {
            id: null, // Will be assigned on save
            field_label: `New ${fieldType.charAt(0).toUpperCase() + fieldType.slice(1)} Field`,
            field_type: fieldType,
            field_options: null,
            is_required: 0,
            display_order: this.fields.length,
            help_text: null // This will store placeholder text
        };

        this.fields.push(newField);
        this.renderCanvas();
        this.selectField(this.fields.length - 1);
    },

    selectField(index) {
        if (index < 0 || index >= this.fields.length) return;

        this.selectedFieldIndex = index;
        const field = this.fields[index];

        // Show config panel
        document.getElementById('fieldConfigPanel').style.display = 'block';
        document.getElementById('noFieldSelected').style.display = 'none';

        // Populate config form
        document.getElementById('selectedFieldId').value = field.id || '';
        document.getElementById('fieldLabel').value = field.field_label;
        document.getElementById('fieldType').value = field.field_type;
        document.getElementById('fieldRequired').checked = field.is_required;
        document.getElementById('fieldPlaceholder').value = field.help_text || '';

        // Handle options
        this.toggleOptionsField(field.field_type);
        if (field.field_options) {
            try {
                const options = JSON.parse(field.field_options);
                this.renderOptions(options);
            } catch (e) {
                console.error('Error parsing field options:', e);
                this.renderOptions([]);
            }
        } else {
            this.renderOptions([]);
        }

        // Highlight selected field
        document.querySelectorAll('.form-field-item').forEach((item, i) => {
            if (i === index) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    },

    saveFieldConfig() {
        if (this.selectedFieldIndex === null) return;

        const field = this.fields[this.selectedFieldIndex];
        const newLabel = document.getElementById('fieldLabel').value;
        
        // Don't save if label is empty
        if (!newLabel || !newLabel.trim()) {
            return;
        }

        field.field_label = newLabel;
        field.field_type = document.getElementById('fieldType').value;
        field.is_required = document.getElementById('fieldRequired').checked ? 1 : 0;
        field.help_text = document.getElementById('fieldPlaceholder').value; // Store placeholder in help_text

        // Handle options
        if (['select', 'checkbox', 'radio'].includes(field.field_type)) {
            const options = this.getOptions();
            field.field_options = JSON.stringify(options);
        } else {
            field.field_options = null;
        }

        this.renderCanvas();
        this.selectField(this.selectedFieldIndex); // Re-select to update UI
    },

    confirmDeleteField(index) {
        this.showConfirmation(
            'Delete Field',
            'Are you sure you want to delete this field? This action cannot be undone.',
            () => this.deleteField(index),
            'Delete',
            'btn-danger'
        );
    },

    deleteField(index) {
        this.fields.splice(index, 1);

        // Update display_order
        this.fields.forEach((f, i) => {
            f.display_order = i;
        });

        this.renderCanvas();

        // Hide config panel
        document.getElementById('fieldConfigPanel').style.display = 'none';
        document.getElementById('noFieldSelected').style.display = 'block';
        this.selectedFieldIndex = null;

        this.showToast('Field deleted successfully', 'success');
    },

    reorderFields(oldIndex, newIndex) {
        const field = this.fields.splice(oldIndex, 1)[0];
        this.fields.splice(newIndex, 0, field);

        // Update display_order
        this.fields.forEach((f, i) => {
            f.display_order = i;
        });

        // Update selected index if needed
        if (this.selectedFieldIndex === oldIndex) {
            this.selectedFieldIndex = newIndex;
        }
    },

    renderCanvas() {
        const canvas = document.getElementById('formCanvas');

        if (this.fields.length === 0) {
            canvas.innerHTML = `
                <div class="canvas-empty-state text-center py-5">
                    <i class="bi bi-cursor" style="font-size: 3rem; color: #dee2e6;"></i>
                    <p class="text-muted mt-3">Drag field types here to build your form</p>
                </div>
            `;
            return;
        }

        canvas.innerHTML = this.fields.map((field, index) => `
            <div class="form-field-item ${index === this.selectedFieldIndex ? 'selected' : ''}">
                <div class="field-item-header">
                    <span class="field-drag-handle">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                    <span class="field-label">${this.escapeHtml(field.field_label)}</span>
                    <span class="field-type-badge badge bg-secondary">${field.field_type}</span>
                    ${field.is_required ? '<span class="badge bg-danger">Required</span>' : ''}
                    <button class="btn btn-sm btn-outline-danger ms-auto delete-field">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    },

    toggleOptionsField(fieldType) {
        const container = document.getElementById('fieldOptionsContainer');
        if (['select', 'checkbox', 'radio'].includes(fieldType)) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    },

    renderTagsPreview() {
        const sampleTags = ['Filipiniana', 'History', 'Manila'];
        let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;padding:8px 0;">';
        sampleTags.forEach(tag => {
            html += `<span style="display:inline-flex;align-items:center;gap:4px;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;">${tag} <span style="cursor:pointer;opacity:.6;">×</span></span>`;
        });
        html += '</div><div style="border:1px solid #E5E7EB;border-radius:8px;display:flex;align-items:center;gap:8px;padding:6px 10px;background:#fff;margin-top:4px;"><input type="text" placeholder="Type a tag & press Enter" style="border:none;outline:none;flex:1;font-size:13px;" disabled><button style="background:#3A9AFF;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:12px;" disabled>Add</button></div>';
        return html;
    },

    renderOptions(options) {
        const list = document.getElementById('optionsList');
        list.innerHTML = options.map((opt, i) => `
            <div class="input-group mb-2">
                <input type="text" class="form-control option-input" value="${this.escapeHtml(opt)}" data-index="${i}">
                <button class="btn btn-outline-danger remove-option" type="button" data-index="${i}">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `).join('');
    },

    addOption() {
        const list = document.getElementById('optionsList');
        const index = list.children.length;
        const div = document.createElement('div');
        div.className = 'input-group mb-2';
        div.innerHTML = `
            <input type="text" class="form-control option-input" placeholder="Option ${index + 1}" data-index="${index}">
            <button class="btn btn-outline-danger remove-option" type="button" data-index="${index}">
                <i class="bi bi-x"></i>
            </button>
        `;
        list.appendChild(div);
    },

    removeOption(index) {
        const inputs = document.querySelectorAll('.option-input');
        if (inputs.length > 1) {
            inputs[index].closest('.input-group').remove();
        } else {
            alert('At least one option is required');
        }
    },

    getOptions() {
        const inputs = document.querySelectorAll('.option-input');
        return Array.from(inputs).map(input => input.value).filter(v => v.trim() !== '');
    },

    async saveForm(status) {
        const formName = document.getElementById('formName').value.trim();
        const formDescription = document.getElementById('formDescription').value.trim();
        const formId = document.getElementById('formId').value;

        if (!formName) {
            alert('Please enter a form name');
            return;
        }

        if (this.fields.length === 0) {
            alert('Please add at least one field');
            return;
        }

        const data = {
            action: formId ? 'update' : 'create',
            form_id: formId || null,
            name: formName,
            description: formDescription,
            status: status,
            fields: this.fields
        };

        try {
            const response = await fetch('../backend/api/form-templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = 'form-library.php';
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving form:', error);
            alert('An error occurred while saving');
        }
    },

    previewForm() {
        const formName = document.getElementById('formName').value.trim();
        const formDescription = document.getElementById('formDescription').value.trim();

        if (!formName) {
            alert('Please enter a form name');
            return;
        }

        if (this.fields.length === 0) {
            alert('Please add at least one field to preview');
            return;
        }

        // Create preview HTML
        let html = `<h4 class="mb-3">${this.escapeHtml(formName)}</h4>`;

        if (formDescription) {
            html += `<p class="text-muted mb-4">${this.escapeHtml(formDescription)}</p>`;
        }

        html += '<div class="form-preview">';

        this.fields.forEach(field => {
            html += '<div class="mb-3">';
            html += `<label class="form-label">${this.escapeHtml(field.field_label)}`;
            if (field.is_required) {
                html += ' <span class="text-danger">*</span>';
            }
            html += '</label>';

            // Render field based on type
            switch (field.field_type) {
                case 'text':
                    html += `<input type="text" class="form-control" placeholder="${this.escapeHtml(field.help_text || '')}" disabled>`;
                    break;
                case 'textarea':
                    html += `<textarea class="form-control" rows="3" placeholder="${this.escapeHtml(field.help_text || '')}" disabled></textarea>`;
                    break;
                case 'number':
                    html += `<input type="number" step="any" class="form-control" placeholder="${this.escapeHtml(field.help_text || '')}" disabled>`;
                    break;
                case 'date':
                    html += '<input type="date" class="form-control" disabled>';
                    break;
                case 'select':
                    html += '<select class="form-select" disabled>';
                    html += '<option>Select an option...</option>';
                    if (field.field_options) {
                        try {
                            const options = JSON.parse(field.field_options);
                            options.forEach(opt => {
                                html += `<option>${this.escapeHtml(opt)}</option>`;
                            });
                        } catch (e) {
                            console.error('Error parsing options:', e);
                        }
                    }
                    html += '</select>';
                    break;
                case 'checkbox':
                    if (field.field_options) {
                        try {
                            const options = JSON.parse(field.field_options);
                            options.forEach(opt => {
                                html += '<div class="form-check">';
                                html += '<input class="form-check-input" type="checkbox" disabled>';
                                html += `<label class="form-check-label">${this.escapeHtml(opt)}</label>`;
                                html += '</div>';
                            });
                        } catch (e) {
                            console.error('Error parsing options:', e);
                        }
                    }
                    break;
                case 'radio':
                    if (field.field_options) {
                        try {
                            const options = JSON.parse(field.field_options);
                            options.forEach(opt => {
                                html += '<div class="form-check">';
                                html += '<input class="form-check-input" type="radio" disabled>';
                                html += `<label class="form-check-label">${this.escapeHtml(opt)}</label>`;
                                html += '</div>';
                            });
                        } catch (e) {
                            console.error('Error parsing options:', e);
                        }
                    }
                    break;
                case 'tags':
                    html += this.renderTagsPreview();
                    break;
            }

            html += '</div>';
        });

        html += '</div>';

        // Show in alert (simple preview)
        const previewWindow = window.open('', 'Form Preview', 'width=600,height=800');
        previewWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Form Preview</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 2rem; font-family: 'Poppins', sans-serif; }
                </style>
            </head>
            <body>
                ${html}
            </body>
            </html>
        `);
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    showConfirmation(title, message, onConfirm, confirmText = 'Confirm', confirmClass = 'btn-primary') {
        // Remove existing modal if any
        const existingModal = document.getElementById('confirmModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal
        const modal = document.createElement('div');
        modal.id = 'confirmModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${this.escapeHtml(title)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${this.escapeHtml(message)}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn ${confirmClass}" id="confirmBtn">${confirmText}</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        document.getElementById('confirmBtn').addEventListener('click', () => {
            bsModal.hide();
            onConfirm();
        });

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    },

    showToast(message, type = 'success') {
        // Remove existing toast if any
        const existingToast = document.getElementById('formBuilderToast');
        if (existingToast) {
            existingToast.remove();
        }

        const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
        const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle';

        const toast = document.createElement('div');
        toast.id = 'formBuilderToast';
        toast.className = 'toast align-items-center text-white border-0';
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex ${bgClass}">
                <div class="toast-body">
                    <i class="bi bi-${icon} me-2"></i>${this.escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        document.body.appendChild(toast);

        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    FormBuilder.init();
});
