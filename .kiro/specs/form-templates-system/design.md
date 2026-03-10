# Design Document: Form Templates System

## Overview

The Form Templates System replaces the individual field management approach with a Google Forms-like template builder. Instead of managing individual metadata fields, administrators create complete form templates that can be saved, managed, and activated as cohesive units. This system provides a visual drag-and-drop interface for building forms, a library for managing multiple templates, and dynamic rendering of the active form on the upload page.

### Design Goals

- Replace individual field management with template-based approach
- Provide intuitive drag-and-drop form building experience
- Support multiple form templates with single active template
- Maintain backward compatibility with existing custom metadata data
- Minimize breaking changes to existing upload functionality
- Enable form template reuse through duplication
- Support draft/active/archived workflow for form templates

### System Context

This system builds upon the existing Custom Metadata System by:
- Migrating from `custom_metadata_fields` to `form_templates` + `form_fields`
- Adding form-level metadata (name, description, status, active state)
- Grouping fields into reusable templates
- Providing visual form builder interface
- Maintaining existing `custom_metadata_values` table with form_id reference

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         User Interface Layer                     │
├─────────────────────────────────────────────────────────────────┤
│  Upload Page    │  Form Library  │  Form Builder  │  Dashboard  │
│  (views/        │  (pages/       │  (pages/       │  (views/    │
│   upload.php)   │   form-        │   form-        │   dashboard │
│                 │   library.php) │   builder.php) │   .php)     │
└────────┬────────┴────────┬───────┴────────┬───────┴────────┬────┘
         │                 │                │                │
         │                 │                │                │
┌────────▼─────────────────▼────────────────▼────────────────▼────┐
│                    JavaScript Layer                              │
├──────────────────────────────────────────────────────────────────┤
│  upload.js       │  form-library. │  form-builder. │  sortable. │
│  (existing)      │  js (new)      │  js (new)      │  js (lib)  │
└────────┬─────────┴────────┬───────┴────────┬───────┴────────┬────┘
         │                  │                │                │
         │                  │                │                │
┌────────▼──────────────────▼────────────────▼────────────────▼────┐
│                      Backend API Layer                            │
├───────────────────────────────────────────────────────────────────┤
│  pages/upload.php │  backend/api/  │  backend/api/  │  backend/  │
│  (modified)       │  form-         │  form-fields.  │  core/     │
│                   │  templates.php │  php (new)     │  functions │
│                   │  (new)         │                │  .php      │
└────────┬──────────┴────────┬───────┴────────┬───────┴────────┬───┘
         │                   │                │                │
         │                   │                │                │
┌────────▼───────────────────▼────────────────▼────────────────▼───┐
│                       Database Layer                              │
├───────────────────────────────────────────────────────────────────┤
│  newspapers      │  form_         │  form_fields   │  custom_    │
│  (existing)      │  templates     │  (new)         │  metadata_  │
│                  │  (new)         │                │  values     │
│                  │                │                │  (modified) │
└───────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

**User Interface Layer:**
- Upload Page: Renders active form template fields, handles empty state
- Form Library: Grid view of all templates with filtering, search, and actions
- Form Builder: Drag-and-drop visual designer with field configuration panel
- Dashboard: Displays custom metadata values (unchanged from existing system)

**JavaScript Layer:**
- upload.js: Existing file handling, extended for dynamic form validation
- form-library.js: Template card interactions, filtering, search, modal dialogs
- form-builder.js: Drag-and-drop, field configuration, auto-save, preview
- sortable.js: Third-party library (SortableJS) for drag-and-drop functionality

**Backend API Layer:**
- pages/upload.php: Modified to load active form template instead of individual fields
- backend/api/form-templates.php: CRUD endpoints for form templates
- backend/api/form-fields.php: CRUD endpoints for fields within templates
- backend/core/functions.php: Helper functions for form operations

**Database Layer:**
- newspapers: Existing table for core file metadata (unchanged)
- form_templates: New table for form template definitions
- form_fields: New table for fields within templates
- custom_metadata_values: Modified to include form_id reference


### Data Flow

#### Form Template Creation Flow

```
1. Admin navigates to Form Library
   ↓
2. Admin clicks "Create New Form"
   ↓
3. System opens Form Builder with empty canvas
   ↓
4. Admin enters form name and description
   ↓
5. Admin drags field types from sidebar to canvas
   ↓
6. For each field, admin configures properties (label, type, required, options)
   ↓
7. Admin reorders fields by dragging
   ↓
8. Admin clicks "Save as Draft" or "Publish"
   ↓
9. JavaScript validates form (name required, at least one field)
   ↓
10. JavaScript POSTs to backend/api/form-templates.php
    ↓
11. PHP begins transaction
    ↓
12. PHP inserts into form_templates table
    ↓
13. PHP gets lastInsertId() as form_id
    ↓
14. PHP inserts each field into form_fields table
    ↓
15. If "Publish", PHP deactivates other active forms
    ↓
16. PHP commits transaction
    ↓
17. PHP logs activity
    ↓
18. PHP returns success response
    ↓
19. JavaScript redirects to Form Library
```

#### Upload Flow with Form Template

```
1. User loads upload page
   ↓
2. PHP queries form_templates WHERE is_active = 1
   ↓
3. If no active form, display empty state with "Configure Metadata Fields" button
   ↓
4. If active form exists, query form_fields WHERE form_id = ? ORDER BY display_order
   ↓
5. PHP renders form fields dynamically
   ↓
6. User fills form fields
   ↓
7. JavaScript validates (including custom required fields)
   ↓
8. User submits form
   ↓
9. PHP validates server-side
   ↓
10. PHP begins transaction
    ↓
11. PHP inserts into newspapers table
    ↓
12. PHP gets lastInsertId() as file_id
    ↓
13. PHP inserts custom_metadata_values (with form_id reference)
    ↓
14. PHP commits transaction
    ↓
15. PHP logs activity
    ↓
16. PHP returns success response
```


## Data Models

### Database Schema

#### Table: form_templates

```sql
CREATE TABLE form_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COMMENT 'User-facing form template name',
    description TEXT DEFAULT NULL COMMENT 'Optional description of form purpose',
    status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    is_active TINYINT(1) DEFAULT 0 COMMENT 'Only one form can be active at a time',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_active (is_active) WHERE is_active = 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores form template definitions';
```

**Note:** MySQL does not support partial unique indexes with WHERE clause. The unique active constraint will be enforced at application level.

#### Table: form_fields

```sql
CREATE TABLE form_fields (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_id INT NOT NULL COMMENT 'References form_templates.id',
    field_label VARCHAR(255) NOT NULL COMMENT 'Display label shown to users',
    field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio') NOT NULL,
    field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/checkbox/radio options',
    is_required TINYINT(1) DEFAULT 0 COMMENT '1 = required field, 0 = optional',
    display_order INT DEFAULT 0 COMMENT 'Sort order for display on forms',
    help_text TEXT DEFAULT NULL COMMENT 'Optional help text displayed near field',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES form_templates(id) ON DELETE CASCADE,
    INDEX idx_form_order (form_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores fields within form templates';
```

#### Modified Table: custom_metadata_values

```sql
ALTER TABLE custom_metadata_values
ADD COLUMN form_id INT DEFAULT NULL COMMENT 'References form_templates.id' AFTER file_id,
ADD FOREIGN KEY (form_id) REFERENCES form_templates(id) ON DELETE SET NULL,
ADD INDEX idx_form_id (form_id);
```

**Migration Note:** Existing rows will have form_id = NULL initially. The migration script will create a default form template and update all existing values to reference it.


### Entity Relationships

```
form_templates (1) ──────< (many) form_fields
      │
      │ (1)
      │
      ▼
   (many) custom_metadata_values (many) ──────> (1) newspapers
      │
      │ (many)
      │
      ▼
   (1) form_fields (via field_id, optional for backward compatibility)
```

**Relationships:**
- One form_template has many form_fields (CASCADE DELETE)
- One form_template has many custom_metadata_values (SET NULL on delete)
- One newspapers record has many custom_metadata_values (CASCADE DELETE)
- One form_field can have many custom_metadata_values (SET NULL on delete)

**Key Constraints:**
- Only one form_template can have is_active = 1 (enforced in application logic)
- form_fields.display_order determines rendering order
- custom_metadata_values.field_id can be NULL (for backward compatibility with migrated data)

## Components and Interfaces

### 1. Empty State Component

**File:** `views/upload.php` (modified section)

**Purpose:** Display guidance when no form template is active.

**UI Specification:**

