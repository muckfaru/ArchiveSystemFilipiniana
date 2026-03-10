# Design Document: Custom Metadata Form Builder System

## Overview

The Custom Metadata Form Builder System extends the existing archive upload functionality to support dynamic, administrator-defined metadata fields. This system enables administrators to create custom form fields, display them on the upload form, store their values, and build an advanced drag-and-drop form builder interface.

### Design Goals

- Maintain backward compatibility with existing upload functionality
- Support incremental deployment across 6 implementation phases
- Minimize disruption to current user workflows
- Leverage existing architecture patterns (PHP with PDO, Bootstrap 5, Vanilla JavaScript)
- Ensure data integrity through database transactions
- Provide intuitive administrative interfaces for field management

### System Context

The system integrates with the existing Quezon City Public Library Archive System, which currently supports:
- Single file uploads (PDF, MOBI, EPUB, images)
- Bulk image uploads (gallery/CBZ mode)
- Core metadata fields (title, publisher, date, category, language)
- File editing and management
- User authentication and activity logging

The custom metadata system adds a layer of extensibility without modifying core upload logic.

## Architecture

### High-Level Architecture


```
┌─────────────────────────────────────────────────────────────────┐
│                         User Interface Layer                     │
├─────────────────────────────────────────────────────────────────┤
│  Upload Form    │  Field Manager  │  Form Builder  │  Dashboard │
│  (views/        │  (pages/        │  (pages/       │  (views/   │
│   upload.php)   │   metadata-     │   metadata-    │   dashboard│
│                 │   fields.php)   │   fields.php)  │   .php)    │
└────────┬────────┴────────┬────────┴────────┬───────┴────────┬───┘
         │                 │                 │                │
         │                 │                 │                │
┌────────▼─────────────────▼─────────────────▼────────────────▼───┐
│                    JavaScript Layer                              │
├──────────────────────────────────────────────────────────────────┤
│  upload.js       │  metadata-      │  form-builder. │  progress- │
│  (existing)      │  fields.js      │  js            │  bar.js    │
│                  │  (new)          │  (new)         │  (new)     │
└────────┬─────────┴────────┬────────┴────────┬───────┴────────┬───┘
         │                  │                 │                │
         │                  │                 │                │
┌────────▼──────────────────▼─────────────────▼────────────────▼───┐
│                      Backend API Layer                           │
├──────────────────────────────────────────────────────────────────┤
│  pages/upload.php │  backend/api/  │  backend/api/  │  backend/  │
│  (existing)       │  custom-       │  custom-       │  core/     │
│                   │  metadata.php  │  fields.php    │  functions │
│                   │  (new)         │  (new)         │  .php      │
└────────┬──────────┴────────┬───────┴────────┬───────┴────────┬───┘
         │                   │                │                │
         │                   │                │                │
┌────────▼───────────────────▼────────────────▼────────────────▼───┐
│                       Database Layer                             │
├──────────────────────────────────────────────────────────────────┤
│  newspapers      │  custom_        │  custom_       │  activity_ │
│  (existing)      │  metadata_      │  metadata_     │  logs      │
│                  │  fields (new)   │  values (new)  │  (existing)│
└──────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities



**User Interface Layer:**
- Upload Form: Renders core and custom fields, handles user input
- Field Manager: Administrative interface for CRUD operations on field definitions
- Form Builder: Drag-and-drop visual designer for field layouts
- Dashboard/Browse: Displays custom metadata values alongside core fields

**JavaScript Layer:**
- upload.js: Existing file handling, validation, and submission logic
- progress-bar.js: Real-time completion percentage calculation
- metadata-fields.js: Field manager interactions (add, edit, delete, toggle)
- form-builder.js: Drag-and-drop functionality, preview mode, auto-save

**Backend API Layer:**
- pages/upload.php: Existing upload controller, extended for custom metadata
- backend/api/custom-metadata.php: CRUD endpoints for custom metadata values
- backend/api/custom-fields.php: CRUD endpoints for field definitions
- backend/core/functions.php: Helper functions for metadata operations

**Database Layer:**
- newspapers: Existing table for core file metadata
- custom_metadata_fields: New table for field definitions
- custom_metadata_values: New table for user-entered custom data
- activity_logs: Extended to log custom metadata operations

### Data Flow

#### Upload Flow with Custom Metadata

```
1. User loads upload form
   ↓
