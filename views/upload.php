<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload - <?= APP_NAME ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/dark-mode.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/admin_pages/upload.css?v=<?= time() ?>" rel="stylesheet">
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <!-- Global Sidebar -->
    <?php include __DIR__ . '/layouts/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><?= $editMode ? 'Edit Archive' : 'Upload Archive' ?></h1>
                <p><?= $editMode ? 'Update file metadata and details' : 'Populate the repository with newspapers, documents, or media files' ?></p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-discard" id="discardBtn" disabled>Discard</button>
                <button type="button" class="btn-complete" id="uploadBtnDesktop" disabled>
                    <?= $editMode ? 'Save Changes' : 'Upload' ?>
                </button>
            </div>
        </div>

        <!-- Drop Zone (Full Width - Hidden when files uploaded) -->
        <div class="drop-zone-container" id="dropZoneContainer" <?= $editMode ? 'style="display: none;"' : '' ?>>
            <div class="drop-zone" id="mainDropZone">
                <input type="file" id="fileInput" name="file" hidden multiple
                    accept=".pdf,.doc,.docx,.xml,.tiff,.tif,.jpg,.jpeg,.png,.mobi,.epub">
                
                <svg class="drop-icon" width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <path d="M14 2H6C4.9 2 4.01 2.9 4.01 4L4 20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2ZM6 20V4H13V9H18V20H6Z" fill="#94A3B8"/>
                </svg>
                <p class="drop-title">Drag and drop files here</p>
                <p class="drop-subtitle">or <span class="click-browse" onclick="document.getElementById('fileInput').click()">click to browse</span> your computer</p>
            </div>
        </div>

        <!-- Selected File Preview (Hidden by default) -->
        <div class="selected-file-preview" id="selectedFilePreview" style="display: none;">
            <div class="preview-left">
                <span class="preview-label">SELECTED FILE</span>
                <div class="preview-info">
                    <span class="preview-filename" id="previewFilename">filename.pdf</span>
                    <span class="preview-error text-danger small fw-bold" id="previewError" style="display: none;"></span>
                    <span class="preview-size" id="previewSize">0 KB</span>
                </div>
            </div>
            <div class="preview-right">
                <button type="button" class="btn-add-file" onclick="document.getElementById('fileInput').click()">
                    ADD FILE
                </button>
            </div>
        </div>

        <!-- Bulk Upload Stats (Hidden by default) -->
        <div class="bulk-stats-container" id="bulkStatsContainer" style="display: none;">
            <div class="bulk-stats">
                <div class="stat-item">
                    <span class="stat-label">TOTAL FILES</span>
                    <span class="stat-value" id="totalFiles">0</span>
                </div>
                <div class="stat-item ready">
                    <span class="stat-label">READY</span>
                    <span class="stat-value" id="readyFiles">0</span>
                </div>
                <div class="stat-item pending">
                    <span class="stat-label">PENDING</span>
                    <span class="stat-value" id="pendingFiles">0</span>
                </div>
                <button type="button" class="btn-add-files" onclick="document.getElementById('fileInput').click()">
                    ADD FILES
                </button>
                <span class="ready-status" id="bulkReadyStatus" style="display: none;">
                    ALL READY FOR UPLOAD/EXTRACTION
                </span>
            </div>

            <!-- File Tabs -->
            <div class="file-tabs" id="fileTabs"></div>
        </div>

        <!-- Edit Mode Indicator (Refactored) -->
        <?php if ($editMode && !empty($editItem['file_name'])): ?>
            <div class="card border-0 shadow-sm mb-4" id="editModeIndicator" style="border-left: 5px solid #3A9AFF !important; background-color: #fff;">
                <div class="card-body d-flex align-items-center justify-content-between p-4">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; background-color: rgba(58, 154, 255, 0.1); color: #3A9AFF;">
                            <i class="bi bi-pencil-square fs-4"></i>
                        </div>
                        <div>
                            <small class="text-uppercase fw-bold" style="font-size: 0.75rem; color: #3A9AFF; letter-spacing: 0.5px;">You are editing</small>
                            <div class="fw-bold text-dark fs-5"><?= htmlspecialchars($editItem['file_name']) ?></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" style="border-color: #3A9AFF; color: #3A9AFF;" onclick="document.getElementById('fileInput').click()">
                        <i class="bi bi-arrow-repeat me-1"></i> Change File
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Bulk Upload Controls & Tabs -->
        <div id="bulkUploadContainer" class="mb-4" style="display: none;">
            <!-- New Stats Bar Design -->
            <div class="bulk-stats-container mb-4">
                <!-- Document Stats (PDF/MOBI) -->
                <div class="bulk-stats-wrapper" id="docStatsWrapper">
                    <div class="stat-col">
                        <span class="stat-label">TOTAL FILES</span>
                        <span class="stat-number" id="totalFilesCount">0</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-col">
                        <span class="stat-label text-success">READY</span>
                        <span class="stat-number text-success" id="readyFilesCount">0</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-col">
                        <span class="stat-label text-warning">PENDING</span>
                        <span class="stat-number text-warning" id="pendingFilesCount">0</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-col action-col">
                        <button type="button" class="btn btn-add-files" id="addMoreBtn">
                            + ADD FILES
                        </button>
                        <input type="file" id="addMoreInput" multiple style="display: none;" accept=".pdf,.epub,.mobi">
                        <div id="duplicateStatusContainer" class="duplicate-status" style="display: none;">
                            <i class="bi bi-check-circle-fill"></i> NO DUPLICATE FILES DETECTED
                        </div>
                    </div>
                </div>

                <!-- Photo Stats (Images) -->
                <div class="photo-stats-wrapper" id="photoStatsWrapper" style="display: none;">
                    <div class="photo-stats-left">
                        <div class="info-badge">
                            <i class="bi bi-info-circle-fill"></i>
                            <span>All photos share a single metadata entry</span>
                        </div>
                        <div class="stat-divider-vertical"></div>
                        <div class="photo-count-display">
                            <span class="count-number" id="totalPhotosCount">0</span>
                            <span class="count-label">Photos</span>
                        </div>
                    </div>
                    <div class="photo-stats-right">
                        <button type="button" class="btn-add-photos" id="addMorePhotosBtn" onclick="document.getElementById('fileInput').click()">
                            <i class="bi bi-plus-circle"></i>
                            <span>Add Photos</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tabs Scroll Container -->
            <div id="tabsWrapper" class="tabs-container border-bottom">
                <ul class="nav nav-tabs border-bottom-0 flex-nowrap overflow-auto" id="bulkTabs" role="tablist" style="scrollbar-width: thin;">
                    <!-- Tabs injected via JS -->
                </ul>
            </div>

            <!-- Photo Gallery Grid Container -->
            <div id="pageOrderGridWrapper" class="border-bottom p-4 bg-light" style="display: none; overflow-x: auto; white-space: nowrap; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                <div id="pageOrderGrid" class="d-inline-flex gap-3 align-items-start" style="padding: 8px 0;">
                    <!-- Photo thumbnails injected via JS -->
                </div>
            </div>
        </div>

        <!-- Two Column Layout: Form (Left) + Thumbnail (Right) -->
        <div class="content-grid">
            
            <!-- Form Section (Left) -->
            <div class="form-section">
                <!-- Alert Container -->
                <div id="alertContainer"><?php if (isset($_GET['error'])): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?><?php if ($alert): ?><div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert"><i class="bi bi-<?= $alert['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i><?= htmlspecialchars($alert['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?></div>
    
                <form id="uploadForm" action="<?= APP_URL ?>/admin_pages/upload.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'upload' ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="edit_id" value="<?= $editItem['id'] ?>">
                        <input type="hidden" name="existing_thumbnail" id="existing_thumbnail" value="<?= htmlspecialchars($editItem['thumbnail_path']) ?>">
                    <?php endif; ?>

                    <!-- Old Bulk Section Removed -->
                    
                    <!-- Bulk Upload Controls & Tabs MOVED UP -->

                    <!-- General Information Card -->
                    <div class="info-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= APP_URL ?>/assets/images/info.png" alt="Info" class="header-icon">
                                <span class="header-title">GENERAL INFORMATION</span>
                            </div>
                            <span id="currentFileName" class="badge bg-light text-dark border d-none"></span>
                        </div>

                        <?php if (empty($formFields) && empty($customFields)): ?>
                        <!-- Empty State: No Active Form Template -->
                        <div class="empty-state-container text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="bi bi-inbox" style="font-size: 4rem; color: #6c757d;"></i>
                            </div>
                            <h3 class="empty-state-title">No Metadata Fields Defined</h3>
                            <p class="empty-state-description text-muted">
                                Please customize your metadata structure to start uploading archives.<br>
                                Define fields like Author, Date, and Keywords to keep your library organized.
                            </p>
                            <a href="<?= APP_URL ?>/admin_pages/form-library.php" class="btn btn-primary mt-3">
                                <i class="bi bi-gear"></i> Configure Metadata Fields
                            </a>
                        </div>
                        <?php else: ?>
                        <!-- Custom Metadata Fields (Form Template Fields) -->
                        <?php if (!empty($formFields)): ?>

                        <?php 
                        // Group fields for 2-column layout
                        $fieldIndex = 0;
                        $totalFields = count($formFields);
                        
                        while ($fieldIndex < $totalFields):
                            $field = $formFields[$fieldIndex];
                            $fieldName = 'field_' . $field['id'];
                            $fieldValue = $customMetadataValues[$field['id']] ?? '';
                            $required = $field['is_required'] ? 'required' : '';
                            $requiredAttr = $field['is_required'] ? 'data-required="true"' : '';
                            
                            // Check if this field should take full width
                            $isFullWidth = in_array($field['field_type'], ['textarea', 'checkbox', 'radio', 'tags']);
                            
                            if ($isFullWidth):
                                // Full width field
                        ?>
                        <div class="form-group-full">
                            <label>
                                <?= strtoupper(htmlspecialchars($field['field_label'])) ?>
                                <?php if ($field['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <textarea class="custom-field" 
                                          id="<?= $fieldName ?>" 
                                          name="<?= $fieldName ?>"
                                          rows="3" placeholder="<?= htmlspecialchars($field['help_text'] ?? '') ?>"
                                          <?= $required ?>
                                          <?= $requiredAttr ?>><?= htmlspecialchars($fieldValue) ?></textarea>
                            
                            <?php elseif ($field['field_type'] === 'checkbox'): 
                                $options = json_decode($field['field_options'], true);
                                $selectedValues = $fieldValue ? json_decode($fieldValue, true) : [];
                                if (!is_array($selectedValues)) $selectedValues = [];
                            ?>
                                <div class="checkbox-group">
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
                                </div>
                            
                            <?php elseif ($field['field_type'] === 'radio'): 
                                $options = json_decode($field['field_options'], true);
                            ?>
                                <div class="radio-group">
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
                                </div>
                            <?php elseif ($field['field_type'] === 'tags'): ?>
                                <?php
                                    $existingTags = array_filter(array_map('trim', explode(',', $fieldValue)));
                                ?>
                                <input type="hidden" 
                                       id="<?= $fieldName ?>_hidden"
                                       name="<?= $fieldName ?>"
                                       value="<?= htmlspecialchars($fieldValue) ?>">
                                <div class="tags-field-wrapper">
                                    <div class="tags-pills-row" id="<?= $fieldName ?>_pills">
                                        <?php foreach ($existingTags as $t): ?>
                                            <span class="tag-chip"><?= htmlspecialchars($t) ?><button type="button" class="tag-chip-remove" onclick="event.stopPropagation();removeTagChip(this,'<?= $fieldName ?>')" title="Remove"><i class="bi bi-x"></i></button></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="tags-input-row">
                                        <input type="text" 
                                               id="<?= $fieldName ?>_input"
                                               class="tags-text-input"
                                               placeholder="Type a tag and press Enter or comma…"
                                               onkeydown="handleTagKeydown(event,'<?= $fieldName ?>')"
                                               onclick="event.stopPropagation()"
                                               autocomplete="off">
                                        <button type="button" class="tags-add-btn" onclick="event.stopPropagation();addTagFromInput('<?= $fieldName ?>')">Add</button>
                                    </div>
                                </div>
                                <div class="tags-hint">Press <strong>Enter</strong> or <strong>,</strong> to add a tag. Click <strong>×</strong> to remove.</div>
                            <?php endif; ?>
                        </div>
                        <?php
                                $fieldIndex++;
                            else:
                                // Two-column layout for text, number, date, select
                                $nextField = null;
                                $nextFieldName = '';
                                $nextFieldValue = '';
                                $nextRequired = '';
                                $nextRequiredAttr = '';
                                
                                // Check if there's a next field that can be paired
                                if ($fieldIndex + 1 < $totalFields) {
                                    $nextField = $formFields[$fieldIndex + 1];
                                    if (!in_array($nextField['field_type'], ['textarea', 'checkbox', 'radio', 'tags'])) {
                                        $nextFieldName = 'field_' . $nextField['id'];
                                        $nextFieldValue = $customMetadataValues[$nextField['id']] ?? '';
                                        $nextRequired = $nextField['is_required'] ? 'required' : '';
                                        $nextRequiredAttr = $nextField['is_required'] ? 'data-required="true"' : '';
                                    } else {
                                        $nextField = null; // Can't pair with full-width field
                                    }
                                }
                        ?>
                        <div class="form-row-2col">
                            <!-- First Field -->
                            <div class="form-group">
                                <label>
                                    <?= strtoupper(htmlspecialchars($field['field_label'])) ?>
                                    <?php if ($field['is_required']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($field['field_type'] === 'text'): ?>
                                    <input type="text" 
                                           class="custom-field" 
                                           id="<?= $fieldName ?>" 
                                           name="<?= $fieldName ?>"
                                           value="<?= htmlspecialchars($fieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($field['help_text'] ?? '') ?>"
                                           <?= $required ?>
                                           <?= $requiredAttr ?>>
                                
                                <?php elseif ($field['field_type'] === 'number'): ?>
                                    <input type="number" step="any" 
                                           class="custom-field" 
                                           id="<?= $fieldName ?>" 
                                           name="<?= $fieldName ?>"
                                           value="<?= htmlspecialchars($fieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($field['help_text'] ?? '') ?>"
                                           <?= $required ?>
                                           <?= $requiredAttr ?>>
                                
                                <?php elseif ($field['field_type'] === 'date'): ?>
                                    <input type="date" 
                                           class="custom-field" 
                                           id="<?= $fieldName ?>" 
                                           name="<?= $fieldName ?>"
                                           value="<?= htmlspecialchars($fieldValue) ?>"
                                           <?= $required ?>
                                           <?= $requiredAttr ?>>
                                
                                <?php elseif ($field['field_type'] === 'select'): 
                                    $options = json_decode($field['field_options'], true);
                                ?>
                                    <select class="custom-field" 
                                            id="<?= $fieldName ?>" 
                                            name="<?= $fieldName ?>"
                                            <?= $required ?>
                                            <?= $requiredAttr ?>>
                                        <option value="">Select an option...</option>
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>"
                                                    <?= $fieldValue === $option ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($option) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Second Field (if exists) -->
                            <?php if ($nextField): ?>
                            <div class="form-group">
                                <label>
                                    <?= strtoupper(htmlspecialchars($nextField['field_label'])) ?>
                                    <?php if ($nextField['is_required']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($nextField['field_type'] === 'text'): ?>
                                    <input type="text" 
                                           class="custom-field" 
                                           id="<?= $nextFieldName ?>" 
                                           name="<?= $nextFieldName ?>"
                                           value="<?= htmlspecialchars($nextFieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($nextField['help_text'] ?? '') ?>"
                                           <?= $nextRequired ?>
                                           <?= $nextRequiredAttr ?>>
                                
                                <?php elseif ($nextField['field_type'] === 'number'): ?>
                                    <input type="number" step="any" 
                                           class="custom-field" 
                                           id="<?= $nextFieldName ?>" 
                                           name="<?= $nextFieldName ?>"
                                           value="<?= htmlspecialchars($nextFieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($nextField['help_text'] ?? '') ?>"
                                           <?= $nextRequired ?>
                                           <?= $nextRequiredAttr ?>>
                                
                                <?php elseif ($nextField['field_type'] === 'date'): ?>
                                    <input type="date" 
                                           class="custom-field" 
                                           id="<?= $nextFieldName ?>" 
                                           name="<?= $nextFieldName ?>"
                                           value="<?= htmlspecialchars($nextFieldValue) ?>"
                                           <?= $nextRequired ?>
                                           <?= $nextRequiredAttr ?>>
                                
                                <?php elseif ($nextField['field_type'] === 'select'): 
                                    $nextOptions = json_decode($nextField['field_options'], true);
                                ?>
                                    <select class="custom-field" 
                                            id="<?= $nextFieldName ?>" 
                                            name="<?= $nextFieldName ?>"
                                            <?= $nextRequired ?>
                                            <?= $nextRequiredAttr ?>>
                                        <option value="">Select an option...</option>
                                        <?php foreach ($nextOptions as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>"
                                                    <?= $nextFieldValue === $option ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($option) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <?php 
                                $fieldIndex += 2; // Skip both fields
                            else: 
                                $fieldIndex++; // Only skip current field
                            ?>
                            <div class="form-group"></div> <!-- Empty placeholder -->
                            <?php endif; ?>
                        </div>
                        <?php
                            endif;
                        endwhile;
                        ?>
                        <?php endif; ?>

                        <!-- Legacy Custom Metadata Fields (Fallback) -->
                        <?php if (!empty($customFields)): ?>

                        <?php 
                        // Group fields for 2-column layout
                        $fieldIndex = 0;
                        $totalFields = count($customFields);
                        
                        while ($fieldIndex < $totalFields):
                            $field = $customFields[$fieldIndex];
                            $fieldName = 'custom_' . $field['field_name'];
                            $fieldValue = $customMetadataValues[$field['id']] ?? '';
                            $required = $field['is_required'] ? 'required' : '';
                            $requiredAttr = $field['is_required'] ? 'data-required="true"' : '';
                            
                            // Check if this field should take full width
                            $isFullWidth = in_array($field['field_type'], ['textarea', 'checkbox', 'radio', 'tags']);
                            
                            if ($isFullWidth):
                                // Full width field
                        ?>
                        <div class="form-group-full">
                            <label>
                                <?= strtoupper(htmlspecialchars($field['field_label'])) ?>
                                <?php if ($field['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <textarea class="custom-field" 
                                          id="<?= $fieldName ?>" 
                                          name="<?= $fieldName ?>"
                                          rows="3" placeholder="<?= htmlspecialchars($field['help_text'] ?? '') ?>"
                                          <?= $required ?>
                                          <?= $requiredAttr ?>><?= htmlspecialchars($fieldValue) ?></textarea>
                            
                            <?php elseif ($field['field_type'] === 'checkbox'): 
                                $options = json_decode($field['field_options'], true);
                                $selectedValues = $fieldValue ? json_decode($fieldValue, true) : [];
                                if (!is_array($selectedValues)) $selectedValues = [];
                            ?>
                                <div class="checkbox-group">
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
                                </div>
                            
                            <?php elseif ($field['field_type'] === 'radio'): 
                                $options = json_decode($field['field_options'], true);
                            ?>
                                <div class="radio-group">
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
                                </div>
                            <?php elseif ($field['field_type'] === 'tags'): ?>
                                <?php
                                    $existingTags = array_filter(array_map('trim', explode(',', $fieldValue)));
                                ?>
                                <input type="hidden" 
                                       id="<?= $fieldName ?>_hidden"
                                       name="<?= $fieldName ?>"
                                       value="<?= htmlspecialchars($fieldValue) ?>">
                                <div class="tags-field-wrapper" onclick="document.getElementById('<?= $fieldName ?>_input').focus()">
                                    <div class="tags-pills-row" id="<?= $fieldName ?>_pills">
                                        <?php foreach ($existingTags as $t): ?>
                                            <span class="tag-chip"><?= htmlspecialchars($t) ?><button type="button" class="tag-chip-remove" onclick="removeTagChip(this,'<?= $fieldName ?>')" title="Remove"><i class="bi bi-x"></i></button></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="tags-input-row">
                                        <input type="text" 
                                               id="<?= $fieldName ?>_input"
                                               class="tags-text-input"
                                               placeholder="Type a tag and press Enter or comma…"
                                               onkeydown="handleTagKeydown(event,'<?= $fieldName ?>')"
                                               autocomplete="off">
                                        <button type="button" class="tags-add-btn" onclick="addTagFromInput('<?= $fieldName ?>')">Add</button>
                                    </div>
                                </div>
                                <div class="tags-hint">Press <strong>Enter</strong> or <strong>,</strong> to add a tag. Click <strong>×</strong> to remove.</div>
                            <?php endif; ?>
                        </div>
                        <?php
                                $fieldIndex++;
                            else:
                                // Two-column layout for text, number, date, select
                                $nextField = null;
                                $nextFieldName = '';
                                $nextFieldValue = '';
                                $nextRequired = '';
                                $nextRequiredAttr = '';
                                
                                // Check if there's a next field that can be paired
                                if ($fieldIndex + 1 < $totalFields) {
                                    $nextField = $customFields[$fieldIndex + 1];
                                    if (!in_array($nextField['field_type'], ['textarea', 'checkbox', 'radio', 'tags'])) {
                                        $nextFieldName = 'custom_' . $nextField['field_name'];
                                        $nextFieldValue = $customMetadataValues[$nextField['id']] ?? '';
                                        $nextRequired = $nextField['is_required'] ? 'required' : '';
                                        $nextRequiredAttr = $nextField['is_required'] ? 'data-required="true"' : '';
                                    } else {
                                        $nextField = null; // Can't pair with full-width field
                                    }
                                }
                        ?>
                        <div class="form-row-2col">
                            <!-- First Field -->
                            <div class="form-group">
                                <label>
                                    <?= strtoupper(htmlspecialchars($field['field_label'])) ?>
                                    <?php if ($field['is_required']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($field['field_type'] === 'text'): ?>
                                    <input type="text" 
                                           class="custom-field" 
                                           id="<?= $fieldName ?>" 
                                           name="<?= $fieldName ?>"
                                           value="<?= htmlspecialchars($fieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($field['help_text'] ?? '') ?>"
                                           <?= $required ?>
                                           <?= $requiredAttr ?>>
                                
                                <?php elseif ($field['field_type'] === 'number'): ?>
                                    <input type="number" step="any" 
                                           class="custom-field" 
                                           id="<?= $fieldName ?>" 
                                           name="<?= $fieldName ?>"
                                           value="<?= htmlspecialchars($fieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($field['help_text'] ?? '') ?>"
                                           <?= $required ?>
                                           <?= $requiredAttr ?>>
                                
                                <?php elseif ($field['field_type'] === 'date'): ?>
                                    <input type="date" 
                                           class="custom-field" 
                                           id="<?= $fieldName ?>" 
                                           name="<?= $fieldName ?>"
                                           value="<?= htmlspecialchars($fieldValue) ?>"
                                           <?= $required ?>
                                           <?= $requiredAttr ?>>
                                
                                <?php elseif ($field['field_type'] === 'select'): 
                                    $options = json_decode($field['field_options'], true);
                                ?>
                                    <select class="custom-field" 
                                            id="<?= $fieldName ?>" 
                                            name="<?= $fieldName ?>"
                                            <?= $required ?>
                                            <?= $requiredAttr ?>>
                                        <option value="">Select an option...</option>
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>"
                                                    <?= $fieldValue === $option ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($option) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Second Field (if exists) -->
                            <?php if ($nextField): ?>
                            <div class="form-group">
                                <label>
                                    <?= strtoupper(htmlspecialchars($nextField['field_label'])) ?>
                                    <?php if ($nextField['is_required']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($nextField['field_type'] === 'text'): ?>
                                    <input type="text" 
                                           class="custom-field" 
                                           id="<?= $nextFieldName ?>" 
                                           name="<?= $nextFieldName ?>"
                                           value="<?= htmlspecialchars($nextFieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($nextField['help_text'] ?? '') ?>"
                                           <?= $nextRequired ?>
                                           <?= $nextRequiredAttr ?>>
                                
                                <?php elseif ($nextField['field_type'] === 'number'): ?>
                                    <input type="number" step="any" 
                                           class="custom-field" 
                                           id="<?= $nextFieldName ?>" 
                                           name="<?= $nextFieldName ?>"
                                           value="<?= htmlspecialchars($nextFieldValue) ?>"
                                           placeholder="<?= htmlspecialchars($nextField['help_text'] ?? '') ?>"
                                           <?= $nextRequired ?>
                                           <?= $nextRequiredAttr ?>>
                                
                                <?php elseif ($nextField['field_type'] === 'date'): ?>
                                    <input type="date" 
                                           class="custom-field" 
                                           id="<?= $nextFieldName ?>" 
                                           name="<?= $nextFieldName ?>"
                                           value="<?= htmlspecialchars($nextFieldValue) ?>"
                                           <?= $nextRequired ?>
                                           <?= $nextRequiredAttr ?>>
                                
                                <?php elseif ($nextField['field_type'] === 'select'): 
                                    $nextOptions = json_decode($nextField['field_options'], true);
                                ?>
                                    <select class="custom-field" 
                                            id="<?= $nextFieldName ?>" 
                                            name="<?= $nextFieldName ?>"
                                            <?= $nextRequired ?>
                                            <?= $nextRequiredAttr ?>>
                                        <option value="">Select an option...</option>
                                        <?php foreach ($nextOptions as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>"
                                                    <?= $nextFieldValue === $option ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($option) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <?php 
                                $fieldIndex += 2; // Skip both fields
                            else: 
                                $fieldIndex++; // Only skip current field
                            ?>
                            <div class="form-group"></div> <!-- Empty placeholder -->
                            <?php endif; ?>
                        </div>
                        <?php
                            endif;
                        endwhile;
                        ?>
                        <?php endif; ?>
                        
                        <?php endif; ?> <!-- End of empty state conditional -->
                    </div>

                    <button type="submit" id="uploadBtn" style="display: none;"></button>
                </form>
            </div>

            <!-- Thumbnail Section (Right) -->
            <div class="thumbnail-section">
                <div class="info-card">
                    <div class="card-header">
                        <img src="<?= APP_URL ?>/assets/images/image_icon.png" alt="Image" class="header-icon">
                        <span class="header-title">THUMBNAIL PREVIEW</span>
                    </div>

                    <div class="thumbnail-area" id="thumbnailArea">
                        <input type="file" id="thumbnailInput" name="thumbnail" hidden accept="image/*">

                        <div class="thumbnail-placeholder" id="thumbnailPlaceholder"
                            <?= ($editMode && !empty($editItem['thumbnail_path'])) ? 'style="display:none;"' : '' ?>>
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                                <path d="M21 19V5C21 3.9 20.1 3 19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19ZM8.5 13.5L11 16.51L14.5 12L19 18H5L8.5 13.5Z" fill="#3A9AFF"/>
                            </svg>
                            <p class="thumb-label">UPLOAD THUMBNAIL</p>
                            <p class="thumb-hint">Recommended aspect ratio 16:9 for optimal stitched cover image.</p>
<button type="button" class="btn-browse-thumb" id="browseThumbnailBtn">BROWSE</button>
                        </div>

                        <img id="thumbnailPreview"
                            src="<?= ($editMode && !empty($editItem['thumbnail_path'])) ? APP_URL . '/' . $editItem['thumbnail_path'] : '#' ?>"
                            <?= ($editMode && !empty($editItem['thumbnail_path'])) ? '' : 'style="display: none;"' ?>>

                        <button type="button" id="removeThumbnailBtn"
                            <?= ($editMode && !empty($editItem['thumbnail_path'])) ? '' : 'style="display: none;"' ?>>&times;</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Footer -->
    <div class="mobile-footer">
        <div class="d-flex gap-3">
            <button class="btn-text-discard flex-fill border rounded p-3 text-center" disabled id="discardBtnMobile">DISCARD</button>
            <button class="btn-upload-submit flex-fill justify-content-center p-3" id="uploadBtnMobile">UPLOAD</button>
        </div>
    </div>

    <!-- Upload Confirmation Modal -->
    <div class="modal fade" id="confirmUploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-standard">
            <div class="modal-content modal-minimalist">
                <div class="modal-header">
                    <div class="modal-icon icon-info">
                        <i class="bi bi-cloud-upload"></i>
                    </div>
                    <h5 class="modal-title">Upload Files?</h5>
                </div>
                <div class="modal-body">
                    <p>Review your files before uploading</p>
                    <!-- File List -->
                    <div id="uploadFileList" style="max-height: 200px; overflow-y: auto; background: #F8FAFC; padding: 12px; border-radius: 8px; border: 1px solid #E5E7EB; display: none; margin-top: 16px;">
                        <!-- Files will be injected here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmUploadBtn">
                        <i class="bi bi-check-circle me-1"></i> Confirm Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Discard Confirmation Modal -->
    <div class="modal fade" id="discardModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-standard">
            <div class="modal-content modal-minimalist">
                <div class="modal-header">
                    <div class="modal-icon icon-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h5 class="modal-title">Discard Changes?</h5>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to discard all changes? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDiscardAction()" data-bs-dismiss="modal">
                        <i class="bi bi-trash me-1"></i> Discard
                    </button>
                </div>
            </div>
        </div>
    </div>

     <!-- Unsaved Changes Modal -->
    <div class="modal fade" id="unsavedChangesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-standard">
            <div class="modal-content modal-minimalist">
                <div class="modal-header">
                    <div class="modal-icon icon-warning">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <h5 class="modal-title">Unsaved Changes</h5>
                </div>
                <div class="modal-body">
                    <p>You have unsaved inputs. If you leave this page, your data will be lost.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Stay on Page</button>
                    <button type="button" class="btn btn-danger" id="confirmLeaveBtn">
                        <i class="bi bi-box-arrow-right me-1"></i> Leave Page
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Define APP_URL for JavaScript
        const APP_URL = "<?= APP_URL ?>";
    </script>

    <script src="<?= APP_URL ?>/assets/js/admin_pages/upload-tags.js?v=<?= time() ?>"></script>
    <script src="<?= APP_URL ?>/assets/js/admin_pages/upload.js?v=<?= time() ?>"></script>

    <!-- Tag Widget JS for custom 'tags' field type -->
    <script>
    /**
     * Tag Chip Widget — used by custom metadata fields of type "tags"
     * fieldName: the base name of the field (e.g. "field_5" or "custom_keywords")
     */
    function getTagsFromHidden(fieldName) {
        const hidden = document.getElementById(fieldName + '_hidden');
        if (!hidden || !hidden.value.trim()) return [];
        return hidden.value.split(',').map(t => t.trim()).filter(Boolean);
    }

    function syncHiddenFromPills(fieldName) {
        const pills = document.getElementById(fieldName + '_pills');
        const hidden = document.getElementById(fieldName + '_hidden');
        if (!pills || !hidden) return;
        const chips = Array.from(pills.querySelectorAll('.tag-chip'));
        // Get text excluding the remove button text
        const tags = chips.map(chip => {
            const clone = chip.cloneNode(true);
            const btn = clone.querySelector('.tag-chip-remove');
            if (btn) btn.remove();
            return clone.textContent.trim();
        }).filter(Boolean);
        hidden.value = tags.join(', ');
    }

    function addTagChip(fieldName, tagText) {
        tagText = tagText.trim();
        if (!tagText) return;

        // Prevent duplicates (case-insensitive)
        const existingTags = getTagsFromHidden(fieldName).map(t => t.toLowerCase());
        if (existingTags.includes(tagText.toLowerCase())) return;

        const pills = document.getElementById(fieldName + '_pills');
        if (!pills) return;

        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.innerHTML = `${escapeHtmlTag(tagText)}<button type="button" class="tag-chip-remove" onclick="removeTagChip(this,'${fieldName}')" title="Remove"><i class="bi bi-x"></i></button>`;
        pills.appendChild(chip);

        syncHiddenFromPills(fieldName);
    }

    function removeTagChip(btn, fieldName) {
        const chip = btn.closest('.tag-chip');
        if (chip) chip.remove();
        syncHiddenFromPills(fieldName);
    }

    function addTagFromInput(fieldName) {
        const input = document.getElementById(fieldName + '_input');
        if (!input) return;
        const rawValue = input.value;
        // Handle comma-separated batch input
        rawValue.split(',').forEach(tag => {
            tag = tag.trim();
            if (tag) addTagChip(fieldName, tag);
        });
        input.value = '';
        input.focus();
    }

    function handleTagKeydown(event, fieldName) {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addTagFromInput(fieldName);
        }
    }

    function escapeHtmlTag(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
    </script>

</body>

</html>