```html
<!-- Empty State (shown when no active form) -->
<div class="empty-state-container text-center py-5">
    <div class="empty-state-icon mb-3">
        <i class="bi bi-inbox" style="font-size: 4rem; color: #6c757d;"></i>
    </div>
    <h3 class="empty-state-title">No Metadata Fields Defined</h3>
    <p class="empty-state-description text-muted">
        Please customize your metadata structure to start uploading archives.<br>
        Define fields like Author, Date, and Keywords to keep your library organized.
    </p>
    <a href="form-library.php" class="btn btn-primary mt-3">
        <i class="bi bi-gear"></i> Configure Metadata Fields
    </a>
</div>
```

**CSS Classes:**
- `.empty-state-container`: Centered container with padding
- `.empty-state-icon`: Large icon with muted color
- `.empty-state-title`: Bold heading
- `.empty-state-description`: Muted text with line breaks

**Integration Logic:**

```php
// In pages/upload.php controller
$stmt = $pdo->query("SELECT * FROM form_templates WHERE is_active = 1");
$activeForm = $stmt->fetch();

if ($activeForm) {
    // Load form fields
    $stmt = $pdo->prepare("
        SELECT * FROM form_fields 
        WHERE form_id = ? 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$activeForm['id']]);
    $formFields = $stmt->fetchAll();
} else {
    $formFields = [];
}
```


### 2. Form Library Component

**File:** `pages/form-library.php`

**Purpose:** Display all form templates with filtering, search, and management actions.

**Controller Logic:**

```php
<?php
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect('dashboard.php?error=' . urlencode('Access denied'));
}

// Get all form templates with field counts
$stmt = $pdo->query("
    SELECT 
        ft.*,
        COUNT(ff.id) as field_count
    FROM form_templates ft
    LEFT JOIN form_fields ff ON ft.id = ff.form_id
    GROUP BY ft.id
    ORDER BY ft.updated_at DESC
");
$formTemplates = $stmt->fetchAll();

// Load view
include __DIR__ . '/../views/form-library.php';
```

**View Structure:** `views/form-library.php`

```html
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <h1>Form Templates</h1>
    <button class="btn btn-primary" id="createFormBtn">
        <i class="bi bi-plus-circle"></i> Create New Form
    </button>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-tabs mb-4" id="formFilterTabs">
    <li class="nav-item">
        <a class="nav-link active" data-filter="all" href="#">All Forms</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-filter="active" href="#">Active</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-filter="draft" href="#">Drafts</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-filter="archived" href="#">Archived</a>
    </li>
</ul>

<!-- Search Bar -->
<div class="mb-4">
    <input type="text" 
           class="form-control" 
           id="formSearchInput" 
           placeholder="Search forms by name or description...">
</div>

<!-- Form Templates Grid -->
<div class="row" id="formTemplatesGrid">
    <?php foreach ($formTemplates as $template): ?>
    <div class="col-md-4 mb-4 form-template-card" 
         data-status="<?= $template['status'] ?>"
         data-name="<?= strtolower($template['name']) ?>"
         data-description="<?= strtolower($template['description'] ?? '') ?>">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="card-title"><?= htmlspecialchars($template['name']) ?></h5>
                    <?php if ($template['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php elseif ($template['status'] === 'draft'): ?>
                        <span class="badge bg-secondary">Draft</span>
                    <?php else: ?>
                        <span class="badge bg-warning">Archived</span>
                    <?php endif; ?>
                </div>
                <p class="card-text text-muted small">
                    <?= htmlspecialchars($template['description'] ?? 'No description') ?>
                </p>
                <div class="form-meta text-muted small mb-3">
                    <i class="bi bi-list-ul"></i> <?= $template['field_count'] ?> fields
                    <span class="ms-2">
                        <i class="bi bi-clock"></i> 
                        <?= date('M j, Y', strtotime($template['updated_at'])) ?>
                    </span>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <div class="btn-group w-100" role="group">
                    <button class="btn btn-sm btn-outline-primary edit-form" 
                            data-form-id="<?= $template['id'] ?>">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-secondary preview-form" 
                            data-form-id="<?= $template['id'] ?>">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <?php if (!$template['is_active']): ?>
                            <li>
                                <a class="dropdown-item set-active-form" 
                                   href="#" 
                                   data-form-id="<?= $template['id'] ?>">
                                    <i class="bi bi-check-circle"></i> Set as Active
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item duplicate-form" 
                                   href="#" 
                                   data-form-id="<?= $template['id'] ?>">
                                    <i class="bi bi-files"></i> Duplicate
                                </a>
                            </li>
                            <?php if ($template['status'] !== 'archived'): ?>
                            <li>
                                <a class="dropdown-item archive-form" 
                                   href="#" 
                                   data-form-id="<?= $template['id'] ?>">
                                    <i class="bi bi-archive"></i> Archive
                                </a>
                            </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger delete-form" 
                                   href="#" 
                                   data-form-id="<?= $template['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Empty State (when no forms match filter) -->
<div class="text-center py-5 d-none" id="noFormsMessage">
    <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
    <p class="text-muted mt-3">No forms found</p>
</div>
```


**JavaScript:** `assets/js/pages/form-library.js`

```javascript
const FormLibrary = {
    init() {
        this.bindEvents();
        this.currentFilter = 'all';
        this.searchQuery = '';
    },
    
    bindEvents() {
        // Create new form
        document.getElementById('createFormBtn').addEventListener('click', () => {
            window.location.href = 'form-builder.php';
        });
        
        // Filter tabs
        document.querySelectorAll('#formFilterTabs .nav-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.setFilter(e.target.dataset.filter);
            });
        });
        
        // Search
        document.getElementById('formSearchInput').addEventListener('input', (e) => {
            this.searchQuery = e.target.value.toLowerCase();
            this.applyFilters();
        });
        
        // Delegate events for dynamic elements
        document.addEventListener('click', (e) => {
            if (e.target.closest('.edit-form')) {
                const formId = e.target.closest('.edit-form').dataset.formId;
                window.location.href = `form-builder.php?id=${formId}`;
            }
            if (e.target.closest('.preview-form')) {
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
        document.querySelectorAll('#formFilterTabs .nav-link').forEach(tab => {
            tab.classList.remove('active');
            if (tab.dataset.filter === filter) {
                tab.classList.add('active');
            }
        });
        
        this.applyFilters();
    },
    
    applyFilters() {
        const cards = document.querySelectorAll('.form-template-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const status = card.dataset.status;
            const name = card.dataset.name;
            const description = card.dataset.description;
            
            // Check filter
            let matchesFilter = this.currentFilter === 'all' || status === this.currentFilter;
            
            // Check search
            let matchesSearch = this.searchQuery === '' || 
                               name.includes(this.searchQuery) || 
                               description.includes(this.searchQuery);
            
            if (matchesFilter && matchesSearch) {
                card.classList.remove('d-none');
                visibleCount++;
            } else {
                card.classList.add('d-none');
            }
        });
        
        // Show/hide empty state
        const noFormsMessage = document.getElementById('noFormsMessage');
        if (visibleCount === 0) {
            noFormsMessage.classList.remove('d-none');
        } else {
            noFormsMessage.classList.add('d-none');
        }
    },
    
    async setActiveForm(formId) {
        if (!confirm('Set this form as active? The current active form will be deactivated.')) {
            return;
        }
        
        try {
            const response = await fetch('/backend/api/form-templates.php', {
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
            const response = await fetch('/backend/api/form-templates.php', {
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
    
    // Additional methods: previewForm, archiveForm, deleteForm
};

document.addEventListener('DOMContentLoaded', () => {
    FormLibrary.init();
});
```


### 3. Form Builder Component

**File:** `pages/form-builder.php`

**Purpose:** Visual drag-and-drop interface for creating and editing form templates.

**Controller Logic:**

```php
<?php
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect('dashboard.php?error=' . urlencode('Access denied'));
}

$editMode = false;
$formTemplate = null;
$formFields = [];

// Check if editing existing form
if (isset($_GET['id'])) {
    $formId = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
    $stmt->execute([$formId]);
    $formTemplate = $stmt->fetch();
    
    if ($formTemplate) {
        $editMode = true;
        
        // Load form fields
        $stmt = $pdo->prepare("
            SELECT * FROM form_fields 
            WHERE form_id = ? 
            ORDER BY display_order ASC
        ");
        $stmt->execute([$formId]);
        $formFields = $stmt->fetchAll();
    }
}

// Load view
include __DIR__ . '/../views/form-builder.php';
```

**View Structure:** `views/form-builder.php`

