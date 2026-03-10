/**
 * Form Library JavaScript
 * Archive System - Quezon City Public Library
 */

const FormLibrary = {
    currentFilter: 'all',
    searchQuery: '',
    
    init() {
        this.bindEvents();
    },
    
    bindEvents() {
        // Create new form
        const createBtn = document.getElementById('createFormBtn');
        if (createBtn) {
            createBtn.addEventListener('click', () => {
                window.location.href = 'form-builder.php';
            });
        }
        
        // Filter tabs
        document.querySelectorAll('#formFilterTabs .filter-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.setFilter(e.target.dataset.filter);
            });
        });
        
        // Search
        const searchInput = document.getElementById('formSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value.toLowerCase();
                this.applyFilters();
            });
        }
        
        // Delegate events for dynamic elements
        document.addEventListener('click', (e) => {
            if (e.target.closest('.edit-form')) {
                const formId = e.target.closest('.edit-form').dataset.formId;
                window.location.href = `form-builder.php?id=${formId}`;
            }
            if (e.target.closest('.preview-form')) {
                e.preventDefault();
                const formId = e.target.closest('.preview-form').dataset.formId;
                this.previewForm(formId);
            }
            if (e.target.closest('.set-active-form')) {
                e.preventDefault();
                const formId = e.target.closest('.set-active-form').dataset.formId;
                this.setActiveForm(formId);
            }
            if (e.target.closest('.duplicate-form')) {
                e.preventDefault();
                const formId = e.target.closest('.duplicate-form').dataset.formId;
                this.duplicateForm(formId);
            }
            if (e.target.closest('.archive-form')) {
                e.preventDefault();
                const formId = e.target.closest('.archive-form').dataset.formId;
                this.archiveForm(formId);
            }
            if (e.target.closest('.delete-form')) {
                e.preventDefault();
                const formId = e.target.closest('.delete-form').dataset.formId;
                this.deleteForm(formId);
            }
        });
    },
    
    setFilter(filter) {
        this.currentFilter = filter;
        
        // Update active tab
        document.querySelectorAll('#formFilterTabs .filter-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.filter === filter) {
                tab.classList.add('active');
            }
        });
        
        this.applyFilters();
    },
    
    applyFilters() {
        const rows = document.querySelectorAll('.form-template-row');
        const table = document.querySelector('.forms-table tbody');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const status = row.dataset.status;
            const name = row.dataset.name;
            const description = row.dataset.description;
            
            // Check filter
            let matchesFilter = this.currentFilter === 'all' || status === this.currentFilter;
            
            // Check search
            let matchesSearch = this.searchQuery === '' || 
                               name.includes(this.searchQuery) || 
                               description.includes(this.searchQuery);
            
            if (matchesFilter && matchesSearch) {
                row.classList.remove('d-none');
                visibleCount++;
            } else {
                row.classList.add('d-none');
            }
        });
        
        // Show/hide empty state and table
        const noFormsMessage = document.getElementById('noFormsMessage');
        const tableContainer = document.querySelector('.table-container');
        
        if (noFormsMessage && tableContainer) {
            if (visibleCount === 0 && rows.length > 0) {
                tableContainer.querySelector('table').classList.add('d-none');
                noFormsMessage.classList.remove('d-none');
            } else {
                tableContainer.querySelector('table').classList.remove('d-none');
                noFormsMessage.classList.add('d-none');
            }
        }
    },
    
    async setActiveForm(formId) {
        if (!confirm('Set this form as active? The current active form will be deactivated.')) {
            return;
        }
        
        try {
            const response = await fetch('../backend/api/form-templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set_active', form_id: formId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error setting active form:', error);
            alert('An error occurred');
        }
    },
    
    async duplicateForm(formId) {
        try {
            const response = await fetch('../backend/api/form-templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'duplicate', form_id: formId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error duplicating form:', error);
            alert('An error occurred');
        }
    },
    
    async archiveForm(formId) {
        if (!confirm('Archive this form template?')) {
            return;
        }
        
        try {
            const response = await fetch('../backend/api/form-templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'archive', form_id: formId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error archiving form:', error);
            alert('An error occurred');
        }
    },
    
    async deleteForm(formId) {
        if (!confirm('Delete this form template? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch('../backend/api/form-templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', form_id: formId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else if (result.requires_confirmation) {
                // Form has associated values, ask for confirmation
                if (confirm(result.message + '\n\nProceed with deletion?')) {
                    // Retry with confirmation
                    const confirmResponse = await fetch('../backend/api/form-templates.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', form_id: formId, confirm: true })
                    });
                    
                    const confirmResult = await confirmResponse.json();
                    
                    if (confirmResult.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + confirmResult.message);
                    }
                }
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error deleting form:', error);
            alert('An error occurred');
        }
    },
    
    async previewForm(formId) {
        try {
            const response = await fetch(`../backend/api/form-templates.php?action=get&form_id=${formId}`);
            const result = await response.json();
            
            if (result.success) {
                this.showPreviewModal(result.template, result.fields);
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error loading form preview:', error);
            alert('An error occurred');
        }
    },
    
    showPreviewModal(template, fields) {
        const modalBody = document.getElementById('previewModalBody');
        
        let html = `<h4 class="mb-3">${template.name}</h4>`;
        
        if (template.description) {
            html += `<p class="text-muted mb-4">${template.description}</p>`;
        }
        
        html += '<div class="form-preview">';
        
        fields.forEach(field => {
            html += '<div class="mb-3">';
            html += `<label class="form-label">${field.field_label}`;
            if (field.is_required) {
                html += ' <span class="text-danger">*</span>';
            }
            html += '</label>';
            
            if (field.help_text) {
                html += `<div class="form-text mb-2">${field.help_text}</div>`;
            }
            
            // Render field based on type
            switch (field.field_type) {
                case 'text':
                    html += '<input type="text" class="form-control" disabled>';
                    break;
                case 'textarea':
                    html += '<textarea class="form-control" rows="3" disabled></textarea>';
                    break;
                case 'number':
                    html += '<input type="number" class="form-control" disabled>';
                    break;
                case 'date':
                    html += '<input type="date" class="form-control" disabled>';
                    break;
                case 'select':
                    html += '<select class="form-select" disabled>';
                    html += '<option>Select an option...</option>';
                    if (field.field_options) {
                        const options = JSON.parse(field.field_options);
                        options.forEach(opt => {
                            html += `<option>${opt}</option>`;
                        });
                    }
                    html += '</select>';
                    break;
                case 'checkbox':
                    if (field.field_options) {
                        const options = JSON.parse(field.field_options);
                        options.forEach(opt => {
                            html += '<div class="form-check">';
                            html += '<input class="form-check-input" type="checkbox" disabled>';
                            html += `<label class="form-check-label">${opt}</label>`;
                            html += '</div>';
                        });
                    }
                    break;
                case 'radio':
                    if (field.field_options) {
                        const options = JSON.parse(field.field_options);
                        options.forEach(opt => {
                            html += '<div class="form-check">';
                            html += '<input class="form-check-input" type="radio" disabled>';
                            html += `<label class="form-check-label">${opt}</label>`;
                            html += '</div>';
                        });
                    }
                    break;
                case 'tags':
                    html += '<div style="display:flex;flex-wrap:wrap;gap:6px;padding:8px 0;">';
                    ['Filipiniana', 'History', 'Manila'].forEach(tag => {
                        html += `<span style="display:inline-flex;align-items:center;gap:4px;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;">${tag} <span style="opacity:.6;">×</span></span>`;
                    });
                    html += '</div>';
                    html += '<div style="border:1px solid #E5E7EB;border-radius:8px;display:flex;align-items:center;gap:8px;padding:6px 10px;background:#fff;margin-top:4px;">';
                    html += '<input type="text" placeholder="Type a tag & press Enter" style="border:none;outline:none;flex:1;font-size:13px;" disabled>';
                    html += '<button style="background:#3A9AFF;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:12px;" disabled>Add</button></div>';
                    break;
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        
        modalBody.innerHTML = html;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    FormLibrary.init();
});
