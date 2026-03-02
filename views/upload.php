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
    <link href="<?= APP_URL ?>/assets/css/pages/upload.css?v=<?= time() ?>" rel="stylesheet">
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
    
                <form id="uploadForm" action="<?= APP_URL ?>/pages/upload.php" method="POST" enctype="multipart/form-data">
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

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label>ARCHIVE TITLE</label>
                                <input type="text" name="title" id="title" placeholder="Enter title..."
                                    value="<?= $editMode ? htmlspecialchars($editItem['title']) : '' ?>" required>
                                <div class="invalid-feedback">Title is required.</div>
                            </div>
                            <div class="form-group">
                                <label>PUBLISHER</label>
                                <input type="text" name="publisher" id="publisher" placeholder="Enter publisher name..."
                                    value="<?= $editMode ? htmlspecialchars($editItem['publisher']) : '' ?>">
                            </div>
                        </div>

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label>DATE PUBLISHED</label>
                                <input type="date" name="publication_date" id="publication_date" placeholder="mm/dd/yyyy" min="1000-01-01" max="9999-12-31"
                                    value="<?= $editMode ? $editItem['publication_date'] : '' ?>" required>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="publication_month_only">
                                    <label class="form-check-label" for="publication_month_only">I only know month and year</label>
                                </div>
                                <div class="invalid-feedback">Use a valid date with a 4-digit year.</div>
                            </div>
                            <div class="form-group">
                                <label>EDITION</label>
                                <select name="edition" id="edition">
                                    <option value="" selected disabled>Select Edition...</option>
                                    <option value="Morning" <?= ($editMode && $editItem['edition'] === 'Morning') ? 'selected' : '' ?>>Morning</option>
                                    <option value="Evening" <?= ($editMode && $editItem['edition'] === 'Evening') ? 'selected' : '' ?>>Evening</option>
                                    <option value="Special" <?= ($editMode && $editItem['edition'] === 'Special') ? 'selected' : '' ?>>Special</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label>CATEGORY</label>
                                <select name="category_id" id="category_id" required>
                                    <option value="" selected disabled>Select Category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($editMode && $editItem['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>
                            <div class="form-group">
                                <label>LANGUAGE</label>
                                <select name="language_id" id="language_id" required>
                                    <option value="" selected disabled>Select Language...</option>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang['id'] ?>" <?= ($editMode && $editItem['language_id'] == $lang['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lang['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a language.</div>
                            </div>
                        </div>

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label>PAGE COUNT</label>
                                <input type="number" name="page_count" id="page_count" placeholder="1" min="1"
                                    value="<?= $editMode ? $editItem['page_count'] : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>VOLUME / ISSUE REFERENCE</label>
                                <input type="text" name="volume_issue" id="volume_issue" placeholder="e.g., Vol. 1, No. 1"
                                    value="<?= $editMode ? htmlspecialchars($editItem['volume_issue']) : '' ?>">
                            </div>
                        </div>

                        <div class="form-group-full">
                            <label>KEYWORDS / TAGS</label>
                            <div class="tags-input">
                                <input type="text" id="tagInput" placeholder="Type a tag and press enter...">
                                <button type="button" id="addTagBtn" class="btn-add-tag">
                                    +
                                </button>
                            </div>
                            <input type="hidden" name="keywords" id="keywordsHidden"
                                value="<?= $editMode ? htmlspecialchars($editItem['keywords']) : '' ?>">
                            <div class="tags-display" id="tagsContainer"></div>
                        </div>

                        <div class="form-group-full">
                            <label>DESCRIPTION</label>
                            <textarea name="description" id="description" rows="4"
                                placeholder="Enter a comprehensive description of the archive content..."><?= $editMode ? htmlspecialchars($editItem['description']) : '' ?></textarea>
                        </div>
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

    <script src="<?= APP_URL ?>/assets/js/pages/upload-tags.js?v=<?= time() ?>"></script>
    <script src="<?= APP_URL ?>/assets/js/pages/upload.js?v=<?= time() ?>"></script>

</body>

</html>