```html
<div class="form-builder-container">
    <!-- Header -->
    <div class="form-builder-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="form-library.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Library
            </a>
        </div>
        <div>
            <button class="btn btn-outline-secondary me-2" id="previewFormBtn">
                <i class="bi bi-eye"></i> Preview
            </button>
            <button class="btn btn-secondary me-2" id="saveDraftBtn">
                <i class="bi bi-save"></i> Save as Draft
            </button>
            <button class="btn btn-primary" id="publishFormBtn">
                <i class="bi bi-check-circle"></i> Publish
            </button>
        </div>
    </div>
    
    <!-- Form Metadata -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="mb-3">
                <label for="formName" class="form-label">Form Name *</label>
                <input type="text" 
                       class="form-control" 
                       id="formName" 
                       placeholder="e.g., Book Metadata Form"
                       value="<?= htmlspecialchars($formTemplate['name'] ?? '') ?>"
                       required>
            </div>
            <div class="mb-3">
                <label for="formDescription" class="form-label">Description</label>
                <textarea class="form-control" 
                          id="formDescription" 
                          rows="2"
                          placeholder="Optional description of this form's purpose"><?= htmlspecialchars($formTemplate['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- Main Builder Area -->
    <div class="row">
        <!-- Field Types Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Field Types</h6>
                </div>
                <div class="card-body">
                    <div class="field-type-list">
                        <div class="field-type-item" draggable="true" data-field-type="text">
                            <i class="bi bi-input-cursor-text"></i> Text
                        </div>
                        <div class="field-type-item" draggable="true" data-field-type="textarea">
                            <i class="bi bi-textarea-t"></i> Textarea
                        </div>
                        <div class="field-type-item" draggable="true" data-field-type="number">
                            <i class="bi bi-123"></i> Number
                        </div>
                        <div class="field-type-item" draggable="true" data-field-type="date">
                            <i class="bi bi-calendar"></i> Date
                        </div>
                        <div class="field-type-item" draggable="true" data-field-type="select">
                            <i class="bi bi-list"></i> Select Dropdown
                        </div>
                        <div class="field-type-item" draggable="true" data-field-type="checkbox">
                            <i class="bi bi-check-square"></i> Checkbox
                        </div>
                        <div class="field-type-item" draggable="true" data-field-type="radio">
                            <i class="bi bi-circle"></i> Radio Buttons
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Canvas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Form Canvas</h6>
                </div>
                <div class="card-body">
                    <div id="formCanvas" class="form-canvas">
                        <?php if (empty($formFields)): ?>
                        <div class="canvas-empty-state text-center py-5">
                            <i class="bi bi-cursor" style="font-size: 3rem; color: #dee2e6;"></i>
                            <p class="text-muted mt-3">Drag field types here to build your form</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($formFields as $field): ?>
                        <div class="form-field-item" data-field-id="<?= $field['id'] ?>">
                            <div class="field-item-header">
                                <span class="field-drag-handle">
                                    <i class="bi bi-grip-vertical"></i>
                                </span>
                                <span class="field-label"><?= htmlspecialchars($field['field_label']) ?></span>
                                <span class="field-type-badge badge bg-secondary"><?= $field['field_type'] ?></span>
                                <?php if ($field['is_required']): ?>
                                <span class="badge bg-danger">Required</span>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-danger ms-auto delete-field">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Field Configuration Panel -->
        <div class="col-md-3">
            <div class="card" id="fieldConfigPanel" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">Field Settings</h6>
                </div>
                <div class="card-body">
                    <input type="hidden" id="selectedFieldId">
                    
                    <div class="mb-3">
                        <label for="fieldLabel" class="form-label">Label *</label>
                        <input type="text" class="form-control" id="fieldLabel" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fieldType" class="form-label">Type *</label>
                        <select class="form-select" id="fieldType" required>
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="select">Select Dropdown</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="radio">Radio Buttons</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="fieldOptionsContainer" style="display: none;">
                        <label class="form-label">Options *</label>
                        <div id="optionsList"></div>
                        <button class="btn btn-sm btn-outline-primary mt-2" id="addOptionBtn">
                            <i class="bi bi-plus"></i> Add Option
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fieldHelpText" class="form-label">Help Text</label>
                        <textarea class="form-control" id="fieldHelpText" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fieldRequired">
                            <label class="form-check-label" for="fieldRequired">
                                Required Field
                            </label>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100" id="saveFieldConfigBtn">
                        Save Changes
                    </button>
                </div>
            </div>
            
            <div class="card" id="noFieldSelected">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-hand-index" style="font-size: 2rem;"></i>
                    <p class="mt-3 small">Click a field to configure its properties</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data for edit mode -->
<input type="hidden" id="formId" value="<?= $formTemplate['id'] ?? '' ?>">
<input type="hidden" id="formFieldsData" value='<?= json_encode($formFields) ?>'>
```


**JavaScript:** `assets/js/pages/form-builder.js`

```javascript
const FormBuilder = {
    fields: [],
    selectedFieldIndex: null,
    sortable: null,
    
    init() {
        this.loadExistingFields();
        this.initSortable();
        this.bindEvents();
    },
    
    loadExistingFields() {
        const fieldsData = document.getElementById('formFieldsData').value;
        if (fieldsData) {
            this.fields = JSON.parse(fieldsData);
        }
    },
    
    initSortable() {
        const canvas = document.getElementById('formCanvas');
        
        // Initialize SortableJS for drag-and-drop
        this.sortable = new Sortable(canvas, {
            animation: 150,
            handle: '.field-drag-handle',
            ghostClass: 'sortable-ghost',
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
        // Save buttons
        document.getElementById('saveDraftBtn').addEventListener('click', () => {
            this.saveForm('draft');
        });
        
        document.getElementById('publishFormBtn').addEventListener('click', () => {
            this.saveForm('active');
        });
        
        document.getElementById('previewFormBtn').addEventListener('click', () => {
            this.previewForm();
        });
        
        // Field configuration
        document.getElementById('fieldType').addEventListener('change', (e) => {
            this.toggleOptionsField(e.target.value);
        });
        
        document.getElementById('addOptionBtn').addEventListener('click', () => {
            this.addOption();
        });
        
        document.getElementById('saveFieldConfigBtn').addEventListener('click', () => {
            this.saveFieldConfig();
        });
        
        // Delegate events
        document.addEventListener('click', (e) => {
            if (e.target.closest('.form-field-item') && !e.target.closest('.delete-field')) {
                const fieldItem = e.target.closest('.form-field-item');
                const index = Array.from(fieldItem.parentNode.children).indexOf(fieldItem);
                this.selectField(index);
            }
            
            if (e.target.closest('.delete-field')) {
                const fieldItem = e.target.closest('.form-field-item');
                const index = Array.from(fieldItem.parentNode.children).indexOf(fieldItem);
                this.deleteField(index);
            }
        });
    },
    
    addField(fieldType) {
        const newField = {
            id: null, // Will be assigned on save
            field_label: `New ${fieldType.charAt(0).toUpperCase() + fieldType.slice(1)} Field`,
            field_type: fieldType,
            field_options: null,
            is_required: 0,
            display_order: this.fields.length,
            help_text: null
        };
        
        this.fields.push(newField);
        this.renderCanvas();
        this.selectField(this.fields.length - 1);
    },
    
    selectField(index) {
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
        document.getElementById('fieldHelpText').value = field.help_text || '';
        
        // Handle options
        this.toggleOptionsField(field.field_type);
        if (field.field_options) {
            this.renderOptions(JSON.parse(field.field_options));
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
        field.field_label = document.getElementById('fieldLabel').value;
        field.field_type = document.getElementById('fieldType').value;
        field.is_required = document.getElementById('fieldRequired').checked ? 1 : 0;
        field.help_text = document.getElementById('fieldHelpText').value;
        
        // Handle options
        if (['select', 'checkbox', 'radio'].includes(field.field_type)) {
            const options = this.getOptions();
            field.field_options = JSON.stringify(options);
        } else {
            field.field_options = null;
        }
        
        this.renderCanvas();
    },
    
    deleteField(index) {
        if (confirm('Delete this field?')) {
            this.fields.splice(index, 1);
            this.renderCanvas();
            
            // Hide config panel
            document.getElementById('fieldConfigPanel').style.display = 'none';
            document.getElementById('noFieldSelected').style.display = 'block';
            this.selectedFieldIndex = null;
        }
    },
    
    reorderFields(oldIndex, newIndex) {
        const field = this.fields.splice(oldIndex, 1)[0];
        this.fields.splice(newIndex, 0, field);
        
        // Update display_order
        this.fields.forEach((f, i) => {
            f.display_order = i;
        });
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
                    <span class="field-label">${field.field_label}</span>
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
    
    renderOptions(options) {
        const list = document.getElementById('optionsList');
        list.innerHTML = options.map((opt, i) => `
            <div class="input-group mb-2">
                <input type="text" class="form-control option-input" value="${opt}" data-index="${i}">
                <button class="btn btn-outline-danger remove-option" data-index="${i}">
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
            <button class="btn btn-outline-danger remove-option" data-index="${index}">
                <i class="bi bi-x"></i>
            </button>
        `;
        list.appendChild(div);
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
            const response = await fetch('/backend/api/form-templates.php', {
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
        // Implementation for preview modal
        alert('Preview functionality to be implemented');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    FormBuilder.init();
});
```


