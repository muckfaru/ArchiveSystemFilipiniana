<?php
/**
 * Form Builder View
 * Archive System - Quezon City Public Library
 */

$pageTitle = $editMode ? "Edit Form Template" : "Create Form Template";
include __DIR__ . '/layouts/header.php';
?>

<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin_pages/form-builder.css">

<div class="container-fluid py-4 form-builder-container">
    <!-- Header -->
    <div class="form-builder-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <?php if ($editMode): ?>
                <div class="edit-mode-banner-title">Editing Existing Custom Metadata</div>
                <p class="form-builder-subtitle mb-0">
                    You are editing <strong><?= htmlspecialchars($formTemplate['name'] ?? 'this custom metadata form') ?></strong>. Changes here will update the existing metadata configuration, not create a new one.
                </p>
            <?php else: ?>
                <h4 class="mb-0">Custom Metadata</h4>
                <p class="form-builder-subtitle mb-0">
                    Create a custom metadata form for archived materials using the visual builder below.
                </p>
            <?php endif; ?>
        </div>
        <div class="form-builder-actions">
            <a href="<?= route_url('form-library') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to page
            </a>
            <?php if (!$editMode): ?>
                <button class="btn btn-secondary" id="saveDraftBtn">
                    <i class="bi bi-save"></i> Save as Draft
                </button>
            <?php endif; ?>
            <button class="btn btn-primary" id="publishFormBtn">
                <i class="bi bi-check-circle"></i> <?= $editMode ? 'Save Changes' : 'Publish' ?>
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
    <div class="builder-columns">

        <!-- ── Field Types Panel ────────────────────────── -->
        <div class="builder-panel builder-types-panel">
            <div class="builder-panel-header">
                <h6>Field Types</h6>
                <p class="builder-panel-hint mb-0">Drag or click <strong>+</strong> to add</p>
            </div>
            <div class="builder-panel-body">
                <div class="field-type-list">

                    <div class="field-type-item" draggable="true" data-field-type="text">
                        <div class="field-type-icon"><i class="bi bi-input-cursor-text"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Text</span>
                            <small>Single-line input</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Text field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <div class="field-type-item" draggable="true" data-field-type="textarea">
                        <div class="field-type-icon"><i class="bi bi-textarea-t"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Textarea</span>
                            <small>Long-form text</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Textarea field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <div class="field-type-item" draggable="true" data-field-type="number">
                        <div class="field-type-icon"><i class="bi bi-123"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Number</span>
                            <small>Numeric values</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Number field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <div class="field-type-item" draggable="true" data-field-type="date">
                        <div class="field-type-icon"><i class="bi bi-calendar3"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Date</span>
                            <small>Calendar picker</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Date field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <div class="field-type-item" draggable="true" data-field-type="select">
                        <div class="field-type-icon"><i class="bi bi-chevron-expand"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Dropdown</span>
                            <small>One selection</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Select field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <div class="field-type-item" draggable="true" data-field-type="checkbox">
                        <div class="field-type-icon"><i class="bi bi-check2-square"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Checkbox</span>
                            <small>Multiple choices</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Checkbox field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <div class="field-type-item" draggable="true" data-field-type="radio">
                        <div class="field-type-icon"><i class="bi bi-record-circle"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Radio</span>
                            <small>Single option</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Radio field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <div class="field-type-item" draggable="true" data-field-type="tags">
                        <div class="field-type-icon"><i class="bi bi-tags"></i></div>
                        <div class="field-type-content">
                            <span class="field-type-name">Tags</span>
                            <small>Keyword chips</small>
                        </div>
                        <button type="button" class="field-type-add-btn" aria-label="Add Tags field"><i class="bi bi-plus-lg"></i></button>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Form Canvas Panel ─────────────────────────── -->
        <div class="builder-panel builder-canvas-panel">
            <div class="builder-panel-header">
                <h6>Form Canvas</h6>
                <p class="builder-panel-hint mb-0">Build the metadata structure here</p>
            </div>
            <div class="builder-panel-body">
                <div id="formCanvas" class="form-canvas">
                    <?php if (empty($formFields)): ?>
                        <div class="canvas-empty-state">
                            <div class="canvas-empty-icon">
                                <i class="bi bi-plus-square-dotted"></i>
                            </div>
                            <p class="canvas-empty-title">Start building your form</p>
                            <p class="text-muted">Drag field types here or click the <strong>+</strong> button from the left panel.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($formFields as $index => $field): ?>
                            <div class="form-field-item" data-field-id="<?= $field['id'] ?>">
                                <div class="field-item-header">
                                    <span class="field-drag-handle" title="Drag to reorder">
                                        <i class="bi bi-grip-vertical"></i>
                                    </span>
                                    <span class="field-order-badge"><?= $index + 1 ?></span>
                                    <span class="field-label"><?= htmlspecialchars($field['field_label']) ?></span>
                                    <span class="field-type-badge badge bg-secondary"><?= $field['field_type'] ?></span>
                                    <?php if ($field['is_required']): ?>
                                        <span class="badge bg-danger">Required</span>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger delete-field ms-auto">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Field Settings Panel ──────────────────────── -->
        <div class="builder-panel builder-settings-panel" id="fieldConfigPanel" style="display:none;">
            <div class="builder-panel-header">
                <h6>Field Settings</h6>
                <p class="builder-panel-hint mb-0">Configure the selected field</p>
            </div>
            <div class="builder-panel-body">
                <input type="hidden" id="selectedFieldId">

                <div class="mb-3">
                    <label for="fieldLabel" class="form-label">Label <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="fieldLabel" required>
                    <small class="form-text text-muted" style="font-size:0.7rem;">Press Enter or click away to save</small>
                </div>

                <div class="mb-3">
                    <label for="fieldType" class="form-label">Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="fieldType" required>
                        <option value="text">Text</option>
                        <option value="textarea">Textarea</option>
                        <option value="number">Number</option>
                        <option value="date">Date</option>
                        <option value="select">Dropdown</option>
                        <option value="checkbox">Checkbox</option>
                        <option value="radio">Radio Buttons</option>
                        <option value="tags">Tags</option>
                    </select>
                </div>

                <div class="mb-3" id="fieldOptionsContainer" style="display:none;">
                    <label class="form-label">Options <span class="text-danger">*</span></label>
                    <div id="optionsList"></div>
                    <button class="btn btn-sm btn-outline-primary mt-2 w-100" id="addOptionBtn">
                        <i class="bi bi-plus"></i> Add Option
                    </button>
                </div>

                <div class="mb-3">
                    <label for="fieldPlaceholder" class="form-label">Placeholder</label>
                    <input type="text" class="form-control" id="fieldPlaceholder" placeholder="e.g., Enter value…">
                    <small class="form-text text-muted" style="font-size:0.7rem;">Hint shown in empty input</small>
                </div>

                <div class="mb-0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="fieldRequired">
                        <label class="form-check-label" for="fieldRequired">Required Field</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- No-field placeholder (inside same grid slot as fieldConfigPanel) -->
        <div class="builder-panel builder-settings-panel" id="noFieldSelected">
            <div class="builder-panel-header">
                <h6>Field Settings</h6>
                <p class="builder-panel-hint mb-0">Select a field to configure</p>
            </div>
            <div class="builder-panel-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:160px;">
                <i class="bi bi-hand-index" style="font-size:1.8rem;color:#cbd5e1;"></i>
                <p style="font-size:0.75rem;color:#94a3b8;text-align:center;max-width:150px;margin-top:0.6rem;line-height:1.5;">Click a field in the canvas to configure it</p>
            </div>
        </div>

    </div><!-- /.builder-columns -->
</div>

<!-- Hidden data for edit mode -->
<input type="hidden" id="formId" value="<?= $formTemplate['id'] ?? '' ?>">
<input type="hidden" id="formFieldsData" value='<?= htmlspecialchars(json_encode($formFields), ENT_QUOTES, 'UTF-8') ?>'>
<input type="hidden" id="isEditMode" value="<?= $editMode ? '1' : '0' ?>">

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/admin_pages/form-builder.js"></script>

<?php include __DIR__ . '/layouts/footer.php'; ?>
