/**
 * Form Builder JavaScript
 * Archive System - Quezon City Public Library
 */

const FormBuilder = {
    fields: [],
    selectedFieldIndex: null,
    sortable: null,
    isEditMode: false,

    init() {
        this.isEditMode = this.checkEditMode();
        this.loadExistingFields();
        this.initSortable();
        this.bindEvents();
        this.notifyEditMode();

        // If we have existing fields, render them
        if (this.fields.length > 0) {
            this.renderCanvas();
        }
    },

    checkEditMode() {
        const isEditModeInput = document.getElementById('isEditMode');
        return !!(isEditModeInput && isEditModeInput.value === '1');
    },

    notifyEditMode() {
        const isEditModeInput = document.getElementById('isEditMode');
        if (!isEditModeInput || isEditModeInput.value !== '1') {
            return;
        }

        this.showToast('You are editing an existing custom metadata form.', 'info');
    },

    loadExistingFields() {
        const fieldsInput = document.getElementById('formFieldsData');
        const fieldsData = fieldsInput ? fieldsInput.value : '[]';

        if (!fieldsData || fieldsData === '[]') {
            this.fields = [];
            return;
        }

        try {
            const parsedFields = JSON.parse(fieldsData);
            this.fields = Array.isArray(parsedFields) ? parsedFields.map((field, index) => ({
                id: field.id ?? null,
                field_label: field.field_label || `Field ${index + 1}`,
                field_type: field.field_type || 'text',
                field_options: field.field_options ?? null,
                is_required: Number(field.is_required) ? 1 : 0,
                display_order: Number.isInteger(field.display_order) ? field.display_order : index,
                help_text: field.help_text ?? null
            })) : [];
        } catch (error) {
            console.error('Error loading existing form fields:', error);
            this.fields = [];
            this.showToast('Unable to load existing metadata fields.', 'error');
        }
    },

    initSortable() {
        const canvas = document.getElementById('formCanvas');
        if (!canvas) return;

        // ── Sidebar Sortables ──
        // Each .field-type-list gets a Sortable with pull:'clone' so items
        // can be dragged into the canvas.  sort:false keeps sidebar order fixed.
        document.querySelectorAll('.field-type-list').forEach(sidebar => {
            new Sortable(sidebar, {
                group: {
                    name: 'field-builder',
                    pull: 'clone', // clone the element when dragging out
                    put: false     // nothing can be dropped back here
                },
                animation: 150,
                sort: false,       // items in the sidebar don't reorder
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onStart: () => {
                    canvas.classList.add('drag-over');
                },
                onEnd: () => {
                    canvas.classList.remove('drag-over');
                }
            });
        });

        // ── Canvas Sortable ──
        // Handles BOTH:
        //   1. Reordering existing fields via the drag handle
        //   2. Receiving new fields dragged from the sidebar (onAdd)
        this.sortable = new Sortable(canvas, {
            group: {
                name: 'field-builder',
                pull: false, // cannot drag canvas items back to sidebar
                put: true    // accept items dragged from the sidebar
            },
            animation: 150,
            handle: '.field-drag-handle', // only the grip icon triggers reorder
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',

            // Fires when an item from the SIDEBAR is dropped onto the canvas
            onAdd: (evt) => {
                // SortableJS inserts a clone of the sidebar item into the canvas DOM.
                // We manage our own DOM via renderCanvas(), so remove the clone.
                if (evt.item && evt.item.parentNode) {
                    evt.item.remove();
                }
                canvas.classList.remove('drag-over');

                // The dragged element retains data-field-type from the sidebar HTML
                const fieldType = evt.item.dataset.fieldType;
                if (fieldType) {
                    this.addField(fieldType);
                }
            },

            // Fires when an existing canvas item is dropped after reordering
            onEnd: (evt) => {
                // Guard: only treat as an intra-canvas reorder
                if (evt.from === canvas && evt.to === canvas) {
                    this.reorderFields(evt.oldIndex, evt.newIndex);
                }
                canvas.classList.remove('drag-over');
            }
        });
    },

    bindEvents() {
        // Save buttons with confirmation
        const saveDraftBtn = document.getElementById('saveDraftBtn');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', () => {
                this.showConfirmation(
                    'Save as Draft',
                    'Save this form as a draft? You can publish it later.',
                    () => this.saveForm('draft')
                );
            });
        }

        document.getElementById('publishFormBtn').addEventListener('click', () => {
            this.showConfirmation(
                this.isEditMode ? 'Save Changes' : 'Publish Form',
                this.isEditMode
                    ? 'Apply your changes to this existing custom metadata form? The updated version will be available immediately.'
                    : 'Publish this form? It will be available for use immediately.',
                () => this.saveForm('active'),
                this.isEditMode ? 'Save Changes' : 'Publish',
                'btn-primary'
            );
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
            // + button on a field type card
            const addFieldButton = e.target.closest('.field-type-add-btn');
            if (addFieldButton) {
                e.preventDefault();
                e.stopPropagation();
                const fieldTypeItem = addFieldButton.closest('.field-type-item');
                if (fieldTypeItem && fieldTypeItem.dataset.fieldType) {
                    this.addField(fieldTypeItem.dataset.fieldType);
                }
                return;
            }

            // Click anywhere on a field type card (but not the + button)
            const fieldTypeItem = e.target.closest('.field-type-item');
            if (fieldTypeItem && !e.target.closest('.field-type-add-btn')) {
                this.addField(fieldTypeItem.dataset.fieldType);
                return;
            }

            // Click on a canvas field (not the delete button)
            if (e.target.closest('.form-field-item') && !e.target.closest('.delete-field')) {
                const fieldItem = e.target.closest('.form-field-item');
                const items = Array.from(document.querySelectorAll('.form-field-item'));
                const index = items.indexOf(fieldItem);
                this.selectField(index);
            }

            // Delete button on a canvas field
            if (e.target.closest('.delete-field')) {
                e.stopPropagation();
                const fieldItem = e.target.closest('.form-field-item');
                const items = Array.from(document.querySelectorAll('.form-field-item'));
                const index = items.indexOf(fieldItem);
                this.confirmDeleteField(index);
            }

            // Remove option button in the settings panel
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
        this.normalizeFieldOrder();
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
        this.normalizeFieldOrder();

        this.renderCanvas();

        // Hide config panel
        document.getElementById('fieldConfigPanel').style.display = 'none';
        document.getElementById('noFieldSelected').style.display = 'block';
        this.selectedFieldIndex = null;

        this.showToast('Field deleted successfully', 'success');
    },

    reorderFields(oldIndex, newIndex) {
        if (oldIndex === newIndex || oldIndex < 0 || newIndex < 0) {
            return;
        }

        const field = this.fields.splice(oldIndex, 1)[0];
        this.fields.splice(newIndex, 0, field);
        this.normalizeFieldOrder();

        // Update selected index if needed
        if (this.selectedFieldIndex === oldIndex) {
            this.selectedFieldIndex = newIndex;
        } else if (this.selectedFieldIndex !== null) {
            const movedForward = oldIndex < newIndex;
            if (movedForward && this.selectedFieldIndex > oldIndex && this.selectedFieldIndex <= newIndex) {
                this.selectedFieldIndex -= 1;
            } else if (!movedForward && this.selectedFieldIndex >= newIndex && this.selectedFieldIndex < oldIndex) {
                this.selectedFieldIndex += 1;
            }
        }

        this.renderCanvas();
        if (this.selectedFieldIndex !== null) {
            this.selectField(this.selectedFieldIndex);
        }
    },

    renderCanvas() {
        const canvas = document.getElementById('formCanvas');

        if (this.fields.length === 0) {
            canvas.innerHTML = `
                <div class="canvas-empty-state text-center py-5">
                    <div class="canvas-empty-icon">
                        <i class="bi bi-plus-square-dotted"></i>
                    </div>
                    <p class="canvas-empty-title">Start building your metadata form</p>
                    <p class="text-muted mt-2 mb-0">Drag field types here or click the <code>+</code> button from the left panel.</p>
                </div>
            `;
            return;
        }

        canvas.innerHTML = this.fields.map((field, index) => `
            <div class="form-field-item ${index === this.selectedFieldIndex ? 'selected' : ''}" data-field-index="${index}" data-field-id="${field.id || ''}">
                <div class="field-item-header">
                    <span class="field-drag-handle" title="Drag to reorder">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                    <span class="field-order-badge">${index + 1}</span>
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

    normalizeFieldOrder() {
        this.fields.forEach((field, index) => {
            field.display_order = index;
        });
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
        html += '</div><div style="border:1px solid #E5E7EB;border-radius:8px;display:flex;align-items:center;gap:8px;padding:6px 10px;background:#fff;margin-top:4px;"><input type="text" placeholder="Type a tag &amp; press Enter" style="border:none;outline:none;flex:1;font-size:13px;" disabled><button style="background:#3A9AFF;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:12px;" disabled>Add</button></div>';
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

        if (this.isEditMode && !formId) {
            alert('Unable to save changes because the form ID is missing.');
            return;
        }

        const data = {
            action: formId ? 'update' : 'create',
            form_id: formId || null,
            name: formName,
            description: formDescription,
            status: status,
            fields: this.fields.map((field, index) => ({
                id: field.id || null,
                field_label: field.field_label,
                field_type: field.field_type,
                field_options: field.field_options,
                is_required: field.is_required ? 1 : 0,
                display_order: index,
                help_text: field.help_text ?? null
            }))
        };

        try {
            const response = await fetch(`${APP_URL}/backend/api/form-templates.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const rawResponse = await response.text();
            let result;

            try {
                result = JSON.parse(rawResponse);
            } catch (parseError) {
                console.error('Invalid save response:', rawResponse);
                throw new Error('The server returned an invalid response while saving the form.');
            }

            if (!response.ok) {
                throw new Error(result.message || 'Unable to save the form.');
            }

            if (result.success) {
                window.location.href = APP_URL + '/form-library';
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving form:', error);
            alert('An error occurred while saving');
        }
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
