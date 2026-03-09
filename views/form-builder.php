<?php
/**
 * Form Builder View
 * Archive System - Quezon City Public Library
 */

$pageTitle = $editMode ? "Edit Form Template" : "Create Form Template";
include __DIR__ . '/layouts/header.php';
?>

<link rel="stylesheet" href="../assets/css/admin_pages/form-builder.css">

<div class="container-fluid py-4 form-builder-container">
    <!-- Header -->
    <div class="form-builder-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Custom Metadata</h4>
        </div>
        <div>
            <a href="form-library.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Library
            </a>
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
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="formName" class="form-label">Form Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="formName" placeholder="e.g., Book Metadata Form"
                        value="<?= htmlspecialchars($formTemplate['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="formDescription" class="form-label">Description</label>
                    <textarea class="form-control" id="formDescription" rows="1"
                        placeholder="Optional description of this form's purpose"><?= htmlspecialchars($formTemplate['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Builder Area -->
    <div class="row">
        <!-- Field Types Sidebar -->
        <div class="col-md-3 mb-4">
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
                        <div class="field-type-item" draggable="true" data-field-type="tags">
                            <i class="bi bi-tags"></i> Tags
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Canvas -->
        <div class="col-md-6 mb-4">
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
        <div class="col-md-3 mb-4">
            <div class="card" id="fieldConfigPanel" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">Field Settings</h6>
                </div>
                <div class="card-body">
                    <input type="hidden" id="selectedFieldId">

                    <div class="mb-3">
                        <label for="fieldLabel" class="form-label">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="fieldLabel" required>
                        <small class="form-text text-muted">Press Enter to save</small>
                    </div>

                    <div class="mb-3">
                        <label for="fieldType" class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="fieldType" required>
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="select">Select Dropdown</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="radio">Radio Buttons</option>
                            <option value="tags">Tags</option>
                        </select>
                    </div>

                    <div class="mb-3" id="fieldOptionsContainer" style="display: none;">
                        <label class="form-label">Options <span class="text-danger">*</span></label>
                        <div id="optionsList"></div>
                        <button class="btn btn-sm btn-outline-primary mt-2 w-100" id="addOptionBtn">
                            <i class="bi bi-plus"></i> Add Option
                        </button>
                    </div>

                    <div class="mb-3">
                        <label for="fieldPlaceholder" class="form-label">Placeholder</label>
                        <input type="text" class="form-control" id="fieldPlaceholder" placeholder="e.g., Enter value here...">
                        <small class="form-text text-muted">Hint text shown in the input field</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fieldRequired">
                            <label class="form-check-label" for="fieldRequired">
                                Required Field
                            </label>
                        </div>
                    </div>
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

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="../assets/js/admin_pages/form-builder.js"></script>

<?php include __DIR__ . '/layouts/footer.php'; ?>