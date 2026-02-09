<?php
/**
 * Upload Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get alert message
$alert = getAlert();

// Get categories and languages
$categories = getCategories();
$languages = getLanguages();

// Check if editing
$editMode = isset($_GET['edit']) && intval($_GET['edit']) > 0;
$editItem = null;

if ($editMode) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();

    if (!$editItem) {
        showAlert('danger', 'Document not found.');
        redirect('upload.php');
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' || $action === 'edit') {
        $title = sanitize($_POST['title']);
        $publicationDate = $_POST['publication_date'] ?: null;
        $edition = sanitize($_POST['edition'] ?? '');
        $categoryId = intval($_POST['category_id']) ?: null;
        $languageId = intval($_POST['language_id']) ?: null;
        $pageCount = intval($_POST['page_count']) ?: null;
        $keywords = sanitize($_POST['keywords'] ?? '');
        $publisher = sanitize($_POST['publisher'] ?? '');
        $volumeIssue = sanitize($_POST['volume_issue'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($action === 'upload') {
            // Handle file upload
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileTmp = $file['tmp_name'];

                // Get file extension
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Check allowed extensions
                if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
                    showAlert('danger', 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS));
                    redirect('upload.php');
                }

                // Check file size
                if ($fileSize > MAX_UPLOAD_SIZE) {
                    showAlert('danger', 'File too large. Maximum size: ' . formatFileSize(MAX_UPLOAD_SIZE));
                    redirect('upload.php');
                }

                // Check for duplicates
                if (checkDuplicateFile($fileName)) {
                    showAlert('danger', 'A file with this name already exists.');
                    redirect('upload.php');
                }

                // Generate unique filename
                $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                $uploadPath = UPLOAD_PATH . 'newspapers/' . $newFileName;

                // Move file
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    // Handle thumbnail upload
                    $thumbnailPath = null;
                    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                        $thumbFile = $_FILES['thumbnail'];
                        $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

                        if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                            $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                            $thumbPath = UPLOAD_PATH . 'thumbnails/' . $thumbFileName;

                            if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                                $thumbnailPath = 'uploads/thumbnails/' . $thumbFileName;
                            }
                        }
                    }

                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO newspapers (title, publication_date, edition, category_id, language_id, page_count, 
                                               keywords, publisher, volume_issue, description, file_path, file_name, 
                                               file_type, file_size, thumbnail_path, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title,
                        $publicationDate,
                        $edition,
                        $categoryId,
                        $languageId,
                        $pageCount,
                        $keywords,
                        $publisher,
                        $volumeIssue,
                        $description,
                        'uploads/newspapers/' . $newFileName,
                        $fileName,
                        $fileExt,
                        $fileSize,
                        $thumbnailPath,
                        $currentUser['id']
                    ]);

                    logActivity($currentUser['id'], 'upload', $title);
                    showAlert('success', 'Document uploaded successfully.');
                } else {
                    showAlert('danger', 'Failed to upload file. Please try again.');
                }
            } else {
                showAlert('danger', 'Please select a file to upload.');
            }
            redirect('upload.php');
        }

        if ($action === 'edit') {
            $editId = intval($_POST['edit_id']);

            // Handle new thumbnail upload
            $thumbnailPath = $_POST['existing_thumbnail'] ?? null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumbFile = $_FILES['thumbnail'];
                $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

                if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                    $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                    $thumbPath = UPLOAD_PATH . 'thumbnails/' . $thumbFileName;

                    if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                        $thumbnailPath = 'uploads/thumbnails/' . $thumbFileName;
                    }
                }
            }

            // Handle file replacement
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileTmp = $file['tmp_name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (in_array($fileExt, ALLOWED_EXTENSIONS) && $fileSize <= MAX_UPLOAD_SIZE) {
                    $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                    $uploadPath = UPLOAD_PATH . 'newspapers/' . $newFileName;

                    if (move_uploaded_file($fileTmp, $uploadPath)) {
                        // Update with new file
                        $stmt = $pdo->prepare("
                            UPDATE newspapers SET title = ?, publication_date = ?, edition = ?, category_id = ?, 
                                   language_id = ?, page_count = ?, keywords = ?, publisher = ?, volume_issue = ?, 
                                   description = ?, file_path = ?, file_name = ?, file_type = ?, file_size = ?, 
                                   thumbnail_path = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $title,
                            $publicationDate,
                            $edition,
                            $categoryId,
                            $languageId,
                            $pageCount,
                            $keywords,
                            $publisher,
                            $volumeIssue,
                            $description,
                            'uploads/newspapers/' . $newFileName,
                            $fileName,
                            $fileExt,
                            $fileSize,
                            $thumbnailPath,
                            $editId
                        ]);
                    }
                }
            } else {
                // Update without changing file
                $stmt = $pdo->prepare("
                    UPDATE newspapers SET title = ?, publication_date = ?, edition = ?, category_id = ?, 
                           language_id = ?, page_count = ?, keywords = ?, publisher = ?, volume_issue = ?, 
                           description = ?, thumbnail_path = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title,
                    $publicationDate,
                    $edition,
                    $categoryId,
                    $languageId,
                    $pageCount,
                    $keywords,
                    $publisher,
                    $volumeIssue,
                    $description,
                    $thumbnailPath,
                    $editId
                ]);
            }

            logActivity($currentUser['id'], 'edit', $title);
            showAlert('success', 'Document updated successfully.');
            redirect('upload.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $editMode ? 'Edit' : 'Upload' ?> -
        <?= APP_NAME ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/dark-mode.css" rel="stylesheet">
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px;">
            <div>
                <?php if ($editMode): ?>
                    <h1
                        style="font-size: 28px; font-weight: 600; color: #2C1810; font-family: 'Playfair Display', Georgia, serif; margin: 0;">
                        Upload Archive</h1>
                    <p style="color: #888; margin: 5px 0 0;">Editing "<?= htmlspecialchars($editItem['title']) ?>"</p>
                <?php else: ?>
                    <h1
                        style="font-size: 28px; font-weight: 600; color: #2C1810; font-family: 'Playfair Display', Georgia, serif; margin: 0;">
                        Upload Archive</h1>
                    <p style="color: #888; margin: 5px 0 0;">Populate the repository with newspapers, documents, or media
                        files</p>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if ($editMode): ?>
                    <a href="upload.php"
                        style="padding: 10px 20px; background: #f5f5f5; border: none; border-radius: 8px; color: #666; text-decoration: none; font-weight: 500;">
                        <i class="bi bi-x-lg me-1"></i>Discard
                    </a>
                    <button type="submit" form="uploadForm"
                        style="padding: 10px 20px; background: #4C3939; border: none; border-radius: 8px; color: white; font-weight: 500;">
                        <i class="bi bi-cloud-upload me-2"></i>Upload to Cloud
                    </button>
                <?php else: ?>
                    <button type="button" id="discardBtn" onclick="resetForm()" disabled
                        style="padding: 10px 20px; background: #f5f5f5; border: none; border-radius: 8px; color: #666; font-weight: 500; cursor: pointer;">
                        <i class="bi bi-x-lg me-1"></i>Discard
                    </button>
                    <button type="submit" form="uploadForm" id="uploadBtn" disabled
                        style="padding: 10px 20px; background: #4C3939; border: none; border-radius: 8px; color: white; font-weight: 500; cursor: pointer;">
                        <i class="bi bi-cloud-upload me-2"></i>Upload to Cloud
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Real-time File Checking Indicator -->
        <div id="fileCheckingBar"
            style="background: #e8f5e9; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div
                        style="width: 36px; height: 36px; background: #4caf50; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-lg" style="color: white; font-size: 18px;"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #2e7d32; font-size: 14px;">Real-time File Checking</div>
                        <div id="fileCheckText" style="color: #4caf50; font-size: 13px;">Duplicate detection complete.
                            No duplicate archives found for "filename"</div>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('fileCheckingBar').style.display='none'"
                    style="background: #2e7d32; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                    DECLINE
                </button>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert"
                style="border-radius: 12px;">
                <?= $alert['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'upload' ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="edit_id" value="<?= $editItem['id'] ?>">
                <input type="hidden" name="existing_thumbnail" value="<?= $editItem['thumbnail_path'] ?>">
            <?php endif; ?>

            <?php if ($editMode): ?>
                <!-- Current File Info -->
                <div class="card mb-4">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-danger text-white rounded p-3">
                                <i class="bi bi-file-earmark-pdf fs-4"></i>
                            </div>
                            <div>
                                <strong>
                                    <?= htmlspecialchars($editItem['file_name']) ?>
                                </strong>
                                <div class="text-muted small">
                                    <?= strtoupper($editItem['file_type']) ?> Document •
                                    <?= formatFileSize($editItem['file_size']) ?> •
                                    Uploaded on
                                    <?= date('M d, Y', strtotime($editItem['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <label class="btn btn-secondary" for="newFile">
                            <i class="bi bi-arrow-repeat me-2"></i>Replace File
                        </label>
                        <input type="file" id="newFile" name="file" class="d-none"
                            accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif">
                    </div>
                </div>
            <?php else: ?>
                <!-- Drop Zone Card -->
                <div class="upload-drop-zone">
                    <!-- Upload Icon -->
                    <div class="upload-icon-wrapper">
                        <i class="bi bi-cloud-arrow-up" style="font-size: 28px; color: #C08B5C;"></i>
                    </div>

                    <h3 style="color: #333; font-size: 18px; font-weight: 600; margin: 0 0 8px;">Choose a file or drag &
                        drop it here</h3>
                    <p style="color: #999; font-size: 13px; margin: 0 0 20px;">Supported formats: MOBI, PDF, JPG, PNG, TIFF,
                        TXT (Max: 50MB)</p>

                    <label for="fileInput"
                        style="display: inline-block; padding: 10px 30px; border: 2px solid #4C3939; border-radius: 8px; color: #4C3939; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                        Browse Files
                    </label>
                    <input type="file" id="fileInput" name="file" class="d-none"
                        accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif" required>

                    <!-- Upload Type Tabs -->
                    <div style="display: flex; justify-content: center; gap: 0; margin-top: 25px;">
                        <button type="button" class="upload-tab-btn active" data-tab="singleUpload">
                            SINGLE UPLOAD
                        </button>
                        <button type="button" class="upload-tab-btn" data-tab="bulkUpload">
                            BULK UPLOAD
                        </button>
                    </div>
                </div>

                <!-- File Preview -->
                <div id="filePreview" class="d-none"
                    style="background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 40px; height: 40px; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-file-earmark" style="font-size: 20px; color: #666;"></i>
                        </div>
                        <span id="fileName" style="font-weight: 500; color: #333;"></span>
                    </div>
                    <button type="button" onclick="clearFile()"
                        style="background: #fff5f5; border: none; padding: 8px 12px; border-radius: 6px; color: #dc3545; cursor: pointer;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div id="singleUploadContent">
                    <!-- Single upload form fields will be shown -->
                </div>

                <div id="bulkUploadContent" style="display: none;">

                    <!-- Bulk Upload -->
                    <div class="tab-pane fade" id="bulkUpload">
                        <!-- Bulk Upload Info -->
                        <div class="d-flex justify-content-end mb-3">
                            <span class="text-muted"><i class="bi bi-clock me-1"></i> Editing <span
                                    id="bulkFileCount">0</span> Files in batch</span>
                        </div>

                        <!-- File Tabs -->
                        <div class="bulk-file-tabs bg-primary text-white p-2 rounded-top" id="bulkFileTabs"
                            style="display: none;">
                            <div class="d-flex align-items-center gap-2 overflow-auto">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <span class="text-white-50">Currently Editing:</span>
                                <div class="d-flex gap-2" id="fileTabsContainer"></div>
                            </div>
                        </div>

                        <!-- Bulk Drop Zone -->
                        <div class="upload-area mb-4" id="bulkDropZone">
                            <i class="bi bi-cloud-arrow-up upload-icon"></i>
                            <p class="upload-text">Drag & drop multiple files here</p>
                            <p class="upload-hint">MOBI, PDF, JPG, PNG, TIFF and TXT formats</p>
                            <label class="btn btn-secondary mt-3" for="bulkFileInput">Browse Files</label>
                            <input type="file" id="bulkFileInput" class="d-none"
                                accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif" multiple>
                        </div>

                        <!-- Bulk File List -->
                        <div id="bulkFileList" class="mb-4" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>File Name</th>
                                            <th>Type</th>
                                            <th>Size</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bulkFilesBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Fields -->
            <div class="row g-4">
                <!-- General Information -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="section-title">
                            <i class="bi bi-info-circle-fill"></i>
                            GENERAL INFORMATION
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">ARCHIVE
                                TITLE</label>
                            <input type="text" class="form-control form-control-custom" name="title"
                                value="<?= $editMode ? htmlspecialchars($editItem['title']) : '' ?>"
                                placeholder="e.g. Stranger Things: A Case Study" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">DATE
                                    PUBLISHED</label>
                                <div style="position: relative;">
                                    <input type="date" class="form-control form-control-custom" name="publication_date"
                                        value="<?= $editMode ? $editItem['publication_date'] : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">EDITION</label>
                                <select class="form-select form-control-custom" name="edition">
                                    <option value="">Select Edition</option>
                                    <option value="Morning" <?= ($editMode && $editItem['edition'] === 'Morning') ? 'selected' : '' ?>>Morning</option>
                                    <option value="Evening" <?= ($editMode && $editItem['edition'] === 'Evening') ? 'selected' : '' ?>>Evening</option>
                                    <option value="Special" <?= ($editMode && $editItem['edition'] === 'Special') ? 'selected' : '' ?>>Special</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label-custom">CATEGORY</label>
                                <select class="form-select form-control-custom" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($editMode && $editItem['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">PAGE
                                    COUNT</label>
                                <input type="number" class="form-control form-control-custom" name="page_count"
                                    value="<?= $editMode ? $editItem['page_count'] : '' ?>" placeholder="20" min="1">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">KEYWORDS
                                / TAGS</label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-custom" name="keywords"
                                    value="<?= $editMode ? htmlspecialchars($editItem['keywords']) : '' ?>"
                                    placeholder="Add tag..." style="border-radius: 8px 0 0 8px;">
                                <button type="button" class="btn"
                                    style="background: var(--primary-color); color: white; border-radius: 0 8px 8px 0; padding: 0 15px;">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <div class="mt-2" id="tagsContainer">
                                <span class="badge"
                                    style="background: #e0e0e0; color: #333; padding: 6px 10px; border-radius: 6px; margin-right: 5px; font-weight: 500;">
                                    POLITICS <i class="bi bi-x ms-1" style="cursor: pointer;"></i>
                                </span>
                                <span class="badge"
                                    style="background: #e0e0e0; color: #333; padding: 6px 10px; border-radius: 6px; margin-right: 5px; font-weight: 500;">
                                    SPORTS <i class="bi bi-x ms-1" style="cursor: pointer;"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Convert File -->
                        <div class="mt-4">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">CONVERSION
                                (OPTIONAL)</label>
                            <div style="display: flex; gap: 20px;">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="convertToPdf"
                                        name="convert_pdf">
                                    <label class="form-check-label" for="convertToPdf"
                                        style="font-size: 13px; color: #333; font-weight: 600;">CONVERT TO PDF</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="convertToEpub"
                                        name="convert_epub">
                                    <label class="form-check-label" for="convertToEpub"
                                        style="font-size: 13px; color: #333; font-weight: 600;">CONVERT TO EPUB</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Repository Details -->
                <div class="col-lg-6">
                    <div class="content-card">
                        <div class="section-title">
                            <i class="bi bi-archive-fill"></i>
                            REPOSITORY DETAILS
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">ORIGINAL
                                    TITLE</label>
                                <input type="text" class="form-control form-control-custom" name="publisher"
                                    value="<?= $editMode ? htmlspecialchars($editItem['publisher']) : '' ?>"
                                    placeholder="John Artaro">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"
                                    style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">LANGUAGE</label>
                                <select class="form-select form-control-custom" name="language_id">
                                    <option value="">Select Language</option>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang['id'] ?>" <?= ($editMode && $editItem['language_id'] == $lang['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lang['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">VOLUME /
                                ISSUE REFERENCE</label>
                            <input type="text" class="form-control form-control-custom" name="volume_issue"
                                value="<?= $editMode ? htmlspecialchars($editItem['volume_issue']) : '' ?>"
                                placeholder="VOL. XCIII, No. 44">
                        </div>

                        <div class="mt-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">ARCHIVE
                                DESCRIPTION</label>
                            <textarea class="form-control form-control-custom" name="description" rows="3"
                                placeholder="Add a detailed description for this archive entry..."
                                style="min-height: 100px;"><?= $editMode ? htmlspecialchars($editItem['description']) : '' ?></textarea>
                        </div>

                        <!-- Thumbnail & Status -->
                        <div class="row g-4 mt-3">
                            <div class="col-md-6">
                                <label class="form-label"
                                    style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">
                                    THUMBNAIL COVER (OPTIONAL)
                                </label>
                                <div class="upload-area" id="thumbnailArea"
                                    style="border: 2px dashed var(--border-color); background: var(--bg-light); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer;">
                                    <?php if ($editMode && $editItem['thumbnail_path']): ?>
                                        <img src="<?= APP_URL ?>/<?= $editItem['thumbnail_path'] ?>" id="thumbnailPreview"
                                            style="max-height: 50px; margin-bottom: 10px; display: block; margin: 0 auto 10px;">
                                    <?php else: ?>
                                        <img src="" id="thumbnailPreview"
                                            style="max-height: 50px; margin-bottom: 10px; display: none; margin: 0 auto 10px;">
                                        <div id="thumbnailIcon"
                                            style="width: 40px; height: 40px; background: var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                                            <i class="bi bi-image" style="color: #888; font-size: 20px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size: 10px; color: #888; font-weight: 600; margin-bottom: 2px;">
                                        JPG/PROFILE ONLY</div>
                                    <div style="font-size: 9px; color: #aaa;">(MAX 2MB)</div>
                                    <input type="file" id="thumbnailInput" name="thumbnail" class="d-none"
                                        accept=".jpg,.jpeg,.png">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div
                                    style="background: var(--bg-light); border-radius: 12px; padding: 20px; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                    <i class="bi bi-hourglass-split"
                                        style="font-size: 24px; color: #C08B5C; margin-bottom: 10px;"></i>
                                    <div
                                        style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px; margin-bottom: 5px;">
                                        STATUS</div>
                                    <div style="font-size: 11px; color: #7CA1BF; line-height: 1.4;">
                                        Waiting for primary file upload to begin automated cataloging...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        // Button references
        const discardBtn = document.getElementById('discardBtn');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadForm = document.getElementById('uploadForm');

        // File input handling
        const fileInput = document.getElementById('fileInput');

        // Drop zone handling
        const dropZone = document.querySelector('.upload-area'); // Main drop zone

        const filePreview = document.getElementById('filePreview');
        const fileNameSpan = document.getElementById('fileName');

        // Function to check if form has input
        function checkFormInput() {
            // For bulk upload
            if (bulkFiles.length > 0) {
                if (discardBtn) discardBtn.disabled = false;
                if (uploadBtn) uploadBtn.disabled = false;
                return;
            }

            // For single upload
            const title = document.querySelector('input[name="title"]')?.value.trim();
            const hasFile = fileInput && fileInput.files.length > 0;
            const hasInput = title || hasFile; // Allow discard if title is typed

            if (discardBtn) discardBtn.disabled = !hasInput;
            if (uploadBtn) uploadBtn.disabled = !(title && hasFile); // Require both for upload
        }

        // Add input listeners to all form fields
        const formInputs = document.querySelectorAll('#uploadForm input, #uploadForm select, #uploadForm textarea');
        formInputs.forEach(el => {
            el.addEventListener('input', function (e) {
                checkFormInput();
                // Update bulk file data if in bulk mode
                if (bulkFiles.length > 0) {
                    updateCurrentBulkFileData(e.target.name, e.target.value);
                }
            });
            el.addEventListener('change', function (e) {
                checkFormInput();
                if (bulkFiles.length > 0) {
                    updateCurrentBulkFileData(e.target.name, e.target.value);
                }
            });
        });

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    fileNameSpan.textContent = this.files[0].name;
                    filePreview.classList.remove('d-none');
                    filePreview.style.display = 'flex'; // Ensure flex layout
                    // Hide drop zone content but keep space or hide? 
                    // Based on previous design, we hide the drop zone instructions inside or the whole card content?
                    // The new design has drop zone as a card.
                    // Let's hide the dropzone content to show preview
                    document.querySelector('.upload-area').style.display = 'none';

                    checkFormInput();
                }
            });
        }

        function clearFile() {
            fileInput.value = '';
            filePreview.classList.add('d-none');
            // Show drop zone again
            document.querySelector('.upload-area').style.display = 'block';
            checkFormInput();
        }

        function resetForm() {
            // If in bulk mode, clear bulk files
            if (bulkFiles.length > 0) {
                bulkFiles = [];
                activeFileIndex = 0;
                updateBulkUI();
            }

            // Reset form fields
            document.getElementById('uploadForm').reset();
            clearFile();

            // Clear thumbnails
            const thumbnailPreview = document.getElementById('thumbnailPreview');
            const thumbnailIcon = document.getElementById('thumbnailIcon');
            if (thumbnailPreview) thumbnailPreview.style.display = 'none';
            if (thumbnailIcon) thumbnailIcon.style.display = 'flex';

            checkFormInput();
        }

        // Thumbnail preview
        const thumbnailInput = document.getElementById('thumbnailInput');
        const thumbnailPreview = document.getElementById('thumbnailPreview');
        const thumbnailIcon = document.getElementById('thumbnailIcon');

        if (thumbnailInput) {
            thumbnailInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (thumbnailPreview) {
                            thumbnailPreview.src = e.target.result;
                            thumbnailPreview.style.display = 'block';
                        }
                        if (thumbnailIcon) {
                            thumbnailIcon.style.display = 'none';
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        // ========== BULK UPLOAD FUNCTIONALITY ==========
        // Since we don't have a separate bulk input in the new design (we use tabs to switch mode),
        // we need to handle the tabs.

        const uploadTabBtns = document.querySelectorAll('.upload-tab-btn');
        let isBulkMode = false;

        // Single/Bulk Tab Switching
        uploadTabBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                // Update buttons state
                uploadTabBtns.forEach(b => {
                    b.classList.remove('active');
                    b.style.background = '#f5f5f5';
                    b.style.color = '#666';
                });
                this.classList.add('active');
                this.style.background = '#4C3939';
                this.style.color = 'white';

                const target = this.dataset.tab;
                if (target === 'bulkUpload') {
                    isBulkMode = true;
                    // Change file input to multiple
                    fileInput.setAttribute('multiple', '');
                    document.querySelector('.upload-area h3').textContent = 'Drag & drop multiple files here';
                    document.querySelector('label[for="fileInput"]').textContent = 'Browse Multiple Files';
                } else {
                    isBulkMode = false;
                    // Change file input to single
                    fileInput.removeAttribute('multiple');
                    document.querySelector('.upload-area h3').textContent = 'Choose a file or drag & drop it here';
                    document.querySelector('label[for="fileInput"]').textContent = 'Browse File';

                    // Clear bulk files if switching back? Or keep them? 
                    // Let's clear for simplicity to avoid confusion
                    if (bulkFiles.length > 0 && confirm('Switching to single upload will clear your current bulk selection. Continue?')) {
                        bulkFiles = [];
                        updateBulkUI();
                    } else if (bulkFiles.length > 0) {
                        // User cancelled switch
                        // Revert tab
                        // For now just carry on, they might switch back
                    }
                }
            });
        });

        // Store file data
        let bulkFiles = [];
        let activeFileIndex = 0;

        // Listen for file selection (same input used for both, but attributes change)
        if (fileInput) {
            fileInput.removeEventListener('change', originalFileHandler); // Remove old listener if exists

            fileInput.addEventListener('change', function () {
                if (isBulkMode) {
                    const files = Array.from(this.files);
                    if (files.length > 0) {
                        files.forEach(file => {
                            // Initial metadata for each file
                            bulkFiles.push({
                                file: file,
                                name: file.name,
                                size: file.size,
                                type: file.type,
                                status: 'waiting',
                                // Metadata fields
                                title: file.name.split('.')[0], // Default title is filename
                                publication_date: '',
                                edition: '',
                                category_id: '',
                                page_count: '',
                                keywords: '',
                                publisher: '',
                                volume_issue: '',
                                description: '',
                                language_id: '',
                                thumbnail: null
                            });
                        });

                        // Hide drop zone, show bulk UI
                        document.querySelector('.upload-area').parentElement.style.display = 'none'; // Check hierarchy

                        updateBulkUI();
                    }
                } else {
                    // Single upload logic
                    if (this.files.length > 0) {
                        fileNameSpan.textContent = this.files[0].name;
                        filePreview.classList.remove('d-none');
                        filePreview.style.display = 'flex';
                        document.querySelector('.upload-area').parentElement.style.display = 'none'; // Hide drop card

                        // Auto-fill title with filename
                        const titleInput = document.querySelector('input[name="title"]');
                        if (titleInput && !titleInput.value) {
                            titleInput.value = this.files[0].name.split('.')[0];
                        }

                        checkFormInput();
                    }
                }
            });
        }

        function updateCurrentBulkFileData(field, value) {
            if (bulkFiles[activeFileIndex]) {
                bulkFiles[activeFileIndex][field] = value;
            }
        }

        function loadBulkFileData(index) {
            const fileData = bulkFiles[index];
            if (!fileData) return;

            // Populate form fields
            const fields = ['title', 'publication_date', 'edition', 'category_id', 'page_count', 'keywords', 'publisher', 'volume_issue', 'description', 'language_id'];
            fields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) input.value = fileData[field] || '';
            });

            // Determine active tab/ui
            // Update styling for active tab in bulk list
            document.querySelectorAll('.bulk-file-tab').forEach((el, idx) => {
                if (idx === index) {
                    el.classList.add('active');
                    el.style.backgroundColor = '#C08B5C'; // Active color
                    el.style.color = 'white';
                } else {
                    el.classList.remove('active');
                    el.style.backgroundColor = 'white';
                    el.style.color = '#4C3939';
                }
            });
        }

        function updateBulkUI() {
            const bulkContainer = document.getElementById('bulkUploadContent');

            if (bulkFiles.length > 0) {
                // Generate Bulk UI if not exists or update it
                let bulkList = document.getElementById('bulk-list-container');
                if (!bulkList) {
                    bulkList = document.createElement('div');
                    bulkList.id = 'bulk-list-container';
                    bulkList.className = 'mb-4';
                    // Insert before form fields
                    document.querySelector('.row.g-4').before(bulkList);
                }

                // Currently Editing Header
                let html = `
                    <div class="bulk-header">
                        <div style="font-weight: 600; font-size: 14px;"><i class="bi bi-collection-fill me-2"></i>BULK EDITING (${bulkFiles.length} FILES)</div>
                        <button class="btn btn-sm btn-outline-light" onclick="if(confirm('Clear all?')) { bulkFiles=[]; updateBulkUI(); }">Clear All</button>
                    </div>
                    <div class="bulk-list-container">
                `;

                bulkFiles.forEach((file, idx) => {
                    const isActive = idx === activeFileIndex;
                    const bg = isActive ? '#C08B5C' : 'white';
                    const color = isActive ? 'white' : '#4C3939';
                    const border = isActive ? 'none' : '1px solid #ddd';

                    html += `
                        <div class="bulk-file-tab" onclick="setActiveFile(${idx})" 
                            style="display: inline-block; padding: 10px 15px; background: ${bg}; color: ${color}; border-radius: 8px; margin-right: 10px; cursor: pointer; border: ${border}; font-size: 13px; font-weight: 500;">
                            <i class="bi bi-file-earmark me-1"></i> ${file.name.substring(0, 15)}${file.name.length > 15 ? '...' : ''}
                            ${file.status === 'success' ? '<i class="bi bi-check-circle-fill ms-2 text-white"></i>' : ''}
                            ${file.status === 'error' ? '<i class="bi bi-exclamation-circle-fill ms-2 text-danger"></i>' : ''}
                        </div>
                   `;
                });

                html += `</div>`;
                bulkList.innerHTML = html;
                bulkList.style.display = 'block';

                // Make sure form is visible
                document.querySelector('.row.g-4').style.display = 'flex';

                // Load data for active file
                loadBulkFileData(activeFileIndex);

                // Update upload button text
                if (uploadBtn) {
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload ${bulkFiles.length} Files`;
                    uploadBtn.disabled = false;
                    // Change button processing to AJAX
                    uploadBtn.onclick = processBulkUpload;
                    uploadBtn.type = 'button'; // Prevent default submit
                }

            } else {
                // No bulk files, reset UI
                const bulkList = document.getElementById('bulk-list-container');
                if (bulkList) bulkList.style.display = 'none';

                // Show drop zone
                document.querySelector('.upload-area').parentElement.style.display = 'block';

                // Reset button
                if (uploadBtn) {
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload to Cloud`;
                    uploadBtn.type = 'submit';
                    uploadBtn.onclick = null;
                }
            }
        }

        function setActiveFile(idx) {
            activeFileIndex = idx;
            loadBulkFileData(idx);
        }

        async function processBulkUpload() {
            if (bulkFiles.length === 0) return;

            uploadBtn.disabled = true;
            uploadBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Uploading...`;

            let uploadedCount = 0;
            let errorCount = 0;

            for (let i = 0; i < bulkFiles.length; i++) {
                const fileData = bulkFiles[i];
                if (fileData.status === 'success') {
                    uploadedCount++;
                    continue; // Skip already uploaded
                }

                // Update UI to show processing
                setActiveFile(i);

                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('file', fileData.file);

                // Append metadata
                Object.keys(fileData).forEach(key => {
                    if (key !== 'file' && key !== 'status' && key !== 'thumbnail') {
                        formData.append(key, fileData[key]);
                    }
                });

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    // Since the PHP redirects, we might get a redirected response
                    // Ideally check response text or status
                    const text = await response.text();

                    if (text.includes('uploaded successfully') || response.ok) {
                        fileData.status = 'success';
                        uploadedCount++;
                    } else {
                        fileData.status = 'error';
                        errorCount++;
                    }
                } catch (e) {
                    console.error(e);
                    fileData.status = 'error';
                    errorCount++;
                }

                updateBulkUI();
            }

            uploadBtn.disabled = false;
            uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload Completed`;

            if (errorCount === 0) {
                alert('All files uploaded successfully!');
                window.location.href = 'dashboard.php';
            } else {
                alert(`Upload complete with ${errorCount} errors.`);
            }
        }
    </script>
</body>

</html>