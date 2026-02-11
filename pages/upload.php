<?php
/**
 * Upload Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calibre.php';

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

                    // Convert MOBI to EPUB for web reading
                    if ($fileExt === 'mobi' && isCalibreAvailable()) {
                        $result = convertMobiToEpub($uploadPath);
                        if ($result['success']) {
                            // Log conversion success
                            error_log("MOBI converted to EPUB: " . $result['epub_path']);
                        } else {
                            // Log conversion failure but don't block upload
                            error_log("MOBI conversion failed: " . $result['error']);
                        }
                    }

                    logActivity($currentUser['id'], 'upload', $title);
                    showAlert('success', 'Document uploaded successfully.');
                } else {
                    showAlert('danger', 'Failed to upload file. Please try again.');
                }
            } else {
                showAlert('danger', 'Please select a file to upload.');
            }
            redirect('dashboard.php?success=upload');
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
            redirect('upload.php?success=edit');
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
    
    <!-- Custom CSS for Upload Page -->
    <style>
        /* Bulk Upload Tab Styling */
        .bulk-file-tab {
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
            border-radius: 8px 8px 0 0;
        }
        
        .bulk-file-tab:hover {
            background-color: #f5f5f5;
        }
        
        .bulk-file-tab.active {
            border-bottom-color: #C08B5C;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(192, 139, 92, 0.1);
        }
        
        /* Page Order Grid */
        .page-order-item {
            transition: all 0.2s ease;
            cursor: move;
            position: relative;
            overflow: hidden;
        }
        
        .page-order-item img {
            transition: transform 0.2s ease;
        }
        
        .page-order-item:hover img {
            transform: scale(1.05);
        }
        
        .page-order-item.active-item {
            box-shadow: 0 6px 20px rgba(192, 139, 92, 0.5) !important;
            transform: scale(1.02);
            border-color: #C08B5C !important;
        }
        
        .page-order-item:not(.active-item):hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            border-color: #C08B5C;
        }
        
        .page-order-item.drag-over {
            background-color: rgba(192, 139, 92, 0.15) !important;
            border: 2px dashed #C08B5C !important;
            opacity: 0.9;
        }
        
        .page-order-item .position-absolute.top-0 {
            z-index: 10;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .page-order-item:hover .position-absolute.top-0 {
            opacity: 1;
        }
        
        /* Upload Drop Zone */
        .upload-drop-zone {
            border: 2px dashed #E6E8EB;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: #F9FAFB;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-drop-zone:hover {
            border-color: #C08B5C;
            background-color: #FFF8F6;
        }
        
        .upload-drop-zone.dragover {
            border-color: #C08B5C;
            background-color: #FFF8F6;
            box-shadow: 0 4px 16px rgba(192, 139, 92, 0.2);
        }
        
        /* Disabled Button State */
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Tab Bar */
        #bulkFileTabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: thin;
            scrollbar-color: #C08B5C #f5f5f5;
        }
        
        #bulkFileTabs::-webkit-scrollbar {
            height: 6px;
        }
        
        #bulkFileTabs::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 3px;
        }
        
        #bulkFileTabs::-webkit-scrollbar-thumb {
            background: #C08B5C;
            border-radius: 3px;
        }
        
        #bulkFileTabs > div {
            white-space: nowrap;
            flex-shrink: 0;
        }
    </style>
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
                    <button type="button" onclick="window.location.href='upload.php'"
                        style="padding: 10px 20px; background: #f5f5f5; border: none; border-radius: 8px; color: #666; font-weight: 500;">
                        Discard
                    </button>
                    <button type="submit" form="uploadForm"
                        style="padding: 10px 20px; background: #4C3939; border: none; border-radius: 8px; color: white; font-weight: 500;">
                        <i class="bi bi-cloud-upload me-2"></i>Upload
                    </button>
                <?php else: ?>
                    <button type="button" id="discardBtn" onclick="resetForm()" disabled
                        style="padding: 10px 20px; background: #f5f5f5; border: none; border-radius: 8px; color: #666; font-weight: 500; cursor: pointer;">
                        Discard
                    </button>
                    <button type="submit" form="uploadForm" id="uploadBtn" disabled
                        style="padding: 10px 20px; background: #4C3939; border: none; border-radius: 8px; color: white; font-weight: 500; cursor: pointer;">
                        <i class="bi bi-cloud-upload me-2"></i>Upload
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
                <!-- Drop Zone Card (Mockup Style) -->
                <div class="upload-drop-zone text-center p-5 mb-4 border-2 border-dashed rounded-3 bg-white"
                    style="border-style: dashed !important; border-color: #E0E0E0; border-width: 2px;">
                    <div class="mb-3">
                        <span style="display: inline-block; padding: 15px; background: #F5F5F5; border-radius: 12px;">
                            <i class="bi bi-cloud-arrow-up-fill" style="font-size: 24px; color: #5D4037;"></i>
                        </span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">Drag & Drop Primary File</h5>
                    <p class="text-muted small mb-3" style="font-size: 12px;">PDF, TIFF, or High-Res JPG (Maximum 100MB)</p>

                    <label class="btn px-4 py-2" for="fileInput"
                        style="background: #E0E0E0; color: #5D4037; font-weight: 700; font-size: 11px; letter-spacing: 1px; border-radius: 20px; text-transform: uppercase;">
                        SELECT FILE
                    </label>
            <input type="file" id="fileInput" name="file" class="d-none"
                        accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif" multiple required>
                </div>

                <!-- File Preview -->
                <div id="filePreview" class="d-none"
                    style="background: #F9F5F2; border: 2px solid #C08B5C; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(76, 57, 57, 0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div
                                style="width: 48px; height: 48px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid #E6D5C9;">
                                <i class="bi bi-file-earmark-text-fill" style="font-size: 24px; color: #C08B5C;"></i>
                            </div>
                            <div>
                                <div
                                    style="font-size: 11px; font-weight: 700; color: #8D6E63; letter-spacing: 0.5px; text-transform: uppercase;">
                                    SELECTED FILE</div>
                                <span id="fileName" style="font-weight: 700; color: #4C3939; font-size: 16px;"></span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <!-- Duplicate Check Status -->
                            <div id="duplicateCheckStatus" style="display: block;">
                                <span id="statusWaiting" style="display: none; color: #888; font-size: 13px;">
                                    <i class="bi bi-hourglass me-1"></i> Waiting...
                                </span>
                                <span id="statusChecking" style="display: none; color: #C08B5C; font-size: 13px;">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Checking...
                                </span>
                                <span id="statusReady" style="display: none; color: #22C55E; font-size: 13px;">
                                    <i class="bi bi-check-circle-fill me-1"></i> Ready to upload
                                </span>
                                <span id="statusDuplicate" style="display: none; color: #D32F2F; font-size: 13px;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    <span id="duplicateMessage">Duplicate found!</span>
                                </span>
                            </div>
                            <button type="button" onclick="clearFile()"
                                style="background: white; border: 1px solid #E6D5C9; padding: 8px 12px; border-radius: 8px; color: #A1887F; cursor: pointer; transition: all 0.2s;"
                                onmouseover="this.style.background='#FFF8F6'; this.style.color='#4C3939'"
                                onmouseout="this.style.background='white'; this.style.color='#A1887F'">
                                <i class="bi bi-trash3-fill me-1"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>

                <div id="singleUploadContent">
                    <!-- Single upload form fields will be shown -->
                </div>

                <div id="bulkUploadContent" style="display: none;">

                    <!-- Bulk Upload Mode Notification - Always visible in bulk mode -->
                    <div id="bulkModeAlert" class="alert active d-flex align-items-center mb-4" role="alert"
                        style="border-left: 5px solid #C08B5C; background-color: #F9F5F2; border-radius: 8px; box-shadow: 0 2px 8px rgba(192, 139, 92, 0.1); color: #5D4037;">
                        <i class="bi bi-collection-fill me-3" style="font-size: 28px; color: #C08B5C;"></i>
                        <div>
                            <h5 class="alert-heading mb-1" style="font-weight: 700; color: #4E342E;">Bulk Upload Mode</h5>
                            <div style="font-size: 14px;">
                                You are currently using <strong>Bulk Upload</strong>. This allows you to upload multiple
                                files at once.
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Header Statistics (Document Mode) -->
                    <div id="bulkStatsBar"
                        class="d-none d-flex align-items-center justify-content-between p-3 mb-4 bg-white rounded shadow-sm border">
                        <div class="d-flex text-uppercase small fw-bold" style="letter-spacing: 0.5px; gap: 30px;">
                            <div>
                                <span class="text-muted d-block" style="font-size: 10px;">Total Files</span>
                                <span class="fs-5 text-dark" id="totalFilesCount">0</span>
                            </div>
                            <div>
                                <span class="text-muted d-block" style="font-size: 10px; color: #4CAF50 !important;">Ready</span>
                                <span class="fs-5" style="color: #4CAF50;" id="readyFilesCount">0</span>
                            </div>
                            <div>
                                <span class="text-muted d-block" style="font-size: 10px; color: #FF9800 !important;">Pending</span>
                                <span class="fs-5" style="color: #FF9800;" id="pendingFilesCount">0</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-dark fw-bold text-uppercase"
                            style="font-size: 11px; letter-spacing: 0.5px; white-space: nowrap;"
                            onclick="document.getElementById('bulkFileInput').click()">
                            <i class="bi bi-plus-lg me-1"></i> Add Files
                        </button>
                    </div>

                    <!-- Bulk Document Tabs/List -->
                    <div id="bulkDocumentList" class="d-none mb-4">
                        <div class="d-flex gap-2 overflow-auto pb-2 mb-3" id="bulkFileTabs" style="border-bottom: 2px solid #E0E0E0;">
                            <!-- Tabs will be injected here via JS -->
                        </div>
                    </div>

                    <!-- Bulk Image Page Order -->
                    <div id="bulkPageOrder" class="d-none mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="section-title m-0">
                                <i class="bi bi-grid-3x3-gap-fill"></i> PAGE ORDER MANAGEMENT
                            </div>
                            <span class="badge bg-light text-secondary border">DRAG AND DROP TO REORDER</span>
                        </div>

                        <div class="row g-3" id="pageOrderGrid">
                            <!-- Images will be injected here via JS -->
                        </div>
                    </div>

                    <!-- Default Bulk Drop Zone (Initial State) -->
                    <div class="upload-area mb-4" id="bulkDropZone">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <p class="upload-text">Drag & drop multiple files here</p>
                        <p class="upload-hint">MOBI, PDF, JPG, PNG, TIFF and TXT formats</p>
                            <label class="btn btn-secondary mt-3" for="bulkFileInput"
                                style="padding: 10px 30px; border: 2px solid #4C3939; background: transparent; border-radius: 8px; color: #4C3939; font-weight: 500; cursor: pointer; transition: all 0.2s;">Browse
                                Files</label>
                            <input type="file" id="bulkFileInput" class="d-none"
                                accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif" multiple>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Fields -->
            <div class="row g-4">
                <!-- General Information -->
                <div class="col-lg-8">
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





                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label-custom">LANGUAGE</label>
                                <select class="form-select form-control-custom" name="language_id">
                                    <option value="">Select Language</option>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang['id'] ?>" <?= ($editMode && $editItem['language_id'] == $lang['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lang['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">VOLUME / ISSUE REFERENCE</label>
                                <input type="text" class="form-control form-control-custom" name="volume_issue"
                                    value="<?= $editMode ? htmlspecialchars($editItem['volume_issue']) : '' ?>"
                                    placeholder="VOL. XCIII, No. 44">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.5px;">KEYWORDS
                                / TAGS</label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-custom" id="keywordInput"
                                    placeholder="Type a tag and press add..." style="border-radius: 8px 0 0 8px;">
                                <button type="button" class="btn" id="addTagBtn"
                                    style="background: #4E342E; color: white; border-radius: 0 8px 8px 0; padding: 0 15px;">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <input type="hidden" name="keywords" id="hiddenKeywords"
                                value="<?= $editMode ? htmlspecialchars($editItem['keywords']) : '' ?>">
                            <div class="mt-2 d-flex gap-2 flex-wrap" id="tagsContainer">
                                <!-- Tags will be added here dynamically -->
                            </div>

                        </div>

                        <div class="mt-3">
                            <label class="form-label-custom">DESCRIPTION</label>
                            <textarea class="form-control form-control-custom" name="description" rows="3"
                                placeholder="Enter a comprehensive description of the archive content..."
                                style="min-height: 100px;"><?= $editMode ? htmlspecialchars($editItem['description']) : '' ?></textarea>
                        </div>


                    </div>
                </div>

        <!-- Cover Preview / Thumbnail -->
        <div class="col-lg-4"> <!-- Adjusted column width to match layout -->
            <div class="content-card h-100" style="display: flex; flex-direction: column;">
                <div class="section-title d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-image me-2"></i>THUMBNAIL PREVIEW
                    </span>
                    <span class="badge bg-light text-primary border"
                        style="font-size: 9px; letter-spacing: 0.5px; color: #8DA9C4 !important; border-color: #E6EEF5 !important;">VISUAL
                        FOCUS</span>
                </div>

                <div class="flex-grow-1 d-flex flex-col align-items-center justify-content-center"
                    style="background: #F9FAFB; border: 2px dashed #E6E8EB; border-radius: 12px; margin-top: 20px; min-height: 400px; position: relative;">

                    <?php if ($editMode && $editItem['thumbnail_path']): ?>
                        <img src="<?= APP_URL ?>/<?= $editItem['thumbnail_path'] ?>" id="thumbnailPreview"
                            style="max-width: 100%; max-height: 350px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 8px;">
                        <div class="mt-3 text-center">
                            <label class="btn btn-outline-secondary btn-sm" for="thumbnailInput"
                                style="border-radius: 4px; font-size: 10px; font-weight: 700; padding: 8px 16px; letter-spacing: 0.5px; border-color: #4C3939; color: #4C3939;">
                                CHANGE THUMBNAIL
                            </label>
                        </div>
                    <?php else: ?>
                        <div id="previewPlaceholder" class="text-center p-4">
                            <div
                                style="width: 64px; height: 64px; background: #E6E8EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                <i class="bi bi-image" style="color: #9CA3AF; font-size: 28px;"></i>
                            </div>
                            <h6
                                style="color: #4B5563; font-weight: 700; letter-spacing: 0.5px; font-size: 13px; margin-bottom: 8px;">
                                UPLOAD THUMBNAIL</h6>
                            <p
                                style="color: #9CA3AF; font-size: 11px; max-width: 200px; margin: 0 auto 20px; line-height: 1.5;">
                                Recommended aspect ratio 4:5 for vertical archive cover images.
                            </p>
                            <label class="btn btn-outline-dark btn-sm" for="thumbnailInput"
                                style="border-radius: 4px; font-size: 10px; font-weight: 700; padding: 8px 16px; letter-spacing: 0.5px; border-color: #4C3939; color: #4C3939;">
                                BROWSE MEDIA LIBRARY
                            </label>
                        </div>
                    <?php endif; ?>

                    <img id="thumbnailPreview" style="display: none; max-width: 100%; max-height: 350px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 8px;">
                    <input type="file" id="thumbnailInput" name="thumbnail" class="d-none" accept=".jpg,.jpeg,.png,.tiff,.tif">
                </div>
            </div>
        </div>

        <!-- End Form Fields Row -->
        </div>
        </form>
    </main>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        // Add form submission handler for single file uploads
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadForm');
            form.addEventListener('submit', function(e) {
                // Only prevent default for single file uploads (not bulk)
                if (!isBulkMode && fileInput && fileInput.files.length > 0) {
                    // For single files, allow normal submission since backend handles it
                    // The success modal will be triggered on page return via query parameter
                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Uploading...`;
                }
            });
        });
    </script>

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
                    // Hide drop zone content
                    document.querySelector('.upload-area').style.display = 'none';

                    checkFormInput();

                    // Trigger duplicate check when file is selected
                    const title = document.querySelector('input[name="title"]')?.value.trim();
                    if (title) {
                        checkDuplicate(title, this.files[0].name);
                    }
                }
            });
        }

        // Status display elements
        const statusWaiting = document.getElementById('statusWaiting');
        const statusChecking = document.getElementById('statusChecking');
        const statusReady = document.getElementById('statusReady');
        const statusDuplicate = document.getElementById('statusDuplicate');
        const duplicateMessage = document.getElementById('duplicateMessage');

        // Track duplicate check status
        let isDuplicateCheckPassed = false;
        let duplicateCheckTimeout = null;

        function showStatus(status, message = '') {
            // Hide all status states
            if (statusWaiting) statusWaiting.style.display = 'none';
            if (statusChecking) statusChecking.style.display = 'none';
            if (statusReady) statusReady.style.display = 'none';
            if (statusDuplicate) statusDuplicate.style.display = 'none';

            // Show the requested status
            switch (status) {
                case 'waiting':
                    if (statusWaiting) statusWaiting.style.display = 'block';
                    break;
                case 'checking':
                    if (statusChecking) statusChecking.style.display = 'block';
                    break;
                case 'ready':
                    if (statusReady) statusReady.style.display = 'block';
                    isDuplicateCheckPassed = true;
                    break;
                case 'duplicate':
                    if (statusDuplicate) statusDuplicate.style.display = 'block';
                    if (duplicateMessage && message) duplicateMessage.textContent = message;
                    isDuplicateCheckPassed = false;
                    break;
            }

            // Update upload button state
            updateUploadButtonState();
        }

        function updateUploadButtonState() {
            const title = document.querySelector('input[name="title"]')?.value.trim();
            const hasFile = fileInput && fileInput.files.length > 0;

            if (uploadBtn) {
                // Enable upload button only if we have title, file, and passed duplicate check
                uploadBtn.disabled = !(title && hasFile && isDuplicateCheckPassed);
            }
        }

        async function checkDuplicate(title, fileName) {
            if (!title && !fileName) {
                showStatus('waiting');
                return;
            }

            showStatus('checking');

            try {
                const formData = new FormData();
                formData.append('title', title || '');
                formData.append('file_name', fileName || '');

                const response = await fetch('<?= APP_URL ?>/api/check_duplicate.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.is_duplicate) {
                    showStatus('duplicate', data.message);
                } else {
                    showStatus('ready');
                }
            } catch (error) {
                console.error('Duplicate check error:', error);
                // On error, allow upload (fail open)
                showStatus('ready');
            }
        }

        // Title input change handler - trigger duplicate check
        const titleInput = document.querySelector('input[name="title"]');
        if (titleInput) {
            titleInput.addEventListener('input', function () {
                // Debounce the duplicate check
                clearTimeout(duplicateCheckTimeout);

                const title = this.value.trim();
                const fileName = fileInput?.files[0]?.name || '';

                if (title && fileName) {
                    duplicateCheckTimeout = setTimeout(() => {
                        checkDuplicate(title, fileName);
                    }, 500); // Wait 500ms after user stops typing
                } else if (title || fileName) {
                    showStatus('waiting');
                    isDuplicateCheckPassed = false;
                    updateUploadButtonState();
                }
            });
        }

        function clearFile() {
            // Reset file input
            fileInput.value = '';
            
            // Reset UI for single upload
            filePreview.classList.add('d-none');
            filePreview.style.display = 'none';
            
            // Show drop zone again
            const dropZone = document.querySelector('.upload-drop-zone');
            if (dropZone) dropZone.style.display = 'block';
            
            // Reset bulk UI if in bulk mode
            if (isBulkMode) {
                bulkFiles = [];
                activeFileIndex = 0;
                isBulkMode = false;
                document.getElementById('bulkUploadContent').style.display = 'none';
                updateBulkUI();
            }
            
            // Reset status indicators
            showStatus('waiting');
            isDuplicateCheckPassed = false;
            
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
        const thumbnailArea = document.getElementById('thumbnailArea');

        // Add click handler to thumbnail area to trigger file input
        if (thumbnailArea && thumbnailInput) {
            thumbnailArea.addEventListener('click', function () {
                thumbnailInput.click();
            });
        }

        if (thumbnailInput) {
            thumbnailInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (thumbnailPreview) {
                            thumbnailPreview.src = e.target.result;
                            thumbnailPreview.style.display = 'block';
                        }
                        const previewPlaceholder = document.getElementById('previewPlaceholder');
                        if (previewPlaceholder) previewPlaceholder.style.display = 'none';
                        
                        // Mark current file as edited in bulk mode
                        if (isBulkMode && bulkFiles[activeFileIndex]) {
                            bulkFiles[activeFileIndex].thumbnail = this.files[0];
                            bulkFiles[activeFileIndex].isEdited = true;
                            bulkFiles[activeFileIndex].lastEditTime = Date.now();
                            updateBulkUI();
                            
                            // Update cover preview
                            updateCoverPreview(activeFileIndex);
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        // ========== BULK UPLOAD FUNCTIONALITY ==========
        // Auto-detect bulk mode based on number of files selected

        let isBulkMode = false;
        let isEditingBulkFile = false; // Track if user is currently editing bulk file metadata

        // Store file data
        let bulkFiles = [];
        let activeFileIndex = 0;

        // Listen for file selection - auto-detect bulk mode based on file count
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const files = Array.from(this.files);

                // Auto-detect bulk mode based on file count
                if (files.length > 1) {
                    isBulkMode = true;

                    files.forEach(file => {
                        // Initial metadata for each file
                        bulkFiles.push({
                            file: file,
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            ext: file.name.split('.').pop().toLowerCase(),
                            status: 'waiting',
                            isEdited: false, // Track if metadata has been filled in
                            lastEditTime: 0, // Track when last edited
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
                    document.querySelector('.upload-drop-zone').style.display = 'none';

                    // Show bulk upload content
                    document.getElementById('singleUploadContent').style.display = 'none';
                    document.getElementById('bulkUploadContent').style.display = 'block';

                    updateBulkUI();
                } else if (files.length === 1) {
                    // Single upload logic
                    isBulkMode = false;
                    fileNameSpan.textContent = files[0].name;
                    filePreview.classList.remove('d-none');
                    filePreview.style.display = 'block';
                    document.querySelector('.upload-drop-zone').style.display = 'none';

                    // Auto-fill title with filename
                    const titleInput = document.querySelector('input[name="title"]');
                    if (titleInput && !titleInput.value) {
                        titleInput.value = files[0].name.split('.')[0];
                    }

                    // Check for duplicates
                    const title = document.querySelector('input[name="title"]').value;
                    checkDuplicate(title, files[0].name);

                    checkFormInput();
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

            // Update Cover Preview
            updateCoverPreview(index);

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
            const bulkDropZone = document.getElementById('bulkDropZone');
            const bulkPageOrder = document.getElementById('bulkPageOrder');
            const bulkDocumentList = document.getElementById('bulkDocumentList');
            const bulkStatsBar = document.getElementById('bulkStatsBar');

            if (bulkFiles.length > 0) {
                bulkDropZone.style.display = 'none';

                // Determine mode: Image or Document
                // If any file is PDF/MOBI/EPUB/TXT, defaults to Document mode.
                // If all are images, Image mode.
                const hasDocuments = bulkFiles.some(f => ['pdf', 'mobi', 'epub', 'txt'].includes(f.ext));
                const mode = hasDocuments ? 'document' : 'image';

                // Update Counts for Document Mode
                document.getElementById('totalFilesCount').textContent = bulkFiles.length.toString().padStart(2, '0');
                document.getElementById('readyFilesCount').textContent = bulkFiles.filter(f => f.isEdited === true).length.toString().padStart(2, '0');
                document.getElementById('pendingFilesCount').textContent = bulkFiles.filter(f => f.isEdited !== true).length.toString().padStart(2, '0');


                if (mode === 'image') {
                    // Image Mode UI
                    bulkPageOrder.classList.remove('d-none');
                    bulkDocumentList.classList.add('d-none');
                    bulkStatsBar.classList.add('d-none'); // Don't show stats for images

                    const grid = document.getElementById('pageOrderGrid');
                    grid.innerHTML = '';

                    bulkFiles.forEach((file, idx) => {
                        const col = document.createElement('div');
                        col.className = 'col-md-2 col-4'; // Grid layout

                        // Create thumbnail URL
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            const img = col.querySelector('img');
                            if (img) img.src = e.target.result;
                        };
                        reader.readAsDataURL(file.file);

                        col.innerHTML = `
                            <div class="position-relative page-order-item ${idx === activeFileIndex ? 'active-item' : ''}" onclick="setActiveFile(${idx})" 
                                 draggable="true"
                                 ondragstart="handleDragStart(event, ${idx})"
                                 ondragover="handleDragOver(event)"
                                 ondrop="handleDrop(event, ${idx})"
                                 ondragenter="handleDragEnter(event)"
                                 ondragleave="handleDragLeave(event)"
                                 style="background: #E0E0E0; border-radius: 8px; aspect-ratio: 3/4; overflow: hidden; cursor: pointer; border: ${idx === activeFileIndex ? '3px solid #C08B5C' : '1px solid #ddd'}; transition: all 0.2s;">
                                <img src="" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;" alt="Page ${idx + 1}" draggable="false">
                                <div class="position-absolute d-flex align-items-center justify-content-center" 
                                     style="top: 50%; left: 50%; transform: translate(-50%, -50%); width: 30px; height: 30px; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; font-weight: bold; font-size: 12px;">
                                    ${idx + 1}
                                </div>
                                <div class="position-absolute top-0 end-0 p-1" onclick="event.stopPropagation(); removeBulkFile(${idx});">
                                    <i class="bi bi-x-circle-fill text-danger bg-white rounded-circle" style="font-size: 14px; cursor: pointer;"></i>
                                </div>
                            </div>
                        `;
                        grid.appendChild(col);
                    });

                } else {
                    // Document Mode UI - Show stats and tabs
                    bulkPageOrder.classList.add('d-none');
                    bulkDocumentList.classList.remove('d-none');
                    bulkStatsBar.classList.remove('d-none');

                    const tabsContainer = document.getElementById('bulkFileTabs');
                    tabsContainer.innerHTML = '';

                    bulkFiles.forEach((file, idx) => {
                        const isActive = idx === activeFileIndex;
                        const isEdited = file.isEdited === true;

                        const tab = document.createElement('button');
                        tab.type = 'button';
                        tab.className = `bulk-file-tab d-flex align-items-center gap-2 px-3 py-2 rounded-top border-bottom-0 ${isActive ? 'bg-white text-dark shadow-sm active' : 'bg-light text-muted'}`;
                        tab.style.cursor = 'pointer';
                        tab.style.minWidth = '160px';
                        tab.style.border = isActive ? 'none' : '1px solid transparent';
                        if (isActive) {
                            tab.style.borderTop = '3px solid #C08B5C';
                            tab.style.boxShadow = '0 2px 8px rgba(192, 139, 92, 0.2)';
                        }

                        let icon = 'bi-file-earmark-text';
                        if (file.ext === 'pdf') icon = 'bi-file-earmark-pdf';
                        if (file.ext === 'mobi' || file.ext === 'epub') icon = 'bi-book';

                        // Status indicator: checkmark for edited, pending for not edited
                        let statusHTML = '';
                        if (isEdited) {
                            statusHTML = `<i class="bi bi-check-circle-fill" style="color: #22C55E; font-size: 14px;"></i>`;
                        } else {
                            statusHTML = `<span class="rounded-circle" style="width: 8px; height: 8px; background: #FF9800;"></span>`;
                        }

                        tab.innerHTML = `
                            <i class="bi ${icon}" style="font-size: 16px;"></i>
                            <span class="small fw-bold text-truncate" style="max-width: 80px; display: inline-block; white-space: normal; overflow: visible;">${file.name}</span>
                            ${statusHTML}
                         `;
                        tab.onclick = () => setActiveFile(idx);
                        tabsContainer.appendChild(tab);
                    });
                }

                // Load data for active file
                loadBulkFileData(activeFileIndex);

                // Update upload button
                if (uploadBtn) {
                    // For Documents "Publish All Files", for Images "Finalize Upload"
                    const btnText = mode === 'image' ? 'Finalize Upload' : 'Publish All Files';
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>${btnText}`;
                    uploadBtn.disabled = false;
                    uploadBtn.onclick = processBulkUpload;
                    uploadBtn.type = 'button';
                }

                // Add form field change listeners for bulk mode sync
                addBulkFormListeners();

            } else {
                // No bulk files, reset UI
                bulkDropZone.style.display = 'block';
                bulkPageOrder.classList.add('d-none');
                bulkDocumentList.classList.add('d-none');
                bulkStatsBar.classList.add('d-none');
                document.getElementById('bulkUploadContent').style.display = 'none'; // Hide container if empty

                // Show drop zone
                document.querySelector('.upload-drop-zone').style.display = 'block'; // Show MAIN drop zone if resetting completely? 
                // Wait, if we are in bulk mode tab but no files, show BULK drop zone.
                // But if we reset logic... 

                if (uploadBtn) {
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload`;
                    uploadBtn.type = 'submit';
                    uploadBtn.onclick = null;
                }
                removeBulkFormListeners();
            }
        }

        // Function to remove a single file from bulk list
        function removeBulkFile(idx) {
            bulkFiles.splice(idx, 1);
            
            // If all files removed, reset everything
            if (bulkFiles.length === 0) {
                fileInput.value = '';
                isBulkMode = false;
                activeFileIndex = 0;
                isEditingBulkFile = false;
                
                // Reset UI
                document.getElementById('bulkUploadContent').style.display = 'none';
                document.querySelector('.upload-drop-zone').style.display = 'block';
                
                // Reset buttons
                if (uploadBtn) {
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload`;
                    uploadBtn.disabled = true;
                    uploadBtn.type = 'submit';
                    uploadBtn.onclick = null;
                }
                if (discardBtn) discardBtn.disabled = true;
                
                checkFormInput();
            } else {
                // Some files remain, adjust active index
                if (activeFileIndex >= bulkFiles.length) {
                    activeFileIndex = Math.max(0, bulkFiles.length - 1);
                }
                updateBulkUI();
            }
        }

        // Form field listeners for bulk mode
        let bulkFormListenersAdded = false;
        const formFieldNames = ['title', 'publication_date', 'edition', 'category_id', 'page_count', 'keywords', 'publisher', 'volume_issue', 'description', 'language_id'];

        function handleFormFieldChange(e) {
            if (isBulkMode && bulkFiles.length > 0) {
                const fieldName = e.target.name;
                const value = e.target.value;
                updateCurrentBulkFileData(fieldName, value);
                
                // Mark as edited and set timestamp
                if (bulkFiles[activeFileIndex]) {
                    bulkFiles[activeFileIndex].isEdited = true;
                    bulkFiles[activeFileIndex].lastEditTime = Date.now();
                }
                
                // Set editing flag and disable upload button
                isEditingBulkFile = true;
                if (uploadBtn) uploadBtn.disabled = true;
                
                // Update UI to show file status
                updateBulkUI();
            }
        }

        function addBulkFormListeners() {
            if (bulkFormListenersAdded) return;

            formFieldNames.forEach(name => {
                const input = document.querySelector(`[name="${name}"]`);
                if (input) {
                    input.addEventListener('change', handleFormFieldChange);
                    input.addEventListener('input', handleFormFieldChange);
                }
            });
            bulkFormListenersAdded = true;
        }

        function removeBulkFormListeners() {
            formFieldNames.forEach(name => {
                const input = document.querySelector(`[name="${name}"]`);
                if (input) {
                    input.removeEventListener('change', handleFormFieldChange);
                    input.removeEventListener('input', handleFormFieldChange);
                }
            });
            bulkFormListenersAdded = false;
        }

        function setActiveFile(idx) {
            isEditingBulkFile = false; // Reset editing flag when switching files
            if (uploadBtn) uploadBtn.disabled = false; // Re-enable upload button when switching away
            activeFileIndex = idx;
            loadBulkFileData(idx);
        }

        async function processBulkUpload() {
            if (bulkFiles.length === 0) return;

            uploadBtn.disabled = true;
            uploadBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Uploading...`;

            let uploadedCount = 0;
            let errorCount = 0;
            let firstUploadedId = null;

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
                        
                        // Extract ID from response if available
                        const idMatch = text.match(/"id":\s*(\d+)/);
                        if (idMatch && !firstUploadedId) firstUploadedId = idMatch[1];
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
                // Show success modal
                showUploadSuccessModal(firstUploadedId, true);
                // Redirect after a delay
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 3000);
            } else {
                alert(`Upload complete with ${errorCount} errors.`);
            }
        }

        // ========== KEYWORDS / TAGS FUNCTIONALITY ==========
        const keywordInput = document.getElementById('keywordInput');
        const addTagBtn = document.getElementById('addTagBtn');
        const tagsContainer = document.getElementById('tagsContainer');
        const hiddenKeywords = document.getElementById('hiddenKeywords');

        // Initialize tags from hidden input (if editing)
        let tags = [];
        if (hiddenKeywords.value) {
            tags = hiddenKeywords.value.split(',').map(t => t.trim()).filter(t => t);
            renderTags();
        }

        function renderTags() {
            tagsContainer.innerHTML = '';
            tags.forEach((tag, index) => {
                const badge = document.createElement('span');
                badge.className = 'badge';
                badge.style.cssText = 'background: #4C3939; color: white; padding: 6px 10px; border-radius: 6px; margin-right: 5px; font-weight: 500; font-size: 11px; margin-bottom: 5px; display: inline-flex; align-items: center;';
                badge.innerHTML = `${tag} <i class="bi bi-x ms-2" style="cursor: pointer;" onclick="removeTag(${index})"></i>`;
                tagsContainer.appendChild(badge);
            });
            hiddenKeywords.value = tags.join(', ');
        }

        function addTag() {
            const val = keywordInput.value.trim();
            if (val && !tags.includes(val)) {
                tags.push(val);
                renderTags();
                keywordInput.value = '';
            }
        }

        function removeTag(index) {
            tags.splice(index, 1);
            renderTags();
        }

        if (addTagBtn) {
            addTagBtn.addEventListener('click', addTag);
        }

        if (keywordInput) {
            keywordInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent form submission
                    addTag();
                }
            });
        }
        // ========== COVER PREVIEW & DRAG-DROP LOGIC ==========

        function updateCoverPreview(index) {
            if (!bulkFiles[index]) return;

            const hasDocuments = bulkFiles.some(f => ['pdf', 'mobi', 'epub', 'txt'].includes(f.ext));
            const mode = hasDocuments ? 'document' : 'image';

            const previewImg = document.getElementById('thumbnailPreview');
            const previewPlaceholder = document.getElementById('previewPlaceholder');
            const pContainer = document.querySelector('.col-lg-4 .content-card .flex-grow-1');

            if (mode === 'image') {
                // For images, use first image as thumbnail
                const coverFile = bulkFiles[0];
                if (coverFile) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (previewImg) {
                            previewImg.src = e.target.result;
                            previewImg.style.display = 'block';
                        }
                        if (previewPlaceholder) previewPlaceholder.style.display = 'none';
                        updatePreviewLabel('FIRST IMAGE USED AS THUMBNAIL', 'CHANGE THUMBNAIL');
                    };
                    reader.readAsDataURL(coverFile.file);
                }
            } else {
                // For documents - check if JPG/PNG file or has custom thumbnail
                const file = bulkFiles[index];
                
                // If the document file itself is an image (JPG/PNG)
                if (['jpg', 'jpeg', 'png', 'tiff', 'tif'].includes(file.ext)) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (previewImg) {
                            previewImg.src = e.target.result;
                            previewImg.style.display = 'block';
                        }
                        if (previewPlaceholder) previewPlaceholder.style.display = 'none';
                        updatePreviewLabel('IMAGE PREVIEW', 'CHANGE THUMBNAIL');
                    };
                    reader.readAsDataURL(file.file);
                } else if (file.thumbnail) {
                    // If user manually uploaded a thumbnail
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (previewImg) {
                            previewImg.src = e.target.result;
                            previewImg.style.display = 'block';
                        }
                        if (previewPlaceholder) previewPlaceholder.style.display = 'none';
                        updatePreviewLabel('CUSTOM THUMBNAIL UPLOADED', 'CHANGE THUMBNAIL');
                    };
                    reader.readAsDataURL(file.thumbnail);
                } else {
                    // Show generic document icon
                    if (previewImg) previewImg.style.display = 'none';
                    if (previewPlaceholder) previewPlaceholder.style.display = 'block';
                    updatePreviewLabel('ADD THUMBNAIL', 'UPLOAD THUMBNAIL');
                }
            }
        }

        function updatePreviewLabel(title, buttonText) {
            const container = document.querySelector('.col-lg-4 .content-card .flex-grow-1');
            if (!container) return;

            // Check for our custom label div
            let labelDiv = document.getElementById('dynamicPreviewLabel');

            // Hide default static elements if we haven't already
            const staticDefaults = container.querySelectorAll('.text-center.p-4');
            staticDefaults.forEach(el => el.style.display = 'none');

            if (!labelDiv) {
                labelDiv = document.createElement('div');
                labelDiv.id = 'dynamicPreviewLabel';
                labelDiv.className = 'mt-3 text-center';
                container.appendChild(labelDiv);
            }

            labelDiv.innerHTML = `
                 <h6 style="color: #4B5563; font-weight: 700; letter-spacing: 0.5px; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">${title}</h6>
                 <button type="button" class="btn btn-outline-dark btn-sm" onclick="document.getElementById('thumbnailInput').click()" 
                       style="border-radius: 4px; font-size: 10px; font-weight: 700; padding: 8px 16px; letter-spacing: 0.5px; border-color: #4C3939; color: #4C3939;">
                    ${buttonText}
                </button>
             `;
            labelDiv.style.display = 'block';
        }

        // Drag and Drop Logic
        let draggedItem = null;

        function handleDragStart(e, index) {
            draggedItem = index;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', index);
            e.target.closest('.page-order-item').style.opacity = '0.4';
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDragEnter(e) {
            e.target.closest('.page-order-item')?.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.target.closest('.page-order-item')?.classList.remove('drag-over');
        }

        function handleDrop(e, index) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }

            // Remove style
            document.querySelectorAll('.page-order-item').forEach(el => {
                el.style.opacity = '1';
                el.classList.remove('drag-over');
            });

            if (draggedItem !== null && draggedItem !== index) {
                // Reorder array
                const item = bulkFiles.splice(draggedItem, 1)[0];
                bulkFiles.splice(index, 0, item);

                // Update active index if needed
                if (activeFileIndex === draggedItem) activeFileIndex = index;

                updateBulkUI();
            }

            return false;
        }
    </script>

    <!-- Upload Success Modal -->
    <div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden;">
                <div class="modal-body text-center" style="padding: 40px 30px;">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        style="position: absolute; top: 15px; right: 15px;"></button>
                    <div
                        style="width: 80px; height: 80px; background: #22C55E; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px;">
                        <i class="bi bi-check-lg" style="font-size: 40px; color: white;"></i>
                    </div>
                    <h4 style="font-weight: 700; color: #333; margin-bottom: 10px;">Upload Success</h4>
                    <p style="color: #888; font-size: 14px; margin-bottom: 30px;">Item added successfully and is now
                        available for viewing</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn" id="viewUploadedBtn"
                            style="background: #f5f5f5; color: #666; padding: 12px 30px; border-radius: 10px; font-weight: 600; border: none;">
                            View
                        </button>
                        <button type="button" class="btn" data-bs-dismiss="modal"
                            style="background: #4C3939; color: white; padding: 12px 30px; border-radius: 10px; font-weight: 600; border: none;">
                            Okay
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Upload success modal handling
        let lastUploadedId = null;
        let isBulkUploadSuccess = false;

        function showUploadSuccessModal(uploadedId, isBulk = false) {
            lastUploadedId = uploadedId;
            isBulkUploadSuccess = isBulk;
            
            const modal = document.getElementById('uploadSuccessModal');
            const modalTitle = modal.querySelector('h4');
            const modalText = modal.querySelector('p');
            
            if (isBulk) {
                modalTitle.textContent = 'Bulk Upload Complete';
                modalText.textContent = `${bulkFiles.length} item(s) successfully added and are now available for viewing.`;
            } else {
                modalTitle.textContent = 'Upload Success';
                modalText.textContent = 'Item added successfully and is now available for viewing';
            }
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }

        document.getElementById('viewUploadedBtn')?.addEventListener('click', function () {
            if (lastUploadedId) {
                window.location.href = 'dashboard.php?view=' + lastUploadedId;
            } else {
                window.location.href = 'dashboard.php';
            }
        });

        // Show success modal if page reload after POST
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('success') === 'upload') {
                // Single file upload success
                const modalTitle = document.querySelector('#uploadSuccessModal h4');
                const modalText = document.querySelector('#uploadSuccessModal p');
                if (modalTitle) modalTitle.textContent = 'Upload Success';
                if (modalText) modalText.textContent = 'Document uploaded successfully and is now available for viewing.';

                const modal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'));
                modal.show();

                // Clean up URL
                history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('success') === 'edit') {
                // Update modal text for edit success
                const modalTitle = document.querySelector('#uploadSuccessModal h4');
                const modalText = document.querySelector('#uploadSuccessModal p');
                if (modalTitle) modalTitle.textContent = 'Update Success';
                if (modalText) modalText.textContent = 'Document updated successfully and is now available for viewing.';

                const modal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'));
                modal.show();

                // Clean up URL
                history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>

</html>