2. PHP queries custom_metadata_fields WHERE is_enabled = 1
   ↓
3. PHP renders custom fields in HTML
   ↓
4. JavaScript initializes progress bar (includes custom required fields)
   ↓
5. User fills form fields
   ↓
6. JavaScript validates client-side (including custom validation rules)
   ↓
7. User submits form
   ↓
8. PHP validates server-side
   ↓
9. PHP begins database transaction
   ↓
10. PHP inserts into newspapers table
    ↓
11. PHP gets lastInsertId() as file_id
    ↓
12. PHP inserts custom_metadata_values (one row per custom field)
    ↓
13. PHP commits transaction
    ↓
14. PHP logs activity
    ↓
15. PHP returns success response
```

#### Field Management Flow

```
1. Admin navigates to Field Manager
   ↓
2. PHP queries all custom_metadata_fields
   ↓
3. PHP renders field list table
   ↓
4. Admin clicks "Add Field"
   ↓
5. JavaScript displays modal form
   ↓
6. Admin fills field properties
   ↓
7. JavaScript validates client-side
   ↓
8. JavaScript POSTs to backend/api/custom-fields.php
   ↓
9. PHP validates (uniqueness, field_type, etc.)
   ↓
10. PHP inserts into custom_metadata_fields
    ↓
11. PHP logs activity
    ↓
12. PHP returns JSON response
    ↓
13. JavaScript updates UI without page refresh
```

## Components and Interfaces



### Phase 1: Progress Bar Component

**File:** `assets/js/pages/progress-bar.js`

**Purpose:** Calculate and display form completion percentage in real-time.

**Public API:**
```javascript
class ProgressBar {
  constructor(containerId, requiredFields)
  updateProgress()
  getCompletionPercentage()
  addRequiredField(fieldId)
  removeRequiredField(fieldId)
}
```

**Integration Points:**
- Hooks into existing upload.js field change events
- Reads required fields from DOM data attributes
- Updates visual bar and percentage text
- Supports bulk upload mode (per-tab calculation)

**UI Specification:**
```html
<div class="progress-bar-container">
  <div class="progress-bar-header">
    <span class="progress-label">Form Completion</span>
    <span class="progress-percentage">0%</span>
  </div>
  <div class="progress-bar-track">
    <div class="progress-bar-fill" style="width: 0%; background-color: #dc3545;"></div>
  </div>