### 4. API Endpoints

**File:** `backend/api/form-templates.php`

**Purpose:** CRUD operations for form templates.

```php
<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createFormTemplate($pdo, $currentUser, $input);
            break;
        case 'update':
            updateFormTemplate($pdo, $currentUser, $input);
            break;
        case 'delete':
            deleteFormTemplate($pdo, $currentUser, $input);
            break;
        case 'set_active':
            setActiveFormTemplate($pdo, $currentUser, $input);
            break;
        case 'duplicate':
            duplicateFormTemplate($pdo, $currentUser, $input);
            break;
        case 'archive':
            archiveFormTemplate($pdo, $currentUser, $input);
            break;
        case 'list':
            listFormTemplates($pdo);
            break;
        case 'get':
            getFormTemplate($pdo, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createFormTemplate($pdo, $currentUser, $input) {
    $name = sanitize($input['name']);
    $description = sanitize($input['description'] ?? '');
    $status = $input['status']; // 'draft' or 'active'
    $fields = $input['fields'];
    
    if (empty($name)) {
        throw new Exception('Form name is required');
    }
    
    if (empty($fields)) {
        throw new Exception('At least one field is required');
    }
    
    $pdo->beginTransaction();
    
    try {
        // If status is active, deactivate other forms
        if ($status === 'active') {
            $pdo->exec("UPDATE form_templates SET is_active = 0, status = 'draft' WHERE is_active = 1");
        }
        
        // Insert form template
        $stmt = $pdo->prepare("
            INSERT INTO form_templates (name, description, status, is_active)
            VALUES (?, ?, ?, ?)
        ");
        $isActive = ($status === 'active') ? 1 : 0;
        $stmt->execute([$name, $description, $status, $isActive]);
        
        $formId = $pdo->lastInsertId();
        
        // Insert form fields
        $fieldStmt = $pdo->prepare("
            INSERT INTO form_fields 
            (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($fields as $index => $field) {
            $fieldStmt->execute([
                $formId,
                $field['field_label'],
                $field['field_type'],
                $field['field_options'],
                $field['is_required'],
                $index,
                $field['help_text']
            ]);
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Created form template: $name");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Form template created successfully',
            'form_id' => $formId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    $name = sanitize($input['name']);
    $description = sanitize($input['description'] ?? '');
    $status = $input['status'];
    $fields = $input['fields'];
    
    if (empty($name)) {
        throw new Exception('Form name is required');
    }
    
    if (empty($fields)) {
        throw new Exception('At least one field is required');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Check if form exists
        $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $existingForm = $stmt->fetch();
        
        if (!$existingForm) {
            throw new Exception('Form template not found');
        }
        
        // If status is active, deactivate other forms
        if ($status === 'active') {
            $pdo->exec("UPDATE form_templates SET is_active = 0, status = 'draft' WHERE is_active = 1 AND id != $formId");
        }
        
        // Update form template
        $stmt = $pdo->prepare("
            UPDATE form_templates 
            SET name = ?, description = ?, status = ?, is_active = ?
            WHERE id = ?
        ");
        $isActive = ($status === 'active') ? 1 : 0;
        $stmt->execute([$name, $description, $status, $isActive, $formId]);
        
        // Delete existing fields
        $stmt = $pdo->prepare("DELETE FROM form_fields WHERE form_id = ?");
        $stmt->execute([$formId]);
        
        // Insert updated fields
        $fieldStmt = $pdo->prepare("
            INSERT INTO form_fields 
            (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($fields as $index => $field) {
            $fieldStmt->execute([
                $formId,
                $field['field_label'],
                $field['field_type'],
                $field['field_options'],
                $field['is_required'],
                $index,
                $field['help_text']
            ]);
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Updated form template: $name");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Form template updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $pdo->beginTransaction();
    
    try {
        // Get form name for logging
        $stmt = $pdo->prepare("SELECT name FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $form = $stmt->fetch();
        
        if (!$form) {
            throw new Exception('Form template not found');
        }
        
        // Check if form has associated values
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM custom_metadata_values WHERE form_id = ?");
        $stmt->execute([$formId]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0 && !isset($input['confirm'])) {
            echo json_encode([
                'success' => false,
                'message' => "This form has $count associated values. Are you sure?",
                'requires_confirmation' => true,
                'affected_count' => $count
            ]);
            return;
        }
        
        // Delete form template (CASCADE will delete form_fields)
        $stmt = $pdo->prepare("DELETE FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Deleted form template: {$form['name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Form template deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function setActiveFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $pdo->beginTransaction();
    
    try {
        // Deactivate all forms
        $pdo->exec("UPDATE form_templates SET is_active = 0, status = 'draft' WHERE is_active = 1");
        
        // Activate selected form
        $stmt = $pdo->prepare("UPDATE form_templates SET is_active = 1, status = 'active' WHERE id = ?");
        $stmt->execute([$formId]);
        
        $pdo->commit();
        
        // Log activity
        $stmt = $pdo->prepare("SELECT name FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $formName = $stmt->fetch()['name'];
        logActivity($currentUser['id'], 'custom_metadata_update', "Set active form: $formName");
        
        echo json_encode([
            'success' => true,
            'message' => 'Form template activated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function duplicateFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $pdo->beginTransaction();
    
    try {
        // Get original form
        $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
        $stmt->execute([$formId]);
        $originalForm = $stmt->fetch();
        
        if (!$originalForm) {
            throw new Exception('Form template not found');
        }
        
        // Create duplicate
        $newName = $originalForm['name'] . ' (Copy)';
        $stmt = $pdo->prepare("
            INSERT INTO form_templates (name, description, status, is_active)
            VALUES (?, ?, 'draft', 0)
        ");
        $stmt->execute([$newName, $originalForm['description']]);
        
        $newFormId = $pdo->lastInsertId();
        
        // Copy fields
        $stmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY display_order");
        $stmt->execute([$formId]);
        $fields = $stmt->fetchAll();
        
        $fieldStmt = $pdo->prepare("
            INSERT INTO form_fields 
            (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($fields as $field) {
            $fieldStmt->execute([
                $newFormId,
                $field['field_label'],
                $field['field_type'],
                $field['field_options'],
                $field['is_required'],
                $field['display_order'],
                $field['help_text']
            ]);
        }
        
        $pdo->commit();
        
        // Log activity
        logActivity($currentUser['id'], 'custom_metadata_update', "Duplicated form template: {$originalForm['name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Form template duplicated successfully',
            'form_id' => $newFormId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function archiveFormTemplate($pdo, $currentUser, $input) {
    $formId = intval($input['form_id']);
    
    $stmt = $pdo->prepare("UPDATE form_templates SET status = 'archived', is_active = 0 WHERE id = ?");
    $stmt->execute([$formId]);
    
    // Log activity
    $stmt = $pdo->prepare("SELECT name FROM form_templates WHERE id = ?");
    $stmt->execute([$formId]);
    $formName = $stmt->fetch()['name'];
    logActivity($currentUser['id'], 'custom_metadata_update', "Archived form template: $formName");
    
    echo json_encode([
        'success' => true,
        'message' => 'Form template archived successfully'
    ]);
}

function listFormTemplates($pdo) {
    $stmt = $pdo->query("
        SELECT 
            ft.*,
            COUNT(ff.id) as field_count
        FROM form_templates ft
        LEFT JOIN form_fields ff ON ft.id = ff.form_id
        GROUP BY ft.id
        ORDER BY ft.updated_at DESC
    ");
    $templates = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

function getFormTemplate($pdo, $input) {
    $formId = intval($input['form_id']);
    
    $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
    $stmt->execute([$formId]);
    $template = $stmt->fetch();
    
    if (!$template) {
        throw new Exception('Form template not found');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY display_order");
    $stmt->execute([$formId]);
    $fields = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'template' => $template,
        'fields' => $fields
    ]);
}
```


### 5. Upload Page Integration

**Modified File:** `pages/upload.php`

**Controller Changes:**