</div>
```

**Color Coding:**
- 0-33%: Red (#dc3545)
- 34-66%: Yellow (#ffc107)
- 67-100%: Green (#28a745)

**CSS Classes:**
- `.progress-bar-container`: Outer wrapper
- `.progress-bar-fill`: Animated width transition (0.3s ease)
- `.progress-percentage`: Bold, right-aligned text

### Phase 2: Database Schema

**Migration File:** `backend/migrations/001_create_custom_metadata_tables.php`

**Table: custom_metadata_fields**

```sql
CREATE TABLE custom_metadata_fields (
    id INT PRIMARY KEY AUTO_INCREMENT,
    field_name VARCHAR(100) NOT NULL UNIQUE,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio') NOT NULL,
    field_options TEXT DEFAULT NULL COMMENT 'JSON array for select/checkbox/radio',
    is_required TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    validation_rules TEXT DEFAULT NULL COMMENT 'JSON object with validation config',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled_order (is_enabled, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Table: custom_metadata_values**

```sql
CREATE TABLE custom_metadata_values (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    field_id INT DEFAULT NULL,
    field_value TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES newspapers(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES custom_metadata_fields(id) ON DELETE SET NULL,
    INDEX idx_file_id (file_id),
    INDEX idx_field_id (field_id),
    UNIQUE KEY unique_file_field (file_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Migration Script Structure:**

```php
<?php
require_once __DIR__ . '/../core/config.php';

function runMigration($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Check if tables already exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'custom_metadata_fields'");
        if ($stmt->rowCount() > 0) {
            echo "Migration already applied.\n";
            return;
        }
        
        // Create custom_metadata_fields table
        $pdo->exec("CREATE TABLE custom_metadata_fields ...");
        
        // Create custom_metadata_values table
        $pdo->exec("CREATE TABLE custom_metadata_values ...");
        
        // Update activity_logs enum to include custom_metadata_update
        $pdo->exec("ALTER TABLE activity_logs MODIFY action ENUM(
            'create_user', 'edit_user', 'delete_user', 'upload', 'edit', 
            'delete', 'restore', 'permanent_delete', 'login', 'logout', 
            'settings_update', 'custom_metadata_update'
        ) NOT NULL");
        
        $pdo->commit();
        echo "Migration completed successfully.\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}

runMigration($pdo);
```

### Phase 3: Custom Field Manager Interface

**File:** `pages/metadata-fields.php`

**Controller Logic:**

```php
<?php
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Check admin permissions
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    redirect('dashboard.php?error=' . urlencode('Access denied'));
}

// Get all custom fields
$stmt = $pdo->query("
    SELECT * FROM custom_metadata_fields 
    ORDER BY display_order ASC, created_at DESC
");
$customFields = $stmt->fetchAll();

// Load view
include __DIR__ . '/../views/metadata-fields.php';
```

**View Structure:** `views/metadata-fields.php`



```html
<div class="page-header">
    <h1>Custom Metadata Fields</h1>
    <button class="btn btn-primary" id="addFieldBtn">
        <i class="bi bi-plus-circle"></i> Add Field
    </button>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Label</th>
                <th>Type</th>
                <th>Required</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="fieldsTableBody">
            <?php foreach ($customFields as $field): ?>
            <tr data-field-id="<?= $field['id'] ?>">
                <td><?= htmlspecialchars($field['field_label']) ?></td>
                <td><span class="badge bg-secondary"><?= $field['field_type'] ?></span></td>
                <td><?= $field['is_required'] ? '<i class="bi bi-check-circle text-success"></i>' : '' ?></td>
                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input toggle-field" type="checkbox" 
                               data-field-id="<?= $field['id'] ?>"
                               <?= $field['is_enabled'] ? 'checked' : '' ?>>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary edit-field" 
                            data-field-id="<?= $field['id'] ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-field" 
                            data-field-id="<?= $field['id'] ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Field Modal -->
<div class="modal fade" id="fieldModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fieldModalTitle">Add Custom Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="fieldForm">
                    <input type="hidden" id="fieldId" name="field_id">
                    
                    <div class="mb-3">
                        <label for="fieldLabel" class="form-label">Field Label *</label>
                        <input type="text" class="form-control" id="fieldLabel" 
                               name="field_label" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fieldName" class="form-label">Field Name *</label>
                        <input type="text" class="form-control" id="fieldName" 
                               name="field_name" required 
                               pattern="[a-zA-Z0-9_]+"
                               title="Only letters, numbers, and underscores">
                        <small class="text-muted">Used internally (e.g., author_name)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fieldType" class="form-label">Field Type *</label>
                        <select class="form-select" id="fieldType" name="field_type" required>
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
                        <label for="fieldOptions" class="form-label">Options *</label>
                        <input type="text" class="form-control" id="fieldOptions" 
                               name="field_options"
                               placeholder="Option 1, Option 2, Option 3">
                        <small class="text-muted">Comma-separated values</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   id="isRequired" name="is_required">
                            <label class="form-check-label" for="isRequired">
                                Required Field
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger" id="fieldFormError" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFieldBtn">Save Field</button>
            </div>
        </div>
    </div>
</div>
```

**JavaScript:** `assets/js/pages/metadata-fields.js`

```javascript
// Field Manager JavaScript
const FieldManager = {
    init() {
        this.bindEvents();
        this.loadFields();
    },
    
    bindEvents() {
        document.getElementById('addFieldBtn').addEventListener('click', () => {
            this.openModal();
        });
        
        document.getElementById('saveFieldBtn').addEventListener('click', () => {
            this.saveField();
        });
        
        document.getElementById('fieldType').addEventListener('change', (e) => {
            this.toggleOptionsField(e.target.value);
        });
        
        // Delegate events for dynamic elements
        document.addEventListener('click', (e) => {
            if (e.target.closest('.edit-field')) {
                const fieldId = e.target.closest('.edit-field').dataset.fieldId;
                this.editField(fieldId);
            }
            if (e.target.closest('.delete-field')) {
                const fieldId = e.target.closest('.delete-field').dataset.fieldId;
                this.deleteField(fieldId);
            }
        });
        
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('toggle-field')) {
                const fieldId = e.target.dataset.fieldId;
                const isEnabled = e.target.checked;
                this.toggleField(fieldId, isEnabled);
            }
        });
    },
    
    toggleOptionsField(fieldType) {
        const container = document.getElementById('fieldOptionsContainer');
        const input = document.getElementById('fieldOptions');
        
        if (['select', 'checkbox', 'radio'].includes(fieldType)) {
            container.style.display = 'block';
            input.required = true;
        } else {
            container.style.display = 'none';
            input.required = false;
        }
    },
    
    async saveField() {
        const form = document.getElementById('fieldForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        const fieldId = document.getElementById('fieldId').value;
        const action = fieldId ? 'update' : 'create';
        
        formData.append('action', action);
        
        try {
            const response = await fetch('/backend/api/custom-fields.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('fieldModal')).hide();
                this.loadFields();
                this.showAlert('success', result.message);
            } else {
                document.getElementById('fieldFormError').textContent = result.message;
                document.getElementById('fieldFormError').style.display = 'block';
            }
        } catch (error) {
            console.error('Error saving field:', error);
            this.showAlert('danger', 'An error occurred while saving the field');
        }
    },
    
    // Additional methods: loadFields, editField, deleteField, toggleField, etc.
};

document.addEventListener('DOMContentLoaded', () => {
    FieldManager.init();
});
```

**API Endpoint:** `backend/api/custom-fields.php`



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

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createField($pdo, $currentUser);
            break;
        case 'update':
            updateField($pdo, $currentUser);
            break;
        case 'delete':
            deleteField($pdo, $currentUser);
            break;
        case 'toggle':
            toggleField($pdo, $currentUser);
            break;
        case 'list':
            listFields($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createField($pdo, $currentUser) {
    $fieldName = sanitize($_POST['field_name']);
    $fieldLabel = sanitize($_POST['field_label']);
    $fieldType = $_POST['field_type'];
    $isRequired = isset($_POST['is_required']) ? 1 : 0;
    
    // Validate field_name pattern
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $fieldName)) {
        throw new Exception('Field name must contain only letters, numbers, and underscores');
    }
    
    // Validate field_type
    $allowedTypes = ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio'];
    if (!in_array($fieldType, $allowedTypes)) {
        throw new Exception('Invalid field type');
    }
    
    // Check uniqueness
    $stmt = $pdo->prepare("SELECT id FROM custom_metadata_fields WHERE field_name = ?");
    $stmt->execute([$fieldName]);
    if ($stmt->fetch()) {
        throw new Exception('A field with this name already exists');
    }
    
    // Handle field_options
    $fieldOptions = null;
    if (in_array($fieldType, ['select', 'checkbox', 'radio'])) {
        $optionsInput = $_POST['field_options'] ?? '';
        if (empty($optionsInput)) {
            throw new Exception('Options are required for this field type');
        }
        $optionsArray = array_map('trim', explode(',', $optionsInput));
        $fieldOptions = json_encode($optionsArray);
    }
    
    // Get next display_order
    $stmt = $pdo->query("SELECT MAX(display_order) as max_order FROM custom_metadata_fields");
    $maxOrder = $stmt->fetch()['max_order'] ?? 0;
    $displayOrder = $maxOrder + 1;
    
    // Insert field
    $stmt = $pdo->prepare("
        INSERT INTO custom_metadata_fields 
        (field_name, field_label, field_type, field_options, is_required, display_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$fieldName, $fieldLabel, $fieldType, $fieldOptions, $isRequired, $displayOrder]);
    
    // Log activity
    logActivity($currentUser['id'], 'custom_metadata_update', "Created field: $fieldLabel");
    
    echo json_encode(['success' => true, 'message' => 'Field created successfully']);
}

function updateField($pdo, $currentUser) {
    $fieldId = intval($_POST['field_id']);
    $fieldLabel = sanitize($_POST['field_label']);
    $fieldType = $_POST['field_type'];
    $isRequired = isset($_POST['is_required']) ? 1 : 0;
    
    // Validate field exists
    $stmt = $pdo->prepare("SELECT * FROM custom_metadata_fields WHERE id = ?");
    $stmt->execute([$fieldId]);
    $field = $stmt->fetch();
    if (!$field) {
        throw new Exception('Field not found');
    }
    
    // Handle field_options
    $fieldOptions = null;
    if (in_array($fieldType, ['select', 'checkbox', 'radio'])) {
        $optionsInput = $_POST['field_options'] ?? '';
        if (empty($optionsInput)) {
            throw new Exception('Options are required for this field type');
        }
        $optionsArray = array_map('trim', explode(',', $optionsInput));
        $fieldOptions = json_encode($optionsArray);
    }
    
    // Update field
    $stmt = $pdo->prepare("
        UPDATE custom_metadata_fields 
        SET field_label = ?, field_type = ?, field_options = ?, is_required = ?
        WHERE id = ?
    ");
    $stmt->execute([$fieldLabel, $fieldType, $fieldOptions, $isRequired, $fieldId]);
    
    // Log activity
    logActivity($currentUser['id'], 'custom_metadata_update', "Updated field: $fieldLabel");
    
    echo json_encode(['success' => true, 'message' => 'Field updated successfully']);
}

function deleteField($pdo, $currentUser) {
    $fieldId = intval($_POST['field_id']);
    
    // Check if field has values
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM custom_metadata_values WHERE field_id = ?");
    $stmt->execute([$fieldId]);
    $count = $stmt->fetch()['count'];
    
    if ($count > 0 && !isset($_POST['confirm'])) {
        echo json_encode([
            'success' => false, 
            'message' => "This field has $count values. Are you sure?",
            'requires_confirmation' => true,
            'affected_count' => $count
        ]);
        return;
    }
    
    // Soft delete (set is_enabled = 0)
    $stmt = $pdo->prepare("UPDATE custom_metadata_fields SET is_enabled = 0 WHERE id = ?");
    $stmt->execute([$fieldId]);
    
    // Log activity
    $stmt = $pdo->prepare("SELECT field_label FROM custom_metadata_fields WHERE id = ?");
    $stmt->execute([$fieldId]);
    $fieldLabel = $stmt->fetch()['field_label'];
    logActivity($currentUser['id'], 'custom_metadata_update', "Deleted field: $fieldLabel");
    
    echo json_encode(['success' => true, 'message' => 'Field deleted successfully']);
}

function toggleField($pdo, $currentUser) {
    $fieldId = intval($_POST['field_id']);
    $isEnabled = intval($_POST['is_enabled']);
    
    $stmt = $pdo->prepare("UPDATE custom_metadata_fields SET is_enabled = ? WHERE id = ?");
    $stmt->execute([$isEnabled, $fieldId]);
    
    echo json_encode(['success' => true]);
}

function listFields($pdo) {
    $stmt = $pdo->query("
        SELECT * FROM custom_metadata_fields 
        ORDER BY display_order ASC, created_at DESC
    ");
    $fields = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'fields' => $fields]);
}
```

### Phase 4: Display Custom Fields on Upload Form

**Modified File:** `pages/upload.php`

**Changes to Controller:**



```php
// Add after line 11 (after getting categories and languages)

// Get enabled custom fields
$stmt = $pdo->query("
    SELECT * FROM custom_metadata_fields 
    WHERE is_enabled = 1 
    ORDER BY display_order ASC
");
$customFields = $stmt->fetchAll();

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

**Changes to Upload Action (Single File):**

```php
// Add after successful newspapers INSERT (around line 280)

if ($action === 'upload') {
    // ... existing upload logic ...
    
    if ($stmt->execute([...])) {
        $newId = $pdo->lastInsertId();
        
        // Insert custom metadata values
        if (!empty($customFields)) {
            $metaStmt = $pdo->prepare("
                INSERT INTO custom_metadata_values (file_id, field_id, field_value)
                VALUES (?, ?, ?)
            ");
            
            foreach ($customFields as $field) {
                $fieldKey = 'custom_' . $field['field_name'];
                $fieldValue = null;
                
                if (isset($_POST[$fieldKey])) {
                    if ($field['field_type'] === 'checkbox') {
                        // Checkbox values come as array
                        $fieldValue = json_encode($_POST[$fieldKey]);
                    } else {
                        $fieldValue = sanitize($_POST[$fieldKey]);
                    }
                }
                
                // Only insert if value is not empty or field is required
                if ($fieldValue !== null && $fieldValue !== '') {
                    $metaStmt->execute([$newId, $field['id'], $fieldValue]);
                }
            }
        }
        
        // ... rest of existing logic ...
    }
}
```

**Changes to Edit Action:**

```php
if ($action === 'edit') {
    // ... existing edit logic ...
    
    // Update custom metadata values
    if (!empty($customFields)) {
        foreach ($customFields as $field) {
            $fieldKey = 'custom_' . $field['field_name'];
            $fieldValue = null;
            
            if (isset($_POST[$fieldKey])) {
                if ($field['field_type'] === 'checkbox') {
                    $fieldValue = json_encode($_POST[$fieldKey]);
                } else {
                    $fieldValue = sanitize($_POST[$fieldKey]);
                }
            }
            
            // Check if value already exists
            $stmt = $pdo->prepare("
                SELECT id FROM custom_metadata_values 
                WHERE file_id = ? AND field_id = ?
            ");
            $stmt->execute([$editId, $field['id']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing value
                $stmt = $pdo->prepare("
                    UPDATE custom_metadata_values 
                    SET field_value = ? 
                    WHERE file_id = ? AND field_id = ?
                ");
                $stmt->execute([$fieldValue, $editId, $field['id']]);
            } else if ($fieldValue !== null && $fieldValue !== '') {
                // Insert new value
                $stmt = $pdo->prepare("
                    INSERT INTO custom_metadata_values (file_id, field_id, field_value)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$editId, $field['id'], $fieldValue]);
            }
        }
    }
    
    // ... rest of existing logic ...
}
```

**Changes to Bulk Upload Action:**

```php
if ($action === 'bulk_image_upload') {
    // ... existing bulk upload logic ...
    
    if ($stmt->execute([...])) {
        $newId = $pdo->lastInsertId();
        
        // Insert custom metadata values (same as single upload)
        if (!empty($customFields)) {
            $metaStmt = $pdo->prepare("
                INSERT INTO custom_metadata_values (file_id, field_id, field_value)
                VALUES (?, ?, ?)
            ");
            
            foreach ($customFields as $field) {
                $fieldKey = 'custom_' . $field['field_name'];
                $fieldValue = null;
                
                if (isset($_POST[$fieldKey])) {
                    if ($field['field_type'] === 'checkbox') {
                        $fieldValue = json_encode($_POST[$fieldKey]);
                    } else {
                        $fieldValue = sanitize($_POST[$fieldKey]);
                    }
                }
                
                if ($fieldValue !== null && $fieldValue !== '') {
                    $metaStmt->execute([$newId, $field['id'], $fieldValue]);
                }
            }
        }
        
        // ... rest of existing logic ...
    }
}
```

**Modified View:** `views/upload.php`

**Add Custom Fields Section (after core fields, around line 250):**

```php
<!-- Custom Metadata Fields -->
<?php if (!empty($customFields)): ?>
<div class="form-section-divider">
    <span class="divider-text">Additional Information</span>
</div>

<?php foreach ($customFields as $field): 
    $fieldName = 'custom_' . $field['field_name'];
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

**JavaScript Integration:** Modify `assets/js/pages/upload.js`