```php
// Replace the existing custom fields loading logic with:

// Get active form template
$stmt = $pdo->query("SELECT * FROM form_templates WHERE is_active = 1");
$activeForm = $stmt->fetch();

$formFields = [];
if ($activeForm) {
    // Load form fields
    $stmt = $pdo->prepare("
        SELECT * FROM form_fields 
        WHERE form_id = ? 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$activeForm['id']]);
    $formFields = $stmt->fetchAll();
}

// If editing, get existing custom metadata values
$customMetadataValues = [];
if ($editMode && $editItem) {
    $stmt = $pdo->prepare("
        SELECT field_id, field_value 
        FROM custom_metadata_values 
        WHERE file_id = ?
    ");
    $stmt->execute([$editItem['id']]);
    $values = $stmt->fetchAll();
    foreach ($values as $value) {
        $customMetadataValues[$value['field_id']] = $value['field_value'];
    }
}
```

**Upload Action Changes:**

```php
// In upload action, after inserting into newspapers table:

if ($activeForm && !empty($formFields)) {
    $metaStmt = $pdo->prepare("
        INSERT INTO custom_metadata_values (file_id, form_id, field_id, field_value)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($formFields as $field) {
        $fieldKey = 'field_' . $field['id'];
        $fieldValue = null;
        
        if (isset($_POST[$fieldKey])) {
            if ($field['field_type'] === 'checkbox') {
                $fieldValue = json_encode($_POST[$fieldKey]);
            } else {
                $fieldValue = sanitize($_POST[$fieldKey]);
            }
        }
        
        if ($fieldValue !== null && $fieldValue !== '') {
            $metaStmt->execute([$newId, $activeForm['id'], $field['id'], $fieldValue]);
        }
    }
}
```

**View Changes:** `views/upload.php`

```html
<!-- Replace custom metadata fields section with: -->

<?php if (empty($formFields)): ?>
<!-- Empty State -->
<div class="empty-state-container text-center py-5">
    <div class="empty-state-icon mb-3">
        <i class="bi bi-inbox" style="font-size: 4rem; color: #6c757d;"></i>
    </div>
    <h3 class="empty-state-title">No Metadata Fields Defined</h3>
    <p class="empty-state-description text-muted">
        Please customize your metadata structure to start uploading archives.<br>
        Define fields like Author, Date, and Keywords to keep your library organized.
    </p>
    <a href="form-library.php" class="btn btn-primary mt-3">
        <i class="bi bi-gear"></i> Configure Metadata Fields
    </a>
</div>
<?php else: ?>
<!-- Form Fields -->
<div class="form-section-divider">
    <span class="divider-text">Additional Information</span>
</div>

<?php foreach ($formFields as $field): 
    $fieldName = 'field_' . $field['id'];
    $fieldValue = $customMetadataValues[$field['id']] ?? '';
    $required = $field['is_required'] ? 'required' : '';
    $requiredAttr = $field['is_required'] ? 'data-required="true"' : '';
?>

<div class="mb-3">
    <label for="<?= $fieldName ?>" class="form-label">
        <?= htmlspecialchars($field['field_label']) ?>
        <?php if ($field['is_required']): ?>
            <span class="text-danger">*</span>
        <?php endif; ?>
    </label>
    
    <?php if ($field['help_text']): ?>
        <small class="form-text text-muted d-block mb-1">
            <?= htmlspecialchars($field['help_text']) ?>
        </small>
    <?php endif; ?>
    
    <?php if ($field['field_type'] === 'text'): ?>
        <input type="text" 
               class="form-control custom-field" 
               id="<?= $fieldName ?>" 
               name="<?= $fieldName ?>"
               value="<?= htmlspecialchars($fieldValue) ?>"
               <?= $required ?>
               <?= $requiredAttr ?>>
    
    <?php elseif ($field['field_type'] === 'textarea'): ?>
        <textarea class="form-control custom-field" 
                  id="<?= $fieldName ?>" 
                  name="<?= $fieldName ?>"
                  rows="3"
                  <?= $required ?>
                  <?= $requiredAttr ?>><?= htmlspecialchars($fieldValue) ?></textarea>
    
    <?php elseif ($field['field_type'] === 'number'): ?>
        <input type="number" 
               class="form-control custom-field" 
               id="<?= $fieldName ?>" 
               name="<?= $fieldName ?>"
               value="<?= htmlspecialchars($fieldValue) ?>"
               <?= $required ?>
               <?= $requiredAttr ?>>
    
    <?php elseif ($field['field_type'] === 'date'): ?>
        <input type="date" 
               class="form-control custom-field" 
               id="<?= $fieldName ?>" 
               name="<?= $fieldName ?>"
               value="<?= htmlspecialchars($fieldValue) ?>"
               <?= $required ?>
               <?= $requiredAttr ?>>
    
    <?php elseif ($field['field_type'] === 'select'): 
        $options = json_decode($field['field_options'], true);
    ?>
        <select class="form-select custom-field" 
                id="<?= $fieldName ?>" 
                name="<?= $fieldName ?>"
                <?= $required ?>
                <?= $requiredAttr ?>>
            <option value="">Select an option</option>
            <?php foreach ($options as $option): ?>
                <option value="<?= htmlspecialchars($option) ?>"
                        <?= $fieldValue === $option ? 'selected' : '' ?>>
                    <?= htmlspecialchars($option) ?>
                </option>
            <?php endforeach; ?>
        </select>
    
    <?php elseif ($field['field_type'] === 'checkbox'): 
        $options = json_decode($field['field_options'], true);
        $selectedValues = $fieldValue ? json_decode($fieldValue, true) : [];
    ?>
        <?php foreach ($options as $option): ?>
            <div class="form-check">
                <input class="form-check-input custom-field" 
                       type="checkbox" 
                       name="<?= $fieldName ?>[]" 
                       value="<?= htmlspecialchars($option) ?>"
                       id="<?= $fieldName ?>_<?= md5($option) ?>"
                       <?= in_array($option, $selectedValues) ? 'checked' : '' ?>
                       <?= $requiredAttr ?>>
                <label class="form-check-label" for="<?= $fieldName ?>_<?= md5($option) ?>">
                    <?= htmlspecialchars($option) ?>
                </label>
            </div>
        <?php endforeach; ?>
    
    <?php elseif ($field['field_type'] === 'radio'): 
        $options = json_decode($field['field_options'], true);
    ?>
        <?php foreach ($options as $option): ?>
            <div class="form-check">
                <input class="form-check-input custom-field" 
                       type="radio" 
                       name="<?= $fieldName ?>" 
                       value="<?= htmlspecialchars($option) ?>"
                       id="<?= $fieldName ?>_<?= md5($option) ?>"
                       <?= $fieldValue === $option ? 'checked' : '' ?>
                       <?= $required ?>
                       <?= $requiredAttr ?>>
                <label class="form-check-label" for="<?= $fieldName ?>_<?= md5($option) ?>">
                    <?= htmlspecialchars($option) ?>
                </label>
            </div>
        <?php endforeach; ?>
    
    <?php endif; ?>
</div>

<?php endforeach; ?>
<?php endif; ?>
```


## Migration Strategy

### Migration File: `backend/migrations/002_migrate_to_form_templates.php`

**Purpose:** Migrate from individual custom_metadata_fields to form_templates system.

**Migration Steps:**

1. Create new tables (form_templates, form_fields)
2. Create default form template from existing custom_metadata_fields
3. Migrate existing field definitions to form_fields
4. Add form_id column to custom_metadata_values
5. Update existing custom_metadata_values to reference default form
6. Preserve backward compatibility

**Implementation:**

```php
<?php
/**
 * Migration: Migrate to Form Templates System
 * Archive System - Quezon City Public Library
 * 
 * This migration transforms the custom metadata system from individual
 * field management to template-based form management.
 * 
 * Usage: php backend/migrations/002_migrate_to_form_templates.php
 */

require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        echo "Starting migration: Migrate to Form Templates System\n";
        echo "====================================================\n\n";
        
        $pdo->beginTransaction();
        
        // Check if migration already applied
        $stmt = $pdo->query("SHOW TABLES LIKE 'form_templates'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Migration already applied. Tables exist.\n";
            return;
        }
        
        // Step 1: Create form_templates table
        echo "Step 1: Creating form_templates table...\n";
        $pdo->exec("
            CREATE TABLE form_templates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL COMMENT 'User-facing form template name',
                description TEXT DEFAULT NULL COMMENT 'Optional description of form purpose',
                status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
                is_active TINYINT(1) DEFAULT 0 COMMENT 'Only one form can be active at a time',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores form template definitions'
        ");
        echo "✓ form_templates table created\n\n";
        
        // Step 2: Create form_fields table
        echo "Step 2: Creating form_fields table...\n";
        $pdo->exec("
            CREATE TABLE form_fields (
                id INT PRIMARY KEY AUTO_INCREMENT,
                form_id INT NOT NULL COMMENT 'References form_templates.id',
                field_label VARCHAR(255) NOT NULL COMMENT 'Display label shown to users',
                field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio') NOT NULL,
                field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/checkbox/radio options',
                is_required TINYINT(1) DEFAULT 0 COMMENT '1 = required field, 0 = optional',
                display_order INT DEFAULT 0 COMMENT 'Sort order for display on forms',
                help_text TEXT DEFAULT NULL COMMENT 'Optional help text displayed near field',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (form_id) REFERENCES form_templates(id) ON DELETE CASCADE,
                INDEX idx_form_order (form_id, display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Stores fields within form templates'
        ");
        echo "✓ form_fields table created\n\n";
        
        // Step 3: Check if custom_metadata_fields has data
        echo "Step 3: Checking for existing custom metadata fields...\n";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_metadata_fields");
        $fieldCount = $stmt->fetch()['count'];
        echo "Found $fieldCount existing custom metadata fields\n\n";
        
        if ($fieldCount > 0) {
            // Step 4: Create default form template
            echo "Step 4: Creating default form template...\n";
            $pdo->exec("
                INSERT INTO form_templates (name, description, status, is_active)
                VALUES (
                    'Default Metadata Form',
                    'Migrated from existing custom metadata fields',
                    'active',
                    1
                )
            ");
            $defaultFormId = $pdo->lastInsertId();
            echo "✓ Default form template created (ID: $defaultFormId)\n\n";
            
            // Step 5: Migrate fields to form_fields
            echo "Step 5: Migrating fields to form_fields table...\n";
            $stmt = $pdo->query("
                SELECT * FROM custom_metadata_fields 
                WHERE is_enabled = 1 
                ORDER BY display_order ASC
            ");
            $existingFields = $stmt->fetchAll();
            
            $insertStmt = $pdo->prepare("
                INSERT INTO form_fields 
                (form_id, field_label, field_type, field_options, is_required, display_order, help_text)
                VALUES (?, ?, ?, ?, ?, ?, NULL)
            ");
            
            $fieldMapping = []; // Map old field_id to new field_id
            
            foreach ($existingFields as $field) {
                $insertStmt->execute([
                    $defaultFormId,
                    $field['field_label'],
                    $field['field_type'],
                    $field['field_options'],
                    $field['is_required'],
                    $field['display_order']
                ]);
                
                $newFieldId = $pdo->lastInsertId();
                $fieldMapping[$field['id']] = $newFieldId;
            }
            
            echo "✓ Migrated " . count($existingFields) . " fields\n\n";
        } else {
            echo "No existing fields to migrate\n\n";
            $defaultFormId = null;
        }
        
        // Step 6: Add form_id column to custom_metadata_values
        echo "Step 6: Adding form_id column to custom_metadata_values...\n";
        $pdo->exec("
            ALTER TABLE custom_metadata_values
            ADD COLUMN form_id INT DEFAULT NULL COMMENT 'References form_templates.id' AFTER file_id
        ");
        echo "✓ Column added\n\n";
        
        // Step 7: Add foreign key constraint
        echo "Step 7: Adding foreign key constraint...\n";
        $pdo->exec("
            ALTER TABLE custom_metadata_values
            ADD FOREIGN KEY (form_id) REFERENCES form_templates(id) ON DELETE SET NULL
        ");
        echo "✓ Foreign key added\n\n";
        
        // Step 8: Add index
        echo "Step 8: Adding index on form_id...\n";
        $pdo->exec("
            ALTER TABLE custom_metadata_values
            ADD INDEX idx_form_id (form_id)
        ");
        echo "✓ Index added\n\n";
        
        // Step 9: Update existing custom_metadata_values
        if ($defaultFormId && $fieldCount > 0) {
            echo "Step 9: Updating existing custom_metadata_values...\n";
            
            // Update form_id for all existing values
            $pdo->exec("
                UPDATE custom_metadata_values
                SET form_id = $defaultFormId
                WHERE form_id IS NULL
            ");
            
            // Update field_id mapping
            foreach ($fieldMapping as $oldFieldId => $newFieldId) {
                $stmt = $pdo->prepare("
                    UPDATE custom_metadata_values
                    SET field_id = ?
                    WHERE field_id = ?
                ");
                $stmt->execute([$newFieldId, $oldFieldId]);
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM custom_metadata_values WHERE form_id = $defaultFormId");
            $updatedCount = $stmt->fetch()['count'];
            echo "✓ Updated $updatedCount metadata values\n\n";
        } else {
            echo "Step 9: No existing values to update\n\n";
        }
        
        $pdo->commit();
        
        echo "====================================================\n";
        echo "✓ Migration completed successfully!\n\n";
        echo "Summary:\n";
        echo "  - form_templates table created\n";
        echo "  - form_fields table created\n";
        echo "  - custom_metadata_values table modified\n";
        if ($defaultFormId) {
            echo "  - Default form template created (ID: $defaultFormId)\n";
            echo "  - $fieldCount fields migrated\n";
        }
        echo "\nNext steps:\n";
        echo "  1. Review the default form template in the Form Library\n";
        echo "  2. Create additional form templates as needed\n";
        echo "  3. The old custom_metadata_fields table is preserved for reference\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

function rollbackMigration($pdo) {
    try {
        echo "Starting rollback: Revert Form Templates Migration\n";
        echo "====================================================\n\n";
        
        $pdo->beginTransaction();
        
        echo "WARNING: This will delete all form templates and revert to individual field management!\n";
        echo "Existing custom_metadata_values will be preserved but form_id references will be lost.\n\n";
        
        // Remove form_id column from custom_metadata_values
        echo "Removing form_id column from custom_metadata_values...\n";
        $pdo->exec("ALTER TABLE custom_metadata_values DROP FOREIGN KEY custom_metadata_values_ibfk_2");
        $pdo->exec("ALTER TABLE custom_metadata_values DROP INDEX idx_form_id");
        $pdo->exec("ALTER TABLE custom_metadata_values DROP COLUMN form_id");
        echo "✓ Column removed\n\n";
        
        // Drop form_fields table
        echo "Dropping form_fields table...\n";
        $pdo->exec("DROP TABLE IF EXISTS form_fields");
        echo "✓ form_fields table dropped\n\n";
        
        // Drop form_templates table
        echo "Dropping form_templates table...\n";
        $pdo->exec("DROP TABLE IF EXISTS form_templates");
        echo "✓ form_templates table dropped\n\n";
        
        $pdo->commit();
        
        echo "====================================================\n";
        echo "✓ Rollback completed successfully!\n\n";
        echo "Note: custom_metadata_fields table was preserved.\n";
        echo "You may need to re-enable fields manually.\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "\n✗ Rollback failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Command-line interface
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'up';
    
    if ($command === 'up') {
        runMigration($pdo);
    } elseif ($command === 'down') {
        echo "WARNING: This will delete all form templates!\n";
        echo "Are you sure you want to rollback? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if ($line === 'yes') {
            rollbackMigration($pdo);
        } else {
            echo "Rollback cancelled.\n";
        }
    } else {
        echo "Usage: php 002_migrate_to_form_templates.php [up|down]\n";
        echo "  up   - Run migration (default)\n";
        echo "  down - Rollback migration\n";
    }
} else {
    // Web interface (for testing only - should be disabled in production)
    echo "<pre>";
    runMigration($pdo);
    echo "</pre>";
}
```

### Backward Compatibility Notes

1. **Existing Data Preservation:**
   - All existing custom_metadata_values are preserved
   - Old custom_metadata_fields table is kept for reference
   - Field mappings are maintained through migration

2. **Breaking Changes:**
   - Upload page now requires active form template (shows empty state if none)
   - Individual field management UI is replaced by form template management
   - Field references change from field_name to field_id

3. **Migration Safety:**
   - Transaction-based migration ensures atomicity
   - Rollback capability provided
   - Idempotent migration (can be run multiple times safely)


## Error Handling

### Form Builder Errors

1. **Empty Form Name:** Display inline error, prevent save
2. **No Fields Added:** Display alert, prevent save
3. **Invalid Field Configuration:** Highlight field, show error message
4. **Network Errors:** Display toast notification, allow retry
5. **Concurrent Edits:** Detect version conflicts, prompt user to reload

### Upload Page Errors

1. **No Active Form:** Display empty state with configuration link
2. **Required Field Validation:** Show field-level error messages
3. **Invalid Field Values:** Display validation errors near fields
4. **Save Failures:** Show error toast, preserve form data
5. **Network Timeouts:** Allow retry with preserved data

### Migration Errors

1. **Database Errors:** Rollback transaction, log detailed error
2. **Data Integrity Issues:** Halt migration, provide diagnostic info
3. **Constraint Violations:** Report specific constraint, suggest fix
4. **Partial Migration:** Detect incomplete state, provide recovery steps

### API Error Responses

All API endpoints return consistent error format:

```json
{
  "success": false,
  "message": "Human-readable error message",
  "error_code": "SPECIFIC_ERROR_CODE",
  "details": {}
}
```

**Error Codes:**
- `UNAUTHORIZED`: User not authenticated
- `FORBIDDEN`: User lacks permissions
- `NOT_FOUND`: Resource doesn't exist
- `VALIDATION_ERROR`: Input validation failed
- `CONSTRAINT_VIOLATION`: Database constraint violated
- `CONCURRENT_MODIFICATION`: Resource modified by another user
- `INTERNAL_ERROR`: Unexpected server error


## Correctness Properties

A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.

### Property Reflection

After analyzing all acceptance criteria, I identified several areas of redundancy:

1. **Empty State Properties (1.1-1.6):** These all describe the same UI state and can be tested together as a single example
2. **Active Form Invariant (5.1, 11.2):** These are duplicates - only one property needed
3. **Round-Trip Properties (20.1-20.4):** These describe parts of the same round-trip behavior and should be combined
4. **Form Rendering Properties (8.1-8.6):** While distinct, these can be grouped as they all test form rendering correctness

### Property 1: Active Form Uniqueness Invariant

*For any* point in time, the database SHALL contain at most one form template with is_active = 1.

**Validates: Requirements 5.1, 11.2**

### Property 2: Active Form Deactivation

*For any* form template being set as active, all other form templates SHALL have is_active set to 0 before the new form is activated.

**Validates: Requirements 5.2**

### Property 3: Form Field Rendering Completeness

*For any* active form template with N fields, the upload page SHALL render exactly N input controls in the order specified by display_order.

**Validates: Requirements 8.1, 8.2**

### Property 4: Field Type Rendering Correctness

*For any* form field with field_type T, the rendered HTML SHALL contain an input element of the corresponding type (text→input[type=text], textarea→textarea, select→select, etc.).

**Validates: Requirements 8.3**

### Property 5: Required Field Indicator Display

*For any* form field where is_required = 1, the rendered HTML SHALL include a visual required indicator (asterisk or badge).

**Validates: Requirements 8.4**

### Property 6: Help Text Display

*For any* form field where help_text is not NULL, the rendered HTML SHALL include the help text content near the field.

**Validates: Requirements 8.5**

### Property 7: Field Options Rendering

*For any* form field with field_type in {select, checkbox, radio} and field_options containing N options, the rendered HTML SHALL include exactly N option elements with matching values.

**Validates: Requirements 8.6**

### Property 8: Required Field Validation

*For any* form submission where at least one required field is empty, the system SHALL prevent submission and display error messages for all empty required fields.

**Validates: Requirements 9.1, 9.2, 9.3**

### Property 9: Valid Form Submission

*For any* form submission where all required fields contain values, the system SHALL allow the submission to proceed.

**Validates: Requirements 9.4**

### Property 10: Field Value Persistence

*For any* submitted form with N fields, the system SHALL insert exactly N rows into custom_metadata_values, each containing file_id, form_id, field_id, and field_value.

**Validates: Requirements 10.1, 10.2**

### Property 11: Form Association Preservation

*For any* saved field value, the form_id SHALL match the active form template's ID at the time of submission, and this association SHALL remain unchanged even if the form template is later modified or archived.

**Validates: Requirements 10.3, 10.5**

### Property 12: Metadata Retrieval

*For any* archive with saved metadata, querying custom_metadata_values by file_id SHALL return all field values with their associated form_id and field_id.

**Validates: Requirements 10.4**

### Property 13: Form Duplication Completeness

*For any* form template with N fields being duplicated, the system SHALL create a new form template with N fields where each field has identical properties (field_label, field_type, field_options, is_required, help_text) to the original.

**Validates: Requirements 7.1, 7.4**

### Property 14: Duplicate Form Naming

*For any* form template with name "X" being duplicated, the new form template SHALL have name "X (Copy)".

**Validates: Requirements 7.2**

### Property 15: Duplicate Form Status

*For any* form template being duplicated, the new form template SHALL have status = 'draft' and is_active = 0.

**Validates: Requirements 7.3**

### Property 16: Duplicate Form Identity

*For any* form template being duplicated, the new form template SHALL have a unique ID different from the original form template's ID.

**Validates: Requirements 7.5**

### Property 17: Form Template Round-Trip Serialization

*For any* valid form template with fields, saving the form (serializing to database) then loading it (deserializing from database) SHALL produce an equivalent form structure with identical name, description, status, field count, field order, field properties, and field options.

**Validates: Requirements 20.1, 20.2, 20.3, 20.4**

### Property 18: Migration Field Preservation

*For any* existing custom_metadata_field during migration, the system SHALL create a corresponding form_field with identical field_label, field_type, field_options, is_required, and display_order.

**Validates: Requirements 12.3, 12.4**

### Property 19: Migration Value Association

*For any* existing custom_metadata_value during migration, the system SHALL update its form_id to reference the default form template.

**Validates: Requirements 12.5**

### Property 20: Migration Backward Compatibility

*For any* archive with metadata before migration, after migration completes, querying the archive's metadata SHALL return the same field values as before migration.

**Validates: Requirements 12.6**


## Testing Strategy

### Dual Testing Approach

The system requires both unit tests and property-based tests for comprehensive coverage:

**Unit Tests:** Verify specific examples, edge cases, and error conditions
- Empty state rendering when no active form exists
- Specific field type rendering (text, textarea, select, etc.)
- Migration creates default form with correct name
- Deserialization error handling
- API error responses for invalid inputs
- Concurrent form activation conflicts

**Property-Based Tests:** Verify universal properties across all inputs
- Active form uniqueness invariant (Property 1)
- Form field rendering completeness (Property 3)
- Required field validation (Property 8)
- Form duplication completeness (Property 13)
- Round-trip serialization (Property 17)
- Migration field preservation (Property 18)

Together, unit tests catch concrete bugs while property tests verify general correctness across the input space.

### Property-Based Testing Configuration

**Library Selection:**
- **PHP:** Use [Eris](https://github.com/giorgiosironi/eris) for property-based testing
- Alternative: [PHPUnit with data providers](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.data-providers) for simpler property tests

**Test Configuration:**
- Minimum 100 iterations per property test
- Each test must reference its design document property
- Tag format: `@Feature: form-templates-system, Property {number}: {property_text}`

**Example Property Test Structure:**

```php
/**
 * @Feature: form-templates-system, Property 1: Active Form Uniqueness Invariant
 * @test
 */
public function testActiveFormUniquenessInvariant() {
    $this->forAll(
        Generator\seq(Generator\choose(1, 10))
    )->then(function($formIds) {
        // Create multiple forms
        foreach ($formIds as $id) {
            $this->createFormTemplate("Form $id", 'draft');
        }
        
        // Activate one form
        $this->setActiveForm($formIds[0]);
        
        // Verify only one active form
        $activeCount = $this->countActiveForms();
        $this->assertEquals(1, $activeCount, 
            "Expected exactly 1 active form, found $activeCount");
    });
}
```

### Test Coverage Requirements

**Unit Test Coverage:**
- All API endpoints (create, update, delete, duplicate, archive, set_active)
- All field types rendering (text, textarea, number, date, select, checkbox, radio)
- Empty state display
- Error handling paths
- Migration script execution
- Validation logic

**Property Test Coverage:**
- All 20 correctness properties must have corresponding property tests
- Each property test must run minimum 100 iterations
- Tests must use randomized inputs where applicable
- Tests must verify invariants hold across all generated inputs

### Integration Testing

**Database Integration:**
- Test with actual MySQL database (not mocks)
- Use transactions for test isolation
- Verify foreign key constraints
- Test CASCADE and SET NULL behaviors

**UI Integration:**
- Test form builder drag-and-drop with Selenium/Playwright
- Verify upload page renders active form correctly
- Test form library filtering and search
- Verify empty state display

**Migration Testing:**
- Test migration with various existing data states
- Verify rollback functionality
- Test idempotency (running migration multiple times)
- Verify data integrity after migration

### Performance Testing

**Load Testing:**
- Form with 50+ fields renders in < 500ms
- Form library with 100+ templates loads in < 1s
- Form save operation completes in < 2s
- Upload with 20+ custom fields completes in < 3s

**Stress Testing:**
- Concurrent form activations (verify only one succeeds)
- Concurrent form edits (verify last-write-wins or conflict detection)
- Large form templates (100+ fields)
- Bulk metadata value insertion (1000+ values)


## UI/UX Wireframes

### Form Library Wireframe

```
┌─────────────────────────────────────────────────────────────────┐
│ Form Templates                              [Create New Form]   │
├─────────────────────────────────────────────────────────────────┤
│ [All Forms] [Active] [Drafts] [Archived]                        │
│                                                                  │
│ [Search forms by name or description...                    ]    │
│                                                                  │
│ ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│ │ Book Metadata│  │ Magazine Form│  │ Photo Archive│          │
│ │ [Active]     │  │ [Draft]      │  │ [Draft]      │          │
│ │              │  │              │  │              │          │
│ │ For books and│  │ For magazine │  │ For photo    │          │
│ │ publications │  │ archives     │  │ collections  │          │
│ │              │  │              │  │              │          │
│ │ 12 fields    │  │ 8 fields     │  │ 15 fields    │          │
│ │ Dec 15, 2024 │  │ Dec 14, 2024 │  │ Dec 13, 2024 │          │
│ │              │  │              │  │              │          │
│ │[Edit][Preview│  │[Edit][Preview│  │[Edit][Preview│          │
│ │    [⋮]      ]│  │    [⋮]      ]│  │    [⋮]      ]│          │
│ └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
│ ┌──────────────┐  ┌──────────────┐                             │
│ │ Newspaper    │  │ Audio Archive│                             │
│ │ [Archived]   │  │ [Draft]      │                             │
│ │              │  │              │                             │
│ │ For newspaper│  │ For audio    │                             │
│ │ collections  │  │ files        │                             │
│ │              │  │              │                             │
│ │ 10 fields    │  │ 6 fields     │                             │
│ │ Nov 20, 2024 │  │ Dec 10, 2024 │                             │
│ │              │  │              │                             │
│ │[Edit][Preview│  │[Edit][Preview│                             │
│ │    [⋮]      ]│  │    [⋮]      ]│                             │
│ └──────────────┘  └──────────────┘                             │
└─────────────────────────────────────────────────────────────────┘
```

### Form Builder Wireframe

```
┌─────────────────────────────────────────────────────────────────┐
│ [← Back to Library]              [Preview][Save Draft][Publish] │
├─────────────────────────────────────────────────────────────────┤
│ Form Name: [Book Metadata Form                              ]   │
│ Description: [For cataloging books and publications         ]   │
├─────────────────────────────────────────────────────────────────┤
│ ┌──────────┐ ┌────────────────────────┐ ┌──────────────────┐  │
│ │Field Types│ │   Form Canvas          │ │ Field Settings   │  │
│ ├──────────┤ ├────────────────────────┤ ├──────────────────┤  │
│ │          │ │                        │ │                  │  │
│ │[≡] Text  │ │ [≡] Author Name        │ │ Label:           │  │
│ │          │ │     [Text] [Required]  │ │ [Author Name   ] │  │
│ │[≡] Textarea│ │                      │ │                  │  │
│ │          │ │ [≡] Publication Year   │ │ Type:            │  │
│ │[≡] Number│ │     [Number]           │ │ [Text ▼]         │  │
│ │          │ │                        │ │                  │  │
│ │[≡] Date  │ │ [≡] ISBN               │ │ Help Text:       │  │
│ │          │ │     [Text]             │ │ [Enter author  ] │  │
│ │[≡] Select│ │                        │ │ [full name     ] │  │
│ │          │ │ [≡] Category           │ │                  │  │
│ │[≡] Checkbox│ │   [Select] [Required]│ │ ☑ Required Field │  │
│ │          │ │                        │ │                  │  │
│ │[≡] Radio │ │ [≡] Description        │ │ [Save Changes]   │  │
│ │          │ │     [Textarea]         │ │                  │  │
│ │          │ │                        │ │                  │  │
│ │          │ │ Drag field types here  │ │                  │  │
│ │          │ │ to build your form     │ │                  │  │
│ └──────────┘ └────────────────────────┘ └──────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Upload Page Empty State Wireframe

```
┌─────────────────────────────────────────────────────────────────┐
│ Upload Archive                                                   │
├─────────────────────────────────────────────────────────────────┤
│ File Upload                                                      │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ [Choose File] or drag and drop                              │ │
│ │ Supported: PDF, EPUB, MOBI, Images                          │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                  │
│ Thumbnail                                                        │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ [Choose Thumbnail]                                          │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                  │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │                          📥                                  │ │
│ │                                                              │ │
│ │              No Metadata Fields Defined                     │ │
│ │                                                              │ │
│ │   Please customize your metadata structure to start         │ │
│ │   uploading archives. Define fields like Author, Date,      │ │
│ │   and Keywords to keep your library organized.              │ │
│ │                                                              │ │
│ │              [Configure Metadata Fields]                    │ │
│ │                                                              │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                  │
│                                              [Upload Archive]   │
└─────────────────────────────────────────────────────────────────┘
```

### Upload Page with Active Form Wireframe

```
┌─────────────────────────────────────────────────────────────────┐
│ Upload Archive                                                   │
├─────────────────────────────────────────────────────────────────┤
│ File Upload                                                      │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ [Choose File] or drag and drop                              │ │
│ │ Supported: PDF, EPUB, MOBI, Images                          │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                  │
│ Thumbnail                                                        │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ [Choose Thumbnail]                                          │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                  │
│ ─────────── Additional Information ───────────                  │
│                                                                  │
│ Author Name *                                                    │
│ [                                                            ]   │
│                                                                  │
│ Publication Year                                                 │
│ [                                                            ]   │
│                                                                  │
│ ISBN                                                             │
│ [                                                            ]   │
│                                                                  │
│ Category *                                                       │
│ [Select an option                                         ▼]    │
│                                                                  │
│ Description                                                      │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │                                                              │ │
│ │                                                              │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                  │
│                                              [Upload Archive]   │
└─────────────────────────────────────────────────────────────────┘
```

## CSS Styling Guidelines

### Form Builder Styles

```css
/* Field type items in sidebar */
.field-type-item {
    padding: 12px;
    margin-bottom: 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: move;
    background: white;
    transition: all 0.2s;
}

.field-type-item:hover {
    background: #f8f9fa;
    border-color: #0d6efd;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Form canvas */
.form-canvas {
    min-height: 400px;
    padding: 20px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

.canvas-empty-state {
    color: #6c757d;
}

/* Form field items */
.form-field-item {
    padding: 12px;
    margin-bottom: 12px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}

.form-field-item:hover {
    border-color: #0d6efd;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-field-item.selected {
    border-color: #0d6efd;
    background: #e7f1ff;
}

.field-drag-handle {
    cursor: move;
    color: #6c757d;
    margin-right: 8px;
}

.sortable-ghost {
    opacity: 0.4;
    background: #e7f1ff;
}
```

### Empty State Styles

```css
.empty-state-container {
    padding: 60px 20px;
    text-align: center;
}

.empty-state-icon {
    font-size: 4rem;
    color: #6c757d;
    margin-bottom: 20px;
}

.empty-state-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 12px;
}

.empty-state-description {
    font-size: 1rem;
    color: #6c757d;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 24px;
}
```

### Form Library Styles

```css
.form-template-card {
    transition: transform 0.2s;
}

.form-template-card:hover {
    transform: translateY(-4px);
}

.form-template-card .card {
    height: 100%;
    border: 1px solid #dee2e6;
    transition: box-shadow 0.2s;
}

.form-template-card .card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.form-meta {
    font-size: 0.875rem;
    color: #6c757d;
}

.form-meta i {
    margin-right: 4px;
}
```

## Implementation Notes

### Third-Party Dependencies

**SortableJS:**
- Version: 1.15.0+
- CDN: `https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js`
- Purpose: Drag-and-drop functionality in form builder
- License: MIT

**Bootstrap 5:**
- Already included in existing system
- Used for: Grid layout, cards, modals, forms, buttons

**Bootstrap Icons:**
- Already included in existing system
- Used for: UI icons throughout the interface

### Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

**Required Features:**
- CSS Grid
- Flexbox
- Fetch API
- ES6 JavaScript
- HTML5 Drag and Drop API

### Security Considerations

1. **SQL Injection Prevention:** Use prepared statements for all database queries
2. **XSS Prevention:** Sanitize all user input, escape output with htmlspecialchars()
3. **CSRF Protection:** Validate session tokens on all state-changing operations
4. **Authorization:** Check admin role on all form management endpoints
5. **Input Validation:** Validate field types, options format, and required fields
6. **Rate Limiting:** Limit API requests to prevent abuse
7. **Audit Logging:** Log all form template changes with user ID and timestamp

### Performance Optimizations

1. **Database Indexes:** Ensure indexes on form_id, is_active, display_order
2. **Query Optimization:** Use JOIN to fetch form with fields in single query
3. **Caching:** Cache active form template to reduce database queries
4. **Lazy Loading:** Load form library cards progressively for large datasets
5. **Debouncing:** Debounce search input to reduce unnecessary filtering
6. **Transaction Batching:** Use transactions for multi-row operations

